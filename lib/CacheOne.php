<?php /** @noinspection PhpUnusedParameterInspection */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace eftec;

use DateTime;
use Exception;
use Memcache;
use Redis;
use ReflectionObject;

/**
 * Class CacheOneRedis
 *
 * @package  eftec
 * @version  2.1 2020-03-12
 * @link     https://github.com/EFTEC/CacheOne
 * @author   Jorge Patricio Castro Castillo <jcastro arroba eftec dot cl>
 * @license  MIT
 */
class CacheOne
{
    /** @var string=['redis','memcache','apcu'][$i] */
    var $type;

    /** @var bool if the cache is up */
    var $enabled;

    /** @var Redis */
    var $redis;
    /** @var Memcache */
    var $memcache;
    /** @var string */
    var $schema = '';
    /** @var string The postfix of the catalog of the group */
    var $cat_postfix = '_cat';
    private $separatorUID = ':';

    /**
     * Open the cache
     *
     * @param string     $type        =['auto','redis','memcache','apcu'][$i]
     * @param string     $server      ip of the server.
     * @param string     $schema      Default schema (optional).
     * @param int|string $port        [optional] By default is 6379 (redis) and 11211 (memcached)
     * @param string     $user        (use future)
     * @param string     $password    (use future)
     * @param int        $timeout     Timeout (for connection) in seconds. Zero means unlimited
     * @param int|null   $retry       Retry timeout (in milliseconds)
     * @param int|null   $readTimeout Read timeout (in milliseconds). Zero means unlimited
     */
    public function __construct(
        $type = 'auto',
        $server = '127.0.0.1', $schema = "", $port = 0
        , $user = "", $password = "", $timeout = 8, $retry = null, $readTimeout = null
    ) {
        $this->type = $type;
        if ($type == 'auto') {
            if (class_exists("Redis")) {
                $this->type = 'redis';
            } else {
                if (class_exists("Memcache")) {
                    $this->type = 'memcache';
                } else {
                    if (extension_loaded('apcu')) {
                        $this->type = 'apcu';
                    }
                }
            }
        }
        switch ($this->type) {
            case 'redis':
                if (class_exists("Redis")) {
                    $this->redis = new Redis();
                    $port = (!$port) ? 6379 : $port;
                    try {
                        $r = @$this->redis->pconnect($server, $port, $timeout, null, $retry, $readTimeout);
                    } catch (Exception $e) {
                        $this->redis = null;
                        $this->enabled = false;
                        return;
                    }
                    if ($r === false) {
                        $this->redis = null;
                        $this->enabled = false;
                        return;
                    } else {
                        $this->schema = $schema;
                        $this->enabled = true;
                        return;
                    }
                } else {
                    $this->redis = null;
                    $this->enabled = false;
                    trigger_error('CacheOne: Redis extension not installed');
                }

                return;
                break;
            case 'memcache':
                $this->separatorUID = '_';
                if (class_exists("Memcache")) {
                    $this->memcache = new Memcache();
                    $port = (!$port) ? 11211 : $port;
                    $r = @$this->memcache->connect($server, $port);
                    if ($r === false) {
                        $this->memcache = null;
                        $this->enabled = false;
                    } else {
                        $this->enabled = true;    
                    }
                    
                } else {
                    $this->memcache = null;
                    $this->enabled = false;
                    trigger_error('CacheOne: memcache extension not installed');
                }
                return;
                break;
            case 'apcu':
                if (extension_loaded('apcu')) {
                    $r = @apcu_sma_info();
                    $this->enabled= ($r !== false);
                } else {
                    $this->enabled = false;
                    trigger_error('CacheOne: apcu extension not installed');
                }
                return;
                break;
            default:
                trigger_error("CacheOne: type {$this->type} not defined");
                return;
        }
    }

    /**
     * Fix the cast of an object.
     * Usage utilCache::fixCast($objectRight,$objectBadCast);
     *
     * @param object|array $destination Object may be empty with the right cast.
     * @param object|array $source      Object with the wrong cast.
     *
     * @return void
     * @throws Exception
     */
    public static function fixCast(&$destination, $source) {
        if (is_array($source)) {
            if(count($destination)===0) {
                return;
            }
            $getClass = get_class($destination[0]);
            $array = array();
            foreach ($source as $sourceItem) {
                $obj = new $getClass();
                self::fixCast($obj, $sourceItem);
                $array[] = $obj;
            }
            $destination = $array;
        } else {
            $sourceReflection = new ReflectionObject($source);
            $sourceProperties = $sourceReflection->getProperties();
            foreach ($sourceProperties as $sourceProperty) {
                $name = $sourceProperty->getName();
                if (is_object(@$destination->{$name})) {
                    if (get_class(@$destination->{$name}) == "DateTime") {
                        // source->name is a stdclass, not a DateTime, so we could read the value with the field date
                        /** @noinspection PhpUnhandledExceptionInspection */
                        $destination->{$name} = new DateTime($source->$name->date);
                    } else {
                        self::fixCast($destination->{$name}, $source->$name);
                    }
                } else {
                    $destination->{$name} = $source->$name;
                }
            }
        }
    }

    /**
     * It changes the default database (0) for another one.  It's only for Redis
     *
     * @param int $dbindex
     *
     * @see https://redis.io/commands/select
     */
    function select($dbindex) {
        if ($this->redis) {
            $this->redis->select($dbindex);
        }
    }

    /**
     * It sets a value into a group (optional) for a specific duration<br>
     * <pre>
     * $this->set('','listCustomer',$listCustomer); // store in listCustomer, default ttl = 24 minutes.
     * $this->set('customer','listCustomer',$listCustomer); // store in customer:listCustomer default ttl = 24 minutes.
     * $this->set('customer','listCustomer',$listCustomer); // store in customer:listCustomer default ttl = 24 minutes.
     * $this->set('customer','listCustomer',$listCustomer,86400); // store in customer:listCustomer ttl = 1 day.
     * </pre>
     * <b>Note:</b> The operation of update of the catalog of the group is not atomic but it is tolerable (for a cache)<br>
     *
     * @param string|array $groups   if any, it's a group or category of elements.<br>
     *                               A single key could be member of more than a group<br>
     *                               It's used when we need to invalidate (delete) a group of keys.<br>
     *                               If the group or the first element of the group is empty, then it stores the key<br>
     * @param string       $key      The key used to store the information.
     * @param mixed        $value    This value shouldn't be serialized because the class serializes it.
     * @param int          $duration in seconds. 0 means unlimited. Default is 1440, 24 minutes.
     *
     * @return bool
     */
    function set($groups, $key, $value, $duration = 1440): bool {
        if (!$this->enabled) {
            return false;
        }
        $groups = (is_array($groups)) ? $groups : [$groups]; // transform a string groups into an array
        if (count($groups) === 0) {
            trigger_error('CacheOne: set must have a non empty group of a empty array');
            return false;
        }
        $groupID = $groups[0]; // first group
        $uid = $this->genId($groupID, $key);

        switch ($this->type) {
            case 'redis':
                if ($groupID !== '') {
                    foreach ($groups as $group) {
                        $catUid = $group . $this->cat_postfix;
                        $cat =unserialize( @$this->redis->get($catUid));
                        if ($cat === false) {
                            $cat = array(); // created a new catalog
                        }
                        if(time() % 20 ===0) {
                            // garbage collector of the catalog. We run it around every 20th reads.
                            $keys = array_keys($cat);
                            foreach($keys as $key) {
                                if(!$this->redis->exists($key)) {
                                    unset($cat[$key]);
                                }
                            }
                        }
                        $cat[$uid] = 1; // we added/updated the catalog
                        @$this->redis->set($catUid,serialize($cat)); // we store the catalog back.
                    }
                }
                if ($duration === 0) {
                    return $this->redis->set($uid, serialize($value));
                }
                return $this->redis->set($uid, serialize($value), $duration);
            case 'memcache':
                if ($groupID !== '') {
                    foreach ($groups as $group) {
                        $catUid = $group . $this->cat_postfix;
                        $cat = @$this->memcache->get($catUid);
                        if ($cat === false) {
                            $cat = array(); // created a new catalog
                        }
                        if(time() % 20 ===0) {
                            // garbage collector of the catalog. We run it around every 20th reads.
                            $keys = array_keys($cat);
                            foreach($keys as $key) {
                                if($this->memcache->get($key)===false) {
                                    unset($cat[$key]);
                                }
                            }
                        }
                        $cat[$uid] = 1;
                        @$this->memcache->set($catUid, $cat); // we store the catalog.
                    }
                }
                // 1 day, 0 for unlimited.
                return $this->memcache->set($uid, $value, 0, $duration);
            case 'apcu':
                if ($groupID !== '') {
                    foreach ($groups as $group) {
                        $catUid = $group . $this->cat_postfix;
                        $cat = unserialize(@apcu_fetch($catUid));
                        if ($cat === false) {
                            $cat = array(); // created a new catalog
                        }
                        if(time() % 20 ===0) {
                            // garbage collector of the catalog. We run it around every 20th reads.
                            $keys = array_keys($cat);
                            foreach($keys as $key) {
                                if(!apcu_exists($key)) {
                                    unset($cat[$key]);
                                }
                            }
                        }
                        $cat[$uid] = 1;
                        apcu_store($catUid, serialize($cat), $duration);// we store the catalog
                    }
                }
                return apcu_store($uid, serialize($value), $duration);
            default:
                trigger_error("CacheOne: type {$this->type} not defined");
                return false;
        }
    }

    /**
     * Generates the unique key based in the schema (if any) : group (if any)  key.
     *
     * @param $group
     * @param $key
     *
     * @return string
     */
    private function genId($group, $key) {
        $r = ($this->schema) ? $this->schema . $this->separatorUID : '';
        $r .= ($group) ? $group . $this->separatorUID : '';
        return $r . $key;
    }

    /**
     * It get an item from the cache<br>
     * <pre>
     * $result=$this->get('','listCustomers'); // it gets the key1 if any or false if not found
     * $result=$this->get('customer','listCustomers'); // it gets customers:key1 if any or false if not found
     * $result=$this->get('customer','listCustomers',[]); // it gets customers:key1 if any or an empty array
     * </pre>
     *
     * @param string $group        if any, it's a group or category of elements.<br>
     *                             It's used when we need to invalidate (delete) a group of keys.
     * @param string $key          key to return.
     *
     * @param bool   $defaultValue [default is false] If not found or error, then it returns this value.<br>
     *
     * @return mixed returns false if the value is not found, otherwise it returns the value.
     */
    function get($group, $key, $defaultValue = false) {
        if (!$this->enabled) {
            return $defaultValue;
        }
        $uid = $this->genId($group, $key);
        switch ($this->type) {
            case 'redis':
                $r =unserialize( $this->redis->get($uid));
                return $r === false ? $defaultValue : $r;
            case 'memcache':
                if ($this->memcache == null) {
                    return false;
                }
                $v = $this->memcache->get($uid);
                return $v === false ? $defaultValue : $v;
                break;
            case 'apcu':
                $r = unserialize(apcu_fetch($uid));
                return $r === false ? $defaultValue : $r;
                break;
            default:
                trigger_error("CacheOne: type {$this->type} not defined");
                return $defaultValue;
        }

    }

    /**
     * It invalidates all the keys inside a group or groups.<br>
     * <pre>
     * $this->invalidateGroup('customer);
     * </pre>
     * <b>Note:</b> if a key is member of more than one group, then it is invalidated for all groups.<br>
     * <b>Note:</b> The operation of update of the catalog of the group is not atomic but it is tolerable (for a cache)<br>
     *
     * @param string|array $group Delete an entire group (or groups)
     *
     * @return bool Returns true if it deleted more than one key or false if error or no key deleted.
     */
    function invalidateGroup($group): bool {
        if (!is_array($group)) {
            $group = [$group];
        }
        switch ($this->type) {
            case 'redis':
                $numDelete = 0;
                if ($this->redis !== null) {
                    foreach ($group as $nameGroup) {
                        $cdumplist =unserialize( @$this->redis->get($nameGroup . $this->cat_postfix)); // it reads the catalog
                        if (is_array($cdumplist)) {
                            $keys = array_keys($cdumplist);
                            foreach ($keys as $key) {
                                $numDelete += @$this->redis->del($key);
                            }
                        }
                        @$this->redis->set($nameGroup . $this->cat_postfix,serialize(array())); // update the catalog (empty)
                    }
                }
                return $numDelete > 0;
            case 'memcache':
                $count = 0;
                if ($this->memcache !== null) {
                    foreach ($group as $nameGroup) {
                        $cdumplist = @$this->memcache->get($nameGroup . $this->cat_postfix); // it reads the catalog
                        if (is_array($cdumplist)) {
                            $keys = array_keys($cdumplist);
                            foreach ($keys as $key) {
                                @$this->memcache->delete($key);
                            }
                        }
                        @$this->memcache->set($nameGroup . $this->cat_postfix, array()); // update the catalog
                        $count++;
                    }
                }
                return $count > 0;
                break;
            case 'apcu':
                $count = 0;
                if ($this->enabled) {

                    foreach ($group as $nameGroup) {
                        $cdumplist = unserialize(@apcu_fetch($nameGroup . $this->cat_postfix)); // it reads the catalog

                        if (is_array($cdumplist)) {
                            $keys = array_keys($cdumplist);

                            foreach ($keys as $key) {
                                @apcu_delete($key);
                            }
                        }
                        apcu_delete($nameGroup . $this->cat_postfix);
                        $count++;
                    }
                }
                return $count > 0;
                break;
            default:
                trigger_error("CacheOne: type {$this->type} not defined");
                return false;
        }
    }

    /**
     * It invalidates all cache from the current database (redis) or all cache (for memcache and apcu)<br>
     * <pre>
     * $this->invalidateAll();
     * </pre>
     *
     * @return bool true if the operation is correct, otherwise false.
     */
    public function invalidateAll() {
        switch ($this->type) {
            case 'redis':
                if ($this->redis == null) {
                    return false;
                }
                return $this->redis->flushDB();
            case 'memcache':
                return @$this->memcache->flush();
                break;
            case 'apcu':
                return apcu_clear_cache();
            default:
                trigger_error("CacheOne: type {$this->type} not defined");
                return false;
        }
    }

    /**
     * Invalidates a single key.<br>
     * <pre>
     * $this->invalidate('','listCustomer'); // invalidates ListCustomer
     * $this->invalidate('customer','listCustomer'); // invalidates customer:ListCustomer
     * </pre>
     * <b>Note:</b> If a key is member of more than a group, then it is deleted from all groups.<br>
     * <b>Note:</b> The catalog of the group is not updated.
     *
     * @param string $group (optional), the group of the key.
     * @param string $key   Delete a single key
     *
     * @return bool
     */
    function invalidate($group = '', $key = ''): bool {
        $uid = $this->genId($group, $key);
        switch ($this->type) {
            case 'redis':
                if ($this->redis == null) {
                    return false;
                }
                $num = $this->redis->del($uid);
                return ($num > 0);
            case 'memcache':
                return @$this->memcache->delete($uid);
                break;
            case 'apcu':
                return apcu_delete($uid);
            default:
                trigger_error("CacheOne: type {$this->type} not defined");
                return false;
        }
    }

}