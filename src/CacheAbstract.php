<?php

namespace Cachearium;

/**
 * Abstract class for caches
 *
 */
abstract class CacheAbstract {
	/**
	 * Controls debug on html page for all Cache backends.
	 * @var boolean
	 */
	public static $debugOnPage = false;

	/**
	 * Controls debug to a file
	 * @var string the file name, or null if file debug is off.
	 */
	public static $debugLogFile = null;

	/**
	 * Is this cache enabled?
	 * @var boolean $enabled
	 */
	protected $enabled = true;

	/**
	 * Holds recursive data for start()/end(). Array of CacheData
	 *
	 * @var array
	 */
	private $loopdata = array();

	private $inloop = 0;
	protected $lifetime = 0;

	/**
	 * This is a namespace string to avoid clashes with other instances of this application.
	 * Initialize it to a unique string. If you are not running multiple instances, ignore.
	 *
	 * @var string
	 */
	protected $namespace = "";

	/**
	 * Array for basic cache profiling. Keys are CacheLogEnum, values are counters.
	 *
	 * @var array
	 */
	static protected $summary = array(
		CacheLogEnum::ACCESSED   => 0,
		CacheLogEnum::MISSED     => 0,
		CacheLogEnum::DELETED    => 0,
		CacheLogEnum::CLEANED    => 0,
		CacheLogEnum::SAVED      => 0,
		CacheLogEnum::PREFETCHED => 0,
	);

	/**
	 * Stores cache log for debugging.
	 * @var array
	 */
	protected $cache_log = array();

	/**
	 * Is log enabled? Log can take a lot of RAM, so only turn this on when
	 * profiling.
	 * @var boolean $should_log
	*/
	protected $should_log = false;

	/**
	 * Returns basic cache statistics. See $summary.
	 *
	 * @return array()
	 */
	public static function getLogSummary() {
		return static::$summary;
	}

	public static function resetLogSummary() {
		static::$summary = array(
			CacheLogEnum::ACCESSED   => 0,
			CacheLogEnum::MISSED     => 0,
			CacheLogEnum::DELETED    => 0,
			CacheLogEnum::CLEANED    => 0,
			CacheLogEnum::SAVED      => 0,
			CacheLogEnum::PREFETCHED => 0,
		);
	}

	/**
	 *
	 * @param boolean $b
	 * @return CacheAbstract
	 */
	public function setLog($b) {
		$this->should_log = $b;
		return $this;
	}

	/**
	 * Returns a cache
	 *
	 * @param string $backend
	 * @throws Cachearium\Exceptions\CacheInvalidBackendException
	 * @return CacheAbstract
	 */
	public static function factory($backend) {
		$classname = '\Cachearium\Backend\Cache' . $backend;
		if (!class_exists($classname)) {
			throw new Exceptions\CacheInvalidBackendException("Class does not exist");
		}
		return $classname::singleton();
	}

	/**
	 * Clears all cache classes.
	 * @codeCoverageIgnore
	 */
	public static function clearAll() {
		$caches = [
			\Cachearium\Backend\CacheRAM::singleton(),
			\Cachearium\Backend\CacheFilesystem::singleton(),
			\Cachearium\Backend\CacheMemcached::singleton(),
			// TODO cache apc is broken \Cachearium\Backend\CacheAPC::singleton()
		];
		foreach($caches as $cacheInst) {
			if ($cacheInst->isEnabled()) {
				$cacheInst->clear();
			}
		}
	}

	/**
	 * Enable this cache
	 *
	 * @return CacheAbstract this
	 */
	final public function setEnabled($b) {
		if ($b) {
			$this->enable();
		}
		else {
			$this->disable();
		}
		return $this;
	}

	/**
	 * Enable this cache
	 *
	 * @return CacheAbstract this
	 */
	public function enable() {
		$this->enabled = true;
		return $this;
	}

	/**
	 * Disable this cache
	 *
	 * @return CacheAbstract
	 */
	public function disable() {
		$this->enabled = false;
		return $this;
	}

	/**
	 * @return True if cache is enabled, working and storing/retrieving data.
	 */
	public function isEnabled() {
		return $this->enabled;
	}

	/**
	 *
	 * @param number $lifetime 0 for infinite
	 */
	public function setDefaultLifetime($lifetime = 0) {
		$this->lifetime = $lifetime;
	}

	public function getDefaultLifetime() {
		return $this->lifetime;
	}

	/**
	 * @param string $name An optional namespace.
	 */
	public function setNamespace($name) {
		$this->namespace = $name;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * Get cached entry.
	 *
	 * @param $k
	 * @return mixed
	 * @throws Cachearium\Exceptions\NotCachedException
	 */
	abstract public function get(CacheKey $k);

	/**
	 * Same as get(), but expanded parameters.
	 *
	 * @param string $base
	 * @param string $id
	 * @param mixed $sub
	 * @return mixed
	 * @throws Cachearium\Exceptions\NotCachedException
	 * @see getK
	 */
	public function getP($base, $id, $sub = null) {
		return $this->get(new CacheKey($base, $id, $sub));
	}

	/**
	 * Same as get, but assumes data was stored with a CacheData object
	 * and will treat it accordingly.
	 *
	 * @param CacheKey $k
	 * @return CacheData
	 * @throws Cachearium\Exceptions\NotCachedException
	 */
	public function getData(CacheKey $k) {
		$cd = CacheData::unserialize($this->get($k));
		if ($cd->checkUpdateToDate($this)) {
			return $cd;
		}
		throw new Exceptions\NotCachedException();
	}

	/**
	 * Same as getData(), but expanded parameters.
	 *
	 * @see getData()
	 * @param string $base
	 * @param string $id
	 * @param mixed $sub
	 */
	public function getDataP($base, $id, $sub = null) {
		return $this->getData(new CacheKey($base, $id, $sub));
	}

	/**
	 * Gets data from multiple cache keys at once
	 *
	 * Backends may override this to provide an efficient implementation over multiple
	 * calls to get().
	 *
	 * @param array $cacheid List of cache keys
	 * @param callable $callback if present will be called for any \NotCachedExceptions.
	 * Callback should have this signature: (CacheAbstract $c, CacheKey $k)
	 * @return array:mixed array with data, using same keys as cacheid. Keys not
	 * found in cache won't be present, but no exception will be thrown
	 */
	public function getMulti(array $cacheid, $callback = null) {
		$retval = [];
		foreach ($cacheid as $k => $c) {
			try {
				$retval[$k] = $this->get($c);
			}
			catch (Exceptions\NotCachedException $e) {
				// if there is a callback, call it
				if ($callback) {
					$retval[$k] = call_user_func($callback, $this, $c);
				}
			}
		}
		return $retval;
	}

	/**
	 * Increment a variable. Backend deals with this, but in general this is atomic.
	 * Backend must only guarantee that the increment is made, but the final value
	 * may not be current + $value due to concurrent accesses.
	 *
	 * @param integer $value
	 * @param CacheKey $k
	 * @param integer $default If key is not in cache, this value is returned.
	 * @return integer
	 */
	abstract public function increment($value, CacheKey $k, $default = 0);

	/**
	 * Invalidates a dependency index. If the index does not exist it is created.
	 * @param CacheKey $k
	 */
	public function invalidate(CacheKey $k) {
		return $this->increment(1, $k, 0);
	}

	/**
	 * Saves data in cache.
	 *
	 * @param mixed $data Data to save in cache
	 * @param CacheKey $k
	 * @param integer $lifetime The lifetime in sceonds, although it is up to the implementation whether
	 * it is respected or not.
	 * @return boolean true if no problem
	 */
	abstract public function store($data, CacheKey $k, $lifetime = 0);

	/**
	 * Same as store() but expanded parameters
	 *
	 * @param mixed $data
	 * @param string $base
	 * @param string $sub
	 * @param string $id
	 * @param number $lifetime
	 * @return boolean true if no problem
	 * @see store()
	 */
	public function storeP($data, $base, $id, $sub = null, $lifetime = 0) {
		return $this->store($data, new CacheKey($base, $id, $sub), $lifetime);
	}

	/**
	 * Same as store() but expanded parameters
	 *
	 * @param CacheData $data
	 * @param number $lifetime
	 * @return boolean true if no problem
	 * @see store()
	 */
	public function storeData(CacheData $data, $lifetime = 0) {
		return $this->store($data->updateDependenciesHash($this)->serialize(), $data->key, $lifetime);
	}

	/**
	 * Deletes an entry from the cache
	 *
	 * @param CacheKey $k
	 * @return boolean
	 */
	abstract public function delete(CacheKey $k);

	/**
	 * @see delete()
	 * @param string $base
	 * @param string $id
	 * @param mixed $sub
	 */
	public function deleteP($base, $id, $sub = null) {
		return $this->delete(new CacheKey($base, $id, $sub));
	}

	/**
	 * Cleans cache: all entries with a certain $base and $id in the $key
	 * are deleted.
	 *
	 * @param CacheKey $k
	 * @return boolean true if no problem
	 */
	public function clean(CacheKey $k) {
		return $this->cleanP($k->getBase(), $k->getId());
	}

	/**
	 * Cleans cache: all entries with a certain $base and $id
	 *
	 * @return boolean true if no problem
	 */
	abstract public function cleanP($base, $id);

	/**
	 * Clears entire cache. Use sparingly.
	 */
	abstract public function clear();

	/**
	 * Prefetches data which will be used. This avoids multiple trips to the cache
	 * server if they can be avoided.
	 *
	 * Backend may ignore this call and implement a noop.
	 *
	 * @param array $data array(0 => CacheKey, ...)
	 */
	abstract public function prefetch($data);

	/**
	 * Generates a report for this backend
	 *
	 * @codeCoverageIgnore
	*/
	abstract public function report();

	/**
	 * Starts a cache if it doesn't exist, or outputs the data and returns true.
	 * Calls extraSub().
	 *
	 * @param CacheKey $k
	 * @param string $lifetime The lifetime, in seconds
	 * @param boolean $print if True echoes the data
	 * @param boolean $fail if false throws an exception if something happens, such
	 * as not cached
	 * @return boolean|string True if cached
	 * @review
	 */
	public function start(CacheKey $k, $lifetime = null, $print = true, $fail = false) {
		$this->extraSub($k->sub);

		return $this->recursiveStart($k, $lifetime, $print, $fail);
	}

	/**
	 * @see recursiveStart()
	 */
	public function recursiveStartP($base, $id, $sub = null, $lifetime = null, $print = true, $fail = false) {
		return $this->recursivestart(new CacheKey($base, $id, $sub), $lifetime, $print, $fail);
	}

	/**
	 * @see start()
	 */
	public function startP($base, $id, $sub = null, $lifetime = null, $print = true, $fail = false) {
		return $this->start(new CacheKey($base, $id, $sub), $lifetime, $print, $fail);
	}

	/**
	 * start() using a callable. Same as start()/c()/end().
	 *
	 * @param CacheKey $k
	 * @param callable $c A callable. Whatever it prints will be cached.
	 * @param array $cparams parameters for the callback, optional
	 * @param integer $lifetime
	 */
	public function startCallback(CacheKey $k, callable $c, array $cparams = [], $lifetime = null) {
		$data = $this->start($k, $lifetime);
		if ($data === false) {
			call_user_func_array($c, $cparams);
			$data = $this->end(false);
		}
		return $data;
	}

	/**
	 * Appends a callback to the current start()/end() cache
	 *
	 * Callbacks are always called at runtime, their result is never cached at
	 * this level. You may cache it in the callback, of course.
	 *
	 * @param function $callback
	 * @return boolean
	 * @review
	 */
	public function appendCallback(callable $callback) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		if (!$this->inloop) {
			return false;
		}

		$data = ob_get_contents();
		ob_clean();
		$this->loopdata[$this->inloop]->appendData($data);
		$this->loopdata[$this->inloop]->appendCallback($callback);

		return true;
	}

	/**
	 * Returns a key given parameters. This is up to storage and different
	 * values may be returned for the same parameters, as storages are likely
	 * to use key-based cache expiration.
	 *
	 * @param CacheKey $k
	 */
	abstract protected function hashKey(CacheKey $k);

	protected function keyFromDeps(CacheKey $k, $deps) {
		$mainkey = $this->hashKey($k);
		foreach ($deps as $d) { // TODO: arrays are ugly
			$mainkey .= $this->hashKey($d); // TODO: one fetch for all
		}
		$mainkey = md5($mainkey);
		return $mainkey;
	}

	/**
	 * Get extra sub
	 * @param unknown $sub
	 */
	private function extraSub(&$sub) {
		if (!is_callable('application_cacheDependencies')) {
			return;
		}
		$extra = application_cacheDependencies();
		if (is_array($sub)) {
			$sub['cacheExtraSubApplication'] = $extra;
		}
		else {
			$sub .= $extra;
		}
	}

	public function newstart(CacheKey $k, $lifetime = null, $fail = false) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		// fetch cache
		try {
			$cachedata = $this->getData($k);
		} catch (Exceptions\NotCachedException $e) {
			// not cached
			if ($fail) {
				throw $e;
			}
		}

		$this->inloop++;
		$this->loopdata[$this->inloop] = new CacheData();
		if ($this->inloop > 1) {
			// we are recursive. push whatever we have so far in the previous cache
			$data = ob_get_contents();
			ob_clean();
			$this->loopdata[$this->inloop - 1]->appendData($data);
			$this->loopdata[$this->inloop - 1]->appendRecursion($k);
		}
		else {
			// something was not cached below. We invalidated all cache
			// dependencies
		}

		$this->loopdata[$this->inloop]->setKey($k);
		$this->loopdata[$this->inloop]->lifetime = $lifetime ? $lifetime : $this->lifetime;

		ob_start();
		ob_implicit_flush(false);

		return false;
	}

	public function newEnd($print = true) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		$data = ob_get_clean();

		/* @var $cachedata CacheData */
		$cachedata = $this->loopdata[$this->inloop];
		$cachedata->appendData($data);

		$cachedata->generateDependenciesHash($this);
		$mainkey = $this->keyFromDeps($cachedata->getKey(), $cachedata->dependencies);
			if (!$this->storeP($cachedata, 'cacherecursive', 0, $mainkey)) {
			throw new \Cachearium\Exceptions\CacheStoreFailure("Storing key");
		}
		if (!$this->storeData($cachedata)) {
			throw new \Cachearium\Exceptions\CacheStoreFailure("Storing data");
		}

		// if recursive
		$this->inloop--;
		if ($this->inloop > 0) {
			return false;
		}

		if ($print) {
			$key = "cache-" . rand();
			// @codeCoverageIgnoreStart
			if (static::$debugOnPage) {
				echo '<span class="debug-probe-begin"
					data-key="' . $key .
					'" data-base="' . $cachedata->getKey()->base .
					'" data-id="' . $cachedata->getKey()->id .
					'" data-sub="' . print_r($cachedata->getKey()->sub, true) .
					'" data-lifetime="' . $cachedata->lifetime .
					'" data-backend="' . get_class($this) .
					'" data-type="save"></span>';
			}
			// @codeCoverageIgnoreEnd

			echo $cachedata->stringify($this);

			// @codeCoverageIgnoreStart
			if (static::$debugOnPage) {
				echo '<span class="debug-probe-end" data-key="' . $key . '"></span>';
			}
			// @codeCoverageIgnoreEnd
			return;
		}

		return $cachedata->stringify($this);
	}

	/**
	 * Prints HTML for cache debug probes -> opens tag
	 *
	 * @param string $key
	 * @param CacheData $cachedata
	 * @param string $type
	 * @codeCoverageIgnore
	 */
	protected function printProbeStart($key, CacheData $cachedata, $type) {
		echo '<span class="cachearium-debug-probe-begin"
			data-key="' . $key .
			'" data-base="' . $cachedata->getKey()->base .
			'" data-id="' . $cachedata->getKey()->id .
			'" data-sub="' . print_r($cachedata->getKey()->sub, true) .
			'" data-lifetime="' . $cachedata->lifetime .
			'" data-backend="' . get_class($this) .
			'" data-type="' . $type . '"></span>';
	}

	/**
	 * Prints HTML for cache debug probes -> closes tag
	 *
	 * @param string $key
	 * @param CacheData $cachedata
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @codeCoverageIgnore
	 */
	protected function printProbeEnd($key, CacheData $cachedata) {
		echo '<span class="cachearium-debug-probe-end" data-key="' . $key . '"></span>';
	}

	/**
	 *
	 * @param CacheKey $k
	 * @param integer $lifetime if null uses the class default
	 * @param boolean $print
	 * @param boolean $fail if true throws a NotCachedException if not cached.
	 * @throws Cachearium\Exceptions\NotCachedException
	 * @throws Cachearium\Exceptions\CacheKeyClashException
	 * @return string The cached item as a string or false if not cached.
	 */
	public function recursiveStart(CacheKey $k, $lifetime = null, $print = true, $fail = false) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		foreach ($this->loopdata as $l) {
			/* @var $l CacheData */
			if ($l->checkClash($k)) {
				throw new Exceptions\CacheKeyClashException();
			}
		}

		// check if we are inside another cache for automatic dependencies.
		/* @var $cachedata CacheData */
		$cachedata = null;
		try {
			$cachedata = $this->getData($k);

			if (!$cachedata->checkUpdateToDate($this)) {
				// stale
				$cachedata = null;
			}
			// TODO $this->prefetch($cachedata->getDependencies());
		}
		catch (Exceptions\NotCachedException $e) {
		}

		// found. just return it.
		if ($cachedata) {
			try {
				$this->log(
					CacheLogEnum::ACCESSED,
					$cachedata->key,
					$cachedata->lifetime
				);
				$key = "cache-" . rand();

				$retval = $cachedata->stringify($this);

				if ($print) {
					// @codeCoverageIgnoreStart
					if (static::$debugOnPage) {
						$this->printProbeStart($key, $cachedata, 'hit');
					}
					// @codeCoverageIgnoreEnd

					echo $retval;

					// @codeCoverageIgnoreStart
					if (static::$debugOnPage) {
						$this->printProbeEnd($key, $cachedata);
					}
					// @codeCoverageIgnoreEnd
				}
				return $retval;
			}
			catch (Exceptions\NotCachedException $e) {
				$this->delete($k); // clear recursively
				if ($this->inloop) {
					throw $e;
				}
			}
		}
		if ($fail) {
			throw new Exceptions\NotCachedException();
		}

		$this->inloop++;
		$cd = new CacheData($k);
		$cd->setLifetime($lifetime ? $lifetime : $this->lifetime);
		$this->loopdata[$this->inloop] = $cd;

		if ($this->inloop > 1) {
			// we are recursive. push whatever we have so far in the previous cache
			$data = ob_get_contents();
			ob_clean();

			foreach ($this->loopdata as $l) {
				if ($l == $cd) { // don't depend on itself
					continue;
				}
				/* @var $l CacheData */
				$l->addDependency($k);
			}
			$this->loopdata[$this->inloop - 1]->appendData($data);
			$this->loopdata[$this->inloop - 1]->appendRecursionData($cd);
		}
		else {
			// something was not cached below. We invalidated all cache
			// dependencies
		}

		ob_start();
		ob_implicit_flush(false);

		return false;
	}

	/**
	 *
	 * @param boolean $print
	 * @throws \Cachearium\Exceptions\CacheStoreFailure
	 * @return string The string. If $print == true the string is printed as well.
	 */
	public function recursiveEnd($print = true) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return '';
		}
		// @codeCoverageIgnoreEnd

		$data = ob_get_clean();

		/* @var $cachedata CacheData */
		$cachedata = $this->loopdata[$this->inloop];
		$cachedata->appendData($data);

		try {
			$cachedata->generateDependenciesHash($this);
		}
		catch (\Cachearium\Exceptions\CacheUnsupportedOperation $e) {
			// not much we can do here, so just keep on going
		}
		$mainkey = $this->keyFromDeps($cachedata->getKey(), $cachedata->dependencies);
		if (!$this->storeP($cachedata, 'cacherecursive', 0, $mainkey)) {
			throw new \Cachearium\Exceptions\CacheStoreFailure("Storing key");
		}
		if (!$this->storeData($cachedata)) {
			throw new \Cachearium\Exceptions\CacheStoreFailure("Storing data");
		}

		// if recursive
		unset($this->loopdata[$this->inloop]);
		$this->inloop--;
		if ($this->inloop > 0) {
			return '';
		}

		if ($print) {
			$key = "cache-" . rand();
			// @codeCoverageIgnoreStart
			if (static::$debugOnPage) {
				$this->printProbeStart($key, $cachedata, 'save');
			}
			// @codeCoverageIgnoreEnd

			$str = $cachedata->stringify($this);
			echo $str;

			// @codeCoverageIgnoreStart
			if (static::$debugOnPage) {
				$this->printProbeEnd($key, $cachedata);
			}
			// @codeCoverageIgnoreEnd
			return $str;
		}

		return $cachedata->stringify($this);
	}

	/**
	 * Ends the cache start().
	 * @see recursiveEnd()
	 */
	public function end($print = true) {
		return $this->recursiveEnd($print);
	}

	/**
	 * Cancels something started by recursiveStart() if you don't want to call recursiveEnd()
	 *
	 */
	public function recursiveAbort() {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return;
		}
		// @codeCoverageIgnoreEnd

		ob_end_clean();

		// if recursive
		unset($this->loopdata[$this->inloop]);
		$this->inloop--;

		return;
	}

	/**
	 * Alias for recursiveAbort()
	 */
	public function abort() {
		$this->recursiveAbort();
	}

	/*
	 * DEBUG
	 */

	/**
	 * High level log for testing and debugging
	 *
	 * @codeCoverageIgnore
	 */
	public static function logHigh($message) {
		if (static::$debugLogFile) {
			file_put_contents(static::$debugLogFile, $message, FILE_APPEND);
		}
	}

	/**
	 * Logs cache accesses for debugging
	 *
	 * @param string $status CacheLogEnum constant
	 * @param CacheKey $k The message to print.
	 * @param integer $lifetime
	 * @codeCoverageIgnore
	 */
	protected function log($status, CacheKey $k, $lifetime = 0) {
		static::$summary[$status]++;

		if ($this->should_log == false) {
			return;
		}

		$bt = debug_backtrace();
		foreach ($bt as $i => $d) {
			if (strpos($d['file'], '/Cache') === false) {
				// TODO: if() may not work well if user has a file called Cache
				$trace = $d['function'] . ' at ' . $d['file'] . ':' . $d['line'];
				$this->cache_log[] = array(
					'status' => $status,
					'message' => "(" . $k->debug() . ", $lifetime) by " . $trace
				);
				break;
			}
		}
	}

	/**
	 * Dumps a short HTML summary of the cache hits/misses
	 * @codeCoverageIgnore
	 */
	public static function dumpSummary() {
		echo '<div id="cache-summary">Cache Summary (non-ajax): ';
		foreach (static::getLogSummary() as $key => $val) {
			echo $key . '=>' . $val . ' / ';
		}
		echo '</div>';
	}

	/**
	 * Renders CSS for live view debugging of cached data.
	 * @codeCoverageIgnore
	 */
	public static function cssDebug() {
?>
[class^="cachearium-debug-probe"] {
	width: 10px;
	height: 10px;
	background-color: #f00;
	display: inline;
	/*visibility: hidden; */
}
.cachearium-debug-overview {
	position: absolute;
	left: 0;
	top: 0;
	background-color: rgba(255, 255, 255, 1);
	border: 1px solid grey;
	z-index: 5000;
}
.cachearium-debug-view {
	position: absolute;
	pointer-events: none;
	border: 1px solid black;
}

.cachearium-debug-view[data-type="hit"] {
	background-color: rgba(0, 255, 0, 0.1);
}
.cachearium-debug-view[data-type="save"] {
	background-color: rgba(255, 0, 0, 0.1);
}
.cachearium-debug-view .cachearium-debug-view-innerdata {
	float: right;
	color: #000;
	height: 10px;
	width: 10px;
	border: 1px solid grey;
	pointer-events: auto;
	overflow: hidden;
	background-color: rgba(255, 0, 0, 0.7);
}
.cachearium-debug-view	.cachearium-debug-view-innerdata:hover {
	width: auto;
	height: auto;
	background-color: rgba(255, 255, 255, 0.9);
	border: 1px solid grey;
}
<?php
	}


	/**
	 * Extensive footer debug code. Shows which parts of the HTML were
	 * cached or missed visually. Great!
	 * @codeCoverageIgnore
	 */
	public static function footerDebug() {
		if (!static::$debugOnPage) {
			return;
		}
		?>
<script>
$(function() {
	var probes = $('.cachearium-debug-probe-begin');
	if (probes.length != $('.cachearium-debug-probe-end').length) {
		alert('Woooooooh! Cache starts do not match cache ends!');
	}

	for (var i = 0; i < probes.length; i++) {
		var p = $(probes[i]);
		var end = $('.cachearium-debug-probe-end[data-key="' + p.data('key') + '"]');
		var between = p.nextUntil(end);
		var bbox = {'top': 100000, 'left': 10000000, 'bottom': 0, 'right': 0 };

		for (var j = 0; j < between.length; j++) {
			var el = $(between[j]);
			var offset = el.offset();
			if (!el.is(':visible')) {
				continue;
			}
			if (bbox.top > offset.top) {
				bbox.top = offset.top;
			}
			if (bbox.left > offset.left) {
				bbox.left = offset.left;
			}
			if (bbox.bottom < (offset.top + el.height())) {
				bbox.bottom = offset.top + el.height();
			}
			if (bbox.right < (offset.left + el.width())) {
				bbox.right = offset.left + el.width();
			}
		}

		var style =
			"z-index: " + (1000 + p.parents().length) + ";" +
			"left: " + bbox.left + "px;" +
			"top: " + bbox.top + "px;" +
			"width: " + (bbox.right - bbox.left) + "px;" +
			"height: " + (bbox.bottom - bbox.top) + "px;";
		var debugel = $('<div class="cachearium-debug-view" style="' + style +
			'" data-key="' + p.data('key') + '"></div>');
		var innerdata = '<span class="cachearium-debug-view-innerdata">';
		$.each(p.data(), function (name, value) {
			debugel.attr("data-" + name, value);
			innerdata += name + ": " + value + "<br/>";
		});
		innerdata += '</span>';
		debugel.append(innerdata);
		$('body').append(debugel);
	}
	$('body').append(
		'<div class="cachearium-debug-overview">' +
			'<span><b>Cachearium</b></span><br/>' +
			'<span>' + probes.length + ' probes</span><br/>' +
			'<a id="cachearium-debug-toggle" href="#">Toggle</a>' +
		'</div>'
	);
	$('#cachearium-debug-toggle').click(function() {
		$('.cachearium-debug-view').toggle();
	});
});
</script>
<?php
	}
}
