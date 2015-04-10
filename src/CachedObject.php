<?php

namespace Cachearium;

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

