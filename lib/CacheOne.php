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
use RuntimeException;

/**
 * Class CacheOne
 *
 * @package  eftec
 * @version  2.6.1 2021-06-12
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
    /** @var mixed */
    private $defaultValue = false;

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
    )
    {
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
                trigger_error("CacheOne: type $this->type not defined");
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
     * <b>Note:</b> The operation of update of the catalog of the group is not atomic but it is tolerable (for a
     * cache)<br>
     *
     * @param string|array $group Delete an entire group (or groups)
     *
     * @return bool Returns true if it deleted more than one key or false if error or no key deleted.
     */
    public function invalidateGroup($group)
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
        $forcedSerializer = isset($forcedSerializer) ? $forcedSerializer : $this->serializer;
        switch ($forcedSerializer) {
            case 'php':
                return unserialize($input);
            case 'json-array':
                return json_decode($input, true);
            case 'json-object':
                return json_decode($input, false);
            case 'none':
                return $input;
            default:
                trigger_error("serialize $this->serializer not defined");
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
     * @param string|array $family [not used] this value could be any value
     *
     * @return mixed
     * @see \eftec\CacheOne::getValue
     */
    public function getCache($uid, $family = '')
    {
        return $this->getValue($uid);
    }

    /**
     * It get an item from the cache<br>
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
     */
    public function getValue($key, $defaultValue = PHP_INT_MAX)
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
     * @see \eftec\CacheOne::getValue
     */
    public function get($group, $key, $defaultValue = PHP_INT_MAX)
    {
        $r=$this->getValue($key, $defaultValue === PHP_INT_MAX ? $this->defaultValue : $defaultValue);
        // $this->resetStack();
        return $r;
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
     * <b>Note:</b> The operation of update of the catalog of the group is not atomic but it is tolerable (for a
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
     */
    public function set($groups, $key, $value, $duration = null)
    {
        if (!$this->enabled) {
            return false;
        }
        $groups = (is_array($groups)) ? $groups : [$groups]; // transform a string groups into an array
        if (count($groups) === 0) {
            throw new RuntimeException('[CacheOne]: set must have a non empty group of a empty array');
        }
        $uid = $this->genId($key);
        return $this->service->set($uid, $groups, $key, $value, isset($duration) ? $duration : $this->defaultTTL);
    }

    /**
     * Generates the unique key based in the schema (if any) +  key.
     *
     * @param string $key
     *
     * @return string
     */
    public function genId($key)
    {
        $r = ($this->schema) ? $this->schema . $this->separatorUID : '';
        return $r . $key;
    }

    /**
     * It push a new value into the cache at the end of the array/list.<br>
     * If the previous value does not exists then, it creates a new array<br>
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
     * @param int          $limit         If zero, then it does not limit the values stored into the array.<br>
     *                                    If the value is not zero and the number of elements of the array surprases
     *                                    this limit, then it trim the first value and it adds a new value at the end
     *                                    of the list.
     * @param string       $limitStrategy =['nonew','popold','shiftold'][$i] // default is shiftold<br>
     *                                    nonew = it does not add a new element if the limit is reached<br>
     *                                    shiftold = if the limit is reached then it pops the first element<br>
     *                                    popold = if the limit is reached then it removes the last element<br>
     *
     * @return array|bool
     */
    public function push($groups, $key, $value, $duration = null, $limit = 0, $limitStrategy = 'shiftold')
    {
        return $this->executePushUnShift('push', $groups, $key, $value, $duration, $limit, $limitStrategy);
    }

    protected function executePushUnShift($type, $groups, $key, $value, $duration = null, $limit = 0
        , $limitStrategy = 'shiftold')
    {
        if (!$this->enabled) {
            return false;
        }
        $groups = (is_array($groups)) ? $groups : [$groups]; // transform a string groups into an array
        $originalArray = $this->service->get($key, []);
        $originalArray = $originalArray === null ? [] : $originalArray;
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
            trigger_error('CacheOne: set must have a non empty group of a empty array');
            return false;
        }
        $uid = $this->genId($key);
        return $this->service->set($uid, $groups, $key, $originalArray, isset($duration)
            ? $duration : $this->defaultTTL);
    }

    /**
     * It push a new value into the cache at the beginer of the array/list.<br>
     * If the previous value does not exists then, it creates a new array<br>
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
     * @param int          $limit         If zero, then it does not limit the values stored into the array.<br>
     *                                    If the value is not zero and the number of elements of the array surprases
     *                                    this limit, then it trim the first value and it adds a new value at the end
     *                                    of the list.
     * @param string       $limitStrategy =['nonew','popold','shiftold'][$i] // default is shiftold<br>
     *                                    nonew = it does not add a new element if the limit is reached<br>
     *                                    shiftold = if the limit is reached then it pops the first element<br>
     *                                    popold = if the limit is reached then it removes the last element<br>
     *
     * @return array|bool
     */
    public function unshift($groups, $key, $value, $duration = null, $limit = 0, $limitStrategy = 'popold')
    {
        return $this->executePushUnShift('unshift', $groups, $key, $value, $duration, $limit, $limitStrategy);
    }

    /**
     * It pops a value at the end of the array.<br>
     * It the array does not exists then it returns $defaultValue<br>
     * It the array is empty then it returns null<br>
     * The original array is modified (it is removed the element that it was pop'ed<br>
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
     */
    public function pop($group, $key, $defaultValue = PHP_INT_MAX, $duration = null)
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
     */
    protected function executePopShift($type, $group, $key, $defaultValue = PHP_INT_MAX, $duration = null)
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
        $rs = $this->service->set($uid, $group, $key, $originalArray, isset($duration)
            ? $duration : $this->defaultTTL);
        return $rs === false ? $defaultValue : $final;
    }

    /**
     * It shift (extract) a value at the beginner of the array.<br>
     * It the array does not exists then it returns $defaultValue<br>
     * It the array is empty then it returns null<br>
     * The original array is modified (it is removed the element that it was pop'ed<br>
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
     */
    public function shift($group, $key, $defaultValue = PHP_INT_MAX, $duration = null)
    {
        return $this->executePopShift('shift', $group, $key, $defaultValue, $duration);
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
     * @return $this
     */
    public function setDefaultTTL($ttl)
    {
        $this->defaultTTL = $ttl;
        return $this;
    }

    /**
     * It sets the default value.
     * @param $value
     * @return $this
     */
    public function setDefaultValue($value)
    {
        $this->defaultValue = $value;
        return $this;
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
                trigger_error("serialize $this->serializer not defined");
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
    public function invalidate($group = '', $key = '')
    {
        return $this->service->invalidate($group, $key);
    }

    protected function resetStack()
    {
        $this->defaultTTL = 1440;
        $this->defaultValue = false;
    }

}