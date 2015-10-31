<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcache\drivers;

use linkcache\interfaces\driver\Base;

/**
 * Apc
 */
class Apc implements Base {

    use \linkcache\traits\CacheDriver;

    /**
     * 构造函数
     * @param array $config 配置
     */
    public function __construct($config = []) {
        // Check apc
        if (!extension_loaded('apc')) {
            throw new \Exception("apc extension is not exists!");
        }
        if (!ini_get('apc.enabled')) {
            throw new \Exception("apc is not enabled!");
        }
        $this->init($config);
    }

    /**
     * 检查驱动是否可用
     * @return boolean      是否可用
     */
    public function checkDriver() {
        return true;
    }

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        if ($time > 0) {
            return apc_store($key, self::setValue(['value' => $value, 'expire_time' => time() + $time]), $time);
        }
        $old = self::getValue(apc_fetch($key));
        if (empty($old) || $time == 0) {
            $ret = apc_store($key, self::setValue(['value' => $value, 'expire_time' => -1]));
            return $ret;
        }
        $old['value'] = $value;
        //如果没有过期，设置过期时间;否则ttl默认为0
        $ttl = $old['expire_time'] > time() ? $old['expire_time'] - time() : 0;
        return apc_store($key, self::setValue($old), $ttl);
    }

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1) {
        if ($time > 0) {
            return apc_add($key, self::setValue(['value' => $value, 'expire_time' => time() + $time]), $time);
        }
        return apc_add($key, self::setValue(['value' => $value, 'expire_time' => -1]));
    }

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed|false  键值,失败返回false
     */
    public function get($key) {
        $value = self::getValue(apc_fetch($key));
        if (empty($value) || !isset($value['expire_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && $value['expire_time'] <= time()) {
            return false;
        }
        return $value['value'];
    }

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function del($key) {
        $ret = apc_delete($key);
        if ($ret === false && !apc_exists($key)) {
            return true;
        }
        return $ret;
    }

    /**
     * 是否存在键值
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function has($key) {
        $value = self::getValue(apc_fetch($key));
        if (empty($value) || !isset($value['expire_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && $value['expire_time'] <= time()) {
            return false;
        }
        return true;
    }

    /**
     * 获取生存剩余时间
     * @param string $key   键名
     * @return int|false    生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在,失败返回false
     */
    public function ttl($key) {
        $value = self::getValue(apc_fetch($key));
        //键值不存在,返回 -2
        if (empty($value) || !isset($value['expire_time']) || !isset($value['value'])) {
            return -2;
        }
        //永不过期
        if ($value['expire_time'] <= 0) {
            return -1;
        }
        $ttl = $value['expire_time'] - time();
        if ($ttl > 0) {
            return $ttl;
        }
        //已过期,返回 -2
        else {
            return -2;
        }
    }

    /**
     * 设置过期时间
     * @param string $key   键名
     * @param int $time     过期时间(单位:秒)。不大于0，则设为永不过期
     * @return boolean      是否成功
     */
    public function expire($key, $time) {
        $value = self::getValue(apc_fetch($key));
        //键值不存在
        if (empty($value) || !isset($value['expire_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && $value['expire_time'] <= time()) {
            return false;
        }
        if ($time <= 0) {
            $value['expire_time'] = -1;
        } else {
            $value['expire_time'] = time() + $time;
        }
        return apc_store($key, self::setValue($value), $time);
    }

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key) {
        $value = self::getValue(apc_fetch($key));
        //键值不存在
        if (empty($value) || !isset($value['expire_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && $value['expire_time'] <= time()) {
            return false;
        }
        $value['expire_time'] = -1;
        return apc_store($key, self::setValue($value));
    }

}
