<?php

use Cachearium\CacheAbstract;
use Cachearium\CacheKey;
use Cachearium\CacheData;
use Cachearium\CacheLogEnum;
use Cachearium\Backend\CacheMemcached;
use Cachearium\Backend\CacheRAM;
use Cachearium\Backend\CacheFilesystem;

class CacheCallbackTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	static public function setUpBeforeClass() {
		CacheMemcached::singleton()->addServers([['localhost', 11211]]); // init server
		CacheAbstract::clearAll();
	}

	protected function _callback() {
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
			$this->_callback($cache);
		}
	}

	public function testcallbackFS() {
		$this->markTestSkipped();
		$cache = CacheFilesystem::singleton();
		if ($cache->isEnabled()) {
			$this->_callback($cache);
		}
	}
}