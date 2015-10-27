<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://www.freebsd.org/copyright/freebsd-license.html)
 */

namespace linkcacheTests;

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
        $this->assertTrue($cache->set('testExpire', 'expire'));
        $this->assertTrue($cache->set('testExpireAt', 'expireAt'));
        $this->assertTrue($cache->set('testPersist', 'persist', 600));
        $this->assertTrue($cache->set('notNum', 'notNum'));
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

    /**
     * @depends testExpire
     * @depends testExpireAt
     */
    public function testTtl()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(10, $cache->ttl('testExpire'));
        $this->assertEquals(10, $cache->ttl('testExpireAt'));
        $this->assertEquals(-1, $cache->ttl('testPersist'));
        $this->assertEquals(-2, $cache->ttl('testNotExist'));
    }

    /**
     * @depends testSet
     */
    public function testExpire()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->expire('testExpire', 10));
    }

    /**
     * @depends testSet
     */
    public function testExpireAt()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->expireAt('testExpireAt', time() + 10));
    }

    /**
     * @depends testSet
     */
    public function testPersist()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->persist('testPersist'));
    }

    public function testLock()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->lock('testLock'));
    }

    /**
     * @depends testLock
     */
    public function testIsLock()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->isLock('testLock'));
        $this->assertFalse($cache->isLock('testNotLock'));
    }

    /**
     * @depends testSet
     */
    public function testIncr()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(1, $cache->incr('incr'));
        $this->assertEquals(3, $cache->incr('incr', 2));
        $this->assertEquals(2, $cache->incr('incr', -1));
        $this->assertFalse($cache->incr('incr', 'str'));
        $this->assertFalse($cache->incr('notNum'));
    }

    /**
     * @depends testSet
     */
    public function testIncrByFloat()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(1.5, $cache->incrByFloat('incrByFloat', 1.5), '', 0.01);
        $this->assertEquals(5.501, $cache->incrByFloat('incrByFloat', 3.501), '', 0.0001);
        $this->assertEquals(3.001, $cache->incrByFloat('incrByFloat', -2.5), '', 0.0001);
        $this->assertFalse($cache->incrByFloat('incrByFloat', 'str'));
        $this->assertFalse($cache->incrByFloat('notNum'));
    }

    /**
     * @depends testSet
     */
    public function testDecr()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(-1, $cache->decr('decr'));
        $this->assertEquals(-3, $cache->decr('decr', 2));
        $this->assertEquals(-2, $cache->decr('decr', -1));
        $this->assertFalse($cache->decr('decr', 'str'));
        $this->assertFalse($cache->decr('notNum'));
    }

    public function testMSet()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->mSet(['mset1' => 1, 'mset2' => 2, 'mset3' => 3]));
    }

    /**
     * @depends testMSet
     */
    public function testMSetNX()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertFalse($cache->mSetNX(['mset1' => 1, 'mset2' => 2, 'msetnx3' => 3]));
        $this->assertFalse($cache->mSetNX(['msetnx1' => 1, 'msetnx2' => 2, 'msetnx3' => 3]));
    }

    /**
     * @depends testSet
     */
    public function testMGet()
    {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertArraySubset(['mset1' => 1, 'mset2' => 2, 'mset3' => 3], $cache->mGet(['mset1', 'mset2', 'mset3']), true);
    }

}
