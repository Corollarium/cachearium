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
			$cache->get($key);
			$this->assertTrue(false);
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->assertTrue(true);
		}

		$this->assertEquals(5, $cache->increment(1, $key, 5));
		$this->assertTrue($cache->store(10, $key));
		$this->assertTrue($cache->delete($key));
		$this->assertTrue($cache->clean($key));
		$this->assertTrue($cache->clear());
		$this->assertFalse($cache->start($key));
		$cache->end();
		$cache->prefetch(array());
		$cache->enable(true);
	}
}
