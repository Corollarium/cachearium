<?php

/**
* This class extends Cache_Lite and offers a form of setting expiring time in a
* per file basis.
*
* This works by setting the file time to 'now + expiration time'. This avoids
* having to read and write data from a separate database or file, or to saving
* metadata in the header of the cached data.
*
* Some filesystems may be troublesome. FAT and NTFS systems seem to be accurate
* to 2 seconds, and this module has not been tested on NFS or SAMBA filesystems.
*
* @package Cache_Lite
* @author Bruno Barberi Gnecco <brunobg@users.sf.net>
*/

require_once('Lite.php');

class Cache_Lite_Timed extends Cache_Lite
{
	var $_bufferedLifetime;

    // --- Public methods ----

    /**
    * Constructor
    *
    * $options is an assoc. To have a look at availables options,
    * see the constructor of the Cache_Lite class in 'Cache_Lite.php'
    *
    * @param array $options options
    * @access public
    */
    function Cache_Lite_Timed($options = array(NULL))
    {
        $this->Cache_Lite($options);
    }

    /**
    * Save some data in a cache file
    *
    * @param string $data data to put in cache (can be another type than strings if automaticSerialization is on)
    * @param string $id cache id
    * @param string $group name of the cache group
    * @param int $lifetime The time in seconds that this entry should live. Defaults to the lifetime
    *  set by the constructor.
    * @return boolean true if no problem (else : false or a PEAR_Error object)
    * @access public
    */
    function save($data, $id = NULL, $group = 'default', $lifetime = null)
    {
    	$res = parent::save($data, $id, $group);
        if ($res === true) {
	        if ($lifetime == null) {
	        	$lifetime = $this->_bufferedLifetime;
	        }
	        if ($lifetime == null) {
	        	$lifetime = $this->_lifeTime;
	        }
	        $res = $this->_setLastModified(time() + $lifetime);
            if (is_object($res)) {
	        	// $res is a PEAR_Error object
                if (!($this->_errorHandlingAPIBreak)) {
	                return false; // we return false (old API)
	            }
	        }
        }
        return $res;
    }

    /**
     * Sets the ctime/mtime status for a file for the given time.
     *
     * @param integer $time Unix timestamp
     * @return boolean
     */
    function _setLastModified($time) {
    	if (@touch($this->_file, $time, $time) === false) {
			return $this->raiseError('Cache_Lite : Unable to write cache file : '.$this->_file, -1);
    	}
    	return true;
    }

    /**
     * Override refresh time function. Returns current time.
     *
     */
    function _setRefreshTime() {
        if (is_null($this->_lifeTime)) {
            $this->_refreshTime = null;
        } else {
            $this->_refreshTime = time();
        }
    }

}
