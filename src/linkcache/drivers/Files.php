<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        https://github.com/dongnan/linkcache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcache\drivers;

use linkcache\CacheDriverInterface;

/**
 * Files
 */
class Files implements CacheDriverInterface {

    use \linkcache\CacheDriverTrait;

    /**
     * 配置信息
     * @var array 
     */
    private $config = [];

    /**
     * 构造函数
     * @param array $config 配置
     */
    public function __construct($config = []) {
        $this->config = $config;
        $this->initPath();
    }

    /**
     * 初始化路径
     */
    private function initPath() {
        if (empty($this->config['path'])) {
            $this->config['path'] = (ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir()) . '/linkcache';
        }
        if (!file_exists($this->config['path'])) {
            mkdir($this->config['path'], 0777, true);
        }
    }

    /**
     * 根据键名获取hash路径
     * @param string $key
     * @return boolean|string
     */
    private function hashPath($key) {
        $md5 = md5($key);
        $dir = $this->config['path'] . '/' . $md5[0] . '/' . $md5[1] . $md5[2];
        $path = $dir . '/' . $md5 . '.lc';
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                return false;
            }
        } else {
            if (!is_writable($dir)) {
                return false;
            }
        }
        return $path;
    }

    /**
     * 设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function set($key, $value, $time = -1) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if ($time > 0 || !file_exists($path)) {
                $value = self::setValue(['value' => $value, 'write_time' => time(), 'expire_time' => $time]);
                return file_put_contents($path, $value, LOCK_EX);
            }
            $value = self::getValue(file_get_contents($path));
            $value['value'] = $value;
            return file_put_contents($key, self::setValue($value), LOCK_EX);
        }
        return false;
    }

    /**
     * 当键名不存在时设置键值
     * @param string $key   键名
     * @param mixed $value  键值
     * @param int $time     过期时间,默认为-1,不设置过期时间;为0则设置为永不过期
     * @return boolean      是否成功
     */
    public function setnx($key, $value, $time = -1) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            $toWrite = true;
            if (file_exists($path)) {
                $old = self::getValue(file_get_contents($path));
                $toWrite = false;
                //已过期
                if ($old['expire_time'] > 0 && ($old['write_time'] + $old['expire_time']) <= time()) {
                    $toWrite = true;
                }
            }
            if ($toWrite) {
                $value = self::setValue(['value' => $value, 'write_time' => time(), 'expire_time' => $time]);
                return file_put_contents($path, $value, LOCK_EX);
            }
        }
        return false;
    }

    /**
     * 获取键值
     * @param string $key   键名
     * @return mixed        键值
     */
    public function get($key) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if (!file_exists($path)) {
                return false;
            }
            $value = self::getValue(file_get_contents($path));
            //已过期
            if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) <= time()) {
                return false;
            }
            return $value['value'];
        }
        return false;
    }

    /**
     * 二次获取键值,在get方法没有获取到值时，调用此方法将有可能获取到
     * 此方法是为了防止惊群现象发生,配合lock和isLock方法,设置新的缓存
     * @param string $key   键名
     * @return mixed        键值
     */
    public function getTwice($key) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if (!file_exists($path)) {
                return false;
            }
            $value = self::getValue(file_get_contents($path));
            //如果已过期两倍于设置的过期时间,则删除缓存
            if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time'] * 2) <= time()) {
                unlink($path);
            }
            return $value['value'];
        }
        return false;
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
        $path = $this->hashPath(self::lockKey($key));
        if ($path !== false) {
            $toWrite = true;
            if (file_exists($path)) {
                $old = self::getValue(file_get_contents($path));
                $toWrite = false;
                //锁已过期
                if ($old['expire_time'] > 0 && ($old['write_time'] + $old['expire_time']) <= time()) {
                    $toWrite = true;
                }
            }
            if ($toWrite) {
                $value = self::setValue(['value' => $value, 'write_time' => time(), 'expire_time' => $time]);
                return file_put_contents($path, $value, LOCK_EX);
            }
        }
        return false;
    }

    /**
     * 对指定键名解锁
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否加锁
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function isLock($key) {
        $path = $this->hashPath(self::lockKey($key));
        if ($path !== false) {
            if (!file_exists($path)) {
                return false;
            }
            $lock = self::getValue(file_get_contents($path));
            //永不过期
            if ($lock['expire_time'] <= 0) {
                return true;
            }
            //锁未过期
            if ($lock['expire_time'] > 0 && ($lock['write_time'] + $lock['expire_time']) > time()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 删除键值
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function del($key) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if (!file_exists($path)) {
                return true;
            }
            return unlink($path);
        }
        return false;
    }

    /**
     * 是否存在键值
     * @param string $key   键名
     * @return boolean      是否存在
     */
    public function has($key) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if (!file_exists($path)) {
                return false;
            }
            $value = self::getValue(file_get_contents($path));
            //永不过期
            if ($value['expire_time'] <= 0) {
                return true;
            }
            //未过期
            if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) > time()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取生存剩余时间
     * @param string $key   键名
     * @return int          生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在
     */
    public function ttl($key) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            //键值不存在,返回 -2
            if (!file_exists($path)) {
                return -2;
            }
            $value = self::getValue(file_get_contents($path));
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
        return false;
    }

    /**
     * 设置过期时间
     * @param string $key   键名
     * @param int $time     过期时间(单位:秒)。不大于0，则设为永不过期
     * @return boolean      是否成功
     */
    public function expire($key, $time) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if (!file_exists($path)) {
                return false;
            }
            $value = self::getValue(file_get_contents($path));
            //已过期
            if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) <= time()) {
                return false;
            }
            if ($time <= 0) {
                $value['expire_time'] = -1;
            } else {
                $value['expire_time'] = time() - $value['write_time'] + $time;
            }
            return file_put_contents($key, self::setValue($value), LOCK_EX);
        }
        return false;
    }

    /**
     * 移除指定键值的过期时间
     * @param string $key   键名
     * @return boolean      是否成功
     */
    public function persist($key) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if (!file_exists($path)) {
                return false;
            }
            $value = self::getValue(file_get_contents($path));
            //已过期
            if ($value['expire_time'] > 0 && ($value['write_time'] + $value['expire_time']) <= time()) {
                return false;
            }
            $value['expire_time'] = -1;
            return file_put_contents($key, self::setValue($value), LOCK_EX);
        }
        return false;
    }

}
