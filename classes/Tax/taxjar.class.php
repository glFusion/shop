<?php
/**
 * TaxJar Tax API class.
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
namespace Shop\Tax;


/**
 * TaxJar tax calculation API.
 * @package shop
 */
class taxjar extends \Shop\Tax
{
    /** Sandbox endpoint.
     * @var string */
    private $api_endpoint_test = 'https://api.sandbox.taxjar.com/v2/rates';

    /** Production endpoint.
     * @var string */
    private $api_endpoint_prod = 'https://api.taxjar.com/v2/rates';

    /** Tracking URL. Set to production or test values in constructor.
     * @var string */
    private $api_key;

    private $test_mode;


    /**
     * Set up local variables and call the parent constructor.
     *
     * @param   mixed   $A      Optional data array or shipper ID
     */
    public function __construct()
    {
        global $_SHOP_CONF;     // todo remove

        $this->api_key = $_SHOP_CONF['taxjar_key'];
        $this->test_mode = (int)$_SHOP_CONF['tax_test_mode'];
        if ($this->test_mode) {
            $this->api_endpoint = $this->api_endpoint_test;     // todo: configure
        } else {
            $this->api_endpoint = $this->api_endpoint_prod;     // todo: configure
        }
        $this->key = 'taxjar';
        $this->cfgFields = array(
            'api_key' => 'password',
        );
    }


    /**
     * Look up a tax rate for the Address provided in the constructor.
     *
     * @return  float   Total tax rate for a location, globally-configurated rate on error.
     */
    public function getRate()
    {
        $data = $this->_getData()['rate'];
        return $data['combined_rate'];
    }


    /**
     * Get all the tax elements, e.g. State, County, City, etc.
     *
     * @return  array       Array of tax data
     */
    public function getRateBreakdown()
    {
        $data = $this->_getData()['rate'];
        $retval = array(
            array(
                'rate'  => (float)$data['state_rate'],
                'name'  => 'State: ' . $data['state'],
                'type'  => 'State'
            ),
            array(
                'rate' => (float)$data['county_rate'],
                'name' => 'County: ' . $data['county'],
                'type' => 'County',
            ),
            array(
                'rate' => (float)$data['city_rate'],
                'name' => 'City: ' . $data['city'],
                'type' => 'City',
            ),
            array(
                'rate' => (float)$data['combined_district_rate'],
                'name' => 'District Rate',
                'type' => 'District',
            ),
            array(
                'rate' => (float)$data['country_rate'],
                'name' => 'Country: ' . $data['country'],
                'type' => 'Country',
            )
        );
        return $retval;
    }


    /**
     * Get tax data from the provider.
     *
     * @return  array   Decoded array of data from the JSON reply
     */
    private function _getData()
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $resp = $this->getCache();      // Try first to read from cache
        if ($resp === NULL) {           // Cache failed, look up via API
            $url_params = '/' . $this->Address->getPostal() .
                '?street=' . rawurlencode($this->Address->getAddress1()) .
                '&city=' . rawurlencode($this->Address->getCity()) .
                '&state=' . rawurlencode($this->Address->getState()) .
                '&country=' . rawurlencode($this->Address->getCountry());
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $this->api_endpoint . $url_params,
                CURLOPT_USERAGENT => 'glFusion Shop',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT_MS => $this->curl_timeout, //timeout in milliseconds
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $this->api_key,
                ),
                CURLOPT_VERBOSE => true,
            ) );
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch);
            $decoded = json_decode($resp, true);
            curl_close($ch);

            if ($status['http_code'] == 200) {
                $this->setCache($resp);
            } elseif (isset($decoded['error'])) {
                $err = $decoded['error']['details'][0];
                SHOP_log("Tax/Avatax {$err['code']}: {$err['message']}, {$err['description']}, {$err['helpLink']}", SHOP_LOG_ERROR);
                $decoded = array(
                    'totalRate' => $_SHOP_CONF['tax_rate'],
                    'rates' => array(
                        'rate'  => $_SHOP_CONF['tax_rate'],
                        'name'  => $LANG_SHOP['sales_tax'],
                        'type'  => 'Total',
                    ),
                );
            }
        } else {
            $decoded = json_decode($resp, true);
        }
        return $decoded;
    }

}

?>
