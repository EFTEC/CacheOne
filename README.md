# CacheOne
CacheOne is a cache class of service for php

First version

# Example

## Creating a cache (using Redis)
```
use eftec\CacheOneRedis;
include "../vendor/autoload.php";
$cache=new CacheOneRedis("127.0.0.1","",6379);
```

## Storing a value
```
$cache->set("group","key1","hello world");
$cache->set("group","key2","hola mundo");
```
Group is optional and it could be used if we need to invalidate (delete) an entire group.


## invalidate a key
```
$cache->invalidate("key1");
```


## invalidate a group
```
$cache->invalidateGroup("group");
```

# Version

- 1.3.1 2018-06-09 First published version

# License

MIT License. Copyright Jorge Castro Castillo
