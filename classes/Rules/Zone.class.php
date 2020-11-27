<?php
/**
 * Class to manage zone rules, allowing or denying sales by region.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.0
 * @since       v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Rules;
use Shop\Region;
use Shop\Country;
use Shop\State;
use Shop\Icon;      // for the admin list
use Shop\Template;


/**
 * Class for zone rules.
 * @package shop
 */
class Zone
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Table key for DBO utilities.
     * @var string */
    private static $TABLE = 'shop.zone_rules';

    /** ID Field name for DBO utilities.
     * @var string */
    private static $F_ID = 'rule_id';

    /** Record ID of the rule.
     * @var integer */
    private $rule_id = 0;

    /** Rule name.
     * @var string */
    private $rule_name = '';

    /** Rule description.
     * @var string */
    private $rule_dscp = '';

    /** Indicate wheter rule is `allow` or `deny`.
     * @var boolean */
    private $allow = 0;

    /** Regions affected by the rule.
     * @var array */
    private $regions = array();

    /** Countries affected by the rule.
     * If not empty, the regions are ignored.
     * @var array */
    private $countries = array();

    /** States affected by the rule.
     * If not empty, the countries are ignored.
     * @var array */
    private $states = array();

    /** Flag to indicate that this rule is active.
     * @var boolean */
    private $enabled = 1;


    /**
     * Load the rule object with values from the database.
     *
     * @param   array   $A      Rule record from the database
     */
    public function __construct($A = NULL)
    {
        global $_TABLES, $LANG_SHOP;

        if (is_array($A)) {
            $this->rule_id = (int)$A['rule_id'];
            $this->rule_name = $A['rule_name'];
            $this->rule_dscp = $A['rule_dscp'];
            $this->regions = @json_decode($A['regions'], true);
            if (!is_array($this->regions)) {
                $this->regions = array();
            }
            $this->countries = @json_decode($A['countries'], true);
            if (!is_array($this->countries)) {
                $this->countries = array();
            }
            $this->states = @json_decode($A['states'], true);
            if (!is_array($this->states)) {
                $this->states = array();
            }
            $this->allow = $A['allow'] ? 1 : 0;
            $this->enabled = $A['enabled'] ? 1 : 0;
        } else {
            $this->rule_id = 0;
        }
    }


    /**
     * Get all rule records from the database.
     *
     * @return  array       Array of Rule objects.
     */
    public static function getAll()
    {
        global $_TABLES;

        static $Rules = NULL;
        if ($Rules === NULL) {
            $sql = "SELECT * FROM {$_TABLES['shop.zone_rules']}
                ORDER BY rule_id ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $Rules[$A['rule_id']] = new self($A);
            }
        }
        return $Rules;
    }


    /**
     * Read a rule from the database and load the local values.
     *
     * @param   integer $rule_id    Rule record ID
     * @return  object  $this
     */
    public static function getInstance($rule_id)
    {
        global $_TABLES;

        $rule_id = (int)$rule_id;
        $sql = "SELECT * FROM {$_TABLES['shop.zone_rules']}
            WHERE rule_id = $rule_id
            LIMIT 1";
        $res = DB_query($sql);
        if ($res && DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $retval = new self($A);
        } else {
            $retval = new self;
        }
        return $retval;
    }


    /**
     * Find the applicable zone rule for a product.
     *
     * @param   object  $P  Product object
     * @return  object      Applicable Zone Rule object
     */
    public static function findRule($P)
    {
        $rule_id = 0;
        if ($P->getRuleID() > 0) {
            $rule_id = $P->getRuleID();
        } else {
            $cats = array();
            foreach ($P->getCategories() as $Cat) {
                $cats = array_merge($cats, $Cat->getPath(false));
            }
            $cats = array_reverse($cats);
            foreach ($cats as $Cat) {
                if ($Cat->getRuleID() > 0) {
                    $rule_id = $Cat->getRuleID();
                    break;
                }
            }
        }
        return self::getInstance($rule_id);
    }


    /**
     * Get the rule ID.
     *
     * @return  integer     Rule record ID
     */
    public function getID()
    {
        return (int)$this->rule_id;
    }


    /**
     * Get the rule name/description
     *
     * @return  string      Name/description of rule
     */
    public function getDscp()
    {
        return $this->rule_dscp == '' ? $this->rule_name : $this->rule_dscp;
    }


    /**
     * Check whether sales are allowed to a region based on this rule.
     *
     * @param   object  $Addr   Address object
     * @return  boolean     True if sales are allowed, False if not
     */
    public function isOK($Addr)
    {
        // If there is no actual rule set, or the rule is disabled, return true
        if ($this->rule_id == 0 || !$this->enabled) {
            return true;
        }

        $State = State::getInstance($Addr);
        $state_id = $State->getID();
        $country_id = $State->getCountryID();
        $region_id = Country::getInstance($Addr->getCountry())->getRegionID();

        // Check if the region, country and country-state is found, in that order
        $apply = in_array($region_id, $this->regions) ||
            in_array($country_id, $this->countries) ||
            in_array($state_id, $this->states);
        return $this->allow ? $apply : !$apply;
    }


    /**
     * Merge values from a new array of regions into the rule variable.
     * Merges the values, sorts by value and strips duplicates.
     *
     * @param   array   $arr1   Original array, e.g. local property
     * @param   array   $arr2   Array to be merged in
     * @return  array       Resulting merged array
     */
    private static function _mergeVals($arr1, $arr2)
    {
        $arr1 = array_merge($arr1, $arr2);
        asort($arr1);
        $arr1 = array_unique($arr1);
        return $arr1;
    }


    /**
     * Add a Region, Country or State to the rule.
     *
     * @param   string  $type   Type of record ID (region, country or state)
     * @param   array   $vals   Record IDs to be added to the local property
     * @return  object  $this
     */
    public function add($type, $vals)
    {
        // Ensure that $vals is an array
        if (!is_array($vals) || empty($vals)) {
            $vals = array();
        }
        // Ensure the array contains only integers
        foreach ($vals as $key=>$val) {
            $vals[$key] = (int)$val;
        }
        switch ($type) {
        case 'region':
            $this->regions = self::_mergeVals($this->regions, $vals);
            break;
        case 'country':
            $this->countries = self::_mergeVals($this->countries, $vals);
            break;
        case 'state':
            $this->states = self::_mergeVals($this->states, $vals);
            break;
        }
        return $this;
    }


    /**
     * Delete some region IDs from the rule.
     *
     * @param   string  $type   Type of record ID (region, country or state)
     * @param   array   $vals   Record IDs to be removed from the local property
     * @return  object  $this
     */
    public function del($type, $vals)
    {
        // Ensure that $vals is an array
        if (!is_array($vals) || empty($vals)) {
            $vals = array();
        }
        if (!empty($vals)) {
            switch ($type) {
            case 'region':
                $this->regions = array_diff($this->regions, $vals);
                break;
            case 'country':
                $this->countries = array_diff($this->countries, $vals);
                break;
            case 'state':
                $this->states = array_diff($this->states, $vals);
                break;
            }
        }
        return $this;
    }


    /**
     * Delete the entire rule.
     *
     * @param   integer $rule_id    Rule record ID
     */
    public static function deleteRule($rule_id)
    {
        global $_TABLES;

        $rule_id = (int)$rule_id;
        DB_delete($_TABLES['shop.zone_rules'], 'rule_id', $rule_id);
    }


    /**
     * Get an option list of rules.
     * Used to add regions from the region admin lists.
     *
     * @param   integer $sel    Currently-selected option ID
     * @return  string      `<option>` tags for the selection list
     */
    public static function optionList($sel=0)
    {
        global $_TABLES;

        return COM_optionList(
            $_TABLES['shop.zone_rules'],
            'rule_id,rule_name',
            $sel,
            1
        );
    }


    /**
     * Save the current values to the database.
     *
     * @param  array   $A      Optional array of values from $_POST
     * @return boolean         True if no errors, False otherwise
     */
    public function Save($A = NULL)
    {
        global $_TABLES, $_SHOP_CONF;

        if (is_array($A)) {
            $this->rule_name = $A['rule_name'];
            $this->rule_dscp = $A['rule_dscp'];
            $this->allow = (int)$A['allow'];
            $this->enabled = isset($A['enabled']) ? 1 : 0;
        }

        if ($this->rule_id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['shop.zone_rules']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.zone_rules']} SET ";
            $sql3 = " WHERE rule_id='{$this->rule_id}'";
        }
        $sql2 = "rule_name = '" . DB_escapeString($this->rule_name) . "',
            rule_dscp = '" . DB_escapeString($this->rule_dscp) . "',
            allow = " . (int)$this->allow . ",
            enabled = " . (int)$this->enabled . ",
            regions = '" . DB_escapeString(json_encode($this->regions)) . "',
            countries = '" . DB_escapeString(json_encode($this->countries)) . "',
            states = '" . DB_escapeString(json_encode($this->states)) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        //SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->rule_id == 0) {
                $this->rule_id = DB_insertID();
            }
            return true;
        } else {
            return false;
        }
    }


    /**
     * Admin List View.
     *
     * @return  string      HTML for the attribute list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $sql = "SELECT * FROM {$_TABLES['shop.zone_rules']}";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'rule_id',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'rule_name',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['type'],
                'field' => 'allow',
                'sort' => false,
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => 'false',
                'align' => 'center',
            ),
        );

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_rule'],
            SHOP_ADMIN_URL . '/rules.php?rule_edit=0',
            array(
                'style' => 'float:left;',
                'class' => 'uk-button uk-button-success',
            )
        );
        $text_arr = array(
            'form_url' => SHOP_ADMIN_URL . '/rules.php',
        );
        $query_arr = array(
            'table' => 'shop.zone_rules',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );
        $defsort_arr = array(
            'field' => 'rule_id',
            'direction' => 'ASC',
        );
        $filter = array();
        $extra = array(
            'count' => DB_count($_TABLES[static::$TABLE]),
        );
        $options = array(
            'chkdelete' => true,
            'chkfield' => 'rule_id',
        );
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_rule_list',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, $extra, $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the attribute list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra information passed in verbatim
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra=array())
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array('title' => $LANG_ADMIN['edit'])),
                SHOP_ADMIN_URL . "/rules.php?rule_edit={$A['rule_id']}"
            );
            break;

        case 'allow':
            $retval = $fieldvalue ? 'Allow' : 'Deny';
            break;

        case 'delete':
            $retval .= COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/rules.php?rule_del=' . $A['rule_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
                $tip = $LANG_SHOP['ck_to_disable'];
            } else {
                $switch = '';
                $enabled = 0;
                $tip = $LANG_SHOP['ck_to_enable'];
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                data-uk-tooltip
                id=\"togenabled{$A['rule_id']}\"
                title=\"$tip\"
                onclick='SHOP_toggle(this,\"{$A['rule_id']}\",\"{$fieldname}\",".
                "\"zone_rule\");' />" . LB;
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }


    /**
     * Create or edit a rule.
     * Editing allows for removing region IDs only.
     *
     * @return  string      Rule edit form
     */
    public function Edit()
    {
        global $LANG_SHOP;

        $T = new Template;
        $T->set_file(array(
            'form'  => 'rule_edit.thtml',
            'tips'  => 'tooltipster.thtml',
        ) );
        $T->set_var(array(
            'rule_id'   => $this->rule_id,
            'rule_name' => $this->rule_name,
            'rule_dscp' => $this->rule_dscp,
            'type_sel' . $this->allow => 'checked="checked"',
            'ena_chk'   => $this->enabled ? 'checked="checked"' : '',
            'doc_url'   => SHOP_getDocURL('zone_rules'),
            'have_regions' => count($this->regions),
            'have_countries' => count($this->countries),
            'have_states' => count($this->states),
            'lang_no_regions' => sprintf(
                $LANG_SHOP['none_defined'],
                $LANG_SHOP['regions']
            ),
            'lang_no_countries' => sprintf(
                $LANG_SHOP['none_defined'],
                $LANG_SHOP['countries']
            ),

            'lang_no_states' => sprintf(
                $LANG_SHOP['none_defined'],
                $LANG_SHOP['states']
            ),
        ) );
        $T->set_block('form', 'regionBlk', 'RB');
        foreach ($this->regions as $id) {
            $Obj = Region::getInstance($id);
            $T->set_var(array(
                'id'    => $id,
                'name'  => $Obj->getName(),
            ) );
            $T->parse('RB', 'regionBlk', true);
        }

        $T->set_block('form', 'countryBlk', 'CB');
        foreach ($this->countries as $id) {
            $Obj = Country::getInstance($id);
            $T->set_var(array(
                'id'    => $id,
                'name'  => $Obj->getName(),
            ) );
            $T->parse('CB', 'countryBlk', true);
        }

        $T->set_block('form', 'stateBlk', 'SB');
        foreach ($this->states as $id) {
            $Obj = State::getInstance($id);
            $T->set_var(array(
                'id'    => $id,
                'name'  => $Obj->getName(),
            ) );
            $T->parse('SB', 'stateBlk', true);
        }
        $T->parse('tooltipster_js', 'tips');
        return $T->parse('output', 'form');
    }

}

?>
