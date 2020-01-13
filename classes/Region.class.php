<?php
/**
 * Class to handle Regions.
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
 * Class to handle country information.
 * @package shop
 */
class Region
{
    /** Region DB record ID.
     * @var integer */
    private $region_id;

    /** Region Name.
     * @var string */
    private $region_name;

    /** Sales are allowed to this region?
     * @var integer */
    private $region_enabled;


    /**
     * Create an object and set the variables.
     *
     * 
     */
    public function __construct($A)
    {
        $this->setID($A['region_id'])
            ->setName($A['region_name'])
            ->setEnabled($A['region_enabled']);
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
            $sql = "SELECT * FROM gl_shop_regions WHERE region_id = " . (int)$code;
            $res = DB_query($sql);
            if ($res && DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
            } else {
                $A = array(
                    'region_id'     => 0,
                    'region_name'  => '',
                    'region_enabled' => 0,
                );
            }
            return new self($A);
        }
    }


    /**
     * Set the record ID.
     * 
     * @param   integer $id     DB record ID
     * @return  object  $this
     */
    private function setID($id)
    {
        $this->region_id = (int)$id;
        return $this;
    }


    /**
     * Return the DB record ID for the country.
     *
     * @return  integer     Record ID
     */
    public function getID()
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
        $this->region_enabled = $enabled == 0 ? 0 : 1;
        return $this;
    }


    public function isEnabled()
    {
        return (int)$this->region_enabled;
    }


    /**
     * Set the Country Name.
     * 
     * @param   string  $name   Name of country
     * @return  object  $this
     */
    private function setName($name)
    {
        $this->region_name = $name;
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
        return $this->data['region_name'];
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
        case 'region_enabled':
            // Determing the new value (opposite the old)
            $oldvalue = $oldvalue == 1 ? 1 : 0;
            $newvalue = $oldvalue == 1 ? 0 : 1;

            $sql = "UPDATE {$_TABLES['shop.regions']}
                SET $varname=$newvalue
                WHERE region_id=$id";
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
     * Region Admin List View.
     *
     * @return  string      HTML for the product list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT * FROM gl_shop_regions";
        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'region_id',
                'sort'  => true,
            ),
            array(
                'text'  => 'Region Name',
                'field' => 'region_name',
                'sort'  => true,
            ),
            array(
                'text'  => 'Enabled',
                'field' => 'region_enabled',
                'sort'  => true,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'region_name',
            'direction' => 'asc',
        );

        $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_region'],
            SHOP_ADMIN_URL . '/index.php?editregion=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );

        $query_arr = array(
            'table' => 'shop.countries',
            'sql' => $sql,
            'query_fields' => array('iso_code', 'country_name'),
            'default_filter' => 'WHERE 1=1',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?countries=x',
        );

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_regionlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the region admin list.
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
        case 'region_enabled':
            if ($fieldvalue == '1') {
                $switch = 'checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                    id=\"togenabled{$A['region_id']}\"
                    onclick='SHOP_toggle(this,\"{$A['region_id']}\",\"region_enabled\",".
                    "\"region\");' />" . LB;
            break;

        case 'region_name':
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . "/index.php?countries&region_id={$A['region_id']}"
            );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


}

?>
