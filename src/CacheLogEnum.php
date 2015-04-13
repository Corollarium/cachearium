<?php

namespace Cachearium;

/**
 * Enum Class for cache logs
 *
 * @author corollarium
 *
 */
class CacheLogEnum {
	const ACCESSED = 'accessed';
	const MISSED = 'missed';
	const DELETED = 'deleted';
	const CLEANED = 'cleaned';
	const SAVED = 'saved';
	const PREFETCHED = 'prefetched';

	public static function getNames() {
		return array(
			self::ACCESSED   => '<span class="cache-success">Accessed</span>',
			self::MISSED     => '<span class="cache-miss">Missed</span>',
			self::DELETED    => '<span class="cache-deleted">Deleted</span>',
			self::CLEANED    => '<span class="cache-cleaned">Cleaned</span>',
			self::SAVED      => '<span class="cache-save">Saved</span>',
			self::PREFETCHED => '<span class="cache-prefetched">Prefetched</span>'
		);
	}

	/**
	 * Returns an array with all enum values.
	 * @return array
	 * @codeCoverageIgnore
	 */
	static public function getAll() {
		return array_keys(static::getNames());
	}

	/**
	 * Checks if a value is a valid grant
	 *
	 * @param string $value
	 * @return boolean true if valid
	 * @codeCoverageIgnore
	 */
	static public function valid($value) {
		return array_key_exists($value, static::getNames());
	}

	/**
	 * Given a name, returns its value or a string saying it is invalid.
	 *
	 * @param string $value
	 * @return string
	 * @codeCoverageIgnore
	 */
	static public function getName($value) {
		if (static::valid($value)) {
			$x = static::getNames();
			return $x[$value];
		}
		return 'Invalid: ' . htmlspecialchars($value);
	}
}
