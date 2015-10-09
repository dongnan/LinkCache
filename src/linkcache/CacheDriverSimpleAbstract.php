<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcache;

use linkcache\CacheDriverInterface;
use linkcache\CacheDriverSimpleInterface;

/**
 * CacheDriverSimpleAbstract
 */
abstract class CacheDriverSimpleAbstract implements CacheDriverSimpleInterface, CacheDriverInterface {

    use \linkcache\CacheDriverTrait;

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        if ($time > 0) {
            return $this->setOne($key, self::setValue(['value' => $value, 'write_time' => time(), 'expire_time' => $time]));
        }
        $old = self::getValue($this->getOne($key));
        $old['value'] = $value;
        return $this->setOne($key, self::setValue($old));
    }

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1) {
        $toWrite = true;
        $old = self::getValue($this->getOne($key));
        if (!empty($old) && isset($old['expire_time']) && isset($old['write_time']) && isset($old['value'])) {
            $toWrite = false;
            //已过期
            if ($old['expire_time'] > 0 && ($old['write_time'] + $old['expire_time']) <= time()) {
                $toWrite = true;
            }
        }
        if ($toWrite) {
            return $this->setOne($key, self::setValue(['value' => $value, 'write_time' => time(), 'expire_time' => $time]));
        }
    }

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed        键值
     */
    public function get($key) {
        $value = self::getValue($this->getOne($key));
        if (empty($value) || !isset($value['expire_time']) || !isset($value['write_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) <= time()) {
            return false;
        }
        return $value['value'];
    }

    /**
     * 二次获取键值,在get方法没有获取到值时，调用此方法将有可能获取到
     * 此方法是为了防止惊群现象发生,配合lock和isLock方法,设置新的缓存
     * @param string $key   键名
     * @return mixed        键值
     */
    public function getTwice($key) {
        $value = self::getValue($this->getOne($key));
        if (empty($value) || !isset($value['expire_time']) || !isset($value['write_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) <= time()) {
            $this->delOne($key);
        }
        return $value['value'];
    }

    /**
     * 对指定键名加锁（此锁并不对键值做修改限制,仅为键名的锁标记）
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,先判断键名是否加锁,
     * 如果已加锁,则不获取新值;如果未加锁,则获取新值,设置新的缓存
     * @param string $key   键名
     * @param int $time     加锁时间
     * @return boolean      是否成功
     */
    public function lock($key, $time = 60) {
        return $this->setnx(self::lockKey($key), 1, $time);
    }

    /**
     * 对指定键名解锁
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否加锁
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function isLock($key) {
        $lock = self::getValue($this->getOne(self::lockKey($key)));
        if (empty($lock) || !isset($lock['expire_time']) || !isset($lock['write_time']) || !isset($lock['value'])) {
            return false;
        }
        //永不过期
        if ($lock['expire_time'] <= 0) {
            return true;
        }
        //锁未过期
        if ($lock['expire_time'] > 0 && ($lock['write_time'] + $lock['expire_time']) > time()) {
            return true;
        }
        return false;
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
        if (empty($value) || !isset($value['expire_time']) || !isset($value['write_time']) || !isset($value['value'])) {
            return false;
        }
        //永不过期
        if ($value['expire_time'] <= 0) {
            return true;
        }
        //未过期
        if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) > time()) {
            return true;
        }
        return false;
    }

    /**
     * 获取生存剩余时间
     * @param string $key   键名
     * @return int          生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在
     */
    public function ttl($key) {
        $value = self::getValue($this->getOne($key));
        //键值不存在,返回 -2
        if (empty($value) || !isset($value['expire_time']) || !isset($value['write_time']) || !isset($value['value'])) {
            return -2;
        }
        //永不过期
        if ($value['expire_time'] <= 0) {
            return -1;
        }
        $ttl = time() - ($value['write_time'] + $value['expire_time']);
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
        //键值不存在
        if (empty($value) || !isset($value['expire_time']) || !isset($value['write_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) <= time()) {
            return false;
        }
        if ($time <= 0) {
            $value['expire_time'] = -1;
        } else {
            $value['expire_time'] = time() - $value['write_time'] + $time;
        }
        return $this->setOne($key, self::setValue($value));
    }

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key) {
        $value = self::getValue($this->getOne($key));
        //键值不存在
        if (empty($value) || !isset($value['expire_time']) || !isset($value['write_time']) || !isset($value['value'])) {
            return false;
        }
        //已过期
        if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) <= time()) {
            return false;
        }
        $value['expire_time'] = -1;
        return $this->setOne($key, self::setValue($value));
    }

}
