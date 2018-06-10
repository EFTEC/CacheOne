<?php

namespace eftec;

interface ICacheOne
{
    /**
     * Open the cache
     * @param string $server (if any)
     * @param string $schema (if any)
     * @param string $port (if any)
     * @param string $user (if any)
     * @param string $password (if any)
     * @return bool
     */
    function __construct($server="", $schema="", $port="", $user="", $password="") ;

    /**
     * @param string $group if any, it's a group or category of elements.<br>
     *        It's used when we need to invalidate (delete) a group of keys.
     * @param string $key
     * @param mixed $value This value shouldn't be serialized because the class serializes it.
     * @param int $duration in seconds. -1 is unlimited. Default is 1440, 24 minutes.
     * @return bool
     */
    function set($group, $key, $value, $duration = 1440): bool;

    /**
     * @param string $key key to return.
     * @param bool $jsonDecode if false (default value) then the result is json-decoded, otherwise is returned raw.
     * @return bool|mixed returns false if the value is not found, otherwise it returns the value.
     */
    function get($key, $jsonDecode = false);

    /**
     * @param string $group Delete an entire group
     * @return bool
     */
    function invalidateGroup($group): bool;

    /**
     * @param string $key Delete a single key
     * @return bool
     */
    function invalidate($key): bool;

    /**
     * Fix the cast of an object.
     * Usage utilCache::fixCast($objectRight,$objectBadCast);
     * @param object|array $destination Object may be empty with the right cast.
     * @param object|array $source Object with the wrong cast.
     * @return void
     */
    public static function fixCast(&$destination, $source);
}