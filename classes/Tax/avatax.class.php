<?php
/**
 * Get tax rates from Avalara's free Tax API.
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
use Shop\Address;


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

    /** Use test endpoints.
     * @var boolean */
    private $test_mode = false;      // todo configuration item

    /** Primary API endpoint.
     * @var string */
    private $endpoint;

    /** Key value for this provider.
     * @var string */
    protected $key = 'avatax';


    /**
     * Set up internal variables and get the configuration.
     *
     * @param   object  $Address    Address to look up
     */
    public function __construct($Address)
    {
        global $_SHOP_CONF;
        $this->account = $_SHOP_CONF['tax_avatax_account'];
        $this->api_key = $_SHOP_CONF['tax_avatax_key'];
        $this->test_mode = (int)$_SHOP_CONF['tax_test_mode'];
        $this->Address = $Address;
        if ($this->test_mode) {
            $this->endpoint = 'https://sandbox-rest.avatax.com/api/v2/taxrates/byaddress';
        } else {
            $this->endpoint = 'https://rest.avatax.com/api/v2/taxrates/byaddress';
        }
    }


    /**
     * Look up a tax rate for the Address provided in the constructor.
     *
     * @return  float   Total tax rate for a location, globally-configurated rate on error.
     */
    public function getTaxRate()
    {
        global $_SHOP_CONF;

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
                $taxRate = (float)$decoded['totalRate'];
            } elseif (isset($decoded['error'])) {
                $err = $decoded['error']['details'][0];
                SHOP_log("Tax/Avatax {$err['code']}: {$err['message']}, {$err['description']}, {$err['helpLink']}", SHOP_LOG_ERROR);
                $taxRate = $_SHOP_CONF['tax_rate'];
            }
        } else {
            $decoded = json_decode($resp, true);
            $taxRate = (float)$decoded['totalRate'];
        }

        return $taxRate;
    }
}

?>
