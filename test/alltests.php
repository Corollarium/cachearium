<?php
// @codeCoverageIgnoreStart
/**
 * Test suite
 */

if (!defined('TEST_MAIN_METHOD')) {
	define('TEST_MAIN_METHOD', 'AllTests::main');
}

class AllTests {
	public static function main() {
		PHPUnit_TextUI_TestRunner::run(self::suite());
	}

	public static function dirList ($directory) {
		// create an array to hold directory list
		$results = array();

		// create a handler for the directory
		$handler = opendir($directory);

		// keep going until all files in directory have been read
		while ($file = readdir($handler)) {
			if (strpos($file, 'Test.php')) {
				$results[] = $file;
			}
		}

		sort($results);
		closedir($handler);
		// done!
		return $results;
	}

	public static function suite() {
		$suite = new PHPUnit_Framework_TestSuite('cache');

		$files = self::dirList('.');
		foreach ($files as $file) {
			include($file);
			$testname = substr($file, 0, strpos($file, '.'));
			$slash = strpos($file, '/');
			if ($slash !== false) {
				$testname = substr($testname, $slash+1);
			}
			$suite->addTestSuite($testname);
		}

		return $suite;
	}
}

if (defined('PHPUnit_MAIN_METHOD')) {
	if (PHPUnit_MAIN_METHOD == 'AllTests::main') {
		AllTests::main();
	}
}

// @codeCoverageEnd