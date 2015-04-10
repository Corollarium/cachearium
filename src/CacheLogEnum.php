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
}
