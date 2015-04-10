<?php

namespace Cachearium\Exceptions;

/**
 * Thrown when two items with the same exact set of (base, id, sub) are
 * used in different levels of the russian doll
 *
 * @author corollarium
 * @codeCoverageIgnore
 */
class CacheKeyClashException extends \Exception {
}