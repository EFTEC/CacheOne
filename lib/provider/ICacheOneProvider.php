<?php /** @noinspection PhpMissingReturnTypeInspection */

/** @noinspection PhpMissingParamTypeInspection */

namespace eftec\provider;

/**
 * Interface ICacheOneProvider
 * @version 1.0
 *
 * @package eftec\provider
 */
interface ICacheOneProvider
{
    /**
     * @param array $group
     * @return boolean
     */
    public function invalidateGroup($group);

    public function invalidateAll();

    /**
     * @param string $key          The key of the value to read.
     * @param mixed  $defaultValue The default value (if the key is not found)
     * @return mixed               The result, if setSerializer() is set then it could returns a variable.<br>
     *                             If setSerializer() is not set, then it will returns a string.
     */
    public function get($key, $defaultValue = false);

    public function set($uid, $groups, $key, $value, $duration = 1440);

    public function invalidate($group = '', $key = '');

    public function select($dbindex);
}