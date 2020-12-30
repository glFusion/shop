<?php
/**
 * Class to look up locations using ipstack.com.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 * @link        https://ipstack.com
 */
namespace Shop\Geo;


/**
 * Use ipstack.com
 * @package shop
 */
class ipstack extends \Shop\GeoLocator
{
    /** Descriptive key name used for caching.
     * @var string */
    protected $key = 'ipstack';

    /** Descriptive name of provider.
     * @var string */
    protected $provider = 'ipstack.com';

    /** Provider API key.
     * @var string */
    private $api_key = '';


    /**
     * Set the API key.
     */
    public function __construct()
    {
        global $_SHOP_CONF;

        $this->api_key = SHOP_getVar($_SHOP_CONF, 'ipstack_api_key');
        parent::__construct();
    }


    /**
     * Get the endpoing URL to look up an IP address.
     *
     * @return  string      URL endpoint
     */
    private function getEndpoint()
    {
        return 'http://api.ipstack.com/' . $this->ip .
            '?access_key=' . $this->api_key;
    }


    /**
     * Make the API request to the provider and cache the data.
     *
     * @return  array       Array containing country and state codes
     */
    protected function _geoLocate()
    {
        global $_SHOP_CONF, $LANG_SHOP, $_CONF;

        // Can't geolocate if the api key is empty
        if (empty($this->ip) || empty($this->api_key)) {
            return $this->default_data;
        }

        $resp = $this->getCache($this->ip);   // Try first to read from cache
        if ($resp === NULL) {           // Cache failed, look up via API
            $endpoint = $this->getEndpoint($this->ip);
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $endpoint,
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
            curl_close($ch);

            if ($status['http_code'] == 200) {
                $decoded = json_decode($resp, true);
                if (!isset($decoded['error'])) {
                    $this->setCache($resp, $this->ip);
                } else {
                    $msg = $decoded['error']['info'];
                    $decoded = $this->default_data;
                    $decoded['message'] = $msg;
                    SHOP_log($msg, SHOP_LOG_ERROR);
                }
            } else {
                SHOP_log("Geo/ipstack error {$status['http_code']}, data: $resp", SHOP_LOG_ERROR);
                return $this->default_data;
            }
        } else {
            $decoded = json_decode($resp, true);
        }
        $retval = array(
            'ip' => $decoded['ip'],
            'continent_code' => $decoded['continent_code'],
            'continent_name' => $decoded['continent_name'],
            'country_code' => $decoded['country_code'],
            'country_name' => $decoded['country_name'],
            'state_code' => $decoded['region_code'],
            'state_name' => $decoded['region_name'],
            'city_name' => $decoded['city'],
            'zip' => $decoded['zip'],
            'lat' => (float)$decoded['latitude'],
            'lng' => (float)$decoded['longitude'],
            'timezone' => $_CONF['timezone'],
            'status' => true,
            'isp' => NULL,
        );
        return $retval;
    }

}
