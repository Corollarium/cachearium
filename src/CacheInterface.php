<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

/**
 * Interface for classes which cache data.
 */
trait Cached {
	/**
	 * Clean all caches created by the class. Used when a new version is
	 * deployed to avoid stale data.
	 *
	 */
	abstract public function cacheClean();
}

trait CachedObject {
	/**
	 * Returns a cache key for this object.
	 *
	 * This simplifies storage of data associated to a class. What you usually
	 * want to do here is a return new CacheKey('classname', $this->getId(), $atts);
	 *
	 * @return CacheKey
	 */
	abstract public function getCacheKey($atts = null);
}

