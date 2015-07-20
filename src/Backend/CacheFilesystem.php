<?php

namespace Cachearium\Backend;

use Cachearium\CacheAbstract;
use Cachearium\CacheKey;
use Cachearium\CacheLogEnum;

require_once(__DIR__ . '/external/Timed.php');

/**
 * Caches in filesystem
 *
 */
class CacheFilesystem extends CacheAbstract {
	/**
	 *
	 * @var \Cache_Lite_Timed
	 */
	private $cache;

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
		$this->enable();
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
			$this->enable();
		}

		return $this;
	}

	public function enable() {
		$this->cache = new \Cache_Lite_Timed(
			array(
				'cacheDir' => $this->path,
				'lifeTime' => $this->getDefaultLifetime(), // in seconds
				'automaticCleaningFactor' => 200,
				'hashedDirectoryLevel' => 1,
				'writeControl' => false,
			)
		);
		return parent::enable();
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::hashKey($k)
	 */
	protected function hashKey(CacheKey $k) {
		$group = $this->namespace .  $k->base . $k->id;
		return $group;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::increment($value, $k, $default)
	 */
	public function increment($value, CacheKey $k, $default = 0) {
		throw new \Cachearium\Exceptions\CacheUnsupportedOperation();
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::get($k)
	 */
	public function get(CacheKey $k) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			throw new \Cachearium\Exceptions\NotCachedException();
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
		throw new \Cachearium\Exceptions\NotCachedException();
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::store($data, $k, $lifetime)
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
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::delete($k)
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

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::setDefaultLifetime($lifetime)
	 */
	public function setDefaultLifetime($lifetime = 0) {
		parent::setDefaultLifetime($lifetime);
		$this->cache->setLifeTime($this->getDefaultLifetime());
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::cleanP($base, $id)
	 */
	public function cleanP($base, $id) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		$group = $this->hashKey(new CacheKey($base, $id));
		$retval = $this->cache->clean($group, 'ingroup');
		$this->log(CacheLogEnum::CLEANED, new CacheKey($base, $id));
		return $retval;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::clear()
	 */
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

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\CacheAbstract::report()
	 * @codeCoverageIgnore
	 */
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
}
