<?php

use eftec\CacheOneRedis;

include "../vendor/autoload.php";

echo "<h1>testing errors</h1>";

echo "<h2>Connecting wrong redis</h2>";
$cache=new CacheOneRedis("192.168.0.1","",6379);
var_dump($cache->enabled);
echo "<h2>reading missing not connected</h2>";
var_dump($cache->get("group","keymissing"));
echo "<h2>Connecting right redis</h2>";
$cache=new CacheOneRedis("127.0.0.1","",6379);
var_dump($cache->enabled);
echo "<h2>reading missing id</h2>";
var_dump($cache->get("group","keymissing"));