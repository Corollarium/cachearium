<?php

namespace Cachearium\Backend;

use Cachearium\Backend\CacheRAM;
use Cachearium\CacheLogEnum;
use Cachearium\CacheKey;
use Cachearium\Exceptions\NotCachedException;

/**
 * Cache class which uses memcache.
 *
 * Keys are set up as: [namespace]_[id]_[subgroup]
 *
 * We inherit from CacheRAM to avoid fetching things multiple times.
 *
 */
class CacheMemcached extends CacheRAM {
	/**
	 * The memcache var
	 * @var \Memcached
	 */
	private $memcached;

	private $fetches = 0; /// number of times we had to hit memcache directly

	/**
	 * Cache constructor (this is a singleton).
	 *
	 * @param $serves optional. If present, addServers() is called with this parameter
	 * but only if the singleton is being created for the first time.
	 * @return Cache The cache singleton.
	 * @codeCoverageIgnore
	 */
	static public function singleton($servers = []) {
		static $instances;
		if (!isset($instances)) {
			$instances = new CacheMemcached();
			if (count($servers)) {
				$instances->addServers($servers);
			}
		}

		return $instances;
	}

	/**
	 * Is memcached available in this system?
	 *
	 * @return boolean
	 */
	static public function hasMemcachedExt() {
		return extension_loaded('memcached');
	}

	// @codeCoverageIgnoreStart
	// Prevent users to clone the instance
	public function __clone() {
		trigger_error('Cloning is not allowed.', LH_TRIGGER_UNEXPECTED);
	}
	// @codeCoverageIgnoreEnd

	/**
	 * @codeCoverageIgnore
	 */
	public function errorCallback() {
		// memcache error, probably offline. Logging to DB is bad (will overflow
		// the DB). We should really restart memcached
		// TODO: via Batch?
		$this->disable();
	}

	public function getFetches() {
		return []; // TODO
		foreach ($data as $item) {
			$x = unserialize($item['keys']);
			if ($x === false) {
				continue;
			}

			parent::store($x, $item);
		}

		return $this->fetches;
	}

	/**
	 * Constructor.
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		if (!self::hasMemcachedExt()) {
			$this->disable();
			return;
		}
		$this->memcached = new \Memcached;
		if (!$this->memcached) {
			$this->disable();
			return;
		}

		if (\Memcached::HAVE_IGBINARY) {
			$this->memcached->setOption(\Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_IGBINARY);
		}
		$this->memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
		$this->lifetime = 3600;
	}

	/**
	 *
	 * @param array $servers Each entry in servers is supposed to be an array
	 *  containing hostname, port, and, optionally, weight of the server.
	 * $servers = array(
	 * array('mem1.domain.com', 11211, 33),
	 * array('mem2.domain.com', 11211, 67)
	 * );
	 * @return boolean
	 * @codeCoverageIgnore
	 */
	public function addServers($servers) {
		if (!self::hasMemcachedExt()) {
			return false;
		}
		return $this->memcached->addServers($servers);
	}

	/**
	 * Converts cachekey to a string for the data group.
	 *
	 * @param CacheKey $k
	 * @return string
	 */
	private function getGroupString(CacheKey $k) {
		return md5(strtr($this->namespace . $k->getBase() . $k->getId(), ' ', '_'));
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::hashKey()
	 */
	protected function hashKey(CacheKey $k) {
		$group = $this->getGroupString($k);
		$ns_key = $this->memcached->get($group);

		// if not set, initialize it
		if ($ns_key == false) {
			$ns_key = 1;
			$this->memcached->set($group, $ns_key);
		}
		$group = $group . $ns_key;

		if (!is_string($k->sub)) {
			$sub = md5(serialize($k->sub));
		}
		else {
			$sub = $k->sub;
		}
		$group .= $sub;

		return $group;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::get()
	 */
	public function get(CacheKey $k) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			throw new NotCachedException();
		}
		// @codeCoverageIgnoreEnd

		// see if it is in RAM
		$should_log = $this->should_log;
		try {
			$this->should_log = false;
			$data = parent::get($k);
			$this->should_log = $should_log;
			$this->log(CacheLogEnum::PREFETCHED, $k);
			return $data;
		}
		catch (NotCachedException $e) {
			$this->should_log = $should_log;
		}

		$group = $this->hashKey($k);

		$this->fetches++;
		$retval = $this->memcached->get($group);

		$this->log(
			($retval !== false ? CacheLogEnum::ACCESSED : CacheLogEnum::MISSED),
			$k
		);
		if ($retval == false) {
			throw new NotCachedException();
		}

		$x = unserialize($retval);
		if ($x === false) {
			throw new NotCachedException();
		}

		parent::store($x, $k);

		return $x;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::increment()
	 */
	public function increment($value, CacheKey $k, $default = 0) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return $default;
		}
		// @codeCoverageIgnoreEnd

		$group = $this->hashKey($k);

		$this->log(CacheLogEnum::SAVED, $k);

		$x = $this->memcached->increment(
			$group, $value, $default, $this->lifetime
		);
		parent::store($x, $k, $this->lifetime);

		return $x;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::store()
	 */
	public function store($data, CacheKey $k, $lifetime = 0) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		$group = $this->hashKey($k);

		$this->log(CacheLogEnum::SAVED, $k);

		$x = $this->memcached->set(
			$group, serialize($data), $lifetime ? $lifetime : $this->lifetime
		);
		parent::store($data, $k, $lifetime);

		return $x;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::delete()
	 */
	public function delete(CacheKey $k) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			throw new NotCachedException();
		}
		// @codeCoverageIgnoreEnd

		$group = $this->hashKey($k);

		$this->log(CacheLogEnum::DELETED, $k);

		parent::delete($k);
		return $this->memcached->delete($group);
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::cleanP()
	 */
	public function cleanP($base, $id) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			throw new NotCachedException();
		}
		// @codeCoverageIgnoreEnd

		$group = $this->getGroupString(new CacheKey($base, $id));

		parent::cleanP($base, $id);
		$this->memcached->increment($group);
		return true;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::clear()
	 */
	public function clear() {
		if ($this->memcached) {
			$this->memcached->flush();
		}
		parent::clear();
		return true;
	}

	public function prefetchKeys($keys) {
		$retval = $this->memcached->get($keys);
		foreach ($retval as $i) {
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::prefetch()
	 */
	public function prefetch($data) {
		$keys = array();

		foreach ($data as &$item) {
			$keys[] = $this->hashKey($item);
		}

		$this->memcached->getDelayed($keys);

		// TODO: fetchall vs get?
	}

	/**
	 * Clear prefetched data. This is rarely useful.
	 */
	public function prefetchClear() {
		parent::clear();
	}

	/**
	 * (non-PHPdoc)
	 * @see \Cachearium\Backend\CacheRAM::report()
	 * @codeCoverageIgnore
	 */
	public function report() {
		if ($this->should_log == false) {
			return;
		}
		echo '<style>
			.cache-success { background-color: #468847; border-radius: 3px; color: #FFF; padding: 2px 4px; }
			.cache-prefetched { background-color: #76F877; border-radius: 3px; color: #FFF; padding: 2px 4px; }
			.cache-miss { background-color: #B94A48; border-radius: 3px; color: #FFF; padding: 2px 4px; }
			.cache-save { background-color: #0694F8; border-radius: 3px; color: #FFF; padding: 2px 4px; }
			.cache-deleted { background-color: #F89406; border-radius: 3px; color: #FFF; padding: 2px 4px; }
			.cache-cleaned { background-color: #F894F8; border-radius: 3px; color: #FFF; padding: 2px 4px; }
		</style>';
		echo '<div class="cachearium cachearium-memcache"><h2>Cache MemCache system</h2>';
		echo '<h3>System is: ' . ($this->enabled ? 'enabled' : 'disabled') . '</h3>';
		echo '<h3>Total fetches: ' . ($this->fetches) . '</h3>';

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
		echo '</div>';
	}
}
