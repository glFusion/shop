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
    use \Shop\Traits\DBO;        // Import database operations

    /** Table name.
     * @var string */
    protected static $TABLE = 'shop.orderstatus';

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
            $this->enabled      = SHOP_getVar($A, 'enabled', 'integer', 0);
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
                    FROM {$_TABLES[self::$TABLE]}
                    WHERE enabled = 1
                    ORDER BY orderby ASC";
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
        $statuses = self::getAll(true);
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

        $T = new Template;
        $T->set_file('ordstat', 'orderstatus.thtml');
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
    public static function XToggle($id, $field, $oldvalue)
    {
        global $_TABLES;

        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $id = (int)$id;
        if ($id < 1)
            return $oldvalue;
        $field = DB_escapeString($field);

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES[self::$TABLE]}
                SET $field = $newvalue
                WHERE id ='$id'";
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
     * @param   string  Name of the status
     * @return  string      Language-specific description
     */
    public static function getDscp($name)
    {
        global $LANG_SHOP;

        return SHOP_getVar($LANG_SHOP['orderstatus'], $name, 'string', $name);
    }


    /**
     * Display the admin list order statuses.
     *
     * @return  string      Display HTML
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

        $sql = "SELECT * FROM {$_TABLES['shop.orderstatus']}";

        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'name',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['notify_buyer'],
                'field' => 'notify_buyer',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['notify_admin'],
                'field' => 'notify_admin',
                'sort'  => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field'     => 'id',
            'direction' => 'ASC',
        );

        $display = COM_startBlock(
            '', '', COM_getBlockTemplate('_admin_block', 'header')
        );

        $query_arr = array(
            'table' => 'shop.orderstatus',
            'sql' => $sql,
            'query_fields' => array('name'),
            'default_filter' => 'WHERE id > 1',
        );

        $text_arr = array(
            'has_extras' => false,
            'has_limit' => true,    // required, or default_filter is ignored
            'form_url' => SHOP_ADMIN_URL . '/index.php',
        );

        $display .= "<h2>{$LANG_SHOP['statuses']}</h2>\n";
        $display .= $LANG_SHOP['admin_hdr_wfstatus'] . "\n";
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_statuslist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the order status listing.
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
        case 'enabled':
        case 'notify_buyer':
        case 'notify_admin':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"{$fieldname}_check\"
                id=\"tog{$fieldname}{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"{$fieldname}\",".
                "\"orderstatus\");' />" . LB;
            break;

        case 'name':
            $retval = \Shop\OrderStatus::getDscp($fieldvalue);
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }

}

?>
