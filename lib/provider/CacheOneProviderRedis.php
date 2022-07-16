<?php

/** @noinspection PhpComposerExtensionStubsInspection */

namespace eftec\provider;

use eftec\CacheOne;
use Exception;
use Redis;

class CacheOneProviderRedis implements ICacheOneProvider
{
    /** @var null|Redis */
    private $redis;
    /** @var null|CacheOne */
    private $parent;

    /**
     * AbstractCacheOneRedis constructor.
     *
     * @param CacheOne $parent
     * @param string   $server
     * @param string   $schema
     * @param int      $port
     * @param int      $timeout
     * @param int|null $retry
     * @param int|null $readTimeout
     */
    public function __construct(
        CacheOne $parent,
        string   $server = '127.0.0.1',
        string   $schema = "",
        int      $port = 0,
        int      $timeout = 8,
        ?int $retry = null,
        ?int $readTimeout = null
    ) {
        $this->parent = $parent;

        $this->redis = new Redis();
        $port = (!$port) ? 6379 : $port;
        try {
            $conStatus = @$this->redis->pconnect($server, $port, $timeout, null, $retry, $readTimeout);
            if(is_numeric($schema) || $schema===null) {
                $this->redis->select($schema??0);
                $this->redis->_prefix(null);
            } else {
                $this->redis->select(0);
                $this->redis->_prefix($schema);
            }
        } catch (Exception $e) {
            $this->redis = null;
            $this->parent->enabled = false;
            return;
        }
        if ($conStatus === false) {
            $this->redis = null;
            $this->parent->enabled = false;
            return;
        }
        $this->parent->schema = $schema;
        $this->parent->enabled = true;
    }

    public function getInstance(): ?object
    {
        return $this->redis;
    }

    public function invalidateGroup(array $group) : bool
    {
        $numDelete = 0;
        if ($this->redis !== null) {
            foreach ($group as $nameGroup) {
                $guid = $this->parent->genCatId($nameGroup);
                $cdumplist = $this->parent->unserialize(@$this->redis->get($guid)); // it reads the catalog
                $cdumplist = (is_object($cdumplist)) ? (array)$cdumplist : $cdumplist;
                if (is_array($cdumplist)) {
                    $keys = array_keys($cdumplist);
                    foreach ($keys as $key) {
                        $numDelete += @$this->redis->del($key);
                    }
                }
                @$this->redis->del($guid); // delete the catalog
            }
        }
        return $numDelete > 0;
    }

    public function invalidateAll(): bool
    {
        if ($this->redis === null) {
            return false;
        }
        if($this->parent->schema) {
            $keys = $this->redis->keys($this->parent->schema . ':*');
        } else {
            $keys = $this->redis->keys('*');
        }
        if ($keys)
        {
            return $this->redis->del($keys)!==0;
        }
        return false;
    }

    public function get(string $key, $defaultValue = false)
    {
        $uid = $this->parent->genId($key);
        $r = $this->parent->unserialize($this->redis->get($uid));
        return $r ?? $defaultValue;
    }

    public function set(string $uid, array $groups, string $key, $value, int $duration = 1440): bool
    {
        if (count($groups) === 0) {
            trigger_error('[CacheOne]: set group must contains at least one element');
            return false;
        }
        $groupID = $groups[0]; // first group
        if ($groupID !== '') {
            foreach ($groups as $group) {
                $catUid = $this->parent->genCatId($group);
                $cat = $this->parent->unserialize(@$this->redis->get($catUid));
                $cat = (is_object($cat)) ? (array)$cat : $cat;
                if ($cat === null) {
                    $cat = array(); // created a new catalog
                }
                if (time() % 100 === 0) {
                    // garbage collector of the catalog. We run it around every 20th reads.
                    $keys = array_keys($cat);
                    foreach ($keys as $keyf) {
                        if (!$this->redis->exists($keyf)) {
                            unset($cat[$keyf]);
                        }
                    }
                }
                $cat[$uid] = 1; // we added/updated the catalog
                $catDuration = (($duration === 0 || $duration > $this->parent->catDuration)
                    && $this->parent->catDuration !== 0)
                    ? $duration : $this->parent->catDuration;
                @$this->redis->set($catUid, $this->parent->serialize($cat), $catDuration); // we store the catalog back.
            }
        }
        if ($duration === 0) {
            return $this->redis->set($uid, $this->parent->serialize($value)); // infinite duration
        }
        return $this->redis->set($uid, $this->parent->serialize($value), $duration);
    }

    public function invalidate(string $group = '', string $key = ''): bool
    {
        $uid = $this->parent->genId($key);
        if ($this->redis === null) {
            return false;
        }
        $num = $this->redis->del($uid);
        return ($num > 0);
    }

    public function select($dbindex) : void
    {
        $this->redis->select($dbindex);
    }

}
