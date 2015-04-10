<?php

use Cachearium\CacheAbstract;
use Cachearium\CacheData;
use Cachearium\CacheKey;
use Cachearium\Backend\CacheNull;

class CacheNullTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	public function testCacheNull() {
		$key = new CacheKey('any', 'thing');

		$cache = CacheNull::singleton();
		$this->assertInstanceOf('Cachearium\Backend\CacheNull', $cache);

		$cache = CacheAbstract::factory('Null');
		$this->assertInstanceOf('Cachearium\Backend\CacheNull', $cache);

		try {
			$cache->getK($key);
			$this->assertTrue(false);
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}

		$this->assertEquals(5, $cache->incrementK(1, $key, 5));
		$this->assertTrue($cache->storeK(10, $key));
		$this->assertTrue($cache->deleteK($key));
		$this->assertTrue($cache->cleanK($key));
		$this->assertTrue($cache->clear());
		$this->assertFalse($cache->startK($key));
		$cache->end();
		$cache->prefetch(array());
		$cache->enable(true);
	}
}
