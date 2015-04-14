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

function callbackTesterStart() {
	return CALLBACKVALUE . rand();
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
			$this->_callback($cache);
		}
	}

	public function testcallbackFS() {
		$cache = CacheFilesystem::singleton();
		if ($cache->isEnabled()) {
			$this->_callback($cache);
		}
	}

	protected function _startcallback(CacheAbstract $cache) {
		$key = new CacheKey("startcallback", 1);

		$this->assertFalse($cache->start($key));
		echo "something ";
		$cache->appendCallback('callbackTesterStart');
		echo " otherthing";
		$output = $cache->end(false);

		$this->assertContains(CALLBACKVALUE, $output);

		// run again, we should have another value
		$second = $cache->start($key);
		$this->assertNotFalse($second);
		$this->assertContains(CALLBACKVALUE, $second);
		$this->assertNotEquals($second, $output);
	}

	public function teststartCallbackRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->_startcallback($cache);
		}
	}

	public function teststartCallbackMemcached() {
		$cache = CacheMemcached::singleton();
		if ($cache->isEnabled()) {
			$this->_startcallback($cache);
		}
	}

	public function teststartCallbackFS() {
		$cache = CacheFilesystem::singleton();
		if ($cache->isEnabled()) {
			$this->_startcallback($cache);
		}
	}
}