<?php

namespace Cachearium;

define('CACHEDATA_TYPE_CALLBACK', 'callback');
define('CACHEDATA_TYPE_RECURSION', 'recursion');
define('CACHEDATA_TYPE_RECURSION_DATA', 'recursiondata');
define('CACHEDATA_TYPE_DATA', 'data');

/**
 * Class used to store cache data in start()/get();
 *
 */
class CacheData {
	/**
	 *
	 * @var CacheKey
	 */
	public $key;

	/**
	 * Lifetime: how long this cache should live. It's up to the storage implementation
	 * the details of how this will be obeyed, if at all.
	 * @var integer
	 */
	public $lifetime;

	/**
	 * This is a storage of dependencies for this cache. This is stored here as
	 * the russian doll model will automatically push up dependencies. In the end
	 * this will probably be used by the storage for easy invalidation.
	 *
	 * @var array:CacheKey
	 */
	public $dependencies = array();

	/**
	 * The actual cached data
	 * @var array
	 */
	public $data = array();

	private $dependenciesHash = '';

	public function __construct($data = null, CacheKey $ck = null) {
		if ($data) {
			$this->appendData($data);
		}
		if ($ck) {
			$this->setKey($ck);
		}
	}

	/**
	 *
	 * @param CacheKey $ck
	 * @return CacheData
	 */
	public function setKey(CacheKey $ck) {
		$this->key = $ck;
		return $this;
	}

	/**
	 *
	 * @param unknown $callback
	 * @return CacheData
	 */
	public function appendCallback($callback) {
		$this->data[] = array('type' => CACHEDATA_TYPE_CALLBACK, 'data' => $callback);
		return $this;
	}

	/**
	 *
	 * @param CacheData $cd
	 * @return CacheData
	 */
	public function mergeDependencies(CacheData $cd) {
		$this->dependencies = array_unique(array_merge($this->dependencies, $cd->dependencies));
		return $this;
	}

	public function clearDependenciesHash() {
		$this->dependenciesHash = '';
	}

	/**
	 * Checks if dependencies are still fresh.
	 * @param CacheAbstract $cache
	 * @return boolean
	 */
	public function checkUpdateToDate(CacheAbstract $cache) {
		// no deps? bail out
		if (!count($this->dependencies)) {
			return true;
		}
		if ($this->generateDependenciesHash($cache) == $this->dependenciesHash) {
			return true;
		}
		return false;
	}

	public function dependencyInit(CacheAbstract $cache, CacheKey $k) {
		return $cache->incrementK(0, $k, 0);
	}

	/**
	 * Get a fresh hash based on dependencies. Does not update the current hash.
	 * @param CacheAbstract $cache
	 * @return string
	 */
	public function generateDependenciesHash(CacheAbstract $cache) {
		if (!count($this->dependencies)) {
			return '';
		}
		$values = $cache->getMulti($this->dependencies, array($this, 'dependencyInit'));
		return md5(implode($values));
	}

	public function updateDependenciesHash(CacheAbstract $cache) {
		$this->dependenciesHash = $this->generateDependenciesHash($cache);
		return $this;
	}

	public function updateDependenciesHashIfNull(CacheAbstract $cache) {
		if (!$this->dependenciesHash) {
			$this->dependenciesHash = $this->generateDependenciesHash($cache);
		}
		return $this;
	}

	public function getKey() {
		return $this->key;
	}

	public function getDependenciesHash() {
		return $this->dependenciesHash;
	}

	public function getDependencies() {
		return $this->dependencies;
	}

	/**
	 *
	 * @param any $data Any kind of data you want to store. usually strings.
	 * @return CacheData
	 */
	public function appendData($data) {
		if ($data) {
			$this->data[] = array('type' => CACHEDATA_TYPE_DATA, 'data' => $data);
		}
		return $this;
	}

	/**
	 * Convenience function. Returns the first data CACHEDATA_TYPE_DATA that you
	 * stored. Returns null if there is none.
	 *
	 * @return any|NULL
	 */
	public function getFirstData() {
		foreach ($this->data as $d) {
			if ($d['type'] == CACHEDATA_TYPE_DATA) {
				return $d['data'];
			}
		}
		return null;
	}

	public function appendRecursionK(CacheKey $k) {
		$this->addDependency($k);
		$this->data[] = array(
			'type' => CACHEDATA_TYPE_RECURSION,
			'data' => $k
		);
		return $this;
	}

	public function appendRecursionData(CacheData $d) {
		if (!$d->getKey()) {
			throw new CacheInvalidDataException();
		}
		$this->addDependency($d->getKey());
		$this->data[] = array(
			'type' => CACHEDATA_TYPE_RECURSION_DATA,
			'data' => $d->getKey()
		);
		return $this;
	}

	/**
	 * Adds a dependency
	 * @param CacheKey $k
	 * @return CacheData This
	 */
	public function addDependency(CacheKey $k) {
		$this->dependencies[] = $k;
		$this->clearDependenciesHash();
		return $this;
	}

	/**
	 * Adds a dependency
	 * @param array[CacheKey] $k
	 * @return CacheData This
	 */
	public function addDependencies(array $deps) {
		foreach ($deps as $k) {
			$this->addDependency($k);
		}
		return $this;
	}

	/**
	 * Sets the list of dependencies and updates the dependency hash
	 *
	 * @param array:CacheKey $deps
	 * @param CacheAbstract $cache
	 * @return CacheData
	 */
	public function setDependencies(array $deps, CacheAbstract $cache) {
		$this->dependencies = [];
		foreach ($deps as $k) {
			$this->addDependency($k);
		}
		$this->generateDependenciesHash($cache);
		return $this;
	}

	/**
	 *
	 * @param integer $lifetime
	 * @return CacheData
	 */
	public function setLifetime($lifetime) {
		$this->lifetime = $lifetime;
		return $this;
	}

	/**
	 * Checks if a set of keys clashes with the ones used here.
	 * @param string $base
	 * @param string $id
	 * @param any $sub
	 * @return boolean True if they match and there is a clash
	 */
	public function checkClash(CacheKey $k) {
		return ($this->key == $k);
	}

	/**
	 * Converts this data to a string that can output. This is not a hash
	 * key or a serialization, but an actual render for humans.
	 *
	 * @throws NotCachedException
	 */
	public function stringify(CacheAbstract $c, $recurse = true) {
		$retval = [];
		foreach ($this->data as $item) {
			if ($item['type'] == CACHEDATA_TYPE_CALLBACK) {
				$callback = $item['data'];
				if (is_callable($callback)) {
					$retval[] = $callback();
				}
			}
			else if ($item['type'] == CACHEDATA_TYPE_RECURSION) {
				if ($recurse) {
					$retval[] = $c->getK($item['data']);
				}
			}
			else if ($item['type'] == CACHEDATA_TYPE_RECURSION_DATA) {
				if ($recurse) {
					$data = $c->getDataK($item['data']);
					$retval[] = $data->stringify($c);
				}
			}
			else {
				$retval[] = $item['data'];
			}
		}
		return implode('', $retval);
	}

	/**
	 * Serialize this object to a string so we can easily store.
	 *
	 * @return string
	 */
	public function serialize() {
		return serialize($this);
	}

	/**
	 * Unserializes data and returns a new CacheData. This is what you
	 * should use to get the object data from the storage.
	 *
	 * @param string $data
	 * @return CacheData
	 */
	static public function unserialize($data) {
		return unserialize($data);
	}
}
