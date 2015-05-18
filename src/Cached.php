<?php

namespace Cachearium;

/**
 * Interface for classes which cache data.
 */
interface Cached {
	/**
	 * Clean all caches created by the class. Used when a new version is
	 * deployed to avoid stale data.
	 *
	 */
	abstract public function cacheClean();
}