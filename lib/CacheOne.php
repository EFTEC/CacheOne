<?php /** @noinspection UnknownInspectionInspection */
/** @noinspection ClassConstantCanBeUsedInspection */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpComposerExtensionStubsInspection */

namespace eftec;

use DateTime;
use eftec\provider\CacheOneProviderAPCU;
use eftec\provider\CacheOneProviderDocumentOne;
use eftec\provider\CacheOneProviderMemcache;
use eftec\provider\CacheOneProviderRedis;
use eftec\provider\CacheOneProviderPdoOne;
use eftec\provider\ICacheOneProvider;
use Exception;
use JsonException;
use ReflectionObject;
use RuntimeException;

/**
 * Class CacheOne
 *
 * @package  eftec
 * @version  2.18
 * @link     https://github.com/EFTEC/CacheOne
 * @author   Jorge Patricio Castro Castillo <jcastro arroba eftec dot cl>
 * @license  Dual License: Commercial and MIT
 */
class CacheOne
{
    public const VERSION = "2.18";
    /** @var bool if true then it records every operation in $this::debugLog */
    public bool $debug = false;
    /** @var array If debug is true, then it records operations here. */
    public array $debugLog = [];
    /** @var ICacheOneProvider|null */
    public ?ICacheOneProvider $service = null;
    /** @var string=['redis','memcache','apcu','pdoone','documentone'][$i] */
    public string $type = 'apcu';
    /** @var bool if the cache is up */
    public bool $enabled = false;
    /** @var string */
    public string $schema = '';
    /** @var string The postfix of the catalog of the group */
    public string $cat_postfix = '_cat';
    /**
     * @var int The duration of the catalog in seconds<br>
     * The default value is 7 days. The limit is 30 days (memcache limit). 0 = never expires.
     */
    public int $catDuration = 6048;
    private int $defaultTTL = 1440;
    private string $separatorUID = ':';
    /** @var string=['php','json-array','json-object','none'][$i] How to serialize/unserialize the values */
    private string $serializer = 'php';
    /** @var mixed */
    private $defaultValue = false;
    /** @var CacheOne|null used for singleton, method instance() */
    protected static ?CacheOne $instance = null;

    /**
     * Open the cache
     *
     * @param string       $type             =['auto','redis','memcache','apcu','pdoone','documentone','document'][$i]
     * @param string|array $server           IP of the server.  If you use PdoOne, then you must use an array with the
     *                                       whole configuration or any other type of value if you want to inject
     *                                       an existing instance of PdoOne.
     * @param string       $schema           Default schema (optional).<br>
     *                                       In Redis: if schema is a number, then it sets the database, otherwise it is
     *                                       used as a prefix.
     * @param int|string   $port             [optional] By default is 6379 (redis) and 11211 (memcached)
     * @param string       $user             (use future)
     * @param string       $password         (use future)
     * @param int          $timeout          Timeout (for connection) in seconds. Zero means unlimited
     * @param int|null     $retry            Retry timeout (in milliseconds)
     * @param int|null     $readTimeout      Read timeout (in milliseconds). Zero means unlimited
     */
    public function __construct(
        string $type = 'auto',
               $server = '127.0.0.1',
        string $schema = "",
               $port = 0
        ,
        string $user = "",
        string $password = "",
        int    $timeout = 8,
        int    $retry = null,
        int    $readTimeout = null
    )
    {
        $type = ($type === 'document') ? 'documentone' : $type;
        $this->type = $type;
        if ($type === 'auto') {
            if (class_exists("Redis")) {
                $this->type = 'redis';
            } elseif (class_exists("Memcache")) {
                $this->type = 'memcache';
            } elseif (extension_loaded('apcu')) {
                $this->type = 'apcu';
            } elseif (class_exists('\eftec\PdoOne')) {
                $this->type = 'pdoone';
            } elseif (class_exists('\eftec\DocumentStoreOne\DocumentStoreOne')) {
                $this->type = 'documentone';
            }
        }
        switch ($this->type) {
            case 'redis':
                if (class_exists("Redis")) {
                    $this->service = new CacheOneProviderRedis($this, $server, $schema, $port,
                        $timeout, $retry, $readTimeout);
                } else {
                    $this->service = null;
                    $this->enabled = false;
                    throw new RuntimeException('CacheOne: Redis extension not installed');
                }
                break;
            case 'memcache':
                $this->separatorUID = '_';
                if (class_exists("Memcache")) {
                    $this->service = new CacheOneProviderMemcache($this, $server, $port, $schema);
                } else {
                    $this->enabled = false;
                    throw new RuntimeException('CacheOne: memcache extension not installed');
                }
                break;
            case 'pdoone':
                if (PdoOne::instance(false) !== null) {
                    $this->service = new CacheOneProviderPdoOne($this, $schema);
                } else {
                    try {
                        if (!is_array($server)) {
                            throw new RuntimeException('CacheOne: $server must contain the configuration of PdoOne');
                        }
                        $inst = new PdoOne($server['databaseType'], $server['server'], $server['user'],
                            $server['pwd'], $server['database']);
                        if (!isset($server['databasetable'])) {
                            throw new RuntimeException('CacheOne: PdoOne configuration without databasetable');
                        }
                        $this->service = new CacheOneProviderPdoOne($this, $server['database']);
                        $inst->setKvDefaultTable($server['databasetable']);
                        $inst->open();
                        $this->enabled = true;
                    } catch (Exception $ex) {
                        $this->enabled = false;
                        throw new RuntimeException('CacheOne: pdoone extension not installed or no instance is found');
                    }
                }
                break;
            case 'apcu':
                if (extension_loaded('apcu')) {
                    $this->service = new CacheOneProviderAPCU($this, $schema);
                } else {
                    $this->enabled = false;
                    throw new RuntimeException('CacheOne: apcu extension not installed');
                }
                break;
            case 'documentone':
                if (class_exists('\eftec\DocumentStoreOne\DocumentStoreOne')) {
                    $this->service = new CacheOneProviderDocumentOne($this, $server, $schema);
                } else {
                    $this->enabled = false;
                    throw new RuntimeException('CacheOne: DocumentStoreOne library not installed');
                }
                break;
            default:
                throw new RuntimeException("CacheOne: type $this->type not defined");
        }
        if (self::$instance === null) {
            self::$instance = $this;
        }
    }

    /**
     * It creates a new instance of CacheOne using an array
     * @param array $array =['type','server','schema','port','user','password','timeout','retry','readTimeout'][$i]<br>
     *                     it could be an associative array or an indexed array.
     * @return void
     */
    public static function factory(array $array): CacheOne
    {
        if (isset($array[0])) {
            return new CacheOne(...$array);
        }
        return new CacheOne(
            $array['type'] ?? 'auto',
            $array['server'] ?? '127.0.0.1',
            $array['schema'] ?? "",
            $array['port'] ?? 0,
            $array['user'] ?? "",
            $array['password'] ?? "",
            $array['timeout'] ?? 8,
            $array['retry'] ?? null,
            $array['readTimeout'] ?? null
        );
    }

    /**
     * It returns the first instance created CacheOne or throw an error if the instance is not set.
     * @param bool $throwIfNull if true and the instance is not set, then it throws an exception<br>
     *                          if false and the instance is not set, then it returns null
     * @return CacheOne|null
     */
    public static function instance(bool $throwIfNull = true): ?CacheOne
    {
        if (self::$instance === null && $throwIfNull) {
            throw new RuntimeException('instance not created for CacheOne');
        }
        return self::$instance;
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
    public static function fixCast(&$destination, $source): void
    {
        if (is_array($source)) {
            if (count($destination) === 0) {
                return;
            }
            $getClass = get_class($destination[0]);
            $array = [];
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
     * It changes the default database (0) for another one.  It's only for Redis and PdoOne
     *
     * @param string|int $dbindex
     *
     * @see https://redis.io/commands/select
     */
    public function select($dbindex): void
    {
        try {
            $this->service->select($dbindex);
        } catch (Exception $e) {
            throw new RuntimeException("Error in CacheOne selecting database $dbindex, message:" . $e->getMessage());
        }
    }

    /**
     * It returns an instance of the provider used, so you can access directly to it.<br>
     * <b>pdoone</b> Returns an object of the type PdoOne<br>
     * <b>documentone</b> Returns an object of the type DocumentOne<br>
     * <b>apcu</b> Returns null<br>
     * <b>memcache</b> Returns an object of the type Memcache<br>
     * <b>redis</b> Returns an object of the type Redis<br>
     * @return object|null
     */
    public function getInstanceProvider(): ?object
    {
        return $this->service->getInstance();
    }

    /**
     * It invalidates all the keys inside a group or groups.<br>
     * <pre>
     * $this->invalidateGroup('customer');
     * </pre>
     * <b>Note:</b> if a key is member of more than one group, then it is invalidated for all groups.<br>
     * <b>Note:</b> The operation of update of the catalog of the group is not atomic, but it is tolerable (for a
     * cache)<br>
     *
     * @param string|array $group Delete an entire group (or groups)
     *
     * @return bool Returns true if it deleted more than one key or false if error or no key deleted.
     * @throws Exception
     */
    public function invalidateGroup($group): bool
    {
        if ($this->debug) {
            $this->debugLog[] = ['type' => 'invalidateGroup', 'step' => 'init', 'family' => json_encode($group, JSON_THROW_ON_ERROR)];
        }
        if (!is_array($group)) {
            $group = [$group];
        }
        $r = $this->service->invalidateGroup($group);
        if ($this->debug) {
            $this->debugLog[] = ['type' => 'invalidateGroup', 'step' => 'end', 'value' => $r];
        }
        return $r;
    }

    public function genCatId($group): string
    {
        $r = ($this->schema) ? $this->schema . $this->separatorUID : '';
        return $r . $group . $this->cat_postfix;
    }

    /**
     * @param             $input
     * @param string|null $forcedSerializer =[null,'php','json-array','json-object','none'][$i]
     *
     * @return mixed
     * @throws JsonException
     */
    public function unserialize($input, string $forcedSerializer = null)
    {
        if ($input === false) {
            return false;
        }
        $forcedSerializer = $forcedSerializer ?? $this->serializer;
        switch ($forcedSerializer) {
            case 'php':
                /** @noinspection UnserializeExploitsInspection */
                return $input === null ? null : unserialize($input);
            case 'json-array':
                return $input === null ? null : json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            case 'json-object':
                return $input === null ? null : json_decode($input, false, 512, JSON_THROW_ON_ERROR);
            case 'none':
                return $input;
            default:
                throw new RuntimeException("serialize $this->serializer not defined");
        }
    }

    /**
     * It invalidates all cache<br>
     * In <b>redis<b>, it deletes the current schema. If not schema, then it deletes all redis<br>
     * In <b>memcached<b> and <b>apcu</b>, it deletes all cache<br>
     * In <b>documentone</b> it deletes the content of the database-folder<br>
     * <pre>
     * $this->invalidateAll();
     * </pre>
     *
     * @return bool true if the operation is correct, otherwise false.
     * @throws Exception
     */
    public function invalidateAll(): bool
    {
        return $this->service->invalidateAll();
    }

    /**
     * Wrappper of get()
     *
     * @param string       $key    Key of the cache to obtain.
     * @param string|array $family [not used] this value could be any value
     *
     * @return mixed
     * @throws Exception
     * @throws Exception
     * @see CacheOne::getValue
     */
    public function getCache(string $key, $family = '')
    {
        if ($this->debug) {
            $this->debugLog[] = ['type' => 'getCache', 'step' => 'init', 'key' => $key, 'family' => json_encode($family, JSON_THROW_ON_ERROR)];
        }
        return $this->getValue($key);
    }

    /**
     * It gets an item from the cache<br>
     * <pre>
     * $result=$this->get('','listCustomers'); // it gets the key1 if any or false if not found
     * $result=$this->get('customer','listCustomers'); // it gets customers:key1 if any or false if not found
     * $result=$this->get('customer','listCustomers',[]); // it gets customers:key1 if any or an empty array
     * </pre>
     *
     * @param string $key          key to return.
     *
     * @param mixed  $defaultValue [default is false] If not found or error, then it returns this value.<br>
     *
     * @return mixed returns false if the value is not found, otherwise it returns the value.
     * @throws Exception
     */
    public function getValue(string $key, $defaultValue = PHP_INT_MAX)
    {
        $defaultValue = $defaultValue === PHP_INT_MAX ? $this->defaultValue : $defaultValue;
        if (!$this->enabled) {
            return $defaultValue;
        }
        return $this->service->get($key, $defaultValue);
    }

    /**
     * Wrappper of getValue(). It is keep for compatibility purpose.
     * @param mixed  $group        [not used] You can use any value here
     * @param string $key          The string to read
     * @param mixed  $defaultValue The return value if the value is not found (defalt is false)
     * @return array|false|mixed|string|null
     * @throws Exception
     * @throws Exception
     * @see CacheOne::getValue
     */
    public function get($group, string $key, $defaultValue = PHP_INT_MAX)
    {
        // $this->resetStack();
        return $this->getValue($key, $defaultValue); // === PHP_INT_MAX ? $this->defaultValue : $defaultValue);
    }

    /**
     * It gets a key and renew the value if the value was found<br>
     * It is useful to renew the duration of the cache while keeping the same value.
     *
     * @param string   $key
     * @param int|null $duration
     * @param mixed    $defaultValue
     * @return mixed
     * @throws Exception
     */
    public function getRenew(string $key, ?int $duration = null, $defaultValue = PHP_INT_MAX)
    {
        $r = $this->getValue($key, $defaultValue);
        if ($r !== false) {
            $this->set('', $key, $r, $duration); // it is already part of a group
        }
        return $r;
    }

    /**
     * @return string
     */
    public function getSerializer(): string
    {
        return $this->serializer;
    }

    /**
     * @param string $serializer =['php','json-array','json-object','none'][$i] By default it uses php.
     *
     * @return CacheOne
     */
    public function setSerializer(string $serializer): CacheOne
    {
        $this->serializer = $serializer;
        return $this;
    }

    /**
     * Wrapper of function set()
     *
     * @param string       $key      Key of the cache to set a value
     * @param string|array $family   Family/group of the cache
     * @param mixed        $data     data to set
     * @param ?int         $duration time to live (in seconds), null means unlimited.
     *
     * @return bool
     * @throws Exception
     * @see CacheOne::set
     */
    public function setCache(string $key, $family = '', $data = null, ?int $duration = null): bool
    {
        if ($this->debug) {
            $this->debugLog[] = ['type' => 'setCache', 'step' => 'init', 'key' => $key, 'family' => json_encode($family, JSON_THROW_ON_ERROR)];
        }
        return $this->set($family, $key, $data, $duration);
    }

    /**
     * It sets a value into a group (optional) for a specific duration<br>
     * <pre>
     * $this->set('','listCustomer',$listCustomer); // store in listCustomer, default ttl = 24 minutes.
     * $this->set('customer','listCustomer',$listCustomer); // store in customer:listCustomer default ttl = 24 minutes.
     * $this->set('customer','listCustomer',$listCustomer); // store in customer:listCustomer default ttl = 24 minutes.
     * $this->set('customer','listCustomer',$listCustomer,86400); // store in customer:listCustomer ttl = 1 day.
     * </pre>
     * <b>Note:</b> The operation of update of the catalog of the group is not atomic, but it is tolerable (for a
     * cache)<br>
     * <b>Note:</b> The duration is ignored when we use "documentone". It uses instead the default ttl <br>
     *
     * @param string|array $groups   if any, it's a group or category of elements.<br>
     *                               A single key could be member of more than a group<br>
     *                               It's used when we need to invalidate (delete) a group of keys.<br>
     *                               If the group or the first element of the group is empty, then it stores the
     *                               key<br>
     * @param string       $key      The key used to store the information.
     * @param mixed        $value    This value shouldn't be serialized because the class serializes it.
     * @param int|null     $duration In seconds. 0 means unlimited. Default (null) is 1440, 24 minutes.<br>
     *                               It is ignored when type="documentone".
     *
     *
     * @return bool
     * @throws Exception
     * @throws Exception
     */
    public function set($groups, string $key, $value, ?int $duration = null): bool
    {
        if (!$this->enabled) {
            return false;
        }
        $groups = (is_array($groups)) ? $groups : [$groups]; // transform a string groups into an array
        if (count($groups) === 0) {
            throw new RuntimeException('[CacheOne]: set must have a non empty group of a empty array');
        }
        $uid = $this->genId($key);
        return $this->service->set($uid, $groups, $key, $value, $duration ?? $this->defaultTTL);
    }

    /**
     * Generates the unique key based in the schema (if any) +  key.
     *
     * @param string $key
     *
     * @return string
     */
    public function genId(string $key): string
    {
        $r = ($this->schema) ? $this->schema . $this->separatorUID : '';
        return $r . $key;
    }

    /**
     * It pushes a new value into the cache at the end of the array/list.<br>
     * If the previous value does not exist then, it creates a new array<br>
     * If the previous value is not an array then, it throws an exception<br>
     * <b>Note:</b> This operation is not atomic<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->push('','cart',$item,2000); // it adds a new element into the cart unlimitely.
     * $this->push('','cart',$item,2000,20,'popold'); // it limits the cart to 20 elements, pop old item if req.
     * $this->push('','cart',$item,2000,20,'nonew'); // if the cart has 20 elements, then it doesn't add $item
     * </pre><br>
     *
     * @param string|array $groups        if any, it's a group or category of elements.<br>
     *                                    A single key could be member of more than a group<br>
     *                                    It's used when we need to invalidate (delete) a group of keys.<br>
     *                                    If the group or the first element of the group is empty, then it stores the
     *                                    key<br>
     * @param string       $key           The key used to store the information.
     * @param mixed        $value         This value shouldn't be serialized because the class serializes it.
     * @param int|null     $duration      In seconds. 0 means unlimited. Default (null) is 1440, 24 minutes.<br>
     *                                    It is ignored when type="documentone".
     * @param int          $limit         If zero, then it does not limIf the values stored into the array.<br>
     *                                    If the value is not zero and the number of elements of the array surprases
     *                                    this limit, then it trims the first value, and it adds a new value at the end
     *                                    of the list.
     * @param string       $limitStrategy =['nonew','popold','shiftold'][$i] // default is shiftold<br>
     *                                    nonew = it does not add a new element if the limit is reached<br>
     *                                    shiftold = if the limit is reached then it pops the first element<br>
     *                                    popold = if the limit is reached then it removes the last element<br>
     *
     * @return bool
     * @throws Exception
     */
    public function push($groups, string $key, $value, int $duration = null, int $limit = 0, string $limitStrategy = 'shiftold'): bool
    {
        return $this->executePushUnShift('push', $groups, $key, $value, $duration, $limit, $limitStrategy);
    }

    /**
     * @param string       $type          =['push','unshift'][$i]
     * @param string|array $groups
     * @param string       $key
     * @param mixed        $value
     * @param int|null     $duration
     * @param int          $limit
     * @param string       $limitStrategy =['shiftold','nonew','popold'][$i]
     * @return bool
     * @throws Exception
     */
    protected function executePushUnShift(string $type, $groups, string $key, $value, ?int $duration = null, int $limit = 0
        , string                                 $limitStrategy = 'shiftold'): bool
    {
        if (!$this->enabled) {
            return false;
        }
        $groups = (is_array($groups)) ? $groups : [$groups]; // transform a string groups into an array
        $originalArray = $this->service->get($key, []);
        $originalArray = $originalArray ?? [];
        if (!is_array($originalArray)) {
            throw new RuntimeException('[CacheOne] unable to push cache, the value stored is not an array 
            or setSerializer is not set');
        }
        if ($limit > 0 && count($originalArray) >= $limit) {
            if ($limitStrategy === 'shiftold') {
                array_shift($originalArray); // we remove the most old element of the array
            } elseif ($limitStrategy === 'nonew') {
                // true but no value added.
                return true;
            } else {
                // popold
                array_pop($originalArray); // we remove the most recent element of the array
            }
        }
        if ($type === 'push') {
            $originalArray[] = $value;
        } else {
            array_unshift($originalArray, $value);
        }
        if (count($groups) === 0) {
            throw new RuntimeException('CacheOne: set must have a non empty group of a empty array');
        }
        $uid = $this->genId($key);
        return $this->service->set($uid, $groups, $key, $originalArray, $duration ?? $this->defaultTTL);
    }

    /**
     * It pushes a new value into the cache at the beginer of the array/list.<br>
     * If the previous value does not exist then, it creates a new array<br>
     * If the previous value is not an array then, it throws an exception<br>
     * <b>Note:</b> This operation is not atomic<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->unshift('','cart',$item,2000); // it adds a new element into the cart unlimitely.
     * $this->unshift('','cart',$item,2000,20,'popold'); // it limits the cart to 20 elements, pop old item if req.
     * $this->unshift('','cart',$item,2000,20,'nonew'); // if the cart has 20 elements, then it doesn't add $item
     * </pre><br>
     *
     * @param string|array $groups        if any, it's a group or category of elements.<br>
     *                                    A single key could be member of more than a group<br>
     *                                    It's used when we need to invalidate (delete) a group of keys.<br>
     *                                    If the group or the first element of the group is empty, then it stores the
     *                                    key<br>
     * @param string       $key           The key used to store the information.
     * @param mixed        $value         This value shouldn't be serialized because the class serializes it.
     * @param int|null     $duration      In seconds. 0 means unlimited. Default (null) is 1440, 24 minutes.<br>
     *                                    It is ignored when type="documentone".
     * @param int          $limit         If zero, then it does not limIf the values stored into the array.<br>
     *                                    If the value is not zero and the number of elements of the array surprases
     *                                    this limit, then it trims the first value, and it adds a new value at the end
     *                                    of the list.
     * @param string       $limitStrategy =['nonew','popold','shiftold'][$i] // default is shiftold<br>
     *                                    nonew = it does not add a new element if the limit is reached<br>
     *                                    shiftold = if the limit is reached then it pops the first element<br>
     *                                    popold = if the limit is reached then it removes the last element<br>
     *
     * @return bool
     * @throws Exception
     */
    public function unshift($groups, string $key, $value, int $duration = null, int $limit = 0, string $limitStrategy = 'popold'): bool
    {
        return $this->executePushUnShift('unshift', $groups, $key, $value, $duration, $limit, $limitStrategy);
    }

    /**
     * It pops a value at the end of the array.<br>
     * If the array does not exist then it returns $defaultValue<br>
     * If the array is empty then it returns null<br>
     * The original array is modified (it is removed the element that it was pop'ed)<br>
     * <b>Example:</b><br>
     * <pre>
     * $element=$this->pop('','cart');
     * </pre>
     *
     * @param string|array $group        if any, it's a group or category of elements.<br>
     *                                   A single key could be member of more than a group<br>
     *                                   It's used when we need to invalidate (delete) a group of keys.<br>
     *                                   If the group or the first element of the group is empty, then it stores the
     *                                   key<br>
     * @param string       $key          The key used to store the information.
     * @param mixed        $defaultValue [default is false] If not found or error, then it returns this value.<br>
     * @param int|null     $duration     In seconds. 0 means unlimited. Default (null) is 1440, 24 minutes.<br>
     *                                   It is ignored when type="documentone".
     * @return false|mixed|string|null
     * @throws Exception
     * @throws Exception
     */
    public function pop($group, string $key, $defaultValue = PHP_INT_MAX, int $duration = null)
    {
        return $this->executePopShift('pop', $group, $key, $defaultValue, $duration);
    }

    /**
     * @param string       $type
     * @param string|array $group
     * @param string       $key
     * @param mixed        $defaultValue
     * @param int|null     $duration
     * @return bool|int|mixed|string|null
     * @throws Exception
     * @throws Exception
     */
    protected function executePopShift(string $type, $group, string $key, $defaultValue = PHP_INT_MAX, int $duration = null)
    {
        $defaultValue = $defaultValue === PHP_INT_MAX ? $this->defaultValue : $defaultValue;
        if (!$this->enabled) {
            return $defaultValue;
        }
        $originalArray = $this->service->get($key, $defaultValue);
        if ($originalArray === false) {
            // key not found, nothing to pop
            return $defaultValue;
        }
        if (!is_array($originalArray)) {
            throw new RuntimeException('[CacheOne] unable to pop cache, the value stored is not an array 
            or setSerializer is not set');
        }
        $final = $type === 'pop' ? array_pop($originalArray) : array_shift($originalArray);
        $uid = $this->genId($key);
        $group = (!is_array($group)) ? [$group] : $group;
        $rs = $this->service->set($uid, $group, $key, $originalArray, $duration ?? $this->defaultTTL);
        return $rs === false ? $defaultValue : $final;
    }

    /**
     * It shifts (extract) a value at the beginner of the array.<br>
     * If the array does not exist then it returns $defaultValue<br>
     * If the array is empty then it returns null<br>
     * The original array is modified (it is removed the element that it was pop'ed)<br>
     * <b>Example:</b><br>
     * <pre>
     * $element=$this->shift('','cart');
     * </pre>
     *
     * @param string|array $group        if any, it's a group or category of elements.<br>
     *                                   A single key could be member of more than a group<br>
     *                                   It's used when we need to invalidate (delete) a group of keys.<br>
     *                                   If the group or the first element of the group is empty, then it stores the
     *                                   key<br>
     * @param string       $key          The key used to store the information.
     * @param mixed        $defaultValue [default is false] If not found or error, then it returns this value.<br>
     * @param int|null     $duration     In seconds. 0 means unlimited. Default (null) is 1440, 24 minutes.<br>
     *                                   It is ignored when type="documentone".
     * @return false|mixed|string|null
     * @throws Exception
     * @throws Exception
     */
    public function shift($group, string $key, $defaultValue = PHP_INT_MAX, int $duration = null)
    {
        return $this->executePopShift('shift', $group, $key, $defaultValue, $duration);
    }

    /**
     * It gets the default time to live. Zero means unlimited.
     *
     * @return int (in seconds)
     */
    public function getDefaultTTL(): int
    {
        return $this->defaultTTL;
    }

    /**
     * @param int $duration number in seconds of the time to live. Zero means unlimited.
     * @return $this
     */
    public function setDefaultTTL(int $duration): CacheOne
    {
        $this->defaultTTL = $duration;
        return $this;
    }

    /**
     * It sets the default value.
     * @param mixed $value
     * @return $this
     */
    public function setDefaultValue($value): CacheOne
    {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * @param mixed $input
     *
     * @return false|string
     * @throws JsonException
     */
    public function serialize($input)
    {
        switch ($this->serializer) {
            case 'php':
                return serialize($input);
            case 'json-array':
            case 'json-object':
                return json_encode($input, JSON_THROW_ON_ERROR);
            case 'none':
                return $input;
            default:
                throw new RuntimeException("serialize $this->serializer not defined");
        }
    }

    /**
     * Wrapper of function invalidate()
     *
     * @param string       $key    key of the cache to invalidate.
     * @param string|array $family family of the cache to invalidate.<br>
     *                             If it is an array. then it invalidates every family in the array
     *
     * @return bool
     * @throws Exception
     * @see CacheOne::invalidate
     *
     */
    public function invalidateCache(string $key = '', $family = ''): bool
    {
        if (is_array($family)) {
            $result = true;
            foreach ($family as $f) {
                $result = $result && $this->invalidate($f, $key);
            }
            return $result;
        }
        return $this->invalidate($family, $key);
    }

    /**
     * Invalidates a single key or a groups (family) of keys.<br>
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
     * @throws Exception
     * @throws Exception
     */
    public function invalidate(string $group = '', string $key = ''): bool
    {
        return $this->service->invalidate($group, $key);
    }
}
