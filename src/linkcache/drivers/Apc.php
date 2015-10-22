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
use linkcache\interfaces\driver\Multi;

/**
 * Apc
 */
class Apc implements Base, Multi {

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
            return apc_store($key, self::setValue(['value' => $value, 'expire_time' => $time]), $time * 2);
        }
        $old = self::getValue($this->getOne($key));
        if (empty($old) || $time == 0) {
            return apc_store($key, self::setValue(['value' => $value, 'expire_time' => -1]));
        }
        $old['value'] = $value;
        //如果没有过期，设置过期时间;否则ttl默认为0
        $ttl = $old['expire_time'] > time() ? ($old['expire_time'] - time()) * 2 : 0;
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
            return apc_add($key, self::setValue(['value' => $value, 'expire_time' => $time]), $time * 2);
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
     * 二次获取键值,在get方法没有获取到值时，调用此方法将有可能获取到
     * 此方法是为了防止惊群现象发生,配合lock和isLock方法,设置新的缓存
     * @param string $key   键名
     * @return mixed|false  键值,失败返回false
     */
    public function getTwice($key) {
        $value = self::getValue(apc_fetch($key));
        if (empty($value) || !isset($value['expire_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && $value['expire_time'] <= time()) {
            apc_delete($key);
        }
        return $value['value'];
    }

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function del($key) {
        return apc_delete($key);
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
        return apc_store($key, self::setValue($value), $time * 2);
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

    /**
     * 批量设置键值
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSet($sets) {
        return apc_store($sets);
    }

    /**
     * 批量设置键值(当键名不存在时)
     * 只有当键值全部设置成功时,才返回true,否则返回false并尝试回滚
     * @param array $sets   键值数组
     * @return boolean      是否成功
     */
    public function mSetNX($sets) {
        return apc_add($sets);
    }

    /**
     * 批量获取键值
     * @param array $keys   键名数组
     * @return array|false  键值数组,失败返回false
     */
    public function mGet($keys) {
        $ret = [];
        $values = apc_fetch($keys);
        foreach ($keys as $key) {
            $ret[$key] = isset($values[$key]) ? $values[$key] : false;
        }
        return $ret;
    }

}
