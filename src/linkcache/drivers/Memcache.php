<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        https://github.com/dongnan/linkcache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcache\drivers;

use linkcache\CacheDriverInterface;
use linkcache\CacheDriverExtendInterface;

/**
 * Memcache
 */
class Memcache implements CacheDriverInterface, CacheDriverExtendInterface {

    use \linkcache\CacheDriverTrait;

    /**
     * 配置信息
     * @var array 
     */
    private $config = [];

    /**
     * Memcache 对象
     * @var \Memcache 
     */
    private $handler;

    /**
     * 是否连接server
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
        if (!extension_loaded('memcache')) {
            throw new \Exception("memcache extension is not exists!");
        }
        $this->handler = new \Memcache();
        $this->config = $config;
        $this->initServers();
    }

    /**
     * 初始化servers
     */
    public function initServers() {
        if (empty($this->config['servers'])) {
            $servers = [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 1, 'persistent' => true, 'timeout' => 1, 'retry_interval' => 15, 'status' => true],
            ];
        } else {
            $servers = $this->config['servers'];
        }
        foreach ($servers as $server) {
            $host = isset($server['host']) ? $server['host'] : '127.0.0.1';
            $port = isset($server['port']) ? $server['port'] : 11211;
            $persistent = isset($server['persistent']) ? $server['persistent'] : null;
            $weight = isset($server['weight']) ? $server['weight'] : null;
            $timeout = isset($server['timeout']) ? $server['timeout'] : null;
            $retry_interval = isset($server['retry_interval']) ? $server['retry_interval'] : null;
            $status = isset($server['status']) ? $server['status'] : null;
            $failure_callback = isset($server['failure_callback']) ? $server['failure_callback'] : null;
            $this->handler->addserver($host, $port, $persistent, $weight, $timeout, $retry_interval, $status, $failure_callback);
        }
        if (!empty($this->config['compress'])) {
            $threshold = isset($this->config['compress']['threshold']) ? $this->config['compress']['threshold'] : 2000;
            $min_saving = isset($this->config['compress']['min_saving']) ? $this->config['compress']['min_saving'] : 0.2;
            $this->handler->setcompressthreshold($threshold, $min_saving);
        }
    }

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1);

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1);

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed        键值
     */
    public function get($key);

    /**
     * 二次获取键值,在get方法没有获取到值时，调用此方法将有可能获取到
     * 此方法是为了防止惊群现象发生,配合lock和isLock方法,设置新的缓存
     * @param string $key   键名
     * @return mixed        键值
     */
    public function getTwice($key);

    /**
     * 对指定键名加锁（此锁并不对键值做修改限制,仅为键名的锁标记）
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,先判断键名是否加锁,
     * 如果已加锁,则不获取新值;如果未加锁,则获取新值,设置新的缓存
     * @param string $key   键名
     * @param int $time     加锁时间
     * @return boolean      是否成功
     */
    public function lock($key, $time = 60);

    /**
     * 对指定键名解锁
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否加锁
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function isLock($key);

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function del($key);

    /**
     * 是否存在键值
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function has($key);

    /**
     * 获取生存剩余时间
     * @param string $key   键名
     * @return int          生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在
     */
    public function ttl($key);

    /**
     * 设置过期时间
     * @param string $key   键名
     * @param int $time     过期时间(单位:秒)。不大于0，则设为永不过期
     * @return boolean      是否成功
     */
    public function expire($key, $time);

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key);

    /**
     * 递增
     * @param string $key   键名
     * @param int $step     递增步长
     * @return boolean      是否成功
     */
    public function incr($key, $step = 1);

    /**
     * 浮点数递增
     * @param string $key   键名
     * @param float $float  递增步长
     * @return boolean      是否成功
     */
    public function incrByFloat($key, $float);

    /**
     * 递减
     * @param string $key   键名
     * @param int $step     递减步长
     * @return boolean      是否成功
     */
    public function decr($key, $step = 1);

    /**
     * 批量设置键值
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSet($sets);

    /**
     * 批量设置键值(当键名不存在时)
     * 只有当键值全部设置成功时,才返回true,否则返回false并尝试回滚
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSetNX($sets);

    /**
     * 批量获取键值
     * @param array $keys   键名数组
     * @return array        键值数组
     */
    public function mGet($keys);
}
