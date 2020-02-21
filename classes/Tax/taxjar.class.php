<?php
/**
 * TaxJar Tax API class.
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
namespace Shop\Tax;


/**
 * TaxJar tax calculation API.
 * @package shop
 */
class taxjar extends \Shop\Tax
{
    /** Sandbox endpoint.
     * @var string */
    private $api_endpoint_test = 'https://api.sandbox.taxjar.com';

    /** Production endpoint.
     * @var string */
    private $api_endpoint_prod = 'https://api.taxjar.com';

    /** Tracking URL. Set to production or test values in constructor.
     * @var string */
    private $api_token;

    /** Use Taxjar nexus? Fals to use configured nexus setting.
     * @var boolean */
    private $use_tj_nexus;

    /**
     * Set up local variables and call the parent constructor.
     *
     * @param   mixed   $A      Optional data array or shipper ID
     */
    public function __construct()
    {
        global $_SHOP_CONF;     // todo remove

        $this->api_token= $_SHOP_CONF['tax_taxjar_token'];
        $this->test_mode = (int)$_SHOP_CONF['tax_test_mode'];
        $this->use_tj_nexus = $_SHOP_CONF['tax_taxjar_nexus'] ? true : false;
        if ($this->test_mode) {
            $this->api_endpoint = $this->api_endpoint_test;     // todo: configure
        } else {
            $this->api_endpoint = $this->api_endpoint_prod;     // todo: configure
        }
        $this->key = 'taxjar';
    }


    /**
     * Look up a tax rate for the Address provided in the constructor.
     *
     * @return  float   Total tax rate for a location, globally-configurated rate on error.
     */
    public function getRate()
    {
        $data = $this->_getData()['rate'];
        $rate = 0;
        foreach (array('combined_rate', 'standard_rate') as $key) {
            if (array_key_exists($key, $data)) {
                $rate = (float)$data[$key];
            }
        }
        foreach ($this->Order->getItems() as &$Item) {
            if ($Item->isTaxable()) {
                $tax = $rate * $Item->getQuantity() * $Item->getNetPrice();
                $Item->setTax($tax)->setTaxRate($rate);
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
        $data = $this->_getData()['rate'];
        switch ($data['country']) {
        case 'US':
            $retval = $this->_breakdownUS($data);
            break;
        case 'CA':
            $retval = $this->_breakdownCA($data);
            break;
        case 'AU':
            $retval = $this->_breakdownAU($data);
            break;
        default:        // EU
            $retval = $this->_breakdownEU($data);
            break;
        }
        return $retval;
    }


    /**
     * Get the rate breakdown array for US addresses.
     *
     * @param   array   $data   Data returned from provider
     * @return  array       Standard Tax object data array
     */
    private function _breakdownUS($data)
    {
        global $LANG_SHOP;

        $retval = array(
            'country' => $data['country'],
            'totalRate' => $data['combined_rate'],
            'freightTaxable' => $data['freight_taxable'],
            'rates' => array(
                array(
                    'rate'  => (float)$data['state_rate'],
                    'name'  => $data['state'] . ' ' . $LANG_SHOP['state_rate'],
                    'type'  => 'State'
                ),
                array(
                    'rate' => (float)$data['county_rate'],
                    'name' => $data['county'] . ' ' . $LANG_SHOP['county_rate'],
                    'type' => 'County',
                ),
                array(
                    'rate' => (float)$data['city_rate'],
                    'name' => $data['city'] . ' ' . $LANG_SHOP['city_rate'],
                    'type' => 'City',
                ),
                array(
                    'rate' => (float)$data['combined_district_rate'],
                    'name' => $LANG_SHOP['special_rate'],
                    'type' => 'District',
                ),
                array(
                    'rate' => (float)$data['country_rate'],
                    'name' => $data['country'] . ' ' . $LANG_SHOP['country_rate'],
                    'type' => 'Country',
                ),
            ),
        );
        return $retval;
    }


    /**
     * Get the rate breakdown array for Canadiaan addresses.
     *
     * @param   array   $data   Data returned from provider
     * @return  array       Standard Tax object data array
     */
    private function _breakdownCA($data)
    {
        $retval = array(
            'country' => $data['country'],
            'totalRate' => $data['combined_rate'],
            'freightTaxable' => $data['freight_taxable'],
            'rates' => array(
                array(
                    'rate'  => (float)$data['combioned_rate'],
                    'name'  => 'Total Tax',
                    'type'  => 'Total'
                ),
            ),
        );
        return $retval;
    }



    /**
     * Get the rate breakdown array for Australian addresses.
     *
     * @param   array   $data   Data returned from provider
     * @return  array       Standard Tax object data array
     */
    private function _breakdownAU($data)
    {
        $retval = array(
            'country' => $data['country'],
            'totalRate' => $data['combined_rate'],
            'freightTaxable' => $data['freight_taxable'],
            'rates' => array(
                array(
                    'rate'  => (float)$data['country_rate'],
                    'name'  => 'Country: ' . $data['country'],
                    'type'  => 'Country'
                ),
            ),
        );
        return $retval;
    }


    /**
     * Get the rate breakdown array for European addresses.
     *
     * @param   array   $data   Data returned from provider
     * @return  array       Standard Tax object data array
     */
    private function _breakdownEU($data)
    {
        foreach (array('combined_rate', 'standard_rate') as $key) {
            if (array_key_exists($key, $data)) {
                $combined_rate = $data[$key];
                break;
            }
        }
        $retval = array(
            'country' => $data['country'],
            'totalRate' => 0,
            'freightTaxable' => $data['freight_taxable'],
            'rates' => array(
                array(
                    'rate'  => (float)$data['standard_rate'],
                    'name'  => 'Standard Rate',
                    'type'  => 'Standard'
                ),
                array(
                    'rate'  => (float)$data['reduced_rate'],
                    'name'  => 'Reduced Rate',
                    'type'  => 'Reduced'
                ),
                array(
                    'rate'  => (float)$data['super_reduced_rate'],
                    'name'  => 'Super-Reduced Rate',
                    'type'  => 'Super-Reduced'
                ),
                array(
                    'rate'  => (float)$data['parking_rate'],
                    'name'  => 'Parking Rate',
                    'type'  => 'Parking'
                ),
                array(
                    'rate'  => $data['distance_threshold'],
                    'name'  => 'Distance Threshold',
                    'type'  => 'Distance'
                ),
            ),
        );
        return $retval;
    }


    /**
     * Get tax data from the provider.
     *
     * @return  array   Decoded array of data from the JSON reply
     */
    protected function _getData()
    {
        global $_SHOP_CONF, $LANG_SHOP;

        // Default return value if no rate returned or no nexus
        $default = array(
            'rate' => array(
                'zip'   => $this->Address->getPostal(),
                'state_rate' => 0,
                'state' => $this->Address->getState(),
                'freight_taxable'  => false,
                'county_rate' => 0,
                'county' => 'Undefined',
                'country_rate' => 0,
                'country' => $this->Address->getCountry(),
                'combined_rate' => 0,
                'combined_district_rate' => 0,
                'city_rate' => 0,
                'city' => $this->Address->getCity(),
            ),
        );

        if (!$this->hasNexus()) {
            return $default;
        }

        $resp = $this->getCache();      // Try first to read from cache
        if ($resp === NULL) {           // Cache failed, look up via API
            $url_params = '/v2/rates/' . $this->Address->getPostal() .
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
                    'Authorization: Bearer ' . $this->api_token,
                ),
                //CURLOPT_VERBOSE => true,
            ) );
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch);
            $decoded = json_decode($resp, true);
            curl_close($ch);

            if ($status['http_code'] == 200) {
                $this->setCache($resp);
            } elseif (isset($decoded['error'])) {
                $err = $decoded['error']['details'][0];
                SHOP_log("Tax/TaxJar {$err['code']}: {$err['message']}, {$err['description']}, {$err['helpLink']}", SHOP_LOG_ERROR);
                $decoded = $default;
            }
        } else {
            $decoded = json_decode($resp, true);
        }
        return $decoded;
    }


    /**
     * Determine if the shop has a nexus in the destination state/province.
     * Retrieves the nexus locations saved at taxjar.com and compares the
     * state and country to the destination.
     * @TODO: Maybe use this function, or internal nexus settings.
     *
     * @return  boolean     True if there is a nexus, False if not.
     */
    protected function hasNexus()
    {
        // If not using the Taxjar nexus via API, then use the internal nexus config.
        if (!$this->use_tj_nexus) {
            return parent::hasNexus();
        }

        $retval = false;
        $decoded = array(
            'regions' => array()
        );
        $resp = $this->getCache('nexus');      // Try first to read from cache
        if ($resp === NULL) {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $this->api_endpoint . '/v2/nexus/regions',
                CURLOPT_USERAGENT => 'glFusion Shop',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT_MS => $this->curl_timeout, //timeout in milliseconds
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $this->api_token,
                ),
                CURLOPT_VERBOSE => true,
            ) );
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch);
            curl_close($ch);
            if ($status['http_code'] == 200) {
                $decoded = json_decode($resp,true);
                $this->setCache($resp, 'nexus');
            }
        } else {
            $decoded = json_decode($resp, true);
        }

        foreach ($decoded['regions'] as $key=>$data) {
            if (
                $data['country_code'] == $this->Address->getCountry()
                &&
                $data['region_code'] == $this->Address->getState()
            ) {
                $retval = true;
                break;
            }
        }
        return $retval;
    }

}

?>
