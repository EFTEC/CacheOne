<?php

namespace eftec;

use DateTime;
use Redis;
use ReflectionObject;

/**
 * Class CacheOneRedis
 * @package eftec
 * @version 1.3.1 2018-06-09
 * @link https://github.com/EFTEC/CacheOne
 * @author   Jorge Patricio Castro Castillo <jcastro arroba eftec dot cl>
 * @license  MIT
 */
class CacheOneRedis implements ICacheOne {

    /** @var bool if the cache is up */
    var $enabled;

    /** @var Redis */
    var $redis;

    /**
     * Open the cache
     * @param string $server ip of the server.
     * @param string $schema Default schema (optional).
     * @param int|string $port By default is 6379
     * @param string $user (use future)
     * @param string $password (use future)
     */
    public function __construct($server = '127.0.0.1', $schema = "", $port = 6379, $user = "", $password = "")
    {
        if (class_exists("Redis")) {
            $this->redis= new Redis();
            $r=$this->redis->pconnect($server,$port, 4); // 4 sec timeout to connects.
            if ($r===false) {
                $this->redis=null;
                $this->enabled=false;
                return;
            } else {
                if ($schema) {
                    $this->redis->setOption(Redis::OPT_PREFIX, $schema.":");
                }
                $this->enabled=true;
                return;
            }
        }
        $this->redis=null;
        $this->enabled=false;
    }



    /**
     * @param string $group if any, it's a group or category of elements.<br>
     *        It's used when we need to invalidate (delete) a group of keys.
     * @param string $key
     * @param mixed $value This value shouldn't be serialized because the class serializes it.
     * @param int $duration in seconds. -1 is unlimited. Default is 1440, 24 minutes.
     * @return bool
     */
    function set($group, $key, $value, $duration = 1440): bool
    {
        if ($this->redis == null) return false;
        if ($group!="") {
            $cat = json_decode(@$this->redis->get("_group:".$group), true);
            if (!$cat) {
                $cat = array(); // created a new catalog
            }
            $cat[$key] = 1;
            @$this->redis->set("_group:".$group, json_encode($cat)); // we store the catalog of the group
        }
        $result = $this->redis->set($key, json_encode($value), $duration);
        return $result;
    }

    /**
     * @param string $key key to return.
     * @param bool $jsonDecode if false (default value) then the result is json-decoded, otherwise is returned raw.
     * @return mixed returns null if the value is not found, otherwise it returns the value.
     */
    function get($key, $jsonDecode = false)
    {
        if ($this->redis==null) return null;

        $v=$this->redis->get($key);
        if ($v===false) return null;
        $result = $jsonDecode ? $v : json_decode($v);
        return $result;
    }

    /**
     * @param string $group Delete an entire group
     * @return bool
     */
    function invalidateGroup($group): bool
    {
        if ($this->redis == null) return false;
        $cat = json_decode(@$this->redis->get("_group:".$group), true);
        if (!is_array($cat)) return false;
        foreach($cat as $key=>$val) {
            $this->redis->del($key);
        }
        $this->redis->del("_group:".$group);
        return true;
    }

    /**
     * @param string $key Delete a single key
     * @return bool true if deletes one (or more than one), false if it doesn't delete a key.
     */
    function invalidate($key): bool
    {
        if ($this->redis==null) return false;
        $num=$this->redis->del($key);
        return ($num>0);
    }

    /**
     * Fix the cast of an object.
     * Usage utilCache::fixCast($objectRight,$objectBadCast);
     * @param object|array $destination Object may be empty with the right cast.
     * @param object|array $source  Object with the wrong cast.
     * @return void
     */
    public static function fixCast(&$destination,$source)
    {
        if (is_array($source)) {
            $getClass=get_class($destination[0]);
            $array=array();
            foreach($source as $sourceItem) {
                $obj = new $getClass();
                self::fixCast($obj,$sourceItem);
                $array[]=$obj;
            }
            $destination=$array;
        } else {
            $sourceReflection = new ReflectionObject($source);
            $sourceProperties = $sourceReflection->getProperties();
            foreach ($sourceProperties as $sourceProperty) {
                $name = $sourceProperty->getName();
                if (is_object(@$destination->{$name})) {
                    if (get_class(@$destination->{$name})=="DateTime") {
                        // source->name is a stdclass, not a DateTime, so we could read the value with the field date
                        $destination->{$name}=new DateTime($source->$name->date);
                    } else {
                        self::fixCast($destination->{$name}, $source->$name);
                    }
                } else {
                    $destination->{$name} = $source->$name;
                }
            }
        }
    }


}