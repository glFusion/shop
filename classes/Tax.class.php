<?php
/**
 * Class to get and cache sales tax rates.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Retrieve and cache sales tax information.
 * @package shop
 */
abstract class Tax
{
    /** Address object used for rate lookup.
     * @var object */
    protected $Address;

    /** Order object used for tax calculations.
     * @var object */
    protected $Order;

    /** Use test endpoints.
     * @var boolean */
    protected $test_mode = false;

    /** Default tax rates used if _getData() would return nothing.
     * @var array */
    protected $default_rates = array(
        'totalRate' => 0,
        'rates' => array(
            array(
                'rate'  => 0,
                'name'  => 'No Nexus',
                'type'  => 'Total',
            ),
        ),
    );


    /**
     * Get an instance of the tax provider class.
     *
     * @return  object      Tax provider object
     */
    public static function getProvider()
    {
        global $_SHOP_CONF;

        $cls = '\\Shop\\Tax\\' . $_SHOP_CONF['tax_provider'];
        if (class_exists($cls)) {
            return new $cls;
        } else {
            // Fallback to internal provider
            return new \Shop\Tax\internal;
        }
    }


    /**
     * Set the address for the tax calculation.
     *
     * @param   object  $Addr   Address object
     * @return  object  $this
     */
    public function withAddress($Addr)
    {
        $this->Address = $Addr;
        return $this;
    }


    /**
     * Set the Order object to use for calculations.
     *
     * @param   object  $Order  Order object
     * @return  object  $this
     */
    public function withOrder($Order)
    {
        $this->Order = $Order;
        if ($this->Address == NULL) {
            $this->Address = $this->Order->getShipto();
        }
        return $this;
    }


    /**
     * Make a cache key for a specific tax request.
     *
     * @param   string  $key    Additional cache key for data type
     * @return  string      Cache key
     */
    private function _makeCacheKey($key='')
    {
        if ($key != '') {
            $key = $key . '.';
        }
        $parts = $this->Address->getCity() .
            $this->Address->getState() .
            $this->Address->getPostal() .
            $this->Address->getCountry();
        return 'shop.tax.' . $this->key . '.' . $key . md5($parts);
    }


    /**
     * Read a Tracking object from cache.
     *
     * @param   string  $key    Additional cache key for data type
     * @return  object|null     Tracking object, NULL if not found
     */
    protected function getCache($key='')
    {
        global $_TABLES;

        $key = $this->_makeCacheKey($key);
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
     * @param   string  $key        Additional cache key for data type
     * @param   integer $exp        Seconds for cache timeout
     */
    protected function setCache($data, $key='', $exp=0)
    {
        global $_TABLES;

        $key = $this->_makeCacheKey($key);
        $key = DB_escapeString($key);
        $data = DB_escapeString(base64_encode(@serialize($data)));
        if ($exp <= 0) {
            $exp = 86400 * 7;
        }
        $exp += time();
        $sql = "INSERT IGNORE INTO {$_TABLES['shop.cache']} SET
            cache_key = '$key',
            expires = $exp,
            data = '$data'
            ON DUPLICATE KEY UPDATE
                expires = $exp,
                data = '$data'";
        DB_query($sql);
    }


    /**
     * Determine if the shop has a nexus in the destination state/province.
     * Uses a statically-configured array of state,country values.
     *
     * @return  boolean     True if there is a nexus, False if not.
     */
    protected function hasNexus()
    {
        global $_SHOP_CONF;

        if (empty($_SHOP_CONF['tax_nexuses'])) {
            // No nexus locations configured
            return true;
        }
        foreach ($_SHOP_CONF['tax_nexuses'] as $str) {
            list($country, $state) = explode(',', strtoupper($str));
            if (
                $this->Address->getCountry() == (string)$country
                &&
                (
                    empty($state)
                    ||
                    $this->Address->getState() == (string)$state
                )
            ) {
                return true;
            }
        }
        return false;
    }


    /**
     * Look up a tax rate for the Address provided in the constructor.
     *
     * @return  float   Total tax rate for a location, globally-configurated rate on error.
     */
    public function getRate()
    {
        if ($this->hasNexus()) {
            $rate = $this->_getData()['totalRate'];
        } else {
            $rate = 0;;
        }
        foreach ($this->Order->getItems() as &$Item) {
            if ($Item->isTaxable()) {
                $tax = $rate * $Item->getQuantity() * $Item->getNetPrice();
                $Item->setTotalTax($tax)->setTaxRate($rate);
            }
        }
        return $rate;
    }


    /**
     * Get all the tax elements, e.g. State, County, City, etc.
     *
     * @return  array       Array of tax data
     */
    public function getRateBreakdown()
    {
        if ($this->hasNexus()) {
            $data = $this->_getData();
            return array(
                'country' => $this->Address->getCountry(),
                'totalRate' => $data['totalRate'],
                'freightTaxable' => 0,
                'rates' => $data['rates'],
            );
        } else {
            return $this->default_rates;
        }
    }

}

?>
