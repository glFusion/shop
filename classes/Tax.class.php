<?php
/**
 * Class to get and cache sales tax rates.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
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
    const TAX_ORIGIN = 0;
    const TAX_DESTINATION = 1;

    /** Address object used for rate lookup.
     * @var object */
    protected $Address = NULL;

    /** Order object used for tax calculations.
     * @var object */
    protected $Order = NULL;

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
     * @param   string  $name   Optional provider class name
     * @return  object      Tax provider object
     */
    public static function getProvider(?string $name = NULL) : object
    {
        if ($name === NULL) {
            $name = Config::get('tax_provider');
        }
        $cls = '\\Shop\\Tax\\' . $name;
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
    public function withAddress(Address $Addr) : self
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
    public function withOrder(Order $Order) : self
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
    private function _makeCacheKey(string $key='') : string
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
    protected function getCache(string $key='') : ?object
    {
        global $_TABLES;

        $key = $this->_makeCacheKey($key);
        return Cache::get($key);
    }


    /**
     * Set the current Tracking object into cache.
     *
     * @param   string  $data       Data to set in cache
     * @param   string  $key        Additional cache key for data type
     * @param   integer $exp        Seconds for cache timeout
     */
    protected function setCache(string $data, string $key='', int $exp=0) : void
    {
        global $_TABLES;

        $key = $this->_makeCacheKey($key);
        if ($exp <= 0) {
            $exp = 86400 * 7;
        }
        Cache::set($key, $data, $exp);
    }


    /**
     * Determine if the shop has a nexus in the destination state/province.
     * Uses a statically-configured array of state,country values.
     * Requires a valid Address object being used for tax determination.
     *
     * @return  boolean     True if there is a nexus, False if not.
     */
    protected function hasNexus() : bool
    {
        $nexuses = Config::get('tax_nexuses');
        if (empty($nexuses) || !is_array($nexuses)) {
            // Return true if no nexus locations configured
            return true;
        }

        foreach ($nexuses as $str) {
            $parts = explode('-', strtoupper($str));
            $country = $parts[0];
            $state = isset($parts[1]) ? $parts[1] : '';
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
     * Updates the order items if an order is defined.
     *
     * @return  float   Total tax rate for a location, globally-configurated rate on error.
     */
    public function getRate() : float
    {
        if ($this->hasNexus()) {
            $rate = $this->_getData()['totalRate'];
        } else {
            $rate = 0;;
        }
        if ($this->Order !== NULL) {
            foreach ($this->Order->getItems() as &$Item) {
                if ($Item->isTaxable()) {
                    $tax = $rate * $Item->getQuantity() * $Item->getNetPrice();
                    $Item->setTax($tax)->setTaxRate($rate);
                }
            }
        }
        return $rate;
    }


    /**
     * Get all the tax elements, e.g. State, County, City, etc.
     *
     * @return  array       Array of tax data
     */
    public function getRateBreakdown() : array
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
