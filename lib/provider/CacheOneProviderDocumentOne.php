<?php /** @noinspection ReturnTypeCanBeDeclaredInspection */

/** @noinspection PhpMissingParamTypeInspection */

namespace eftec\provider;

use eftec\CacheOne;
use eftec\DocumentStoreOne\DocumentStoreOne;

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
     * @param string     $server      ip of the server.
     * @param string     $schema      Default schema (optional).
     */
    public function __construct($parent,$server,$schema)
    {
        $this->parent = $parent;

        $this->documentOne=new DocumentStoreOne($server,$schema);
        $this->parent->enabled=true;
        $this->documentOne->autoSerialize(true);
        
    }

    public function invalidateGroup($group)
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

    public function invalidateAll()
    {
        $keys=$this->documentOne->select('*',true);
        $r=true;
        foreach($keys as $k) {
            $r=$r && $this->documentOne->delete($k);
        }
        return $r;
    }

    public function get($key, $defaultValue = false)
    {
        $uid = $this->parent->genId($key);
        $age=$this->documentOne->getTimeStamp($uid,true);
        $defaultTTL=$this->parent->getDefaultTTL();
        if($age>$defaultTTL && $defaultTTL!==0) {
            // file expired.
            $this->documentOne->delete($uid);
            return false;
        }
        return $this->documentOne->get($uid);
    }

    public function set($uid, $groups, $key, $value, $duration = 1440)
    {
        if (count($groups) === 0) {
            trigger_error('[CacheOne]: set group must contains at least one element');
            return false;
        }
        $groupID = $groups[0]; // first group
        if ($groupID !== '') {
            foreach ($groups as $group) {
                $catUid = $this->parent->genCatId($group);
                $cat = $this->documentOne->get($catUid);
                if ($cat === false) {
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
        return $this->documentOne->insertOrUpdate($uid, $value);
    }

    public function invalidate($group = '', $key = '')
    {
        $uid = $this->parent->genId($key);
        return @$this->documentOne->delete($uid);
    }

    public function select($dbindex)
    {
    }
}