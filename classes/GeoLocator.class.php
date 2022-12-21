<?php
/**
 * Base class for IP Geolocation.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Geolocation base class.
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

        $this->withIP();
        $this->default_data['timezone'] = $_CONF['timezone'];
        $this->default_data['state_code'] = Config::get('state');
        $this->default_data['country_code'] = Config::get('country');
    }


    /**
     * Get an instance of the geolocation provider class.
     *
     * @param   string  $cls    Optional Provider class name override
     * @return  object      Geolocation provider object
     */
    public static function getProvider(string $cls='') : object
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
    public function withIP(?string $ip = NULL) : self
    {
        if (empty($ip)) {
            $spoof = Config::get('spoof_address');
            if (!empty($spoof)) {
                $this->ip = $spoof;
            } else {
                $this->ip = $_SERVER['REAL_ADDR'];
            }
        } else {
            $this->ip = $ip;
        }
        return $this;
    }


    /**
     * Get the geolocation data from the provider.
     *
     * @return  array   Array of location data
     */
    protected function _geoLocate() : array
    {
        return $this->default_data;
    }


    /**
     * Default dummy function.
     *
     * @param   string  $ip     IP address
     * @return  false
     */
    public function geoLocate()
    {
        $retval = $this->_geoLocate();
        if ($retval['status'] == false) {
            // Already determined to be invalid, no further validation needed.
            return $retval;
        }

        // Validate that good data was received.
        // At least country_code and state_code are required.
        if (
            !isset($retval['country_code']) ||
            empty($retval['country_code']) ||
            !isset($retval['state_code']) ||
            empty($retval['state_code'])
        ) {
            $retval['status'] = false;
        }
        return $retval;
    }


    /**
     * Make a cache key for a specific request.
     *
     * @param   string  $ip     IP address
     * @return  string      Cache key
     */
    private function _makeCacheKey(string $ip) : string
    {
        return DB_escapeString('shop.geo.' . $this->key . '.' . $ip);
    }


    /**
     * Read a Tracking object from cache.
     *
     * @param   string  $key    Additional cache key for data type
     * @return  object|null     Tracking object, NULL if not found
     */
    protected function getCache(string $key='') : ?string
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
     * @param   integer $exp        Minutes for cache timeout
     */
    protected function setCache(string $data, string $key, int $exp=0) : void
    {
        global $_TABLES;
        if ($exp <= 0) {
            $exp = 1440;        // 1 day
        }
        $key = $this->_makeCacheKey($key);
        Cache::set($key, $data, '', $exp);
    }


    /**
     * Get the provider name.
     *
     * @return  string      Name of provider
     */
    public function getProviderName() : string
    {
        return $this->provider;
    }


    /**
     * Check if the IP address is an RFC1918 private address.
     *
     * @param   string  $ip     IP address
     * @return  boolean     True if RFC1918
     */
    public static function isRFC1918(string $ip) : bool
    {
        $parts = explode('.', $ip);
        if ($parts[0] == '10') {
            return true;
        } elseif ($parts[0] == '192' && $parts[1] == '168') {
            return true;
        } elseif ($parts[0] == '172' && $parts[1] >= '16' && $parts[1] <= '31') {
            return true;
        } else {
            return false;
        }
    }

}
