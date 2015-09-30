<?php

/**
 * LinkCache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace LinkCache;

/**
 * 缓存驱动扩展接口
 */
interface CacheDriverExtendInterface {

    public function incr($key, $step = 1);

    public function incrByFloat($key, $float);

    public function decr($key, $step = 1);

    public function mSet($sets);

    public function mSetNX($sets);

    public function mGet($keys);
}
