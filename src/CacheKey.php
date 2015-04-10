<?php

namespace Cachearium;

/**
 * Cache keys have three conceptual levels. This is useful for namespacing.
 *
 */
class CacheKey {
	/**
	 * This is the base key. It's the main index, so to speak. This is useful as a
	 * first level to separate cache data logically.
	 *
	 * @var string $base
	 */
	public $base;

	/**
	 * This is the second key, usually an id.
	 * @var string
	 */
	public $id;

	/**
	 *
	 * @var string
	 */
	public $sub;

	/**
	 * @param string $base Base string name for the type of cache (e.g., Event)
	 * @param string $id Item id
	 * @param string $sub If an item is cache in parts, this is used to specify the parts.
	 */
	public function __construct($base, $id, $sub = '') {
		$this->base = $base;
		$this->id = $id;
		$this->sub = $sub;
	}

	public function getBase() {
		return $this->base;
	}

	public function getId() {
		return $this->id;
	}

	public function getSub() {
		return $this->sub;
	}

	public function setBase($base) {
		$this->base = $base;
		return $this;
	}

	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	public function setSub($sub) {
		$this->sub = $sub;
		return $this;
	}

	/**
	 * Returns a hash for key.
	 * @return string
	 */
	public function getHash() {
		return md5($this->base . $this->id . $this->sub);
	}

	/**
	 * Prints as a pretty string for debugging
	 * @return string
	 * @codeCoverageIgnore
	 */
	public function debug() {
		return $this->base . ", " . $this->id . ", " . print_r($this->sub, true);
	}
}
