<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

require_once(__DIR__ . '/../Cache.php');

class CacheLogEnumTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	public function testEnum() {
		$this->assertGreaterThan(0, count(CacheLogEnum::getNames()));
	}
}
