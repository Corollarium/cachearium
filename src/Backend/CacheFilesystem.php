<?php

namespace Cachearium\Backend;

use Cachearium\CacheAbstract;
use Cachearium\CacheKey;

require_once(__DIR__ . '/external/Timed.php');

/**
 * Caches in filesystem
 *
 */
class CacheFilesystem extends CacheAbstract {
	/**
	 *
	 * @var Cache_Lite_Timed
	 */
	private $cache;

	private $namespace = "none";

	private $path = '/tmp/';

	// @codeCoverageIgnoreStart
	/**
	 * Cache constructor (this is a singleton).
	 *
	 * @return CacheFS The cache singleton.
	 */
	static public function singleton() {
		static $instances;

		if (!isset($instances)) {
			$instances = new CacheFilesystem();
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
		$this->lifetime = 3600 * 24 * 30;
		$this->enable(true);
	}

	/**
	 * Sets path to store data
	 *
	 * @param string $path
	 * @throws RuntimeException
	 * @return CacheFS
	 * @codeCoverageIgnore
	 */
	public function setPath($path) {
		if (!is_writable($this->path) || !file_exists($this->path) || !is_dir($this->path)) {
			throw new RuntimeException('Invalid dir or missing permissions');
		}

		$this->path = $path . '/';

		// reload
		if ($this->isEnabled()) {
			$this->enable(true);
		}

		return $this;
	}

	public function enable($boolean = true) {
		if ($boolean) {
			// ok, go
			$this->cache = new \Cache_Lite_Timed(
				array(
					'cacheDir' => $this->path,
					'lifeTime' => $this->getDefaultLifetime(), // in seconds
					'automaticCleaningFactor' => 200,
					'hashedDirectoryLevel' => 1,
				)
			);
		}
		return parent::enable($boolean);
	}

	protected function hashKey(CacheKey $k) {
		$group = $this->namespace .  $k->base . $k->id;
		return $group;
	}

	public function increment($value, CacheKey $k, $default = 0) {
		throw new CacheUnsupportedOperation();
	}

	/**
	 * Get cached entry.
	 *
	 * @param string $base Base string name for the type of cache (e.g., Event)
	 * @param string $id Item id
	 * @param string $sub If an item is cache in parts, this is used to specify the parts.
	 * @return string or FALSE if nothing found.
	 */
	public function get(CacheKey $k) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			throw new NotCachedException();
		}
		// @codeCoverageIgnoreEnd

		$group = $this->hashKey($k);
		if (!is_string($k->sub)) {
			$cacheid = md5(serialize($k->sub));
		}
		else {
			$cacheid = $k->sub;
		}
		$retval = $this->cache->get($cacheid, $group);

		$this->log(
			($retval !== false ? CacheLogEnum::ACCESSED : CacheLogEnum::MISSED),
			$k
		);

		if ($retval) {
			return unserialize($retval);
		}
		throw new NotCachedException();
	}

	/**
	 * Saves cache information.
	 *
	 * @param string $base Base string name for the type of cache (e.g., Event)
	 * @param string $id Item id
	 * @param array $sub If an item is cache in parts, this is used to specify the parts.
	 * @param any $data Data to save in cache
	 * @return boolean true if no problem
	 */
	public function store($data, CacheKey $k, $lifetime = -1) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		$group = $this->hashKey($k);
		if (!is_string($k->sub)) {
			$cacheid = md5(serialize($k->sub));
		}
		else {
			$cacheid = $k->sub;
		}
		return $this->cache->save(
			serialize($data), $cacheid, $group, ($lifetime < 0 ? $this->getDefaultLifetime() : $lifetime)
		);
	}

	/**
	 * Deletes an entry from the cache
	 *
	 * @param string $base Base string name for the type of cache (e.g., Event)
	 * @param string $id Item id
	 * @param array $sub If an item is cache in parts, this is used to specify the parts.
	 * @return boolean
	 */
	public function delete(CacheKey $k) {
		$group = $this->hashKey($k);
		if (!is_string($k->sub)) {
			$cacheid = md5(serialize($k->sub));
		}
		else {
			$cacheid = $k->sub;
		}
		$this->log(CacheLogEnum::DELETED, $k);
		return $this->cache->remove($cacheid, $group);
	}

	public function setDefaultLifetime($lifetime = 0) {
		parent::setDefaultLifetime($lifetime);
		$this->cache->setLifeTime($this->getDefaultLifetime());
	}

	/**
	 * Cleans cache for a given type/id.
	 *
	 * @param string $base Base string name for the type of cache (e.g., Event)
	 * @param string $id Item id
	 * @return boolean true if no problem
	 */
	public function cleanP($base, $id) {
		if (!$this->enabled) {
			return false;
		}

		$group = $this->hashKey(new CacheKey($base, $id));
		$retval = $this->cache->clean($group, 'ingroup');
		$this->log(CacheLogEnum::CLEANED, new CacheKey($base, $id));
		return $retval;
	}

	public function clear() {
		if ($this->cache) {
			$this->cache->clean();
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

	// @codeCoverageIgnoreStart
	public function report() {
		if ($this->should_log == false) {
			return;
		}
		echo '<div class="cachearium cachearium-filesystem"><h2>Cache FS system</h2>';
		echo '<h3>System is: ' . ($this->enabled ? 'enabled' : 'disabled') . '</h3>';
		echo '<ul>';
		foreach ($this->cache_log as $entry) {
			echo '<li>' . CacheLogEnum::getName($entry['status']) . $entry['message'] . '</li>';
		}
		echo '</ul></div>';
	}
	// @codeCoverageIgnoreEnd
}
