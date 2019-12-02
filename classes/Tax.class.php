<?php
/**
 * Class to get and cache sales tax rates.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Retrieve and cache sales tax information.
 * @package shop
 */
class Tax
{
    /** Address object used for rate lookup.
     * @var object */
    protected $Address;


    /**
     * Get the tax rate for the provided address.
     * The default function only returns the globally-configured rate.
     *
     * @return  float   Globally-configured rate.
     */
    public function getTaxRate()
    {
        global $_SHOP_CONF;

        return SHOP_getVar($_SHOP_CONF, 'tax_rate', 'float');
    }


    /**
     * Make a cache key for a specific tracking request.
     *
     * @param   string  $shipper    Shipper ID code
     * @param   string  $tracknum   Tracking Number
     * @return  string      Cache key
     */
    private function _makeCacheKey()
    {
        $parts = $this->Address->getAddress1() .
            $this->Address->getCity() .
            $this->Address->getState() .
            $this->Address->getPostal();
        return 'shop.tax.' . $this->key . '.' . md5($parts);
    }


    /**
     * Read a Tracking object from cache.
     *
     * @param   object  $Address    Address object
     * @return  object|null     Tracking object, NULL if not found
     */
    protected function getCache()
    {
        global $_TABLES;

        $key = $this->_makeCacheKey();
        $key = DB_escapeString($key);
        $exp = time();
        $data = DB_getItem(
            $_TABLES['shop.cache'],
            'data',
            "cache_key = '$key' AND expires >= $exp"
        );
        if ($data !== NULL) {
            $data = @unserialize(base64_decode($data));
        }
        return $data;
    }


    /**
     * Set the current Tracking object into cache.
     *
     * @param   string  $data       Data to set in cache
     */
    protected function setCache($data)
    {
        global $_TABLES;

        $key = $this->_makeCacheKey();
        $key = DB_escapeString($key);
        $data = DB_escapeString(base64_encode(@serialize($data)));
        $exp = time() + (86400 * 7);
        $sql = "INSERT IGNORE INTO {$_TABLES['shop.cache']} SET
            cache_key = '$key',
            expires = $exp,
            data = '$data'
            ON DUPLICATE KEY UPDATE
                expires = $exp,
                data = '$data'";
        DB_query($sql);
    }

}

?>
