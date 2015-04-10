<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

/**
 * Thrown when the backend does not exists or cannot be instantiated
 *
 * @author corollarium
 * @codeCoverageIgnore
 */
class CacheInvalidBackendException extends Exception {
}

/**
 * Thrown when the item is not cached.
 *
 * @author corollarium
 * @codeCoverageIgnore
 */
class NotCachedException extends Exception {
}

/**
 * Thrown when a parameter is invalid
 *
 * @author corollarium
 * @codeCoverageIgnore
 */
class CacheInvalidParameterException extends Exception {
}

/**
 * Thrown when two items with the same exact set of (base, id, sub) are
 * used in different levels of the russian doll
 *
 * @author corollarium
 * @codeCoverageIgnore
 */
class CacheKeyClashException extends Exception {
}

/**
 * Invalid data being stored.
 * @author corollarium
 * @codeCoverageIgnore
 */
class CacheInvalidDataException extends Exception {
}

/**
 * Backend does not support this operation.
 * @author corollarium
 *
 */
class CacheUnsupportedOperation extends Exception {
}