<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

require_once(__DIR__ . '/../Cache.php');

class MockCachedClass {
	use Cached;
	use CachedObject;

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
		$this->assertInstanceOf('CacheKey', $k);
		$this->assertEquals('MockCachedClass', $k->getBase());
		$this->assertEquals(1, $k->getId());
	}
}
