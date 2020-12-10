<?php
/**
 * Class to manage order workflows.
 * Workflows are the steps that a buyer goes through during the purchase process.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for workflow items.
 * Workflows are defined in the database and can be re-ordered and
 * individually enabled or disabled. The workflows determine which screens
 * are displayed during checkout and in what order they appear.
 * @package shop
 */
class Workflow
{
    /** Indicate that this workflow is disabled.
     * @const integer */
    const DISABLED = 0;

    /** Indicate that this workflow is required for physical items.
     * @const integer */
    const REQ_PHYSICAL = 1;

    /** Indicate that this workflow is required for virtual items.
     * @const integer */
    const REQ_VIRTUAL = 2;   // unused placeholder

    /** Indicate that this workflow is required for all items.
     * @const integer */
    const REQ_ALL = 3;

    /** Database table name.
     * @var string */
    protected static $TABLE = 'shop.workflows';

    /** Workflow Name.
     * @var string */
    private $wf_name;

    /** Database ID of the workflow record.
     * @var integer */
    public $wf_id;

    /** Flag to indicate that the workflow is enabled.
     * @var boolean */
    public $enabled;


    /**
     * Constructor.
     * Initializes the array of workflows.
     *
     * @param   array   $A  Record array, form or DB
     */
    public function __construct($A = array())
    {
        if (!empty($A)) {
            $this->wf_name = $A['wf_name'];
            $this->enabled = (int)$A['enabled'];
            $this->wf_id = (int)$A['id'];
        }
    }


    /**
     * Load the workflows into the global workflow array.
     */
    public static function Load()
    {
        global $_TABLES, $_SHOP_CONF;

        if (!isset($_SHOP_CONF['workflows'])) {
            $_SHOP_CONF['workflows'] = array();
            $sql = "SELECT wf_name
                    FROM {$_TABLES[self::$TABLE]}
                    WHERE enabled > 0
                    ORDER BY id ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $_SHOP_CONF['workflows'][] = $A['wf_name'];
            }
        }
    }


    /**
     * Get all workflow items, in order of processing.
     * If a cart is supplied, get the appropriate enabled workflows based
     * on the cart contents.
     * If the cart is NULL, get all workflows.
     *
     * @param   object  $Cart   Shopping cart object.
     * @return  array   Array of workflow names
     */
    public static function getAll($Cart = NULL)
    {
        global $_TABLES;

        if ($Cart) {
            $statuses = array(self::REQ_ALL, self::REQ_VIRTUAL);
            if ($Cart->hasPhysical()) $statuses[] = self::REQ_PHYSICAL;
            $statuslist = implode(',', $statuses);
            $where = " WHERE enabled IN ($statuslist)";
        } else {
            $where = '';
            $statuslist = '0';
        }
        $cache_key = 'workflows_enabled_' . $statuslist;
        $workflows = Cache::get($cache_key);
        if (!$workflows) {
            $workflows = array();
            $sql = "SELECT * FROM {$_TABLES[self::$TABLE]}
                $where
                ORDER BY id ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $workflows[] = new self($A);
            }
            Cache::set($cache_key, $workflows, 'workflows');
        }
        return $workflows;
    }


    /**
     * Get the workflow name.
     *
     * @return  string  Workflow name
     */
    public function getName()
    {
        return $this->wf_name;
    }


    /**
     * Get the friendly title of the workflow.
     *
     * @return  string  Workflow title
     */
    public function getTitle()
    {
        global $LANG_SHOP;

        if (isset($LANG_SHOP[$this->wf_name])) {
            return $LANG_SHOP[$this->wf_name];
        } else {
            return $this->wf_name;
        }
    }


    /**
     * Determine if this workflow is needed for a given order.
     *
     * @param   object  $Cart   Cart object
     * @return  boolean     True if workflow is required, False if not
     */
    public function isNeeded($Cart)
    {
        switch ($this->wf_name) {
        case 'addresses':
            $retval = $Cart->requiresBillto() || $Cart->requiresShipto();
            break;
        case 'shipping':
            $retval = $Cart->requiresShipto();
            break;
        case 'viewcart':
        case 'payment':
        case 'confirm':
        default:
            $retval = true;
            break;
        }
        return $retval;
    }


    /**
     * Check if this workflow has been satisfied based on the cart contents.
     * Verifies that each required field from the workflow's page has been
     * satisfied.
     *
     * @param   object  $Cart   Shopping cart/Order object
     * @return  boolean     True if the workflow is complete, False if not
     */
    public function isSatisfied($Cart)
    {
        // If a workflow is not required at all, consider it satisfied.
        if (!$this->isNeeded($Cart)) {
            return true;
        }

        switch ($this->wf_name) {
        case 'viewcart':
            $status = true;
            break;
        case 'addresses':
            $billto = !$Cart->requiresBillto() || (
                $Cart->getBillto()->getID() == 0 &&
                $Cart->getBillto()->isValid() == ''
            );
            $shipto = !$Cart->requiresShipto() || (
                    $Cart->getShipto()->getID() > 0 &&
                    $Cart->getShipto()->isValid() == ''
            );
            $email = !empty($Cart->getBuyerEmail());
            $status = ($billto && $shipto && $email);
            break;
        case 'billto':
            $status = !$Cart->requiresBillto() || $Cart->getBillto()->isValid() == '';
            break;
        case 'shipto':
            $status = !$Cart->requiresShipto() || $Cart->getShipto()->isValid() == '';
            break;
        case 'shipping':
            $status = !$Cart->requiresShipto() || $Cart->getShipperID() > 0;
            break;
        case 'payment':
            $GW = Gateway::getInstance($Cart->getPmtMethod());
            $status = $GW->hasAccess($Cart->getTotal());
            break;
        default:
            $status = false;
            break;
        }
        return $status;
    }


    /**
     * Finds the next used workflow.
     * Used to step through all workflow steps even if subsequent flows have
     * been satisfied.
     *
     * @param   object  $Cart   Cart object, to see which flows are needed.
     * @return  string      Workflow name
     */
    /*public static function findNextStep($Cart, $current_name)
    {
        $Flows = self::getAll();
        foreach ($Flows as $Flow) {
            if (
}*/


    /**
     * Finds the first unsatisfied workflow.
     *
     * @param   object  $Cart   Cart object to pass to isSatisfied()
     * @return  string      Name of required workflow
     */
    public static function findFirstNeeded($Cart)
    {
        $retval = 'confirm';
        $Flows = self::getAll();
        foreach ($Flows as $Flow) {
            if (!$Flow->isSatisfied($Cart)) {
                $retval = $Flow->getName();
                break;
            }
        }
        return $retval;
    }


    /**
     * Get an instance of a workflow step.
     *
     * @uses    self::getall() to take advantage of caching
     * @param   integer $id     Workflow record ID
     * @return  object          Workflow object, or NULL if not defined/disabled
     */
    public static function getInstance($id)
    {
        global $_TABLES;

        if (is_numeric($id)) {
            $key_fld = 'wf_id';
        } else {
            $key_fld = 'wf_name';
        }
        $workflows = self::getAll();
        foreach ($workflows as $wf) {
            if ($wf->$key_fld == $id) {
                return $wf;
            }
        }
        return NULL;
    }


    /**
     * Sets the "enabled" field to the specified value.
     *
     * @param   integer $id         ID number of element to modify
     * @param   string  $field      Database fieldname to change
     * @param   integer $newvalue   New value to set
     * @return  integer     New value, or old value upon failure
     */
    public static function setValue($id, $field, $newvalue)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id < 1) {
            return -1;
        }
        $field = DB_escapeString($field);

        // Determing the new value (opposite the old)
        $newvalue = (int)$newvalue;

        $sql = "UPDATE {$_TABLES[self::$TABLE]}
                SET $field = $newvalue
                WHERE id='$id'";
        DB_query($sql, 1);
        if (!DB_error()) {
            Cache::clear('workflows');
            return $newvalue;
        } else {
            SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
            return -1;
        }
    }


    /**
     * Get the next view in the workflow to be displayed.
     * This function receives the name of the current view, then looks
     * in it's array of views to return the next in line.
     * Workflows that are not needed, e.g. `addresses` if there are no
     * physical items, are skipped.
     *
     * @param   string  $currview   Current view
     * @return  string              Next view in line
     */
    public static function getNextView($currview = '', $Cart)
    {
        global $_SHOP_CONF;

        // Load the views, if not done already done
        $workflows = self::getAll();

        // Bypass any workflows before the current step
        for ($i = 0; $i < count($workflows) - 1; $i++) {
            if ($workflows[$i]->getName() == $currview) {
                break;
            }
        }

        // Search remaining workflows for the first one still needed.
        $i++;
        for (; $i < count($workflows) -1; $i++) {
            if ($workflows[$i]->isNeeded($Cart)) {
                break;
            }
        }
        return $workflows[$i];

        // If the current view is empty, or isn't part of our array,
        // then set the current key to -1 so we end up returning value 0.
        if ($currview == '') {
            $curr_key = -1;
        } else {
            $curr_key = array_search($currview, $workflows);
            if ($curr_key === false) $curr_key = -1;
        }

        if ($curr_key > -1) {
            Cart::setSession('prevpage', $workflows[$curr_key]);
        }
        if (isset($workflows[$curr_key + 1])) {
            $view = $workflows[$curr_key + 1];
        } else {
            $view = 'checkoutcart';
        }
        return $view;
    }


    /**
     * Display the admin list for order workflows.
     *
     * @param   mixed   $item_id    Numeric or string item ID
     * @return  string      Display HTML
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

        $sql = "SELECT * FROM {$_TABLES['shop.workflows']}";

        $header_arr = array(
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'wf_name',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['enabled'],
                'field' => 'wf_enabled',
                'sort' => false,
            ),
        );

        $defsort_arr = array(
            'field'     => 'id',
            'direction' => 'ASC',
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $query_arr = array(
            'table' => 'shop.workflows',
            'sql' => $sql,
            'query_fields' => array('wf_name'),
            'default_filter' => '',
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/index.php',
        );

        $display .= "<h2>{$LANG_SHOP['workflows']}</h2>\n";
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_workflowlist',
            array(__CLASS__ , 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the workflow listing.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        $retval = '';

        switch($fieldname) {
        case 'wf_enabled':
            $fieldvalue = $A['enabled'];
            if ($A['can_disable'] == 1) {
                $retval = "<select id=\"sel{$fieldname}{$A['id']}\" name=\"{$fieldname}_sel\" " .
                    "onchange='SHOPupdateSel(this,\"{$A['id']}\",\"enabled\", \"workflow\");'>" . LB;
                foreach ($LANG_SHOP['wf_statuses'] as $val=>$str) {
                    $sel = $fieldvalue == $val ? 'selected="selected"' : '';
                    $retval .= "<option value=\"{$val}\" $sel>{$str}</option>" . LB;
                }
                $retval .= '</select>' . LB;
            } else {
                $retval = $LANG_SHOP['required'];
            }
            break;

        case 'wf_name':
            if (isset($LANG_SHOP[$fieldvalue])) {
                $retval = $LANG_SHOP[$fieldvalue];
            } else {
                $retval = $fieldvalue;
            }
            break;

       default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }

}

?>
