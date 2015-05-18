<?php

use Cachearium\CacheKey;

class MockCachedClass implements Cachearium\Cached, Cachearium\CachedObject {
	public function cacheClean() {
		return;
	}

	public function getCacheKey() {
		return new CacheKey('MockCachedClass', 1);
	}
}

class CacheInterfaceTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	public function testInterface() {
		$c = new MockCachedClass();

		$c->cacheClean();

		$k = $c->getCacheKey();
		$this->assertInstanceOf('Cachearium\CacheKey', $k);
		$this->assertEquals('MockCachedClass', $k->getBase());
		$this->assertEquals(1, $k->getId());
	}
}
