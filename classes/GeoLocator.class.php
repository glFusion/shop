<?php
/**
 * Base class for IP Geolocation.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Use ipgeolocation.io.
 * @package shop
 */
class GeoLocator
{
    /** Name of provider used for cache records.
     * @var string */
    protected $key = 'dummy';

    /** Default provider descriptive name.
     * @var string */
    protected $provider = 'undefined';

    /** IP address to look up.
     * @var string */
    protected $ip = '';

    /** Default data returned in case of error.
     * @var array */
    protected $default_data = array(
        'ip' => '',
        'continent_code' => '',
        'country_code' => '',
        'country_name' => '',
        'state_code' => '',
        'state_name' => '',
        'city_name' => '',
        'zip' => '',
        'timezone' => '',
        'lat' => 0,
        'lng' => 0,
        'status' => false,
        'isp' => NULL,
    );


    /**
     * Set up properties.
     */
    public function __construct()
    {
        global $_CONF;

        $this->ip = $_SERVER['REAL_ADDR'];
        $this->default_data['timezone'] = $_CONF['timezone'];
    }


    /**
     * Get an instance of the geolocation provider class.
     *
     * @param   string  $cls    Optional Provider class name override
     * @return  object      Geolocation provider object
     */
    public static function getProvider($cls='')
    {
        global $_SHOP_CONF;

        if (empty($cls)) {
            $cls = $_SHOP_CONF['ipgeo_provider'];
        }
        $cls = '\\Shop\\Geo\\' . $cls;
        if (class_exists($cls)) {
            return new $cls;
        } else {
            return new self;
        }
    }


    /**
     * Provide an IP address to look up, overriding the default.
     *
     * @param   string  $ip     IP address
     * @return  object  $this
     */
    public function withIP($ip = '')
    {
        if (empty($ip)) {
            $this->ip = $_SERVER['REAL_ADDR'];
        } else {
            $this->ip = $ip;
        }
        return $this;
    }


    /**
     * Default dummy function.
     *
     * @param   string  $ip     IP address
     * @return  false
     */
    public function geoLocate()
    {
        return $this->default_data;
    }


    /**
     * Make a cache key for a specific request.
     *
     * @param   string  $ip     IP address
     * @return  string      Cache key
     */
    private function _makeCacheKey($ip)
    {
        return DB_escapeString('shop.geo.' . $this->key . '.' . $ip);
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
        return Cache::get($key);
    }


    /**
     * Set the current geolocation data into cache.
     *
     * @param   string  $data       Data to set in cache
     * @param   string  $key        Additional cache key for data type
     * @param   integer $exp        Seconds for cache timeout
     */
    protected function setCache($data, $key, $exp=0)
    {
        global $_TABLES;
        if ($exp <= 0) {
            $exp = 1440 * 14;   // 14 days
        }
        $key = $this->_makeCacheKey($key);
        Cache::set($key, $data, $exp);
    }


    /**
     * Get the provider name.
     *
     * @return  string      Name of provider
     */
    public function getProviderName()
    {
        return $this->provider;
    }

}
