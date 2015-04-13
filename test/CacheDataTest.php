<?php

use Cachearium\CacheAbstract;
use Cachearium\CacheData;
use Cachearium\CacheKey;

define("CALLBACKDATATESTERVALUE", "thatstuff");

function callbackDataTester() {
	return CALLBACKDATATESTERVALUE;
}


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
		$cache = Cachearium\Backend\CacheRAM::singleton();
		$ck1 = new CacheKey('callback', 1, 'sub');
		$ck2 = new CacheKey('callback', 2, 'sub');
		$cd1 = new CacheData(null, $ck1);
		$cd2 = new CacheData(null, $ck2);

		$cd2->appendCallback('callbackDataTester');
		$cd1->appendData('something');
		$cd1->appendRecursionData($cd2);
		$this->assertTrue($cache->storeData($cd2));
		$this->assertTrue($cache->storeData($cd1));
		$this->assertNotFalse($cache->getData($ck2));
		$this->assertNotFalse($cache->getData($ck1));

		$this->assertEquals('something' . CALLBACKDATATESTERVALUE, $cd1->stringify($cache));
	}

	public function testDependencies() {
		$cache = Cachearium\Backend\CacheRAM::singleton();

		$ck1 = new CacheKey('recursion', 1, 'sub');
		$ck2 = new CacheKey('recursion', 2, 'sub');
		$ck3 = new CacheKey('recursion', 3, 'sub');
		$cd1 = new CacheData('this', $ck1);
		$cd2 = new CacheData('is', $ck2);
		$cd3 = new CacheData('recursion', $ck3);
		$cd2->appendRecursionData($cd3);
		$cd1->appendRecursionData($cd2);
		$this->assertTrue($cache->storeData($cd3));
		$this->assertTrue($cache->storeData($cd2));
		$this->assertTrue($cache->storeData($cd1));
		$this->assertNotFalse($cache->getData($ck1));
		$this->assertNotFalse($cache->getData($ck2));
		$this->assertNotFalse($cache->getData($ck3));

		$this->assertEquals('thisisrecursion', $cd1->stringify($cache));

		$cd2 = new CacheData('breaks', $ck2);
		$this->assertTrue($cache->storeData($cd2));
		try {
			$cache->getData($ck1);
			$this->fail();
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->assertTrue(true);
		}
	}
}
