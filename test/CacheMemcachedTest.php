<?php

use Cachearium\CacheAbstract;
use Cachearium\Backend\CacheRAM;
use Cachearium\CacheKey;
use Cachearium\Backend\CacheMemcached;

class CacheMemcachedTest extends PHPUnit_Framework_TestCase {

	protected function setUp() {
		if (!extension_loaded('memcached')) {
			$this->markTestSkipped('The Memcached extension is not available.');
		}
		// ob_start();
	}

	public function testNamespace() {
		$cache = CacheMemcached::singleton();
		$this->assertEquals($cache, $cache->setNamespace("testmem"));
		$this->assertEquals("testmem", $cache->getNamespace());

		$key = new CacheKey('namespace', 1);
		$cache->store(333, $key);
		try {
			$data = $cache->get($key);
		}
		catch(Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}

		$this->assertEquals($cache, $cache->setNamespace("other"));
		try {
			$data = $cache->get($key);
			$this->fail();
		}
		catch(Cachearium\Exceptions\NotCachedException $e) {
			$this->assertTrue(true);
		}

	}
}