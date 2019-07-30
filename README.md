# CacheOne
CacheOne is a cache class of service for php


# Example

## Creating a cache (using Redis)

Creates a new connection to redis

```
use eftec\CacheOneRedis;
include "../vendor/autoload.php";
$cache=new CacheOneRedis("127.0.0.1","",6379);
```

## Storing a value

It store a value inside a group and a key.

```
$cache->set("group","key1","hello world");
$cache->set("group","key2","hola mundo");
```
Group is optional and it could be used if we need to invalidate (delete) an entire group.


## invalidate a key

It invalidates a specific key

```
$cache->invalidate("key1");
```


## invalidate a group

It invalidates a whole group of keys.

```
$cache->invalidateGroup("group");
```

## Select a database (Redis)

It selects a different database. By default the database is 0.

```
$cache->select(1);
```

# Version

- 1.4.2 2019-07-30 Added select() to select a different database index. It also adds timeout for the constructor
- 1.4.1 2018-08-15 Added an internal function that obtains the id.
- 1.4   2018-09-05 Fixed the groups
- 1.3.1 2018-06-09 First published version

# License

MIT License. Copyright Jorge Castro Castillo
