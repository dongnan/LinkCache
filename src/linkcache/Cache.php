<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        https://github.com/dongnan/linkcache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcache;

/**
 * 缓存类
 */
class Cache {

    /**
     * 缓存驱动
     */
    protected $driver;

    /**
     * 默认配置
     * @var array 
     */
    static protected $config = [
        'default' => '',
        //当前缓存驱动失效时，采用的备份驱动
        'fallback' => 'files',
        'memcache' => [
            //host,port,weight,persistent,timeout,retry_interval,status,failure_callback
            'servers' => [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 1, 'persistent' => true, 'timeout' => 1, 'retry_interval' => 15, 'status' => true],
            ],
            'compress' => ['threshold' => 2000, 'min_saving' => 0.2],
        ],
        'memcached' => [
            'servers' => [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 1],
            ],
            //参考 Memcached::setOptions
            'options' => [],
        ],
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => '',
            'timeout' => ''
        ],
        'ssdb' => [
            'host' => '127.0.0.1',
            'port' => 8888,
            'password' => '',
            'timeout' => ''
        ],
    ];

    /**
     * 缓存驱动实例集合
     * @var array
     */
    static private $_drivers = [];

    /**
     * 缓存类实例集合
     * @var array 
     */
    static private $_instances = [];

    /**
     * 构造缓存
     * @param string $type  缓存驱动类型
     * @param array $config 驱动配置
     * @throws \Exception   异常
     */
    public function __construct($type = '', $config = []) {
        $key = $type . md5(serialize($config));
        if (!isset(self::$_drivers[$key])) {
            $class = strpos($type, '\\') ? $type : 'linkcache\\drivers\\' . ucwords(strtolower($type));
            if (class_exists($class)) {
                if (!empty($type) && isset(self::$config[$type])) {
                    $config = array_merge(self::$config[$type], $config);
                }
                self::$_drivers[$key] = new $class($config);
            } else {
                throw new \Exception("{$class} is not exists!");
            }
        }
        $this->driver = self::$_drivers[$key];
    }

    /**
     * 获取缓存驱动实例
     * @return linkcache\CacheDriverInterface
     */
    public function getDriver() {
        return $this->driver;
    }

    /**
     * 设置默认配置
     * @param array $config 配置信息
     */
    static public function setConfig($config) {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * 获取缓存类实例
     * @param string $type  缓存驱动类型
     * @param array $config 驱动配置
     * @return Cache        缓存类实例
     * @throws \Exception   异常
     */
    static public function getInstance($type = '', $config = []) {
        $key = $type . md5(serialize($config));
        if (!isset(self::$_instances[$key])) {
            self::$_instances[$key] = new Cache($type, $config);
        }
        return self::$_instances[$key];
    }

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        return $this->driver->set($key, $value, $time);
    }

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1) {
        return $this->driver->setnx($key, $value, $time);
    }

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed        键值
     */
    public function get($key) {
        return $this->driver->get($key);
    }

    /**
     * 二次获取键值,在get方法没有获取到值时，调用此方法将有可能获取到
     * 此方法是为了防止惊群现象发生,配合lock和isLock方法,设置新的缓存
     * @param string $key   键名
     * @return mixed        键值
     */
    public function getTwice($key) {
        return $this->driver->getTwice($key);
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
        return $this->driver->lock($key, $time);
    }

    /**
     * 对指定键名解锁
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否加锁
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function isLock($key) {
        return $this->driver->isLock($key);
    }

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function del($key) {
        return $this->driver->del($key);
    }

    /**
     * 是否存在键值
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function has($key) {
        return $this->driver->has($key);
    }

    /**
     * 获取生存剩余时间
     * @param string $key   键名
     * @return int          生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在
     */
    public function ttl($key) {
        return $this->driver->ttl($key);
    }

    /**
     * 设置过期时间
     * @param string $key   键名
     * @param int $time     过期时间(单位:秒)
     * @return boolean      是否成功
     */
    public function expire($key, $time) {
        return $this->driver->expire($key, $time);
    }

    /**
     * 设置过期时间戳
     * @param string $key   键名
     * @param int $time     过期时间戳
     * @return boolean      是否成功
     */
    public function expireAt($key, $time) {
        $difftime = $time - time();
        if ($difftime) {
            return $this->driver->expire($key, $difftime);
        } else {
            return $this->driver->del($key);
        }
    }

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key) {
        return $this->driver->persist($key);
    }

    /**
     * 递增
     * @param string $key   键名
     * @param int $step     递增步长
     * @return boolean      是否成功
     */
    public function incr($key, $step = 1) {
        if (method_exists($this->driver, 'incr')) {
            return $this->driver->incr($key, $step);
        } else {
            $value = $this->driver->get($key);
            if (!is_int($value) || !is_int($step)) {
                return false;
            }
            return $this->driver->set($key, $value + $step);
        }
    }

    /**
     * 浮点数递增
     * @param string $key   键名
     * @param float $float  递增步长
     * @return boolean      是否成功
     */
    public function incrByFloat($key, $float) {
        if (method_exists($this->driver, 'incrByFloat')) {
            return $this->driver->incrByFloat($key, $float);
        } else {
            $value = $this->driver->get($key);
            if (!is_numeric($value) || !is_numeric($float)) {
                return false;
            }
            return $this->driver->set($key, $value + $float);
        }
    }

    /**
     * 递减
     * @param string $key   键名
     * @param int $step     递减步长
     * @return boolean      是否成功
     */
    public function decr($key, $step = 1) {
        if (method_exists($this->driver, 'decr')) {
            return $this->driver->decr($key, $step);
        } else {
            $value = $this->driver->get($key);
            if (!is_int($value) || !is_int($step)) {
                return false;
            }
            return $this->driver->set($key, $value - $step);
        }
    }

    /**
     * 批量设置键值
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSet($sets) {
        if (method_exists($this->driver, 'mSet')) {
            return $this->driver->mSet($sets);
        } else {
            $oldSets = [];
            $status = true;
            foreach ($sets as $key => $value) {
                $oldSets[$key] = $this->driver->get($key);
                $status = $this->driver->set($key, $value);
                if (!$status) {
                    break;
                }
            }
            //如果失败，尝试回滚，但不保证成功
            if (!$status) {
                foreach ($oldSets as $key => $value) {
                    if ($value === false) {
                        $this->driver->del($key);
                    } else {
                        $this->driver->set($key, $value);
                    }
                }
            }
            return $status;
        }
    }

    /**
     * 批量设置键值(当键名不存在时)
     * 只有当键值全部设置成功时,才返回true,否则返回false并尝试回滚
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSetNX($sets) {
        if (method_exists($this->driver, 'mSetNX')) {
            return $this->driver->mSetNX($sets);
        } else {
            $newSets = [];
            $status = true;
            foreach ($sets as $key => $value) {
                $status = $this->driver->setnx($key, $value);
                if ($status) {
                    $newSets[$key] = $value;
                } else {
                    break;
                }
            }
            //如果失败，尝试回滚，但不保证成功
            if (!$status) {
                foreach ($newSets as $key => $value) {
                    if ($value === false) {
                        $this->driver->del($key);
                    }
                }
            }
            return $status;
        }
    }

    /**
     * 批量获取键值
     * @param array $keys   键名数组
     * @return array        键值数组
     */
    public function mGet($keys) {
        if (method_exists($this->driver, 'mGet')) {
            return $this->driver->mGet($keys);
        } else {
            $values = [];
            foreach ($keys as $key) {
                $values[] = $this->driver->get($key);
            }
            return $values;
        }
    }

    public function __set($name, $value) {
        return $this->driver->set($name, $value);
    }

    public function __get($name) {
        return $this->driver->get($name);
    }

    public function __unset($name) {
        $this->driver->del($name);
    }

    /**
     * Call the cache driver's method
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args) {

        if (method_exists($this->driver, $method)) {
            return call_user_func_array(array($this->driver, $method), $args);
        } else {
            throw new \Exception(__CLASS__ . ":{$method} is not exists!");
        }
    }

}
