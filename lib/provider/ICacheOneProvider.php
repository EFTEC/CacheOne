<?php


namespace eftec\provider;

/**
 * Interface ICacheOneProvider
 * @version 1.0
 *
 * @package eftec\provider
 */
interface ICacheOneProvider
{
    public function getInstance() ;
    /**
     * @param array $group
     * @return boolean
     */
    public function invalidateGroup(array $group) : bool;

    public function invalidateAll() : bool;

    /**
     * @param string $key          The key of the value to read.
     * @param mixed  $defaultValue The default value (if the key is not found)
     * @return mixed               The result, if setSerializer() is set then it could return a variable.<br>
     *                             If setSerializer() is not set, then it will return a string.
     */
    public function get(string $key, $defaultValue = false);

    /**
     * @param string $uid
     * @param array  $groups
     * @param string $key
     * @param mixed  $value
     * @param int    $duration
     * @return bool
     */
    public function set(string $uid, array $groups, string $key, $value, int $duration = 1440) : bool;

    /**
     * @param string $group
     * @param string $key
     * @return bool
     */
    public function invalidate(string $group = '', string $key = '') : bool;

    /**
     * @param string|int $dbindex
     * @return void
     */
    public function select($dbindex) : void;

    public function initialize():bool;
    public function isInitialized():bool;

}
