<?php

use Cachearium\CacheAbstract;
use Cachearium\CacheKey;
use Cachearium\CacheData;
use Cachearium\Backend\CacheMemcached;
use Cachearium\Backend\CacheRAM;
use Cachearium\Backend\CacheFilesystem;

class CacheBasicTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	static public function setUpBeforeClass() {
		CacheMemcached::singleton([['localhost', 11211]]); // init server
		CacheAbstract::clearAll();
	}

	static public function tearDownAfterClass() {
	}

	public function testFactory() {
		try {
			CacheAbstract::factory("invalidbackend");
			$this->assertTrue(false);
		}
		catch (CacheInvalidBackendException $e) {
			$this->assertTrue(true);
		}
	}

	private function setGetClean(CacheAbstract $cache) {
		$base = 'base';

		// enable
		$this->assertTrue($cache->isEnabled());
		$cache->enable(false);
		$this->assertFalse($cache->isEnabled());
		$cache->enable(true);
		$this->assertTrue($cache->isEnabled());
		$cache->disable();
		$this->assertFalse($cache->isEnabled());
		$cache->enable(true);
		$this->assertTrue($cache->isEnabled());

		$cache->setDefaultLifetime(3600);
		$this->assertEquals(3600, $cache->getDefaultLifetime());

		$key1 = new CacheKey($base, 1);
		$cache->clean($key1);

		try {
			$data = $cache->get($key1);
			$this->fail();
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->assertTrue(true);
		}

		$retval = $cache->store(234, $key1);
		$this->assertTrue($retval);

		try {
			$data = $cache->get($key1);
			$this->assertEquals(234, $data);
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}

		// sleep(1);

		try {
			$data = $cache->get($key1);
			$this->assertEquals(234, $data);
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}

		$cache->clean($key1);
		try {
			$data = $cache->get($key1);
			$this->fail();
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->assertTrue(true);
		}

		$key2 = new CacheKey($base, 2, 'a');
		$key3 = new CacheKey($base, 3, 'a');
		// now change again and delete
		$retval = $cache->store(234, $key2);
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->get($key2);
			$this->assertEquals(234, $data);
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->delete($key2));

		// test null
		$retval = $cache->store(null, $key3);
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->get($key3);
			$this->assertEquals(null, $data);
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->delete($key3));

		$this->assertArrayHasKey(CacheLogEnum::ACCESSED, $cache->getLogSummary());
		$this->assertGreaterThan(0, $cache->getLogSummary()[CacheLogEnum::ACCESSED]);
	}

	public function testSetGetCleanRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->setGetClean($cache);
		}
	}

	public function testSetGetCleanFS() {
		$cache = CacheFilesystem::singleton();
		if ($cache->isEnabled()) {
			$this->setGetClean($cache);
		}
	}

	public function testSetGetCleanMemcached() {
		$cache = CacheMemcached::singleton([['localhost', 11211]]);
		if ($cache->isEnabled()) {
			$this->setGetClean($cache);
		}
	}

	private function getStoreData(CacheAbstract $cache) {
		$base = 'base';

		$this->assertTrue($cache->isEnabled());
		$cache->setDefaultLifetime(3600);
		$this->assertEquals(3600, $cache->getDefaultLifetime());

		// clean
		$key1 = new CacheKey($base, 1);
		$cache->clean($key1);

		// nothing there
		try {
			$data = $cache->get($key1);
			$this->fail();
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->assertTrue(true);
		}

		// store
		$cd = new CacheData(234, $key1);
		$retval = $cache->storeData($cd);
		$this->assertTrue($retval);

		// get
		try {
			$data = $cache->getData($key1);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals(234, $data->getFirstData());
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}

		sleep(1);

		try {
			$data = $cache->getData($key1);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals(234, $data->getFirstData());
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}

		// clean
		$cache->clean($key1);
		try {
			$data = $cache->getData($key1);
			$this->fail();
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->assertTrue(true);
		}

		// check conflicts
		$key2 = new CacheKey($base, 2, 'a');
		$key3 = new CacheKey($base, 3, 'a');
		// now change again and delete
		$retval = $cache->storeData(new CacheData(234, $key2));
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->getData($key2);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals(234, $data->getFirstData());
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->delete($key2));

		// test null
		$retval = $cache->storeData(new CacheData(null, $key3));
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->getData($key3);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals(null, $data->getFirstData());
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->delete($key3));
	}

	public function testgetStoreDataRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->getStoreData($cache);
		}
	}

	public function testgetStoreDataMemcached() {
		$cache = CacheMemcached::singleton([['localhost', 11211]]);
		if ($cache->isEnabled()) {
			$this->getStoreData($cache);
		}
	}

	public function testgetStoreDataFS() {
		$cache = CacheFilesystem::singleton();
		if ($cache->isEnabled()) {
			$this->getStoreData($cache);
		}
	}

	private function dependency(CacheAbstract $cache) {
		// store
		$key1 = new CacheKey('Namespace', 'Subname');
		$cd = new CacheData('xxxx', $key1);
		$depkey = new CacheKey('Namespace', 'SomeDep');
		$cd->addDependency($depkey);
		$cache->storeData($cd);

		// check if it is cached
		try {
			$data = $cache->getData($key1);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals('xxxx', $data->getFirstData());
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->fail();
		}

		// invalidate a dependency
		$cache->invalidate($depkey);

		// get the original and it should be uncached
		try {
			$data = $cache->getData($key1);
			$this->fail();
		}
		catch (Cachearium\Exceptions\NotCachedException $e) {
			$this->assertTrue(true);
		}
	}

	public function testdependencyRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->dependency($cache);
		}
	}

	public function testdependencyMemcached() {
		$cache = CacheMemcached::singleton([['localhost', 11211]]);
		if ($cache->isEnabled()) {
			$this->dependency($cache);
		}
	}

	public function testdependencyFS() {
		$this->markTestSkipped();
		$cache = CacheFilesystem::singleton();
		if ($cache->isEnabled()) {
			$this->dependency($cache);
		}
	}
}
