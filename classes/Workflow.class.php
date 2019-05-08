<?php
/**
 * Class to manage order workflows.
 * Workflows are the steps that a buyer goes through during the purchase process.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
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
    static $table = 'shop.workflows';

    /** Workflow Name.
     * @var string */
    public $wf_name;

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
                    FROM {$_TABLES[self::$table]}
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
            $sql = "SELECT * FROM {$_TABLES[self::$table]}
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
     * Check if this workflow has been satisfided based on the cart contents.
     *
     * @param   object  $Cart   Shopping cart/Order object
     * @return  boolean     True if the workflow is complete, False if not
     */
    public function isSatisfied($Cart)
    {
        switch ($this->wf_name) {
        case 'billto':
        case 'shipto':
            $A = $Cart->getAddress($this->wf_name);
            $status = UserInfo::isValidAddress($A) == '' ? true : false;
            break;
        default:
            $status = true;
            break;
        }
        return $status;
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

        $workflows = self::getAll();
        foreach ($workflows as $wf) {
            if ($wf->wf_id == $id) {
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
        if ($id < 1)
            return -1;
        $field = DB_escapeString($field);

        // Determing the new value (opposite the old)
        $newvalue = (int)$newvalue;

        $sql = "UPDATE {$_TABLES[self::$table]}
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
     *
     * @param   string  $currview   Current view
     * @return  string              Next view in line
     */
    public static function getNextView($currview = '')
    {
        global $_SHOP_CONF;

        /** Load the views, if not done already */
        $workflows = self::getAll();

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

}   // class Workflow

?>
