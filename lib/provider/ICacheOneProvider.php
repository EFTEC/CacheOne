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

    public function get($group, $key, $defaultValue = false);

    public function set($uid, $groups, $key, $value, $duration = 1440);

    public function invalidate($group = '', $key = '');

    public function select($dbindex);
}