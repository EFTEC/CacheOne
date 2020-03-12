<?php


use eftec\CacheOne;
include "../vendor/autoload.php";



var_dump(class_exists('\eftec\DocumentStoreOne\DocumentStoreOne'));

/** @var $type=['redis','memcache','apcu'][$i] */
$type='apcu';

echo "<h1>Sandbox test. You could change the type of test by editing the line 6</h1>";


echo "<br><b>1- connecting ($type): it must not show error</b><br>";
$cache=new CacheOne($type);
$cache->select(0);

echo "<br><b>2- set two values in the same group: it must shows 2 true</b><br>";
var_dump($cache->set("group","key1","hello world"));
var_dump($cache->set("group","key2","hola mundo"));

echo "<br><b>3- get: it must show hello world:</b><br>";
var_dump($cache->get("group","key1"));
echo "<br><b>4- invalidate key</b><br>";
$cache->invalidate("key1");
echo "<br><b>5- invalidate group</b><br>";
$cache->invalidateGroup("group");
echo "<br><b>6- get: it must show false (the key was deleted):</b><br>";
$key1=$cache->get("group","key1");
var_dump($key1);
echo "<br><b>7- create two groups and one key per each one</b><br>";
$cache->set("group1","key1","hello world");
$cache->set("group2","key2","hola mundo");
echo "<br><b>8- get: it must shows hello world and hola mundo</b><br>";
var_dump($cache->get("group1","key1"));
var_dump($cache->get("group2","key2"));
echo "<br><b>9- invalidating both groups</b><br>";
$cache->invalidateGroup(["group1","group2"]);
echo "<br><b>10- get: it must show false (the key was deleted):</b><br>";
var_dump($cache->get("group1","key1"));
echo "<br><b>11- get: it must show false (the second key was deleted):</b><br>";
var_dump($cache->get("group2","key2"));