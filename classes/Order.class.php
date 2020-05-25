<?php
/**
 * Order class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Order class.
 * @package shop
 */
class Order
{
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_CLOSED = 'closed';

    /** Session variable name for storing cart info.
     * @var string */
    protected static $session_var = 'glShopCart';

    /** Flag to indicate that administrative actions are being done.
     * @var boolean */
    private $isAdmin = false;

    /** Flag to indicate that this order has been finalized.
     * This is not related to the order status, but only to the current view
     * in the workflow.
     * @var boolean */
    private $isFinalView = false;

    /** Flag to indicate that this is a new record.
     * @var boolean */
    protected $isNew = true;

    /** Miscellaneious information values used by the Cart class.
     * @var array */
    protected $m_info = array();

    /** Flag to indicate that "no shipping" should be set.
     * @deprecated ?
     * @var boolean */
    private $no_shipping = 1;

    /** Address field names.
     * @var array */
    protected $_addr_fields = array(
        'name', 'company', 'address1', 'address2',
        'city', 'state', 'zip', 'country',
    );

    /** OrderItem objects.
     * @var array */
    protected $items = array();

    /** Order item total, excluding discount codes.
      @var float */
    protected $gross_items = 0;

    /** Order final total, incl. shipping, handling, etc.
     * @var float */
    protected $total = 0;

    /** Number of taxable line items on the order.
     * @var integer */
    protected $tax_items = 0;

    /** Sales tax rate for the order.
     * @var float */
    protected $tax_rate = 0;
    /** Total tax charged on the order.
     * @var float */
    protected $tax = 0;

    /** Total shipping charge for the order.
     * @var float */
    protected $shipping = 0;

    /** Total handling charge for the order.
     * @var float */
    protected $handling = 0;

    /** Currency object, used for formatting amounts.
     * @var object */
    protected $Currency;

    /** Statuses that indicate an order is still in a "cart" phase.
     * @var array */
    protected static $nonfinal_statuses = array('cart', 'pending');

    /** Order number.
     * @var string */
    protected $order_id = '';

    /** Order sequence.
     * This is incremented when an order moves out of "pending" status
     * and becomes a real order.
     * @var integer */
    protected $order_seq = 0;

    /** Order Date.
     * This field is defined here since it contains an object and
     * needs to be accessed directly.
     * @var object */
    protected $order_date = NULL;

    /** Billing address object.
     * @var object */
    protected $Billto = NULL;

    /** Shipping address object.
     * @var object */
    protected $Shipto = NULL;

    /** Discount code applied.
     * @var string */
    protected $discount_code = '';

    /** Discount percentage applied.
     * @var float */
    protected $discount_pct = 0;

    /** Item total, i.e. net order amount excluding taxes and fees.
     * @var float */
    protected $net_items = 0;

    /** Total nontaxable items.
     * @var float */
    protected $net_nontax = 0;

    /** Total taxable items.
     * @var float */
    protected $net_taxable = 0;

    /** Is tax charged on shipping?
     * @var boolean */
    protected $tax_shipping = 0;

    /** Is tax charged on handling?
     * @var boolean */
    protected $tax_handling = 0;

    /** Special instructions entered by the buyer.
     * @var string */
    private $instructions = '';

    /** Currency code.
     * @var string */
    private $currency = '';

    /** Selected payment method (gateway name).
     * @var string */
    private $pmt_method = '';

    /** Experimental flag to mark whether an order needs to be saved.
     * @var boolean */
    protected $tainted = false;

    /** Flag to indicate that there are invalid items on the order.
     * @var boolean */
    private $hasInvalid = false;

    /** Amount paid on the order. Not part of the order record.
     * @var float */
    private $_amt_paid = 0;

    /** Username to show in log messages.
     * @var string */
    private $log_user = '';

    /** Buyer's email address.
     * @var string */
    protected $buyer_email = '';


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $id     Optional order ID to read
     */
    public function __construct($id='')
    {
        global $_USER, $_SHOP_CONF;

        $this->uid = (int)$_USER['uid'];
        $this->currency = $_SHOP_CONF['currency'];
        if (!empty($id)) {
            $this->order_id = $id;
            if (!$this->Load($id)) {
                $this->isNew = true;
                $this->items = array();
            } else {
                $this->isNew = false;
            }
        }
        if ($this->isNew) {
            if (empty($id)) {
                // Only create a new ID if one wasn't supplied.
                // Carts may supply an ID that needs to be static.
                $this->order_id = self::_createID();
            }
            $this->order_date = SHOP_now();
            $this->token = $this->_createToken();
            $this->shipping = 0;
            $this->handling = 0;
            $this->by_gc = 0;
            $this->shipper_id = NULL;
            $this->Billto = new Address;
            $this->Shipto = new Address;
        }
    }


    /**
     * Get an object instance for an order.
     *
     * @param   string|array    $key    Order ID or record
     * @return  object          Order object
     */
    public static function getInstance($key)
    {
        static $orders = array();
        if (is_array($key)) {
            $id = SHOP_getVar($key, 'order_id');
        } else {
            $id = $key;
        }
        if (!empty($id)) {
            if (!array_key_exists($id, $orders)) {
                $orders[$id] = new self($id);
            }
            return $orders[$id];
        } else {
            return new self;
        }
    }


    /**
     * Get the order status.
     *
     * @return  string      Order status
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * Allow other callers to determine if this is a new order, e.g. not found.
     *
     * @return  boolean     1 if not an existing record, 0 if it is
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Load the order information from the database.
     *
     * @param   string  $id     Order ID
     * @return  boolean     True on success, False if order not found
     */
    public function Load($id = '')
    {
        global $_TABLES;

        $this->tainted = false;
        if ($id != '') {
            $this->order_id = $id;
        }

        $sql = "SELECT ord.*,
            ( SELECT sum(pmt_amount) FROM {$_TABLES['shop.payments']} pmt
            WHERE pmt.pmt_order_id = ord.order_id
            ) as amt_paid
            FROM {$_TABLES['shop.orders']} ord
            WHERE ord.order_id='{$this->order_id}'";
        //COM_errorLog($sql);
        //echo $sql;die;
        $res = DB_query($sql);
        if (!$res) {
            return false;    // requested order not found
        }
        $A = DB_fetchArray($res, false);
        if (empty($A)) {
            return false;
        }
        if ($this->setVars($A)) {
            $this->isNew = false;
        }

        // Now load the items
        $items = array();
        $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}
                WHERE order_id = '{$this->order_id}'";
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $items[$A['id']] = $A;
            }
        }
        // Now load the arrays into objects
        foreach ($items as $item) {
            $this->items[$item['id']] = new OrderItem($item);
        }
        return true;
    }


    /**
     * Add a single item to this order.
     * Extracts item information from the provided $data variable, and
     * reads the item information from the database as well.  The entire
     * item record is added to the $items array as 'data'
     *
     * @param   array   $args   Array of item data
     */
    public function addItem($args)
    {
        if (!is_array($args)) return;

        // Set the product_id if it is not supplied but the item_id is,
        // which is formated as "id|opt1,opt2,..."
        if (!isset($args['product_id'])) {
            $item_id = explode('|', $args['item_id']);  // TODO: DEPRECATE
            $args['product_id'] = $item_id[0];
        }
        $args['order_id'] = $this->order_id;    // make sure it's set
        $args['token'] = $this->_createToken();  // create a unique token
        $OI = new OrderItem($args);
        $OI->setQuantity($args['quantity'])
            ->applyDiscountPct($this->getDiscountPct())
            ->Save();
        $this->items[] = $OI;
        $this->calcTotalCharges();
        //$this->Save();
    }


    /**
     * Set the billing address.
     *
     * @param   array   $A      Array of info, such as from $_POST
     */
    public function setBillto($A)
    {
        global $_TABLES;

        $have_address = false;
        if (is_object($A)) {
            /*$this->billto_id        = $A->getID();
            $this->billto_name      = $A->getName();
            $this->billto_company   = $A->getCompany();
            $this->billto_address1  = $A->getAddress1();
            $this->billto_address2  = $A->getAddress2();
            $this->billto_city      = $A->getCity();
            $this->billto_state     = $A->getState();
            $this->billto_country   = $A->getCountry();
            $this->billto_zip       = $A->getPostal();*/
            $this->Billto = $A;
            $have_address = true;
        } elseif (is_array($A)) {
            $addr_id = SHOP_getVar($A, 'useaddress', 'integer', 0);
            if ($addr_id == 0) {
                $addr_id = SHOP_getVar($A, 'addr_id', 'integer', 0);
            }
            if ($addr_id > 0) {
                // If set, the user has selected an existing address. Read
                // that value and use it's values.
                Cart::setSession('billing', $addr_id);
                $this->Billto = Address::getInstance($addr_id);
                $this->Billto->fromArray($A);
            } else {
                $this->Billto = new Address($A);
            }
            $have_address = true;

            /*if (!empty($A)) {
                $this->billto_id        = $addr_id;
                $this->billto_name      = SHOP_getVar($A, 'name');
                $this->billto_company   = SHOP_getVar($A, 'company');
                $this->billto_address1  = SHOP_getVar($A, 'address1');
                $this->billto_address2  = SHOP_getVar($A, 'address2');
                $this->billto_city      = SHOP_getVar($A, 'city');
                $this->billto_state     = SHOP_getVar($A, 'state');
                $this->billto_country   = SHOP_getVar($A, 'country');
                $this->billto_zip       = SHOP_getVar($A, 'zip');
            $have_address = true;
            }*/
        }
        if ($have_address) {
            $sql = "UPDATE {$_TABLES['shop.orders']} SET
                billto_id   = '{$this->Billto->getID()}',
                billto_name = '" . DB_escapeString($this->Billto->getName()) . "',
                billto_company = '" . DB_escapeString($this->Billto->getCompany()) . "',
                billto_address1 = '" . DB_escapeString($this->Billto->getAddress1()) . "',
                billto_address2 = '" . DB_escapeString($this->Billto->getAddress2()) . "',
                billto_city = '" . DB_escapeString($this->Billto->getCity()) . "',
                billto_state = '" . DB_escapeString($this->Billto->getState()) . "',
                billto_country = '" . DB_escapeString($this->Billto->getCountry()) . "',
                billto_zip = '" . DB_escapeString($this->Billto->getPostal()) . "'
                WHERE order_id = '" . DB_escapeString($this->order_id) . "'";
            DB_query($sql);
        }
        return $this;
    }


    /**
     * Set the shipping address.
     *
     * @param   array|NULL  $A      Array of info, or NULL to clear
     * @return  object      Current Order object
     */
    public function setShipto($A)
    {
        global $_TABLES;

        $have_address = false;
        if ($A === NULL) {
            $this->Shipto = new Address;
            // Clear out the shipping address
            /*$this->shipto_id        = 0;
            $this->shipto_name      = '';
            $this->shipto_company   = '';
            $this->shipto_address1  = '';
            $this->shipto_address2  = '';
            $this->shipto_city      = '';
            $this->shipto_state     = '';
            $this->shipto_country   = '';
            $this->shipto_zip       = '';*/
            $have_address = true;
        } elseif (is_array($A)) {
            $addr_id = SHOP_getVar($A, 'useaddress', 'integer', 0);
            if ($addr_id == 0) {
                $addr_id = SHOP_getVar($A, 'addr_id', 'integer', 0);
            }
            if ($addr_id > 0) {
                // If set, read and use an existing address
                Cart::setSession('shipping', $addr_id);
                $this->Shipto = Address::getInstance($addr_id);
                $this->Shipto->fromArray($A);
            } else {
                $this->Shipto = new Address($A);
            //if (!empty($A)) {
                /*$this->shipto_id        = $addr_id;
                $this->shipto_name      = SHOP_getVar($A, 'name');
                $this->shipto_company   = SHOP_getVar($A, 'company');
                $this->shipto_address1  = SHOP_getVar($A, 'address1');
                $this->shipto_address2  = SHOP_getVar($A, 'address2');
                $this->shipto_city      = SHOP_getVar($A, 'city');
                $this->shipto_state     = SHOP_getVar($A, 'state');
                $this->shipto_country   = SHOP_getVar($A, 'country');
                $this->shipto_zip       = SHOP_getVar($A, 'zip');
                $this->Shipto = new Address($A);*/
            }
            $this->setTaxRate(
                Tax::getProvider()
                ->withOrder($this)
                ->getRate()
            );
            $have_address = true;
        } elseif (is_object($A)) {
            /*$this->shipto_id        = $A->getID();
            $this->shipto_name      = $A->getName();
            $this->shipto_company   = $A->getCompany();
            $this->shipto_address1  = $A->getAddress1();
            $this->shipto_address2  = $A->getAddress2();
            $this->shipto_city      = $A->getCity();
            $this->shipto_state     = $A->getState();
            $this->shipto_country   = $A->getCountry();
            $this->shipto_zip       = $A->getPostal();*/
            $this->Shipto = $A;
            $this->setTaxRate(
                Tax::getProvider()
                ->withOrder($this)
                ->getRate()
            );
            $have_address = true;
        }

        if ($have_address) {
            $sql = "UPDATE {$_TABLES['shop.orders']} SET
                shipto_id   = '{$this->Shipto->getID()}',
                shipto_name = '" . DB_escapeString($this->Shipto->getName()) . "',
                shipto_company = '" . DB_escapeString($this->Shipto->getCompany()) . "',
                shipto_address1 = '" . DB_escapeString($this->Shipto->getAddress1()) . "',
                shipto_address2 = '" . DB_escapeString($this->Shipto->getAddress2()) . "',
                shipto_city = '" . DB_escapeString($this->Shipto->getCity()) . "',
                shipto_state = '" . DB_escapeString($this->Shipto->getState()) . "',
                shipto_country = '" . DB_escapeString($this->Shipto->getCountry()) . "',
                shipto_zip = '" . DB_escapeString($this->Shipto->getPostal()) . "',
                tax_rate = '{$this->tax_rate}',
                tax = '{$this->tax}'
                WHERE order_id = '" . DB_escapeString($this->order_id) . "'";
            DB_query($sql);
            SHOP_log($sql, SHOP_LOG_DEBUG);
        }
        return $this;
    }


    /**
     * Set all class variables, from a form or a database item
     *
     * @param   array   $A      Array of items
     * @return  object      Current Order object
     */
    public function setVars($A)
    {
        global $_USER, $_CONF, $_SHOP_CONF;

        if (!is_array($A)) return false;
        $tzid = COM_isAnonUser() ? $_CONF['timezone'] : $_USER['tzid'];

        $this->uid      = SHOP_getVar($A, 'uid', 'int');
        $this->status   = SHOP_getVar($A, 'status');
        $this->pmt_method = SHOP_getVar($A, 'pmt_method');
        $this->pmt_txn_id = SHOP_getVar($A, 'pmt_txn_id');
        $this->currency = SHOP_getVar($A, 'currency', 'string', $_SHOP_CONF['currency']);
        $dt = SHOP_getVar($A, 'order_date', 'integer');
        if ($dt > 0) {
            $this->order_date = new \Date($dt, $tzid);
        } else {
            $this->order_date = SHOP_now();
        }
        $this->order_id = SHOP_getVar($A, 'order_id');
        $this->shipping = SHOP_getVar($A, 'shipping', 'float');
        $this->handling = SHOP_getVar($A, 'handling', 'float');
        $this->tax = SHOP_getVar($A, 'tax', 'float');
        $this->order_total = SHOP_getVar($A, 'order_total', 'float');
        $this->instructions = SHOP_getVar($A, 'instructions');
        $this->by_gc = SHOP_getVar($A, 'by_gc', 'float');
        $this->token = SHOP_getVar($A, 'token', 'string');
        $this->buyer_email = SHOP_getVar($A, 'buyer_email');
        $this->billto_id = SHOP_getVar($A, 'billto_id', 'integer');
        $this->shipto_id = SHOP_getVar($A, 'shipto_id', 'integer');
        $this->order_seq = SHOP_getVar($A, 'order_seq', 'integer');
        $this->setDiscountPct(SHOP_getVar($A, 'discount_pct', 'float'));
        $this->setDiscountCode(SHOP_getVar($A, 'discount_code'));
        //if ($this->status != 'cart') {
            $this->tax_rate = SHOP_getVar($A, 'tax_rate');
        //}
        $this->setTaxShipping($A['tax_shipping'])
            ->setTaxHandling($A['tax_handling']);

        $this->m_info = @unserialize(SHOP_getVar($A, 'info'));
        if ($this->m_info === false) $this->m_info = array();
        /*foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fld = $type . '_' . $name;
                $this->$fld = $A[$fld];
            }
        }*/
        $this->Billto = (new Address())->fromArray(
            $this->getAddress('billto', $A), 'billto'
        );
        $this->Shipto = (new Address())->fromArray(
            $this->getAddress('shipto', $A), 'shipto'
        );
        if (isset($A['uid'])) $this->uid = $A['uid'];

        if (isset($A['order_id']) && !empty($A['order_id'])) {
            $this->order_id = $A['order_id'];
            $this->isNew = false;
            Cart::setSession('order_id', $A['order_id']);
        } else {
            $this->order_id = '';
            $this->isNew = true;
            Cart::clearSession('order_id');
        }
        $this->shipper_id = $A['shipper_id'];
        $this->gross_items = SHOP_getVar($A, 'gross_items', 'float', 0);
        $this->net_taxable = SHOP_getVar($A, 'net_taxable', 'float', 0);
        $this->net_nontax = SHOP_getVar($A, 'net_nontax', 'float', 0);
        if (isset($A['amt_paid'])) {    // only present in DB record
            $this->_amt_paid = (float)$A['amt_paid'];
            $this->total = (float)$A['order_total'];
        }
        return $this;
    }


    /**
     * Set the "tax on shipping" flag.
     *
     * @param   boolean $flag   True to tax shipping, False if not
     * @return  object  $this
     */
    private function setTaxShipping($flag)
    {
        $this->tax_shipping = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if this order required tax on shipping.
     *
     * @return  integer     1 if shipping is taxed, 0 if not
     */
    public function getTaxShipping()
    {
        return $this->tax_shipping ? 1 : 0;
    }


    /**
     * Check if this order required tax on handling.
     *
     * @return  integer     1 if handling is taxed, 0 if not
     */
    public function getTaxHandling()
    {
        return $this->tax_handling ? 1 : 0;
    }


    /**
     * Set the "tax on handling" flag.
     *
     * @param   boolean $flag   True to tax handling, False if not
     * @return  object  $this
     */
    private function setTaxHandling($flag)
    {
        $this->tax_handling = $flag ? 1 : 0;
        return $this;
    }


    /**
     * API function to delete an entire order record.
     * Only orders that have a status of "cart" or "pending" can be deleted.
     * Finalized (paid, shipped, etc.) orders cannot  be removed.
     * Trying to delete a nonexistant order returns true.
     *
     * @param   string  $order_id       Order ID, taken from $_SESSION if empty
     * @return  boolean     True on success, False on error.
     */
    public static function Delete($order_id = '')
    {
        global $_TABLES;

        if ($order_id == '') {
            $order_id = Cart::getSession('order_id');
        }
        if (!$order_id) return true;

        $order_id = DB_escapeString($order_id);

        // Just get an instance of this order since there are a couple of values to check.
        $Ord = self::getInstance($order_id);
        if ($Ord->isNew) return true;

        // Only orders with no sequence number can be deleted.
        // Only orders with certain status values can be deleted.
        if ($Ord->order_seq !== NULL || $Ord->isFinal()) {
            return false;
        }

        // Checks passed, delete the order and items
        $sql = "START TRANSACTION;
            DELETE FROM {$_TABLES['shop.oi_opts']} WHERE oi_id IN (
                SELECT id FROM {$_TABLES['shop.orderitems']} WHERE order_id = '$order_id'
            );
            DELETE FROM {$_TABLES['shop.orderitems']} WHERE order_id = '$order_id';
            DELETE FROM {$_TABLES['shop.orders']} WHERE order_id = '$order_id';
            COMMIT;";
        DB_query($sql);
        return DB_error() ? false : true;
    }


    /**
     * Save the current order to the database
     *
     * @return  string      Order ID
     */
    public function Save()
    {
        global $_TABLES, $_SHOP_CONF;

        if (!SHOP_isMinVersion()) return '';

        // Save all the order items
        /*$this->net_nontax = $this->net_taxable = $this->gross_items = 0;*/
        foreach ($this->items as $item) {
            $item->Save();
        }
        $order_total = $this->getOrderTotal();

        if ($this->isNew) {
            // Shouldn't have an empty order ID, but double-check
            if ($this->order_id == '') $this->order_id = self::_createID();
            if ($this->Billto->getName() == '') {
                $this->billto_name = COM_getDisplayName($this->uid);
            }
            Cart::setSession('order_id', $this->order_id);
            // Set field values that can only be set once and not updated
            $sql1 = "INSERT INTO {$_TABLES['shop.orders']} SET
                    order_id='{$this->order_id}',
                    token = '" . DB_escapeString($this->token) . "',
                    uid = '" . (int)$this->uid . "', ";
            $sql2 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.orders']} SET ";
            $sql2 = " WHERE order_id = '{$this->order_id}'";
        }

        $fields = array(
            "order_date = '{$this->order_date->toUnix()}'",
            "status = '{$this->status}'",
            //"pmt_txn_id = '" . DB_escapeString($this->pmt_txn_id) . "'",
            "pmt_method = '" . DB_escapeString($this->pmt_method) . "'",
            "by_gc = '{$this->by_gc}'",
            //"phone = '" . DB_escapeString($this->phone) . "'",
            "tax = '{$this->tax}'",
            "shipping = '{$this->shipping}'",
            "handling = '{$this->handling}'",
            "gross_items = '{$this->gross_items}'",
            "net_nontax = '{$this->net_nontax}'",
            "net_taxable = '{$this->net_taxable}'",
            "instructions = '" . DB_escapeString($this->instructions) . "'",
            "buyer_email = '" . DB_escapeString($this->buyer_email) . "'",
            "info = '" . DB_escapeString(@serialize($this->m_info)) . "'",
            "tax_rate = '{$this->tax_rate}'",
            "currency = '{$this->currency}'",
            "shipper_id = '{$this->shipper_id}'",
            "discount_code = '" . DB_escapeString($this->discount_code) . "'",
            "discount_pct = '{$this->discount_pct}'",
            "order_total = {$order_total}",
        );

        $billto = $this->Billto->toArray();
        $shipto = $this->Shipto->toArray();
        foreach (array('billto', 'shipto') as $type) {
            $fld = $type . '_id';
            $fields[] = "$fld = " . (int)$$type['id'];
            foreach ($this->_addr_fields as $name) {
                $fld = $type . '_' . $name;
                $fields[] = $fld . "='" . DB_escapeString($$type[$name]) . "'";
            }
        }
        $sql = $sql1 . implode(', ', $fields) . $sql2;
        //echo $sql;die;
        //SHOP_log(("Save: " . $sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        $this->isNew = false;
        return $this->order_id;
    }


    /**
     * View or print the current order.
     * Access is controlled by the caller invoking canView() since a token
     * may be required.
     *
     * @param  string  $view       View to display (cart, final order, etc.)
     * @param  integer $step       Current step, for updating next_step in the form
     * @return string      HTML for order view
     */
    public function View($view = 'order', $step = 0)
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP, $LANG_SHOP_HELP;

        // Safety valve in case an invalid order is requested.
        // This is for administrators, the canView() function will trap this
        // for regular users.
        if ($this->isNew) {
            COM_setMsg($LANG_SHOP['item_not_found']);
            return '';
        }

        $this->isFinalView = false;
        $is_invoice = true;    // normal view/printing view
        $icon_tooltips = array();

        switch ($view) {
        case 'adminview':
        case 'order':
            $this->isFinalView = true;
            $tplname = 'order';
            break;
        case 'checkout':
            $this->checkRules();
            $this->setTaxRate(
                Tax::getProvider()
                    ->withOrder($this)
                    ->getRate()
                )
                ->calcTotalCharges()
                ->Save();
            $tplname = 'order';
            break;
        case 'viewcart':
            $this->checkRules();
            $this->tax_rate = 0;
            $tplname = 'viewcart';
            break;
        case 'packinglist':
            // Print a packing list. Same as print view but no prices or fees shown.
            $tplname = 'packinglist';
            $is_invoice = false;
            $this->isFinalView = true;
            break;
        case 'print':
        case 'printorder':
            $this->isFinalView = true;
            $tplname = 'order.print';
            break;
        case 'pdfpl':
            $is_invoice = false;
        case 'pdforder':
            $this->isFinalView = true;
            $tplname = 'order.pdf';
            break;
        case 'shipment':
            $this->isFinalView = true;
            $tplname = 'shipment';
            break;
        }
        $step = (int)$step;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('order', $tplname . '.thtml');
        $billto = $this->Billto->toArray();
        $shipto = $this->Shipto->toArray();
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fldname = $type . '_' . $name;
                $T->set_var($fldname, $$type[$name]);
            }
        }

        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (Workflow::getAll($this) as $key => $wf) {
            $T->set_var('have_' . $wf->wf_name, 'true');
        }

        $T->set_block('order', 'ItemRow', 'iRow');

        $Currency = Currency::getInstance($this->currency);
        $this->no_shipping = 1;   // no shipping unless physical item ordered
        $this->gross_items = 0;
        $this->net_items = 0;
        $this->net_nontax = 0;
        $this->net_taxable = 0;
        $item_qty = array();        // array to track quantity by base item ID
        $have_images = false;
        $has_sale_items = false;
        $item_net = 0;
        $good_items = 0;        // Count non-embargoed items
        $discount_items = 0;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            $P->setVariant($item->getVariantID());
            if ($is_invoice) {
                $img = $P->getImage('', $_SHOP_CONF['order_tn_size']);
                if (!empty($img['url'])) {
                    $img_url = COM_createImage(
                        $img['url'],
                        '',
                        array(
                            'width' => $img['width'],
                            'height' => $img['height'],
                        )
                    );
                    $T->set_var('img_url', $img_url);
                    $have_images = true;
                } else {
                    $T->clear_var('img_url');
                }
            }

            //$item_discount = $P->getDiscount($item->quantity);
            /*if (!isset($item_qty[$item->product_id])) {
                $total_item_q = $this->getTotalBaseItems($item->product_id);
                $item_qty[$item->product_id] = array(
                    'qty'   => $total_item_q,
                    'discount' => $P->getDiscount($total_item_q),
                );
            }*/
            //if ($item_qty[$item->product_id]['discount'] > 0) {
            if ($item->getDiscount() > 0) {
                $discount_items ++;
                $price_tooltip = sprintf(
                    $LANG_SHOP['reflects_disc'],
                    ($item->getDiscount() * 100)
                );
            } else {
                $price_tooltip = '';
            }
            if ($item->getProduct()->isOnSale()) {
                $has_sale_items = true;
                $sale_tooltip = $LANG_SHOP['sale_price'] . ': ' . $item->getProduct()->getSale()->name;
            } else {
                $sale_tooltip = '';
            }

            if (!$item->getInvalid()) {
                $good_items++;
            }
            $item_total = $item->getPrice() * $item->getQuantity();
            $item_net = $item->getNetPrice() * $item->getQuantity();
            $this->gross_items += $item_total;
            if ($P->isTaxable()) {
                $this->net_taxable += $item_net;
            } else {
                $this->net_nontax += $item_net;
            }
            $this->net_items += $item_net;
            $T->set_var(array(
                'cart_item_id'  => $item->getID(),
                'fixed_q'       => $P->getFixedQuantity(),
                'item_id'       => htmlspecialchars($item->getProductId()),
                'item_dscp'     => htmlspecialchars($item->getDscp()),
                'item_price'    => $Currency->FormatValue($item->getPrice()),
                'item_quantity' => $item->getQuantity(),
                'item_total'    => $Currency->FormatValue($item_total),
                'is_admin'      => $this->isAdmin,
                'is_file'       => $item->canDownload(),
                'taxable'       => $P->isTaxable(),
                'tax_icon'      => $LANG_SHOP['tax'][0],
                'sale_icon'     => $LANG_SHOP['sale_price'][0],
                'discount_icon' => $LANG_SHOP['discount'][0],
                'discount_tooltip' => $price_tooltip,
                'sale_tooltip'  => $sale_tooltip,
                'token'         => $item->getToken(),
                'item_options'  => $item->getOptionDisplay(),
                'sku'           => $P->getSKU($item),
                'item_link'     => $P->getLink($item->getID()),
                'pi_url'        => SHOP_URL,
                'is_invoice'    => $is_invoice,
                'del_item_url'  => COM_buildUrl(
                    SHOP_URL . '/cart.php?action=delete&id=' . $item->getID()
                ),
                'embargoed'     => $item->getInvalid(),
            ) );
            if ($P->isPhysical()) {
                $this->no_shipping = 0;
            }
            $qty_bo = 0;
            if ($this->status == 'cart') {      // TODO, divorce cart from order
                $qty_bo = $P->getQuantityBO($item->getQuantity());
                if ($qty_bo > 0) {
                    $T->set_var(array(
                        'msg_bo' => sprintf($LANG_SHOP['qty_bo'], $qty_bo),
                        'bo_icon' => $LANG_SHOP['backordered'][0],
                    ) );
                }
                $T->set_var(array(
                    'min_ord_qty' => $P->getMinOrderQty(),
                    'max_ord_qty' => $P->getMaxOrderQty(),
                ) );
            }
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        // Reload the address objects in case the addresses were updated
        $ShopAddr = new Company;
        //$this->Billto = new Address($this->getAddress('billto'));
        //$this->Shipto = new Address($this->getAddress('shipto'));

        // Call selectShipper() here to get the shipping amount into the local var.
        $shipper_select = $this->selectShipper();

        $this->total = $this->getTotal();     // also calls calcTax()
        $by_gc = (float)$this->getInfo('apply_gc');
        // Only show the icon descriptions when the invoice amounts are shown
        if ($is_invoice) {
            if ($discount_items > 0) {
                $icon_tooltips[] = $LANG_SHOP['discount'][0] . ' = ' . $LANG_SHOP['price_incl_disc'];
            }
            if ($this->tax_items > 0) {
                $icon_tooltips[] = $LANG_SHOP['taxable'][0] . ' = ' . $LANG_SHOP['taxable'];
            }
            if ($has_sale_items) {
                $icon_tooltips[] = $LANG_SHOP['sale_price'][0] . ' = ' . $LANG_SHOP['sale_price'];
            }
            if ($qty_bo) {
                $icon_tooltips[] = $LANG_SHOP['backordered'][0] . ' = ' . $LANG_SHOP['backordered'];
            }
            $icon_tooltips = implode('<br />', $icon_tooltips);
        }
        /*if ($this->tax_rate > 0) {
            $lang_tax_on_items = $LANG_SHOP['sales_tax'];
            //$lang_tax_on_items = sprintf($LANG_SHOP['tax_on_x_items'], $this->tax_rate * 100, $this->tax_items);
        } else {
            $lang_tax_on_items = $LANG_SHOP['sales_tax'];*/
        /*if ($view == 'viewcart') {
            // Back out sales tax if tax is not charged. This happens when viewing the cart
            // and a tax amount gets set, but shouldn't be shown in the order yet.
            $this->total -= $this->tax;
        }*/

        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'not_final'     => !$this->isFinalView,
            'order_date'    => $this->order_date->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->order_date->format($_SHOP_CONF['datetime_fmt'], false),
            'order_number'  => $this->order_id,
            'order_instr'   => htmlspecialchars($this->instructions),
            'shop_name'     => $ShopAddr->toHTML('company'),
            'shop_addr'     => $ShopAddr->toHTML('address'),
            'shop_phone'    => $_SHOP_CONF['shop_phone'],
            'apply_gc'      => $by_gc > 0 ? $Currency->FormatValue($by_gc) : 0,
            'net_total'     => $Currency->Format($this->total - $by_gc),
            'status'        => $this->status,
            'token'         => $this->token,
            'allow_gc'      => $_SHOP_CONF['gc_enabled']  && !COM_isAnonUser() ? true : false,
            'next_step'     => $step + 1,
            'not_anon'      => !COM_isAnonUser(),
            'total_prefix'  => $Currency->Pre(),
            'total_postfix' => $Currency->Post(),
            'total_num'     => $Currency->FormatValue($this->total),
            'cur_decimals'  => $Currency->Decimals(),
            'item_subtotal' => $Currency->FormatValue($this->gross_items),
            'return_url'    => SHOP_getUrl(),
            'is_invoice'    => $is_invoice,
            'icon_dscp'     => $icon_tooltips,
            'print_url'     => $this->buildUrl('print'),
            'have_images'   => $is_invoice ? $have_images : false,
            'linkPackingList' => self::linkPackingList($this->order_id),
            'linkPrint'     => self::linkPrint($this->order_id, $this->token),
            'billto_addr'   => $this->Billto->toHTML(),
            'shipto_addr'   => $this->Shipto->toHTML(),
            'shipment_block' => $this->getShipmentBlock(),
            'itemsToShip'   => $this->itemsToShip(),
            'ret_url'       => urlencode($_SERVER['REQUEST_URI']),
            'tax_items'     => $this->tax_items,
            'discount_code_fld' => $this->canShowDiscountEntry(),
            'discount_code' => $this->getDiscountCode(),
            'dc_row_vis'    => $this->getDiscountCode(),
            'dc_amt'        => $Currency->FormatValue($this->getDiscountAmount() * -1),
            'net_items'     => $Currency->Format($this->net_items),
            'good_items'    => $good_items,
            'cart_tax'      => $this->tax > 0 ? $Currency->FormatValue($this->tax) : 0,
            'lang_tax_on_items'  => $LANG_SHOP['sales_tax'],
            'total'     => $Currency->Format($this->total),
            'handling'  => $this->handling > 0 ? $Currency->FormatValue($this->handling) : 0,
            'subtotal'  => $this->gross_items == $this->total ? '' : $Currency->Format($this->gross_items),
            'tax_icon'  => $LANG_SHOP['tax'][0],
            'tax_shipping' => $this->getTaxShipping(),
            'tax_handling' => $this->getTaxHandling(),
            'amt_paid' => $Currency->Format($this->_amt_paid),
            'is_paid' => $this->_amt_paid >= $this->total,
        ) );

        if (!$this->no_shipping) {
            $T->set_var(array(
                'shipper_id'    => $this->shipper_id,
                'ship_method'   => Shipper::getInstance($this->shipper_id)->getName(),
                'ship_select'   => $this->isFinalView ? NULL : $shipper_select,
                'shipping'      => $Currency->FormatValue($this->shipping),
            ) );
        }

        if ($this->isAdmin) {
            $T->set_var(array(
                'is_admin'      => true,
                'purch_name'    => COM_getDisplayName($this->uid),
                'purch_uid'     => $this->uid,
                //'stat_update'   => OrderStatus::Selection($this->order_id, 1, $this->status),
                'order_status'  => $this->status,
            ) );
            $T->set_block('ordstat', 'StatusSelect', 'Sel');
            foreach (OrderStatus::getAll() as $key => $data) {
                if (!$data->enabled) continue;
                $T->set_var(array(
                    'selected' => $key == $this->status ? 'selected="selected"' : '',
                    'stat_key' => $key,
                    'stat_descr' => OrderStatus::getDscp($key),
                ) );
                $T->parse('Sel', 'StatusSelect', true);
            }
        }

        // Instantiate a date object to handle formatting of log timestamps
        $dt = new \Date('now', $_USER['tzid']);
        $log = $this->getLog();
        $T->set_block('order', 'LogMessages', 'Log');
        foreach ($log as $L) {
            $dt->setTimestamp($L['ts']);
            $T->set_var(array(
                'log_username'  => $L['username'],
                'log_msg'       => $L['message'],
                'log_ts'        => $dt->format($_SHOP_CONF['datetime_fmt'], true),
                'log_ts_tip'    => $dt->format($_SHOP_CONF['datetime_fmt'], false),
            ) );
            $T->parse('Log', 'LogMessages', true);
        }

        $payer_email = $this->buyer_email;
        if ($payer_email == '' && !COM_isAnonUser()) {
            $payer_email = $_USER['email'];
        }
        $focus_fld = SESS_getVar('shop_focus_field');
        if ($focus_fld) {
            $T->set_var('focus_element', $focus_fld);
            SESS_unSet('shop_focus_field');
        }
        $T->set_var('payer_email', $payer_email);

        switch ($view) {
        case 'viewcart':
            $T->set_var('gateway_radios', $this->getCheckoutRadios());
            if ($this->hasInvalid) {
                $T->set_var('rules_msg', $LANG_SHOP_HELP['hlp_rules_noitems']);
            }
            break;
        case 'checkout':
            $T->set_var('checkout', true);
            if ($this->hasInvalid) {
                $T->set_var('rules_msg', $LANG_SHOP_HELP['hlp_rules_noitems']);
            } else {
                $gw = Gateway::getInstance($this->getInfo('gateway'));
                if ($gw) {
                    $T->set_var(array(
                        'gateway_vars'  => $this->checkoutButton($gw),
                        'pmt_method'    => $gw->getDscp(),
                    ) );
                }
            }
        default:
            break;
        }
        $status = $this->status;
        $Payments = $this->getPayments();
        if ($this->pmt_method != '') {
            $gw = Gateway::getInstance($this->pmt_method);
            if ($gw !== NULL) {
                $pmt_method = $gw->getDscp();
            } else {
                $pmt_method = $this->pmt_method;
            }

            $T->set_var(array(
                'pmt_method' => $pmt_method,
                //'pmt_txn_id' => $this->pmt_txn_id,
                //'ipn_det_url' => IPN::getDetailUrl($this->pmt_txn_id, 'txn_id'),
            ) );
        }
        $T->set_block('order', 'Payments', 'pmtRow');
        foreach ($Payments as $Payment) {
            $T->set_var(array(
                'gw_name' => Gateway::getInstance($Payment->getGateway())->getDscp(),
                'ipn_det_url' => IPN::getDetailUrl($Payment->getRefID(), 'txn_id'),
                'pmt_txn_id' => $Payment->getRefID(),
                'pmt_amount' => $Currency->formatValue($Payment->getAmount()),
            ) );
            $T->parse('pmtRow', 'Payments', true);
        }

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
     * If the order is paid, move its status from `pending` to `processing`.
     * Only updates the order if the status is pending, not if it has already
     * been move further along.
     *
     * @return  boolean     True if status is changed, False if left as-is
     */
    public function updatePmtStatus()
    {
        if (
            (
                $this->getStatus() == 'cart' ||
                $this->getStatus() == 'pending' ||
                $this->getStatus() == 'invoiced'
            ) &&
            $this->isPaid()
        ) {
            // Get the status to set. For non-physical items, the order is
            // fullfilled so close it.
            if ($this->hasPhysical()) {
                $this->updateStatus(self::STATUS_PROCESSING);
            } else {
                $this->updateStatus(self::STATUS_CLOSED);
            }
            return true;
        }
        return false;       // return false if no change
    }


    /**
     * Update the order's status flag to a new value.
     * If the new status isn't really new, the order is unchanged and "true"
     * is returned.  If this is called by some automated process, $log can
     * be set to "false" to avoid logging the change, such as during order
     * creation.
     *
     * @uses    Order::Log()
     * @param   string  $newstatus      New order status
     * @param   boolean $log            True to log the change, False to not
     * @param   boolean $notify         True to notify the buyer, False to not.
     * @return  string      New status, old status if not updated.
     */
    public function updateStatus($newstatus, $log = true, $notify=true)
    {
        global $_TABLES, $LANG_SHOP;

        // When orders are paid by IPN, move the status to "processing"
        if ($newstatus == 'paid') {
            $newstatus = 'processing';
        }

        $oldstatus = $this->status;

        // If the status isn't really changed, don't bother updating anything
        // and just treat it as successful
        if ($oldstatus == $newstatus) {
            return $oldstatus;
        }

        $this->status = $newstatus;
        $db_order_id = DB_escapeString($this->order_id);
        $log_user = $this->log_user;

        // If promoting from a cart status to a real order, add the sequence number.
        if (!$this->isFinal($oldstatus) && $this->isFinal() && $this->order_seq < 1) {
            $sql = "START TRANSACTION;
                SELECT COALESCE(MAX(order_seq)+1,1) FROM {$_TABLES['shop.orders']} INTO @seqno FOR UPDATE;
                UPDATE {$_TABLES['shop.orders']}
                    SET status = '". DB_escapeString($newstatus) . "',
                    order_seq = @seqno
                WHERE order_id = '$db_order_id';
                COMMIT;";
            DB_query($sql);
            $this->order_seq = (int)DB_getItem(
                $_TABLES['shop.orders'],
                'order_seq',
                "order_id = '{$db_order_id}'"
            );
        } else {
            // Update the status but leave the sequence alone
            $sql = "UPDATE {$_TABLES['shop.orders']} SET
                    status = '". DB_escapeString($newstatus) . "'
                WHERE order_id = '$db_order_id';";
            DB_query($sql);
        }
        //echo $sql;die;
        //SHOP_log($sql, SHOP_LOG_DEBUG);
        if (DB_error()) {
            $this->status = $oldstatus;     // update in-memory object
            return $oldstatus;
        }
        $msg = sprintf($LANG_SHOP['status_changed'], $oldstatus, $newstatus);
        if ($log) {
            $this->Log($msg, $log_user);
        }
        if ($notify) {
            $this->Notify($newstatus, $msg);
        }
        return $newstatus;
    }


    /**
     * Log a message related to this order.
     * Typically used to log status changes.  If this is called for an
     * order object, the local "log_user" variable can be preset to the
     * log user name.  Otherwise, the current user's display name will be
     * associated with the log entry.
     *
     * @param   string  $msg        Log message
     * @param   string  $log_user   Optional log username
     */
    public function Log($msg, $log_user = '')
    {
        global $_TABLES, $_USER;

        // Don't log empty messages by mistake
        if (empty($msg)) return;

        // If the order ID is omitted, get information from the current
        // object.
        if (empty($log_user)) {
            $log_user = COM_getDisplayName($_USER['uid']) .
                ' (' . $_USER['uid'] . ')';
        }
        $order_id = DB_escapeString($this->order_id);
        $sql = "INSERT INTO {$_TABLES['shop.order_log']} SET
            username = '" . DB_escapeString($log_user) . "',
            order_id = '$order_id',
            message = '" . DB_escapeString($msg) . "',
            ts = UNIX_TIMESTAMP()";
        DB_query($sql);
        return !DB_error();
    }


    /**
     * Get the last log entry.
     * Called from admin ajax to display the log after the status is updated.
     * Resets the "ts" field to the formatted timestamp.
     *
     * @return  array   Array of DB fields.
     */
    public function XgetLastLog()
    {
        global $_TABLES, $_SHOP_CONF, $_USER;

        $sql = "SELECT * FROM {$_TABLES['shop.order_log']}
                WHERE order_id = '" . DB_escapeString($this->order_id) . "'
                ORDER BY ts DESC
                LIMIT 1";
        //echo $sql;die;
        if (!DB_error()) {
            $L = DB_fetchArray(DB_query($sql), false);
            if (!empty($L)) {
                $dt = new \Date($L['ts'], $_USER['tzid']);
                $L['ts'] = $dt->format($_SHOP_CONF['datetime_fmt'], true);
            }
        }
        return $L;
    }


    /**
     * Send an email to the administrator and/or buyer.
     *
     * @param   string  $status     Order status (pending, paid, etc.)
     * @param   string  $gw_msg     Optional gateway message to include with email
     * @param   boolean $force      True to force notification
     */
    public function Notify($status='', $gw_msg='', $force=false)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        // Check if any notification is to be sent for this status update.
        $notify_buyer = OrderStatus::getInstance($status)->notifyBuyer();
        $notify_admin = OrderStatus::getInstance($status)->notifyAdmin();
        if (!$force && !$notify_buyer && !$notify_admin) {
            return;
        }

        $Shop = new Company;
        $Cust = Customer::getInstance($this->uid);
        if ($force || $notify_buyer) {
            $save_language = $LANG_SHOP;    // save the site language
            $save_userlang = $_CONF['language'];
            $_CONF['language'] = $Cust->getLanguage(true);
            $LANG_SHOP = self::loadLanguage($_CONF['language']);
            // Set up templates, using language-specific ones if available.
            // Fall back to English if no others available.
            $T = new \Template(array(
                SHOP_PI_PATH . '/templates/notify/' . $Cust->getLanguage(),
                SHOP_PI_PATH . '/templates/notify/' . COM_getLanguageName(),
                SHOP_PI_PATH . '/templates/notify/english',
                SHOP_PI_PATH . '/templates/notify', // catch templates using language strings
            ) );
            $T->set_file(array(
                'msg'       => 'msg_buyer.thtml',
                'msg_body'  => 'order_detail.thtml',
                'tracking'  => 'tracking_info.thtml',
            ) );

            $text = $this->_prepareNotification($T, $gw_msg, true);

            SHOP_log("Sending email to " . $this->uid . ' at ' . $this->buyer_email, SHOP_LOG_DEBUG);
            $subject = SHOP_getVar(
                $LANG_SHOP['subj_email_user'],
                $status,
                'string',
                $LANG_SHOP['sub_email']
            );
            $subject = sprintf($subject, $Shop->getCompany());
            if ($this->buyer_email != '') {
                COM_emailNotification(array(
                    'to' => array($this->buyer_email),
                    'from' => array(
                        'email' => $_CONF['site_mail'],
                        'name'  => $Shop->getCompany(),
                    ),
                    'htmlmessage' => $text,
                    'subject' => htmlspecialchars($subject),
                ) );
                SHOP_log("Buyer Notification Done.", SHOP_LOG_DEBUG);
            }
            $LANG_SHOP = $save_language;    // Restore the default language
        }

        if ($notify_admin) {        // never forced
            // Set up templates, using language-specific ones if available.
            // Fall back to English if no others available.
            // This uses the site default language.
            $Cust = Customer::getInstance($this->uid);
            $T = new \Template(array(
                SHOP_PI_PATH . '/templates/notify/' . COM_getLanguageName(),
                SHOP_PI_PATH . '/templates/notify/english',
                SHOP_PI_PATH . '/templates/notify', // catch templates using language strings
            ) );
            $T->set_file(array(
                'msg'       => 'msg_admin.thtml',
                'msg_body'  => 'order_detail.thtml',
            ) );

            $text = $this->_prepareNotification($T, $gw_msg, false);

            if (!empty($_SHOP_CONF['admin_email_addr'])) {
                $email_addr = $_SHOP_CONF['admin_email_addr'];
            } else {
                $email_addr = $_CONF['site_mail'];
            }
            SHOP_log("Sending email to admin at $email_addr", SHOP_LOG_DEBUG);
            if (!empty($email_addr)) {
                COM_emailNotification(array(
                    'to' => array(
                        'email' => $email_addr,
                        'name'  => $Shop->getCompany(),
                    ),
                    'from' => SHOP_getVar($_CONF, 'noreply_mail', 'string', $_CONF['site_mail']),
                    'htmlmessage' => $text,
                    'subject' => htmlspecialchars($LANG_SHOP['subj_email_admin']),
                ) );
                SHOP_log("Admin Notification Done.", SHOP_LOG_DEBUG);
            }
        }
    }


    /**
     * This function actually creates the text for notification emails.
     *
     * @param   object  &$T         Template object reference
     * @param   string  $gw_msg     Optional gateway message to include
     * @param   boolean $incl_trk   True to include package tracking info
     * @return  string      Text for email body
     */
    private function _prepareNotification(&$T, $gw_msg='', $incl_trk=true)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        // Add all the items to the message
        $total = (float)0;      // Track total purchase value
        $files = array();       // Array of filenames, for attachments
        $item_total = 0;
        $dl_links = '';         // Start with empty download links
        $email_extras = array();
        $Cur = Currency::getInstance($this->currency);   // get currency object for formatting
        $Shop = new Company;

        foreach ($this->items as $id=>$item) {
            $P = $item->getProduct();

            // Add the file to the filename array, if any. Download
            // links are only included if the order status is 'paid'
            $file = $P->file;
            if (!empty($file) && $this->status == 'paid') {
                $files[] = $file;
                $dl_url = SHOP_URL . '/download.php?';
                // There should always be a token, but fall back to the
                // product ID if there isn't
                if ($item->getToken() != '') {
                    $dl_url .= 'token=' . urlencode($item->getToken());
                    $dl_url .= '&i=' . $item->getID();
                } else {
                    $dl_url .= 'id=' . $item->getProductId();
                }
                $dl_links .= "<a href=\"$dl_url\">$dl_url</a><br />";
            }

            $ext = $item->getQuantity() * $item->getPrice();
            $item_total += $ext;
            $item_descr = $item->getDscp();
            $options_text = $item->getOptionDisplay();

            $T->set_block('msg_body', 'ItemList', 'List');
            $T->set_var(array(
                'qty'   => $item->getQuantity(),
                'price' => $Cur->FormatValue($item->getPrice()),
                'ext'   => $Cur->FormatValue($ext),
                'name'  => $item_descr,
                'options_text' => $options_text,
            ) );
            //), '', false, false);
            $T->parse('List', 'ItemList', true);
            $x = $P->EmailExtra($item);
            if ($x != '') $email_extras[] = $x;
        }

        $total_amount = $item_total + $this->tax + $this->shipping + $this->handling;
        $user_name = COM_getDisplayName($this->uid);
        if ($this->Billto->getName() == '') {
            $this->Billto->setName($user_name);
        }

        if ($incl_trk) {        // include tracking information block
            $Shipments = Shipment::getByOrder($this->order_id);
            if (count($Shipments) > 0) {
                foreach ($Shipments as $Shp) {
                    $shp_dt = $Shp->getDate()->toMySQL(true);
                    $Packages = $Shp->getPackages();
                    $T->set_block('tracking', 'trackingPackages', 'TP');
                    foreach ($Packages as $Pkg) {
                        $T->set_var(array(
                            'shipment_date' => $shp_dt,
                            'shipper_name'  => $Pkg->getShipperInfo(),
                            'tracking_num'  => $Pkg->getTrackingNumber(),
                            'tracking_url'  => $Pkg->getTrackingURL(false),
                        ) );
                        $shp_dt = '';
                        $T->parse('TP', 'trackingPackages', true);
                    }
                }
                $T->set_var('tracking_info', $T->parse('detail', 'tracking'));
            }
        }

        $T->set_var(array(
            'payment_gross'     => $Cur->Format($total_amount),
            'payment_items'     => $Cur->Format($item_total),
            'tax'               => $Cur->FormatValue($this->tax),
            'tax_num'           => $this->tax,
            'shipping'          => $Cur->FormatValue($this->shipping),
            'shipper_id'        => $this->shipper_id,
            'handling'          => $Cur->FormatValue($this->handling),
            'handling_num'      => $this->handling,
            'payment_date'      => SHOP_now()->toMySQL(true),
            'payer_email'       => $this->buyer_email,
            'payer_name'        => $this->Billto->getName(),
            'store_name'        => $Shop->getCompany(),
            'txn_id'            => $this->pmt_txn_id,
            'pi_url'            => SHOP_URL,
            'pi_admin_url'      => SHOP_ADMIN_URL,
            'dl_links'          => $dl_links,
            'buyer_uid'         => $this->uid,
            'user_name'         => $user_name,
            'gateway_name'      => $this->pmt_method,
            'pmt_method'        => $this->pmt_method,
            'pending'           => $this->status == 'pending' ? 'true' : '',
            'gw_msg'            => $gw_msg,
            'status'            => $this->status,
            'order_instr'       => $this->instructions,
            'order_id'          => $this->order_id,
            'token'             => $this->token,
            'email_extras'      => implode('<br />' . LB, $email_extras),
            'order_date'        => $this->order_date->format($_SHOP_CONF['datetime_fmt'], true),
            'order_url'         => $this->buildUrl('view'),
        ) );
        //), '', false, false);

        $this->_setAddressTemplate($T);

        // If any part of the order is paid by gift card, indicate that and
        // calculate the net amount paid by shop, etc.
        if ($this->by_gc > 0) {
            $T->set_var(array(
                'by_gc'     => $Cur->FormatValue($this->by_gc),
                'net_total' => $Cur->Format($total_amount - $this->by_gc),
            ) );
            //), '', false, false);
        }

        // Show the remaining gift card balance, if any.
        $gc_bal = \Shop\Products\Coupon::getUserBalance($this->uid);
        if ($gc_bal > 0) {
            $T->set_var(array(
                'gc_bal_fmt' => $Cur->Format($gc_bal),
                'gc_bal_num' => $gc_bal,
            ) );
            //), '', false, false);
        }

        // parse templates for subject/text
        $T->set_var(
            'purchase_details',
            $T->parse('detail', 'msg_body') //,
//            '', false, false
        );
        $text = $T->parse('text', 'msg');
        return $text;
    }


    /**
     * Get the miscellaneous charges on this order.
     * Just a shortcut to adding up the non-item charges.
     *
     * @return  float   Total "other" charges, e.g. tax, shipping, etc.
     */
    public function miscCharges()
    {
        return $this->shipping + $this->handling + $this->tax;
    }


    /**
     * Check the user's permission to view this order or cart.
     *
     * @param   string  $token  Token provided by the user, if any
     * @return  boolean     True if allowed to view, False if denied.
     */
    public function canView($token='')
    {
        global $_USER;

        if ($this->isNew) {
            // Record not found in DB, or this is a cart (not an order)
            return false;
        } elseif (
            ($this->uid > 1 && $_USER['uid'] == $this->uid) ||
            plugin_ismoderator_shop()
        ) {
            // Administrator, or logged-in buyer
            return true;
        } elseif ($token !== '' && $token == $this->token) {
            // Correct token provided via parameter
            return true;
        } elseif (isset($_GET['token']) && $_GET['token'] == $this->token) {
            // Anonymous with the correct token
            return true;
        } else {
            // Unauthorized
            return false;
        }
    }


    /**
     * Get all the log entries for this order.
     *
     * @return  array   Array of log entries
     */
    public function getLog()
    {
        global $_TABLES, $_CONF;

        $log = array();
        $sql = "SELECT * FROM {$_TABLES['shop.order_log']}
            WHERE order_id = '" . DB_escapeString($this->order_id) . "'";
        $res = DB_query($sql);
        while ($L = DB_fetchArray($res, false)) {
            $log[] = $L;
        }
        return $log;
    }


    /**
     * Calculate the tax on this order.
     * Sets the tax and tax_items properties and returns the tax amount.
     *
     * @return  float   Sales Tax amount
     */
    public function calcTax()
    {
        if ($this->Shipto === NULL) {
            $this->tax = 0;
            return $this;
        }
        $C = Currency::getInstance($this->currency);
        $tax = 0;
        $this->tax_items = 0;
        foreach ($this->items as &$Item) {
            $this->tax_items += $Item->getTaxable();
            $tax += $Item->getTax();
        }
        if ($this->tax_shipping) {
            $tax += $C->RoundVal($this->tax_rate * $this->shipping);
        }
        if ($this->tax_handling) {
            $tax += $C->RoundVal($this->tax_rate * $this->handling);
        }
        $this->tax = $C->FormatValue($tax);
        return $this;
    }


    /**
     * Calculate the total shipping fee for this order.
     * Sets $this->shipping, no return value.
     */
    public function calcShipping()
    {
        // Only calculate shipping if there are physical items,
        // otherwise shipping = 0
        if ($this->hasPhysical()) {
            $shipper_id = $this->shipper_id;
            $shippers = Shipper::getShippersForOrder($this);
            $have_shipper = false;
            if ($shipper_id !== NULL) {
                // Array is 0-indexed so search for the shipper ID, if any.
                foreach ($shippers as $id=>$shipper) {
                    if ($shipper->getID() == $shipper_id) {
                        // Use the already-selected shipper, if any.
                        // The ship_method var should already be set.
                        $this->shipping = $shippers[$id]->getOrderShipping()->total_rate;
                        $have_shipper = true;
                        break;
                    }
                }
            }
            if (!$have_shipper) {
                // If the specified shipper isn't found for some reason,
                // get the first shipper available, which will be the best rate.
                $shipper = reset($shippers);
                $this->ship_method = $shipper->getName();
                $this->shipping = $shipper->getOrderShipping()->total_rate;
            }
        } else {
            $this->shipping = 0;
        }
        return $this;
    }


    /**
     * Calculate total additional charges: tax, shipping and handling..
     * Simply totals the amounts for each item.
     *
     * @return  float   Total additional charges
     */
    public function calcTotalCharges()
    {
        global $_SHOP_CONF;

        $this->handling = 0;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            $this->handling += $P->getHandling($item->getQuantity());
        }
        $this->calcTax()   // Tax calculation is slightly more complex
            ->calcShipping();
        return $this;
    }


    /**
     * Create a random token string for this order.
     * Allows anonymous users to view the order from an email link.
     *
     * @return  string      Token string
     */
    private function _createToken()
    {
        $len = 12;      // Actual length of the token needed.
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($len / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($len / 2));
        } else {
            $options = array(
                'length'    => ceil($len / 2),
                'letters'   => 3,       // mixed case
                'numbers'   => true,    // include numbers
                'symbols'   => true,    // include symbols
                'mask'      => '',
            );
            $bytes = \Shop\Products\Coupon::generate($options);
        }
        return substr(bin2hex($bytes), 0, $len);
    }


    /**
     * Set a new token on the order.
     * Used after an action is performed to prevent the same action from
     * happening again accidentally.
     *
     * @return  object  $this
     */
    public function setToken()
    {
        global $_TABLES;

        $token = $this->_createToken();
        $sql = "UPDATE {$_TABLES['shop.orders']}
            SET token = '" . DB_escapeString($token) . "'
            WHERE order_id = '" . DB_escapeString($this->order_id) . "'";
        DB_query($sql, 1);
        if (!DB_error()) {
            $this->token = $token;
        }
        return $this;
    }


    /**
     * Get the order total, including tax, shipping and handling.
     *
     * @return  float   Total order amount
     */
    public function getTotal()
    {
        $total = 0;
        foreach ($this->items as $id => $item) {
            $total += ($item->getPrice() * $item->getQuantity());
        }
        // Remove any discount amount.
        $total -= $this->getDiscountAmount();
        if ($this->status == 'cart') {
            // Re-calculate all charges in case of changes.
            $this->calcTotalCharges();
        }
        $total += $this->shipping + $this->tax + $this->handling;
        return Currency::getInstance()->RoundVal($total);
    }


    /**
     * Set the isAdmin field to indicate whether admin access is being requested.
     *
     * @param   boolean $isAdmin    True to get admin view, False for user view
     * @return  object      Current Order object
     */
    public function setAdmin($isAdmin = false)
    {
        $this->isAdmin = $isAdmin == false ? false : true;
        return $this;
    }


    /**
     * Create the order ID.
     * Since it's transmitted in cleartext, it'd be a good idea to
     * use something more "encrypted" than just the session ID.
     * On the other hand, it can't be too random since it needs to be
     * repeatable.
     *
     * @return  string  Order ID
     */
    protected static function _createID()
    {
        global $_TABLES;
        if (function_exists('CUSTOM_shop_orderID')) {
            $func = 'CUSTOM_shop_orderID';
        } else {
            $func = 'COM_makeSid';
        }
        do {
            $id = COM_sanitizeID($func());
        } while (DB_getItem($_TABLES['shop.orders'], 'order_id', "order_id = '$id'") !== NULL);
        return $id;
    }


    /**
     * Check if an item already exists in the cart.
     * This can be used to determine whether to add the item or not.
     * Check for "false" return value as the return may be zero for the
     * first item in the cart.
     *
     * @param   string  $item_id    Item ID to check, e.g. "1|2,3,4"
     * @param   array   $extras     Option custom values, e.g. text fields
     * @return  integer|boolean Item cart record ID if item exists in cart, False if not
     */
    public function Contains($item_id, $extras=array())
    {
        $id_parts = SHOP_explode_opts($item_id, true);

        if (!isset($id_parts[1])) $id_parts[1] = '';
        $args = array(
            'product_id'    => $id_parts[0],
            'variant'       => $id_parts[1],
            'extras'        => $extras,
        );
        $Item2 = new OrderItem($args);
        foreach ($this->items as $id=>$Item1) {
            if ($Item1->Matches($Item2)) {
                return $id;
            }
        }
        // No matching item_id found
        return false;
    }


    /**
     * Get the requested address array.
     * Converts internal vars named 'billto_name', etc. to an array keyed by
     * the base field namee 'name', 'address1', etc. The result can be passed
     * to the Address class.
     *
     * @param   string  $type   Type of address, billing or shipping
     * @param   array   $A      Data array, such as the order record
     * @return  array           Array of name=>value address elements
     */
    public function getAddress($type, $A=NULL)
    {
        if ($type != 'billto') {
            $type = 'shipto';
        }
        $fields = array();
        foreach ($this->_addr_fields as $name) {
            $var = $type . '_' . $name;
            $fields[$var] = isset($A[$var]) ? $A[$var] : '';
        }
        return $fields;
    }


    /**
     * Get the cart info from the private m_info array.
     * If no key is specified, the entire m_info array is returned.
     * If a key is specified but not found, the NULL is returned.
     *
     * @param   string  $key    Specific item to return
     * @return  mixed       Value of item, or entire info array
     */
    public function getInfo($key = '')
    {
        if ($key != '') {
            if (isset($this->m_info[$key])) {
                return $this->m_info[$key];
            } else {
                return NULL;
            }
        } else {
            return $this->m_info;
        }
    }


    /**
     * Get all the items in this order
     *
     * @return  array   Array of OrderItem objects
     */
    public function getItems()
    {
        return $this->items;
    }


    /**
     * Get a single OrderItem object from this order.
     * Returns an empty OrderItem object if the product is not found.
     *
     * @param   mixed   $item_id    OrderItem product ID
     * @return  object      OrderItem object
     */
    public function getItem($item_id)
    {
        foreach ($this->items as $Item) {
            if ($Item->product_id == $item_id) {
                return $Item;
            }
        }
        return new OrderItem;
    }


    /**
     * Set an info item into the private info array.
     *
     * @param   string  $key    Name of var to set
     * @param   mixed   $value  Value to set
     * @return  object      Current Order object
     */
    public function setInfo($key, $value)
    {
        $this->m_info[$key] = $value;
        return $this;
    }


    /**
     * Remove an information item from the private info array.
     *
     * @param   string  $key    Name of var to remove
     */
    public function remInfo($key)
    {
        unset($this->m_info[$key]);
        return $this;
    }


    /**
     * Get the gift card amount applied to this cart.
     *
     * @return  float   Gift card amount
     */
    public function getGC()
    {
        return (float)$this->getInfo('apply_gc');
    }


    /**
     * Apply a gift card amount to this cart.
     *
     * @param   float   $amt    Amount of credit to apply
     * @return  object      Current Order object
     */
    public function setGC($amt)
    {
        $amt = (float)$amt;
        if ($amt == -1) {
            $gc_bal = \Shop\Products\Coupon::getUserBalance();
            $amt = min($gc_bal, \Shop\Products\Coupon::canPayByGC($this));
        }
        if ($amt != $this->getInfo('apply_gc')) {
            $this->setInfo('apply_gc', $amt);
            $this->tainted = true;
        }
        return $this;
    }


    /**
     * Set the chosen payment gateway into the cart information.
     * Used so the gateway will be pre-selected if the buyer returns to the
     * cart update page.
     *
     * @param   string  $gw_name    Gateway name
     * @return  object      Current Order object
     */
    public function setGateway($gw_name)
    {
        if ($gw_name != $this->getInfo('gateway')) {
            $this->setInfo('gateway', $gw_name);
            $this->tainted = true;
        }
        $this->setPmtMethod($gw_name);
        return $this;
    }


    /**
     * Check if this order has any physical items.
     * Used to adapt workflows based on product types.
     *
     * @return  integer     Number of physical items x quantity
     */
    public function hasPhysical()
    {
        $retval = 0;
        foreach ($this->items as $id=>$item) {
            if ($item->getProduct()->isPhysical()) {
                $retval += $item->getQuantity();
            }
        }
        return $retval;
    }


    /**
     * Check if there are any taxable items on this order.
     *
     * @return  integer     Number of taxable items
     */
    public function hasTaxable()
    {
        $retval = 0;
        foreach ($this->items as $id=>$item) {
            if ($item->getProduct()->isTaxable()) {
                $retval += $item->getQuantity();
            }
        }
        return $retval;
    }


    /**
     * Check if this order has only downloadable items.
     *
     * @return  boolean     True if download only, False if now.
     */
    public function isDownloadOnly()
    {
        foreach ($this->items as $id=>$item) {
            if (!$item->getProduct()->isDownload(true)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Check if this order is paid.
     * Starting with v1.3.0 the payment table is checked for total payments.
     *
     * @return  boolean     True if not a cart or pending order, false otherwise
     */
    public function isPaid()
    {
        return $this->_amt_paid >= $this->total;
    }


    /**
     * Get shipping information for the items to use when selecting a shipper.
     *
     * @return  array   Array('units'=>unit_count, 'amount'=> fixed per-item amount)
     */
    public function getItemShipping()
    {
        $shipping_amt = 0;
        $shipping_units = 0;
        foreach ($this->items as $item) {
            $shipping_amt += $item->getShipping();
            $shipping_units += $item->getShippingUnits();
        }
        return array(
            'units' => $shipping_units,
            'amount' => $shipping_amt,
        );
    }


    /**
     * Set the buyer email to the supplied email address.
     * First checks that the supplied address is a valid one.
     *
     * @param   string  $email  Email address
     * @return  object      Current Order object
     */
    public function setEmail($email)
    {
        if (COM_isEmail($email)) {
            $this->buyer_email = $email;
        }
        return $this;
    }


    /**
     * Set shipper information in the info array, including the best rate.
     *
     * @param   integer $shipper_id     Shipper record ID
     * @return  object      Current Order object
     */
    public function setShipper($shipper_id)
    {
        if ($this->hasPhysical()) {
            $shippers = Shipper::getShippersForOrder($this);
            // Have to iterate through all the shippers since the array is
            // ordered by rate, not shipper ID
            foreach ($shippers as $sh) {
                if ($sh->getID()  == $shipper_id) {
                    $this->shipping = $sh->getOrderShipping()->total_rate;
                    $this->shipper_id = $sh->getID();
                    break;
                }
            }
        } else {
            $this->shipping = 0;
            $this->shipper_id = 0;
        }
        return $this;
    }


    /**
     * Select the shipping method for this order.
     * Displays a list of shippers with the rates for each
     * @todo    1. Sort by rate DONE
     *          2. Save shipper selection with the order
     *
     *  @param  integer $step   Current step in workflow
     *  @return string      HTML for shipper selection form
     */
    public function selectShipper()
    {
        if (!$this->hasPhysical()) {
            return '';
        }

        // Get all the shippers and rates for the selection
        $shippers = Shipper::getShippersForOrder($this);
        if (empty($shippers)) return '';

        // Get the best or previously-selected shipper for the default choice
        $best = NULL;
        $shipper_id = $this->shipper_id;
        if ($shipper_id !== NULL) {
            // Array is 0-indexed so search for the shipper ID, if any.
            foreach ($shippers as $id=>$shipper) {
                if ($shipper->getID() == $shipper_id) {
                    // Already have a shipper selected
                    $best = $shippers[$id];
                    break;
                }
            }
        }
        if ($best === NULL) {
            // None already selected, grab the first one. It has the best rate.
            $best = reset($shippers);
        }
        if (!$best) {
            // Error getting shippers, shouldn't happen unless shippers have been deleted.
            $this->shipper_id = 0;
            $this->shipping = 0;
            return '';
        }
        $this->shipper_id = $best->getID();
        $this->shipping = $best->getOrderShipping()->total_rate;

        $T = SHOP_getTemplate('shipping_method', 'form');
        $T->set_block('form', 'shipMethodSelect', 'row');

        // Save the base charge (total items and handling, exclude tax if present)
        $base_chg = $this->gross_items + $this->handling + $this->tax;
        $ship_rates = array();
        foreach ($shippers as $shipper) {
            $sel = $shipper->getID() == $best->getID() ? 'selected="selected"' : '';
            $s_amt = $shipper->getOrderShipping()->total_rate;
            $rate = array(
                'amount'    => (string)Currency::getInstance()->FormatValue($s_amt),
                'total'     => (string)Currency::getInstance()->FormatValue($base_chg + $s_amt),
            );
            $ship_rates[$shipper->getID()] = $rate;
            $T->set_var(array(
                'method_sel'    => $sel,
                'method_name'   => $shipper->getName(),
                'method_rate'   => Currency::getInstance()->Format($s_amt),
                'method_id'     => $shipper->getID(),
                'order_id'      => $this->order_id,
                'multi'         => count($shippers) > 1 ? true : false,
            ) );
            $T->parse('row', 'shipMethodSelect', true);
        }
        $T->set_var('shipper_json', json_encode($ship_rates));
        $T->parse('output', 'form');
        return  $T->finish($T->get_var('output'));
    }


    /**
     * Set all the billing and shipping address vars into the template.
     *
     * @param   object  $T      Template object
     */
    private function _setAddressTemplate(&$T)
    {
        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (Workflow::getAll($this) as $key => $wf) {
            $T->set_var('have_' . $wf->wf_name, 'true');
        }
        $billto = $this->Billto->toArray();
        $shipto = $this->Shipto->toArray();
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fldname = $type . '_' . $name;
                $T->set_var($fldname, $$type[$name]);
            }
        }
    }


    /**
     * Determine if an order is final, that is, cannot be updated or deleted.
     *
     * @param   string  $status     Status to check, if not the current status
     * @return  boolean     True if order is final, False if still a cart or pending
     */
    public function isFinal($status = NULL)
    {
        if ($status === NULL) {     // checking current status
            $status = $this->status;
        }
        return !in_array($status, self::$nonfinal_statuses);
    }


    /**
     * Convert from one currency to another.
     *
     * @param   string  $new    New currency, configured currency by default
     * @param   string  $old    Original currency, $this->currency by default
     * @return  object      Current Order object
     */
    public function convertCurrency($new ='', $old='')
    {
        global $_SHOP_CONF;

        if ($new == '') $new = $_SHOP_CONF['currency'];
        if ($old == '') $old = $this->currency;
        // If already set, return OK. Nothing to do.
        if ($new != $old) {
            // Update each item's pricing
            foreach ($this->items as $Item) {
                $Item->convertCurrency($old, $new);
            }

            // Update the currency amounts stored with the order
            foreach (array('tax', 'shipping', 'handling') as $fld) {
                $this->$fld = Currency::Convert($this->$fld, $new, $old);
            }

            // Set the order's currency code to the new value and save.
            $this->currency = $new;
            $this->Save();
        }
        return true;
    }


    /**
     * Provide a central location to get the URL to print or view a single order.
     *
     * @param   string  $view   View type (order or print)
     * @return  string      URL to the view/print page
     */
    public function buildUrl($view)
    {
        return COM_buildUrl(SHOP_URL . "/order.php?mode=$view&id={$this->order_id}&token={$this->token}");
    }


    /**
     * Check if there are any non-cart orders or IPN messages in the database.
     * Used to determine if data can be migrated from Paypal.
     *
     * @return  boolean     True if orders table is empty
     */
    public static function haveOrders()
    {
        global $_TABLES;

        return (
            (int)DB_getItem(
                $_TABLES['shop.orders'],
                'count(*)',
                "status <> 'cart'"
            ) > 0 ||
            IPN::Count() > 0
        );
    }


    /**
     * Get the base language name from the full string contained in the user record.
     * Wrapper for Customer::getLanguage().
     *
     * @see     Customer::getLanguage()
     * @param   boolean $fullname   True to return full name of language
     * @return  string  Language name for the buyer.
     */
    private function _getLangName($fullname = false)
    {
        $Cust = Customer::getInstance($this->uid);
        return $Cust->getLanguage($fullname);
    }


    /**
     * Loads the requested language array to send email in the recipient's language.
     * If $requested is an array, the first valid language file is loaded.
     * If not, the $requested language file is loaded.
     * If $requested doesn't refer to a vailid language, then $_CONF['language']
     * is assumed.
     *
     * After loading the base language file, the same filename is loaded from
     * language/custom, if available. The admin can override language strings
     * by creating a language file in that directory.
     *
     * @param   mixed   $requested  A single or array of language strings
     * @return  array       $LANG_SHOP, the global language array for the plugin
     */
    public static function loadLanguage($requested)
    {
        global $_CONF;

        // Add the requested language, which may be an array or
        // a single item.
        if (is_array($requested)) {
            $languages = $requested;
        } else {
            // If no language requested, load the site/user default
            $languages = array($requested);
        }

        // Add the site language as a failsafe
        $languages[] = $_CONF['language'];

        // Final failsafe, include "english.php" which is known to exist
        $languages[] = 'english';

        // Search the array for desired language files, in order.
        $langpath = SHOP_PI_PATH . '/language';
        foreach ($languages as $language) {
            if (file_exists("$langpath/$language.php")) {
                include "$langpath/$language.php";
                // Include admin-supplied overrides, if any.
                if (file_exists("$langpath/custom/$language.php")) {
                    include "$langpath/custom/$language.php";
                }
                break;
            }
        }
        return $LANG_SHOP;
    }


    /**
     * Get the total quantity of items with the same base item ID.
     * Used to calculate prices where discounts apply.
     * Similar to self::Contains() but this only considers the base item ID
     * and ignores option selections rather than looking for an exact match.
     *
     * @param   mixed   $item_id    Item ID to check
     * @return  float       Total quantity of items on the order.
     */
    public function getTotalBaseItems($item_id)
    {
        static $qty = array();

        // Extract the item ID if options were included in the parameter
        $x = explode('|', $item_id);
        $item_id = $x[0];
        if (!isset($qty[$item_id])) {
            $qty[$item_id] = 0;
            foreach ($this->items as $item) {
                if ($item->getProductId() == $item_id) {
                    $qty[$item_id] += $item->getQuantity();
                }
            }
        }
        return $qty[$item_id];
    }


    /**
     * Apply quantity discounts to all like items on the order.
     * This allows all items of the same product to be considered for the
     * discount regardless of options chosen.
     * If the return value is true then the cart/order should be saved. False
     * is returned if there were no changes.
     *
     * @param   mixed   $item_id    Base Item ID
     * @return  boolean     True if any prices were changed, False if not.
     */
    public function applyQtyDiscounts($item_id)
    {
        $have_changes = false;
        $x = explode('|', $item_id);
        $item_id = $x[0];

        // Get the product item and see if it has any quantity discounts.
        // If not, just return.
        $P = Product::getByID($item_id);
        if (!$P->hasDiscounts()) {
            return false;
        }

        $total_qty = $this->getTotalBaseItems($item_id);
        foreach ($this->items as $key=>$OI) {
            if ($OI->getProductID() != $item_id) continue;
            $qty_discount = $P->getDiscount($total_qty);
            $new_price = $P->getDiscountedPrice($total_qty, $OI->getOptionsPrice());
            $OI->setPrice($new_price);
            $OI->setDiscount($qty_discount);
            $OI->Save();
        }
        return true;
    }


    /**
     * Purge all orders from the database.
     * No safety check or confirmation is done; that should be done before
     * calling this function.
     */
    public static function Purge()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['shop.orders']}");
        DB_query("TRUNCATE {$_TABLES['shop.orderitems']}");
        DB_query("TRUNCATE {$_TABLES['shop.oi_opts']}");
        DB_query("TRUNCATE {$_TABLES['shop.order_log']}");
    }


    /**
     * Create the complete tag to link to the packing list for this order.
     *
     * @param   string  $order_id   Order ID
     * @param   string  $target     Target, defaule = "_blank"
     * @return  string      Complete tag
     */
    public static function linkPackingList($order_id, $target='_blank')
    {
        global $LANG_SHOP;

        return COM_createLink(
            '<i class="uk-icon-mini uk-icon-list"></i>',
           SHOP_ADMIN_URL . '/report.php?pdfpl=' . $order_id,
            array(
                'class' => 'tooltip',
                'title' => $LANG_SHOP['packinglist'],
                'target' => $target,
            )
        );
    }


    /**
     * Create the complete tag to link to the print view of this order.
     *
     * @param   string  $order_id   Order ID
     * @param   string  $token      Access token
     * @param   string  $target     Target, defaule = "_blank"
     * @return  string      Complete tag
     */
    public static function linkPrint($order_id, $token='', $target = '_blank')
    {
        global $LANG_SHOP;

        $url = SHOP_URL . '/order.php?mode=pdforder&id=' . $order_id;
        if ($token != '') $url .= '&token=' . $token;
        return COM_createLink(
            '<i class="uk-icon-mini uk-icon-print"></i>',
            COM_buildUrl($url),
            array(
                'class' => 'tooltip',
                'title' => $LANG_SHOP['print'],
                'target' => $target,
            )
        );
    }


    /**
     * Get the total shipping units for this order.
     * Called from the Shipper class when calculating shipping options.
     *
     * @return  float   Total shipping units
     */
    public function totalShippingUnits()
    {
        $units = 0;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            if ($P->isPhysical()) {
                $units += $P->getShippingUnits() * $item->getQuantity();
            }
        }
        return $units;
    }


    /**
     * Create PDF output of one or more orders.
     *
     * @param   array   $ids    Array of order IDs
     * @param   string  $type   View type, 'pl' or 'order'
     * @param   boolean $isAdmin    True if run by an administrator
     * @return  boolean     True on success, False on error
     */
    public static function printPDF($ids, $type='pdfpl', $isAdmin = false)
    {
        USES_lglib_class_html2pdf();
        try {
            if (class_exists('\\Spipu\\Html2Pdf\\Html2Pdf')) {
                $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en');
            } else {
                $html2pdf = new \HTML2PDF('P', 'A4', 'en');
            }
            //$html2pdf->setModeDebug();
            $html2pdf->setDefaultFont('Arial');
        } catch(HTML2PDF_exception $e) {
            SHOP_log($e);
            return false;
        }

        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $ord_id) {
            $O = self::getInstance($ord_id);
            $O->setAdmin($isAdmin);
            if ($O->isNew) {
                continue;
            }
            $content = $O->View($type);
            //echo $content;die;
            try {
                $html2pdf->writeHTML($content);
            } catch(HTML2PDF_exception $e) {
                SHOP_log($e);
                return false;
            }
        }
        $html2pdf->Output($type . 'list.pdf', 'I');
        return true;
    }


    /**
     * Force the user ID to a given value.
     * Used by the gateway to set the correct user during IPN processing.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    public function setUid($uid)
    {
        $this->uid = (int)$uid;
        return $this;
    }


    /**
     * Get the customer (user) ID
     *
     * @return  integer     User ID
     */
    public function getUid()
    {
        return (int)$this->uid;
    }


    /**
     * Get the order ID.
     *
     * @return  string  Order ID
     */
    public function getOrderID()
    {
        return $this->order_id;
    }


    /**
     * Set the order date.
     *
     * @param   mixed   $dt Date/Time or timestamp to set
     * @return  object  $this
     */
    public function setOrderDate($dt=NULL)
    {
        global $_CONF;

        if ($dt === NULL) {
            $dt = time();
        }
        if (is_numeric($dt)) {
            $this->order_date->setTimestamp($dt);
        } else {
            $this->order_date = new \Date($dt, $_CONF['timezone']);
        }
        return $this;
    }


    /**
     * Get the order date.
     *
     * @return  object  Date object
     */
    public function getOrderDate()
    {
        return $this->order_date;
    }


    /**
     * Get the Billing address.
     *
     * @return  object  Address object with billing information
     */
    public function getBillto()
    {
        return $this->Billto;
    }


    /**
     * Get the Shipping address.
     *
     * @return  object  Address object with shipping information
     */
    public function getShipto()
    {
        return $this->Shipto;
    }


    /**
     * Set the amount paid by gift card.
     *
     * @param   float   $amt    Amount paid by GC
     * @return  object  $this
     */
    public function setByGC($amt)
    {
        $this->by_gc = (float)$amt;
        return $this;
    }


    /**
     * Set the payment method for the order (to be deprecated).
     *
     * @param   string  $method Payment method/gateway name
     * @return  object  $this
     */
    public function setPmtMethod($method)
    {
        $this->pmt_method = $method;
        return $this;
    }


    /**
     * Set the payment transaction used to pay the order.
     *
     * @deprecated
     * @param   string  $txn_id Transaction ID
     * @return  object  $this
     */
    public function setPmtTxnID($txn_id)
    {
        $this->pmt_txn_id = $txn_id;
        return $this;
    }


    /**
     * Set the username to show in the order log entries.
     *
     * @param   string  $name   User/system name to show
     * @return  object  $this
     */
    public function setLogUser($name)
    {
        $this->log_user = $name;
        return $this;
    }


    /**
     * Set the buyer's email address.
     *
     * @param   string  $email  Buyer email address
     * @return  object  $this
     */
    public function setBuyerEmail($email)
    {
        $this->buyer_email = $email;
        return $this;
    }


    /**
     * Get the buyer's email address.
     *
     * @return  string      Buyer email address
     */
    public function getBuyerEmail()
    {
        return $this->buyer_email;
    }


    /**
     * Get the buyer's instructions for the order.
     *
     * @return  string      Special instructions
     */
    public function getInstructions()
    {
        return $this->instructions;
    }


    /**
     * Get the shipping info block for display on order views.
     *
     * @return  string      HTML for shipping info block
     */
    public function getShipmentBlock()
    {
        global $_CONF;

        $Shipments = Shipment::getByOrder($this->order_id);
        if (empty($Shipments)) {
            return '';
        }
        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('html', 'shipping_block.thtml');
        $T->set_block('html', 'Packages', 'packages');
        foreach ($Shipments as $Shipment) {
            $Packages = $Shipment->getPackages();
            if (empty($Packages)) {
                // Create a dummy package so something shows for the shipment
                $Packages = array(new ShipmentPackage());
            }
            $show_ship_info = true;
            foreach ($Packages as $Pkg) {
                $Shipper = Shipper::getInstance($Pkg->getShipperID());
                $url = $Shipper->getTrackingUrl($Pkg->getTrackingNumber());
                $T->set_var(array(
                    'show_ship_info' => $show_ship_info,
                    'ship_date'     => $Shipment->getDate()->toMySQL(true),
                    'shipment_id'   => $Shipment->getID(),
                    'shipper_info'  => $Pkg->getShipperInfo(),
                    'tracking_num'  => $Pkg->getTrackingNumber(),
                    'shipper_id'    => $Pkg->getShipperID(),
                    'tracking_url'  => $url,
                    'ret_url'       => urlencode($_SERVER['REQUEST_URI']),
                ) );
                $show_ship_info = false;
                $T->parse('packages', 'Packages', true);
            }
        }
        $T->parse('output', 'html');
        $html = $T->finish($T->get_var('output'));
        return $html;
    }


    /**
     * Get the token assigned to this order.
     *
     * @return  string  Token string
     */
    public function getToken()
    {
        return $this->token;
    }


    /**
     * Get all shipment objects related to this order.
     *
     * @return  array   Array of Shipment objects
     */
    public function getShipments()
    {
        return Shipment::getByOrder($this->order_id);
    }


    /**
     * Get the total number of items yet to be shipped.
     * Returns max items to ship or zero, if for some reason extra shipments
     * were created.
     * Only considers physical products.
     *
     * @return  integer     Total items (quantitity) to be shipped
     */
    public function itemsToShip()
    {
        $gross_items = 0;
        $shipped_items = 0;
        foreach ($this->items as $oi_id=>$data) {
            if ($data->getProduct()->isPhysical()) {
                $gross_items += $data->getQuantity();
                $shipped_items += ShipmentItem::getItemsShipped($oi_id);
            }
        }
        return max(0, $gross_items - $shipped_items);
    }


    /**
     * Check if this order has been completely shipped.
     *
     * @return  boolean     True if no further shipment is needed
     */
    public function isShippedComplete()
    {
        $shipped = array();
        $Shipments = $this->getShipments();
        foreach ($Shipments as $Shipment) {
            foreach ($Shipment->getItems() as $SI) {
                if (!isset($shipped[$SI->getOrderItemID()])) {
                    $shipped[$SI->getOrderItemID()] = $SI->getQuantity();
                } else {
                    $shipped[$SI->getOrderItemID()] += $SI->getQuantity();
                }
            }
        }
        foreach ($this->getItems() as $OI) {
            if (!$OI->getProduct()->isPhysical()) {
                continue;
            }
            if (
                !isset($shipped[$OI->getID()]) ||
                $shipped[$OI->getID()] < $OI->getQuantity()
            ) {
                return false;
            }
        }
        return true;
    }


    /**
     * Set the order status to a new value.
     *
     * @param   string  $newstatus  New status to set
     * @return  object  $this
     */
    public function setStatus($newstatus)
    {
        global $LANG_SHOP;

        if (array_key_exists($newstatus, $LANG_SHOP['orderstatus'])) {
            $this->status = $newstatus;
        } else {
            SHOP_log("Invalid log status '{$newstatus}' specified for order {$this->getID()}");
        }
        return $this;
    }


    /**
     * Set the sales tax rate for this order.
     * No action if the new rate is the same as the existing rate.
     *
     * @param   float   $new_rate   New tax rate
     * @return  object  $this
     */
    public function setTaxRate($new_rate)
    {
        global $_TABLES;

        $new_rate = (float)$new_rate;
        //if ($this->tax_rate != $new_rate) {
        $this->tax_rate = (float)$new_rate;
        foreach ($this->getItems() as $Item) {
            if ($Item->isTaxable()) {
                $Item->setTaxRate($this->tax_rate);
                $Item->Save();
            }
        }
        $this->calcTax();
        // See if tax is charged on shipping and handling.
        if ($this->tax_rate > 0) {
            $State = State::getInstance($this->Shipto);
            $this->setTaxShipping($State->taxesShipping())
                ->setTaxHandling($State->taxesHandling());
        } else {
            $this->setTaxShipping(0)
                ->setTaxHandling(0);
        }
        DB_query(
            "UPDATE {$_TABLES['shop.orders']} SET
                tax_rate = {$this->tax_rate},
                tax_shipping = {$this->tax_shipping},
                tax_handling = {$this->tax_handling}
            WHERE order_id = '{$this->order_id}'"
        );
        return $this;
    }


    /**
     * Get the sales tax rate applied to this order.
     *
     * @return  float       Sales tax rate
     */
    public function getTaxRate()
    {
        return (float)$this->tax_rate;
    }


    /**
     * Set the discount code applied to this order.
     *
     * @param   string  $code   Discount code
     * @return  object  $this
     */
    public function setDiscountCode($code)
    {
        $this->discount_code = strtoupper(trim($code));
        return $this;
    }


    /**
     * Get the discount code applied to this order, if any.
     *
     * @return  string      Discount code
     */
    public function getDiscountCode()
    {
        return $this->discount_code;
    }


    /**
     * Set the discount percentage applied from a discount code.
     *
     * @param   float   $pct    Discount percentage
     * @return  object  $this
     */
    public function setDiscountPct($pct)
    {
        $this->discount_pct = (float)$pct;
        return $this;
    }


    /**
     * Get the total shipping charge for this order.
     *
     * @return  float       Shipping charge
     */
    public function getShipping()
    {
        return (float)$this->shipping;
    }


    /**
     * Get the total handling charge for this order.
     *
     * @return  float       Handling charge
     */
    public function getHandling()
    {
        return (float)$this->handling;
    }


    /**
     * Get the total sales tax for this order.
     *
     * @return  float       Sales Tax
     */
    public function getTax()
    {
        return (float)$this->tax;
    }


    /**
     * Get the discount percentage applied from a discount code.
     *
     * @return  float   $pct    Discount percentage
     */
    public function getDiscountPct()
    {
        return (float)$this->discount_pct;
    }


    /**
     * Determine if the discount code entry field can be shown.
     * This wrapper allows for future conditions based on group menbership,
     * existence of sale prices, etc. but currently just shows the field if
     * there are any active codes.
     *
     * @return  boolean     True if the field can be shown, False if not.
     */
    private function canShowDiscountEntry()
    {
        return DiscountCode::countCurrent() > 0 ? true : false;
    }


    /**
     * Get the amount of the discount yielded by the discount code.
     *
     * @return  float   Discount amount
     */
    public function getDiscountAmount()
    {
        return max(
            $this->getCurrency()->RoundVal(
                $this->gross_items - $this->net_nontax - $this->net_taxable
            ),
            0
        );
    }


    /**
     * Get the currency object for this order.
     *
     * @return  object      Currency object
     */
    public function getCurrency()
    {
        return Currency::getInstance($this->currency);
    }


    /**
     * Get the record ID of the requested shipper.
     *
     * @return  integer      Shipper record ID
     */
    public function getShipperID()
    {
        return (int)$this->shipper_id;
    }



    /**
     * Apply a discount code to the order and all items.
     * The discount code and discount percent must be set in the order first.
     *
     * @return  object  $this
     */
    protected function applyDiscountCode()
    {
        global $_TABLES;

        $sql = "UPDATE {$_TABLES['shop.orders']} SET
            discount_code = '" . DB_escapeString($this->discount_code) . "',
            discount_pct = '" . (float)$this->discount_pct . "'
            WHERE order_id = '" . (int)$this->order_id . "'";
        DB_query($sql);
        if (!DB_error()) {
            foreach ($this->items as $id=>$Item) {
                $this->items[$id]->applyDiscountPct($this->getDiscountPct());
            }
        }
        return $this;
    }


    /**
     * Validate a discount code. If valid, save the elements in the order.
     * Doesn't update the order if the code is valid, in case a valid code
     * was previously entered.
     *
     * @param   string  $code   Entered discount code, current code if null
     * @return  boolean     True if valud, False if not.
     */
    public function validateDiscountCode($code='')
    {
        // Get the existing values to see if either has changed.
        $have_code = $this->getDiscountCode();
        $have_pct = $this->getDiscountPct();

        // If no code is supplied, check the existing discount code.
        if (empty($code)) {         // could be null or empty string
            $code = $have_code;
        }

        // Still empty? Then the order has no code.
        if (empty($code)) {
            return true;
        }

        // Now check that the code is valid. It may have expired, or the order
        // total may have changed.
        if (!empty($code)) {
            $DC = DiscountCode::getInstance($code);
            $this->calcItemTotals();
            $pct = $DC->Validate($this->gross_items);
        }

        // If the code and percentage have not changed, just return true.
        // Otherwise update the discount in the order and items.
        if ($pct == $have_pct && $code == $have_code) {
            return true;
        }

        if ($pct > 0) {
            // Valid code, set the new values.
            $this->setDiscountCode($code);
            $this->setDiscountPct($pct);
            $msg = $DC->getMessage();
            $status = true;
        } else {
            // Invalid code, remove it from the order.
            $this->setDiscountCode('');
            $this->setDiscountPct(0);
            $msg = $DC->getMessage();
            if ($have_code) {
                // If there was a valid code, indicate that it has been removed
                $msg .= ' ' . $LANG_SHOP['dc_removed'];
            }
            $status = false;
        }
        $this->applyDiscountCode();      // apply code to order and items
        COM_setMsg($msg, $status ? 'info' : 'error', $status);
        return $status;
    }


    /**
     * Calculate the net items totals, taxable and nontaxable.
     */
    protected function calcItemTotals()
    {
        $this->net_nontax = $this->net_taxable = $this->gross_items = $this->net_items = 0;
        foreach ($this->items as $Item) {
            //$Item->Save();
            $item_gross = $Item->getPrice() * $Item->getQuantity();
            $item_net = $Item->getNetPrice() * $Item->getQuantity();
            $this->gross_items += $item_gross;
            $this->net_items += $item_net;
            if ($Item->isTaxable()) {
                $this->net_taxable += $item_net;
            } else {
                $this->net_nontax += $item_net;
            }
        }
        return $this;
    }


    /**
     * Get the total order value including miscellaneous charges.
     * Also calls functions to set internal values.
     *
     * @uses    self::calcItemTotals()
     * @uses    self::calcTax()
     * @return  float   Total order amount
     */
    protected function getOrderTotal()
    {
        $this->calcItemTotals()->calcTax();
        return (float)$this->net_items + $this->shipping + $this->tax + $this->handling;
    }


    /**
     * Check the zone rules for each item, and mark those that aren't allowed.
     * Also sets `$this->hasInvalid` if any invalid items are found so the
     * checkout button can be suppressed.
     *
     * @return  object  $this
     */
    private function checkRules()
    {
        $this->hasInvalid = false;
        foreach ($this->items as $id=>$Item) {
            if ($Item->getProduct()->getRuleID() > 0) {
                $status = $Item->getInvalid();
                $Rule = $Item->getProduct()->getRule();
                if (!$Rule->isOK($this->getShipto())) {
                    $Item->setInvalid(true);
                    $this->hasInvalid = true;
                } else {
                    $Item->setInvalid(false);
                }
            }
        }
    }


    /**
     * Get all the payments recorded for this order.
     *
     * @return  array       Array of Payment objects.
     */
    public function getPayments()
    {
        return Payment::getByOrder($this->order_id);
    }


    /**
     * Get the total amount paid against this order.
     *
     * @return  float       Total amount paid
     */
    public function getAmountPaid()
    {
        return (float)$this->_amt_paid;
    }


    /**
     * Utility function to get any amount field.
     *
     * @see     updateTotals()
     * @param   string  $fld_name   Name of internal field
     * @return  float       Field value
     */
    public function getAmount($fld_name)
    {
        return (float)$this->$fld_name;
    }


    /**
     * Maintenance function to sync order total fields from item records.
     *
     * @param   string  $ord_id     Optional order ID, all orders if empty
     */
    public static function updateTotals($ord_id = '')
    {
        global $_TABLES;

        if ($ord_id != '') {
            // Simulate the result from DB_fetchAll()
            $A = array(
                array(
                    'order_id' => $ord_id,
                ),
            );
        } else {
            $sql = "SELECT order_id
                FROM {$_TABLES['shop.orders']}";
            $res = DB_query($sql);
            $A = DB_fetchAll($res, false);
        }
        $decimals = array();
        foreach ($A as $data) {
            $Ord = self::getInstance($data['order_id']);
            $gross_items = 0;
            $net_taxable = 0;
            $net_nontax = 0;
            $tax = 0;
            $shipping = $Ord->getShipping();
            $handling = $Ord->getHandling();
            $currency = $Ord->getCurrency();
            $curcode = $currency->getCode();
            if (!isset($decimals[$curcodei])) {
                $decimals[$curcode] = $currency->Decimals();
            }
            $dec = $decimals[$curcode];
            foreach ($Ord->getItems() as $Item) {
                echo "Processing item {$Item->getID()}\n";
                $gross_item = round($Item->getPrice(), $dec);
                $net_item = round($Item->getNetPrice(), $dec);
                $gross_items += $gross_item * $Item->getQuantity();
                if ($Item->isTaxable()) {
                    $net_taxable += round($net_item * $Item->getQuantity(), $dec);
                    $item_tax = round($net_item * $Item->getTaxRate(), $dec);
                } else {
                    $net_nontax += round($net_item * $Item->getQuantity(), $dec);
                    $item_tax = 0;
                }
                $tax += $item_tax;
                if ($item_tax != $Item->getTax()) {
                    $sql = "UPDATE {$_TABLES['shop.orderitems']}
                        SET tax = $tax
                        WHERE id = '{$Item->getID()}'";
                    DB_query($sql);
                }
            }
            $order_total = $net_taxable + $net_nontax + $shipping + $handling + $tax;
            $order_total = round($order_total, $dec);
            if (
                $order_total != $Ord->getAmount('order_total') ||
                $net_taxable != $Ord->getAmount('net_taxable') ||
                $net_nontax != $Ord->getAmount('net_nontax') ||
                $tax != $Ord->getAmount('tax')
            ) {
                $sql = "UPDATE {$_TABLES['shop.orders']} SET
                    order_total = $order_total,
                    tax = $tax,
                    net_nontax = $net_nontax,
                    net_taxable = $net_taxable
                    WHERE order_id = '{$Ord->getOrderID()}'";
               DB_query($sql);
            }
        }
    }

}

?>
