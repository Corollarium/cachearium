<?php

class CacheKeyTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	public function testCacheKey() {
		$ck = new CacheKey('base', 'id', 'sub');
		$this->assertEquals('base', $ck->getBase());
		$this->assertEquals('id', $ck->getId());
		$this->assertEquals('sub', $ck->getSub());

		$ck = new CacheKey(null, null);
		$ck->setBase('base')
			->setId('id')
			->setSub('sub');
		$this->assertEquals('base', $ck->getBase());
		$this->assertEquals('id', $ck->getId());
		$this->assertEquals('sub', $ck->getSub());
		$this->assertNotNull($ck->getHash());
	}
}
