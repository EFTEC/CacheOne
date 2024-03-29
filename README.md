# CacheOne
CacheOne is a cache class of service for php. It supports Redis, Memcache, PDO and/or APCU.

Unlikely other cache libraries, this library is based in group (optional). So it's suitable to invalidate a single key 
or an entire group of elements.



[![Packagist](https://img.shields.io/packagist/v/eftec/CacheOne.svg)](https://packagist.org/packages/eftec/CacheOne)
[![Total Downloads](https://poser.pugx.org/eftec/CacheOne/downloads)](https://packagist.org/packages/eftec/CacheOne)
[![Maintenance](https://img.shields.io/maintenance/yes/2024.svg)]()
[![composer](https://img.shields.io/badge/composer-%3E1.6-blue.svg)]()
[![php](https://img.shields.io/badge/php-7.4-green.svg)]()
[![php](https://img.shields.io/badge/php-8.3-green.svg)]()
[![CocoaPods](https://img.shields.io/badge/docs-70%25-yellow.svg)]()


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

# Table of Contents

<!-- TOC -->
* [CacheOne](#cacheone)
* [Example](#example)
* [Table of Contents](#table-of-contents)
* [Definitions](#definitions)
  * [Creating a new instance of CacheOne](#creating-a-new-instance-of-cacheone)
  * [Storing a value](#storing-a-value)
  * [Getting a value](#getting-a-value)
  * [setDefaultTTL](#setdefaultttl)
  * [Pushing and Popping values form an array](#pushing-and-popping-values-form-an-array)
    * [push](#push)
    * [unshift](#unshift)
    * [pop](#pop)
    * [shift](#shift)
  * [invalidate a key](#invalidate-a-key)
  * [invalidate a group](#invalidate-a-group)
  * [invalidate all](#invalidate-all)
  * [setSerializer($serializer)](#setserializerserializer)
  * [getSerializer();](#getserializer)
  * [Select a database (Redis/PdoOne)](#select-a-database-redispdoone)
* [CLI](#cli)
    * [Example REDIS](#example-redis)
* [Version](#version)
* [License](#license)
<!-- TOC -->

# Definitions


## Creating a new instance of CacheOne

Creates a new connection using Redis (Redis is an on memory cache library)

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("redis","127.0.0.1","",6379);
```

Creates a new connection using apcu (APCU is an extension for PHP to cache content)

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("apcu");
```

Creates a new connection using **PdoOne** (PdoOne is a library to connect to the database using PDO)

```php
use eftec\PdoOne;
use eftec\CacheOne;
include "../vendor/autoload.php";

$pdo=new PdoOne('mysql','127.0.0.1','root','abc.123','travisdb',false,null,1,'KVTABLE');
$pdo->logLevel=3; // optional, if you want to debug the errors. 
$pdo->open();
// $pdo->createTableKV();  // you should create the key-value table if it doesn't exist.
$cache=new CacheOne("pdoone"); // the instance $pdo is injected automatically into CacheOne.
```
or you could use to create a PdoOne instance:
```php
$cache=new CacheOne(
  "pdoone",
  ['mysql','127.0.0.1','root','abc.123','travisdb',false,null,1,'KVTABLA']
  ); 
```

Creates a new connection using memcache (Memcache is an old, but it is still a functional memory cache server)

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("memcache","127.0.0.1"); // minimum configuration
$cache=new CacheOne("memcache","127.0.0.1",11211,'schema'); // complete configuration
```

Creates a new connection using the class **DocumentOne** (file system)

This example requires the library **eftec/documentstoreone**

```php
use eftec\CacheOne;
include "../vendor/autoload.php";
$cache=new CacheOne("documentone",__DIR__."/base","schema"); // folder /base/schema must exists
```

The library **DocumentStoreOne** works with concurrency.

or creating a new connection, using redis, memcache, apcu or documentone (it takes the first available)

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
Group is optional, and it could be used if we need to invalidate (delete) an entire group.

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

## Pushing and Popping values form an array

### push

It pushes (adds) a new value at the **end of the array**.  If the array does not exist, then it is created a new array. This command allows to limit the numbers of elements of the array.

Syntax:
> push($groups, $key, $value, $duration = null, $limit = 0, $limitStrategy = 'shiftold') : bool

* **$Limit** is used to limit the number maximum of elements of the array, if zero, then it does not limit the elements.
* **$LimitStrategy** is used to determine what to do when we are adding a new element and the limit has been reached, 
  **shiftold** removes the first element of the array, **nonew** does not allow to add a new element, **popold** removes the latest element of the array
  (if the limit has been reached).

```php
// cart could be [1,2,3]
$cache->push('','cart',4,2000); // it adds a new element into the cart unlimitely cart is [1,2,3,4]
$cache->push('','cart',5,2000,4,'shiftold'); // it limits the cart to 20 elements, pop old item if req. cart is [2,3,4,5]
$cache->push('','cart',6,2000,4,'nonew'); // if the cart has 20 elements, then it doesn't add $item. cart now is [2,3,4,5]
```

### unshift

It unshift (add) a new value at the **beginner of the array**.  If the array does not exist, then it is created a new array. This command allows to limit the numbers of elements of the array.

Syntax:

> unshift($groups, $key, $value, $duration = null, $limit = 0, $limitStrategy = 'popold') : bool

* **$Limit** is used to limit the number maximum of elements of the array, if zero, then it does not limit the elements.
* **$LimitStrategy** is used to determine what to do when we are adding a new element and the limit has been reached, **shiftold** removes the first element of the array, **nonew** does not allow to add a new element, **popold** removes the latest element of the array
  (if the limit has been reached).

```php
// cart could be [1,2,3]
$cache->unshift('','cart',4,2000); // it adds a new element into the cart unlimitely cart is [4,1,2,3]
$cache->unshift('','cart',5,2000,4,'shiftold'); // it limits the cart to 20 elements, pop old item if req. cart is [2,3,4,5]
$cache->unshift('','cart',6,2000,4,'nonew'); // if the cart has 20 elements, then it doesn't add $item. cart now is [2,3,4,5]
```



### pop

It pops (extract) a value at the **end of the array**. If the value does not exist then it returns **$defaultValue** The original array is modified removing the last element of the array.

Syntax:

>  pop($group, $key, $defaultValue = false, $duration = null) : mixed

```php
// cart could be [1,2,3,4];
$element=$this->pop('','cart'); // now cart is [1,2,3] and $element is 4
```

### shift

It shifts (extract) a value at the **beginner of the array**. If the value does not exist then it returns **$defaultValue** The original array is modified removing the last element of the array.

Syntax:

>  pop($group, $key, $defaultValue = false, $duration = null) : mixed

```php
// cart could be [1,2,3,4];
$element=$this->shift('','cart'); // now cart is [2,3,4] and $element is 1
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

It invalidates every key(s) inside a group of groups.  It also cleans the catalog of the group and sets it to an empty array.

```php
$cache->invalidateGroup("group"); // invalidate all keys inside group
$cache->invalidateGroup(["group1","group2"]); // invalidate all key inside group1 and group2
```

## invalidate all

> invalidateAll()

It invalidates all cache.

In **redis**, it deletes the current schema. If not schema, then it deletes all Redis

In **memcached** and <b>apcu</b>, it deletes all cache

In <b>documentone</b> it deletes the content of the database-folder

```php
$cache->invalidateAll(); 
```

## setSerializer($serializer)

It sets how the values are serialized.  By default, it's PHP.

```php
$cache->setSerializer('php'); // set the serializer to php (default value). It is fastest but it uses more space.
$cache->setSerializer('json-array'); // set the serializer to json-array. It uses fewer space than PHP however it is a bit slower.
$cache->setSerializer('json-object'); // set the serializer to json-object.
$cache->setSerializer('none'); // set the serializer to none (the value must be serialized)
 
```

## getSerializer();

Get then how the values are serialized.

```php
$type=$cache->getSerializer(); // get php,json-array,json-object or none
```

## Select a database (Redis/PdoOne)

>  select($dbindex) 

It selects a different database. By default, the database is 0.

```php
$cache->select(1);
$cache->select('table'); // PdoOne
```

# CLI
This library also has an interactive CLI.In this CLI, you can create the configuration, test it, load and save.
![example/cli1.jpg](example/cli1.jpg)

To open it, you must run the next script:

```shell
php vendor/bin/cacheonecli
# or (windows)
vendor/bin/cacheonecli.bat
```


### Example REDIS
* Open the CLI
* go to menu cache
* select redis
* select yes
* and select the default information
* Once it is done, select test to test the connection

![example/cliredis.jpg](example/cliredis.jpg)

* and finally, save the configuration (select save)
* select yes
* select the filename (c1 in this example)

![example/cliredis2.jpg](example/cliredis2.jpg)

The configuration will be saved as: **c1.config.php**.  
It is a configuration file that you can include or copy and paste in your code

```php
<?php // eftec/CliOne(1.28) PHP configuration file (date gen: 2023-04-08 10:19). DO NOT EDIT THIS FILE 
/**
 * It is a configuration file
 */
$cacheOneConfig=[
    'type' => 'redis',
    'cacheserver' => '127.0.0.1',
    'schema' => '',
    'port' => '6379',
    'user' => NULL,
    'password' => NULL,
];
```
example
```php
include "c1.config.php";
$cache=CacheOne::factory($cacheOneConfig); 
```


# Version
* 2.18 (2024-03-02)
  * Updating dependency to PHP 7.4. The extended support of PHP 7.2 ended 3 years ago.
  * Added more type hinting in the code.
* 2.17
  * [composer.json] updated dependency
  * [CacheOneCli] now loads a PdoOneCli instance if avaible
* 2.16.1 2023-04-08
  * [composer.json] added cacheonecli to the /bin
  * [CacheOne] added method factory()
  * [CacheOneCli] configuration now uses the variable $cacheOneConfig
* 2.16 2023-04-08
  * [CacheOneCli] (1.3) This version breaks dependency with PdoOne. Now, it is optional. 
* 2.15 2023-04-07
  * [CacheOneCli] Updated to 1.2 
  * [CacheOne] updated the constructor, so it allows to pass the configuration of PdoOne as an array
* 2.14
  * updated dependencies. 
* 2.13
  * **[redis]** fixed a problem with redis and get() 
* 2.12.4
  * **[fixed]** solved a problem when the cache is not found.
* 2.12.3
  * **[fixed]** solved a problem with invalidateCache() 
* 2.12 2022-06-12
  * **[fixed]** CacheOne now it could be injected correctly in any case.
  * **[new]** **[redis]**  In Redis, the $schema is used to set the database (if numeric), or to prefix the values.
* 2.11 2022-03-20
  * **[fixed]** added more type "hinting" (type validation) 
  * **[new]** It allows to obtain an instance (if any) of CacheOne using the static method CacheOne::instance()
  * **[new]** It allows to obtain an instance of the provider (PdoOne, DocumentStoreOne, Redis, Memcache) $this->getInstanceProvider()
* 2.10 2022-03-15
  * **[new]** method getRenew() that get a value and renews its duration.
  * **[update]** Provider DocumentOne now works with TTL.
* 2.9 2022-03-12
  * Cleared some references and added type hinting to the code. 
  * In Redis: invalidateAll() does not delete all the server if there is a schema.
  * in PdoOne: set() does not crash in PHP 8.1 if the catalog is null.
* 2.8 2022-02-10
  * Added a new provider PdoOne (Pdo / Database).
  * Dropped support for PHP 7.1 and lower. If you want to use an old version of this library then you can stay with the version 2.7.
- 2.7 2021-06-13
  * method get() used by provider, never needed the family/group, so it is removed. It is the last version for 2.7
- 2.6.1 2021-06-12
  * changed dependencies in composer.json
- 2.6 2021-06-12
    - added the methods push(), pop(), shift() and unshift()
- 2.5 2020-09-20
    * Separated provider in different classes. Now it also allows to use the file system (**DocumentOne**).   
- 2.4 2020-09-13
    * The code was refactored.   
- 2.3.1
    * fix: The catalog is always stored as an array, even if the serializer is json-object
- 2.3
    * Added method setSerializer() and getSerializer(). By default, CacheOne uses PHP for serialization.
    With this feature, it is possible to serialize using json or none
- 2.2.2 2020-03-13
    * Now the duration of the catalog is always laster than the duration of the key
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

Dual license, Commercial and MIT License. Copyright Jorge Castro Castillo
