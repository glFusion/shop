<?php
/**
 * Class to handle State information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to handle state information.
 * @package shop
 */
class State
{
    /** State DB record ID.
     * @var integer */
    private $state_id;

    /** Country DB record ID.
     * @var integer */
    private $country_id;

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


    /**
     * Create an object and set the variables.
     *
     * 
     */
    public function __construct($A)
    {
        $this->setID($A['state_id'])
            ->setCountryID($A['country_id'])
            ->setISO($A['iso_code'])
            ->setName($A['state_name'])
            ->setEnabled($A['state_enabled']);
    }


    /**
     * Get an instance of a state object.
     *
     * @param   string  $code   State iso_code and country iso_code
     * @return  object  Country object
     */
    public static function getInstance($code)
    {
        global $_TABLES;
        static $instances = array();

        if (isset($instances[$code])) {
            return $instances[$code];
        } else {
            $parts = explode(',', $code);
            if (count($parts) == 2) {
                $s_iso = DB_escapeString($parts[1]);
                $c_iso = DB_escapeString($parts[0]);
                $sql = "SELECT * FROM gl_shop_states st
                    LEFT JOIN gl_shop_countries c
                    ON c.country_id = st.country_id
                    WHERE st.iso_code = '$s_iso'
                    AND c.iso_code = '$c_iso'";
            } else {
                $s_iso = DB_escapeString($parts[0]);
                $c_iso = '';
                $sql = "SELECT * FROM gl_shop_states WHERE iso_code = '$s_iso'";
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
     * Return the DB record ID for the state.
     *
     * @return  integer     Record ID
     */
    public function getCountryID()
    {
        return (int)$this->country_id;
    }


    /**
     * Set the Country record ID.
     * 
     * @param   integer $id     1 or 0, enabled vs disabled
     * @return  object  $this
     */
    private function setEnabled($enabled)
    {
        $this->state_enabled = $enabled == 0 ? 0 : 1;
        return $this;
    }


    public function isEnabled()
    {
        return (
            $this->state_enabled &&
            $this->getCountry()->isEnabled()
        );
    }


    /**
     * Set the Country Name.
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
     * Get data for a state from the static array.
     * Return array with empty values for unknown countries.
     * Returns all countries if no ID is provided.
     *
     * @param   string  $code       Country Code
     * @return  array       Array of state data (name and dialing code)
     */
    public static function getAll($country, $enabled=true)
    {
        global $_TABLES;

        $country = (int)$country;
        $enabled = $enabled ? 1 : 0;
        $cache_key = 'shop.states.' . $country . '_' . $enabled;
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $sql = "SELECT * FROM gl_shop_states
                WHERE country_id = $country";
            if ($enabled) {
                $sql .= " AND state_enabled = 1";
            }
            $sql .= " ORDER BY iso_code ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['iso_code']] = new self($A);
            }
            Cache::set($cache_key, $retval, 'regions', 43200);
        }
        return $retval;
    }


    /**
     * Make a name=>code selection for all states under a country.
     *
     * @param   integer $country    Country ID to limit selection
     * @param   boolean $enable     True to limit to only enabled states
     * @return  array   Array of state_name=>state_code
     */
    public static function makeSelection($country, $enabled=true)
    {
        $C = self::getAll($country, $enabled);
        $retval = array();
        foreach ($C as $code=>$data) {
            $retval[$data->getName()] = $data->getID();
        }
        return $retval;
    }


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $varname    Name of DB field to set
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function Toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        $id = (int)$id;
        switch ($varname) {     // allow only valid field names
        case 'state_enabled':
            // Determing the new value (opposite the old)
            $oldvalue = $oldvalue == 1 ? 1 : 0;
            $newvalue = $oldvalue == 1 ? 0 : 1;

            $sql = "UPDATE {$_TABLES['shop.states']}
                SET $varname=$newvalue
                WHERE state_id=$id";
            // Ignore SQL errors since varname is indeterminate
            DB_query($sql, 1);
            if (DB_error()) {
                SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
                return $oldvalue;
            } else {
                return $newvalue;
            }
        }
    }


    /**
     * State Admin List View.
     *
     * @return  string      HTML for the product list.
     */
    public static function adminList($country_id = 0)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT s.state_id, s.state_name, s.state_enabled, s.iso_code, c.country_name FROM gl_shop_states s
            LEFT JOIN gl_shop_countries c
                ON c.country_id = s.country_id";
        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'state_id',
                'sort'  => true,
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
                'text'  => 'Enabled',
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
            'form_url' => SHOP_ADMIN_URL . '/index.php?stats=x',
        );

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_statelist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', ''
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
        case 'state_enabled':
            if ($fieldvalue == '1') {
                $switch = 'checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                    id=\"togenabled{$A['state_id']}\"
                    onclick='SHOP_toggle(this,\"{$A['state_id']}\",\"state_enabled\",".
                    "\"state\");' />" . LB;
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}

?>
