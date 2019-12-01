<?php
/**
 * Class to handle Country information.
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
 * Class to handle country information.
 * @package shop
 */
class Country
{
    /** Country Name.
     * @var string */
    private $name;

    /** Country Dialing Code.
     * @var string */
    private $dialing_code;

    /** All countries with data.
     * @var array */
    private static $_countries = array(
        'AD' => array('name' => 'Andorra', 'code' => '376'),
        'AE' => array('name' => 'United Arab Emirates', 'code' => '971'),
        'AF' => array('name' => 'Afghanistan', 'code' => '93'),
        'AG' => array('name' => 'Antigua And Barbuda', 'code' => '1268'),
        'AI' => array('name' => 'Anguilla', 'code' => '1264'),
        'AL' => array('name' => 'Albania', 'code' => '355'),
        'AM' => array('name' => 'Armenia', 'code' => '374'),
        'AN' => array('name' => 'Netherlands Antilles', 'code' => '599'),
        'AO' => array('name' => 'Angola', 'code' => '244'),
        'AQ' => array('name' => 'Antarctica', 'code' => '672'),
        'AR' => array('name' => 'Argentina', 'code' => '54'),
        'AS' => array('name' => 'American Samoa', 'code' => '1684'),
        'AT' => array('name' => 'Austria', 'code' => '43'),
        'AU' => array('name' => 'Australia', 'code' => '61'),
        'AW' => array('name' => 'Aruba', 'code' => '297'),
        'AZ' => array('name' => 'Azerbaijan', 'code' => '994'),
        'BA' => array('name' => 'Bosnia And Herzegovina', 'code' => '387'),
        'BB' => array('name' => 'Barbados', 'code' => '1246'),
        'BD' => array('name' => 'Bangladesh', 'code' => '880'),
        'BE' => array('name' => 'Belgium', 'code' => '32'),
        'BF' => array('name' => 'Burkina Faso', 'code' => '226'),
        'BG' => array('name' => 'Bulgaria', 'code' => '359'),
        'BH' => array('name' => 'Bahrain', 'code' => '973'),
        'BI' => array('name' => 'Burundi', 'code' => '257'),
        'BJ' => array('name' => 'Benin', 'code' => '229'),
        'BL' => array('name' => 'Saint Barthelemy', 'code' => '590'),
        'BM' => array('name' => 'Bermuda', 'code' => '1441'),
        'BN' => array('name' => 'Brunei Darussalam', 'code' => '673'),
        'BO' => array('name' => 'Bolivia', 'code' => '591'),
        'BR' => array('name' => 'Brazil', 'code' => '55'),
        'BS' => array('name' => 'Bahamas', 'code' => '1242'),
        'BT' => array('name' => 'Bhutan', 'code' => '975'),
        'BW' => array('name' => 'Botswana', 'code' => '267'),
        'BY' => array('name' => 'Belarus', 'code' => '375'),
        'BZ' => array('name' => 'Belize', 'code' => '501'),
        'CA' => array('name' => 'Canada', 'code' => '1'),
        'CC' => array('name' => 'Cocos (keeling) Islands', 'code' => '61'),
        'CD' => array('name' => 'Congo, The Democratic Republic Of The', 'code' => '243'),
        'CF' => array('name' => 'Central African Republic', 'code' => '236'),
        'CG' => array('name' => 'Congo', 'code' => '242'),
        'CH' => array('name' => 'Switzerland', 'code' => '41'),
        'CI' => array('name' => 'Cote D Ivoire', 'code' => '225'),
        'CK' => array('name' => 'Cook Islands', 'code' => '682'),
        'CL' => array('name' => 'Chile', 'code' => '56'),
        'CM' => array('name' => 'Cameroon', 'code' => '237'),
        'CN' => array('name' => 'China', 'code' => '86'),
        'CO' => array('name' => 'Colombia', 'code' => '57'),
        'CR' => array('name' => 'Costa Rica', 'code' => '506'),
        'CU' => array('name' => 'Cuba', 'code' => '53'),
        'CV' => array('name' => 'Cape Verde', 'code' => '238'),
        'CX' => array('name' => 'Christmas Island', 'code' => '61'),
        'CY' => array('name' => 'Cyprus', 'code' => '357'),
        'CZ' => array('name' => 'Czech Republic', 'code' => '420'),
        'DE' => array('name' => 'Germany', 'code' => '49'),
        'DJ' => array('name' => 'Djibouti', 'code' => '253'),
        'DK' => array('name' => 'Denmark', 'code' => '45'),
        'DM' => array('name' => 'Dominica', 'code' => '1767'),
        'DO' => array('name' => 'Dominican Republic', 'code' => '1809'),
        'DZ' => array('name' => 'Algeria', 'code' => '213'),
        'EC' => array('name' => 'Ecuador', 'code' => '593'),
        'EE' => array('name' => 'Estonia', 'code' => '372'),
        'EG' => array('name' => 'Egypt', 'code' => '20'),
        'ER' => array('name' => 'Eritrea', 'code' => '291'),
        'ES' => array('name' => 'Spain', 'code' => '34'),
        'ET' => array('name' => 'Ethiopia', 'code' => '251'),
        'FI' => array('name' => 'Finland', 'code' => '358'),
        'FJ' => array('name' => 'Fiji', 'code' => '679'),
        'FK' => array('name' => 'Falkland Islands (malvinas)', 'code' => '500'),
        'FM' => array('name' => 'Micronesia, Federated States Of', 'code' => '691'),
        'FO' => array('name' => 'Faroe Islands', 'code' => '298'),
        'FR' => array('name' => 'France', 'code' => '33'),
        'GA' => array('name' => 'Gabon', 'code' => '241'),
        'GB' => array('name' => 'United Kingdom', 'code' => '44'),
        'GD' => array('name' => 'Grenada', 'code' => '1473'),
        'GE' => array('name' => 'Georgia', 'code' => '995'),
        'GH' => array('name' => 'Ghana', 'code' => '233'),
        'GI' => array('name' => 'Gibraltar', 'code' => '350'),
        'GL' => array('name' => 'Greenland', 'code' => '299'),
        'GM' => array('name' => 'Gambia', 'code' => '220'),
        'GN' => array('name' => 'Guinea', 'code' => '224'),
        'GQ' => array('name' => 'Equatorial Guinea', 'code' => '240'),
        'GR' => array('name' => 'Greece', 'code' => '30'),
        'GT' => array('name' => 'Guatemala', 'code' => '502'),
        'GU' => array('name' => 'Guam', 'code' => '1671'),
        'GW' => array('name' => 'Guinea-bissau', 'code' => '245'),
        'GY' => array('name' => 'Guyana', 'code' => '592'),
        'HK' => array('name' => 'Hong Kong', 'code' => '852'),
        'HN' => array('name' => 'Honduras', 'code' => '504'),
        'HR' => array('name' => 'Croatia', 'code' => '385'),
        'HT' => array('name' => 'Haiti', 'code' => '509'),
        'HU' => array('name' => 'Hungary', 'code' => '36'),
        'ID' => array('name' => 'Indonesia', 'code' => '62'),
        'IE' => array('name' => 'Ireland', 'code' => '353'),
        'IL' => array('name' => 'Israel', 'code' => '972'),
        'IM' => array('name' => 'Isle Of Man', 'code' => '44'),
        'IN' => array('name' => 'India', 'code' => '91'),
        'IQ' => array('name' => 'Iraq', 'code' => '964'),
        'IR' => array('name' => 'Iran, Islamic Republic Of', 'code' => '98'),
        'IS' => array('name' => 'Iceland', 'code' => '354'),
        'IT' => array('name' => 'Italy', 'code' => '39'),
        'JM' => array('name' => 'Jamaica', 'code' => '1876'),
        'JO' => array('name' => 'Jordan', 'code' => '962'),
        'JP' => array('name' => 'Japan', 'code' => '81'),
        'KE' => array('name' => 'Kenya', 'code' => '254'),
        'KG' => array('name' => 'Kyrgyzstan', 'code' => '996'),
        'KH' => array('name' => 'Cambodia', 'code' => '855'),
        'KI' => array('name' => 'Kiribati', 'code' => '686'),
        'KM' => array('name' => 'Comoros', 'code' => '269'),
        'KN' => array('name' => 'Saint Kitts And Nevis', 'code' => '1869'),
        'KP' => array('name' => 'Korea Democratic Peoples Republic Of', 'code' => '850'),
        'KR' => array('name' => 'Korea Republic Of', 'code' => '82'),
        'KW' => array('name' => 'Kuwait', 'code' => '965'),
        'KY' => array('name' => 'Cayman Islands', 'code' => '1345'),
        'KZ' => array('name' => 'Kazakstan', 'code' => '7'),
        'LA' => array('name' => 'Lao Peoples Democratic Republic', 'code' => '856'),
        'LB' => array('name' => 'Lebanon', 'code' => '961'),
        'LC' => array('name' => 'Saint Lucia', 'code' => '1758'),
        'LI' => array('name' => 'Liechtenstein', 'code' => '423'),
        'LK' => array('name' => 'Sri Lanka', 'code' => '94'),
        'LR' => array('name' => 'Liberia', 'code' => '231'),
        'LS' => array('name' => 'Lesotho', 'code' => '266'),
        'LT' => array('name' => 'Lithuania', 'code' => '370'),
        'LU' => array('name' => 'Luxembourg', 'code' => '352'),
        'LV' => array('name' => 'Latvia', 'code' => '371'),
        'LY' => array('name' => 'Libyan Arab Jamahiriya', 'code' => '218'),
        'MA' => array('name' => 'Morocco', 'code' => '212'),
        'MC' => array('name' => 'Monaco', 'code' => '377'),
        'MD' => array('name' => 'Moldova, Republic Of', 'code' => '373'),
        'ME' => array('name' => 'Montenegro', 'code' => '382'),
        'MF' => array('name' => 'Saint Martin', 'code' => '1599'),
        'MG' => array('name' => 'Madagascar', 'code' => '261'),
        'MH' => array('name' => 'Marshall Islands', 'code' => '692'),
        'MK' => array('name' => 'Macedonia, The Former Yugoslav Republic Of', 'code' => '389'),
        'ML' => array('name' => 'Mali', 'code' => '223'),
        'MM' => array('name' => 'Myanmar', 'code' => '95'),
        'MN' => array('name' => 'Mongolia', 'code' => '976'),
        'MO' => array('name' => 'Macau', 'code' => '853'),
        'MP' => array('name' => 'Northern Mariana Islands', 'code' => '1670'),
        'MR' => array('name' => 'Mauritania', 'code' => '222'),
        'MS' => array('name' => 'Montserrat', 'code' => '1664'),
        'MT' => array('name' => 'Malta', 'code' => '356'),
        'MU' => array('name' => 'Mauritius', 'code' => '230'),
        'MV' => array('name' => 'Maldives', 'code' => '960'),
        'MW' => array('name' => 'Malawi', 'code' => '265'),
        'MX' => array('name' => 'Mexico', 'code' => '52'),
        'MY' => array('name' => 'Malaysia', 'code' => '60'),
        'MZ' => array('name' => 'Mozambique', 'code' => '258'),
        'NA' => array('name' => 'Namibia', 'code' => '264'),
        'NC' => array('name' => 'New Caledonia', 'code' => '687'),
        'NE' => array('name' => 'Niger', 'code' => '227'),
        'NG' => array('name' => 'Nigeria', 'code' => '234'),
        'NI' => array('name' => 'Nicaragua', 'code' => '505'),
        'NL' => array('name' => 'Netherlands', 'code' => '31'),
        'NO' => array('name' => 'Norway', 'code' => '47'),
        'NP' => array('name' => 'Nepal', 'code' => '977'),
        'NR' => array('name' => 'Nauru', 'code' => '674'),
        'NU' => array('name' => 'Niue', 'code' => '683'),
        'NZ' => array('name' => 'New Zealand', 'code' => '64'),
        'OM' => array('name' => 'Oman', 'code' => '968'),
        'PA' => array('name' => 'Panama', 'code' => '507'),
        'PE' => array('name' => 'Peru', 'code' => '51'),
        'PF' => array('name' => 'French Polynesia', 'code' => '689'),
        'PG' => array('name' => 'Papua New Guinea', 'code' => '675'),
        'PH' => array('name' => 'Philippines', 'code' => '63'),
        'PK' => array('name' => 'Pakistan', 'code' => '92'),
        'PL' => array('name' => 'Poland', 'code' => '48'),
        'PM' => array('name' => 'Saint Pierre And Miquelon', 'code' => '508'),
        'PN' => array('name' => 'Pitcairn', 'code' => '870'),
        'PR' => array('name' => 'Puerto Rico', 'code' => '1'),
        'PT' => array('name' => 'Portugal', 'code' => '351'),
        'PW' => array('name' => 'Palau', 'code' => '680'),
        'PY' => array('name' => 'Paraguay', 'code' => '595'),
        'QA' => array('name' => 'Qatar', 'code' => '974'),
        'RO' => array('name' => 'Romania', 'code' => '40'),
        'RS' => array('name' => 'Serbia', 'code' => '381'),
        'RU' => array('name' => 'Russian Federation', 'code' => '7'),
        'RW' => array('name' => 'Rwanda', 'code' => '250'),
        'SA' => array('name' => 'Saudi Arabia', 'code' => '966'),
        'SB' => array('name' => 'Solomon Islands', 'code' => '677'),
        'SC' => array('name' => 'Seychelles', 'code' => '248'),
        'SD' => array('name' => 'Sudan', 'code' => '249'),
        'SE' => array('name' => 'Sweden', 'code' => '46'),
        'SG' => array('name' => 'Singapore', 'code' => '65'),
        'SH' => array('name' => 'Saint Helena', 'code' => '290'),
        'SI' => array('name' => 'Slovenia', 'code' => '386'),
        'SK' => array('name' => 'Slovakia', 'code' => '421'),
        'SL' => array('name' => 'Sierra Leone', 'code' => '232'),
        'SM' => array('name' => 'San Marino', 'code' => '378'),
        'SN' => array('name' => 'Senegal', 'code' => '221'),
        'SO' => array('name' => 'Somalia', 'code' => '252'),
        'SR' => array('name' => 'Suriname', 'code' => '597'),
        'ST' => array('name' => 'Sao Tome And Principe', 'code' => '239'),
        'SV' => array('name' => 'El Salvador', 'code' => '503'),
        'SY' => array('name' => 'Syrian Arab Republic', 'code' => '963'),
        'SZ' => array('name' => 'Swaziland', 'code' => '268'),
        'TC' => array('name' => 'Turks And Caicos Islands', 'code' => '1649'),
        'TD' => array('name' => 'Chad', 'code' => '235'),
        'TG' => array('name' => 'Togo', 'code' => '228'),
        'TH' => array('name' => 'Thailand', 'code' => '66'),
        'TJ' => array('name' => 'Tajikistan', 'code' => '992'),
        'TK' => array('name' => 'Tokelau', 'code' => '690'),
        'TL' => array('name' => 'Timor-leste', 'code' => '670'),
        'TM' => array('name' => 'Turkmenistan', 'code' => '993'),
        'TN' => array('name' => 'Tunisia', 'code' => '216'),
        'TO' => array('name' => 'Tonga', 'code' => '676'),
        'TR' => array('name' => 'Turkey', 'code' => '90'),
        'TT' => array('name' => 'Trinidad And Tobago', 'code' => '1868'),
        'TV' => array('name' => 'Tuvalu', 'code' => '688'),
        'TW' => array('name' => 'Taiwan, Province Of China', 'code' => '886'),
        'TZ' => array('name' => 'Tanzania, United Republic Of', 'code' => '255'),
        'UA' => array('name' => 'Ukraine', 'code' => '380'),
        'UG' => array('name' => 'Uganda', 'code' => '256'),
        'US' => array('name' => 'United States', 'code' => '1'),
        'UY' => array('name' => 'Uruguay', 'code' => '598'),
        'UZ' => array('name' => 'Uzbekistan', 'code' => '998'),
        'VA' => array('name' => 'Holy See (vatican City State)', 'code' => '39'),
        'VC' => array('name' => 'Saint Vincent And The Grenadines', 'code' => '1784'),
        'VE' => array('name' => 'Venezuela', 'code' => '58'),
        'VG' => array('name' => 'Virgin Islands, British', 'code' => '1284'),
        'VI' => array('name' => 'Virgin Islands, U.s.', 'code' => '1340'),
        'VN' => array('name' => 'Viet Nam', 'code' => '84'),
        'VU' => array('name' => 'Vanuatu', 'code' => '678'),
        'WF' => array('name' => 'Wallis And Futuna', 'code' => '681'),
        'WS' => array('name' => 'Samoa', 'code' => '685'),
        'XK' => array('name' => 'Kosovo', 'code' => '381'),
        'YE' => array('name' => 'Yemen', 'code' => '967'),
        'YT' => array('name' => 'Mayotte', 'code' => '262'),
        'ZA' => array('name' => 'South Africa', 'code' => '27'),
        'ZM' => array('name' => 'Zambia', 'code' => '260'),
        'ZW' => array('name' => 'Zimbabwe', 'code' => '263'),
    );
    
    
    /**
     * Create an object and set the variables.
     *
     * @param   string  $code   2-letter Country code
     */
    public function __construct($code)
    {
        $this->data = self::getInfo($code);
        $this->dialingCode = $this->data['code'];
        $this->name = $this->data['name'];
    }


    /**
     * Get an instance of a country object.
     *
     * @param   string  $code   2-letter country code
     * @return  object  Country object
     */
    public static function getInstance($code)
    {
        return new self($code);
    }


    /**
     * Return USPS country name by country ISO 3166-1-alpha-2 code.
     * Return empty string for unknown countries.
     *
     * @return  string      Country name, empty string if not found
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Get the dialing code for a country.
     *
     * @param   boolean $format     True to format with leading zeroes
     * @return  string      Country dialing code, empty string if not found
     */
    public function getDialingCode($format=false)
    {
        if ($format) {
            return sprintf('%03d', $this->dialing_code);
        } else {
            return $this->dialing_code;
        }
    }


    /**
     * Get data for a country from the static array.
     * Return array with empty values for unknown countries.
     * Returns all countries if no ID is provided.
     *
     * @param   string  $code       Country Code
     * @return  array       Array of country data (name and dialing code)
     */
    public static function getInfo($code = NULL)
    {
        $code = strtoupper($code);
        if (empty($code)) {
            // Nothing requested, return all counries
            return self::$_countries;
        } elseif (isset(self::$_countries[$code])) {
            // Found the requestd country ID, return the country name
            return self::$_countries[$code];
        } else {
            // Country ID not found, return false
            return array('name' => '', 'code' => '');
        }
    }


    /**
     * Make a name=>code selection for the plugin configuration.
     *
     * @return  array   Array of country_name=>country_code
     */
    public static function makeConfigSelection()
    {
        $C = self::getInfo();
        $retval = array();
        foreach ($C as $code=>$data) {
            $retval[$data['name']] = $code;
        }
        return $retval;
    }

}

?>
