<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
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
            $this->fallback = empty($config['fallback']) ? 'files' : $config['fallback'];
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

}
