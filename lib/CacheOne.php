<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection ClassConstantCanBeUsedInspection */
/** @noinspection ReturnTypeCanBeDeclaredInspection */
/** @noinspection PhpMissingParamTypeInspection */
/** @noinspection PhpUnusedParameterInspection */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace eftec;

use DateTime;
use eftec\provider\CacheOneProviderAPCU;
use eftec\provider\CacheOneProviderDocumentOne;
use eftec\provider\CacheOneProviderMemcache;
use eftec\provider\CacheOneProviderRedis;
use eftec\provider\ICacheOneProvider;
use Exception;
use ReflectionObject;

/**
 * Class CacheOne
 *
 * @package  eftec
 * @version  2.5 2020-09-20
 * @link     https://github.com/EFTEC/CacheOne
 * @author   Jorge Patricio Castro Castillo <jcastro arroba eftec dot cl>
 * @license  MIT
 */
class CacheOne
{
    /** @var ICacheOneProvider */
    public $service;
    /** @var string=['redis','memcache','apcu','documentone'][$i] */
    public $type;

    /** @var bool if the cache is up */
    public $enabled;

    /** @var string */
    public $schema = '';
    /** @var string The postfix of the catalog of the group */
    public $cat_postfix = '_cat';
    /**
     * @var int The duration of the catalog in seconds<br>
     * The default value is 7 days. The limit is 30 days (memcache limit). 0 = never expires.
     */
    public $catDuration = 6048;
    private $defaultTTL = 1440;
    private $separatorUID = ':';
    /** @var string=['php','json-array','json-object','none'][$i] How to serialize/unserialize the values */
    private $serializer = 'php';

    /**
     * Open the cache
     *
     * @param string     $type        =['auto','redis','memcache','apcu','documentone'][$i]
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
        $server = '127.0.0.1',
        $schema = "",
        $port = 0
        ,
        $user = "",
        $password = "",
        $timeout = 8,
        $retry = null,
        $readTimeout = null
    ) {
        $this->type = $type;
        if ($type === 'auto') {
            if (class_exists("Redis")) {
                $this->type = 'redis';
            } elseif (class_exists("Memcache")) {
                $this->type = 'memcache';
            } elseif (extension_loaded('apcu')) {
                $this->type = 'apcu';
            } elseif (class_exists('\eftec\DocumentStoreOne\DocumentStoreOne')) {
                $this->type = 'documentone';
            }
        }
        switch ($this->type) {
            case 'redis':
                if (class_exists("Redis")) {
                    $this->service = new CacheOneProviderRedis($this, $server, $schema, $port,
                        $timeout, $retry, $readTimeout);
                    return;
                }
                $this->service = null;
                $this->enabled = false;
                trigger_error('CacheOne: Redis extension not installed');

                return;
            case 'memcache':
                $this->separatorUID = '_';
                if (class_exists("Memcache")) {
                    $this->service = new CacheOneProviderMemcache($this, $server, $port, $schema);
                } else {
                    $this->enabled = false;
                    trigger_error('CacheOne: memcache extension not installed');
                }
                return;
            case 'apcu':
                if (extension_loaded('apcu')) {
                    $this->service = new CacheOneProviderAPCU($this, $schema);
                } else {
                    $this->enabled = false;
                    trigger_error('CacheOne: apcu extension not installed');
                }
                return;
            case 'documentone':
                if (class_exists('\eftec\DocumentStoreOne\DocumentStoreOne')) {
                    $this->service = new CacheOneProviderDocumentOne($this, $server, $schema);
                } else {
                    $this->enabled = false;
                    trigger_error('CacheOne: DocumentStoreOne library not installed');
                }
                break;
            default:
                trigger_error("CacheOne: type {$this->type} not defined");
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
    public static function fixCast(&$destination, $source)
    {
        if (is_array($source)) {
            if (count($destination) === 0) {
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
                    if (get_class(@$destination->{$name}) === "DateTime") {
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
    public function select($dbindex)
    {
        $this->service->select($dbindex);
    }

    /**
     * It invalidates all the keys inside a group or groups.<br>
     * <pre>
     * $this->invalidateGroup('customer');
     * </pre>
     * <b>Note:</b> if a key is member of more than one group, then it is invalidated for all groups.<br>
     * <b>Note:</b> The operation of update of the catalog of the group is not atomic but it is tolerable (for a cache)<br>
     *
     * @param string|array $group Delete an entire group (or groups)
     *
     * @return bool Returns true if it deleted more than one key or false if error or no key deleted.
     */
    public function invalidateGroup($group): bool
    {
        if (!is_array($group)) {
            $group = [$group];
        }
        return $this->service->invalidateGroup($group);
    }

    public function genCatId($group)
    {
        $r = ($this->schema) ? $this->schema . $this->separatorUID : '';
        return $r . $group . $this->cat_postfix;
    }

    /**
     * @param             $input
     * @param null|string $forcedSerializer =[null,'php','json-array','json-object','none'][$i]
     *
     * @return mixed
     */
    public function unserialize($input, $forcedSerializer = null)
    {
        $forcedSerializer = $forcedSerializer ?? $this->serializer;
        switch ($forcedSerializer) {
            case 'php':
                /** @noinspection UnserializeExploitsInspection */
                return unserialize($input);
            case 'json-array':
                return json_decode($input, true);
            case 'json-object':
                return json_decode($input, false);
            case 'none':
                return $input;
            default:
                trigger_error("serialize {$this->serializer} not defined");
                return null;
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
    public function invalidateAll()
    {
        return $this->service->invalidateAll();
    }

    /**
     * Wrappper of get()
     *
     * @param string       $uid
     * @param string|array $family
     *
     * @return mixed
     * @see \eftec\CacheOne::get
     */
    public function getCache($uid, $family = '')
    {
        return $this->get($family, $uid);
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
    public function get($group, $key, $defaultValue = false)
    {
        if (!$this->enabled) {
            return $defaultValue;
        }
        return $this->service->get($group, $key, $defaultValue);
    }

    /**
     * @return string
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * @param string $serializer =['php','json-array','json-object','none'][$i] By default it uses php.
     *
     * @return CacheOne
     */
    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;
        return $this;
    }

    /**
     * Wrapper of function set()
     *
     * @param string       $uid
     * @param string|array $family
     * @param null         $data
     * @param null         $ttl
     *
     * @return bool
     * @see \eftec\CacheOne::set
     */
    public function setCache($uid, $family = '', $data = null, $ttl = null)
    {
        return $this->set($family, $uid, $data, $ttl);
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
     * <b>Note:</b> The duration is ignored when we use "documentone". It uses instead the default ttl <br>
     *
     * @param string|array $groups   if any, it's a group or category of elements.<br>
     *                               A single key could be member of more than a group<br>
     *                               It's used when we need to invalidate (delete) a group of keys.<br>
     *                               If the group or the first element of the group is empty, then it stores the key<br>
     * @param string       $key      The key used to store the information.
     * @param mixed        $value    This value shouldn't be serialized because the class serializes it.
     * @param int|null     $duration In seconds. 0 means unlimited. Default (null) is 1440, 24 minutes.<br>
     *                               It is ignored when type="documentone".
     *
     *
     * @return bool
     */
    public function set($groups, $key, $value, $duration = null): bool
    {
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
        return $this->service->set($groupID, $uid, $groups, $key, $value, $duration ?? $this->defaultTTL);
    }

    /**
     * Generates the unique key based in the schema (if any) : group (if any)  key.
     *
     * @param $group
     * @param $key
     *
     * @return string
     */
    public function genId($group, $key)
    {
        $r = ($this->schema) ? $this->schema . $this->separatorUID : '';
        // $r .= ($group) ? $group . $this->separatorUID : '';
        return $r . $key;
    }

    /**
     * It gets the default time to live. Zero means unlimited.
     * 
     * @return int (in seconds)
     */
    public function getDefaultTTL()
    {
        return $this->defaultTTL;
    }

    /**
     * @param int $ttl number in seconds of the time to live. Zero means unlimited.
     */
    public function setDefaultTTL($ttl)
    {
        $this->defaultTTL = $ttl;
    }

    /**
     * @param $input
     *
     * @return false|string
     */
    public function serialize($input)
    {
        switch ($this->serializer) {
            case 'php':
                return serialize($input);
            case 'json-array':
            case 'json-object':
                return json_encode($input);
            case 'none':
                return $input;
            default:
                trigger_error("serialize {$this->serializer} not defined");
                return '';
        }
    }

    /**
     * Wrapper of function invalidate()
     *
     * @param string $uid
     * @param string $family
     *
     * @return bool
     * @see \eftec\CacheOne::invalidate
     *
     */
    public function invalidateCache($uid = '', $family = '')
    {
        return $this->invalidate($family, $uid);

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
    public function invalidate($group = '', $key = ''): bool
    {
        return $this->service->invalidate($group, $key);
    }

}