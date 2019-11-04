<?php
/**
 * Class to handle billing and shipping addresses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to handle address formatting.
 * @package shop
 */
class Address
{
    /** Address field names.
     * @var array */
    private static $_names= array(
        'name', 'company', 'address1', 'address2',
        'city', 'state', 'zip', 'country', 
    );

    /** Address data fields.
     * @var array */
    private $properties = array();


    /**
     * Load the supplied address values, if any, into the properties.
     * `$data` may be an array or a json_encoded string.
     *
     * @param   string|array    $data   Address data
     */
    public function __construct($data=array())
    {
        if (!is_array($data)) {
            $data = json_decode($data, true);
        }
        if (!is_array($data)) {
            $data = array();
        }
        foreach (self::$_names as $key) {
            $this->$key= isset($data[$key]) ? $data[$key] : '';
        }
    }


    /**
     * Set a property value.
     *
     * @param   string  $key    Key to properties array
     * @param   mixed   $val    Value to set
     */
    public function __set($key, $val)
    {
        global $_SHOP_CONF;

        switch($key) {
        case 'country':
            if (empty($val)) {
                $val = $_SHOP_CONF['country'];
            } else {
                $val = strtoupper($val);
            }
            break;
        }
        $this->properties[$key] = $val;
    }


    /**
     * Get a property's value.
     *
     * @param   string  $key    Name of value to retrieve
     * @return  mixed       Value of property, NULL if not set
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    /**
     * Convert the address fields to a single JSON string.
     *
     * @param   boolean $escape     True to escape for DB storage
     * @return  string  Address string
     */
    public function toJSON($escape=false)
    {
        $str = json_encode($this->properties);
        if ($escape) {
            $str = DB_escapeString($str);
        }
        return $str;
    }


    /**
     * Get the city, state, zip line, formatted by country.
     *
     * @return  string  Formatted string for city, state, zip
     */
    private function getCityLine()
    {
        switch($this->country) {
        case 'US':
        case 'CA':
        case 'AU':
        case 'TW':
            $retval = $this->city . ' ' . $this->state . ' ' . $this->zip;
            break;
        case 'GB':
        case 'CO':
        case 'IE':
        case '':        // default if no country code given
            $retval = $this->city . $sep . $this->zip;
            break;
        default:
            $retval = trim($this->zip. ' ' . $this->city . ' ' . $this->state);
            break;
        }
        return $retval;
    }


    /**
     * Render the address as text, separated by the specified separator.
     * Sets the city, state, zip line according to the country format.
     * A single address field can be retrieved by setting `$part` to one
     * of the field names. The special value `address` can be supplied to
     * get all the address lines except company and person name.
     *
     * @param   string  $part   Optional part of address to retrieve
     * @param   string  $sep    Line separator, simple `\n` by default.
     * @return  string      HTML formatted address
     */
    public function toText($part="all", $sep="\n")
    {
        global $_SHOP_CONF;

        $retval = '';
        $common = array(
            'name', 'company', 'address1', 'address2',
        );

        if ($part == 'address') {
            // Requesting only the address portion, remove name and company
            unset($common[0]);
            unset($common[1]);
        } elseif ($part != 'all') {
            // Immediately return the single requested element.
            // Typically name or company, not address components.
            if ($this->$part !== NULL) {
                return $this->$part;
            } else {
                return '';
            }
        }

        // No specific part requested, format and return all element
        foreach ($common as $key) {
            if ($this->$key != '') {
                $retval .= $this->$key . $sep;
            }
        }

        $retval .= $this->getCityLine();

        // Include the country as the last line, unless this is a domestic address.
        if ($_SHOP_CONF['country'] != $this->country && $this->country != '') {
            $retval .=  $sep . self::getCountryName($this->country);
        }
        return $retval;
    }


    /**
     * Get the address in HTML format. Uses `<br />\n` betwen lines.
     *
     * @uses    self::toText()
     * @param   string  $part   Optional part of address to retrieve
     * @return  string      Address as HTML
     */
    public function toHTML($part='all')
    {
        return $this->toText($part, "<br />\n");
    }


    /**
     * Get a single element of an address, e.g. `address` or `city`.
     *
     * @param   string  $key    Field name to retrieve
     * @return  string          Value of address field
     */
    public function getPart($key)
    {
        if (isset($this->$key)) {
            return $this->$key;
        } else {
            return '';
        }
    }


    /**
     * Return USPS country name by country ISO 3166-1-alpha-2 code.
     * Return false for unknown countries.
     * Returns all countries if no ID is provided.
     *
     * @param   string  $countryID  2-character country ID
     * @return  mixed   Country Name, false if not found, or all countries if no ID given
     */
    public static function getCountryName($countryID = NULL)
    {
        $countries = [
            'AD' => 'Andorra',
            'AE' => 'United Arab Emirates',
            'AF' => 'Afghanistan',
            'AG' => 'Antigua and Barbuda',
            'AI' => 'Anguilla',
            'AL' => 'Albania',
            'AM' => 'Armenia',
            'AN' => 'Netherlands Antilles',
            'AO' => 'Angola',
            'AR' => 'Argentina',
            'AT' => 'Austria',
            'AU' => 'Australia',
            'AW' => 'Aruba',
            'AX' => 'Aland Island (Finland)',
            'AZ' => 'Azerbaijan',
            'BA' => 'Bosnia-Herzegovina',
            'BB' => 'Barbados',
            'BD' => 'Bangladesh',
            'BE' => 'Belgium',
            'BF' => 'Burkina Faso',
            'BG' => 'Bulgaria',
            'BH' => 'Bahrain',
            'BI' => 'Burundi',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BN' => 'Brunei Darussalam',
            'BO' => 'Bolivia',
            'BR' => 'Brazil',
            'BS' => 'Bahamas',
            'BT' => 'Bhutan',
            'BW' => 'Botswana',
            'BY' => 'Belarus',
            'BZ' => 'Belize',
            'CA' => 'Canada',
            'CC' => 'Cocos Island (Australia)',
            'CD' => 'Congo, Democratic Republic of the',
            'CF' => 'Central African Republic',
            'CG' => 'Congo, Republic of the',
            'CH' => 'Switzerland',
            'CI' => 'Ivory Coast (Cote d Ivoire)',
            'CK' => 'Cook Islands (New Zealand)',
            'CL' => 'Chile',
            'CM' => 'Cameroon',
            'CN' => 'China',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'CU' => 'Cuba',
            'CV' => 'Cape Verde',
            'CX' => 'Christmas Island (Australia)',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DE' => 'Germany',
            'DJ' => 'Djibouti',
            'DK' => 'Denmark',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'DZ' => 'Algeria',
            'EC' => 'Ecuador',
            'EE' => 'Estonia',
            'EG' => 'Egypt',
            'ER' => 'Eritrea',
            'ES' => 'Spain',
            'ET' => 'Ethiopia',
            'FI' => 'Finland',
            'FJ' => 'Fiji',
            'FK' => 'Falkland Islands',
            'FM' => 'Micronesia, Federated States of',
            'FO' => 'Faroe Islands',
            'FR' => 'France',
            'GA' => 'Gabon',
            'GB' => 'United Kingdom',
            'GD' => 'Grenada',
            'GE' => 'Georgia, Republic of',
            'GF' => 'French Guiana',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GL' => 'Greenland',
            'GM' => 'Gambia',
            'GN' => 'Guinea',
            'GP' => 'Guadeloupe',
            'GQ' => 'Equatorial Guinea',
            'GR' => 'Greece',
            'GS' => 'South Georgia (Falkland Islands)',
            'GT' => 'Guatemala',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HK' => 'Hong Kong',
            'HN' => 'Honduras',
            'HR' => 'Croatia',
            'HT' => 'Haiti',
            'HU' => 'Hungary',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IN' => 'India',
            'IQ' => 'Iraq',
            'IR' => 'Iran',
            'IS' => 'Iceland',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JO' => 'Jordan',
            'JP' => 'Japan',
            'KE' => 'Kenya',
            'KG' => 'Kyrgyzstan',
            'KH' => 'Cambodia',
            'KI' => 'Kiribati',
            'KM' => 'Comoros',
            'KN' => 'Saint Kitts (Saint Christopher and Nevis)',
            'KP' => 'North Korea (Korea, Democratic People\'s Republic of)',
            'KR' => 'South Korea (Korea, Republic of)',
            'KW' => 'Kuwait',
            'KY' => 'Cayman Islands',
            'KZ' => 'Kazakhstan',
            'LA' => 'Laos',
            'LB' => 'Lebanon',
            'LC' => 'Saint Lucia',
            'LI' => 'Liechtenstein',
            'LK' => 'Sri Lanka',
            'LR' => 'Liberia',
            'LS' => 'Lesotho',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'LV' => 'Latvia',
            'LY' => 'Libya',
            'MA' => 'Morocco',
            'MC' => 'Monaco (France)',
            'MD' => 'Moldova',
            'MG' => 'Madagascar',
            'MK' => 'Macedonia, Republic of',
            'ML' => 'Mali',
            'MM' => 'Burma',
            'MN' => 'Mongolia',
            'MO' => 'Macao',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MS' => 'Montserrat',
            'MT' => 'Malta',
            'MU' => 'Mauritius',
            'MV' => 'Maldives',
            'MW' => 'Malawi',
            'MX' => 'Mexico',
            'MY' => 'Malaysia',
            'MZ' => 'Mozambique',
            'NA' => 'Namibia',
            'NC' => 'New Caledonia',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NI' => 'Nicaragua',
            'NL' => 'Netherlands',
            'NO' => 'Norway',
            'NP' => 'Nepal',
            'NR' => 'Nauru',
            'NZ' => 'New Zealand',
            'OM' => 'Oman',
            'PA' => 'Panama',
            'PE' => 'Peru',
            'PF' => 'French Polynesia',
            'PG' => 'Papua New Guinea',
            'PH' => 'Philippines',
            'PK' => 'Pakistan',
            'PL' => 'Poland',
            'PM' => 'Saint Pierre and Miquelon',
            'PN' => 'Pitcairn Island',
            'PT' => 'Portugal',
            'PY' => 'Paraguay',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RS' => 'Serbia',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'SA' => 'Saudi Arabia',
            'SB' => 'Solomon Islands',
            'SC' => 'Seychelles',
            'SD' => 'Sudan',
            'SE' => 'Sweden',
            'SG' => 'Singapore',
            'SH' => 'Saint Helena',
            'SI' => 'Slovenia',
            'SK' => 'Slovak Republic',
            'SL' => 'Sierra Leone',
            'SM' => 'San Marino',
            'SN' => 'Senegal',
            'SO' => 'Somalia',
            'SR' => 'Suriname',
            'ST' => 'Sao Tome and Principe',
            'SV' => 'El Salvador',
            'SY' => 'Syrian Arab Republic',
            'SZ' => 'Eswatini',
            'TC' => 'Turks and Caicos Islands',
            'TD' => 'Chad',
            'TG' => 'Togo',
            'TH' => 'Thailand',
            'TJ' => 'Tajikistan',
            'TK' => 'Tokelau (Union Group) (Western Samoa)',
            'TL' => 'East Timor (Timor-Leste, Democratic Republic of)',
            'TM' => 'Turkmenistan',
            'TN' => 'Tunisia',
            'TO' => 'Tonga',
            'TR' => 'Turkey',
            'TT' => 'Trinidad and Tobago',
            'TV' => 'Tuvalu',
            'TW' => 'Taiwan (R.O.C.)',
            'TZ' => 'Tanzania',
            'UA' => 'Ukraine',
            'UG' => 'Uganda',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VA' => 'Vatican City',
            'VC' => 'Saint Vincent and the Grenadines',
            'VE' => 'Venezuela',
            'VG' => 'British Virgin Islands',
            'VN' => 'Vietnam',
            'VU' => 'Vanuatu',
            'WF' => 'Wallis and Futuna Islands',
            'WS' => 'Western Samoa',
            'YE' => 'Yemen',
            'YT' => 'Mayotte (France)',
            'ZA' => 'South Africa',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
            'US' => 'United States',
        ];

        if ($countryID === NULL) {
            // Nothing requested, return all counries
            return $countries;
        } elseif (isset($countries[$countryID])) {
            // Found the requestd country ID, return the country name
            return $countries[$countryID];
        } else {
            // Country ID not found, return false
            return '';
        }
    }

}
