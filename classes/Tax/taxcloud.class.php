<?php
/**
 * Get tax rates from taxcloud.com's API.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Tax;
use Shop\Address;
use Shop\Company;
use Shop\Validators\taxcloud as Validator;

/**
 * Get tax rates from ServiceObjects' FastTax API.
 * @see https://docs.serviceobjects.com/display/devguide/FT+-+Operations
 * @package shop
 */
class taxcloud extends \Shop\Tax
{
    /** Timeout setting for Curl calls.
     * @var integer */
    private $curl_timeout = 5000;

    /** API endpoint.
     * @var string */
    private $endpoint;

    /** Key value for this provider.
     * @var string */
    protected $key = 'taxcloud';

    private $api_key;
    private $api_id;

    /**
     * Set up internal variables and get the configuration.
     */
    public function __construct()
    {
        global $_SHOP_CONF;

        $this->api_key = $_SHOP_CONF['tax_taxcloud_key'];
        $this->api_id = $_SHOP_CONF['tax_taxcloud_id'];
        $this->endpoint = 'https://api.taxcloud.net/1.0/TaxCloud/Lookup';
    }


    /**
     * Look up a tax rate and fill in the rate and amount for the orderitems.
     * The nexus is not considered since only nexuses configured at taxcloud.com
     * are used.
     *
     * @return  float   Total tax rate for a location, globally-configurated rate on error.
     */
    public function getRate()
    {
        $data = $this->_getData()['rate'];
        if (array_key_exists('combined_rate', $data)) {
            return (float)$data['combined_rate'];
        }
        return 0;
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
                'zip'   => '',
                'state_rate' => 0,
                'state' => '',
                'freight_taxable'  => false,
                'county_rate' => 0,
                'county' => 'Undefined',
                'country_rate' => 0,
                'country' => '',
                'combined_rate' => 0,
                'combined_district_rate' => 0,
                'city_rate' => 0,
                'city' => '',
            ),
        );

        // Validate the destination address first
        if ($this->Address != NULL) {
            $Validator = new Validator($this->Address);
            $Validator->Validate();
            $Address = $Validator->getAddress();
            $default['zip'] = $Address->getPostal();
            $default['state'] = $Address->getState();
            $default['country'] = $Address->getcountry();
            $default['city'] = $Address->getCity();
        } else {
            return $default;
        }

        $Company = Company::getInstance();
        $req = array(
            'apiLoginID' => $this->api_id,
            'apiKey' => $this->api_key,
            'cartID' => $this->Order->getOrderID(),
            'customerID' => $this->Order->getUid(),
            'deliveredBySeller' => false,
            'destination' => array(
                'Address1' => $Address->getAddress1(),
                'Address2' => $Address->getAddress2(),
                'City' => $Address->getCity(),
                'State' => $Address->getState(),
                'Zip5' => $Address->getZip5(),
                'Zip4' => $Address->getZip4(),
            ),
            'origin' => array(
                'Address1' => $Company->getAddress1(),
                'Address2' => $Company->getAddress2(),
                'City' => $Company->getCity(),
                'State' => $Company->getState(),
                'Zip5' => $Company->getZip5(),
                'Zip4' => $Company->getZip4(),
            ),
        );
        $cartItems = array();
        $i = 0;
        $item_index = array();
        foreach ($this->Order->getItems() as $Item) {
            $item_index[$Item->getID()] = $i;
            if (!$Item->isTaxable()) {
                continue;
            }
            $cartItems[] = array(
                'Index' => $Item->getID(),
                'ItemID' => $Item->getProductId(),
                'Price' => $Item->getNetPrice(),
                'Qty' => $Item->getQuantity(),
                'TIC' => 94001,
            );
            $i++;
        }
        $req['cartItems'] = $cartItems;
        //var_export(json_encode($req));die;
        // We'll save the $req array as an array so we can reference the
        // cart items from the response.

        $decoded = $this->getCache();      // Try first to read from cache
        if ($decoded === NULL) {           // Cache failed, look up via API
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => json_encode($req),
                CURLOPT_URL => $this->endpoint,
                CURLOPT_USERAGENT => 'glFusion Shop Plugin',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT_MS => $this->curl_timeout, //timeout in milliseconds
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                ),
            ) );
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch);
            $decoded = json_decode($resp, true);
            $this->setCache($decoded);
        }
        //var_dump($decoded);die;
        if (SHOP_getVar($decoded, 'ResponseType', 'integer')) {
            $Items = $this->Order->getItems();
            foreach (SHOP_getVar($decoded, 'CartItemsResponse', 'array') as $item) {
                $idx = $item['CartItemIndex'];
                if (isset($Items[$idx])) {
                    $itm_total = $Items[$idx]->getQuantity() * $Items[$idx]->getNetPrice();
                    if ($itm_total > 0) {
                        $tax_rate = $item['TaxAmount'] / $itm_total;
                    } else {
                        $tax_rate = 0;
                    }
                    $Items[$idx]->setTotalTax($item['TaxAmount'])
                        ->setTaxRate($tax_rate);
                    $default['rate']['combined_rate'] = $tax_rate;
                }
            }
        }
        return $default;
    }


    /**
     * Determine if the shop has a nexus in the destination state/province.
     * Taxcloud requires nexuses to be configured on their site and will
     * calculate tax accordingly. Therefore, do not use the internal table.
     *
     * @return  boolean     True always
     */
    protected function hasNexus()
    {
        return true;
    }

}

?>
