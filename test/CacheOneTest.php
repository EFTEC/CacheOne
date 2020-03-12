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
    }
    
    private function runMe($type)  {
        $cache=new CacheOne($type);
        $cache->select(0);
        $cache->invalidateAll();
        
        // wrapper test
        $this->assertEquals(true,$cache->setCache("key1","family","hello world"));
        $this->assertEquals("hello world",$cache->getCache("key1","family"));
        $this->assertEquals(true,$cache->invalidateCache("key1","family"));
        $this->assertEquals(false,$cache->getCache("key1","family"));
        
        $this->assertEquals(true,$cache->set("group","key1","hello world"));
        $this->assertEquals(true,$cache->set("group","key1","hello world"));

        

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
        $this->runMe($type);
        
    }
    public function test_apcu()
    {
        $type='apcu';
        $this->runMe($type);

    }
    public function test_auto()
    {
        $this->runMe('auto');
    }
    public function test_memcache()
    {
        $type='memcache';
        $this->runMe($type);

    }

}
