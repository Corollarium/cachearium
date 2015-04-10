<?php

class CacheLogEnumTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	public function testEnum() {
		$this->assertGreaterThan(0, count(CacheLogEnum::getNames()));
	}
}
