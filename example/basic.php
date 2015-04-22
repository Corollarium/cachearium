<?php

require_once(__DIR__ . '/../src/Cache.php');

use Cachearium\Backend\CacheRAM;
use Cachearium\CacheKey;

$cache = CacheRAM::singleton();

// turn on page debugging on
$cache::$debugOnPage = true;
$cache->setLog(true);

?>
<html>
<head>
<title>Cachearium debug probe</title>
<script src="https://code.jquery.com/jquery-2.1.3.min.js"></script>
<style>
<?php $cache->cssDebug(); ?>

body {
	margin-top: 80px;
}

.bigoutsidediv {
	width: 100%;
}
.mediumdiv {
	width: 96%;
	margin: 2%;
	border: 1px solid blue;
	height: 100px;
}
.smalldiv {
	width: 31%;
	margin: 0 1%;
	float: left;
	height: 80px;
	border: 1px solid green;
}


</style>
</head>
<body>
<?php


function someCachedStuff() {
	$cache = CacheRAM::singleton();

	if (!$cache->start(new CacheKey("outside", 1))) {
		// not cached
		echo '<div>';
		echo "some random bla bla" . rand();
		echo '</div>';
		$cache->end();
	}
}

echo '<p>first time is not cached</p>';
someCachedStuff();

echo '<p>second time is cached</p>';
someCachedStuff();

echo '<hr/>';

$cache->footerDebug();

$cache->report();

?>
</body>
</html>