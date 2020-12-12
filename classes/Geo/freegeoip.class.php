<?php
/**
 * Class to look up locations using IP Geolocation.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 * @link        https://freegeoip.app
 */
namespace Shop\Geo;


/**
 * Use feegeoip.app
 * @package shop
 */
class freegeoip extends \Shop\GeoLocator
{
    /** Descriptive key name used for caching.
     * @var string */
    protected $key = 'freegeoip';

    /** Descriptive name of provider.
     * @var string */
    protected $provider = 'freegeoip.app';

    /** Provider API key.
     * @var string */
    private $api_key = '';


    /**
     * Set the API key.
     */
    public function __construct()
    {
        global $_SHOP_CONF;

        $this->api_key = $_SHOP_CONF['ipgeo_api_key'];
    }


    /**
     * Get the endpoing URL to look up an IP address.
     *
     * @return  string      URL endpoint
     */
    private function getEndpoint()
    {
        return 'https://freegeoip.app/json/' . $this->ip;
    }


    /**
     * Make the API request to the provider and cache the data.
     *
     * @return  array       Array containing country and state codes
     */
    protected function _geoLocate()
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $resp = $this->getCache($this->ip);   // Try first to read from cache
        if ($resp === NULL) {           // Cache failed, look up via API
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $this->getEndpoint($this->ip),
                CURLOPT_USERAGENT => 'glFusion Shop',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT_MS => 5000,
                CURLOPT_HTTPHEADER => array(
                    "Accept: application/json",
                ),
                //CURLOPT_VERBOSE => true,
            ) );
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch);
            $this->setCache($resp, $this->ip);
        }
        $decoded = json_decode($resp, true);
        if (is_array($decoded)) {
            $retval = array(
                'ip' => (string)$decoded['ip'],
                'continent_code' => '',
                'continent_name' => '',
                'country_code' => (string)$decoded['country_code'],
                'country_name' => (string)$decoded['country_name'],
                'state_code' => (string)$decoded['region_code'],
                'state_name' => (string)$decoded['region_name'],
                'city_name' => (string)$decoded['city'],
                'zip' => (string)$decoded['zip_code'],
                'lat' => (float)$decoded['latitude'],
                'lng' => (float)$decoded['longitude'],
                'timezone' => '',
                'status' => true,
                'isp' => '',
            );
        } else {
            $retval = $this->default_data;
        }
        return $retval;
    }

}
