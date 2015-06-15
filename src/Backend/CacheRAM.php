<?php

namespace Cachearium\Backend;

use Cachearium\CacheAbstract;
use Cachearium\CacheKey;
use Cachearium\CacheData;
use Cachearium\CacheLogEnum;
use Cachearium\Exceptions\NotCachedException;


/**
 * This class caches on local RAM, only for the duration of this execution.
 *
 * It's a very simple implementation. It's reasonably inneficient because it
 * a 3-level array, but it does invalidation correctly.
 *
 * This is useful for data that is loaded many times in one execution but
 * which may change constantly, or in servers that have no external cache
 * support for a quick speedup.
 */
class CacheRAM extends CacheAbstract {
	private $storage = array(); // storage

	private $memoryLimit = 500000000;

	// @codeCoverageIgnoreStart
	/**
	 * Cache constructor (this is a singleton).
	 *
	 * @return CacheRAM The cache singleton.
	 *
	 */
	static public function singleton() {
		static $instances;

		if (!isset($instances)) {
			$instances = new CacheRAM();
		}
		return $instances;
	}

	// Prevent users to clone the instance
	public function __clone() {
		trigger_error('Cloning is not allowed.', LH_TRIGGER_UNEXPECTED);
	}
	// @codeCoverageIgnoreEnd

	/**
	 * Constructor.
	 * @codeCoverageIgnore
	 */
	private function __construct() {
	}

	protected function hashKey(CacheKey $k) {
		return $this->namespace . $k->base . $k->id . serialize($k->sub);
	}

	/**
	 * @param CacheKey $k
	 * @throws CacheInvalidDataException
	 * @codeCoverageIgnore
	 */
	private function checkValidArgs(CacheKey $k) {
		if (is_array($k->base) || is_array($k->id)) {
			throw new CacheInvalidDataException('Invalid get parameter');
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::getData($k)
	 */
	public function getData(CacheKey $k) {
		$cd = CacheData::unserialize($this->get($k));

		if ($cd->checkUpdateToDate($this)) {
			return $cd;
		}
		$this->delete($k);
		throw new NotCachedException();
	}

	public function increment($value, CacheKey $k, $default = 0) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return $default;
		}
		// @codeCoverageIgnoreEnd

		if (!is_string($k->sub)) {
			$sub = md5(serialize($k->sub));
		}
		else {
			$sub = $k->sub;
		}
		$this->checkValidArgs($k);

		$idx = $this->namespace . $k->base . $k->id;
		if (isset($this->storage[$idx]) && isset($this->storage[$idx][$sub])) {
			$this->storage[$idx][$sub] += $value;
		}
		else {
			$this->storage[$idx][$sub] = $default;
		}
		return $this->storage[$idx][$sub];
	}

	public function get(CacheKey $k) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			throw new NotCachedException();
		}
		// @codeCoverageIgnoreEnd

		if (!is_string($k->sub)) {
			$sub = md5(serialize($k->sub));
		}
		else {
			$sub = $k->sub;
		}
		$this->checkValidArgs($k);

		$idx = $this->namespace . $k->base . $k->id;
		if (isset($this->storage[$idx])
			and array_key_exists($sub, $this->storage[$idx])
		) {
			$this->log(CacheLogEnum::ACCESSED, $k);
			return $this->storage[$idx][$sub];
		}
		$this->log(CacheLogEnum::MISSED, $k);
		throw new NotCachedException();
	}

	public function store($data, CacheKey $k, $lifetime = 0) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		if (!is_string($k->sub)) {
			$sub = md5(serialize($k->sub));
		}
		else {
			$sub = $k->sub;
		}
		$this->checkValidArgs($k);

		$this->storage[$this->namespace . $k->base . $k->id][$sub] = $data;
		return true;
	}

	public function delete(CacheKey $k) {
		if (!is_string($k->sub)) {
			$sub = md5(serialize($k->sub));
		}
		else {
			$sub = $k->sub;
		}

		$this->checkValidArgs($k);

		unset($this->storage[$this->namespace . $k->base . $k->id][$sub]);
		return true;
	}

	public function cleanP($base, $id) {
		unset($this->storage[$this->namespace . $base . $id]);
		return true;
	}

	public function clear() {
		$this->storage = array();
		return true;
	}

	public function getMemoryLimit($memoryLimit) {
		return $this->memoryLimit;
	}

	/**
	 *
	 * @param integer $memoryLimit
	 * @return \Cachearium\Backend\CacheRAM
	 */
	public function setMemoryLimit($memoryLimit) {
		$this->memoryLimit = $memoryLimit;
		return $this;
	}

	/**
	 * Clears cache if PHP memory usage is above a chosen limit
	 * This checks the ENTIRE PHP memory usage, which may be a lot more
	 * than what is used by this backend.
	 *
	 * @return boolean
	 */
	public function limitRAM() {
		if (memory_get_usage() > $this->memoryLimit) {
			$this->clear();
		}
		return true;
	}

	/**
	 * (non-PHPdoc)
	 * @see CacheAbstract::prefetch()
	 * @codeCoverageIgnore
	 */
	public function prefetch($data) {
		// nothing.
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function report() {
		if ($this->should_log == false) {
			return;
		}
		echo '<div><h2>Cache RAM system</h2>';
		echo '<h3>System is: ' . ($this->enabled ? 'enabled' : 'disabled') . '</h3>';
		$stats = array_fill_keys(array_keys(CacheLogEnum::getNames()), 0);
		echo '<ul>';
		foreach ($this->cache_log as $entry) {
			echo '<li>' . CacheLogEnum::getName($entry['status']) . $entry['message'] . '</li>';
			$stats[$entry['status']]++;
		}
		echo '</ul>';

		echo '<ul>';
		foreach ($stats as $key => $val) {
			echo '<li>' . CacheLogEnum::getName($key) . '=' . $val . '</li>';
		}
		echo '</ul>';

		echo '</ul></div>';
	}
}
