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
 * @link        https://app.ipgeolocation.io/
 */
namespace Shop\Geo;
use Shop\State;


/**
 * Use ipgeolocation.io.
 * @package shop
 */
class ipgeo extends \Shop\GeoLocator
{
    /** Descriptive key name used for caching.
     * @var string */
    protected $key = 'ipgeo';

    /** Descriptive name of provider.
     * @var string */
    protected $provider = 'ipgeolocation.io';

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
        return 'https://api.ipgeolocation.io/ipgeo?apiKey=' . $this->api_key .
            '&ip=' . $this->ip;
    }


    /**
     * Make the API request to the provider and cache the data.
     *
     * @return  array       Array containing country and state codes
     */
    public function geoLocate()
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
            $decoded = json_decode($resp, true);
            curl_close($ch);
            switch ($status['http_code']) {
            case 200:
                $decoded['status'] = true;
                $decoded['state_iso'] = State::isoFromName($decoded['country_code2'], $decoded['state_prov']);
                $resp = json_encode($decoded);  // re-encode to save in cache
                $this->setCache($resp, $this->ip);
                $msg = '';
                break;
            case 403:
            case 404:
            case 423:
                // Known bad IP error, cache default data
                $msg = $decoded['message'];
                $decoded = $this->default_data;
                $decoded['message'] = $msg;        // save message with cached data
                $resp = json_encode($decoded);
                $this->setCache($resp, $this->ip);
                return $this->default_data;
                break;
            default:
                // Some other error, do not cache the response
                $decoded['status'] = false;
                $msg = "Error {$status['http_code']}, data: $resp";
                return $this->default_data;
                break;
            }
        } else {
            $decoded = json_decode($resp, true);
        }
        $retval = array(
            'ip' => (string)$decoded['ip'],
            'continent_code' => (string)$decoded['continent_code'],
            'continent_name' => (string)$decoded['continent_name'],
            'country_code' => (string)$decoded['country_code2'],
            'country_name' => (string)$decoded['country_name'],
            'state_code' => (string)$decoded['state_iso'],
            'state_name' => (string)$decoded['state_prov'],
            'city_name' => (string)$decoded['city'],
            'zip' => (string)$decoded['zipcode'],
            'lat' => (float)$decoded['latitude'],
            'lng' => (float)$decoded['longitude'],
            'timezone' => (string)$decoded['time_zone']['name'],
            'status' => $decoded['status'],
            'isp' => $decoded['isp'],
        );
        return $retval;
    }

}
