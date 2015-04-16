<?php

namespace Cachearium\Backend;

use Cachearium\CacheAbstract;

/**
 * Caches in APC
 *
 */
class CacheAPC extends CacheAbstract {
	// @codeCoverageIgnoreStart
	/**
	 * Cache constructor (this is a singleton).
	 *
	 * @return Cache The cache singleton.
	 */
	static public function singleton() {
		static $instances;

		if (!isset($instances)) {
			$instances = new CacheAPC();
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
		$this->enable();
	}

	public function enable() {
		if (!extension_loaded('apc')) {
			$this->enabled = false;
			return false;
		}
		return parent::enable();
	}

	private function checkValidArgs($base, $id, $sub) {
		if (is_array($base) || is_array($id) || !is_string($sub)) {
			throw new CacheInvalidDataException('Invalid get parameter');
		}
	}

	public function get($base, $id, $sub = LH_DEFAULT_CACHE_ID) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			throw new NotCachedException();
		}
		// @codeCoverageIgnoreEnd

		if (!is_string($sub)) {
			$sub = md5(serialize($sub));
		}
		$this->checkValidArgs($base, $id, $sub);

		$key = (new CacheKey($base, $id, $sub))->getHash();
		$success = false;
		$data = apc_fetch($key, $success);
		if (!$success) {
			$this->log(CacheLogEnum::MISSED, $base, $id, $sub);
			throw new NotCachedException();
		}
		return $data;
	}

	public function store($data, $base, $id, $sub = LH_DEFAULT_CACHE_ID, $lifetime = 0) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		if (!is_string($sub)) {
			$sub = md5(serialize($sub));
		}
		$this->checkValidArgs($base, $id, $sub);

		$key = (new CacheKey($base, $id, $sub))->getHash();
		apc_store($key, $data, $lifetime);
		return true;
	}

	public function delete($base, $id, $sub = LH_DEFAULT_CACHE_ID) {
		if (!is_string($sub)) {
			$sub = md5(serialize($sub));
		}

		$this->checkValidArgs($base, $id, $sub);

		$key = (new CacheKey($base, $id, $sub))->getHash();
		apc_delete($key);
		return true;
	}

	public function clean($base, $id) {
		// TODO
		return true;
	}

	public function clear() {
		apc_clear_cache('user');
		return true;
	}

	public function prefetch($data) {
		// nothing.
	}

	public function report() {
		if ($this->should_log == false) {
			return;
		}
		// TODO
	}
}
