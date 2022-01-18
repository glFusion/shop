<?php
/**
 * Class to validate addresses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Validators;
use \SimpleXMLElement;
use Shop\Cache;


/**
 * Class to handle address validation.
 * Extends the USPS Shipper class to get the endpoint and credentials.
 * @package shop
 */
class usps extends \Shop\Shippers\usps
{
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
        parent::__construct();
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
        if ($this->Address->getCountry() != 'US' || !$this->hasValidConfig()) {
            return false;
        }

        $cache_key = 'av.usps.' . md5(@serialize($this->Address->toText('address')));
        $result = Cache::get($cache_key);
        if ($result === NULL) {
            $xml = new SimpleXMLElement(
                '<AddressValidateRequest USERID="' . $this->getConfig('user_id') . '"></AddressValidateRequest>'
            );
            $xml->addChild('Revision', '1');
            $addr = $xml->addChild('Address');
            $addr->addChild('Address1', $this->Address->getAddress1());
            $addr->addChild('Address2', $this->Address->getAddress2());
            $addr->addChild('City', $this->Address->getCity());
            $addr->addChild('State', $this->Address->getState());
            $addr->addChild('Zip5', $this->Address->getZip5());
            $addr->addChild('Zip4', $this->Address->getZip4());
            $request = 'API=Verify&XML=' . urlencode($xml->asXML());
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->getEndpoint() . '?' . $request);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch);
            curl_close($ch);

            if ($http_code['http_code'] != 200|| !$result) {
                // Assume address is ok to avoid interrupting checkout flow
                return false;
            }
            Cache::set($cache_key, $result, 'addresses', 3600);
        }
        $xml = new SimpleXMLElement($result);
        if (property_exists($xml, 'Number')) {
            SHOP_log("USPS Validator Error {$xml->Number}, {$xml->Description}, {$xml->Source}");
            return false;
        }
        $xml = $xml->Address;

        // Check for the validation result
        switch ($xml->DPVConfirmation) {
        case 'N':
            // Validation failed, address not found
            // Returns false with no changes to the address object
            return false;
        default:
            // 'Y' or 'D' - succeeded, possibly missing required second line
            $status = true;
        }
        if ($xml->Address1) {
            $this->Address->setAddress2((string)$xml->Address1);
        } else {
            $this->Address->setAddress2('');
        }
        if ($xml->Address2) {
            $this->Address->setAddress1((string)$xml->Address2);
        } else {
            $this->Address->setAddress1('');
        }
        if ($xml->City) {
            $this->Address->setCity((string)$xml->City);
        }
        if ($xml->State) {
            $this->Address->setState((string)$xml->State);
        }
        if ($xml->Zip5) {
            $zip = (string)$xml->Zip5;
            if ($xml->Zip4) {
                $zip .= '-' . (string)$xml->Zip4;
            }
            $this->Address->setPostal($zip);
        }

        return $status;
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
