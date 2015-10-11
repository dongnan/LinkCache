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
use \Exception;

/**
 * Memcached
 */
class Memcached implements Base, Lock, Incr, Multi {

    use \linkcache\traits\CacheDriver;

    /**
     * Memcached 对象
     * @var \Memcached 
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
        if (!extension_loaded('memcached')) {
            throw new \Exception("memcached extension is not exists!");
        }
        $this->handler = new \Memcached();
        $this->init($config);
        //最大重连次数
        if (isset($config['maxReConnected'])) {
            $this->maxReConnected = (int) $config['maxReConnected'];
        }
        $this->initServers();
    }

    /**
     * 初始化servers
     */
    private function initServers() {
        if (empty($this->config['servers'])) {
            $servers = [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 1],
            ];
        } else {
            $servers = $this->config['servers'];
        }
        foreach ($servers as $server) {
            $host = isset($server['host']) ? $server['host'] : '127.0.0.1';
            $port = isset($server['port']) ? $server['port'] : 11211;
            $weight = isset($server['weight']) ? $server['weight'] : 0;
            $this->handler->addserver($host, $port, $weight);
        }
        if (!empty($this->config['options'])) {
            $this->handler->setOptions($this->config['options']);
        }
        //如果获取服务器池的统计信息返回false,说明服务器池中有不可用服务器
        if ($this->handler->getStats() === false) {
            $this->isConnected = false;
        } else {
            $this->isConnected = true;
        }
    }

    /**
     * 检查连接状态
     * @return boolean
     */
    public function checkDriver() {
        if (!$this->isConnected && $this->reConnected < $this->maxReConnected) {
            if ($this->handler->getStats() !== false) {
                $this->isConnected = true;
            } else {
                $this->handler->initServers();
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
     * 获取handler(Memcached实例)
     * @return \Memcached
     */
    public function getHandler() {
        return $this->handler;
    }

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        $value = self::setValue($value);
        try {
            if ($time > 0) {
                return $this->handler->setMulti([$key => $value, self::timeKey($key) => $time + time()], time() + $time * 2);
            }
            //如果存在timeKey且已过期，则删除timeKey
            $expireTime = $this->handler->get(self::timeKey($key));
            if ($expireTime > 0 && $expireTime <= time() || $time == 0) {
                $this->handler->delete(self::timeKey($key));
            }
            return $this->handler->set($key, $value);
        } catch (Exception $ex) {
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
        $value = self::setValue($value);
        try {
            if ($time > 0) {
                if ($this->handler->add($key, $value, time() + $time * 2)) {
                    $ret = $this->handler->set(self::timeKey($key), $time + time(), time() + $time * 2);
                    //如果执行失败，则尝试删除key
                    if ($ret === false) {
                        $this->handler->delete($key);
                    }
                    return $ret !== false ? true : false;
                }
                return false;
            }
            return $this->handler->add($key, $value);
        } catch (Exception $ex) {
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
            $expireTime = $this->handler->get(self::timeKey($key));
            //如果过期，则返回false
            if ($expireTime > 0 && $expireTime <= time()) {
                return false;
            }
            $value = $this->handler->get($key);
            return $this->getValue($value);
        } catch (Exception $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

    /**
     * 二次获取键值,在get方法没有获取到值时，调用此方法将有可能获取到
     * 此方法是为了防止惊群现象发生,配合lock和isLock方法,设置新的缓存
     * @param string $key   键名
     * @return mixed|false  键值,失败返回false
     */
    public function getTwice($key) {
        try {
            $value = $this->handler->get($key);
            return $this->getValue($value);
        } catch (Exception $ex) {
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
            $this->handler->delete(self::timeKey($key));
            return $this->handler->delete($key);
        } catch (Exception $ex) {
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
            $value = $this->handler->get($key);
            if ($value === false && $this->handler->getResultCode() === \Memcached::RES_NOTFOUND) {
                return false;
            }
            return true;
        } catch (Exception $ex) {
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
            $expireTime = $this->handler->get(self::timeKey($key));
            if ($expireTime) {
                return $expireTime > time() ? $expireTime - time() : -2;
            }
            $value = $this->handler->get($key);
            if ($value === false && $this->handler->getResultCode() === \Memcached::RES_NOTFOUND) {
                return -2;
            } else {
                return -1;
            }
        } catch (Exception $ex) {
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
            //值不存在,直接返回 false
            if ($value === false && $this->handler->getResultCode() === \Memcached::RES_NOTFOUND) {
                return false;
            }
            //设为永不过期
            if ($time <= 0) {
                if ($this->handler->set($key, $value)) {
                    $ret = $this->handler->delete(self::timeKey($key));
                    if ($ret === false && $this->handler->getResultCode() !== \Memcached::RES_NOTFOUND) {
                        return false;
                    }
                    return true;
                }
                return false;
            }
            return $this->handler->setMulti([$key => $value, self::timeKey($key) => $time + time()], time() + $time * 2);
        } catch (Exception $ex) {
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
            if ($value === false && $this->handler->getResultCode() === \Memcached::RES_NOTFOUND) {
                return false;
            }
            if ($this->handler->set($key, $value)) {
                $ret = $this->handler->delete(self::timeKey($key));
                //如果删除失败，且失败原因不是key未找到，则返回false
                if ($ret === false && $this->handler->getResultCode() !== \Memcached::RES_NOTFOUND) {
                    return false;
                }
                return true;
            }
            return false;
        } catch (Exception $ex) {
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
            return $this->handler->set(self::lockKey($key), 1, time() + $time);
        } catch (Exception $ex) {
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
            return (boolean) $this->handler->get(self::lockKey($key));
        } catch (Exception $ex) {
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
            $ret = $this->handler->increment($key, $step);
            //如果key不存在
            if ($ret === false && $this->handler->getResultCode() === \Memcached::RES_NOTFOUND) {
                if ($this->handler->set($key, $step)) {
                    return $step;
                }
                return false;
            }
            return $ret;
        } catch (Exception $ex) {
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
            if (!is_numeric($value) || !is_numeric($float)) {
                return false;
            }
            $expire = $this->handler->get(self::timeKey($key));
            if ($expire > 0) {
                if ($this->handler->set($key, $value += $float, $expire)) {
                    return $value;
                }
                return false;
            }
            if ($this->handler->set($key, $value += $float)) {
                return $value;
            }
            return false;
        } catch (Exception $ex) {
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
            $ret = $this->handler->decrement($key, $step);
            //如果key不存在
            if ($ret === false && $this->handler->getResultCode() === \Memcached::RES_NOTFOUND) {
                if ($this->handler->set($key, -$step)) {
                    return -$step;
                }
                return false;
            }
            return $ret;
        } catch (Exception $ex) {
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
            return $this->handler->setMulti($sets);
        } catch (Exception $ex) {
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
                $status = $this->handler->add($key, $value);
                if ($status) {
                    $keys[] = $key;
                } else {
                    break;
                }
            }
            //如果失败，尝试回滚，但不保证成功
            if (!$status) {
                foreach ($keys as $key) {
                    $this->handler->delete($key);
                }
            }
            return $status;
        } catch (Exception $ex) {
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
            $values = $this->handler->getMulti($keys);
            foreach ($keys as $key) {
                $ret[$key] = isset($values[$key]) ? $values[$key] : false;
            }
            return $ret;
        } catch (Exception $ex) {
            self::exception($ex);
            //连接状态置为false
            $this->isConnected = false;
        }
        return false;
    }

}
