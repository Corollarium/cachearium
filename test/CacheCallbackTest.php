<?php

use Cachearium\CacheAbstract;
use Cachearium\CacheKey;
use Cachearium\CacheData;
use Cachearium\CacheLogEnum;
use Cachearium\Backend\CacheMemcached;
use Cachearium\Backend\CacheRAM;
use Cachearium\Backend\CacheFilesystem;

define("CALLBACKVALUE", "banana");

function callbackTester() {
	echo CALLBACKVALUE;
}

class CacheCallbackTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	static public function setUpBeforeClass() {
		CacheMemcached::singleton()->addServers([['localhost', 11211]]); // init server
		CacheAbstract::clearAll();
	}

	protected function _callback(CacheAbstract $cache) {
		$base = 'callback';

		$key1 = new CacheKey($base, 1);
		$cache->clean($key1);

		$this->assertEquals(CALLBACKVALUE, $cache->startCallback($key1, 'callbackTester'));

		try {
			$data = $cache->getData($key1);
			$this->assertEquals(CALLBACKVALUE, $data->stringify($cache));
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}

		// TODO
	}

	public function testcallbackRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->_callback($cache);
		}
	}

	public function testcallbackMemcached() {
		$cache = CacheMemcached::singleton();
		if ($cache->isEnabled()) {
			// $this->_callback($cache);
		}
	}

	public function testcallbackFS() {
		$cache = CacheFilesystem::singleton();
		if ($cache->isEnabled()) {
			// $this->_callback($cache);
		}
	}
}