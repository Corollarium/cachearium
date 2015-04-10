<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

require_once(__DIR__ . '/../Cache.php');

class CacheStartEndTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	static public function setUpBeforeClass() {
		CacheAbstract::clearAll();
	}

	static public function tearDownAfterClass() {
	}

	private function _startBasic(CacheAbstract $cache) {
		$expected = "Basic works";
		$key = new CacheKey('startendtest', 'basic', 0);

		// won't be cached.
		if (!$cache->startK($key)) {
			$this->assertTrue(true);
			echo $expected;
		}
		else {
			$this->assertTrue(false);
		}
		$data = $cache->end(false);

		// check if we have the correct data
		$this->assertEquals($expected, $data);

		// now it should be cached
		$this->assertEquals($expected, $cache->startK($key, null, false));
	}

	public function testStartBasicRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->_startBasic($cache);
		}
	}

	private function _startMultiLevel(CacheAbstract $cache) {
		$expected = ["first level", "second level 1", "second level 2", "second level 3"];
		$i = 0;
		if (!$cache->startK(new CacheKey('startmultilevel', 'first', 0), null, false)) {
			$this->assertTrue(true);
			echo $expected[$i++];
			if (!$cache->startK(new CacheKey('startmultilevel', 'second', $i), null, false)) {
				echo $expected[$i++];
				$cache->end();
			}
			if (!$cache->startK(new CacheKey('startmultilevel', 'second', $i), null, false)) {
				echo $expected[$i++];
				$cache->end();
			}
			if (!$cache->startK(new CacheKey('startmultilevel', 'second', $i), null, false)) {
				echo $expected[$i++];
				$cache->end();
			}
		}
		else {
			$this->assertTrue(false);
		}
		$data = $cache->end(false);
		$this->assertEquals(implode('', $expected), $data);
	}

	public function testStartMultiLevel() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->_startMultiLevel($cache);
		}
	}

	private function _startNested(CacheAbstract $cache) {
		$expected = ["first level", "second level 1", "second level 2", "second level 3"];
		$i = 0;
		$data = '';
		if (!($data = $cache->startK(new CacheKey('startnested', 'first', 0), null, false))) {
			$this->assertTrue(true);
			echo $expected[$i++];
			if (!$cache->startK(new CacheKey('startnested', 'second', $i), null, false)) {
				echo $expected[$i++];
				if (!$cache->startK(new CacheKey('startnested', 'second', $i), null, false)) {
					echo $expected[$i++];
					if (!$cache->startK(new CacheKey('startnested', 'second', $i), null, false)) {
						echo $expected[$i++];
						$cache->end();
					}
					$cache->end();
				}
				$cache->end();
			}
			$data = $cache->end(false);
		}
		else {
			$this->assertTrue(false);
		}
		$this->assertEquals(implode('', $expected), $data);
	}

	public function testStartNested() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->_startNested($cache);
		}
	}
}