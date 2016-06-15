<?php

/**
 * LinkCache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
 */

namespace linkcache\drivers;

use linkcache\interfaces\driver\Base;
use linkcache\interfaces\driver\Lock;
use linkcache\interfaces\driver\Incr;
use linkcache\interfaces\driver\Multi;
use \RedisException;

/**
 * Redis
 */
class Redis implements Base, Lock, Incr, Multi {

    use \linkcache\traits\CacheDriver;

    /**
     * Redis 对象
     * @var \Redis 
     */
    private $handler;

    /**
     * 是否连接server
     * @var boolean 
     */
    private $isConnected = false;

    /**
     * 重连次数
     * @var int
     */
    private $reConnected = 0;

    /**
     * 最大重连次数,默认为3次
     * @var int
     */
    private $maxReConnected = 3;

    /**
     * 构造函数
     * @param array $config 配置
     * @throws \Exception   异常
     */
    public function __construct($config = []) {
        if (!extension_loaded('redis')) {
            throw new \Exception("redis extension is not exists!");
        }
        $this->handler = new \Redis();
        $this->init($config);
        //最大重连次数
        if (isset($config['maxReConnected'])) {
            $this->maxReConnected = (int) $config['maxReConnected'];
        }
        $this->connect();
    }

    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    public function __get($name) {
        return $this->get($name);
    }

    public function __unset($name) {
        return $this->del($name);
    }

    /**
     * Call the redis handler's method
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args) {
        if (method_exists($this->handler, $method)) {
            return call_user_func_array(array($this->handler, $method), $args);
        } else {
            throw new \Exception(__CLASS__ . ":{$method} is not exists!");
        }
    }

    /**
     * 连接redis
     */
    private function connect() {
        $host = !empty($this->config['host']) ? $this->config['host'] : '127.0.0.1';
        $port = !empty($this->config['port']) ? $this->config['port'] : 6379;
        $password = !empty($this->config['password']) ? $this->config['password'] : '';
        $database = !empty($this->config['database']) ? $this->config['database'] : 0;
        $timeout = !empty($this->config['timeout']) ? $this->config['timeout'] : 1;
        $persistent = isset($this->config['persistent']) ? $this->config['persistent'] : false;
        $func = $persistent ? 'pconnect' : 'connect';
        if (empty($timeout)) {
            $this->isConnected = $this->handler->$func($host, $port);
        } else {
            $this->isConnected = $this->handler->$func($host, $port, $timeout);
        }
        if ($this->isConnected) {
            if (!empty($password)) {
                $this->handler->auth($password);
            }
            if ($database) {
                $this->handler->select($database);
            }
        }
    }

    /**
     * 获取handler(redis实例)
     * @return \Redis
     */
    public function getHandler() {
        return $this->handler;
    }

    /**
     * 检查驱动是否可用
     * @return boolean      是否可用
     */
    public function checkDriver() {
        if (!$this->isConnected && $this->reConnected < $this->maxReConnected) {
            try {
                if ($this->handler->ping() == '+PONG') {
                    $this->isConnected = true;
                }
            } catch (RedisException $ex) {
                self::exception($ex);
                $this->connect();
            }
            if (!$this->isConnected) {
                $this->reConnected++;
            }
            //如果重连成功,重连次数置为0
            else {
                $this->reConnected = 0;
            }
        }
        return $this->isConnected;
    }

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,<=0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        try {
            if ($time > 0) {
                return $this->handler->setex($key, $time, self::setValue($value));
            }
            return $this->handler->set($key, self::setValue($value));
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,<=0则设置为永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1) {
        try {
            if ($time > 0) {
                if ($this->handler->setnx($key, self::setValue($value))) {
                    $ret = $this->handler->expire($key, $time);
                    //如果执行失败，则尝试删除key
                    if ($ret === false) {
                        $this->handler->del($key);
                    }
                    return $ret !== false ? true : false;
                }
                return false;
            }
            return $this->handler->setnx($key, self::setValue($value));
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 设置键值，将自动延迟过期;<br>
     * 此方法用于缓存对过期要求宽松的数据;<br>
     * 使用此方法设置缓存配合getDE方法可以有效防止惊群现象发生
     * @param string $key    键名
     * @param mixed $value   键值
     * @param int $time      过期时间，<=0则设置为永不过期
     * @param int $delayTime 延迟过期时间，如果未设置，则使用配置中的设置
     * @return boolean       是否成功
     */
    public function setDE($key, $value, $time, $delayTime = null) {
        try {
            if ($time > 0) {
                $delayTime = $this->getDelayTime($delayTime);
                $ret = $this->handler->multi()
                        ->setex($key, $time + $delayTime, self::setValue($value))
                        ->setex(self::timeKey($key), $time + $delayTime, self::setValue(['expire_time' => time() + $time + $delayTime, 'delay_time' => $delayTime]))
                        ->exec();
                return $ret !== false ? true : false;
            }
            $timeValue = self::getValue($this->handler->get(self::timeKey($key)));
            //已过期或 time<=0 时
            if ($timeValue !== false && (self::isExpiredDE($timeValue) || $time <= 0)) {
                $this->handler->del($key, self::timeKey($key));
            }
            return $this->handler->set($key, self::setValue($value));
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed|false  键值,失败返回false
     */
    public function get($key) {
        try {
            return self::getValue($this->handler->get($key));
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 获取延迟过期的键值，与setDE配合使用;<br>
     * 此方法用于获取setDE设置的缓存数据;<br>
     * 当isExpired为true时，说明key已经过期，需要更新;<br>
     * 更新数据时配合isLock和lock方法，防止惊群现象发生
     * @param string $key       键名
     * @param boolean $isExpired 是否已经过期
     * @return mixed|false      键值,失败返回false
     */
    public function getDE($key, &$isExpired = null) {
        try {
            $timeValue = self::getValue($this->handler->get(self::timeKey($key)));
            $isExpired = self::isExpiredDE($timeValue);
            return self::getValue($this->handler->get($key));
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function del($key) {
        try {
            $ret = $this->handler->del($key, self::timeKey($key));
            if ($ret > 0 || ($ret === 0 && !$this->handler->exists($key))) {
                return true;
            }
            return false;
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 是否存在键值
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function has($key) {
        try {
            return $this->handler->exists($key);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 判断延迟过期的键值理论上是否存在
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function hasDE($key) {
        try {
            $status = $this->handler->exists($key);
            if ($status) {
                $timeValue = self::getValue($this->handler->get(self::timeKey($key)));
                //不存在或已过期
                if ($timeValue !== false && self::isExpiredDE($timeValue)) {
                    return false;
                }
                return true;
            }
            return false;
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 获取生存剩余时间
     * @param string $key   键名
     * @return int|false    生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在,失败返回false
     */
    public function ttl($key) {
        try {
            return $this->handler->ttl($key);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 获取延迟过期的键值理论生存剩余时间
     * @param string $key   键名
     * @return int|false    生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在,失败返回false
     */
    public function ttlDE($key) {
        try {
            $ttl = $this->handler->ttl($key);
            if ($ttl > 0) {
                $value = self::getValue($this->handler->get(self::timeKey($key)));
                if (isset($value['expire_time']) && isset($value['delay_time'])) {
                    $ttl = $value['expire_time'] - $value['delay_time'] - time();
                    return $ttl > 0 ? $ttl : -2;
                }
            }
            return $ttl;
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 设置过期时间
     * @param string $key   键名
     * @param int $time     过期时间(单位:秒)。不大于0，则设为永不过期
     * @return boolean      是否成功
     */
    public function expire($key, $time) {
        try {
            //$time不大于0，则永不过期
            if ($time <= 0) {
                return $this->handler->persist($key);
            } else {
                return $this->handler->expire($key, $time);
            }
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 以延迟过期的方式设置过期时间
     * @param string $key    键名
     * @param int $time      过期时间(单位:秒)。不大于0，则设为永不过期
     * @param int $delayTime 延迟过期时间，如果未设置，则使用配置中的设置
     * @return boolean       是否成功
     */
    public function expireDE($key, $time, $delayTime = null) {
        try {
            //$time不大于0，则永不过期
            if ($time <= 0) {
                $this->handler->del(self::timeKey($key));
                return $this->handler->persist($key);
            }
            if ($this->hasDE($key)) {
                $delayTime = $this->getDelayTime($delayTime);
                $ret = $this->handler->multi()
                        ->expire($key, $time + $delayTime)
                        ->setex(self::timeKey($key), $time + $delayTime, self::setValue(['expire_time' => time() + $time + $delayTime, 'delay_time' => $delayTime]))
                        ->exec();
                return $ret !== false ? true : false;
            }
            return false;
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key) {
        try {
            $this->handler->del(self::timeKey($key));
            return $this->handler->persist($key);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 对指定键名设置锁标记（此锁并不对键值做修改限制,仅为键名的锁标记）;<br>
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,先判断键名是否有锁标记,<br>
     * 如果已加锁,则不获取新值;<br>
     * 如果未加锁,则先设置锁，若设置失败说明锁已存在，若设置成功则获取新值,设置新的缓存
     * @param string $key   键名
     * @param int $time     加锁时间
     * @return boolean      是否成功
     */
    public function lock($key, $time = 60) {
        try {
            return $this->setnx(self::lockKey($key), 1, $time);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 判断键名是否有锁标记;<br>
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否有锁标记
     * @param string $key   键名
     * @return boolean      是否加锁
     */
    public function isLock($key) {
        try {
            return $this->handler->exists(self::lockKey($key));
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 对指定键名移除锁标记
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function unlock($key) {
        try {
            return $this->del(self::lockKey($key));
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 递增
     * @param string $key   键名
     * @param int $step     递增步长
     * @return int|false    递增后的值,失败返回false
     */
    public function incr($key, $step = 1) {
        if (!is_int($step)) {
            return false;
        }
        try {
            return $this->handler->incrBy($key, $step);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 浮点数递增
     * @param string $key   键名
     * @param float $float  递增步长
     * @return float|false  递增后的值,失败返回false
     */
    public function incrByFloat($key, $float) {
        if (!is_numeric($float)) {
            return false;
        }
        try {
            return $this->handler->incrByFloat($key, $float);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 递减
     * @param string $key   键名
     * @param int $step     递减步长
     * @return int|false    递减后的值,失败返回false
     */
    public function decr($key, $step = 1) {
        if (!is_int($step)) {
            return false;
        }
        try {
            return $this->handler->incrBy($key, -$step);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 批量设置键值
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSet($sets) {
        try {
            foreach ($sets as &$value) {
                $value = self::setValue($value);
            }
            return $this->handler->mset($sets);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 批量设置键值(当键名不存在时);<br>
     * 只有当键值全部设置成功时,才返回true,否则返回false并尝试回滚
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSetNX($sets) {
        try {
            foreach ($sets as &$value) {
                $value = self::setValue($value);
            }
            return $this->handler->msetnx($sets);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 批量获取键值
     * @param array $keys   键名数组
     * @return array|false  键值数组,失败返回false
     */
    public function mGet($keys) {
        try {
            $values = $this->handler->mget($keys);
            if (!$values) {
                return false;
            }
            foreach ($values as &$value) {
                $value = self::getValue($value);
            }
            return array_combine($keys, $values);
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 批量判断键值是否存在
     * @param array $keys   键名数组
     * @return array  返回存在的keys
     */
    public function mHas($keys) {
        try {
            $hasKeys = [];
            foreach ($keys as $key) {
                if ($this->handler->exists($key)) {
                    $hasKeys[] = $key;
                }
            }
            return $hasKeys;
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 批量删除键值
     * @param array $keys   键名数组
     * @return boolean  是否成功
     */
    public function mDel($keys) {
        try {
            $ret = $this->handler->del($keys);
            if ($ret > 0) {
                return true;
            } elseif ($ret === 0) {
                $hasKeys = $this->mHas($keys);
                //在 PHP 5.5 之前,empty() 仅支持变量;任何其他东西将会导致一个解析错误
                if (empty($hasKeys) && is_array($hasKeys)) {
                    return true;
                }
            }
            return false;
        } catch (RedisException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

}
