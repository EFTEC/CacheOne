<?php /** @noinspection ReturnTypeCanBeDeclaredInspection */
/** @noinspection PhpMissingParamTypeInspection */

/** @noinspection PhpComposerExtensionStubsInspection */

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
     * @param null     $retry
     * @param null     $readTimeout
     */
    public function __construct(
        $parent,
        $server = '127.0.0.1',
        $schema = "",
        $port = 0,
        $timeout = 8,
        $retry = null,
        $readTimeout = null
    ) {
        $this->parent = $parent;

        $this->pdoOne = PdoOne::instance(true);

        $this->parent->schema = $schema;
        $this->parent->enabled = true;
    }

    /**
     * @throws Exception
     */
    public function invalidateGroup($group) : bool
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
    public function invalidateAll()
    {
        if ($this->pdoOne === null) {
            return false;
        }
        return $this->pdoOne->flushKV();
    }

    /**
     * @throws Exception
     */
    public function get($key, $defaultValue = false)
    {
        $uid = $this->parent->genId($key);
        $r = $this->parent->unserialize($this->pdoOne->getKV($uid));
        return $r === false ? $defaultValue : $r;
    }

    /**
     * @throws Exception
     */
    public function set($uid, $groups, $key, $value, $duration = 1440)
    {
        $groups = (is_array($groups)) ? $groups : [$groups]; // transform a string groups into an array
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
                if ($cat === false) {
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
    public function invalidate($group = '', $key = '')
    {
        $uid = $this->parent->genId($key);
        if ($this->pdoOne === null) {
            return false;
        }
        $num = $this->pdoOne->delKV($uid);
        return ($num > 0);
    }

    public function select($dbindex) {
        $this->pdoOne->setKvDefaultTable($dbindex);
    }

}