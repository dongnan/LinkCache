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

use linkcache\interfaces\driver\Base;
use linkcache\interfaces\driver\Lock;
use linkcache\interfaces\driver\Incr;
use linkcache\interfaces\driver\Multi;

/**
 * SSDB
 */
class SSDB implements Base, Lock, Incr, Multi {

    use \linkcache\traits\CacheDriver;

    /**
     * SimpleSSDB 对象
     * @var \SimpleSSDB 
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
        self::import('SSDB.php');
        if (!class_exists('\\SimpleSSDB')) {
            throw new \Exception("class 'SimpleSSDB' is not exists!");
        }
        $this->init($config);
        //最大重连次数
        if (isset($config['maxReConnected'])) {
            $this->maxReConnected = (int) $config['maxReConnected'];
        }
        $this->connect();
    }

    public function __set($name, $value) {
        return $this->handler->set($name, $value);
    }

    public function __get($name) {
        return $this->handler->get($name);
    }

    public function __unset($name) {
        $this->handler->del($name);
    }

    /**
     * Call the SSDB handler's method
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
     * 连接ssdb
     */
    private function connect() {
        $host = !empty($this->config['host']) ? $this->config['host'] : '127.0.0.1';
        $port = !empty($this->config['port']) ? $this->config['port'] : 8888;
        $password = !empty($this->config['password']) ? $this->config['password'] : '';
        $timeoutms = !empty($this->config['timeoutms']) ? $this->config['timeoutms'] : 1000;
        try {
            $this->handler = new \SimpleSSDB($host, $port, $timeoutms);
            $this->isConnected = true;
        } catch (\SSDBException $ex) {
            self::exception($ex);
            $this->isConnected = false;
        }
        if ($this->isConnected) {
            if (!empty($password)) {
                $this->handler->auth($password);
            }
        }
    }

    /**
     * 获取handler(SimpleSSDB实例)
     * @return \SimpleSSDB
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
                if ($this->handler->ping()) {
                    $this->isConnected = true;
                }
            } catch (\SSDBException $ex) {
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
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        try {
            if ($time > 0) {
                return $this->handler->setx($key, self::setValue($value), $time);
            }
            return $this->handler->set($key, self::setValue($value));
        } catch (\SSDBException $ex) {
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
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
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
        } catch (\SSDBException $ex) {
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
            return $this->getValue($this->handler->get($key));
        } catch (\SSDBException $ex) {
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
            return $this->handler->del($key);
        } catch (\SSDBException $ex) {
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
        } catch (\SSDBException $ex) {
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
            //不存在
            if (!$this->handler->exists($key)) {
                return -2;
            }
            return $this->handler->ttl($key);
        } catch (\SSDBException $ex) {
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
            $value = $this->handler->get($key);
            if (is_null($value) || $value === false) {
                return false;
            }
            //$time不大于0，则永不过期
            if ($time <= 0) {
                return $this->handler->set($key, $value);
            } else {
                return $this->handler->expire($key, $time);
            }
        } catch (\SSDBException $ex) {
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
            $value = $this->handler->get($key);
            if (is_null($value) || $value === false) {
                return false;
            }
            return $this->handler->set($key, $value);
        } catch (\SSDBException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
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
        try {
            return $this->handler->setx(self::lockKey($key), 1, $time);
        } catch (\SSDBException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 对指定键名解锁
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否加锁
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function isLock($key) {
        try {
            return $this->handler->exists(self::lockKey($key));
        } catch (\SSDBException $ex) {
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
            return $this->handler->incr($key, $step);
        } catch (\SSDBException $ex) {
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
            $value = $this->handler->get($key);
            if (!is_numeric($value)) {
                return false;
            }
            if ($this->set($key, $value += $float)) {
                return $value;
            }
            return false;
        } catch (\SSDBException $ex) {
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
            return $this->handler->incr($key, -$step);
        } catch (\SSDBException $ex) {
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
            return $this->handler->multi_set($sets);
        } catch (\SSDBException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 批量设置键值(当键名不存在时)
     * 只有当键值全部设置成功时,才返回true,否则返回false并尝试回滚
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSetNX($sets) {
        try {
            $keys = [];
            $status = true;
            foreach ($sets as $key => $value) {
                $status = $this->handler->setnx($key, $value);
                if ($status) {
                    $keys[] = $key;
                } else {
                    break;
                }
            }
            //如果失败，尝试回滚，但不保证成功
            if (!$status) {
                foreach ($keys as $key) {
                    $this->handler->del($key);
                }
            }
            return $status;
        } catch (\SSDBException $ex) {
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
            $ret = [];
            $values = $this->handler->multi_get($keys);
            foreach ($keys as $key) {
                $ret[$key] = isset($values[$key]) ? $values[$key] : false;
            }
            return $ret;
        } catch (\SSDBException $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

}
