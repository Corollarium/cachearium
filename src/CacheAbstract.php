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
	 * Controls debug on html page for all Cache backends.
	 * @var boolean
	 */
	public static $debugLogToFile = false;

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
	 * Array for basic cache profiling. Keys are CacheLogEnum, values are counters.
	 *
	 * @var array
	 */
	static private $summary = array(
		CacheLogEnum::ACCESSED   => 0,
		CacheLogEnum::MISSED     => 0,
		CacheLogEnum::DELETED    => 0,
		CacheLogEnum::CLEANED    => 0,
		CacheLogEnum::SAVED      => 0,
		CacheLogEnum::PREFETCHED => 0,
	);

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
	static public function getLogSummary() {
		return self::$summary;
	}

	/**
	 * Returns a cache
	 *
	 * @param string $backend
	 * @throws CacheInvalidBackendException
	 * @return CacheAbstract
	 */
	static public function factory($backend) {
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
	static public function clearAll() {
		// TODO: should only clear the ones that were instantiated?
		foreach (get_declared_classes() as $classname) {
			if (is_subclass_of($classname, 'CacheAbstract')) {
				$class = new ReflectionClass($classname);
				if (!$class->isAbstract() && $classname::singleton()->isEnabled()) {
					$classname::singleton()->clear();
				}
			}
		}
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
	 * Get cached entry.
	 *
	 * @param $k
	 * @return any
	 * @throws NotCachedException
	 */
	abstract public function get(CacheKey $k);

	/**
	 *
	 * @param string $base
	 * @param string $id
	 * @param any $sub
	 * @return any
	 * @throws NotCachedException
	 * @see getK
	 */
	public function getP($base, $id, $sub = null) {
		return $this->get(new CacheKey($base, $id, $sub));
	}

	/**
	 *
	 * @param CacheKey $k
	 * @return CacheData
	 * @throws NotCachedException
	 */
	public function getData(CacheKey $k) {
		$cd = CacheData::unserialize($this->get($k));
		if ($cd->checkUpdateToDate($this)) {
			return $cd;
		}
		throw new NotCachedException();
	}

	public function getDataP($base, $id, $sub = null) {
		return $this->getData(new CacheKey($base, $id, $sub));
	}

	/**
	 * Gets data from multiple cache keys
	 *
	 * Backends may override this to provide an efficient implementation
	 *
	 * @param multitype:CacheKey $cacheid
	 * @return multitype:any array with data, using same keys as cacheid. Keys not
	 * found in cache won't be present, but no exception will be generated
	 */
	public function getMulti(array $cacheid, $callback = null) {
		$retval = [];
		foreach ($cacheid as $k => $c) {
			try {
				$retval[$k] = $this->get($c);
			}
			catch (NotCachedException $e) {
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
	 * Saves cache information.
	 *
	 * @param any $data Data to save in cache
	 * @param CacheKey $k
	 * @param integer $lifetime The lifetime, although it is up to the implementation whether
	 * it is respected or not.
	 * @return boolean true if no problem
	 */
	abstract public function store($data, CacheKey $k, $lifetime = 0);

	/**
	 * @param unknown $data
	 * @param unknown $base
	 * @param unknown $sub
	 * @param unknown $id
	 * @param number $lifetime
	 * @see store()
	 */
	public function saveP($data, $base, $id, $sub = null, $lifetime = 0) {
		return $this->store($data, new CacheKey($base, $id, $sub), $lifetime);
	}

	/**
	 * @param unknown $data
	 * @param unknown $base
	 * @param unknown $sub
	 * @param unknown $id
	 * @param number $lifetime
	 * @see store()
	 */
	public function storeP($data, $base, $id, $sub = null, $lifetime = 0) {
		return $this->store($data, new CacheKey($base, $id, $sub), $lifetime);
	}

	public function storeData(CacheData $data, $lifetime = 0) {
		return $this->store($data->updateDependenciesHash($this)->serialize(), $data->key, $lifetime);
	}

	/**
	 * Deletes an entry from the cache
	 *
	 * @param CacheKey $k
	 * @return unknown_type
	 */
	abstract public function delete(CacheKey $k);

	public function deleteP($base, $id, $sub = null) {
		return $this->delete(new CacheKey($base, $id, $sub));
	}

	/**
	 * Cleans cache: all entries with a certain $base and $id in the $key
	 * are deleted.
	 *
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
	 * @param array $data array(0 => array('base', 'id', 'sub'), ...)
	 * @return unknown_type
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
	 *
	 * @param string $base Base string name for the type of cache (e.g., Event)
	 * @param string $id Item id
	 * @param string $sub If an item is cache in parts, this is used to specify the parts.
	 * @param string $lifetime The lifetime, in seconds
	 * @return boolean True if cached
	 * @review
	 */
	public function start(CacheKey $k, $lifetime = null, $print = true, $fail = false) {
		$this->extraSub($k->sub);

		return $this->recursivestart($k, $lifetime, $print, $fail);
	}

	public function recursiveStartP($base, $id, $sub = null, $lifetime = null, $print = true, $fail = false) {
		return $this->recursivestart(new CacheKey($base, $id, $sub), $lifetime, $print, $fail);
	}

	public function startP($base, $id, $sub = null, $lifetime = null, $print = true, $fail = false) {
		return $this->start(new CacheKey($base, $id, $sub), $lifetime, $print, $fail);
	}

	/**
	 *
	 * @param CacheKey $k
	 * @param integer $lifetime
	 * @param callable $c
	 */
	public function startCallback(CacheKey $k, callable $c, $lifetime = null) {
		$data = $this->start($k, $lifetime);
		if (!$data) {
			$c();
			$this->end();
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
	 * @param string $base
	 * @param string $id
	 * @param any $sub
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

		} catch (NotCachedException $e) {
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

		$mainkey = $cachedata->generateDependenciesHash($this);
		$mainkey = $this->keyFromDeps($cachedata->getKey(), $cachedata->dependencies);
		$this->store($cachedata, 'cacherecursive', 0, $mainkey);
		$this->storeData($cachedata);

		// if recursive
		$this->inloop--;
		if ($this->inloop > 0) {
			return false;
		}

		if ($print) {
			$key = "cache-" . rand();
			// @codeCoverageIgnoreStart
			if (self::$debugOnPage) {
				echo '<span class="debug-probe-begin"
					data-key="' . $key .
					'" data-base="' . $cachedata->base .
					'" data-id="' . $cachedata->id .
					'" data-sub="' . print_r($cachedata->sub, true) .
					'" data-lifetime="' . $cachedata->lifetime .
					'" data-type="save"></span>';
			}
			// @codeCoverageIgnoreEnd

			echo $cachedata->stringify($this);

			// @codeCoverageIgnoreStart
			if (self::$debugOnPage) {
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
	protected function printProbeStart($key, $cachedata, $type) {
		echo '<span class="debug-probe-begin"
			data-key="' . $key .
			'" data-base="' . $cachedata->base .
			'" data-id="' . $cachedata->id .
			'" data-sub="' . print_r($cachedata->sub, true) .
			'" data-lifetime="' . $cachedata->lifetime .
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
	protected function printProbeEnd($key, $cachedata) {
		echo '<span class="debug-probe-end" data-key="' . $key . '"></span>';
	}

	/**
	 *
	 * @param CacheKey $k
	 * @param integer $lifetime if null uses the class default
	 * @param boolean $print
	 * @param boolean $fail if true throws a NotCachedException if not cached.
	 * @throws NotCachedException
	 * @throws CacheKeyClashException
	 * @return boolean
	 */
	public function recursivestart(CacheKey $k, $lifetime = null, $print = true, $fail = false) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		// check if we are inside another cache for automatic dependencies.
		$cachedata = null;
		try {
			$cachedata = $this->getData($k);

			if (!$cachedata->checkUpdateToDate($this)) {
				// stale
				$cachedata = null;
			}
			// TODO $this->prefetch($cachedata->getDependencies());
		}
		catch (NotCachedException $e) {
		}

		/* @var $cachedata CacheData */
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
					if (self::$debugOnPage) {
						$this->printProbeStart($key, $cachedata, 'hit');
					}
					// @codeCoverageIgnoreEnd

					echo $retval;

					// @codeCoverageIgnoreStart
					if (self::$debugOnPage) {
						$this->printProbeEnd($key, $cachedata);
					}
					// @codeCoverageIgnoreEnd
				}
				return $retval;
			}
			catch (NotCachedException $e) {
				$this->delete($k); // clear recursively
				if ($this->inloop) {
					throw $e;
				}
			}
		}
		if ($fail) {
			throw new NotCachedException();
		}

		$this->inloop++;
		$cd = new CacheData();
		$cd->setKey($k)->setLifetime($lifetime ? $lifetime : $this->lifetime);
		$this->loopdata[$this->inloop] = $cd;

		if ($this->inloop > 1) {
			// we are recursive. push whatever we have so far in the previous cache
			$data = ob_get_contents();
			ob_clean();

			foreach ($this->loopdata as $l) {
				/* @var $l CacheData */
				if ($l == $cd) { // don't depend on itself
					continue;
				}

				if ($l->checkClash($k)) {
					throw new CacheKeyClashException();
				}
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

	public function recursiveEnd($print = true) {
		// @codeCoverageIgnoreStart
		if (!$this->enabled) {
			return false;
		}
		// @codeCoverageIgnoreEnd

		$data = ob_get_clean();

		/* @var $cachedata CacheData */
		$cachedata = $this->loopdata[$this->inloop];
		$cachedata->appendData($data);

		$mainkey = $cachedata->generateDependenciesHash($this);
		$mainkey = $this->keyFromDeps($cachedata->getKey(), $cachedata->dependencies);
		$this->store($cachedata, 'cacherecursive', 0, $mainkey);
		$this->storeData($cachedata);

		// if recursive
		$this->inloop--;
		if ($this->inloop > 0) {
			return false;
		}

		if ($print) {
			$key = "cache-" . rand();
			// @codeCoverageIgnoreStart
			if (self::$debugOnPage) {
				$this->printProbeStart($key, $cachedata, 'save');
			}
			// @codeCoverageIgnoreEnd

			echo $cachedata->stringify($this);

			// @codeCoverageIgnoreStart
			if (self::$debugOnPage) {
				$this->printProbeEnd($key, $cachedata);
			}
			// @codeCoverageIgnoreEnd
			return;
		}

		return $cachedata->stringify($this);
	}

	/**
	 * Ends the cache start().
	 *
	 */
	public function end($print = true) {
		return $this->recursiveend($print);
	}

	/*
	 * DEBUG
	 */

	/**
	 * High level log for testing and debugging
	 *
	 * @codeCoverageIgnore
	 */
	static public function logHigh($message) {
		if (self::$debugLogToFile) {
			file_put_contents('/tmp/logcache', $message, FILE_APPEND);
		}
	}

	/**
	 * Logs cache accesses for debugging
	 *
	 * @param CacheLogEnum $status
	 * @param string $data The message to print.
	 * @codeCoverageIgnore
	 */
	protected function log($status, CacheKey $k, $lifetime = 0) {
		self::$summary[$status]++;

		if ($this->should_log == false) {
			return;
		}

		$bt = debug_backtrace();
		$trace = $bt[1]['function'] . ' at ' . $bt[1]['file'] . ':' . $bt[1]['line'];
		$this->cache_log[] = array(
			'status' => $status,
			'message' => "(" . $k->debug() . ", $lifetime) by " . $trace
		);
	}

	/**
	 * Dumps a short HTML summary of the cache hits/misses
	 * @codeCoverageIgnore
	 */
	static public function dumpSummary() {
		echo '<div id="cache-summary">Cache Summary (non-ajax): ';
		foreach (self::getLogSummary() as $key => $val) {
			echo $key . '=>' . $val . ' / ';
		}
		echo '</div>';
	}

	/**
	 * Extensive footer debug code. Shows which parts of the HTML were
	 * cached or missed visually. Great!
	 * @codeCoverageIgnore
	 */
	static public function footerDebug() {
		if (!self::$debugOnPage) {
			return;
		}
		?>
<script>
$(function() {
	var probes = $('.debug-probe-begin');
	if (probes.length != $('.debug-probe-end').length) {
		alert('Woooooooh! Cache starts do not match cache ends!');
	}

	for (var i = 0; i < probes.length; i++) {
		var p = $(probes[i]);
		var end = $('.debug-probe-end[data-key="' + p.data('key') + '"]');
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
		var debugel = $('<div class="debug-view" style="' + style + '"></div>');
		var innerdata = '<span class="debug-view-innerdata">';
		$.each(p.data(), function (name, value) {
			debugel.attr("data-" + name, value);
			innerdata += name + ": " + value + "<br/>";
		});
		innerdata += '</span>';
		debugel.append(innerdata);
		$('body').append(debugel);
	}
	$('body').append('<div class="debug-overview">' + probes.length + ' probes</div>');
});
</script>
<?php
	}
}
