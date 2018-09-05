<?php

namespace eftec;
use DateTime;
use ReflectionObject;

/**
 * Class CacheOneMemcached
 * @package eftec
 * @version 1.3.1 2018-06-10
 * @link https://github.com/EFTEC/CacheOne
 * @author   Jorge Patricio Castro Castillo <jcastro arroba eftec dot cl>
 * @license  MIT
 */
class CacheOneMemcached implements ICacheOne
{
    /** @var bool if the cache is up */
    var $enabled=false;

    /** @var \Memcache */
    var $memcache;

    /**
     * Open the cache
     * @param string $server (if any)
     * @param string $schema (if any)
     * @param string $port (if any)
     * @param string $user (if any)
     * @param string $password (if any)
     * @return bool
     */
    public function __construct($server = "", $schema = "", $port = "", $user = "", $password = "")
    {

        if (class_exists("Memcache")) {
            $this->memcache = new \Memcache();
            $r=@$this->memcache->connect($server, $port);
            if ($r===false) {
                $this->memcache=null;
                $this->enabled=false;
            }
            $this->enabled=true;
        } else {
            $this->memcache=null;
            $this->enabled=false;
        }
        return $this->enabled;
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
        if ($this->memcache == null) return false;

        $cat=json_decode(@$this->memcache ->get($group.'_cat'),true);
        if (!$cat) {
            $cat=array(); // created a new catalog
        }
        $cat[$key]=1;
        @$this->memcache->set($group.'_cat',json_encode($cat)); // we store the catalog.
        $result=$this->memcache->set($key,json_encode($value),0,$duration); // 1 day, 0 for unlimited.
        return $result;
    }

    /**
     * @param string $group if any, it's a group or category of elements.<br>
     *        It's used when we need to invalidate (delete) a group of keys.
     * @param string $key key to return.
     * @param bool $jsonDecode if false (default value) then the result is json-decoded, otherwise is returned raw.
     * @return bool|mixed returns false if the value is not found, otherwise it returns the value.
     */
    function get($group,$key, $jsonDecode = false)
    {
        if ($this->memcache == null) return false;
        $v=$this->memcache->get($key);
        $result = $jsonDecode ? $v : json_decode($v);
        return $result;
    }

    /**
     * @param string $group Delete an entire group
     * @return bool
     */
    function invalidateGroup($group): bool
    {
        // TODO: test
        $r=false;
        if ($this->memcache !==null) {
            $cdumplist =json_decode( @$this->memcache ->get($group.'_cat'));
            if (is_array($cdumplist)) {
                $keys = array_keys($cdumplist);
                foreach ($keys as $key) {
                    @$this->memcache ->delete($key);
                }
            }
            $r=@$this->memcache ->set($group.'_cat',array());
        }
        return $r;
    }

    /**
     * @param string $key Delete a single key
     * @return bool
     */
    function invalidate($key): bool
    {
        return @$this->memcache->delete($key);
    }

    /**
     * Fix the cast of an object.
     * Usage utilCache::fixCast($objectRight,$objectBadCast);
     * @param object|array $destination Object may be empty with the right cast.
     * @param object|array $source Object with the wrong cast.
     * @return void
     */
    public static function fixCast(&$destination, $source)
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