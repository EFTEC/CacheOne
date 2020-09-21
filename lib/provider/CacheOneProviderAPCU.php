<?php /** @noinspection ReturnTypeCanBeDeclaredInspection */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace eftec\provider;

use eftec\CacheOne;

class CacheOneProviderAPCU implements ICacheOneProvider
{

    /** @var null|CacheOne */
    private $parent = null;
    

    /**
     * AbstractCacheOneRedis constructor.
     *
     * @param CacheOne $parent
     * @param string   $schema
     */
    public function __construct(
        $parent,
        $schema = ""
    ) {
        $this->parent = $parent;
        $r = @apcu_sma_info();
        $this->parent->enabled = ($r !== false);
        $this->parent->schema = $schema;
    }

    public function invalidateGroup($group)
    {
        $count = 0;
        if ($this->parent->enabled) {
            foreach ($group as $nameGroup) {
                $guid = $this->parent->genCatId($nameGroup);
                $cdumplist = $this->parent->unserialize(@apcu_fetch($guid)); // it reads the catalog
                $cdumplist = (is_object($cdumplist)) ? (array)$cdumplist : $cdumplist;
                if (is_array($cdumplist)) {
                    $keys = array_keys($cdumplist);

                    foreach ($keys as $key) {
                        @apcu_delete($key);
                    }
                }
                apcu_delete($guid);
                $count++;
            }
        }
        return $count > 0;
    }

    public function invalidateAll()
    {
        return apcu_clear_cache();
    }

    public function get($group, $key, $defaultValue = false)
    {
        $uid = $this->parent->genId($group, $key);
        $r = $this->parent->unserialize(apcu_fetch($uid));
        return $r === false ? $defaultValue : $r;
    }

    public function set($groupID, $uid, $groups, $key, $value, $duration = 1440)
    {
        if ($groupID !== '') {
            foreach ($groups as $group) {
                $catUid = $this->parent->genCatId($group);
                $cat = $this->parent->unserialize(@apcu_fetch($catUid));
                $cat = (is_object($cat)) ? (array)$cat : $cat;
                if ($cat === false) {
                    $cat = array(); // created a new catalog
                }
                if (time() % 100 === 0) {
                    // garbage collector of the catalog. We run it around every 20th reads.
                    $keys = array_keys($cat);
                    foreach ($keys as $keyf) {
                        if (!apcu_exists($keyf)) {
                            unset($cat[$keyf]);
                        }
                    }
                }
                $cat[$uid] = 1;
                $catDuration = (($duration === 0 || $duration > $this->parent->catDuration) && $this->parent->catDuration !== 0)
                    ? $duration : $this->parent->catDuration;
                apcu_store($catUid, $this->parent->serialize($cat), $catDuration);// we store the catalog
            }
        }
        return apcu_store($uid, $this->parent->serialize($value), $duration);
    }

    public function invalidate($group = '', $key = '')
    {
        $uid = $this->parent->genId($group, $key);
        return apcu_delete($uid);
    }

    public function select($dbindex)
    {
        
    }
}