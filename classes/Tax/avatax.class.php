<?php
/**
 * Get tax rates from Avalara's free Tax API.
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
use Shop\Address;
use Shop\Config;


/**
 * Get tax rates from Avalara's free tax API.
 * @see https://developer.avalara.com/
 * @package shop
 */
class avatax extends \Shop\Tax
{
    /** Timeout setting for Curl calls.
     * @var integer */
    private $curl_timeout = 5000;

    /** Primary API endpoint.
     * @var string */
    private $endpoint;

    /** Key value for this provider.
     * @var string */
    protected $key = 'avatax';

    /** Tax class mapping from Product Types.
     * @var array */
    protected $taxclasses = array(
        10040 => 'SI026666',    //Installation services',
        19000 => 'S0557082',    //Miscellaneous services',
        19001 => 'SA036298',    //Services - Advertising',
        //19002 => 'SP010000',  //Services - Parking',
        19003 => 'OA020300',    //Event Admission',
        19004 => 'S0557082',    //Services - Product Training',
        19005 => 'SP140000',    //Professional Services',
        19006 => 'SJ010000',    //Services - Cleaning',
        19007 => 'SR060000',    //Services - Repair',
        19008 => 'SB040100',    //Services - Hair',
        19009 => 'SD086570',    //Services - Design',
        20010 => 'PC040100',    //Clothing - General',
        20041 => 'PC030106',    //Clothing - Swim Suits',
        30070 => 'DC020500',    //Downloadbable Software',
        31000 => 'D0000000',    //Downloads - General',
        40010 => 'PF050300',    //Candy and similar items',
        40020 => 'PF050700',    //Non-food dietary supplements',
        //40030 => 'Food for humans consumption, unprepared',
        40050 => 'PF050100',    //Soft drinks, soda, and other similar beverages',
        40060 => 'PF050115',    //Bottled, drinkable water',
        //41000 => 'Foods intended for on-site consumption. Ex. Restaurant meals.',
        51010 => 'PH050101',    //Non-Prescription Drugs - Human',
        51020 => 'PH050114',    //Prescription Drugs - Human',
        81100 => 'PB100200',    //Books, printed',
        81110 => 'PB100200',    //Textbooks, printed',
        81120 => 'PB100300',    //Religious books and manuals, printed',
        81300 => 'PN058970',    //Periodicals, printed, sold by subscription',
        81310 => 'PN050814',    //Periodicals, printed, sold individually',
    );


    /**
     * Set up internal variables and get the configuration.
     */
    public function __construct()
    {
        $this->account = Config::get('tax_avatax_account');
        $this->api_key = Config::get('tax_avatax_key');
        $this->test_mode = (int)Config::get('tax_test_mode');
        if ($this->test_mode) {
            $this->endpoint = 'https://sandbox-rest.avatax.com/api/v2/taxrates/byaddress';
        } else {
            $this->endpoint = 'https://rest.avatax.com/api/v2/taxrates/byaddress';
        }
    }


    /**
     * Get tax data from the provider.
     *
     * @return  array   Decoded array of data from the JSON reply
     */
    protected function _getData()
    {
        global $LANG_SHOP;

        if (!$this->hasNexus()) {
            return $this->default_rates;
        }

        $resp = $this->getCache();      // Try first to read from cache
        if ($resp === NULL) {           // Cache failed, look up via API
            $url_params = 'line1=' . rawurlencode($this->Address->getAddress1());
            if ($this->Address->getAddress2() != '') {
                $url_params .= '&line2=' . rawurlencode($this->Address->getAddress2());
            }
            $url_params .= 
                '&city=' . rawurlencode($this->Address->getCity()) .
                '&region=' . rawurlencode($this->Address->getState()) .
                '&postalCode=' . rawurlencode($this->Address->getPostal()) .
                '&country=' . rawurlencode($this->Address->getCountry());
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $this->endpoint . '?' . $url_params,
                CURLOPT_USERAGENT => 'glFusion Shop',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT_MS => $this->curl_timeout, //timeout in milliseconds
                CURLOPT_USERPWD => $this->account . ':' . $this->api_key,
                CURLOPT_HTTPHEADER => array(
                    "Accept: application/json",
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
                SHOP_log("Tax/Avatax {$err['code']}: {$err['message']}, {$err['description']}, {$err['helpLink']}", SHOP_LOG_ERROR);
                $decoded = $this->default_rates;
            }
        } else {
            $decoded = json_decode($resp, true);
        }

        return $decoded;
    }

}

