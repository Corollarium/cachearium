<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

/**
 * This class caches on local RAM, only for the duration of this execution.
 *
 * It's a very simple implementation. It's reasonably inneficient because it
 * a 3-level array, but it does invalidation correctly.
 *
 * This is useful for data that is loaded many times in one execution but
 * which may change constantly, or in servers that have no external cache
 * support for a quick speedup.
 *
 * @author corollarium
 */
class CacheRAM extends CacheAbstract {
	private $storage = array(); // storage

	// @codeCoverageIgnoreStart
	/**
	 * Cache constructor (this is a singleton).
	 *
	 * @return Cache The cache singleton.
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
		return $k->base . $k->id . serialize($k->sub);
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
	 *
	 * @param CacheKey $k
	 * @return CacheData
	 * @throws NotCachedException
	 */
	public function getDataK(CacheKey $k) {
		$cd = CacheData::unserialize($this->getK($k));

		if ($cd->checkUpdateToDate($this)) {
			return $cd;
		}
		$this->deleteK($k);
		throw new NotCachedException();
	}

	public function incrementK($value, CacheKey $k, $default = 0) {
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

		$idx = $k->base . $k->id;
		if (isset($this->storage[$idx]) && isset($this->storage[$idx][$sub])) {
			$this->storage[$idx][$sub] += $value;
		}
		else {
			$this->storage[$idx][$sub] = $default;
		}
		return $this->storage[$idx][$sub];
	}

	public function getK(CacheKey $k) {
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

		$idx = $k->base . $k->id;
		if (isset($this->storage[$idx])
			and array_key_exists($sub, $this->storage[$idx])
		) {
			$this->log(CacheLogEnum::ACCESSED, $k);
			return $this->storage[$idx][$sub];
		}
		$this->log(CacheLogEnum::MISSED, $k);
		throw new NotCachedException();
	}

	public function storeK($data, CacheKey $k, $lifetime = 0) {
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

		$this->storage[$k->base . $k->id][$sub] = $data;
		return true;
	}

	public function deleteK(CacheKey $k) {
		if (!is_string($k->sub)) {
			$sub = md5(serialize($k->sub));
		}
		else {
			$sub = $k->sub;
		}

		$this->checkValidArgs($k);

		unset($this->storage[$k->base . $k->id][$sub]);
		return true;
	}

	public function clean($base, $id) {
		unset($this->storage[$base . $id]);
		return true;
	}

	public function clear() {
		$this->storage = array();
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
