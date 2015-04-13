<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

// exceptions
require_once(__DIR__ . '/Exceptions/CacheInvalidBackendException.php');
require_once(__DIR__ . '/Exceptions/CacheInvalidParameterException.php');
require_once(__DIR__ . '/Exceptions/CacheUnsupportedOperation.php');
require_once(__DIR__ . '/Exceptions/CacheInvalidDataException.php');
require_once(__DIR__ . '/Exceptions/CacheKeyClashException.php');
require_once(__DIR__ . '/Exceptions/NotCachedException.php');

// CODE
require_once(__DIR__ . '/CacheLogEnum.php');
require_once(__DIR__ . '/CacheKey.php');
require_once(__DIR__ . '/CacheException.php');
require_once(__DIR__ . '/Cached.php');
require_once(__DIR__ . '/CachedObject.php');
require_once(__DIR__ . '/CacheData.php');
require_once(__DIR__ . '/CacheAbstract.php');

// backends
require_once(__DIR__ . '/Backend/CacheNull.php');
require_once(__DIR__ . '/Backend/CacheRAM.php');
// require_once(__DIR__ . '/backends/CacheAPC.php');
require_once(__DIR__ . '/Backend/CacheFilesystem.php');
require_once(__DIR__ . '/Backend/CacheMemcached.php');
