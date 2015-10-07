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
