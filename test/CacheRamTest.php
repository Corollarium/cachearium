<?php

use Cachearium\Backend\CacheRAM;

class CacheRamTest extends PHPUnit_Framework_TestCase {


	public function testNothing() {
		CacheRAM::singleton();
	}
}