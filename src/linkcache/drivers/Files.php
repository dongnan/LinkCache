<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
 */

namespace linkcache\drivers;

use linkcache\abstracts\DriverSimple;

/**
 * Files
 */
class Files extends DriverSimple {

    /**
     * 构造函数
     * @param array $config 配置
     */
    public function __construct($config = []) {
        $this->init($config);
        $this->initPath();
    }

    /**
     * 检查驱动是否可用
     * @return boolean      是否可用
     */
    public function checkDriver() {
        return true;
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
     * @param string $key
     * @param string $value
     * @return boolean
     */
    protected function setOne($key, $value) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            $byte = file_put_contents($path, $value, LOCK_EX);
            if($byte !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取键值
     * @param string $key
     * @return mixed
     */
    protected function getOne($key) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if (!file_exists($path)) {
                return false;
            }
            return file_get_contents($path);
        }
        return false;
    }

    /**
     * 删除键值
     * @param string $key
     * @return boolean
     */
    protected function delOne($key) {
        $path = $this->hashPath($key);
        if ($path !== false) {
            if (!file_exists($path)) {
                return true;
            }
            return unlink($path);
        }
        return false;
    }

}
