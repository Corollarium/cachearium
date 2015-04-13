<?php

require_once(__DIR__ . '/../src/Cache.php');

use Cachearium\Backend\CacheRAM;
use Cachearium\CacheKey;

$cache = CacheRAM::singleton();

// turn on page debugging on
$cache::$debugOnPage = true;

?>
<html>
<head>
<title>Cachearium debug probe</title>
<script src="https://code.jquery.com/jquery-2.1.3.min.js"></script>
<style>
<?php $cache->cssDebug(); ?>
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

if (!$cache->start(new CacheKey("outside", 1))) {
	// not cached

	// big div
	echo '<div class="bigoutsidediv"><h1>Outside</h1>';

	// some smaller divs
	for ($i = 0; $i < 3; $i++) {
		if (!$cache->start(new CacheKey("medium", $i))) {
			echo '<div class="mediumdiv">';

			// some even smaller divs
			for ($j = 0; $j < 3; $j++) {
				if (!$cache->start(new CacheKey("small", $j))) {
					echo '<div class="smalldiv">';
					echo "Got here $i - $j";
					echo '</div>';
					$cache->end();
				}
			}
			echo '</div>';
			$cache->end();
		}
	}

	// this should be cached
	echo '<p>This below will be cached, since we saved it above</p>';
	$cache->start(new CacheKey("medium", 0));

	echo '</div>';
	$cache->end();
}


$cache->footerDebug();
?>
</body>
</html>