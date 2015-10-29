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
 * TestDriverFiles
 */
class TestDriverFiles extends \PHPUnit_Framework_TestCase {

    protected $cacheDriver = 'files';

    public function testSet() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->set('test1', 1));
        $this->assertTrue($cache->set('test2', 2, 1));
        $this->assertTrue($cache->set('testDel', 'del'));
        $this->assertTrue($cache->set('notNum', 'notNum'));
    }

    /**
     * @depends testSet
     */
    public function testGet() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(1, $cache->get('test1'));
        $this->assertEquals(2, $cache->get('test2'));
        $this->assertEquals('del', $cache->get('testDel'));
        $this->assertFalse($cache->get('notExist'));
        sleep(1);
        $this->assertFalse($cache->get('test2'));
    }

    /**
     * @depends testSet
     * @depends testGet
     */
    public function testDel() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testDel'));
        $this->assertFalse($cache->get('testDel'));
        $this->assertTrue($cache->del('setnx'));
        $this->assertTrue($cache->del('setnx2'));
    }

    /**
     * @depends testDel
     */
    public function testSetnx() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->setnx('setnx', 'setnx'));
        $this->assertFalse($cache->setnx('setnx', 'again'));
        $this->assertTrue($cache->setnx('setnx2', 'setnx2', 1));
    }

    public function testHas() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->set('testHas', 1));
        $this->assertTrue($cache->has('testHas'));
        $this->assertTrue($cache->del('testHas'));
        $this->assertFalse($cache->has('testHas'));
    }

    public function testExpire() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testExpire'));
        $this->assertTrue($cache->set('testExpire', 'expire'));
        $this->assertTrue($cache->expire('testExpire', 10));
    }

    public function testExpireAt() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testExpireAt'));
        $this->assertTrue($cache->set('testExpireAt', 'expireAt'));
        $this->assertTrue($cache->expireAt('testExpireAt', time() + 10));
    }

    public function testPersist() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testPersist'));
        $this->assertTrue($cache->set('testPersist', 'persist', 600));
        $this->assertTrue($cache->persist('testPersist'));
    }

    /**
     * @depends testSet
     * @depends testSetnx
     * @depends testExpire
     * @depends testExpireAt
     * @depends testPersist
     */
    public function testTtl() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(10, $cache->ttl('testExpire'));
        $this->assertEquals(10, $cache->ttl('testExpireAt'));
        $this->assertEquals(-1, $cache->ttl('testPersist'));
        $this->assertEquals(-2, $cache->ttl('testNotExist'));
        sleep(1);
        $this->assertEquals(-2, $cache->ttl('setnx2'));
    }

    public function testLock() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testLock'));
        $this->assertTrue($cache->lock('testLock'));
    }

    /**
     * @depends testLock
     */
    public function testIsLock() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->isLock('testLock'));
        $this->assertFalse($cache->isLock('testNotLock'));
    }

    /**
     * @depends testSet
     */
    public function testIncr() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('incr'));
        $this->assertEquals(1, $cache->incr('incr'));
        $this->assertEquals(3, $cache->incr('incr', 2));
        $this->assertEquals(2, $cache->incr('incr', -1));
        $this->assertFalse($cache->incr('incr', 'str'));
        $this->assertFalse($cache->incr('notNum'));
    }

    /**
     * @depends testSet
     */
    public function testIncrByFloat() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('incrByFloat'));
        $this->assertEquals(1.5, $cache->incrByFloat('incrByFloat', 1.5), '', 0.01);
        $this->assertEquals(5.001, $cache->incrByFloat('incrByFloat', 3.501), '', 0.0001);
        $this->assertEquals(2.5, $cache->incrByFloat('incrByFloat', -2.501), '', 0.0001);
        $this->assertFalse($cache->incrByFloat('incrByFloat', 'str'));
        $this->assertFalse($cache->incrByFloat('notNum', 1.2));
    }

    /**
     * @depends testSet
     */
    public function testDecr() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('decr'));
        $this->assertEquals(-1, $cache->decr('decr'));
        $this->assertEquals(-3, $cache->decr('decr', 2));
        $this->assertEquals(-2, $cache->decr('decr', -1));
        $this->assertFalse($cache->decr('decr', 'str'));
        $this->assertFalse($cache->decr('notNum'));
    }

    public function testMSet() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('mset1'));
        $this->assertTrue($cache->del('mset2'));
        $this->assertTrue($cache->del('mset3'));
        $this->assertTrue($cache->mSet(['mset1' => 1, 'mset2' => 2, 'mset3' => 3]));
    }

    /**
     * @depends testMSet
     */
    public function testMSetNX() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('msetnx1'));
        $this->assertTrue($cache->del('msetnx2'));
        $this->assertTrue($cache->del('msetnx3'));
        $this->assertFalse($cache->mSetNX(['mset1' => 1, 'mset2' => 2, 'msetnx3' => 3]));
        $this->assertTrue($cache->mSetNX(['msetnx1' => 1, 'msetnx2' => 2, 'msetnx3' => 3]));
    }

    /**
     * @depends testMSet
     */
    public function testMGet() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $sets = $cache->mGet(['mset1', 'mset2', 'mset3']);
        foreach ($sets as &$set) {
            //Redis 会自动将数字转为字符串，此处做兼容，不做严格验证
            $set !== false && $set = (int) $set;
        }
        $this->assertArraySubset(['mset1' => 1, 'mset2' => 2, 'mset3' => 3], $sets, true);
        $sets = $cache->mGet(['mset1', 'mset2', 'mset4']);
        foreach ($sets as &$set) {
            //Redis 会自动将数字转为字符串，此处做兼容，不做严格验证
            $set !== false && $set = (int) $set;
        }
        $this->assertArraySubset(['mset1' => 1, 'mset2' => 2, 'mset4' => false], $sets, true);
        $sets = $cache->mGet(['msetnx1', 'msetnx2', 'msetnx3']);
        foreach ($sets as &$set) {
            //Redis 会自动将数字转为字符串，此处做兼容，不做严格验证
            $set !== false && $set = (int) $set;
        }
        $this->assertArraySubset(['msetnx1' => 1, 'msetnx2' => 2, 'msetnx3' => 3], $sets, true);
    }

}
