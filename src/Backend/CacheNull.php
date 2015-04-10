<?php

namespace Cachearium\Backend;

use Cachearium\CacheAbstract;
use Cachearium\CacheKey;
use Cachearium\CacheData;
use Cachearium\CacheLogEnum;
use Cachearium\Exceptions\NotCachedException;

/**
 * Null cache class. Does nothing but implements all required methods.
 *
 */
class CacheNull extends CacheAbstract {
	// @codeCoverageIgnoreStart
	/**
	 * Cache constructor (this is a singleton).
	 *
	 * @return Cache The cache singleton.
	 */
	static public function singleton() {
		static $instances;

		if (!isset($instances)) {
			$instances = new CacheNull();
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
		$this->enable(false);
	}

	public function enable($bool = true) {
	}

	public function get(CacheKey $k) {
		throw new NotCachedException();
	}

	public function increment($value, CacheKey $k, $default = 0) {
		return $default;
	}

	public function store($data, CacheKey $k, $lifetime = 0) {
		return true;
	}

	public function delete(CacheKey $k) {
		return true;
	}

	public function cleanP($base, $id) {
		return true;
	}

	public function clear() {
		return true;
	}

	public function start(CacheKey $k, $lifetime = null, $print = true, $fail = false) {
		return false;
	}

	public function end($print = true) {
		return '';
	}

	public function prefetch($data) {
	}

	/**
	 * @codeCoverageIgnore
	 */
	protected function hashKey(CacheKey $k) {
		return $k->getBase() . $k->getId() . serialize($k->getSub());
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function report() {
	}
}
