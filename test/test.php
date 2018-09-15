<?php

use eftec\CacheOneRedis;

include "../vendor/autoload.php";
echo "<h1>connecting</h1>";
$cache=new CacheOneRedis("127.0.0.1","",6379);
$cache->set("group","key1","hello world");
$cache->set("group","key2","hola mundo");
var_dump($cache->get("group","key1"));
$cache->invalidate("key1");
$cache->invalidateGroup("group");
