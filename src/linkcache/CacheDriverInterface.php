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
 * 缓存驱动接口
 */
interface CacheDriverInterface {

    public function set($key, $value, $time = -1);

    public function setnx($key, $value, $time = -1);

    public function get($key);

    public function getTwice($key);

    public function lock($key, $time = 60);

    public function isLock($key);

    public function del($key);

    public function has($key);

    public function ttl($key);

    public function expire($key, $time);

    public function persist($key);
}
