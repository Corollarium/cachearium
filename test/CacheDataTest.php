<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

require_once(__DIR__ . '/../Cache.php');

class CacheDataTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	static public function setUpBeforeClass() {
		CacheAbstract::clearAll();
	}

	public function testKey() {
		$ck = new CacheKey('base', 'id', 'sub');
		$cd = new CacheData('', $ck);
		$this->assertEquals($cd->key, $ck);

		$ck2 = new CacheKey('base', 'id', 'sub2');
		$cd->setKey($ck2);
		$this->assertEquals($cd->key, $ck2);
	}

	public function testMultiData() {
		$ck1 = new CacheKey('data', 1, 'sub');
		$ck2 = new CacheKey('data', 2, 'sub');
		$ck3 = new CacheKey('data', 3, 'sub');
		$this->assertNotNull($ck1);
		$this->assertNotNull($ck2);
		$this->assertNotNull($ck3);
		$cd1 = new CacheData();
		$cd2 = new CacheData('is', $ck2);
		$cd3 = new CacheData('data', $ck3);
		$this->assertNotNull($cd1);
		$this->assertNotNull($cd2);
		$this->assertNotNull($cd3);
		$this->markTestIncomplete();
	}

	public function testCallback() {
		/*$ck1 = new CacheKey('callback', 1, 'sub');
		$cd1 = new CacheData();
		$cd1->appendCallback($callback);
		$cd1->appendRecursion($cd2);
		$cd2->appendRecursion($cd3);
		$this->assertEquals('thisisrecursion', $cd1->stringify());*/
	}

	public function testDependencies() {
		$ck1 = new CacheKey('recursion', 1, 'sub');
		$ck2 = new CacheKey('recursion', 2, 'sub');
		$ck3 = new CacheKey('recursion', 3, 'sub');
		$cd1 = new CacheData('this', $ck1);
		$cd2 = new CacheData('is', $ck2);
		$cd3 = new CacheData('recursion', $ck3);
		$cd1->appendRecursionData($cd2);
		$cd2->appendRecursionData($cd3);
		$this->assertEquals('thisisrecursion', $cd1->stringify(CacheRAM::singleton()));
	}
}
