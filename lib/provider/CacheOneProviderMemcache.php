<?php /** @noinspection PhpMissingParamTypeInspection */
/** @noinspection ReturnTypeCanBeDeclaredInspection */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace eftec\provider;

use eftec\CacheOne;
use Memcache;

class CacheOneProviderMemcache implements ICacheOneProvider
{
    /** @var Memcache */
    private $memcache;
    /** @var CacheOne */
    private $parent;

    /**
     * CacheOneProviderMemcache constructor.
     *
     * @param CacheOne $parent
     * @param          $server
     * @param          $port
     * @param          $schema
     */
    public function __construct($parent,$server,$port,$schema)
    {
        $this->parent = $parent;
        $this->memcache = new Memcache();
        $port = (!$port) ? 11211 : $port;
        $r = @$this->memcache->connect($server, $port);
        if ($r === false) {
            $this->memcache = null;
            $this->parent->enabled = false;
        } else {
            $this->parent->schema = $schema;
            $this->parent->enabled = true;
        }
    }

    public function invalidateGroup(array $group) : bool
    {
        $count = 0;
        if ($this->memcache !== null) {
            foreach ($group as $nameGroup) {
                $guid = $this->parent->genCatId($nameGroup);
                $cdumplist = @$this->memcache->get($guid); // it reads the catalog
                if (is_array($cdumplist)) {
                    $keys = array_keys($cdumplist);
                    foreach ($keys as $key) {
                        @$this->memcache->delete($key);
                    }
                }
                @$this->memcache->delete($guid); // delete the catalog
                $count++;
            }
        }
        return $count > 0;
    }

    public function invalidateAll() : bool
    {
        if($this->memcache===null) {
            return false;
        }
        return @$this->memcache->flush();
    }

    public function get(string $key, $defaultValue = false)
    {
        if ($this->memcache === null) {
            return false;
        }
        $uid = $this->parent->genId($key);
        $v = $this->memcache->get($uid);
        return $v === false ? $defaultValue : $v;
    }

    public function set(string $uid, array $groups, string $key, $value, int $duration = 1440) : bool
    {
        if (count($groups) === 0) {
            trigger_error('[CacheOne]: set group must contains at least one element');
            return false;
        }
        $groupID = $groups[0]; // first group
        if ($groupID !== '') {
            foreach ($groups as $group) {
                $catUid = $this->parent->genCatId($group);
                $cat = @$this->memcache->get($catUid);
                if ($cat === false) {
                    $cat = array(); // created a new catalog
                }
                if (time() % 100 === 0) {
                    // garbage collector of the catalog. We run it around every 20th reads.
                    $keys = array_keys($cat);
                    foreach ($keys as $keyf) {
                        if ($this->memcache->get($keyf) === false) {
                            unset($cat[$keyf]);
                        }
                    }
                }
                $cat[$uid] = 1;
                // the duration of the catalog is 0 (infinite) or the maximum value between the
                // default duration and the duration of the key
                $catDuration = (($duration === 0 || $duration > $this->parent->catDuration) && $this->parent->catDuration !== 0)
                    ? $duration : $this->parent->catDuration;
                $catDuration = ($catDuration !== 0) ? time() + $catDuration : 0; // duration as timestamp
                @$this->memcache->set($catUid, $cat, 0, $catDuration); // we store the catalog.
            }
        }
        return $this->memcache->set($uid, $value, 0, $duration);
    }

    public function invalidate(string $group = '', string $key = '') : bool
    {
        $uid = $this->parent->genId($key);
        return @$this->memcache->delete($uid);
    }

    public function select($dbindex) : void
    {
    }
}
