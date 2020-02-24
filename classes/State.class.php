<?php
/**
 * Class to handle State information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to handle state information.
 * @package shop
 */
class State extends RegionBase
{
    /** Table key.
     * @var string */
    protected static $TABLE = 'shop.states';

    /** Cache tag.
     * @var string */
    protected static $TAG = 'states';

    /** Table type, used to create variable names.
     * .@var string */
    protected static $KEY = 'state';

    /** State DB record ID.
     * @var integer */
    private $state_id;

    /** Country DB record ID.
     * @var integer */
    private $country_id;

    /** Country ISO code.
     * @var string */
    private $country_iso;

    /** Country Name.
     * @var string */
    private $state_name;

    /** Country ISO code.
     * @var string */
    private $iso_code;

    /** Sales are allowed to this state?
     * @var integer */
    private $state_enabled;

    /** Country object.
     * @var object */
    private $Country;

    /** Does this state charge tax on shipping?
     * @var boolean */
    private $tax_shipping = 0;

    /** Does this state charge tax on handling?
     * @var boolean */
    private $tax_handling = 0;


    /**
     * Create an object and set the variables.
     *
     * @param   array   $A  DB record array
     */
    public function __construct($A)
    {
        $this->setID($A['state_id'])
            ->setCountryID($A['country_id'])
            ->setCountryISO($A['country_iso'])
            ->setISO($A['iso_code'])
            ->setName($A['state_name'])
            ->setEnabled($A['state_enabled'])
            ->setTaxHandling($A['tax_handling'])
            ->setTaxShipping($A['tax_shipping']);
    }


    /**
     * Get an instance of a state object.
     * The code may be a combination of country and state ISO, such as
     * `US-CA`, or a DB record ID for the state.
     *
     * @param   mixed   $code    Record ID, Address object or country-state code
     * @return  object  Country object
     */
    public static function getInstance($code)
    {
        global $_TABLES;
        static $instances = array();

        if ($code instanceof Address) {
            $code = $code->getCountry() . '-' . $code->getState();
        }

        if (isset($instances[$code])) {
            return $instances[$code];
        } elseif (is_integer($code)) {
            $sql = "SELECT s.*, c.alpha2 as country_iso
                    FROM {$_TABLES['shop.states']} s
                    LEFT JOIN {$_TABLES['shop.countries']} c
                        ON c.country_id = s.country_id
                    WHERE s.state_id = $code";
        } else {
            $parts = explode('-', $code);
            if (count($parts) == 2) {
                $s_iso = DB_escapeString($parts[1]);
                $c_iso = DB_escapeString($parts[0]);
                $sql = "SELECT s.*, c.alpha2 as country_iso
                    FROM {$_TABLES['shop.states']} s
                    LEFT JOIN {$_TABLES['shop.countries']} c
                    ON c.country_id = s.country_id
                    WHERE s.iso_code = '$s_iso'
                    AND c.alpha2 = '$c_iso'";
            } else {
                // Try with just the state, but this is unpredictable
                $s_iso = DB_escapeString($parts[0]);
                $c_iso = '';
                $sql = "SELECT s.*, c.alpha2 as country_iso
                    FROM {$_TABLES['shop.states']} s
                    LEFT JOIN {$_TABLES['shop.countries']} c
                    ON c.country_id = s.country_id
                    WHERE s.iso_code = '$s_iso'";
            }
        }
        $sql .= ' LIMIT 1';
        $res = DB_query($sql);
        if ($res && DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
        } else {
            $A = array(
                'state_id'    => 0,
                'state_id'     => 0,
                'iso_code'      => '',
                'state_name'  => '',
                'state_enabled' => 0,
            );
        }
        return new self($A);
    }


    /**
     * Set the ISO code.
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
     * Return the ISO code for the state.
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
        $this->state_id = (int)$id;
        return $this;
    }


    /**
     * Return the DB record ID for the state.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->state_id;
    }


    /**
     * Set the Country record ID.
     *
     * @param   integer $id     DB record ID for the parent country
     * @return  object  $this
     */
    private function setCountryID($id)
    {
        $this->country_id = (int)$id;
        return $this;
    }


    /**
     * Set the Country ISO code.
     * This is not set in the DB, just used as needed.
     *
     * @param   string  $iso    Country ISO code
     * @return  object  $this
     */
    private function setCountryISO($iso)
    {
        $this->country_iso = $iso;
        return $this;
    }


    /**
     * Return the DB record ID for the state.
     *
     * @return  integer     Record ID
     */
    public function getCountryID()
    {
        return (int)$this->country_id;
    }


    /**
     * Set the Enabled flag for the state.
     *
     * @param   integer $enabled    Zero to disable, nonzero to enable
     * @return  object  $this
     */
    private function setEnabled($enabled)
    {
        $this->state_enabled = $enabled == 0 ? 0 : 1;
        return $this;
    }


    /**
     * Get the value of the Enabled flag for the state.
     *
     * @return  integer     Zero if disabled, 1 if enabled
     */
    public function getEnabled()
    {
        return $this->state_enabled ? 1 : 0;
    }


    /**
     * Check if the state is enabled.
     * State, Country and Region must be enabled to return true
     *
     * @return  boolean     True if enabled, False if not.
     */
    public function isEnabled()
    {
        return (
            $this->state_enabled &&
            $this->getCountry()->isEnabled()
        );
    }


    /**
     * Set the State full name.
     *
     * @param   string  $name   Name of state
     * @return  object  $this
     */
    private function setName($name)
    {
        $this->state_name = $name;
        return $this;
    }


    /**
     * Return USPS state name by state ISO 3166-1-alpha-2 code.
     * Return empty string for unknown countries.
     *
     * @return  string      Country name, empty string if not found
     */
    public function getName()
    {
        return $this->state_name;
    }


    /**
     * Get the Country object associated with this state.
     *
     * @return  object  Country object
     */
    public function getCountry()
    {
        if ($this->Country === NULL) {
            $this->Country = Country::getInstance($this->getCountryID());
        }
        return $this->Country;
    }


    /**
     * Set the tax_shipping flag.
     *
     * @param   boolean $flag   True to tax shipping, False if not
     * @return  object  $this
     */
    public function setTaxShipping($flag)
    {
        $this->tax_shipping = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if this state charges tax on shipping.
     *
     * @return  integer     1 if tax is charged, 0 if not.
     */
    public function taxesShipping()
    {
        return $this->tax_shipping ? 1 : 0;
    }


    /**
     * Set the tax_handling flag.
     *
     * @param   boolean $flag   True to tax handling, False if not
     * @return  object  $this
     */
    public function setTaxHandling($flag)
    {
        $this->tax_handling = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if this state charges tax on handling charges.
     *
     * @return  integer     1 if tax is charged, 0 if not.
     */
    public function taxesHandling()
    {
        return $this->tax_handling ? 1 : 0;
    }


    /**
     * Get all the state objects as an array.
     *
     * @param   string  $country    ISO code for a country
     * @param   boolean $enabled    True to get only enabled states
     * @return  array       Array of State objects
     */
    public static function getAll($country, $enabled=true)
    {
        global $_TABLES;

        $country = DB_escapeString($country);
        $enabled = $enabled ? 1 : 0;
        $cache_key = 'shop.states.' . $country . '_' . $enabled;
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $sql = "SELECT s.state_name, s.iso_code
                FROM {$_TABLES['shop.states']} s
                LEFT JOIN {$_TABLES['shop.countries']} c
                    ON c.country_id = s.country_id
                LEFT JOIN {$_TABLES['shop.regions']} r
                    ON r.region_id = c.region_id
                WHERE c.alpha2 = '$country'";
            if ($enabled) {
                $sql .= " AND s.state_enabled = 1
                    AND c.country_enabled = 1
                    AND r.region_enabled = 1";
            }
            $sql .= " ORDER BY s.state_name ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['iso_code']] = new self($A);
            }
//            Cache::set($cache_key, $retval, 'regions', 43200);
        }
        return $retval;
    }


    /**
     * Return option list elements to select a state.
     *
     * @param   string  $country    Country code
     * @param   string  $state      Selected state code
     * @param   boolean $enabled    True to return only enabled states
     * @return  string      HTML option elements
     */
    public static function optionList($country='', $state='', $enabled=true)
    {
        $retval = '';
        $arr = self::getAll($country, $enabled);
        foreach ($arr as $iso=>$S) {
            $selected = $state == $iso ? 'selected="selected"' : '';
            $retval .= "<option $selected value=\"$iso\">{$S->getName()}</option>";
        }
        return $retval;
    }


    /**
     * Edit a state record.
     *
     * @return  string      HTML for editing form
     */
    public function Edit()
    {
        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file(array(
            'form' => 'state.thtml',
            'tips' => 'tooltipster.thtml',
        ) );

        $T->set_var(array(
            'state_id'      => $this->getID(),
            'state_name'    => $this->getName(),
            'iso_code'      => $this->getISO(),
            'ena_chk'       => $this->state_enabled ? 'checked="checked"' : '',
            'tx_shp_chk'    => $this->tax_shipping ? 'checked="checked"' : '',
            'tx_hdl_chk'    => $this->tax_handling ? 'checked="checked"' : '',
            'country_options' => Country::optionLIst($this->country_iso, false),
            'doc_url'       => SHOP_getDocUrl('state_form'),
        ) );
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save the state information.
     *
     * @param   array   $A  Optional data array from $_POST
     * @return  boolean     True on success, False on failure
     */
    public function Save($A=NULL)
    {
        global $_TABLES, $LANG_SHOP;

        $this->Country = Country::getInstance($A['country_iso']);
        $country_id = $this->Country->getID();
        if (is_array($A)) {
            $this->setID($A['state_id'])
                ->setCountryID($country_id)
                ->setCountryISO($A['country_iso'])
                ->setISO($A['iso_code'])
                ->setName($A['state_name'])
                ->setEnabled($A['state_enabled']);
        }
        if ($this->getID() > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.states']} SET ";
            $sql3 = " WHERE state_id ='" . $this->getID() . "'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.states']} SET ";
            $sql3 = '';
        }

        $sql2 = "country_id = {$this->getCountryID()},
            iso_code = '" . DB_escapeString($this->getISO()) . "',
            state_name = '" . DB_escapeString($this->getName()) . "',
            state_enabled = {$this->getEnabled()},
            tax_shipping = {$this->taxesShipping()},
            tax_handling = {$this->taxesShipping()}";
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
     * State Admin List View.
     *
     * @param   integer $country_id     Optional country ID to limit list
     * @return  string      HTML for the product list.
     */
    public static function adminList($country_id = 0)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $country_id = (int)$country_id;
        $sql = "SELECT s.state_id, s.state_name, s.state_enabled, s.iso_code,
                s.tax_shipping, s.tax_handling, c.country_name
            FROM {$_TABLES['shop.states']} s
            LEFT JOIN {$_TABLES['shop.countries']} c
                ON c.country_id = s.country_id";
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => 'ID',
                'field' => 'state_id',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => 'State Name',
                'field' => 'state_name',
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
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['tax_shipping'],
                'field' => 'tax_shipping',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['tax_handling'],
                'field' => 'tax_handling',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'state_enabled',
                'sort'  => true,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 's.iso_code',
            'direction' => 'asc',
        );

        $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_state'],
            SHOP_ADMIN_URL . '/index.php?editstate=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );

        $query_arr = array(
            'table' => 'shop.states',
            'sql' => $sql,
            'query_fields' => array('s.iso_code', 's.state_name'),
            'default_filter' => $country_id > 0 ? "WHERE s.country_id=$country_id" : 'WHERE 1=1',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?states=x&country_id=' . (int)$country_id,
        );

        /*$options = array(
            'chkdelete' => 'true',
            'chkfield' => 'state_id',
            'chkname' => 'state_id',
            'chkactions' => self::getBulkActionButtons(),
        );*/

        $filter = $LANG_SHOP['country'] . ': <select name="country_id"
            onchange="javascript: document.location.href=\'' .
                SHOP_ADMIN_URL .
                '/index.php?states&amp;country_id=\'+' .
                'this.options[this.selectedIndex].value">' .
            '<option value="0">' . $LANG_SHOP['all'] . '</option>' . LB .
            COM_optionList(
                $_TABLES['shop.countries'], 'country_id,country_name', $country_id, 1
            ) .
            "</select>" . LB;

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_statelist',
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
     * Get an individual field for the state admin list.
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
            $retval = COM_createLink(
                Icon::getHTML('edit'),
                SHOP_ADMIN_URL . '/index.php?editstate=' . $A['state_id']
            );
            break;

        case 'state_enabled':
        case 'tax_shipping':
        case 'tax_handling':
            if ($fieldvalue == '1') {
                $switch = 'checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                    id=\"togenabled{$A['state_id']}\"
                    onclick='SHOP_toggle(this,\"{$A['state_id']}\",\"{$fieldname}\",".
                    "\"state\");' />" . LB;
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Get the state ISO code from the country ISO and state name.
     *
     * @param   string  $alpha2     2-letter ISO code for the country
     * @param   string  $state_name Full state name
     * @return  string      2-letter ISO code for state
     */
    public static function isoFromName($alpha2, $state_name)
    {
        global $_TABLES;

        $retval = '';
        $alpha2 = DB_escapeString($alpha2);
        $state_name = DB_escapeString($state_name);
        $sql = "SELECT s.iso_code FROM {$_TABLES['shop.states']} s
            LEFT JOIN {$_TABLES['shop.countries']} c
                ON c.country_id = s.country_id
            WHERE c.alpha2='$alpha2' AND  s.state_name='$state_name'";
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            $retval = $A['iso_code'];
        }
        return $retval;
    }

}

?>
