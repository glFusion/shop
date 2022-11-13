<?php
/**
 * Class to manage order processing statuses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Shop\FieldList;
use Shop\Template;
use Shop\Config;


/**
 * Class for order processing workflow items.
 * Order statuses are defined in the database and can be re-ordered and
 * individually enabled or disabled.
 * @package shop
 */
class OrderStatus
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Order is still in the shopping-cart phase.
     */
    public const CART = 'cart';

    /** Indicates the order is open. Open orders may be updated.
     */
    public const PENDING = 'pending';

    /** Order has been invoiced and is awaiting payment.
     */
    public const INVOICED = 'invoiced';

    /** Order is in-process.
     */
    public const PROCESSING = 'processing';

    /** Order has been shipped complete.
     */
    public const SHIPPED = 'shipped';

    /** Order has been refunded.
     */
    public const REFUNDED = 'refunded';

    /** Order is complete and paid. No further action needed.
     */
    public const CLOSED = 'closed';

    /** Payment received.
     * Not really an order status, but may be used for logging.
     */
    public const PAID = 'paid';

    /** Order was cancelled.
     */
    public const CANCELED = 'canceled';

    /** Order is archived.
     * One use of this is after anonymous order data is redacted.
     */
    public const ARCHIVED = 'archived';

    /** Table name.
     * @var string */
    protected static $TABLE = 'shop.orderstatus';

    /** Record ID.
     * @var integer */
    private $id = 0;

    /** Status Name.
     * @var string */
    private $name = '';

    /** Enabled flag.
     * @var integer */
    public $enabled = 1;

    /** True to notify the buyer when an order changes to this status.
     * @var boolean */
    private $notify_buyer = 0;

    /** True to notify the administrator when an order changes to this status.
     * @var boolean */
    private $notify_admin = 0;

    /** Flag indicating that this status represents a valid order.
     * Canceled, Refunded mark an order as "invalid" for example.
     * @var boolean */
    private $order_valid = 1;

    /** Flag indicating that this order is closed and may be archived.
     * @var boolean */
    private $order_closed = 0;

    /** Flag indicating that the order can be viewed by the customer.
     * Also will appear in the customer's "My Account" order list.
     * @var boolean */
    private $cust_viewable = 1;

    /** Flag indicating that this status is eligible for affiliate payments.
     * @var boolean */
    private $aff_eligible = 0;


    /**
     * Constructor.
     * Initializes the array of orderstatus.
     *
     * @see     self::getAll()
     * @param   array   $A  Array of data from the DB
     */
    public function __construct(?array $A=NULL)
    {
        if (is_array($A)) {
            $this->setVars(new DataArray($A));
        }
    }


    /**
     * Set the record variables into the array.
     *
     * @param   DataArray   $A  Array from DB or $_POST
     * @return  object  $this
     */
    public function setVars(DataArray $A) : self
    {
        $this->id           = $A->getInt('id');
        $this->name         = $A->getString('name', 'undefined');
        $this->enabled      = $A->getInt('enabled');
        $this->notify_buyer = $A->getInt('notify_buyer');
        $this->notify_admin = $A->getInt('notify_admin');
        $this->order_valid  = $A->getInt('order_valid');
        $this->aff_eligible = $A->getInt('aff_eligible');
        return $this;
    }


    /**
     * Get a single status by its record ID.
     * Normally used when editing and updating.
     *
     * @param   integer $id     Record ID
     * @return  object      OrderStatus object
     */
    public static function getById(int $id) : self
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES[self::$TABLE]} WHERE id = ?",
                array($id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        $retval = new self;
        if (is_array($data)) {
            $retval->setVars(new DataArray($data));
        }
        return $retval;
    }


    /**
     * Get all order status objects into an array.
     *
     * @param   object  $Cart   Not used, for compatibility with Workflow::getAll()
     */
    public static function getAll()
    {
        global $_TABLES;
        static $statuses = NULL;

        if ($statuses === NULL) {
            $statuses = array();
            $db = Database::getInstance();
            try {
                $data = $db->conn->executeQuery(
                    "SELECT * FROM {$_TABLES[self::$TABLE]}
                    WHERE enabled = 1
                    ORDER BY orderby ASC"
                )->fetchAllAssociative();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $data = false;
            }
            if (is_array($data)) {
                foreach ($data as $A) {
                    $statuses[$A['name']] = new self($A);
                }
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
    public static function Selection(string $order_id, int $showlog=0, string $selected = '') : string
    {
        $options = array();
        foreach (self::getAll() as $key => $data) {
            if (!$data->enabled) continue;
            $options[self::getDscp($key)] = array(
                'selected' => $key == $selected,
                'value' => $key,
            );
        }
        $status_selection = FieldList::select(array(
            'name' => 'newstatus[' . $order_id . ']',
            'id' => 'statSelect_' . $order_id,
            'onchange' => "SHOP_ordShowStatSubmit('{$order_id}', SHOP_getStatus('{$order_id}'), this.value);",
            'options' => $options,
        ) );

        $T = new Template;
        $T->set_file('ordstat', 'orderstatus.thtml');
        $T->set_var(array(
            'order_id'  => $order_id,
            'oldvalue'  => $selected,
            'showlog'   => $showlog == 1 ? 1 : 0,
            'status_select' => $status_selection,
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

        return SHOP_getVar($LANG_SHOP['orderstatus'], $name, 'string', ucfirst($name));
    }


    private static function _getByAttribute(?string $column=NULL, ?int $status=NULL) : array
    {
        global $_TABLES;

        $retval = array();
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$_TABLES['shop.orderstatus']}
            WHERE enabled = ?";
        $values = array(1);
        $types = array(Database::INTEGER);
        if ($column !== NULL && $status !== NULL) {
            $sql .= " AND $column = ?";
            $values[] = $status;
            $types[] = Database::INTEGER;
        }
        try {
            $data = $db->conn->executeQuery($sql, $values, $types)->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $retval[$A['name']] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Get all the statuses that mark an order as "closed".
     *
     * @param   boolean $flag   True for matching statuses, False for nonmatching
     * @return  array   Array of eligible OrderStatus objects
     */
    public static function getOrderClosed(bool $flag=true) : array
    {
        $flag = $flag ? 1 : 0;
        return self::_getByAttribute('order_closed', $flag);
    }


    /**
     * Get all the statuses that mark an order as "valid".
     *
     * @param   boolean $flag   True for matching statuses, False for nonmatching
     * @return  array   Array of eligible OrderStatus objects
     */
    public static function getOrderValid(bool $flag=true) : array
    {
        $flag = $flag ? 1 : 0;
        return self::_getByAttribute('order_valid', $flag);
    }


    /**
     * Get all the statuses that allow affiliate payments.
     *
     * @param   boolean $flag   True for matching statuses, False for nonmatching
     * @return  array   Array of eligible OrderStatus objects
     */
    public static function getAffiliateEligible(bool $flag=true) : array
    {
        $flag = $flag ? 1 : 0;
        return self::_getByAttribute('aff_eligible', $flag);
    }


    /**
     * Get all the statuses that are customer-viewable.
     *
     * @param   boolean $flag   True for matching statuses, False for nonmatching
     * @return  array   Array of eligible OrderStatus objects
     */
    public static function getCustomerViewable(bool $flag=true) : array
    {
        $flag = $flag ? 1 : 0;
        return self::_getByAttribute('cust_viewable', $flag);
    }


    /**
     * Check if this order status allows affiliate payments.
     *
     * @return  boolean     True if eligible, False if not
     */
    public function isAffiliateEligible() : bool
    {
        return $this->aff_eligible != 0;
    }


    /**
     * Check an order status to make sure it's one of the validated ones.
     *
     * @param   string  $status     Order status
     * @return  boolean     True if a validated status, False if not
     */
    public static function checkOrderValid(string $status) : bool
    {
        return array_key_exists($status, self::getOrderValid());
    }


    /**
     * Check an order status to see if it's customer-viewable.
     *
     * @param   string  $status     Order status
     * @return  boolean     True if a viewable status, False if not
     */
    public static function checkCustomerViewable(string $status) : bool
    {
        return array_key_exists($status, self::getCustomerViewable());
    }


    /**
     * Check if a requested status is valid.
     *
     * @param   string  $status     Status to check
     * @return  boolean     True if valid, False if non-existent
     */
    public static function isValid(string $status) : bool
    {
        return array_key_exists($status, self::getAll());
    }


    /**
     * Check if an order can be deleted.
     * Hard-coded to allow only `cart` and `pending` orders to be removed.
     *
     * @param   string  $status     Status to check
     * @return  boolean     True if valid, False if non-existent
     */
    public static function isDeletable(string $status) : bool
    {
        return $status == 'pending' || $status == 'cart';
    }


    /**
     * Display the admin list order statuses.
     *
     * @return  string      Display HTML
     */
    public static function adminList()
    {
        global $_SHOP_CONF, $_TABLES, $LANG_SHOP, $LANG_ADMIN;

        $header_arr = array(
            array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'name',
                'sort'  => false,
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
            array(
                'text'  => $LANG_SHOP['order_valid'],
                'field' => 'order_valid',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['order_closed'],
                'field' => 'order_closed',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['cust_viewable'],
                'field' => 'cust_viewable',
                'sort'  => false,
                'align' => 'center',
            ),
        );
        if (Config::get('aff_enabled')) {
            // Add the affiliate-eligible checkbox if the affiliate
            // system is enabled.
            $header_arr[] = array(
                'text'  => $LANG_SHOP['aff_eligible'],
                'field' => 'aff_eligible',
                'sort'  => false,
                'align' => 'center',
            );
        }

        $defsort_arr = array(
            'field'     => 'id',
            'direction' => 'ASC',
        );
        $display = COM_startBlock(
            '', '', COM_getBlockTemplate('_admin_block', 'header')
        );
        $display .= "<h2>{$LANG_SHOP['statuses']}</h2>\n";
        /*$display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/index.php?editstatus=0',
            'style' => 'success',
        ) );*/
        $query_arr = array(
            'table' => 'shop.orderstatus',
            'sql' => "SELECT * FROM {$_TABLES['shop.orderstatus']}",
            'query_fields' => array('name'),
        );
        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/index.php',
        );

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
        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . '/index.php?editstatus=' . $A['id'],
            ) );
            break;

        case 'enabled':
        case 'notify_buyer':
        case 'notify_admin':
        case 'order_valid':
        case 'order_closed':
        case 'cust_viewable':
        case 'aff_eligible':
            $retval .= FieldList::checkbox(array(
                'name' => "{$fieldname}_check",
                'id' => "tog{$fieldname}{$A['id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['id']}','{$fieldname}','orderstatus');",
            ) );
            break;

        case 'name':
            $retval = self::getDscp($fieldvalue);
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }


    /**
     * Edit or create an order status record.
     *
     * @return  string      HTML for the editing form
     */
    public function edit() : string
    {
        $T = new Template('admin');
        $T->set_file(array(
            'form' => 'orderstatus.thtml',
            'tips' => '../tooltipster.thtml',
        ) );
        $T->set_var(array(
            'os_id' => $this->id,
            'name' => $this->name,
            'old_name' => $this->name,
            'enabled_chk' => $this->enabled ? 'checked="checked"' : '',
            'notify_buyer_chk' => $this->notify_buyer ? 'checked="checked"' : '',
            'notify_admin_chk' => $this->notify_admin ? 'checked="checked"' : '',
            'order_valid_chk' => $this->order_valid ? 'checked="checked"' : '',
            'order_closed_chk' => $this->order_closed ? 'checked="checked"' : '',
            'cust_viewable_chk' => $this->cust_viewable ? 'checked="checked"' : '',
            'aff_eligible_chk' => $this->aff_eligible ? 'checked="checked"' : '',
            'doc_url' => SHOP_getDocUrl('orderstatus_form'),
        ) );
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save the current values to the database.
     *
     * @param   DataArray   $A  Array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save(?DataArray $A=NULL) : bool
    {
        global $_TABLES, $_SHOP_CONF;

        $reorder = false;
        if (!empty($A)) {
            $this->setVars($A);
        }
        $this->name = strtolower($this->name);

        $db = Database::getInstance();
        if ($this->id == 0) {
            // Adding a new record, make sure one doesn't already exist.
            $max = 0;
        } elseif ($this->name == $A['old_name']) {
            // Updating with no name change, one record should exist.
            $max = 1;
        } else {
            // Changing the name, make sure it's not already in use.
            $max = 0;
        }
        $count = $db->getCount(
            $_TABLES['shop.orderstatus'],
            array('name'),
            array($this->name),
            array(Database::STRING)
        );
        if ($count > $max) {
            COM_setMsg('The item code exists', 'error');
            return false;
        }

        if ($this->id > 0) {
            try {
                $db->conn->update(
                    $_TABLES['shop.orderstatus'],
                    array(
                        'name' => $this->name,
                        'notify_buyer' => $this->notify_buyer,
                        'notify_admin' => $this->notify_admin,
                        'order_valid' => $this->order_valid,
                        'aff_eligible' => $this->aff_eligible,
                    ),
                    array('id' => $this->id),
                    array(
                        Database::STRING,
                        Database::INTEGER,
                        Database::INTEGER,
                        Database::INTEGER,
                        Database::INTEGER,
                        Database::INTEGER,
                    )
                );
                $this->id = $db->conn->lastInsertId();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        } else {
            try {
                $db->conn->insert(
                    $_TABLES['shop.orderstatus'],
                    array(
                        'name' => $this->name,
                        'notify_buyer' => $this->notify_buyer,
                        'notify_admin' => $this->notify_admin,
                        'order_valid' => $this->order_valid,
                        'aff_eligible' => $this->aff_eligible,
                    ),
                    array(
                        Database::STRING,
                        Database::INTEGER,
                        Database::INTEGER,
                        Database::INTEGER,
                        Database::INTEGER,
                    )
                );
                $this->id = $db->conn->lastInsertId();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }

}
