[![Build Status](https://travis-ci.org/Corollarium/cachearium.svg)](https://travis-ci.org/Corollarium/cachearium)
[![Code Coverage](https://scrutinizer-ci.com/g/Corollarium/cachearium/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Corollarium/cachearium/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/corollarium/cachearium/v/stable.svg)](https://packagist.org/packages/corollarium/cachearium)
[![Latest Unstable Version](https://poser.pugx.org/corollarium/cachearium/v/unstable.svg)](https://packagist.org/packages/corollarium/cachearium)
[![License](https://poser.pugx.org/corollarium/cachearium/license.svg)](https://packagist.org/packages/corollarium/cachearium)

# Cachearium

High level cache in your PHP applications. What, another one? Nope, this one is better. Fast, simple and with easy invalidation. Includes:

- recursive cache system, all the nested russian dolls you ever wanted
- easy to integrate with your [existing classes](#cache-associated-with-an-object-model-that-you-can-easily-clean)
- key based cache [expiration](https://signalvnoise.com/posts/3113), no more headaches to invalidate stuff
- [multiple dependencies](#store-a-value-with-multiple-dependencies)
- lifetime expiration, because stuff rots
- [low level cache](#store-a-single-value-and-invalidate-it) storage access, when you want to go raw
- lots of [examples](https://github.com/Corollarium/cachearium/tree/master/example) and [tests](https://github.com/Corollarium/cachearium/tree/master/test) ready to copy/paste
- [variable fragments](#cache-with-a-variable-fragment) for things that are almost the same but not quite
- [pluggable backend modules](#backends): RAM, Memcached, Filesystem and you can add your own
- [detailed logs](#to-see-a-detailed-log) for profiling and debugging, and also see what is cached [live in your webpage](#live-cache-probes)

Cachearium was developed by [Corollarium](https://corollarium.com) because we needed a great cache system.

# Installation

## Composer

Add this to your composer.json: [see packagist](https://packagist.org/packages/corollarium/cachearium)

If you prefer the cutting edge version, with only the freshest bugs: 

```
"corollarium/cachearium": "dev-master"
```

## Manual

No composer? No fret!

- Download the package
- Include `require_once('cachearium/Cached.php');`

# Debug and profile

## Live cache probes

![Cachearium cache debug probes](https://raw.githubusercontent.com/Corollarium/cachearium/master/example/cacheariumprobe.png)

Image showing cache debug probes. Pink areas are not cached. Green areas are cached. Note that they are nested. The red squares show the dialog with information about each cache hit/miss so you can easily see the cache key, which backend was used and other relevant information. 

Probes are only available when you call start()/end().

```php

$cache::$debugOnPage = true;

...
if (!$cache->start($key)) {
	// some stuff
	$cache->end();
}
...

// this is required for the probes
$cache->footerDebug();

```

## To see a detailed log

```php

$cache->setLog(true);
....
$cache->report(); // will print a detailed report
```

# Use cases/examples

See the example/ directory, because it's all there for you. Really, just point a webserver
there and play.

## Store a single value and invalidate it

This is basic storage.

```php
$data = 'xxxx';

// store
$cache = CacheAbstract::factory('your backend');
$cache->store($data, new CacheKey('Namespace', 'Subname'));

// get it later
try { 
	$data2 = $cache->get(new CacheKey('Namespace', 'Subname'));
	// $data2 == 'xxxx';
}
catch (NotCachedException($e)) {
	// handle not cached
}

// store new value with automatic invalidation
$data = 'yyy';
$cache->store($data, new CacheKey('Namespace', 'Subname'));
```

## Store using CacheData

CacheData provides a more sophisticated class to store values.


```php
$data = 'xxxx';

// store
$cache = CacheAbstract::factory('your backend');
$cache->storeData(new CacheData(new CacheKey('Namespace', 'Subname'), $data));

// get it later
try { 
	$data2 = $cache->getData(new CacheKey('Namespace', 'Subname'));
	// $data2->getFirstData() == 'xxxxx'
}
catch (NotCachedException($e)) {
	// handle not cached
}

// store new value with automatic invalidation
$lifeTime = 3000;
$cache->storeData(new CacheData(new CacheKey('Namespace', 'Subname')), $lifeTime);
```

## Store a value with multiple dependencies

You can have multiple dependencies so you can invalidate all cache data that
relate to a certain key.  

```php
$cache = CacheAbstract::factory('your backend');

// create a storage key and bucket
$key = new CacheKey('Namespace', 'Subname');
$cd = new CacheData($key, $data);

// add dependencies. setDependencies will generate immediately, avoiding races.
// otherwise you find results, the DB changes in another process and you get a
// stale dependency. note that addDependencies does not do that, leaving the
// hash to be calculated later
$dep = new CacheKey('Namespace', 'SomeDep');
$cd->setDependencies([$dep]);

// store.
$data = 'xxxx';
$cache->storeData($cd);

// at this point $cache->get($key) will return your data

// invalidate a dependency. This will be called on your write method.
$cache->invalidate($dep);

// at this point $cache->get($key) will throw an NotCachedException
```

### Example: Store searches and invalidate them when an attribute is written to

```php
function someSearch() {
	$key = new CacheKey('search', 'someSearch'); // keys for this data
	$cache = CacheAbstract::factory('backend');
	try {
		return $cache->get($key); // TODO
	}
	catch (NotCachedException($e)) {
		// handle not cached below
	}

	$searchdata = getSomeData(); // results of some horrible slow query

	// attributes that are used in this search
	$dependencies = [
		new CacheKey('attribute', 'name'), 
		new CacheKey('attribute', 'description')
		new CacheKey('attribute', 'cost')
	];
	
	// create cache data
	$cd = 
	$cache->storeData(
		(new CacheData($key, $searchdata))
		->setDependencies($dependencies);
	);
	
	return $searchdata;
}

function writeSomeStuff() {
	// changed or added some attribute value in some object

	$cache = CacheAbstract::factory('backend');
	$cache->invalidate(new CacheKey('attribute', 'name')); // invalidates any cache that depends on this key
}
```

## Cache associated with an object/model that you can easily clean

It's likely that you have a MVC application. Model classes can easily cache data

```php
class Foo extends Model {
	use Cached;
	
	/**
	 * Unique object id in your application (primary key)
	 */
	public function getId() {
		return $this->id;	
	}
	
	public function cacheClean() {
		$cache = CacheAbstract::factory('backend');
		$cache->clean('Foo', $this->getId());
	}
	
	public function save() {
		// save stuff on db
		$this->cacheClean(); // clear any cache associated with this item
	}

	public function cacheStore($data, $key) {
		$cache = CacheAbstract::factory('backend');
		return $cache->save($data, 'Foo', $this->getId(), $key);
	}

	public function cacheGet($key) {
		$cache = CacheAbstract::factory('backend');
		return $cache->get('Foo', $this->getId(), $key);
	}
}
```

## Nested cache for contents. Useful for generating HTML made of fragments

This uses the russian doll approach to bubble up any invalidations. This means that
if you have a list of items and you change one of them, you only invalidate its own
cache entry and the entry for the whole list. You can regenerate the list with a 
single DB hit. 

```php

	$cache = CacheAbstract::factory('your backend');
	
	$cache->start(new CacheKey('main'));
	
		$cache->start(new CacheKey('header'));
		$cache->end();
		
		foreach ($somestuff as $stuff) {
			$stuff->render();
		}
	
		$cache->start(new CacheKey('footer'));
			
		$cache->end();
	$cache->end();
	
	class Stuff {
		public function getCacheKey() {
			return new CacheKey('stuff', $this->getId());
		}
		
		public function write() {
			write_some_stuff();
	
			$cache = CacheAbstract::factory('your backend');
			$cache->clean($this->getCacheKey());
		}
	
		public function render() {
			$cache = CacheAbstract::factory('your backend');
			$cache->start($stuff->getCacheKey()->setSub('render'));
	
			$html = '<p>some html here</p>';
	
			// other dependency if you have it
			$cache->addDependency($otherstuff->getCacheKey()); 
			
			$cache->end();
		}
	}
```

## Cache with a variable fragment
This is how to handle something such as a site header, that is almost completely the
same except for a small part that varies for each user.

```php

	function callbackTesterStart() {
		return rand();
	}

	$key = new CacheKey("startcallback", 1);
	$cache->start($key);
	echo "something ";
	
	// this will never be cached, every call to start() will use the rest
	// of the cached data and call the callback everytime for the variable data 
	$cache->appendCallback('callbackTesterStart');
	
	// everything goes on as planned here
	echo " otherthing";
	$output = $cache->end(false);
```

## Always add something specific to the cache keys

Let's say for example you have a multi-language website. Caching fragments will 
always need to add the language as part of the key. Cachearium provides a simple
way to do this by creating a special function:

```php
function application_cacheDependencies() {
	// can return an array or a string
	return [Application::getLanguage(), somethingelse()];
}
```

This will be added automatically to your keys in every call to start(). If you 
need to override this for a single call, use recursiveStart() instead.

## Cleaning the house

You can clear the entire cache with `$cache->clear()` or `CacheAbstract::clearAll()`.

# Backends

## Null

Does nothing. You can use it to turn off your caches for tests without changing
any code calls.

## RAM

Caches in RAM, for the duration of the request only. Useful for quick caches that
should not persist between requests.

## Memcache

Uses Memcache as a backend, and stores data in RAM temporarily to avoid repeated
requests in the same run.

## Filesystem

Stores in the filesystem.