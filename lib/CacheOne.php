<?php /** @noinspection PhpComposerExtensionStubsInspection */

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
 * @version  2.0 2020-03-12
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

    
    private $separatorUID=':';

    /** @var string */
    var $schema = '';

    /**
     * Open the cache
     *
     * @param string     $type        =['auto','redis','memcache','apcu'][$i]
     * @param string     $server      ip of the server.
     * @param string     $schema      Default schema (optional).
     * @param int|string $port        By default is 6379
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
                    $port=(!$port)?6379:$port;
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
                $this->separatorUID='_';
                if (class_exists("Memcache")) {
                    $this->memcache = new Memcache();
                    $port=(!$port)?11211:$port;
                    $r = @$this->memcache->connect($server, $port);
                    if ($r === false) {
                        $this->memcache = null;
                        $this->enabled = false;
                    }
                    $this->enabled = true;
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
                    if ($r === false) {
                        $this->enabled = false;
                    }
                    $this->enabled = true;
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
     * @param string $group    if any, it's a group or category of elements.<br>
     *                         It's used when we need to invalidate (delete) a group of keys.
     * @param string $key      The key used to store the information.
     * @param mixed  $value    This value shouldn't be serialized because the class serializes it.
     * @param int    $duration in seconds. -1 is unlimited. Default is 1440, 24 minutes.
     *
     * @return bool
     */
    function set($group, $key, $value, $duration = 1440): bool {
        
        $uid = $this->getId($group, $key);
        switch ($this->type) {
            case 'redis':
                if ($this->redis == null) {
                    return false;
                }
                return $this->redis->set($uid, serialize($value), $duration);
            case 'memcache':
                
                if ($this->memcache == null) {
                    return false;
                }
                //$cat = unserialize(@$this->memcache->get($group . '_cat')); // we read the catalog (if any)
                $cat = @$this->memcache->get($group . '_cat');
                if (!$cat) {
                    $cat = array(); // created a new catalog
                }
                
                $cat[$uid] = 1;
                @$this->memcache->set($group . '_cat', $cat); // we store the catalog.
                // 1 day, 0 for unlimited.
                return $this->memcache->set($uid, $value, 0, $duration);
            case 'apcu':
                if (!$this->enabled) {
                    return false;
                }

                if (!$group) {
                    return apcu_store($key, serialize($value), $duration);
                }
                $cat = unserialize(@apcu_fetch($group . '_cat'));
                if (!$cat) {
                    $cat = array(); // created a new catalog
                }
                $cat[$uid] = 1;
                apcu_store($group . '_cat', serialize($cat), $duration);// we store the catalog

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
    private function getId($group, $key) {
        $r=($this->schema) ? $this->schema.$this->separatorUID : '';
        $r.=($group) ? $group.$this->separatorUID : '';
        return $r.$key;
    }

    /**
     * @param string $group  if any, it's a group or category of elements.<br>
     *                       It's used when we need to invalidate (delete) a group of keys.
     * @param string $key    key to return.
     *
     * @return mixed returns false if the value is not found, otherwise it returns the value.
     */
    function get($group, $key) {
        if (!$this->enabled) {
            return false;
        }
        $uid = $this->getId($group, $key);
        switch ($this->type) {
            case 'redis':
                $v = $this->redis->get($uid);
                return unserialize($v);
            case 'memcache':
                if ($this->memcache == null) {
                    return false;
                }
                $v = $this->memcache->get($uid);
                return $v;
                break;
            case 'apcu':
                $v = apcu_fetch($uid);
                return unserialize($v);
                break;
            default:
                trigger_error("CacheOne: type {$this->type} not defined");
                return false;
        }

    }

    /**
     * @param string|array $group Delete an entire group (or groups)
     *
     * @return bool
     */
    function invalidateGroup($group): bool {
        if (!is_array($group)) {
            $group = [$group];
        }
        switch ($this->type) {
            case 'redis':
                $numDelete = 0;
                foreach ($group as $nameGroup) {
                    $it = null;
                    $keys = $this->redis->scan($it, $this->getId($nameGroup, "*"), 99999);
                    if ($keys !== false && count($keys) !== 0) {
                        $numDelete += $this->redis->del($keys);
                    }
                }
                return ($numDelete > 0);
                break;
            case 'memcache':
                $count = 0;
                if ($this->memcache !== null) {
                    foreach ($group as $nameGroup) {
                        $cdumplist = @$this->memcache->get($nameGroup . '_cat'); // it reads the catalog
                        if (is_array($cdumplist)) {
                            $keys = array_keys($cdumplist);
                            foreach ($keys as $key) {
                                @$this->memcache->delete($key);
                            }
                        }
                        @$this->memcache->set($nameGroup . '_cat', array()); // update the catalog
                        $count++;
                    }
                }
                return $count > 0;
                break;
            case 'apcu':
                $count = 0;
                if ($this->enabled) {

                    foreach ($group as $nameGroup) {
                        $cdumplist = unserialize(@apcu_fetch($nameGroup . '_cat')); // it reads the catalog

                        if (is_array($cdumplist)) {
                            $keys = array_keys($cdumplist);

                            foreach ($keys as $key) {
                                @apcu_delete($key);
                            }
                        }
                        apcu_delete($nameGroup . '_cat');
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
     * Invalidates a single key.
     *
     * @param string $group (optional), the group of the key.
     * @param string $key   Delete a single key
     *
     * @return bool
     */
    function invalidate($group = '', $key = ''): bool {
        $uid = $this->getId($group, $key);
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