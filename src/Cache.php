<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

require_once(__DIR__ . '/CacheLogEnum.php');
require_once(__DIR__ . '/CacheKey.php');
require_once(__DIR__ . '/CacheException.php');
require_once(__DIR__ . '/CacheInterface.php');
require_once(__DIR__ . '/CacheData.php');
require_once(__DIR__ . '/CacheAbstract.php');

// backends
require_once(__DIR__ . '/backends/CacheNull.php');
require_once(__DIR__ . '/backends/CacheRAM.php');
// require_once(__DIR__ . '/backends/CacheAPC.php');
require_once(__DIR__ . '/backends/CacheFilesystem.php');
require_once(__DIR__ . '/backends/CacheMemcached.php');
