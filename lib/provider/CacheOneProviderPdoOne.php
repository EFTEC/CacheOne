<?php


namespace eftec\provider;

use eftec\CacheOne;
use eftec\PdoOne;
use Exception;
use eftec;

class CacheOneProviderPdoOne implements ICacheOneProvider
{
    /** @var null|PdoOne */
    private $pdoOne;
    /** @var null|CacheOne */
    private $parent;

    /**
     * AbstractCacheOnePdoOne constructor.
     *
     * @param CacheOne $parent
     * @param string   $server
     * @param string   $schema
     * @param int      $port
     * @param int      $timeout
     * @param mixed    $retry
     * @param int|null $readTimeout
     * @noinspection PhpUnusedParameterInspection
     */
    public function __construct(
        CacheOne $parent,
        string   $server = '127.0.0.1',
        string   $schema = "",
        int      $port = 0,
        int      $timeout = 8,
                 $retry = null,
        ?int     $readTimeout = null
    )
    {
        $this->parent = $parent;
        $this->pdoOne = PdoOne::instance();
        $this->parent->schema = $schema;
        $this->parent->enabled = true;
    }

    public function getInstance(): ?PdoOne
    {
        return $this->pdoOne;
    }

    /**
     * @throws Exception
     */
    public function invalidateGroup(array $group): bool
    {
        $numDelete = 0;
        if ($this->pdoOne !== null) {
            foreach ($group as $nameGroup) {
                $guid = $this->parent->genCatId($nameGroup);
                $cdumplist = $this->parent->unserialize(@$this->pdoOne->getKV($guid)); // it reads the catalog
                $cdumplist = (is_object($cdumplist)) ? (array)$cdumplist : $cdumplist;
                if (is_array($cdumplist)) {
                    $keys = array_keys($cdumplist);
                    foreach ($keys as $key) {
                        $numDelete += @$this->pdoOne->delKV($key);
                    }
                }
                @$this->pdoOne->delKV($guid); // delete the catalog
            }
        }
        return $numDelete > 0;
    }

    /**
     * @throws Exception
     */
    public function invalidateAll(): bool
    {
        if ($this->pdoOne === null) {
            return false;
        }
        return $this->pdoOne->flushKV();
    }

    /**
     * @throws Exception
     */
    public function get(string $key, $defaultValue = false)
    {
        $uid = $this->parent->genId($key);
        $r = $this->parent->unserialize($this->pdoOne->getKV($uid));
        return $r ?? $defaultValue;
    }

    /**
     * @throws Exception
     */
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
                $cat = $this->parent->unserialize(@$this->pdoOne->getKV($catUid));
                $cat = (is_object($cat)) ? (array)$cat : $cat;
                if ($cat === null) {
                    $cat = array(); // created a new catalog
                }
                if (time() % 100 === 0) {
                    // garbage collector of the catalog. We run it around every 20th reads.
                    $keys = array_keys($cat);
                    foreach ($keys as $keyf) {
                        if (!$this->pdoOne->existKV($keyf)) {
                            unset($cat[$keyf]);
                        }
                    }
                }
                $cat[$uid] = 1; // we added/updated the catalog
                $catDuration = (($duration === 0 || $duration > $this->parent->catDuration)
                    && $this->parent->catDuration !== 0)
                    ? $duration : $this->parent->catDuration;
                @$this->pdoOne->setKV($catUid, $this->parent->serialize($cat), $catDuration); // we store the catalog back.
            }
        }
        if ($duration === 0) {
            return $this->pdoOne->setKV($uid, $this->parent->serialize($value)); // infinite duration
        }
        return $this->pdoOne->setKV($uid, $this->parent->serialize($value), $duration);
    }

    /**
     * @throws Exception
     */
    public function invalidate(string $group = '', string $key = ''): bool
    {
        $uid = $this->parent->genId($key);
        if ($this->pdoOne === null) {
            return false;
        }
        $num = $this->pdoOne->delKV($uid);
        return ($num > 0);
    }

    public function select($dbindex): void
    {
        $this->pdoOne->setKvDefaultTable($dbindex);
    }

}
