<?php
/**
 * Class to handle Country information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
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
    /** Country DB record ID.
     * @var integer */
    private $country_id;

    /** Region DB record ID.
     * @var integer */
    private $region_id;

    /** Country Name.
     * @var string */
    private $country_name;

    /** Currency Code.
     * @var string */
    private $currency_code;

    /** Country ISO code.
     * @var string */
    private $iso_code;

    /** Country Dialing Code.
     * @var string */
    private $dialing_code;

    /** Sales are allowed to this country?
     * @var integer */
    private $country_enabled;

    /** Region object.
     * @var object */
    private $Region;


    /**
     * Create an object and set the variables.
     *
     * 
     */
    public function __construct($A)
    {
        $this->setID($A['country_id'])
            ->setISO($A['iso_code'])
            ->setRegionID($A['region_id'])
            ->setName($A['country_name'])
            ->setCurrencyCode($A['currency_code'])
            ->setEnabled($A['country_enabled'])
            ->setDialingCode($A['dial_code']);
    }


    /**
     * Get an instance of a country object.
     *
     * @param   string  $code   2-letter country code
     * @return  object  Country object
     */
    public static function getInstance($code)
    {
        global $_TABLES;
        static $instances = array();

        if (isset($instances[$code])) {
            return $instances[$code];
        } else {
            $sql = "SELECT * FROM gl_shop_countries WHERE ";
            if (is_integer($code)) {
                $sql .= "country_id = $code";
            } else {
                $sql .= "iso_code = '" . DB_escapeString($code) . "'";
            }
            $res = DB_query($sql);
            if ($res && DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
            } else {
                $A = array(
                    'country_id'    => 0,
                    'region_id'     => 0,
                    'currency_code' => '',
                    'iso_code'      => '',
                    'country_name'  => '',
                    'dial_code'     => '',
                    'country_enabled' => 0,
                );
            }
            return new self($A);
        }
    }


    /**
     * Set the record ID.
     * 
     * @param   string  $code   2-letter ISO code
     * @return  object  $this
     */
    private function setISO($code)
    {
        $this->iso_code = $code;
        return $this;
    }


    /**
     * Return the DB record ID for the country.
     *
     * @return  string      ISO code
     */
    public function getISO()
    {
        return $this->iso_code;
    }


    /**
     * Set the record ID.
     * 
     * @param   integer $id     DB record ID
     * @return  object  $this
     */
    private function setID($id)
    {
        $this->country_id = (int)$id;
        return $this;
    }


    /**
     * Return the DB record ID for the country.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->country_id;
    }


    /**
     * Set the Region record ID.
     * 
     * @param   integer $id     DB record ID for the region
     * @return  object  $this
     */
    private function setRegionID($id)
    {
        $this->region_id = (int)$id;
        return $this;
    }


    /**
     * Return the DB record ID for the country.
     *
     * @return  integer     Record ID
     */
    public function getRegionID()
    {
        return (int)$this->region_id;
    }


    /**
     * Set the Region record ID.
     * 
     * @param   integer $id     DB record ID for the region
     * @return  object  $this
     */
    private function setEnabled($enabled)
    {
        $this->country_enabled = $enabled == 0 ? 0 : 1;
        return $this;
    }


    /**
     * Check if sales to this country are allowed.
     * Checks the country `enabled` flag as well as the parent region.
     *
     * @return  boolean     True if enabled, False if not
     */
    public function isEnabled()
    {
        return (
            $this->country_enabled &&
            $this->getRegion()->isEnabled()
        );
    }


    /**
     * Set the Country Name.
     * 
     * @param   string  $name   Name of country
     * @return  object  $this
     */
    private function setName($name)
    {
        $this->country_name = $name;
        return $this;
    }


    /**
     * Return USPS country name by country ISO 3166-1-alpha-2 code.
     * Return empty string for unknown countries.
     *
     * @return  string      Country name, empty string if not found
     */
    public function getName()
    {
        return $this->country_name;
    }


    /**
     * Set the dialing code.
     * 
     * @param   integer $code   Numeric dialing code
     * @return  object  $this
     */
    private function setDialingCode($code)
    {
        $this->dialing_code = (int)$code;;
        return $this;
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
            return (int)$this->dialing_code;
        }
    }


    /**
     * Set the currency code.
     * 
     * @param   string  $code   Currency code
     * @return  object  $this
     */
    private function setCurrencyCode($code)
    {
        $this->currency_code = $code;;
        return $this;
    }


    /**
     * Get the currency code for a country.
     *
     * @return  string      Country currency code
     */
    public function getCurrencyCode()
    {
        return $this->currency_code;
    }


    /**
     * Get the region object associated with this country.
     *
     * @return  object  Region object
     */
    public function getRegion()
    {
        if ($this->Region === NULL) {
            $this->Region = Region::getInstance($this->getRegionID());
        }
        return $this->Region;
    }


    /**
     * Get data for a country from the static array.
     * Return array with empty values for unknown countries.
     * Returns all countries if no ID is provided.
     *
     * @param   string  $code       Country Code
     * @return  array       Array of country data (name and dialing code)
     */
    public static function getAll($enabled=true)
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM gl_shop_countries";
        if ($enabled) {
            $sql .= ' WHERE country_enabled = 1';
        }
        $sql .= ' ORDER BY country_name ASC';
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['iso_code']] = new self($A);
        }
        return $retval;
    }


    /**
     * Make a name=>code selection for the plugin configuration.
     *
     * @return  array   Array of country_name=>country_code
     */
    public static function makeConfigSelection($enabled = true)
    {
        $C = self::getAll($enabled);
        $retval = array();
        foreach ($C as $code=>$data) {
            $retval[$data->getName()] = $code;
        }
        return $retval;
    }


    /**
     * Product Admin List View.
     *
     * @return  string      HTML for the product list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT * FROM gl_shop_countries";
        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'id',
                'sort'  => true,
            ),
            array(
                'text'  => 'Country Name',
                'field' => 'country_name',
                'sort'  => true,
            ),
            array(
                'text'  => 'ISO Code',
                'field' => 'iso_code',
                'sort'  => true,
            ),
            array(
                'text'  => 'Dialing Code',
                'field' => 'diaing_code',
                'sort'  => true,
            ),
            array(
                'text'  => 'Enabled',
                'field' => 'country_enabled',
                'sort'  => true,
            ),
        );
    }


    /**
     * Get an individual field for the history screen.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = 'checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                    id=\"togenabled{$A['country_id']}\"
                    onclick='SHOP_toggle(this,\"{$A['country_id']}\",\"enabled\",".
                    "\"country\");' />" . LB;
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}

?>
