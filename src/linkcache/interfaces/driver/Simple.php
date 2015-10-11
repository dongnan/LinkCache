<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcache\interfaces\driver;

/**
 * Simple
 */
interface Simple {

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @return boolean      是否成功
     */
    public function setOne($key, $value);

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed        键值
     */
    public function getOne($key);

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function delOne($key);
}
