<?php

/** @noinspection PhpMissingParamTypeInspection */

namespace eftec\provider;

use eftec\CacheOne;
use eftec\DocumentStoreOne\DocumentStoreOne;
use Exception;

class CacheOneProviderDocumentOne implements ICacheOneProvider
{
    /** @var DocumentStoreOne */
    private $documentOne;
    /** @var null|CacheOne */
    private $parent;

    /**
     * CacheOneProviderDocumentOne constructor.
     *
     * @param CacheOne|null $parent
     * @param string     $server      root folder of the document server.
     * @param string     $schema      Default folder schema (optional).
     */
    public function __construct($parent,$server,$schema)
    {
        $this->parent = $parent;

        $this->documentOne=new DocumentStoreOne($server,$schema);
        $this->parent->enabled=true;
        $this->documentOne->autoSerialize(true);

    }
    public function getInstance(): DocumentStoreOne
    {
        return $this->documentOne;
    }

    public function invalidateGroup(array $group) : bool
    {
        $count = 0;
        if ($this->parent->enabled) {
            foreach ($group as $nameGroup) {
                $guid = $this->parent->genCatId($nameGroup);
                $cdumplist = $this->documentOne->get($guid);
                if (is_array($cdumplist)) {
                    $keys = array_keys($cdumplist);
                    foreach ($keys as $key) {
                        $this->documentOne->delete($key);
                    }
                }
                $this->documentOne->delete($guid);
                $count++;
            }
        }
        return $count > 0;
    }

    public function invalidateAll() : bool
    {
        $keys=$this->documentOne->select('*',true);
        $r=true;
        foreach($keys as $k) {
            $r=$r && $this->documentOne->delete($k);
        }
        return $r;
    }

    public function get(string $key, $defaultValue = false)
    {
        try {
            $uid = $this->parent->genId($key);
            $age = $this->documentOne->getTimeStamp($uid, true);
            if ($age>0 && $age<1000000000) { // anything <1000000000 means it never expires
                // file expired.
                $this->documentOne->delete($uid);
                return false;
            }
            return $this->documentOne->get($uid);
        } catch(Exception $ex) {
            return $defaultValue;
        }
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
                $this->documentOne->throwable=false;
                $cat = $this->documentOne->get($catUid);
                $this->documentOne->throwable=true;
                if ($cat === null) {
                    $cat = array(); // created a new catalog
                }

                if (time() % 100 === 0) {
                    // garbage collector of the catalog. We run it around every 20th reads.
                    $keys = array_keys($cat);
                    foreach ($keys as $keyf) {
                        if ($this->documentOne->get($keyf) === false) {
                            unset($cat[$keyf]);
                        }
                    }
                }
                $cat[$uid] = 1;
                // the duration of the catalog is 0 (infinite) or the maximum value between the
                // default duration and the duration of the key
                //$catDuration = (($duration === 0 || $duration > $this->catDuration) && $this->catDuration
                //    !== 0)
                //    ? $duration : $this->catDuration;
                //$catDuration = ($catDuration !== 0) ? time() + $catDuration : 0; // duration as timestamp
                // $catUid, $cat, 0, $catDuration
                $this->documentOne->insertOrUpdate($catUid,$cat); // we store the catalog.

            }
        }
        $r=$this->documentOne->insertOrUpdate($uid, $value);
        if($duration===0) {
            $this->documentOne->setTimeStamp($uid, 1, true);
        } else {
            $this->documentOne->setTimeStamp($uid, $duration, false);
        }
        return $r;
    }

    public function invalidate(string $group = '', string $key = '') : bool
    {
        $uid = $this->parent->genId($key);
        return @$this->documentOne->delete($uid);
    }

    public function select($dbindex) : void
    {
    }
}
