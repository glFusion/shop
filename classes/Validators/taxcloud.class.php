<?php
/**
 * Class to validate addresses using taxcloud.com
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Validators;


/**
 * Class to handle address validation.
 * @package shop
 */
class taxcloud
{
    /** API Auth ID.
     * @var string */
    private $api_id;

    /** API Auth Key.
     * @var string */
    private $api_key;

    /** Internal address object, a clone of the provided object.
     * @var object */
    private $Address;


    /**
     * Load the supplied address values, if any, into the properties.
     * `$data` may be an array or a json_encoded string.
     *
     * @param   string|array    $Address    Address object
     */
    public function __construct($Address)
    {
        global $_SHOP_CONF;

        $this->api_id = $_SHOP_CONF['tax_taxcloud_id'];
        $this->api_key = $_SHOP_CONF['tax_taxcloud_key'];
        $this->Address = clone $Address;
    }


    /**
     * Perform the validation. Only works with US addresses.
     * Will return `true` if the address was successfull checked, and the
     * address may be modified. Returns `false` if the address cannot be
     * confirmed as deliverable. Also returns `false` for http errors or if
     * attempting to confirm a non-US address.
     *
     * @return  boolean     True on success, False on failure
     */
    public function Validate()
    {
        // Ensure that the provider is configured
        if (
            empty($this->api_id) ||
            empty($this->api_key) ||
            $this->Address->getCountry() != 'US'
        ) {
            return false;
        }

        $req = array(
            'apiLoginID' => $this->api_id,
            'apiKey'    => $this->api_key,
            'Address1'  => $this->Address->getAddress1(),
            'Address2'  => $this->Address->getAddress2(),
            'City'      => $this->Address->getCity(),
            'State'     => $this->Address->getState(),
            'Zip5'      => $this->Address->getZip5(),
            'Zip4'      => $this->Address->getZip4(),
        );
        $req = json_encode($req);
        $endpoint = 'https://api.taxcloud.net/1.0/TaxCloud/VerifyAddress';
        $cache_key = 'av.taxcloud.' . md5($req);
        $decoded = \Shop\Cache::get($cache_key);
        if ($decoded === NULL) {
            // Set up curl options and make the API call
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $req,
                CURLOPT_URL => $endpoint,
                CURLOPT_USERAGENT => 'glFusion Shop',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT_MS => 5000, //timeout in milliseconds
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                ),
            ) );
            $resp = curl_exec($ch);
            $http_code = curl_getinfo($ch);
            if ($http_code['http_code'] != 200) {
                // Assume address is ok to avoid interrupting checkout flow
                return false;
            }
            $decoded = json_decode($resp, true);
            if (empty($decoded)) {
                return false;
            }
            \Shop\Cache::set($cache_key, $decoded, 'addresses', 3600);
        }
        if (SHOP_getVar($decoded, 'ErrNumber', 'integer') == 0) {
            $this->Address->setAddress1(SHOP_getVar($decoded, 'Address1'));
            $this->Address->setAddress2(SHOP_getVar($decoded, 'Address2'));
            $this->Address->setCity(SHOP_getVar($decoded, 'City'));
            $this->Address->setState(SHOP_getVar($decoded, 'State'));
            $zip = SHOP_getVar($decoded, 'Zip5');
            $zip4 = SHOP_getVar($decoded, 'Zip4');
            if (!empty($zip4)) {
                $zip .= '-' . $zip4;
            }
            $this->Address->setPostal($zip);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get the address object after validation.
     *
     * @return  object      Address, possibly modified
     */
    public function getAddress()
    {
        return $this->Address;
    }

}

?>
