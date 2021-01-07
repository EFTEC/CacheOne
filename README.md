# CacheOne
CacheOne is a cache class of service for php. It supports Redis, Memcache and/or APCU.

Unlikely other cache libraries, this library is based in group (optional). So it's suitable to invalidate a single key 
or an entire group of elements.



[![Packagist](https://img.shields.io/packagist/v/eftec/CacheOne.svg)](https://packagist.org/packages/eftec/CacheOne)
[![Total Downloads](https://poser.pugx.org/eftec/CacheOne/downloads)](https://packagist.org/packages/eftec/CacheOne)
[![Maintenance](https://img.shields.io/maintenance/yes/2021.svg)]()
[![composer](https://img.shields.io/badge/composer-%3E1.6-blue.svg)]()
[![php](https://img.shields.io/badge/php-7.x-green.svg)]()
[![php](https://img.shields.io/badge/php-8.x-green.svg)]()
[![CocoaPods](https://img.shields.io/badge/docs-70%25-yellow.svg)]()

- [CacheOne](#cacheone)
  * [Example](#example)
  * [Creating a new instance of CacheOne](#creating-a-new-instance-of-cacheone)
  * [Storing a value](#storing-a-value)
  * [Getting a value](#getting-a-value)
  * [invalidate a key](#invalidate-a-key)
  * [invalidate a group](#invalidate-a-group)
  * [invalidate all](#invalidate-all)
  * [Select a database (Redis)](#select-a-database--redis-)
- [Version](#version)
- [License](#license)



# Example

```php
use eftec\CacheOne;
include "vendor/autoload.php"; // composer's autoload
$cache=new CacheOne("redis","127.0.0.1","",6379);

$cacheValue=$cache->get('','countries'); // read the cache (if any) otherwise false
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

# Definitions


## Creating a new instance of CacheOne

Creates a new connection using redis

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("redis","127.0.0.1","",6379);
```

Creates a new connection using apcu

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("apcu");
```

Creates a new connection using memcache

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("memcache","127.0.0.1");
```

Creates a new connection using documentone (file system)

This example requires the library **eftec/documentstoreone**

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("documentone",__DIR__."/base","schema"); // folder /base/schema must exists
```

The library DocumentStoreOne works with concurrency.



or creating a new connection, or redis, or memcache or apcu or documentone (it takes the first available)

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("auto");
```


## Storing a value

> function set($group, $key, $value, $duration = 1440): bool

It stores a value inside a group and a key.
It returns false if the operation failed.

> Note: The duration is ignored by "documentone"

```php
$cache->set("group","key1","hello world",500);
$cache->set("group","key2","hola mundo",500);
```
Group is optional and it could be used if we need to invalidate (delete) an entire group.

## Getting a value

> function get($group, $key, $defaultValue = false)

It gets a value stored in a group (optional) and key. If the
value is not found then it returns false. Note: a false value could be a valid value.

```php
$result=$cache->get("group","key1");
$result=$cache->get("","key2");
$result=$cache->get("","key2","not found"); // if not key2 (groupless) then it returns not found 
```
## setDefaultTTL

```php
$result=$cache->setDefaultTTL(50); // it sets the default time to live. "documentone" one uses it.
$result=$cache->getDefaultTTL();   // it gets the time to live
```



## invalidate a key

> function invalidate($group = '', $key = ''): bool 

It invalidates a specific key. If the operation fails, then it returns false

```php
$cache->invalidate("group","key1"); // invalidate a key inside a group
$cache->invalidate("","key1"); // invalidate a key without a group.
```


## invalidate a group

> invalidateGroup($group): bool

It invalidates every key(s) inside a group of groups.  It also clean the catalog of the group and sets it to an empty array.

```php
$cache->invalidateGroup("group"); // invalidate all keys inside group
$cache->invalidateGroup(["group1","group2"]); // invalidate all key inside group1 and group2
```

## invalidate all

> invalidateAll()

It invalidates (and delete all the redis repository, memcache or apcu)

```php
$cache->invalidateAll(); 
```

## setSerializer($serializer)

It sets how the values are serialized.  By default it's PHP.

```php
$cache->setSerializer('php'); // set the serializer to php (default value)
$cache->setSerializer('json-array'); // set the serializer to json-array
$cache->setSerializer('json-object'); // set the serializer to json-object
$cache->setSerializer('none'); // set the serializer to none (the value must be serialized)
 
```

## getSerializer();

Get the how the values are serialized.

```php
$type=$cache->getSerializer(); // get php,json-array,json-object or none
```



## Select a database (Redis)

>  select($dbindex) 

It selects a different database. By default the database is 0.

```php
$cache->select(1);
```

# Version

      
- 2.5 2020-09-20
    * Separated provider in different classes. Now it also allows to use the file system (documentone).   
- 2.4 2020-09-13
    * The code was refactored.   
- 2.3.1
    * fix: The catalog is always stored as an array, even if the serializer is json-object
- 2.3
    * Added method setSerializer() and getSerializer(). By default CacheOne uses PHP for serialization.
    With this feature, it is possible to serialize using json or none
- 2.2.2 2020-03-13
    * Now the duration of the catalog is always lasting than the duration of the key
    * Tested the duration and expiration of the cache.
    * phpunit now is part of "require-dev" instead of "require"
- 2.2.1 2020-03-12
    * Internal: key names are not store inside the group. The group is store inside the schema
    * Internal: The catalog has a duration defined by $cache->catDuration (seconds)
- 2.2 2020-03-12
    * wrappers getCache(),setCache(),invalidateCache()
- 2.1 2020-03-12
    * Unit test
    * get() has a default value $defaultValue
    * new method invalidateAll()
- 2.0 2020-03-12 Updated the whole class. Now it works as a single class.
- 1.4.2 2019-07-30 Added select() to select a different database index. It also adds timeout for the constructor
- 1.4.1 2018-08-15 Added an internal function that obtains the id.
- 1.4   2018-09-05 Fixed the groups
- 1.3.1 2018-06-09 First published version

# License

MIT License. Copyright Jorge Castro Castillo
