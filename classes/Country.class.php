<?php
/**
 * Class to handle Country information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
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
class Country extends RegionBase
{
    /** Country DB table key.
     * @var string */
    protected static $TABLE = 'shop.countries';

    /** Cache tag.
     * @var string */
    protected static $TAG = 'countries';

    /** Table type, used to create variable names.
     * .@var string */
    protected static $KEY = 'country';

    /** Country DB record ID.
     * @var integer */
    private $country_id;

    /** Region DB record ID.
     * @var integer */
    private $region_id;

    /** Numeric UN country code
     * @var integer */
    private $country_code;

    /** Country Name.
     * @var string */
    private $country_name;

    /** Currency Code.
     * @var string */
    private $currency_code;

    /** Country 2-character ISO code.
     * @var string */
    private $alpha2;

    /** Country 3-character ISO code.
     * @var string */
    private $alpha3;

    /** Country Dialing Code.
     * @var string */
    private $dial_code;

    /** Sales are allowed to this country?
     * @var integer */
    private $country_enabled;

    /** Region object.
     * @var object */
    private $Region;


    /**
     * Create an object and set the variables.
     *
     * @param   array   $A      Array from form or DB record
     */
    public function __construct($A)
    {
        $this->setVars($A);
    }


    /**
     * Set variables from a DB record or form into local variables.
     *
     * @param   array   $A      Array from $_POST or DB
     */
    private function setVars($A)
    {
        if (isset($A['country_id'])) {
            $this->setID($A['country_id']);
        }
        if (isset($A['alpha2'])) {
            $this->setAlpha2($A['alpha2']);
        }
        if (isset($A['alpha3'])) {
            $this->setAlpha3($A['alpha3']);
        }
        if (isset($A['region_id'])) {
            $this->setRegionID($A['region_id']);
        }
        if (isset($A['country_code'])) {
            $this->setCode($A['country_code']);
        }
        if (isset($A['country_name'])) {
            $this->setName($A['country_name']);
        }
        if (isset($A['currency_code'])) {
            $this->setCurrencyCode($A['currency_code']);
        }
        if (isset($A['country_enabled'])) {
            $this->setEnabled($A['country_enabled']);
        }
        if (isset($A['dial_code'])) {
            $this->setDialCode($A['dial_code']);
        }
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
            $sql = "SELECT * FROM {$_TABLES['shop.countries']} WHERE ";
            if (is_integer($code)) {
                $sql .= "country_id = $code";
            } else {
                $sql .= "alpha2 = '" . DB_escapeString($code) . "'";
            }
            $res = DB_query($sql);
            if ($res && DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
            } else {
                $A = array(
                    'country_id'    => 0,
                    'region_id'     => 0,
                    'country_code'  => 0,
                    'currency_code' => '',
                    'alpha2'      => '',
                    'alpha3'      => '',
                    'country_name'  => '',
                    'dial_code'     => '',
                    'country_enabled' => 1,
                );
            }
            return new self($A);
        }
    }


    /**
     * Set the 2-letter UN Alpha code
     *
     * @param   string  $code   2-letter ISO code
     * @return  object  $this
     */
    private function setAlpha2($code)
    {
        $this->alpha2 = $code;
        return $this;
    }


    /**
     * Return the 2-letter UN Alpha code
     *
     * @return  string      ISO code
     */
    public function getAlpha2()
    {
        return $this->alpha2;
    }


    /**
     * Set the 2-letter UN Alpha code
     *
     * @param   string  $code   2-letter ISO code
     * @return  object  $this
     */
    private function setAlpha3($code)
    {
        $this->alpha3 = $code;
        return $this;
    }


    /**
     * Return the 3-letter UN Alpha code
     *
     * @return  string      ISO code
     */
    public function getAlpha3()
    {
        return $this->alpha3;
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
     * Set the numeric UN country code.
     *
     * @param   integer $code  Country code
     * @return  object  $this
     */
    private function setCode($code)
    {
        $this->country_code = (int)$code;
        return $this;
    }


    /**
     * Return the numeric UN country code.
     *
     * @return  integer     Record ID
     */
    public function getCode()
    {
        return (int)$this->country_code;
    }


    /**
     * Set the Region record ID.
     *
     * @param   integer $enabled    Zero to disable, nonzero to enable
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
    private function setDialCode($code)
    {
        $this->dial_code = (int)$code;;
        return $this;
    }


    /**
     * Get the dialing code for a country.
     *
     * @param   boolean $format     True to format with leading zeroes
     * @return  string      Country dialing code, empty string if not found
     */
    public function getDialCode($format=false)
    {
        if ($format) {
            return sprintf('%03d', $this->dial_code);
        } else {
            return (int)$this->dial_code;
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
     * Get all country objects into an array.
     *
     * @param   string  $enabled    True to get only enabled countries
     * @return  array       Array of Country objects
     */
    public static function getAll($enabled=true)
    {
        global $_TABLES;

        $enabled = $enabled ? 1 : 0;
        $cache_key = 'shop.countries_all_' . $enabled;
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $sql = "SELECT c.* FROM {$_TABLES['shop.countries']} c";
            if ($enabled) {
                $sql .= " LEFT JOIN {$_TABLES['shop.regions']} r
                    ON c.region_id = r.region_id
                    WHERE c.country_enabled = 1
                    AND r.region_enabled =1";
            }
            $sql .= ' ORDER BY c.country_name ASC';
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['alpha2']] = new self($A);
            }
            // Cache for a month, this doesn't change often
            Cache::set($cache_key, $retval, 'regions', 43200);
        }
        return $retval;
    }


    /**
     * Make a name=>code selection for the plugin configuration.
     *
     * @uses    self::getAll()
     * @param   boolean $enabled    True to only show enabled countries
     * @return  array   Array of country_name=>country_code
     */
    public static function makeConfigSelection($enabled = true)
    {
        $C = self::getAll($enabled);
        $retval = array();
        foreach ($C as $code=>$data) {
            $retval[$data->getName()] = $data->getAlpha2();
        }
        return $retval;
    }


    /**
     * Create the option tags for a country selection list.
     *
     * @uses    self::getAll()
     * @param   string  $sel    Currently-selected ISO code
     * @param   boolean $enabled    True for only enabled countries
     * @return  string      Option tags for selection list
     */
    public static function optionList($sel = '', $enabled = true)
    {
        $retval = '';
        $arr = self::getAll($enabled);
        foreach ($arr as $iso=>$C) {
            $selected = $sel == $iso ? 'selected="selected"' : '';
            $retval .= "<option $selected value=\"{$iso}\">{$C->getName()}</option>";
        }
        return $retval;
    }


    /**
     * Edit a country record.
     *
     * @param   array   $A  $_POST values, if re-editing due to an error
     * @return  string      HTML for editing form
     */
    public function Edit($A=NULL)
    {
        if (is_array($A)) {
            $this->setVars($A);
        }
        $T = new Template('admin');
        $T->set_file(array(
            'form' => 'country.thtml',
        ) );
        $T->set_var(array(
            'country_id'    => $this->getID(),
            'alpha2'      => $this->getAlpha2(),
            'alpha3'      => $this->getAlpha3(),
            'country_code' => $this->getCode(),
            'country_name' => $this->getName(),
            'currency_options' => Currency::optionList($this->getCurrencyCode()),
            'dial_code'     => $this->getDialCode(),
            'region_options' => Region::optionLIst($this->region_id, false),
            'ena_chk'       => $this->country_enabled ? 'checked="checked"' : '',
            'tooltipster_js' => Tooltipster::get('country_form'),
        ) );
        $T->parse('output','form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save the country information.
     *
     * @param   array   $A  Optional data array from $_POST
     * @return  boolean     True on success, False on failure
     */
    public function Save($A=NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setID($A['country_id'])
                ->setAlpha2($A['alpha2'])
                ->setAlpha3($A['alpha3'])
                ->setRegionID($A['region_id'])
                ->setCode($A['country_code'])
                ->setName($A['country_name'])
                ->setCurrencyCode($A['currency_code'])
                ->setEnabled($A['country_enabled'])
                ->setDialCode($A['dial_code']);
        }
        if ($this->getID() > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.countries']} SET ";
            $sql3 = " WHERE country_id ='" . $this->getID() . "'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.countries']} SET ";
            $sql3 = '';
        }
        $sql2 = "alpha2 = '" . DB_escapeString($this->getAlpha2()) . "',
            alpha3 = '" . DB_escapeString($this->getAlpha3()) . "',
            region_id = {$this->getRegionID()},
            country_code = {$this->getCode()},
            country_name = '" . DB_escapeString($this->country_name) . "',
            currency_code = '" . DB_escapeString($this->currency_code) . "',
            dial_code = '" . DB_escapeString($this->dial_code) . "',
            country_enabled = " . (int)$this->country_enabled;
        $sql = $sql1 . $sql2 . $sql3;
        //var_dump($this);die;
        //echo $sql;die;
        DB_query($sql, 1);  // suppress errors, show nice error message instead
        if (!DB_error()) {
            if ($this->getID() == 0) {
                $this->setID(DB_insertID());
            }
            $status = true;
        } else {
            $this->addError($LANG_SHOP['err_dup_iso']);
            SHOP_log($sql, SHOP_LOG_ERROR);
            $status = false;
        }
        return $status;
    }


    /**
     * Enable countries in bulk.
     *
     * @param   array   $countries  Array of country record IDs
     */
    public static function Enable($countries)
    {
        self::bulkEnaDisa($countries, 1);
    }


    /**
     * Disable countries in bulk.
     *
     * @param   array   $countries  Array of country record IDs
     */
    public static function Disable($countries)
    {
        self::bulkEnaDisa($countries, 0);
    }


    /**
     * Country Admin List View.
     *
     * @param   integer $region_id  Optional region ID to limit list
     * @return  string      HTML for the product list.
     */
    public static function adminList($region_id=0)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $region_id = (int)$region_id;
        $sql = "SELECT c.*, r.region_name
            FROM {$_TABLES['shop.countries']} c
            LEFT JOIN {$_TABLES['shop.regions']} r
            ON c.region_id = r.region_id";
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => 'ID',
                'field' => 'country_id',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'country_name',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['region'],
                'field' => 'region_name',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['alpha2'],
                'field' => 'alpha2',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['dial_code'],
                'field' => 'dial_code',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'country_enabled',
                'sort'  => true,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'alpha2',
            'direction' => 'asc',
        );

        $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/regions.php?editcountry=0',
            'style' => 'success',
        ) );

        $query_arr = array(
            'table' => 'shop.countries',
            'sql' => $sql,
            'query_fields' => array('alpha2', 'country_name'),
            'default_filter' => $region_id > 0 ? "WHERE c.region_id=$region_id" : 'WHERE 1=1',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/regions.php?countries=x&region_id=' . $region_id,
        );

        $T = new Template('admin');
        $T->set_file(array(
            'filter' => 'sel_region.thtml',
        ) );
        $T->set_var(array(
            'lang_regiontype' => $LANG_SHOP['region'],
            'fld_name' => 'region_id',
            'onchange_url' => SHOP_ADMIN_URL . '/regions.php?countries&amp;region_id=',
            'option_list' => COM_optionList(
                $_TABLES['shop.regions'], 'region_id,region_name', $region_id, 1
            ),
        ) );
        $T->parse('output', 'filter');
        $filter = $T->finish($T->get_var('output'));
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_countrylist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '',
            self::getAdminListOptions(),
            ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the country admin list.
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
        case 'edit':
            $retval = FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . '/regions.php?editcountry=' . $A['country_id'],
            ) );
            break;

        case 'country_enabled':
            $retval .= FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['country_id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['country_id']}','country_enabled','country');",
            ) );
            break;

        case 'country_name':
            $retval .= COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/regions.php?states&country_id=' . $A['country_id']
            );
            break;

        case 'region_name':
            $retval .= COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/regions.php?countries&region_id=' . $A['region_id']
            );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
