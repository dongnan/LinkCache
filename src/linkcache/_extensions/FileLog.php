<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
 */

/**
 * FileLog
 */
class FileLog {

    //日志等级
    const DEBUG = 100;
    const INFO = 200;
    const NOTICE = 250;
    const WARNING = 300;
    const ERROR = 400;
    const CRITICAL = 500;
    const ALERT = 550;
    const EMERGENCY = 600;

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     * @var array $levels Logging levels
     */
    private static $levels = [
        100 => 'DEBUG',
        200 => 'INFO',
        250 => 'NOTICE',
        300 => 'WARNING',
        400 => 'ERROR',
        500 => 'CRITICAL',
        550 => 'ALERT',
        600 => 'EMERGENCY',
    ];

    /**
     * 日志名称
     * @var string 
     */
    private $name;

    /**
     * 日志路径
     * @var string 
     */
    private $path;

    /**
     * 文件句柄
     * @var resource 
     */
    private $stream;

    /**
     * 记录的最小日志等级
     * @var int 
     */
    private $minlevel;

    /**
     * 构造函数
     * @param string $name 
     * @param string $path
     * @param int $level
     */
    public function __construct($name = null, $path = null, $level = FileLog::DEBUG) {
        $this->name = $name? : 'common';
        if (empty($path)) {
            $this->path = (ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir()) . '/linkcache/logs';
        } else {
            $this->path = $path;
        }
        if (!file_exists($this->path)) {
            mkdir($this->path, 0777, true);
        }
        $this->minlevel = $level;
    }

    /**
     * 析构函数
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * 关闭文件句柄
     */
    public function close() {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    /**
     * 添加日志记录
     * @access public
     * @param int $level 日志等级
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function log($level, $message, $data = []) {
        //根据最小日志等级记录日志
        if ($level < $this->minlevel) {
            return false;
        }
        if (!is_resource($this->stream)) {
            $logpath = $this->path . date('/Y/md');
            if (!is_dir($logpath)) {
                mkdir($logpath, 0777, true);
            }
            $logfile = "{$logpath}/{$this->name}.log";
            $this->stream = fopen($logfile, 'a');
            if (!is_resource($this->stream)) {
                throw new Exception("file open failed,filename:{$logfile}");
            }
        }
        $datetime = date('Y-m-d H:i:s');
        $jsonData = preg_replace('/\s+/', ' ', var_export($data, true));
        $levelName = isset(self::$levels[$level]) ? self::$levels[$level] : $level;
        $output = "[{$datetime}] {$this->name}.{$levelName}: {$message} {$jsonData}" . PHP_EOL;
        $len = fwrite($this->stream, $output);
        if ($len !== false) {
            return true;
        }
        return false;
    }

    /**
     * 添加 debug 日志
     * @access public
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function debug($message, $data = []) {
        return $this->log(FileLog::DEBUG, $message, $data);
    }

    /**
     * 添加 info 日志
     * @access public
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function info($message, $data = []) {
        return $this->log(FileLog::INFO, $message, $data);
    }

    /**
     * 添加 notice 日志
     * @access public
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function notice($message, $data = []) {
        return $this->log(FileLog::NOTICE, $message, $data);
    }

    /**
     * 添加 warning 日志
     * @access public
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function warning($message, $data = []) {
        return $this->log(FileLog::WARNING, $message, $data);
    }

    /**
     * 添加 error 日志
     * @access public
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function error($message, $data = []) {
        return $this->log(FileLog::ERROR, $message, $data);
    }

    /**
     * 添加 critical 日志
     * @access public
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function critical($message, $data = []) {
        return $this->log(FileLog::CRITICAL, $message, $data);
    }

    /**
     * 添加 alert 日志
     * @access public
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function alert($message, $data = []) {
        return $this->log(FileLog::ALERT, $message, $data);
    }

    /**
     * 添加 emergency 日志
     * @access public
     * @param string $message 日志内容
     * @param array $data 日志相关数组数据
     * @return boolean
     */
    public function emergency($message, $data = []) {
        return $this->log(FileLog::EMERGENCY, $message, $data);
    }

}
