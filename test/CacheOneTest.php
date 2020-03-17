<?php 
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection SqlNoDataSourceInspection */
/** @noinspection SqlDialectInspection */

namespace eftec\tests;



use eftec\CacheOne;

use PHPUnit\Framework\TestCase;
use stdClass;

class PdoOneTest extends TestCase
{
	/** @var CacheOne */
    protected $cacheOne;
    public function test_noconnect() {
        $cache=new CacheOne('memcache','127.0.0.1','',11212);
        $this->assertEquals(false,$cache->enabled);
        $cache=new CacheOne('redis','127.0.0.1','',11212);
        $this->assertEquals(false,$cache->enabled);
        $this->assertEquals('php',$cache->getSerializer());
    }
    
    private function runMe($type,$schema,$serializer='php')  {
        $cache=new CacheOne($type,'127.0.0.1',$schema);
        $cache->setSerializer($serializer);
        $cache->select(0);
        $cache->invalidateAll();
        
        // wrapper test
        $this->assertEquals(true,$cache->setCache("key1","family","hello world"));
        $this->assertEquals("hello world",$cache->getCache("key1","family"));
        $this->assertEquals(true,$cache->invalidateCache("key1","family"));
        $this->assertEquals(false,$cache->getCache("key1","family"));
        
        $this->assertEquals(true,$cache->set("group","key1","hello world"));
        $this->assertEquals(true,$cache->set("group","key1","hello world"));

        $complex=[['Item1'=>'value1','Item2'=>'value2'],['Item1'=>'value1','Item2'=>'value2']];
        $complex=[$complex,$complex];
        $this->assertEquals(true,$cache->set("group","complex",$complex));
        $this->assertEquals($complex,$cache->get("group","complex"));
        

        $this->assertEquals(true,$cache->set("group","key2","hola mundo"));
        $this->assertEquals('hello world',$cache->get("group","key1"));
        $cache->invalidate("group","key1");
        $this->assertEquals(false,$cache->get("group","key1"));
        $cache->invalidateGroup("group");
        $this->assertEquals(false,$cache->get("group","key2"));
        $cache->set("group1","key1","hello world");
        $cache->set("group2","key2","hola mundo");
        $this->assertEquals('hello world',$cache->get("group1","key1"));
        $this->assertEquals('hola mundo',$cache->get("group2","key2"));
        $cache->invalidateGroup(["group1","group2"]);
        $this->assertEquals(false,$cache->get("group1","key1"));
        $this->assertEquals(false,$cache->get("group2","key2"));
        $cache->set(["group1","group2"],"key1","hello world"); // a key with 2 group
        $this->assertEquals('hello world',$cache->get("group1","key1"));
        $cache->invalidateGroup(["group2"]);
        $this->assertEquals(false,$cache->get("group1","key1"));
        // we left some traceover
        $this->assertEquals(true,$cache->set("group","key1","hello world"));
        $this->assertEquals(true,$cache->set("group","key2","hello world"));
        $this->assertEquals(true,$cache->set("group","key3","hello world"));
        $this->assertEquals(true,$cache->set("group","key4","hello world"));

    }
    public function test_cast() {
        $origin=new stdClass();
        $origin->field="20";
        $template=new stdClass();
        $template->field=20;
        $compare=new stdClass();
        $compare->field=20;
        CacheOne::fixCast($origin,$template);
        $this->assertEquals($compare,$origin);
        $originArray=[$origin,$origin];
        CacheOne::fixCast($originArray,[$template,$template]);
        $this->assertEquals($compare,$origin);
    }

    public function test_redis()
    {
        $type='redis';
        $this->runMe($type,'unittest');
        $this->runDuration($type,'unittest');
        
    }
    public function test_redis_json()
    {
        $type='redis';
        $this->runMe($type,'unittest','json-array');
        $this->runDuration($type,'unittest');

    }
    public function test_apcu()
    {
        // if not, then test fails because it considers the timestamp of execution of php
        ini_set("apc.use_request_time", 0);
        $type='apcu';
        $this->runMe($type,'unittest');
        $this->runDuration($type,'unittest');

    }
    public function test_auto()
    {
        $this->runMe('auto','unittest');
        $this->runDuration('auto','unittest');
    }
    public function test_memcache()
    {
        $type='memcache';
        $this->runMe($type,'unittest');
        $this->runDuration($type,'unittest');
    }
    public function runDuration($type,$schema) {
        $cache=new CacheOne($type,'127.0.0.1',$schema);
        $cache->select(0);
        $cache->invalidateAll();
        $cache->set('group','key','hello world',1);
        $this->assertEquals('hello world',$cache->get('group','key'));
        sleep(2); // expires
        $this->assertEquals(false,$cache->get('group','key'));
        
        
        $cache->invalidateAll();
        $cache->set('group','key1','hello world',4); // each key expires in 4 seconds
        $cache->set('group','key2','hello world',4);
        $cache->set('group','key3','hello world',4);
        $cache->catDuration=1; // the catalog expires in 1 second
        sleep(2); // not enough time to expire
        $cache->invalidateGroup('group'); // the catalog must be alive to expire all the keys
        $this->assertEquals(false,$cache->get('group','key1'));
        $this->assertEquals(false,$cache->get('group','key2'));
        $this->assertEquals(false,$cache->get('group','key3'));

        
        
    }

}
