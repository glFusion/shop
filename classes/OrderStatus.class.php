<?php
/**
 * Class to manage order processing statuses.
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
 * Class for order processing workflow items.
 * Order statuses are defined in the database and can be re-ordered and
 * individually enabled or disabled.
 * @package shop
 */
class OrderStatus extends Workflow
{
    /** Table name.
     * @var string */
    public static $table = 'shop.orderstatus';

    /** Status Name.
     * @var string */
    private $name;

    /** Enabled flag.
     * @var integer */
    public $enabled;

    /** True to notify the buyer when an order changes to this status.
     * @var boolean */
    private $notify_buyer;

    /** True to notify the administrator when an order changes to this status.
     * @var boolean */
    private $notify_admin;

    /**
     * Constructor.
     * Initializes the array of orderstatus.
     *
     * @see     self::getAll()
     * @param   array   $A  Array of data from the DB
     */
    public function __construct($A=array())
    {
        if (is_array($A)) {
            $this->name         = SHOP_getVar($A, 'name', 'string', 'undefined');
            $this->enabled      = SHOP_getVar($A, 'enabled', 'integer', 1);
            $this->notify_buyer = SHOP_getVar($A, 'notify_buyer', 'integer', 1);
            $this->notify_admin = SHOP_getVar($A, 'notify_admin', 'integer', 1);
        } else {
            $this->name         = 'undefined';
            $this->enabled      = 0;
            $this->notify_buyer = 0;
            $this->notify_admin = 0;
        }
    }


    /**
     * Get all order status objects into an array.
     *
     * @param   object  $Cart   Not used, for compatibility with Workflow::getAll()
     */
    public static function getAll($Cart=NULL)
    {
        global $_TABLES;
        static $statuses = NULL;

        if ($statuses === NULL) {
            $statuses = array();
            $sql = "SELECT *
                    FROM {$_TABLES[self::$table]}
                    WHERE enabled = 1
                    ORDER BY id ASC";
            //echo $sql;die;
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $statuses[$A['name']] = new self($A);
            }
        }
        return $statuses;
    }


    /**
     * Get a single status instance.
     *
     * @param   string  Name of status to get
     * @return  array   Array of status info
     */
    public static function getInstance($name)
    {
        $statuses = self::getAll();
        if (isset($statuses[$name])) {
            return $statuses[$name];
        } else {
            return new self();
        }
    }


    /**
     * Creates the complete selection HTML for order status updates.
     *
     * @param   string  $order_id   ID of order being edited
     * @param   integer $showlog    1 to add to the onscreen log, 0 to not
     * @param   string  $selected   Current order status
     * @return  string      HTML for select block
     */
    public static function Selection($order_id, $showlog=0, $selected = '')
    {
        global $LANG_SHOP;

        $T = SHOP_getTemplate('orderstatus', 'ordstat');
        $T->set_var(array(
            'order_id'  => $order_id,
            'oldvalue'  => $selected,
            'showlog'   => $showlog == 1 ? 1 : 0,
        ) );
        $T->set_block('ordstat', 'StatusSelect', 'Sel');
        foreach (self::getAll() as $key => $data) {
            if (!$data->enabled) continue;
            $T->set_var(array(
                'selected' => $key == $selected ?
                                'selected="selected"' : '',
                'stat_key' => $key,
                'stat_descr' => self::getDscp($key),
            ) );
            $T->parse('Sel', 'StatusSelect', true);
        }
        $T->parse('output', 'ordstat');
        return $T->finish ($T->get_var('output'));
    }


    /**
     * Find out whether this status requires notification to the buyer.
     *
     * @return  boolean     True or False
     */
    public function notifyBuyer()
    {
        return $this->notify_buyer == 1 ? true : false;
    }


    /**
     * Find out whether this status requires notification to the administrator
     *
     * @return  boolean     True or False
     */
    public function notifyAdmin()
    {
        return $this->notify_admin == 1 ? true : false;
    }


    /**
     * Toggles a DB field from the given value to the opposite.
     *
     * @param   integer $id         ID number of element to modify
     * @param   string  $field      Database fieldname to change
     * @param   integer $oldvalue   Original value to change
     * @return  integer     New value, or old value upon failure
     */
    public static function Toggle($id, $field, $oldvalue)
    {
        global $_TABLES;

        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $id = (int)$id;
        if ($id < 1)
            return $oldvalue;
        $field = DB_escapeString($field);

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES[self::$table]}
                SET $field = $newvalue
                WHERE id='$id'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (!DB_error()) {
            return $newvalue;
        } else {
            SHOP_log("OrderStatus::Toggle() SQL error: $sql", SHOP_LOG_ERROR);
            return $oldvalue;
        }
    }


    /**
     * Get the name of the order status from the private variable
     *
     * @return  string      Name value
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Get the language string for the description, or the name if not found.
     *
     * @return  string      Language-specific description
     */
    public static function getDscp($name)
    {
        global $LANG_SHOP;

        return SHOP_getVar($LANG_SHOP['orderstatus'], $name, 'string', $name);
    }

}   // class OrderStatus

?>
