<?php

/**
 * LinkCache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcache;

/**
 * 缓存驱动 trait
 */
trait CacheDriverTrait {

    /**
     * 获取timeKey,过期时间的key
     * @param string $key
     * @return string
     */
    static protected function timeKey($key) {
        return $key . '_time';
    }

    /**
     * 获取lockKey
     * @param string $key
     * @return string
     */
    static protected function lockKey($key) {
        return $key . '_lock';
    }

    /**
     * 设置value,用于序列化存储
     * @param mixed $value
     * @return mixed
     */
    static protected function setValue($value) {
        if (!is_numeric($value)) {
            $value = serialize($value);
        }
        return $value;
    }

    /**
     * 获取value,解析可能序列化的值
     * @param mixed $value
     * @return mixed
     */
    static protected function getValue($value) {
        if (!$value) {
            return $value;
        }
        if (!is_numeric($value)) {
            $value = unserialize($value);
        }
        return $value;
    }

    /**
     * 处理异常信息
     * @param \Exception $ex
     */
    static protected function exception($ex) {
        
    }

    /**
     * 返回定义为fallback的Cache实例
     * @return Cache
     */
    static protected function backup() {
        return Cache::getInstance('fallback');
    }

    /**
     * 加载扩展
     * @param string $name
     */
    static protected function import($name) {
        require_once(dirname(__FILE__) . "/_extensions/" . $name);
    }

}
