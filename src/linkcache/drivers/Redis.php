<?php

/**
 * LinkCache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcache\drivers;

use linkcache\CacheDriverInterface;
use linkcache\CacheDriverExtendInterface;
use \RedisException;

/**
 * Redis
 */
class Redis implements CacheDriverInterface, CacheDriverExtendInterface {

    use \linkcache\CacheDriverTrait;

    /**
     * 配置信息
     * @var array 
     */
    private $config = [];

    /**
     * Redis 对象
     * @var \Redis 
     */
    private $handler;

    /**
     * 是否连接redis
     * @var boolean 
     */
    private $isConnected = false;

    /**
     * 是否使用备用缓存
     * @var boolean
     */
    private $fallback = false;

    /**
     * 构造函数
     * @param array $config 配置
     * @throws \Exception   异常
     */
    public function __construct($config = []) {
        if (!extension_loaded('redis')) {
            throw new \Exception("redis extension is not exists!");
        }
        $this->config = $config;
        $this->connect();
    }

    /**
     * 连接redis
     */
    private function connect() {
        $host = isset($this->config['host']) ? $this->config['host'] : '127.0.0.1';
        $port = isset($this->config['port']) ? $this->config['port'] : 6379;
        $password = isset($this->config['password']) ? $this->config['password'] : '';
        $database = isset($this->config['database']) ? $this->config['database'] : 0;
        $timeout = isset($this->config['timeout']) ? $this->config['timeout'] : 1;
        $persistent = isset($this->config['persistent']) ? $this->config['persistent'] : false;
        $func = $persistent ? 'pconnect' : 'connect';
        $this->handler = new \Redis();
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
     * 检查连接状态
     * @return boolean
     */
    private function checkConnection() {
        if (!$this->isConnected && !$this->fallback) {
            try {
                if ($this->handler->ping() == '+PONG') {
                    $this->isConnected = true;
                }
            } catch (RedisException $ex) {
                $this->handler->connect();
            }
            if (!$this->isConnected) {
                $this->fallback = true;
            }
        }
        return $this->isConnected;
    }

    /**
     * 获取handler(redis实例)
     * @return \Redis
     */
    public function getHandler() {
        return $this->handler;
    }

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        if ($this->checkConnection()) {
            $value = self::setValue($value);
            try {
                if ($time >= 0) {
                    $ret = $this->handler->multi()
                            ->setex($key, $time * 2, $value) //两倍时间，防止惊群发生
                            ->setex(self::timeKey($key), $time * 2, $time + time())
                            ->exec();
                    return $ret !== false ? true : false;
                }
                //如果存在timeKey且已过期，则删除timeKey
                $expireTime = $this->handler->get(self::timeKey($key));
                if ($expireTime && $expireTime - time() <= 0) {
                    $this->handler->del(self::timeKey($key));
                }
                return $this->handler->set($key, $value);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->set($key, $value, $time);
            }
        } else {
            return $this->backup()->set($key, $value, $time);
        }
    }

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1) {
        if ($this->checkConnection()) {
            $value = self::setValue($value);
            try {
                if ($time >= 0) {
                    if ($this->handler->setnx($key, $value)) {
                        $ret = $this->handler->multi()
                                ->expire($key, $time * 2) //两倍时间，防止惊群发生
                                ->setex(self::timeKey($key), $time * 2, $time + time())
                                ->exec();
                        //如果执行失败，则尝试删除key
                        if ($ret === false) {
                            $this->handler->del($key);
                        }
                        return $ret !== false ? true : false;
                    }
                    return false;
                }
                return $this->handler->setnx($key, $value);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->setnx($key, $value, $time);
            }
        } else {
            return $this->backup()->setnx($key, $value, $time);
        }
    }

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed        键值
     */
    public function get($key) {
        if ($this->checkConnection()) {
            try {
                $expireTime = $this->handler->get(self::timeKey($key));
                //如果过期，则返回false
                if ($expireTime && $expireTime - time() <= 0) {
                    return false;
                }
                $value = $this->handler->get($key);
                return $this->getValue($value);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->get($key);
            }
        } else {
            return $this->backup()->get($key);
        }
    }

    /**
     * 二次获取键值,在get方法没有获取到值时，调用此方法将有可能获取到
     * 此方法是为了防止惊群现象发生,配合lock和isLock方法,设置新的缓存
     * @param string $key   键名
     * @return mixed        键值
     */
    public function getTwice($key) {
        if ($this->checkConnection()) {
            try {
                $value = $this->handler->get($key);
                return $this->getValue($value);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->getTwice($key);
            }
        } else {
            return $this->backup()->getTwice($key);
        }
    }

    /**
     * 对指定键名加锁（此锁并不对键值做修改限制,仅为键名的锁标记）
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,先判断键名是否加锁,
     * 如果已加锁,则不获取新值;如果未加锁,则获取新值,设置新的缓存
     * @param string $key   键名
     * @param int $time     加锁时间
     * @return boolean      是否成功
     */
    public function lock($key, $time = 60) {
        if ($this->checkConnection()) {
            try {
                return $this->handler->setex(self::lockKey($key), $time, 1);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->lock($key, $time);
            }
        } else {
            return $this->backup()->lock($key, $time);
        }
    }

    /**
     * 对指定键名解锁
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否加锁
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function isLock($key) {
        if ($this->checkConnection()) {
            try {
                return $this->handler->exists(self::lockKey($key));
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->isLock($key);
            }
        } else {
            return $this->backup()->isLock($key);
        }
    }

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function del($key) {
        if ($this->checkConnection()) {
            try {
                $this->handler->del(self::timeKey($key));
                return $this->handler->del($key);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->del($key);
            }
        } else {
            return $this->backup()->del($key);
        }
    }

    /**
     * 是否存在键值
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function has($key) {
        if ($this->checkConnection()) {
            try {
                return $this->handler->exists($key);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->has($key);
            }
        } else {
            return $this->backup()->has($key);
        }
    }

    /**
     * 获取生存剩余时间
     * @param string $key   键名
     * @return int          生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在
     */
    public function ttl($key) {
        if ($this->checkConnection()) {
            try {
                if ($this->handler->exists(self::timeKey($key))) {
                    $time = time();
                    $expireTime = $this->handler->get(self::timeKey($key));
                    if ($expireTime) {
                        return $expireTime > $time ? $expireTime - $time : -2;
                    }
                }
                return $this->handler->ttl($key);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->ttl($key);
            }
        } else {
            return $this->backup()->ttl($key);
        }
    }

    /**
     * 设置过期时间
     * @param string $key   键名
     * @param int $time     过期时间(单位:秒)
     * @return boolean      是否成功
     */
    public function expire($key, $time) {
        if ($this->checkConnection()) {
            try {
                $ret = $this->handler->multi()
                        ->expire($key, $time * 2) //两倍时间，防止惊群发生
                        ->setex(self::timeKey($key), $time * 2, $time + time())
                        ->exec();
                return $ret !== false ? true : false;
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->expire($key, $time);
            }
        } else {
            return $this->backup()->expire($key, $time);
        }
    }

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key) {
        if ($this->checkConnection()) {
            try {
                if ($this->handler->persist($key)) {
                    $this->handler->del(self::timeKey($key));
                    return true;
                }
                return false;
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->persist($key);
            }
        } else {
            return $this->backup()->persist($key);
        }
    }

    /**
     * 递增
     * @param string $key   键名
     * @param int $step     递增步长
     * @return boolean      是否成功
     */
    public function incr($key, $step = 1) {
        if ($this->checkConnection()) {
            if (!is_int($step)) {
                return false;
            }
            try {
                return $this->handler->incrBy($key, $step);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->incr($key, $step);
            }
        } else {
            return $this->backup()->incr($key, $step);
        }
    }

    /**
     * 浮点数递增
     * @param string $key   键名
     * @param float $float  递增步长
     * @return boolean      是否成功
     */
    public function incrByFloat($key, $float) {
        if ($this->checkConnection()) {
            if (!is_numeric($float)) {
                return false;
            }
            try {
                return $this->handler->incrByFloat($key, $float);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->incrByFloat($key, $float);
            }
        } else {
            return $this->backup()->incrByFloat($key, $float);
        }
    }

    /**
     * 递减
     * @param string $key   键名
     * @param int $step     递减步长
     * @return boolean      是否成功
     */
    public function decr($key, $step = 1) {
        if ($this->checkConnection()) {
            if (!is_int($step)) {
                return false;
            }
            try {
                return $this->handler->decrBy($key, $step);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->decr($key, $step);
            }
        } else {
            return $this->backup()->decr($key, $step);
        }
    }

    /**
     * 批量设置键值
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSet($sets) {
        if ($this->checkConnection()) {
            try {
                return $this->handler->mset($sets);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->mSet($sets);
            }
        } else {
            return $this->backup()->mSet($sets);
        }
    }

    /**
     * 批量设置键值(当键名不存在时)
     * 只有当键值全部设置成功时,才返回true,否则返回false并尝试回滚
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSetNX($sets) {
        if ($this->checkConnection()) {
            try {
                return $this->handler->msetnx($sets);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->mSetNX($sets);
            }
        } else {
            return $this->backup()->mSetNX($sets);
        }
    }

    /**
     * 批量获取键值
     * @param array $keys   键名数组
     * @return array        键值数组
     */
    public function mGet($keys) {
        if ($this->checkConnection()) {
            try {
                return $this->handler->mget($keys);
            } catch (RedisException $ex) {
                //连接状态置为false
                $this->isConnected = false;
                return $this->backup()->mGet($keys);
            }
        } else {
            return $this->backup()->mGet($keys);
        }
    }

}
