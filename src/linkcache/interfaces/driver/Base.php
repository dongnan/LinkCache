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
 * Base
 */
interface Base {

    /**
     * 检查驱动是否可用
     * @return boolean      是否可用
     */
    public function checkDriver();

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,<=0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1);

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,<=0则设置为永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1);

    /**
     * 设置键值，将自动延迟过期;<br>
     * 此方法用于缓存对过期要求宽松的数据;<br>
     * 使用此方法设置缓存配合getDE方法可以有效防止惊群现象发生
     * @param string $key    键名
     * @param mixed $value   键值
     * @param int $time      过期时间，<=0则设置为永不过期
     * @param int $delayTime 延迟过期时间，如果未设置，则使用配置中的设置
     * @return boolean       是否成功
     */
    public function setDE($key, $value, $time, $delayTime = null);

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed|false  键值,失败返回false
     */
    public function get($key);

    /**
     * 获取延迟过期的键值，与setDE配合使用;<br>
     * 此方法用于获取setDE设置的缓存数据;<br>
     * 当isExpired为true时，说明key已经过期，需要更新;<br>
     * 更新数据时配合isLock和lock方法，防止惊群现象发生
     * @param string $key       键名
     * @param boolean $isExpired 是否已经过期
     * @return mixed|false      键值,失败返回false
     */
    public function getDE($key, &$isExpired = null);

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
     * 判断延迟过期的键值理论上是否存在
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function hasDE($key);

    /**
     * 获取生存剩余时间
     * @param string $key   键名
     * @return int|false    生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在,失败返回false
     */
    public function ttl($key);

    /**
     * 获取延迟过期的键值理论生存剩余时间
     * @param string $key   键名
     * @return int|false    生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在,失败返回false
     */
    public function ttlDE($key);

    /**
     * 设置过期时间
     * @param string $key   键名
     * @param int $time     过期时间(单位:秒)。不大于0，则设为永不过期
     * @return boolean      是否成功
     */
    public function expire($key, $time);

    /**
     * 以延迟过期的方式设置过期时间
     * @param string $key    键名
     * @param int $time      过期时间(单位:秒)。不大于0，则设为永不过期
     * @param int $delayTime 延迟过期时间，如果未设置，则使用配置中的设置
     * @return boolean       是否成功
     */
    public function expireDE($key, $time, $delayTime = null);

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key);
}
