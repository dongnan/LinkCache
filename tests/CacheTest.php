<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace tests;

/**
 * CacheTest
 */
class CacheTest extends PHPUnit_Framework_TestCase
{

    protected $cacheDriver = 'files';

    public function testSet()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->set('test1', 1));
        $this->assertTrue($cache->set('test2', 2, 3));
        $this->assertTrue($cache->set('testDel', 'del'));
    }

    /**
     * @depends testSet
     */
    public function testSetnx()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertFalse($cache->setnx('test1', 1));
        $this->assertTrue($cache->setnx('test3', 3));
        $this->assertTrue($cache->setnx('test4', 4, 3));
    }

    /**
     * @depends testSet
     * @depends testSetnx
     */
    public function testGet()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(1, $cache->get('test1'));
        $this->assertEquals(2, $cache->get('test2'));
        $this->assertEquals(3, $cache->get('test3'));
        $this->assertEquals(4, $cache->get('test4'));
        sleep(3);
        $this->assertFalse($cache->get('test2'));
        $this->assertFalse($cache->get('test4'));
    }

    /**
     * @depends testSet
     */
    public function testDel()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals('del', $cache->get('testDel'));
        $this->assertTrue($cache->del('testDel'));
        $this->assertFalse($cache->get('testDel'));
    }

    /**
     * @depends testSet
     */
    public function testHas()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('test1'));
    }

}
