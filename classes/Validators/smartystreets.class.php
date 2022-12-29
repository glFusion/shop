<?php
/**
 * Class to validate addresses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Validators;
use Shop\Config;
use Shop\Log;


/**
 * Class to handle address validation.
 * @package shop
 */
class smartystreets
{
    /** API Auth ID.
     * @var string */
    private $auth_id;

    /** API Auth Token.
     * @var string */
    private $auth_token;

    /** License. Default is core, suitable for free usage.
     * @var string */
    private $license = 'us-core-cloud';

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
        $this->auth_id = Config::get('smartystreets_id');
        $this->auth_token = Config::get('smartystreets_token');
        $this->license = Config::get('smartystreets_license');
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
        if (empty($this->auth_id) || empty($this->auth_token)) {
            return false;
        }

        $auth = 'auth-id=' . $this->auth_id . '&auth-token=' . $this->auth_token;
        if ($this->Address->getCountry() == 'US') {
            $endpoint = 'https://us-street.api.smartystreets.com/street-address?';
            $url_params = array(
                'street' => $this->Address->getAddress1(),
                'city' => $this->Address->getCity(),
                'state' => $this->Address->getState(),
                'zipcode' => $this->Address->getPostal(),
            );
            if ($this->Address->getAddress2() != '') {
                $url_params['secondary'] = $this->Address->getAddress2();
            }
        } else {
            // Paid account required for international
            $endpoint = 'https://international-street.api.smartystreets.com/verify?';
            $url_params = array(
                'address1' => $this->Address->getAddress1(),
                'locality' => $this->Address->getCity(),
                'administrative_area' => $this->Address->getState(),
                'postal_code' => $this->Address->getPostal(),
            );
            if ($this->Address->getAddress2() != '') {
                $url_params['address2'] = $this->Address->getAddress2();
            }
        }
        if (!empty($this->license)) {
            $url_params['license'] = $this->license;
        }
        $url_params = http_build_query($url_params);

        $cache_key = 'av.smarty.' . md5($url_params);
        $decoded = \Shop\Cache::get($cache_key);
        //$decoded=NULL;    // for direct testing
        if ($decoded === NULL) {
            // Set up curl options and make the API call
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $endpoint . $auth .'&' . $url_params,
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
                Log::system(Log::ERROR, "SmartyStreets Validator: " . $resp);
                // Assume address is ok to avoid interrupting checkout flow
                return false;
            }
            $decoded = json_decode($resp, true);
            if (empty($decoded)) {
                return false;
            }
            \Shop\Cache::set($cache_key, $decoded, 'addresses', 3600);
        }

        // Check for the validation result
        $analysis = $decoded[0]['analysis'];
        switch ($analysis['dpv_match_code']) {
        case 'N':
            // Validation failed, address not found
            // Returns false with no changes to the address object
            $status = false;
        case 'S':
            // Validation succeeded by dropping the second address line
            //$this->Address->setAddress2('');
        default:
            // 'Y' or 'D' - succeeded, possibly missing required second line
            $status = true;
        }
        if (isset($decoded[0]['delivery_line_1'])) {
            $this->Address->setAddress1($decoded[0]['delivery_line_1']);
        }
        if (isset($decoded[0]['delivery_line_2'])) {
            $this->Address->setAddress2($decoded[0]['delivery_line_2']);
        } else {
            $this->Address->setAddress2('');
        }
        $parts = $decoded[0]['components'];
        if (isset($parts['city_name'])) {
            $this->Address->setCity($parts['city_name']);
        }
        if (isset($parts['state_abbreviation'])) {
            $this->Address->setState($parts['state_abbreviation']);
        }
        if (isset($parts['zipcode'])) {
            $zip = $parts['zipcode'];
            if (isset($parts['plus4_code'])) {
                $zip .= '-' . $parts['plus4_code'];
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
