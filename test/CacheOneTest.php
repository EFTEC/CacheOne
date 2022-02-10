<?php
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection SqlNoDataSourceInspection */
/** @noinspection SqlDialectInspection */

namespace eftec\tests;



use eftec\CacheOne;

use eftec\PdoOne;
use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class CacheOneTest extends TestCase
{
	/** @var CacheOne */
    protected $cacheOne;
    public function test_noconnect(): void
    {
        $cache=new CacheOne('memcache','127.0.0.1','',11212);

        self::assertEquals(false,$cache->enabled);
        $cache=new CacheOne('redis','127.0.0.1','',11212);
        self::assertEquals(false,$cache->enabled);
        self::assertEquals('php',$cache->getSerializer());
    }
    public function test_notfound(): void
    {
        $cache = (new CacheOne('redis', '127.0.0.1', 'test'))->setSerializer('php');

        self::assertEquals(false, $cache->get('','notfound'));
        self::assertEquals(null, $cache->get('','notfound',null));
        self::assertEquals(123, $cache->get('','notfound',123));
        self::assertEquals([], $cache->get('','notfound',[]));
        $cache->invalidate('','notfound2');
        self::assertEquals(true, $cache->push('','notfound2',123));
        self::assertEquals([123], $cache->get('','notfound2'));

    }
    public function test_push(): void
    {
        $cache=(new CacheOne('redis','127.0.0.1','test'))->setSerializer('php');

        self::assertEquals(true,$cache->set('','item',[1,2,3],123));
        // popold
        self::assertEquals(true,$cache->push('','item',4,null,5));
        self::assertEquals(true,$cache->push('','item',5,null,5));
        self::assertEquals(true,$cache->push('','item',6,null,5));
        self::assertEquals([2,3,4,5,6],$cache->get('','item'));
        self::assertEquals(true,$cache->push('','item',7,null,5,'nonew'));
        self::assertEquals([2,3,4,5,6],$cache->get('','item'));

        self::assertEquals(6,$cache->pop('','item'));
        self::assertEquals([2,3,4,5],$cache->get('','item'));
        self::assertEquals(2,$cache->shift('','item'));
        self::assertEquals([3,4,5],$cache->get('','item'));
        self::assertEquals(true,$cache->unshift('','item',2));
        self::assertEquals([2,3,4,5],$cache->get('','item'));
        self::assertEquals(true,$cache->unshift('','item',1));
        self::assertEquals([1,2,3,4,5],$cache->get('','item'));
        self::assertEquals(true,$cache->unshift('','item',10,null,5));
        self::assertEquals([10,1,2,3,4],$cache->get('','item'));
        self::assertEquals(true,$cache->unshift('','item',20,null,5,'shiftold'));
        self::assertEquals([20,1,2,3,4],$cache->get('','item'));
        self::assertEquals(4,$cache->pop('','item'));
        self::assertEquals(3,$cache->pop('','item'));
        self::assertEquals(2,$cache->pop('','item'));
        self::assertEquals(1,$cache->pop('','item'));
        self::assertEquals(20,$cache->pop('','item'));
        self::assertEquals(null,$cache->pop('','item'));
    }
    public function test_push_error1(): void
    {
        $cache=(new CacheOne('redis','127.0.0.1','test'))->setSerializer('php');
        self::assertEquals(true,$cache->set('','item','hello',123));
        $this->expectException(RuntimeException::class);
        $cache->push('','item',4,null,5);
    }
    public function test_push_error2(): void
    {
        $cache=(new CacheOne('redis','127.0.0.1','test'))->setSerializer('php');
        self::assertEquals(true,$cache->set('','item','hello',123));
        $this->expectException(RuntimeException::class);
        $cache->pop('','item');
    }
    public function test_push_error3(): void
    {
        $cache=(new CacheOne('redis','127.0.0.1','test'))->setSerializer('php');
        self::assertEquals(true,$cache->set('','item','hello',123));
        $this->expectException(RuntimeException::class);
        $cache->shift('','item');
        $cache->unshift('','item',4,null,5);
    }
    public function test_push_error4(): void
    {
        $cache=(new CacheOne('redis','127.0.0.1','test'))->setSerializer('php');
        self::assertEquals(true,$cache->set('','item','hello',123));
        $this->expectException(RuntimeException::class);
        $cache->unshift('','item',4,null,5);
    }
    public function test_set_error4(): void
    {
        $cache=(new CacheOne('redis','127.0.0.1','test'))->setSerializer('php');
        $this->expectException(RuntimeException::class);
        $cache->set([],'item','hello',123);
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function runMe($type, $schema, $serializer='php', $server='127.0.0.1'): void
    {
        $cache=new CacheOne($type,$server,$schema);
        $cache->setSerializer($serializer);
        if($type==='pdoone') {
            $cache->select('KVTABLA');
        } else {
            $cache->select(0);
        }
        $cache->invalidateAll();

        // wrapper test
        self::assertEquals(true,$cache->setCache("key1","family","hello world"));
        self::assertEquals("hello world",$cache->getCache("key1","family"));
        self::assertEquals(true,$cache->invalidateCache("key1","family"));
        self::assertEquals(false,$cache->getCache("key1","family"));

        self::assertEquals(true,$cache->set("group","key1","hello world"));
        self::assertEquals(true,$cache->set("group","key1","hello world"));

        $complex=[['Item1'=>'value1','Item2'=>'value2'],['Item1'=>'value1','Item2'=>'value2']];
        $complex=[$complex,$complex];
        self::assertEquals(true,$cache->set("group","complex",$complex));
        self::assertEquals($complex,$cache->get("group","complex"));


        self::assertEquals(true,$cache->set("group","key2","hola mundo"));
        self::assertEquals('hello world',$cache->get("group","key1"));
        $cache->invalidate("group","key1");
        self::assertEquals(false,$cache->get("group","key1"));
        $cache->invalidateGroup("group");
        self::assertEquals(false,$cache->get("group","key2"));
        $cache->set("group1","key1","hello world");
        $cache->set("group2","key2","hola mundo");
        self::assertEquals('hello world',$cache->get("group1","key1"));
        self::assertEquals('hola mundo',$cache->get("group2","key2"));
        $cache->invalidateGroup(["group1","group2"]);
        self::assertEquals(false,$cache->get("group1","key1"));
        self::assertEquals(false,$cache->get("group2","key2"));
        $cache->set(["group1","group2"],"key1","hello world"); // a key with 2 group
        self::assertEquals('hello world',$cache->get("group1","key1"));
        $cache->invalidateGroup(["group2"]);
        self::assertEquals(false,$cache->get("group1","key1"));
        // we left some traceover
        self::assertEquals(true,$cache->set("group","key1","hello world"));
        self::assertEquals(true,$cache->set("group","key2","hello world"));
        self::assertEquals(true,$cache->set("group","key3","hello world"));
        self::assertEquals(true,$cache->set("group","key4","hello world"));

    }
    public function test_cast(): void
    {
        $origin=new stdClass();
        $origin->field="20";
        $template=new stdClass();
        $template->field=20;
        $compare=new stdClass();
        $compare->field=20;
        CacheOne::fixCast($origin,$template);
        self::assertEquals($compare,$origin);
        $originArray=[$origin,$origin];
        CacheOne::fixCast($originArray,[$template,$template]);
        self::assertEquals($compare,$origin);
    }

    public function test_redis(): void
    {
        $type='redis';
        $this->runMe($type,'unittest');
        $this->runDuration($type,'unittest');

    }
    public function test_redis_json(): void
    {
        $type='redis';
        $this->runMe($type,'unittest','json-array');
        $this->runDuration($type,'unittest');

    }
    public function test_apcu(): void
    {
        // if not, then test fails because it considers the timestamp of execution of php
        ini_set("apc.use_request_time", 0);
        $type='apcu';
        $this->runMe($type,'unittest');
        $this->runDuration($type,'unittest');
    }
    public function test_pdoone(): void
    {
        $pdo=new PdoOne('mysql','127.0.0.1','root','abc.123','travisdb');
        $pdo->logLevel=3;
        $pdo->open();
        $pdo->setKvDefaultTable('KVTABLA');
        try {
            $this->assertEquals(true, $pdo->dropTableKV());

        } catch(Exception $ex) {
            var_dump('warning:'.$ex->getMessage());
            var_dump('table not deleted');
        }
        try {
            $pdo->createTableKV();
        } catch(Exception $ex) {
            var_dump('warning:'.$ex->getMessage());
            var_dump('table not created');
        }

        $type='pdoone';
        $this->runMe($type,'unittest');
        $this->runDuration($type,'unittest');
    }
    public function test_document(): void
    {
        // if not, then test fails because it considers the timestamp of execution of php
        ini_set("apc.use_request_time", 0);
        $type='documentone';
        $this->runMe($type,'unittest','php',__DIR__.'/mem');
        $this->runDuration($type,'unittest',__DIR__.'/mem');
    }

    public function test_auto(): void
    {
        $this->runMe('auto','unittest');
        $this->runDuration('auto','unittest');
    }
    public function test_memcache(): void
    {
        $type='memcache';
        $this->runMe($type,'unittest');
        $this->runDuration($type,'unittest');
    }
    public function runDuration($type,$schema,$server='127.0.0.1'): void
    {
        $cache=new CacheOne($type,$server,$schema);
        $cache->setDefaultTTL(1)->setDefaultValue(false);
        if($type==='pdoone') {
            $cache->select('KVTABLA');
        } else {
            $cache->select(0);
        }
        $cache->invalidateAll();
        $cache->set('group','key','hello world',1);
        self::assertEquals('hello world',$cache->get('group','key'));
        sleep(2); // expires
        self::assertEquals(false,$cache->get('group','key'));


        $cache->invalidateAll();
        $cache->set('group','key1','hello world',4); // each key expires in 4 seconds
        $cache->set('group','key2','hello world',4);
        $cache->set('group','key3','hello world',4);
        $cache->catDuration=1; // the catalog expires in 1 second
        sleep(2); // not enough time to expire
        $cache->invalidateGroup('group'); // the catalog must be alive to expire all the keys
        self::assertEquals(false,$cache->get('group','key1'));
        self::assertEquals(false,$cache->get('group','key2'));
        self::assertEquals(false,$cache->get('group','key3'));



    }

}
