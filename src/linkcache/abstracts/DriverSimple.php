<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
 */

namespace linkcache\abstracts;

use linkcache\interfaces\driver\Base;

/**
 * DriverSimple
 */
abstract class DriverSimple implements Base {

    use \linkcache\traits\CacheDriver;

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @return boolean      是否成功
     */
    abstract protected function setOne($key, $value);

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed        键值
     */
    abstract protected function getOne($key);

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    abstract protected function delOne($key);
    
    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,<=0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        if ($time > 0) {
            return $this->setOne($key, self::setValue(['value' => $value, 'expire_time' => time() + $time]), $time);
        }
        return $this->setOne($key, self::setValue(['value' => $value, 'expire_time' => -1]));
    }

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,<=0则设置为永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1) {
        $toWrite = true;
        $old = self::getValue($this->getOne($key));
        //未过期
        if (!self::isExpired($old)) {
            $toWrite = false;
        }
        if ($toWrite) {
            if ($time > 0) {
                return $this->setOne($key, self::setValue(['value' => $value, 'expire_time' => time() + $time]), $time);
            }
            return $this->setOne($key, self::setValue(['value' => $value, 'expire_time' => -1]));
        }
        return false;
    }

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
    public function setDE($key, $value, $time, $delayTime = null) {
        if ($time > 0) {
            $delayTime = $this->getDelayTime($delayTime);
            return $this->setOne($key, self::setValue(['value' => $value, 'expire_time' => time() + $time + $delayTime, 'delay_time' => $delayTime]), $time + $delayTime);
        }
        return $this->setOne($key, self::setValue(['value' => $value, 'expire_time' => -1]));
    }

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed|false  键值,失败返回false
     */
    public function get($key) {
        $value = self::getValue($this->getOne($key));
        //不存在
        if (!self::isExist($value)) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && $value['expire_time'] < time()) {
            $this->delOne($key);
            return false;
        }
        return $value['value'];
    }

    /**
     * 获取延迟过期的键值，与setDE配合使用;<br>
     * 此方法用于获取setDE设置的缓存数据;<br>
     * 当isExpired为true时，说明key已经过期，需要更新;<br>
     * 更新数据时配合isLock和lock方法，防止惊群现象发生
     * @param string $key       键名
     * @param boolean $isExpired 是否已经过期
     * @return mixed|false      键值,失败返回false
     */
    public function getDE($key, &$isExpired = null) {
        $value = self::getValue($this->getOne($key));
        //判断过期情况:1,key不存在;2,key存在,未设置延迟过期,且已过期;3,key存在,已设置延迟过期,且已过期
        $isExpired = self::isExpiredDE($value);
        return isset($value['value']) ? $value['value'] : false;
    }

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function del($key) {
        return $this->delOne($key);
    }

    /**
     * 是否存在键值
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function has($key) {
        $value = self::getValue($this->getOne($key));
        //不存在或已过期
        if (self::isExpired($value)) {
            return false;
        }
        return true;
    }

    /**
     * 判断延迟过期的键值理论上是否存在
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function hasDE($key) {
        $value = self::getValue($this->getOne($key));
        //不存在或已过期
        if (self::isExpiredDE($value)) {
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
        $value = self::getValue($this->getOne($key));
        //键值不存在,返回 -2
        if (!self::isExist($value)) {
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
     * 获取延迟过期的键值理论生存剩余时间
     * @param string $key   键名
     * @return int|false    生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在,失败返回false
     */
    public function ttlDE($key) {
        $value = self::getValue($this->getOne($key));
        //键值不存在,返回 -2
        if (!self::isExist($value)) {
            return -2;
        }
        //永不过期
        if ($value['expire_time'] <= 0) {
            return -1;
        }
        if (isset($value['delay_time'])) {
            $ttl = $value['expire_time'] - $value['delay_time'] - time();
        } else {
            $ttl = $value['expire_time'] - time();
        }
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
        $value = self::getValue($this->getOne($key));
        //不存在或已过期
        if (self::isExpired($value)) {
            return false;
        }
        if ($time <= 0) {
            $value['expire_time'] = -1;
        } else {
            $value['expire_time'] = time() + $time;
        }
        return $this->setOne($key, self::setValue($value), $time);
    }

    /**
     * 以延迟过期的方式设置过期时间
     * @param string $key    键名
     * @param int $time      过期时间(单位:秒)。不大于0，则设为永不过期
     * @param int $delayTime 延迟过期时间，如果未设置，则使用配置中的设置
     * @return boolean       是否成功
     */
    public function expireDE($key, $time, $delayTime = null) {
        $value = self::getValue($this->getOne($key));
        //不存在或已过期
        if (self::isExpiredDE($value)) {
            return false;
        }
        if ($time <= 0) {
            $value['expire_time'] = -1;
        } else {
            $delayTime = $this->getDelayTime($delayTime);
            $value['expire_time'] = time() + $time + $delayTime;
            $value['delay_time'] = $delayTime;
            $time += $delayTime;
        }
        return $this->setOne($key, self::setValue($value), $time);
    }

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key) {
        $value = self::getValue($this->getOne($key));
        //不存在或已过期
        if (self::isExpiredDE($value)) {
            return false;
        }
        $value['expire_time'] = -1;
        return $this->setOne($key, self::setValue($value));
    }

}
