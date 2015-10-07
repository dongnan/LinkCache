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

    static protected function timeKey($key) {
        return $key . '_time';
    }

    static protected function lockKey($key) {
        return $key . '_lock';
    }

    static protected function setValue($value) {
        if (!is_numeric($value)) {
            $value = serialize($value);
        }
        return $value;
    }

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
     * 返回定义为fallback的Cache实例
     * @return Cache
     */
    protected function backup() {
        return Cache::getInstance('fallback');
    }

}
