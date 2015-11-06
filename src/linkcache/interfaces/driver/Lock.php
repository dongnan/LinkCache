<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
 */

namespace linkcache\interfaces\driver;

/**
 * Lock
 */
interface Lock {

    /**
     * 对指定键名设置锁标记（此锁并不对键值做修改限制,仅为键名的锁标记）;<br>
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,先判断键名是否有锁标记,<br>
     * 如果已加锁,则不获取新值;<br>
     * 如果未加锁,则先设置锁，若设置失败说明锁已存在，若设置成功则获取新值,设置新的缓存
     * @param string $key   键名
     * @param int $time     加锁时间
     * @return boolean      是否成功
     */
    public function lock($key, $time = 60);

    /**
     * 判断键名是否有锁标记;<br>
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否有锁标记
     * @param string $key   键名
     * @return boolean      是否加锁
     */
    public function isLock($key);

    /**
     * 对指定键名移除锁标记
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function unlock($key);
}
