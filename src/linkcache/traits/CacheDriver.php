<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
 */

namespace linkcache\traits;

/**
 * 缓存驱动 trait
 */
trait CacheDriver {

    use Cache;

    /**
     * 配置信息
     * @var array 
     */
    protected $config = [];

    /**
     * 是否启用备用缓存
     * @var boolean
     */
    protected $enableFallback = true;

    /**
     * 备用缓存
     * @var string
     */
    protected $fallback = '';

    /**
     * 延迟过期时间,默认为1800s
     * @var int 
     */
    protected $delayExpireTime = 1800;

    /**
     * 初始化
     * @param array $config
     */
    protected function init($config) {
        $this->config = $config;
        //不启用备用缓存
        if (isset($config['fallback']) && $config['fallback'] === false) {
            $this->enableFallback = false;
        } else {
            //默认为fallback,将使用Cache::$config['fallback']作为备用缓存;也可自定义
            $this->fallback = empty($config['fallback']) ? \linkcache\Cache::getConfig('fallback') : $config['fallback'];
        }
        //自定义延迟过期时间
        if (isset($config['delay_expire_time']) && $config['delay_expire_time'] > 0) {
            $this->delayExpireTime = $config['delay_expire_time'];
        }
    }

    /**
     * 获取配置信息
     * @param string $name      键名
     * @return array $config    配置信息
     */
    public function getConfig($name = '') {
        if (empty($name)) {
            return $this->config;
        } else {
            return isset($this->config[$name]) ? $this->config[$name] : null;
        }
    }

    /**
     * 检查使用备用缓存
     * @return boolean      是否使用
     */
    public function isFallback() {
        return isset($this->enableFallback) ? $this->enableFallback : true;
    }

    /**
     * 返回定义为fallback的Cache实例
     * @return Cache
     */
    public function backup() {
        $fallback = !empty($this->fallback) ? $this->fallback : 'files';
        return \linkcache\Cache::getInstance($fallback);
    }

    /**
     * 获取延迟过期时间
     * @param int $delayTime
     * @return int           
     */
    protected function getDelayTime($delayTime = null) {
        if (is_null($delayTime) || $delayTime < 0) {
            $delayTime = $this->delayExpireTime;
        }
        return $delayTime;
    }

    /**
     * 判断缓存数据是否存在
     * @param type $value
     * @return boolean
     */
    static protected function isExist($value) {
        //不存在
        if ($value === false || !isset($value['expire_time'])) {
            return false;
        }
        return true;
    }

    /**
     * 判断缓存数据是否过期
     * @param array $value     缓存数据
     * @return boolean          是否过期
     */
    static protected function isExpired($value) {
        //不存在
        if (!self::isExist($value)) {
            return true;
        }
        //已过期
        if ($value['expire_time'] > 0 && $value['expire_time'] < time()) {
            return true;
        }
        return false;
    }

    /**
     * 判断设置了延迟过期的缓存数据理论上是否已过期
     * @param array $value     缓存数据
     * @return boolean          是否过期
     */
    static protected function isExpiredDE($value) {
        //不存在
        if (!self::isExist($value)) {
            return true;
        }
        //设置了延迟过期
        if (isset($value['delay_time'])) {
            //理论上已过期(由于设置了延迟过期，实际可能未过期)
            if ($value['expire_time'] > 0 && $value['expire_time'] < time() + $value['delay_time']) {
                return true;
            }
        } else {
            //已过期
            if ($value['expire_time'] > 0 && $value['expire_time'] < time()) {
                return true;
            }
        }
        return false;
    }

}
