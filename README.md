# CacheOne
CacheOne is a cache class of service for php. It supports Redis, Memcache and/or APCU.

[![Packagist](https://img.shields.io/packagist/v/eftec/CacheOne.svg)](https://packagist.org/packages/eftec/CacheOne)
[![Total Downloads](https://poser.pugx.org/eftec/CacheOne/downloads)](https://packagist.org/packages/eftec/CacheOne)
[![Maintenance](https://img.shields.io/maintenance/yes/2020.svg)]()
[![composer](https://img.shields.io/badge/composer-%3E1.6-blue.svg)]()
[![php](https://img.shields.io/badge/php-7.x-green.svg)]()
[![CocoaPods](https://img.shields.io/badge/docs-70%25-yellow.svg)]()

## Example

```
use eftec\CacheOne;
include "vendor/autoload.php"; // composer's autoload
$cache=new CacheOne("redis","127.0.0.1","",6379);

$cacheValue=$cache_get('','countries'); // read the cache (if any)
if($cacheValue===false) {
    echo "generating a new list of countries..<br>";
    $countries=['USA','Canada','Japan','Chile'];
    $cache->set('','countries',$countries,500); // store into the cache for 500 seconds.
} else {
    echo "read from cache<br>";
    $countries=$cacheValue;
}
var_dump($countries);


```


## Creating a new instance of CacheOne

Creates a new connection using redis

```
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("redis","127.0.0.1","",6379);
```

Creates a new connection using apcu

```
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("apcu");
```

Creates a new connection using memcache

```
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("memcache","127.0.0.1");
```


## Storing a value

> function set($group, $key, $value, $duration = 1440): bool

It store a value inside a group and a key.
It returns false if the operation failed.

```
$cache->set("group","key1","hello world",500);
$cache->set("group","key2","hola mundo",500);
```
Group is optional and it could be used if we need to invalidate (delete) an entire group.

## Getting a value

> function get($group, $key)

It gets a value stored in a group (optional) and key. If the
value is not found then it returns false. Note: a false value could be a valid value.

```
$result=$cache->get("group","key1");
$result=$cache->get("","key2"); 
```

## invalidate a key

> function invalidate($group = '', $key = ''): bool 

It invalidates a specific key. If the operation fails, then it returns false

```
$cache->invalidate("group",key1"); // invalidate a key inside a group
$cache->invalidate("",key1"); // invalidate a key without a group.
```


## invalidate a group

> invalidateGroup($group): bool

It invalidates every key(s) inside a group of groups.  It also clean the catalog of the group and sets it to an empty array.

```
$cache->invalidateGroup("group"); // invalidate all keys inside group
$cache->invalidateGroup(["group1","group2"]); // invalidate all key inside group1 and group2
```


## Select a database (Redis)

>  select($dbindex) 

It selects a different database. By default the database is 0.

```
$cache->select(1);
```

# Version

- 2.0 2020-03-12 Updated the whole class. Now it works as a single class.
- 1.4.2 2019-07-30 Added select() to select a different database index. It also adds timeout for the constructor
- 1.4.1 2018-08-15 Added an internal function that obtains the id.
- 1.4   2018-09-05 Fixed the groups
- 1.3.1 2018-06-09 First published version

# License

MIT License. Copyright Jorge Castro Castillo
