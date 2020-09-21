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
    public function invalidateGroup($group);

    public function invalidateAll();

    public function get($group, $key, $defaultValue = false);

    public function set($groupID, $uid, $groups, $key, $value, $duration = 1440);

    public function invalidate($group = '', $key = '');

    public function select($dbindex);
}