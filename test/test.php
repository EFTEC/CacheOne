<?php

use eftec\CacheOneRedis;

include "../vendor/autoload.php";

$cache=new CacheOneRedis("127.0.0.1","",6379);
$cache->set("group","key1","hello world");
$cache->set("group","key2","hola mundo");
var_dump($cache->get("key1"));
$cache->invalidate("key1");
$cache->invalidateGroup("group");
