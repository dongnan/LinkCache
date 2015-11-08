<?php

/**
 * linkcache - 一个灵活高效的PHP缓存工具库
 *
 * @author      Dong Nan <hidongnan@gmail.com>
 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
 * @link        http://git.oschina.net/dongnan/LinkCache
 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
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
        $this->assertTrue($cache->set('test2', [1, 2], 1));
        $this->assertTrue($cache->set('test_change', [2, 3], 1));
        $this->assertTrue($cache->set('test_change', [3, 4]));
        $this->assertTrue($cache->set('testDel', 'del'));
        $this->assertTrue($cache->set('notNum', 'notNum'));
    }

    /**
     * @depends testSet
     */
    public function testGet() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(1, $cache->get('test1'));
        $this->assertArraySubset([1, 2], $cache->get('test2'), true);
        $this->assertArraySubset([3, 4], $cache->get('test_change'), true);
        $this->assertEquals('del', $cache->get('testDel'));
        $this->assertFalse($cache->get('notExist'));
        sleep(2);
        $this->assertFalse($cache->get('test2'));
        //不会过期
        $this->assertArraySubset([3, 4], $cache->get('test_change'), true);
    }

    public function testSetDE() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->setDE('test3', 'delay_expire', 1));
        $this->assertTrue($cache->setDE('test4', ['delay' => 1, 'expire' => 2], 1));
    }

    /**
     * @depends testSetDE
     */
    public function testGetDE() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals('delay_expire', $cache->getDE('test3'));
        $this->assertArraySubset(['delay' => 1, 'expire' => 2], $cache->getDE('test4'), true);
        sleep(2);
        $isExpired1 = null;
        $this->assertEquals('delay_expire', $cache->getDE('test3', $isExpired1));
        $this->assertTrue($isExpired1);
        $isExpired2 = null;
        $this->assertArraySubset(['delay' => 1, 'expire' => 2], $cache->getDE('test4', $isExpired2), true);
        $this->assertTrue($isExpired2);
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

    /**
     * @depends testSetDE
     * @depends testGetDE
     */
    public function testHasDE() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->has('test3'));
        $this->assertFalse($cache->hasDE('test3'));
    }

    public function testPersist() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testPersist'));
        $this->assertTrue($cache->set('testPersist', 'persist', 600));
        $this->assertTrue($cache->persist('testPersist'));
    }

    public function testExpire() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testExpire'));
        $this->assertTrue($cache->set('testExpire', 'expire'));
        $this->assertTrue($cache->set('testExpirePersist', 'expire', 1));
        $this->assertTrue($cache->expire('testExpire', 1));
        $this->assertTrue($cache->expire('testExpirePersist', -1));
    }

    public function testExpireAt() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testExpireAt'));
        $this->assertTrue($cache->set('testExpireAt', 'expireAt'));
        $this->assertTrue($cache->set('testExpireAtDel', 'expireAtDel'));
        $this->assertTrue($cache->expireAt('testExpireAt', time() + 1));
        $this->assertTrue($cache->expireAt('testExpireAtDel', time() - 1));
    }

    public function testExpireDE() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testExpireDE'));
        $this->assertTrue($cache->set('testExpireDE', 'expireDE'));
        $this->assertTrue($cache->expireDE('testExpireDE', 1));
    }

    public function testExpireAtDE() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testExpireAtDE'));
        $this->assertTrue($cache->set('testExpireAtDE', 'expireAtDE'));
        $this->assertTrue($cache->expireAtDE('testExpireAtDE', time() + 1));
    }

    /**
     * @depends testSet
     * @depends testSetnx
     * @depends testExpire
     * @depends testExpireAt
     * @depends testPersist
     */
    public function testTtl() {
        sleep(2);
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertEquals(-2, $cache->ttl('testExpire'));
        $this->assertEquals(-1, $cache->ttl('testExpirePersist'));
        $this->assertEquals(-2, $cache->ttl('testExpireAt'));
        $this->assertEquals(-2, $cache->ttl('testExpireAtDel'));
        $this->assertEquals(-1, $cache->ttl('testPersist'));
        $this->assertEquals(-2, $cache->ttl('testNotExist'));
        $this->assertEquals(-2, $cache->ttl('setnx2'));
    }

    /**
     * @depends testExpireDE
     * @depends testExpireAtDE
     */
    public function testTtlDE() {
        sleep(2);
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertGreaterThan(0, $cache->ttl('testExpireDE'));
        $this->assertEquals(-2, $cache->ttlDE('testExpireDE'));
        $this->assertGreaterThan(0, $cache->ttl('testExpireAtDE'));
        $this->assertEquals(-2, $cache->ttlDE('testExpireAtDE'));
    }

    public function testLock() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('testLock'));
        $this->assertTrue($cache->lock('testLock'));
        $this->assertFalse($cache->lock('testLock'));
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
     * @depends testLock
     */
    public function testUnLock() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->unlock('testLock'));
        $this->assertFalse($cache->isLock('testLock'));
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
        $this->assertEquals(1.5, $cache->incrByFloat('incrByFloat', 1.5), '', 0.1);
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
        $this->assertTrue($cache->mSet(['mset1' => '1', 'mset2' => [1, 2, 3], 'mset3' => '3']));
    }

    /**
     * @depends testMSet
     */
    public function testMSetNX() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->del('msetnx1'));
        $this->assertTrue($cache->del('msetnx2'));
        $this->assertTrue($cache->del('msetnx3'));
        $this->assertFalse($cache->mSetNX(['mset1' => '1', 'mset2' => [1, 2, 3], 'msetnx3' => '3']));
        $this->assertTrue($cache->mSetNX(['msetnx1' => '1', 'msetnx2' => '2', 'msetnx3' => '3']));
    }

    /**
     * @depends testMSet
     * @depends testMSetNX
     */
    public function testMGet() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertArraySubset(['mset1' => '1', 'mset2' => [1, 2, 3], 'mset3' => '3'], $cache->mGet(['mset1', 'mset2', 'mset3']), true);
        $this->assertArraySubset(['mset1' => '1', 'mset2' => [1, 2, 3], 'mset4' => false], $cache->mGet(['mset1', 'mset2', 'mset4']), true);
        $this->assertArraySubset(['msetnx1' => '1', 'msetnx2' => '2', 'msetnx3' => '3'], $cache->mGet(['msetnx1', 'msetnx2', 'msetnx3']), true);
    }

    /**
     * @depends testMSet
     * @depends testMSetNX
     */
    public function testMDel() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertTrue($cache->mDel(['mset2', 'msetnx2']));
    }

    /**
     * @depends testMSet
     * @depends testMSetNX
     * @depends testMDel
     */
    public function testMHas() {
        $cache = \linkcache\Cache::getInstance($this->cacheDriver);
        $this->assertArraySubset(['mset1', 'mset3'], $cache->mHas(['mset1', 'mset2', 'mset3', 'mset4']), true);
        $this->assertArraySubset([], $cache->mHas(['msetnx4', 'msetnx5']), true);
        $this->assertArraySubset(['msetnx1'], $cache->mHas(['msetnx1', 'msetnx2']), true);
    }

}
