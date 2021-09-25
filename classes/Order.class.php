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
use Shop\Models\OrderState;
use Shop\Models\ShippingQuote;
use Shop\Models\CustomInfo;
use Shop\Models\Token;
use Shop\Models\ReferralTag;
use Shop\Models\Session;
use Shop\Models\AffiliateSale;


/**
 * Order class.
 * @package shop
 */
class Order
{
    /** Flag to indicate that administrative actions are being done.
     * @var boolean */
    private $isAdmin = false;

    /** Flag to indicate that this order has been finalized.
     * This is not related to the order status, but only to the current view
     * in the workflow.
     * @var boolean */
    private $isFinalView = false;

    /** Session variable name for storing cart info.
     * @var string */
    protected static $session_var = 'glShopCart';

    /** Flag to indicate that this is a new record.
     * @var boolean */
    protected $isNew = true;

    /** Miscellaneious information values used by the Cart class.
     * @var array */
    protected $m_info = NULL;

    /** Flag to indicate that "no shipping" should be set.
     * @deprecated ?
     * @var boolean */
    private $no_shipping = 1;

    /** Address field names.
     * @var array */
    protected $_addr_fields = array(
        'name', 'company', 'address1', 'address2',
        'city', 'state', 'zip', 'country', 'phone',
    );

    /** OrderItem objects.
     * @var array */
    protected $items = array();

    /** Order item total, excluding discount codes.
      @var float */
    protected $gross_items = 0;

    /** Order final total, incl. shipping, handling, etc.
     * @var float */
    protected $order_total = 0;

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

    /** Last modified timestamp.
     * @var string */
    protected $last_mod = '';

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

    /** Order status string, pending, processing, shipped, etc.
     * @var string */
    protected $status = 'cart';

    /** Currency code.
     * @var string */
    private $currency = '';

    /** Selected payment method (gateway name).
     * @var string */
    private $pmt_method = '';

    /** Payment method text description
     * @var string */
    private $pmt_dscp = '';

    /** Experimental flag to mark whether an order needs to be saved.
     * @var boolean */
    protected $tainted = true;

    /** Flag to indicate that there are invalid items on the order.
     * @var boolean */
    private $hasInvalid = false;

    /** Amount paid on the order. Not part of the order record.
     * @var float */
    private $_amt_paid = 0;

    /** Amount paid by gift card.
     * @var float */
    private $by_gc = 0;

    /** Username to show in log messages.
     * @var string */
    private $log_user = '';

    /** Buyer's email address.
     * @var string */
    protected $buyer_email = '';

    /** Shipper record ID.
     * Default is negative to indicate "undefined".
     * @var integer */
    protected $shipper_id = -1;

    /** Shipping method code.
     * Code unique to the shipper/carrier, e.g. "usps.05".
     * @var string */
    protected $shipping_method = '';

    /** Shipping description.
     * Full text description, e.g. "USPS Priority Mail".
     * @var string */
    protected $shipping_dscp = '';

    /** Referral tag value.
     * @var string */
    protected $referral_token = '';

    /** Referring user's glFusion user ID.
     * @var integer */
    protected $referrer_uid = 0;

    /** Expiration timestamp for the referral.
     * @var integer */
    protected $referral_exp = 0;

    /** Holder for custom information.
     * Used only by Cart, required here to create checkout button from orders.
     * @var array */
    public $custom_info = array();

    /** Payment gateway order reference.
     * @var string */
    private $gw_order_ref = '';

    /** Object for customer info.
     * @var object */
    private $Customer = NULL;


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $id     Optional order ID to read
     */
    public function __construct($id='')
    {
        global $_USER;

        $this->uid = (int)$_USER['uid'];
        $this->currency = Config::get('currency');
        if (!empty($id)) {
            $this->order_id = $id;
            if (!$this->Load($id)) {
                $this->isNew = true;
                $this->items = array();
            } else {
                $this->isNew = false;
            }
        }
        $this->Customer = Customer::getInstance($this->uid);
        if ($this->isNew) {
            if (empty($id)) {
                // Only create a new ID if one wasn't supplied.
                // Carts may supply an ID that needs to be static.
                $this->order_id = self::_createID();
            }
            $this->order_date = SHOP_now();
            $this->token = Token::create();
            $this->shipping = 0;
            $this->handling = 0;
            $this->by_gc = 0;
            $this->Billto = new Address;
            $this->Shipto = new Address;
            $this->m_info = new CustomInfo;
        }
    }


    /**
     * Get an object instance for an order.
     *
     * @param   string|array    $key    Order ID or record
     * @param   integer         $uid    User ID (used by Cart class)
     * @return  object          Order object
     */
    public static function getInstance($key, $uid = 0)
    {
        if (is_array($key)) {
            $id = SHOP_getVar($key, 'order_id');
        } else {
            $id = $key;
        }

        if (!empty($id)) {
            if (!is_string($id)) {
                var_dump(debug_backtrace(0));die;
            }
            $retval = new self($id);
        } else {
            $retval = new self;
            $id = $retval->getOrderId();
        }
        return $retval;
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

        if ($id != '') {
            $this->order_id = $id;
        }

        $sql = "SELECT ord.*,
            ( SELECT sum(pmt_amount) FROM {$_TABLES['shop.payments']} pmt
            WHERE pmt.pmt_order_id = ord.order_id AND is_complete = 1
            ) as amt_paid
            FROM {$_TABLES['shop.orders']} ord
            WHERE ord.order_id='{$this->order_id}'";
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
        $this->items = OrderItem::getByOrder($this->order_id);
        $this->tainted = false;
        return true;
    }


    /**
     * Get orders that are not fully paid, optionally limiting to a buyer.
     *
     * @param   integer $uid    Optional use ID to limit search
     * @return  array       Array of Order objects
     */
    public static function getUnpaid($uid = 0)
    {
        global $_TABLES;

        $retval = array();
        if ($uid > 0) {
            $uid_where = " WHERE uid = " . (int)$uid;
        } else {
            $uid_where = '';
        }
        $sql = "SELECT ord.*,
            ord.order_total - ifnull(sum(pmt.pmt_amount),0) as amtDue,
            ifnull(sum(pmt.pmt_amount),0) as amt_paid
            FROM {$_TABLES['shop.orders']} ord
            LEFT JOIN {$_TABLES['shop.payments']} pmt
            ON pmt.pmt_order_id = ord.order_id
            $uid_where
            GROUP BY ord.order_id
            HAVING amtDue > 0";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['order_id']] = new self();
            $retval[$A['order_id']]->setVars($A);
        }
        return $retval;
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

        if (Config::get('aff_enabled') && $this->getReferralToken() == '') {
            $token = ReferralTag::get();
            if ($token) {
                $this->setReferralToken($token, true);
            }
        }

        // Set the product_id if it is not supplied but the item_id is,
        // which is formated as "id|opt1,opt2,..."
        if (!isset($args['product_id'])) {
            $item_id = explode('|', $args['item_id']);  // TODO: DEPRECATE
            $args['product_id'] = $item_id[0];
        }
        $pov_ids = array();
        if (isset($args['options']) && is_array($args['options'])) {
            foreach ($args['options'] as $pov) {
                $pov_ids[] = $pov->getID();
            }
        }
        $PV = ProductVariant::getByAttributes($args['product_id'], $pov_ids);
        $args['variant_id'] = $PV->getID();
        $args['order_id'] = $this->order_id;    // make sure it's set
        $args['token'] = Token::create();  // create a unique token
        $OI = OrderItem::fromArray($args);
        if (isset($args['price'])) {
            $OI->setPrice($args['price']);
        }
        $override_price = isset($args['override']) ? $args['price'] : NULL;
        $OI->setQuantity($args['quantity'], $override_price);
        $OI->applyDiscountPct($this->getDiscountPct())
            ->setTaxRate($this->tax_rate)
            ->Save();
        $this->items[] = $OI;
        $this->calcTotalCharges();
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
        if ($A === NULL) {
            $have_address = true;
            $this->Billto = new Address();
        } elseif (is_object($A)) {
            $this->Billto = $A;
            $have_address = true;
        } elseif (is_array($A)) {
            foreach (array('useaddress', 'addr_id', 'id') as $key) {
                if (isset($A[$key])) {
                    $addr_id = (int)$A[$key];
                    break;
                }
            }
            if ($addr_id > 0) {
                // If set, the user has selected an existing address. Read
                // that value and use it's values.
                Session::set('billing', $addr_id);
                $this->Billto = Address::getInstance($addr_id);
                $this->Billto->fromArray($A);
                $this->Billto->setID($addr_id);
            } else {
                $this->Billto = new Address($A);
            }
            $have_address = true;
        }
        if ($have_address) {
            $this->updateRecord(array(
                "billto_id   = '{$this->Billto->getID()}'",
                "billto_name = '" . DB_escapeString($this->Billto->getName()) . "'",
                "billto_company = '" . DB_escapeString($this->Billto->getCompany()) . "'",
                "billto_address1 = '" . DB_escapeString($this->Billto->getAddress1()) . "'",
                "billto_address2 = '" . DB_escapeString($this->Billto->getAddress2()) . "'",
                "billto_city = '" . DB_escapeString($this->Billto->getCity()) . "'",
                "billto_state = '" . DB_escapeString($this->Billto->getState()) . "'",
                "billto_country = '" . DB_escapeString($this->Billto->getCountry()) . "'",
                "billto_zip = '" . DB_escapeString($this->Billto->getPostal()) . "'",
                "billto_phone = '" . DB_escapeString($this->Billto->getPhone()) . "'"
            ) );
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
        $addr_id = 0;
        if ($A === NULL) {
            $this->Shipto = new Address;
            $have_address = true;
        } elseif (is_array($A)) {
            foreach (array('useaddress', 'addr_id', 'id') as $key) {
                if (isset($A[$key])) {
                    $addr_id = (int)$A[$key];
                    break;
                }
            }
            if ($addr_id > 0) {
                // If set, read and use an existing address
                Session::set('shipping', $this->shipto_id);
                $this->Shipto = Address::getInstance($this->shipto_id);
                $this->Shipto->fromArray($A);
                $this->Shipto->setID($addr_id);
            } else {
                $this->Shipto = new Address($A);
            }
            $have_address = true;
        } elseif (is_object($A)) {
            $this->Shipto = $A;
            $have_address = true;
        } elseif (is_int($A)) {
            $this->Shipto = new Address($A);
            $have_address = true;
        }

        if ($have_address) {
            $this->updateRecord(array(
                "shipto_id   = '{$this->Shipto->getID()}'",
                "shipto_name = '" . DB_escapeString($this->Shipto->getName()) . "'",
                "shipto_company = '" . DB_escapeString($this->Shipto->getCompany()) . "'",
                "shipto_address1 = '" . DB_escapeString($this->Shipto->getAddress1()) . "'",
                "shipto_address2 = '" . DB_escapeString($this->Shipto->getAddress2()) . "'",
                "shipto_city = '" . DB_escapeString($this->Shipto->getCity()) . "'",
                "shipto_state = '" . DB_escapeString($this->Shipto->getState()) . "'",
                "shipto_country = '" . DB_escapeString($this->Shipto->getCountry()) . "'",
                "shipto_zip = '" . DB_escapeString($this->Shipto->getPostal()) . "'",
                "shipto_phone = '" . DB_escapeString($this->Shipto->getPhone()) . "'",
                "tax_rate = '{$this->getTaxRate()}'",
                "tax = '{$this->getTax()}'",
                "shipper_id = 0",   // clear to force shipper re-quoting
            ) );
        }
        $this->setTaxRate(NULL);
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
        $this->pmt_dscp = SHOP_getVar($A, 'pmt_dscp');
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
        $this->m_info = new CustomInfo(SHOP_getVar($A, 'info'));
        //if ($this->m_info === false) $this->m_info = array();
        /*foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fld = $type . '_' . $name;
                $this->$fld = $A[$fld];
            }
        }*/
        $this->Billto = (new Address())->fromArray(
            $this->getAddressArray('billto', $A), 'billto'
        );
        $this->Shipto = (new Address())->fromArray(
            $this->getAddressArray('shipto', $A), 'shipto'
        );
        if (isset($A['uid'])) $this->uid = $A['uid'];

        if (isset($A['order_id']) && !empty($A['order_id'])) {
            $this->order_id = $A['order_id'];
            $this->isNew = false;
            Session::set('order_id', $A['order_id']);
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
        }
        $this->shipping_method = $A['shipping_method'];
        $this->shipping_dscp = $A['shipping_dscp'];
        $this->last_mod = $A['last_mod'];
        $this->gw_order_ref = SHOP_getVar($A, 'gw_order_ref', 'string', NULL);
        $this->referrer_uid = SHOP_getVar($A, 'referrer_uid', 'integer');
        $this->referral_token = SHOP_getVar($A, 'referral_token');
        $this->referral_exp = SHOP_getVar($A, 'referral_exp', 'integer');
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


    public function getTotal()
    {
        return (float)$this->order_total;
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
     * Get the invoice number based on the configured starting number.
     * Returns an empty string if a sequence number has not been assigned,
     * otherwise returns either starting_number + sequence or concatenates
     * the sequence to the starting number if the starting number is not an
     * actual number.
     *
     * Always returns a string for consistency.
     *
     * @since   v1.3.1
     * @return  string      Invoice number
     */
    public function getInvoiceNumber()
    {
        global $_SHOP_CONF;

        if ($this->order_seq == 0) {
            $inv_num = '';
        } elseif (
            isset($_SHOP_CONF['inv_start_num']) &&
            !empty($_SHOP_CONF['inv_start_num'])
        ) {
            if (is_numeric($_SHOP_CONF['inv_start_num'])) {
                // just add the prefix number, e.g. "10001"
                $inv_num = $_SHOP_CONF['inv_start_num'] + $this->order_seq;
            } elseif (strpos($_SHOP_CONF['inv_start_num'], '%') !== false) {
                // formatted string, e.g. "INV-0001-2020"
                $inv_num = sprintf($_SHOP_CONF['inv_start_num'], $this->order_seq);
            } else {
                // prefix string, e.g. "INV-1"
                $inv_num = (string)$_SHOP_CONF['inv_start_num'] . (string)$this->order_seq;
            }
        } elseif (function_exists('CUSTOM_shop_invoiceNumber')) {
            $inv_num = CUSTOM_shop_invoiceNumber($this->order_seq, $this);
        } else {
            $inv_num = $this->order_seq;
        }
        return (string)$inv_num;
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
            $order_id = Session::get('order_id');
        }
        if (!$order_id) {
            // Still an empty order ID, nothing to do
            return true;
        }

        // Just get an instance of this order since there are a couple of values to check.
        $Ord = self::getInstance($order_id);
        if (!$Ord->canDelete()) {
            // Order can't be deleted
            return false;
        }

        // Checks passed, delete the order and items
        $order_id = DB_escapeString($order_id);
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
    public function Save($save_items=false)
    {
        global $_TABLES, $_SHOP_CONF;

        // Do not save an order if the plugin version is not current.
        // May fail due to schema changes.
        if (!SHOP_isMinVersion()) {
            return '';
        }

        // Save all the order items
        if ($save_items) {
            foreach ($this->items as $item) {
                $item->Save();
            }
        }
        $order_total = $this->calcOrderTotal();
        $db_order_id = DB_escapeString($this->order_id);
        if ($this->isNew) {
            // Shouldn't have an empty order ID, but double-check
            if ($this->order_id == '') $this->order_id = self::_createID();
            if ($this->Billto->getName() == '') {
                $this->billto_name = COM_getDisplayName($this->uid);
            }
            Session::set('order_id', $this->order_id);
            // Set field values that can only be set once and not updated
            $sql1 = "INSERT INTO {$_TABLES['shop.orders']} SET
                    order_id = '{$db_order_id}',
                    token = '" . DB_escapeString($this->token) . "',
                    uid = " . (int)$this->uid . ", ";
            $sql2 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.orders']} SET ";
            $sql2 = " WHERE order_id = '{$db_order_id}'";
        }

        $fields = array(
            "order_date = '{$this->order_date->toUnix()}'",
            "status = '{$this->status}'",
            //"pmt_txn_id = '" . DB_escapeString($this->pmt_txn_id) . "'",
            "pmt_method = '" . DB_escapeString((string)$this->pmt_method) . "'",
            "pmt_dscp = '" . DB_escapeString($this->pmt_dscp) . "'",
            "by_gc = '{$this->by_gc}'",
            //"phone = '" . DB_escapeString($this->phone) . "'",
            "tax = '{$this->getTax()}'",
            "shipping = '{$this->getShipping()}'",
            "handling = '{$this->getHandling()}'",
            "gross_items = '{$this->gross_items}'",
            "net_nontax = '{$this->net_nontax}'",
            "net_taxable = '{$this->net_taxable}'",
            "instructions = '" . DB_escapeString($this->instructions) . "'",
            "buyer_email = '" . DB_escapeString($this->buyer_email) . "'",
            //"info = '" . DB_escapeString(@serialize($this->m_info)) . "'",
            "info = '" . DB_escapeString((string)$this->m_info) . "'",
            "tax_rate = {$this->getTaxRate()}",
            "currency = '{$this->currency}'",
            "discount_code = '" . DB_escapeString($this->discount_code) . "'",
            "discount_pct = {$this->getDiscountPct()}",
            "order_total = {$order_total}",
            "shipper_id = {$this->getShipperID()}",
            "shipping_method = '" . DB_escapeString($this->shipping_method) . "'",
            "shipping_dscp = '" . DB_escapeString($this->shipping_dscp) . "'",
            "gw_order_ref = '" . DB_escapeString($this->gw_order_ref) . "'",
            "referral_token = '" . DB_escapeString($this->referral_token) . "'",
            "referrer_uid = {$this->referrer_uid}",
            "referral_exp = {$this->referral_exp}",
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
        //SHOP_log("Save: " . $sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        $this->isNew = false;
        $this->tainted = false;
        return $this->order_id;
    }



    /**
     * Update some fields in the Orders table.
     * This is to avoid the overhead from calling Save().
     *
     * @param   string|array    $vals   One or an array of value strings
     * @return  object  $this
     */
    public function updateRecord($vals)
    {
        global $_TABLES;

        if (is_array($vals)) {
            $vals = implode(',', $vals);
        }
        $sql = "UPDATE {$_TABLES['shop.orders']} SET $vals
            WHERE order_id = '" . DB_escapeString($this->order_id) . "'";
        DB_query($sql);
        /*echo $sql;
        var_dump(debug_backtrace(0));die;
        echo $this->order_id;die;
        exit;*/
        return $this;
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
        $V = new \Shop\Views\Invoice;
        return $V->withOrder($this)->Render();
    }


    /**
     * If the order is paid, move its status from `pending` to `processing`.
     * Only updates the order if the status is pending, not if it has already
     * been move further along.
     *
     * @return  object  $this
     */
    public function updatePmtStatus()
    {
        // Recalculate amount paid in case this order is cached.
        $Pmts = $this->getPayments();
        $total_paid = 0;
        foreach ($Pmts as $Pmt) {
            $total_paid += $Pmt->getAmount();
        }
        $this->_amt_paid = $total_paid;

        if (
            (
                $this->getStatus() == OrderState::CART ||
                $this->getStatus() == OrderState::PENDING ||
                $this->getStatus() == OrderState::INVOICED
            ) &&
            $this->isPaid()
        ) {
            // Automatically set the order status after payment, unless it has
            // already been set to Processing or higher.
            if (!$this->statusAtLeast(OrderState::PROCESSING)) {
                if ($this->hasPhysical()) {
                   $this->updateStatus(OrderState::PROCESSING);
                } else {
                    // No physical items, consider the order closed.
                    $this->updateStatus(OrderState::CLOSED);
                }
            }
        }
        return $this;
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
        global $_TABLES, $LANG_SHOP, $_SHOP_CONF;

        //var_dump(debug_backtrace(0, 2));
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
            if (!$this->verifyReferralTag()) {
                // If the referrer is invalid, remove from the order record
                $other_updates = ", referrer_uid = {$this->referrer_uid},
                    info = '" . DB_escapeString((string)$this->m_info) . "'";
            } else {
                $other_updates = '';
            }
            $sql = "START TRANSACTION;
                SELECT COALESCE(MAX(order_seq)+1,1) FROM {$_TABLES['shop.orders']} INTO @seqno FOR UPDATE;
                UPDATE {$_TABLES['shop.orders']} SET
                    status = '". DB_escapeString($this->status) . "',
                    order_seq = @seqno
                    $other_updates
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
        //SHOP_log($sql, SHOP_LOG_DEBUG);
        if (DB_error()) {
            $this->status = $oldstatus;     // update in-memory object
            return $oldstatus;
        }

        // Process affiliate bonus if the required status has been reached
        if (
            Config::get('aff_enabled') &&
            $this->statusAtLeast(Config::get('aff_min_ordstatus'))
        ) {
            AffiliateSale::create($this);
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
     * Save the referral information with the order.
     *
     * @return  object  $this
     */
    public function saveReferral()
    {
        if ($this->referrer_uid > 0) {
            $exp = (int)(time() + (Config::get('aff_cart_exp_days') * 86400));
        } else {
            $exp = 0;
        }
        $this->updateRecord(array(
            "referral_token = '" . DB_escapeString($this->referral_token) . "'",
            "referrer_uid = " . $this->referrer_uid,
            "referral_exp = " . $exp,
        ) );
        return $this;
    }


    /**
     * Verify that the referrer token is valid, if present.
     * Also resets the token and referring user ID if the token is invalid.
     *
     * @return  boolean     True if valid or not present, False if invalid
     */
    public function verifyReferralTag()
    {
        if ($this->referral_exp > 0 && $this->referral_exp < time()) {
            // Referral has expired, remove it and save to the DB.
            $this->referrer_uid = 0;
            $this->referral_token = '';
            $this->saveReferral();
            return false;
        } else {
            // Save the original values to detect changes.
            $_uid = $this->referrer_uid;
            $_token = $this->referral_token;

            // Set the current value.
            // This also validates the token and sets it to empty if invalid.
            $this->setReferralToken($this->referral_token, true);

            // Finally, check that the values are unchanged, indicating
            // a valid token is already set.
            if (
                $this->referrer_uid != $_uid ||
                $this->referral_token != $_token
            ) {
                return false;
            } else {
                return true;
            }
        }
    }


    /**
     * Complete the purchase when payment or invoicing is complete.
     *
     * @param   object  $IPN    IPN data object
     */
    public function handlePurchase($IPN=NULL)
    {
        foreach ($this->getItems() as $Item) {
            $Item->getProduct()->handlePurchase($Item, $IPN);
        }
        /*if ($this->hasPhysical()) {
            $this->updateStatus(OrderState::PROCESSING);
        } else {
            $this->updateStatus(OrderState::SHIPPED);
        }*/
        return $this;
    }


    /**
     * Get a count of active orders by user ID.
     * Used to determine if a customer is active.
     *
     * @return  integer     Count of actual orders
     */
    public static function countActiveByUser($uid)
    {
        global $_TABLES;

        static $count = array();
        $uid = (int)$uid;
        if (isset($count[$uid])) {
            return $count[$uid];
        } else {
            $sql = "SELECT order_id FROM {$_TABLES['shop.orders']}
                WHERE uid = {$uid}
                AND status NOT IN ('cart', 'pending', 'cancelled', 'refunded')";
            $res = DB_query($sql);
            $count[$uid] = DB_numRows($res);
        }
        return $count[$uid];
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
     * Send an email to the administrator and/or buyer.
     *
     * @param   string  $status     Order status (pending, paid, etc.)
     * @param   string  $gw_msg     Optional gateway message to include with email
     * @param   boolean $force      True to force notification
     * @param   boolean $toadmin    True to include admin email, default=true
     * @return  object  $this
     */
    public function Notify($status='', $gw_msg='', $force=false, $toadmin=true)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        // Check if any notification is to be sent for this status update.
        $notify_buyer = OrderStatus::getInstance($status)->notifyBuyer();
        $notify_admin = OrderStatus::getInstance($status)->notifyAdmin() && $toadmin;
        if (!$force && !$notify_buyer && !$notify_admin) {
            return $this;
        }

        $Shop = new Company;
        if ($force || $notify_buyer) {
            $save_language = $LANG_SHOP;    // save the site language
            $save_userlang = $_CONF['language'];
            $_CONF['language'] = $this->Customer->getLanguage(true);
            $LANG_SHOP = self::loadLanguage($_CONF['language']);
            // Set up templates, using language-specific ones if available.
            // Fall back to English if no others available.
            $T = new Template(array(
                'notify/' . $this->Customer->getLanguage(),
                'notify/' . COM_getLanguageName(),
                'notify/english',
                'notify', // catch templates using language strings
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
            $T = new Template(array(
                'notify/' . COM_getLanguageName(),
                'notify/english',
                'notify', // catch templates using language strings
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
        return $this;
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
        $has_downloads = false; // Assume no downloads
        $item_total = 0;
        $dl_links = '';         // Start with empty download links
        $email_extras = array();
        $U = Customer::getInstance($this->uid);
        $Cur = Currency::getInstance($this->currency);   // get currency object for formatting
        $Shop = new Company;
        if ($this->pmt_method != '') {
            $Gateway = Gateway::getInstance($this->pmt_method);
            $gw_dscp = $Gateway->getDscp();
        } else {
            $gw_dscp = '';
        }

        foreach ($this->items as $id=>$item) {
            $P = $item->getProduct();

            // Add the file to the filename array, if any. Download
            // links are only included if the order status is 'paid'
            $file = $P->getFilename();
            if (!empty($file) && $this->status == 'paid') {
                $has_downloads = true;
                /*$files[] = $file;
                $dl_url = SHOP_URL . '/download.php?';
                // There should always be a token, but fall back to the
                // product ID if there isn't
                if ($item->getToken() != '') {
                    $dl_url .= 'token=' . urlencode($item->getToken());
                    $dl_url .= '&i=' . $item->getID();
                } else {
                    $dl_url .= 'id=' . $item->getProductId();
                }
                $dl_links .= "<a href=\"$dl_url\">$dl_url</a><br />";*/
            }

            $ext = $item->getQuantity() * $item->getPrice();
            $item_total += $ext;

            $T->set_block('msg_body', 'ItemList', 'List');
            $T->set_var(array(
                'qty'   => $item->getQuantity(),
                'price' => $Cur->FormatValue($item->getPrice()),
                'ext'   => $Cur->FormatValue($ext),
                'name'  => $item->getDscp(),
                'options_text' => $item->getOptionDisplay(),
                'extras_text' => $item->getExtraDisplay(),
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

        if ($incl_trk) {        // include tracking information block for buyers
            $order_url = $this->buildUrl('view');
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
            //'txn_id'            => $this->pmt_txn_id,
            'pi_url'            => SHOP_URL,
            'pi_admin_url'      => SHOP_ADMIN_URL,
            'dl_links'          => $dl_links,
            'buyer_uid'         => $this->uid,
            'user_name'         => $user_name,
            'gateway_name'      => $gw_dscp,
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
            'has_downloads'     => $has_downloads,
        ) );
        if ($this->_amt_paid > 0) {
            $T->set_var(array(
                'pmt_amount' => $Cur->formatValue($this->_amt_paid),
                'due_amount' => $Cur->formatValue($total_amount - $this->_amt_paid),
            ) );
        }
        //), '', false, false);

        // Add the affiliate information, if enabled
        if (Config::get('aff_enabled')) {
            $T->set_var(array(
                'affiliate_id' => $U->getAffiliateId(),
                'affiliate_info' => Config::get('aff_info_url'),
                'affiliate_link' => $_CONF['site_url'] . '/index.php?' .
                    Config::get('aff_key', 'shop_ref') . '=' . $U->getAffiliateId(),
            ) );
        }

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
     * Get the order sequence number.
     * Used to check if the order has been invoiced or paid.
     *
     * @return  integer|NULL    Order sequence number
     */
    public function getSeqNum()
    {
        return $this->order_seq;
    }


    /**
     * Get the last-modified timestamp.
     *
     * @param   boolean $unix   True to get as a Unix timestamp
     * @return  string      DateTime that order was last modified
     */
    public function getLastMod($unix=false)
    {
        global $_CONF;
        if ($unix) {
            $dt = new \Date($this->last_mod, $_CONF['timezone']);
            return $dt->toUnix();
        } else {
            return $this->last_mod;
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
        $this->calcTax();   // Tax calculation is slightly more complex
        $this->Save();
        return $this;
    }


    /**
     * Set a new token on the order.
     * Used after an action is performed to prevent the same action from
     * happening again accidentally. Only available to non-final orders since
     * the token may be used to validate payment callbacks, etc. and shoule
     * not be changed once the order is final.
     *
     * @return  object  $this
     */
    public function setToken()
    {
        if (!$this->isFinal()) {
            $this->token = Token::create();
            return $this->updateRecord("token = '" . DB_escapeString($this->token) . "'");
        } else {
            return $this;
        }
    }


    /**
     * Get the order total, including tax, shipping and handling.
     *
     * @return  float   Total order amount
     */
    public function calcTotal()
    {
        global $_TABLES;

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
        $this->order_total = Currency::getInstance()->RoundVal($total);
        $this->updateRecord(array(
            "tax = {$this->tax}",
            "shipping = {$this->shipping}",
            "handling = {$this->handling}",
            "order_total = {$this->order_total}"
        ) );
        return $this->order_total;
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
     * @param   array   $options_text   Ad-hoc options added from plugins
     * @return  integer|boolean Item cart record ID if item exists in cart, False if not
     */
    public function Contains($item_id, $extras=array(), $options_text=array())
    {
        $id_parts = SHOP_explode_opts($item_id, true);

        if (!isset($id_parts[1])) $id_parts[1] = 0;
        $args = array(
            'product_id'    => $id_parts[0],
            'variant_id'    => $id_parts[1],
            'extras'        => $extras,
            'options_text'  => $options_text,
            'quantity'      => 1,
        );
        $Item2 = OrderItem::fromArray($args);
        foreach ($this->items as $id=>$Item1) {
            if ($Item1->Matches($Item2)) {
                return $id;
            }
        }
        // No matching item_id found
        return false;
    }


    /**
     * Get the requested address object.
     * Converts internal vars named 'billto_name', etc. to an array keyed by
     * the base field named 'name', 'address1', etc. The result can be passed
     * to the Address class.
     *
     * @param   string  $type   Type of address, billing or shipping
     * @return  object      Address object
     */
    public function getAddress($type)
    {
        switch ($type) {
        case 'shipto':
            return $this->Shipto;
            break;
        case 'billto':
            return $this->Billto;
            break;
        }
        return NULL;
    }


    /* Get the requested address array.
     * Converts internal vars named 'billto_name', etc. to an array keyed by
     * the base field named 'name', 'address1', etc. The result can be passed
     * to the Address class.
     *
     * @param   string  $type   Type of address, billing or shipping
     * @param   array   $A      Data array, such as the order record
     * @return  array           Array of name=>value address elements
     */
    private function getAddressArray($type, $A=NULL)
    {
        if ($type != 'billto') {
            $type = 'shipto';
        }
        if (!isset($A[$type . '_id'])) {
            $A[$type . '_id'] = 0;
        }
        $fields = array(
            $type . '_id' => $A[$type . '_id'],
        );
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
     * @deprecate
     * @param   mixed   $item_id    OrderItem product ID
     * @return  object      OrderItem object
     */
    public function getItem($item_id)
    {
        return NULL;
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
        return (float)$this->by_gc;
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
        $this->by_gc = (float)$amt;
        return $this->updateRecord(array(
            "by_gc = {$this->by_gc}",
        ) );
    }


    /**
     * Set the buyer-entered special instructions.
     *
     * @param   string  $text   Instruction text
     * @return  object  $this
     */
    public function setInstructions($text)
    {
        $this->instructions = $text;
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
        $this->setPmtMethod($gw_name);
        return $this;
    }


    /**
     * Set the referral token for this order.
     *
     * @param   string  $ref_id     Token ID
     * @return  object  $this
     */
    public function setReferralToken($ref_id, $save=false)
    {
        if (!empty($ref_id)) {
            $Affiliate = Customer::findByAffiliate($ref_id);

            if ($Affiliate && $Affiliate->getUid() != $this->uid) {
                $this->referral_token = $ref_id;
                $this->referrer_uid = $Affiliate->getUid();
            } else {
                $this->referral_token = '';
                $this->referral_uid = 0;
            }
            if ($save) {
                $this->saveReferral();
            }
        }
        return $this;
    }


    /**
     * Get the order referral token.
     *
     * @return  string      Token ID
     */
    public function getReferralToken()
    {
        return $this->referral_token;
    }


    /**
     * Set the referring user's ID in the order.
     *
     * @param   string  $uid    Referring user ID
     * @return  object  $this
     */
    public function setReferrerId($uid)
    {
        $this->referrer_uid = (int)$uid;
        return $this;
    }


    /**
     * Get the referring user's ID.
     *
     * @return  string      User ID
     */
    public function getReferrerId()
    {
        return $this->referrer_uid;
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
     * Get the payment status for display on the order.
     *
     * @return  string      Payment status (pending, partial, paid, etc.)
     */
    public function getPaymentStatus()
    {
        global $LANG_SHOP;

        if ($this->isPaid()) {
            return $LANG_SHOP['paid'];
        } elseif ($this->_amt_paid > 0) {
            return $LANG_SHOP['partial'];
        } else {
            return '<span class="uk-text-bold uk-text-danger">' .
                $LANG_SHOP['pmt_pending'] . '</span>';
        }
    }


    /**
     * Check if this order is paid.
     * Starting with v1.3.0 the payment table is checked for total payments.
     *
     * @return  boolean     True if not a cart or pending order, false otherwise
     */
    public function isPaid()
    {
        return $this->getBalanceDue() < .001;
    }


    /**
     * Check if this order is invoiced.
     * Used to consider net-terms orders as "complete".
     *
     * @return  boolean     True if status is "invoiced"
     */
    public function isInvoiced()
    {
        return $this->status == OrderState::INVOICED;
    }


    /**
     * Check if the order has been shipped complete.
     *
     * @return  boolean     True if shipped, False if not
     */
    public function isShipped()
    {
        return (
            $this->status == OrderState::SHIPPED ||
            $this->status == OrderState::CLOSED
        );
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
        global $_TABLES;
        if ($shipper_id === NULL) {
            $this->shipper_id = -1;
            $this->shipping = 0;
            $this->shipping_method = '';
            $this->shipping_dscp = '';
        } elseif (is_array($shipper_id)) {
            $this->shipper_id = (int)$shipper_id['shipper_id'];
            $this->shipping = (float)$shipper_id['cost'];
            $this->shipping_method = $shipper_id['svc_code'];
            $this->shipping_dscp = $shipper_id['title'];
        }
        $this->setTaxRate(NULL);
        return $this->updateRecord(array(
            "shipper_id = {$this->shipper_id}",
            "shipping = {$this->shipping}",
            "shipping_method = '" . DB_escapeString($this->shipping_method) . "'",
            "shipping_dscp = '" . DB_escapeString($this->shipping_dscp) . "'",
            "order_total = " . $this->calcTotal(),
        ) );
    }


    /**
     * Get the available shipping options for this order.
     *
     * @return  array   Array of shipping methods
     */
    public function getShippingOptions()
    {
        $retval = array();
        if (!$this->hasPhysical()) {
            return $retval;
        }

        // Get all the shippers and rates for the selection
        // Save the base charge (total items and handling, exclude tax if present)
        $base_chg = $this->gross_items + $this->handling + $this->tax;

        $shipping_units = $this->totalShippingUnits();
        $Shippers = Shipper::getAll(true, $shipping_units);
        $methods = array();
        $item_info = $this->getItemShipping();
        foreach ($Shippers as $code=>$Shipper) {
            $quote = $Shipper->getQuote($this, $item_info);
            if ($this->getTaxShipping()) {
                $tax_rate = $this->getTaxRate();
            } else {
                $tax_rate = 0;
            }
            foreach ($quote as $q) {
                $shipper_id = $q['id'];
                $title = $q['svc_title'];
                $ship_tax = $tax_rate * $q['cost'];
                $order_total = $base_chg + $q['cost'] + $ship_tax;
                $order_tax = $this->tax + $ship_tax;
                $methods[] = array(
                    'shipper_id' => $q['shipper_id'],
                    'svc_code' => $q['svc_code'],
                    'title' => $q['svc_title'],
                    'cost' => Currency::getInstance()->FormatValue($q['cost']),
                    'order_tax' => Currency::getInstance()->FormatValue($order_tax),
                    'order_total' => Currency::getInstance()->FormatValue($order_total),
                );
            }
        }

        // Get all the shippers and rates for the selection
        usort($methods, array(__NAMESPACE__ . '\\Shipper', 'sortQuotes'));
        $best_shipper = NULL;
        $best_method = NULL;
        $shipper_id = $this->shipper_id;
        if ($shipper_id > 0) {
            foreach ($methods as $id=>$method) {
                if (
                    $method['shipper_id'] == $shipper_id &&
                    $method['svc_code'] == $this->shipping_method
                ) {
                    $best_method = $method;
                    break;
                }
            }
        } elseif (!empty($methods)) {
            // No shipper previously specified, set the first method to start.
            $best_method = $methods[0];
        }

        if ($best_method === NULL) {
            // None already selected, grab the first one. It has the best rate.
            usort($methods, array(__NAMESPACE__ . '\\Shipper', 'sortQuotes'));
            $best_method = reset($methods);
            $best_shipper = Shipper::getInstance($best_method['shipper_id']);
        }

        if ($best_method === NULL) {
            $this->setShipper(NULL);
            // None already selected, grab the first one. It has the best rate.
        } else {
            $this->setShipper($best_method);
        }

        $T = new Template;
        $T->set_file('form', 'shipping_method.thtml');
        $T->set_block('form', 'shipMethodSelect', 'row');

        foreach ($methods as $method_id=>$method) {
            $sel = $method['svc_code'] == $this->shipping_method;
            $s_amt = $method['cost'];
            $retval[] = array(
                'method_sel'    => $sel,
                'shipper_id'    => $method['shipper_id'],
                'svc_code'      => $method['svc_code'],
                'method_name'   => $method['title'],
                'method_rate'   => $method['cost'],
                'method_id'     => $method['shipper_id'],
                'order_id'      => $this->order_id,
                'multi'         => count($methods) > 1 ? true : false,
            );
            if (count($methods) == 1) {
                $this->shipper_id = $method_id;
                $this->shipping = $s_amt;
            }
        }
        SESS_setVar('shop.shiprate.' . $this->order_id, $methods);
        return  $retval;
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
        // Save the base charge (total items and handling, exclude tax if present)
        $base_chg = $this->gross_items + $this->handling + $this->tax;

        $shipping_units = $this->totalShippingUnits();
        $Shippers = Shipper::getAll(true, $shipping_units);
        $methods = array();
        foreach ($Shippers as $code=>$Shipper) {
            $cache_key = $Shipper->getID() . '.' . $shipping_units . '.' .
                $this->Shipto->toText() . '.' . $this->last_mod .
                '.' . $this->order_total;
            $cache_key = md5($cache_key);
            $quote = Cache::get($cache_key);
            //$quote = NULL;        // debugging
            if ($quote === NULL) {
                $quote = $Shipper->getQuote($this);
                Cache::set($cache_key, $quote, array('shipping'), 30);
            }
            if ($this->getTaxShipping()) {
                $tax_rate = $this->getTaxRate();
            } else {
                $tax_rate = 0;
            }
            foreach ($quote as $q) {
                $shipper_id = $q['id'];
                $title = $q['svc_title'];
                $ship_tax = $tax_rate * $q['cost'];
                $order_total = $base_chg + $q['cost'] + $ship_tax;
                $order_tax = $this->tax + $ship_tax;
                $methods[] = array(
                    'shipper_id' => $q['shipper_id'],
                    'svc_code' => $q['svc_code'],
                    'title' => $q['svc_title'],
                    'cost' => Currency::getInstance()->FormatValue($q['cost']),
                    'order_tax' => Currency::getInstance()->FormatValue($order_tax),
                    'order_total' => Currency::getInstance()->FormatValue($order_total),
                );
            }
        }
        // Get all the shippers and rates for the selection
        usort($methods, array(__NAMESPACE__ . '\\Shipper', 'sortQuotes'));
        $best_shipper = NULL;
        $best_method = NULL;
        $shipper_id = $this->shipper_id;
        if ($shipper_id > 0) {
            // Array is 0-indexed so search for the shipper ID, if any.
            /*foreach ($shippers as $id=>$shipper) {
                if ($shipper->getID() == $shipper_id) {
                    // Already have a shipper selected
                    $best = $shippers[$id];
                    break;
                }
        }*/
            foreach ($methods as $id=>$method) {
                if (
                    $method['shipper_id'] == $shipper_id &&
                    $method['svc_code'] == $this->shipping_method
                ) {
                    $best_method = $method;
                    break;
                }
            }
        } elseif (!empty($methods)) {
            // No shipper previously specified, set the first method to start.
            $best_method = $methods[0];
        }

        if ($best_method === NULL) {
            // None already selected, grab the first one. It has the best rate.
            usort($methods, array(__NAMESPACE__ . '\\Shipper', 'sortQuotes'));
            $best_method = reset($methods);
            $best_shipper = Shipper::getInstance($best_method['shipper_id']);
        }

        if ($best_method === NULL) {
            $this->setShipper(NULL);
            // None already selected, grab the first one. It has the best rate.
        } else {
            $this->setShipper($best_method);
        }

        $T = new Template;
        $T->set_file('form', 'shipping_method.thtml');
        $T->set_block('form', 'shipMethodSelect', 'row');

        $ship_rates = array();
        foreach ($methods as $method_id=>$method) {
        //foreach ($Shippers as $shipper) {
            //$sel = $shipper->getID() == $best_shipper->getID() ? 'selected="selected"' : '';
            $sel = $method['svc_code'] == $this->shipping_method ? 'selected="selected"' : '';
            $s_amt = $method['cost'];
            $rate = array(
                'shipper_id' => $method['shipper_id'],
                'amount'    => (string)Currency::getInstance()->FormatValue($s_amt),
                'total'     => (string)Currency::getInstance()->FormatValue($base_chg + $s_amt),
            );
            $ship_rates[$method_id] = $rate;
            $T->set_var(array(
                'method_sel'    => $sel,
                'method_name'   => $method['title'],
                'method_rate'   => Currency::getInstance()->Format($s_amt),
                'method_id'     => $method_id,
                'order_id'      => $this->order_id,
                'multi'         => count($methods) > 1 ? true : false,
            ) );
            $T->parse('row', 'shipMethodSelect', true);
            if (count($methods) == 1) {
                $this->shipper_id = $method_id;
                $this->shipping = $s_amt;
            }
        }
        SESS_setVar('shop.shiprate.' . $this->order_id, $methods);
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
            $T->set_var('have_' . $wf->getName(), 'true');
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
            $this->Save(true);
        }
        return true;
    }


    /**
     * Provide a central location to get the URL to print or view a single order.
     *
     * @param   string  $view   View type (order or print)
     * @param   boolean $token  True to include the token
     * @return  string      URL to the view/print page
     */
    public function buildUrl($view, $token=true)
    {
        $url = SHOP_URL . "/order.php?mode=$view&id={$this->order_id}";
        if ($token) {
            $url .= "&token={$this->token}";
        }
        return COM_buildUrl($url);
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
        return $this->Customer->getLanguage($fullname);
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
        $languages[] = 'english_utf-8';

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
            if ($OI->getProductID() != $item_id) {
                continue;
            }
            $new_discount = $P->getDiscount($total_qty);
            $new_price = $P->getDiscountedPrice($total_qty, $OI->getOptionsPrice());
            if (
                $new_price != $OI->getPrice() ||
                $new_discount != $OI->getDiscount()
            ) {
                // only update and save if changed
                $OI->setPrice($new_price);
                $OI->setNetPrice($new_price);
                $OI->setDiscount($new_discount);
                $OI->Save(true);
            }
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
     * Force the user ID to a given value.
     * Used by the gateway to set the correct user during IPN processing.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    public function setUid($uid)
    {
        if ($this->uid != $uid) {
            $this->uid = (int)$uid;
        }
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
        if ($this->pmt_method != $method) {
            $this->pmt_method = $method;
            $this->pmt_dscp = Gateway::getInstance($method)->getDscp();
        }
        return $this;
    }


    /**
     * Get the gateway ID of the payment method.
     *
     * @return  string      Payment gateway name
     */
    public function getPmtMethod()
    {
        return $this->pmt_method;
    }


    /**
     * Get the friendly description of the payment gateway.
     *
     * @return  string      Payment gateway description
     */
    public function getPmtDscp()
    {
        return $this->pmt_dscp;
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
        $T = new Template;
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
     * This just forces the status to be the new status, if valid. No
     * notifications or other actions are done.
     *
     * @param   string  $newstatus  New status to set
     * @return  object  $this
     */
    public function setStatus($newstatus)
    {
        global $LANG_SHOP;

        if (OrderState::isValid($newstatus)) {
            $this->status = $newstatus;
        } else {
            SHOP_log("Invalid log status '{$newstatus}' specified for order {$this->getOrderID()}");
        }
        return $this;
    }


    /**
     * Set the sales tax rate for this order.
     * No action if the new rate is the same as the existing rate.
     *
     * @param   float   $new_rate   New tax rate, NULL to recalculate
     * @return  object  $this
     */
    public function setTaxRate($new_rate)
    {
        global $_TABLES;

        if ($new_rate === NULL) {
            if (!$this->hasPhysical()) {
                switch(Config::get('tax_nexus_virt')) {
                case Tax::TAX_ORIGIN:
                    $tax_addr = new Company;
                    break;
                case Tax::TAX_DESTINATION:
                    $tax_addr = $this->Shipto;
                    break;
                default:
                    $tax_addr = Address::fromGeoLocation();
                }
            } elseif (
                Shipper::getInstance($this->getShipperID())->getTaxLocation() == Tax::TAX_ORIGIN
            ) {
                $tax_addr = new Company;
            } else {
                $tax_addr = $this->Shipto;
            }
            $new_rate = Tax::getProvider()
                ->withAddress($tax_addr)
                ->withOrder($this)
                ->getRate();
        }
        $new_rate = (float)$new_rate;

        // Check if the rate changed. If it's the same, nothing to do.
        if ($this->getTaxRate() != $new_rate) {
            $this->tax_rate = (float)$new_rate;

            foreach ($this->getItems() as $Item) {
                if ($Item->isTaxable()) {
                    $Item->setTaxRate($this->tax_rate);
                } else {
                    $Item->setTaxRate(0);
                }
                $Item->Save();
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
            $this->updateRecord(array(
                "tax_rate = {$this->getTaxRate()}",
                "tax_shipping = {$this->getTaxShipping()}",
                "tax_handling = {$this->getTaxHandling()}",
                "order_total = " . $this->calcTotal(),
            ) );
        }
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
     * Set the shipping amount and recalculate the order totals.
     *
     * @param   float   $amt    Shipping amount.
     * @return  object  $this
     */
    public function setShipping($amt)
    {
        $this->shipping = (float)$amt;
        $this->calcTotal();
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
     * Get the description of the shipping method.
     *
     * @return  string      Shipping method description
     */
    public function getShipperDscp()
    {
        return $this->shipping_dscp;
    }


    /**
     * Set the order-wide handling amount.
     *
     * @param   float   $amt    Handling amount
     * @return  object  $this
     */
    public function setHandling($amt)
    {
        $this->handling = (float)$amt;
        return $this;
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
     * Set the order reference assigned by the payment gateway.
     *
     * @param   string  $ref_id     Gateway-assigned order ID
     * @return  object  $this
     */
    public function setGatewayRef($ref_id)
    {
        $this->gw_order_ref = $ref_id;
        return $this;
    }


    /**
     * Get the order reference at the payment gateway.
     *
     * @return  string  Gateway order reference
     */
    public function getGatewayRef()
    {
        return $this->gw_order_ref;
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

        //if (!DB_error()) {
            foreach ($this->items as $id=>$Item) {
                $this->items[$id]->applyDiscountPct($this->getDiscountPct());
                $this->items[$id]->Save();
            }
        //}
        $this->updateRecord(array(
            "discount_code = '" . DB_escapeString($this->discount_code) . "'",
            "discount_pct = '" . (float)$this->discount_pct . "'"
        ) );
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
        global $LANG_SHOP;

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
            $pct = $DC->Validate($this);
        }

        // If the code and percentage have not changed, just return true.
        // Otherwise update the discount in the order and items.
        if ($pct == $have_pct && $code == $have_code) {
//            return true;
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
        SHOP_setMsg($msg, $status ? 'info' : 'error', $status);
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
    protected function calcOrderTotal()
    {
        $this->calcItemTotals()->calcTax();
        return (float)$this->net_items + $this->shipping + $this->tax + $this->handling;
    }


    /**
     * Get the order total value from the database field.
     * Does not perform any calculations.
     *
     * @return  float   Order total
     */
    public function getOrderTotal()
    {
        return (float)$this->order_total;
    }


    /**
     * Get the gross total for line items.
     *
     * @return  float       Item subtotal
     */
    public function getGrossItems()
    {
        return (float)$this->gross_items;
    }


    /**
     * Get the net total for line items.
     *
     * @return  float       Item subtotal
     */
    public function getNetItems()
    {
        return (float)($this->net_nontax + $this->net_taxable);
    }


    /**
     * Check if the order has any invalid items excluded by rules.
     *
     * @return  boolean     True if any items cannot be orderd
     */
    public function hasInvalid()
    {
        return $this->hasInvalid;
    }


    /**
     * Check if there are any items in the cart.
     *
     * @return  boolean     True if cart is NOT empty, False if it is
     */
    public function hasItems()
    {
        return count($this->items);
    }


    /**
     * Check the zone rules for each item, and mark those that aren't allowed.
     * Also sets `$this->hasInvalid` if any invalid items are found so the
     * checkout button can be suppressed.
     *
     * @return  object  $this
     */
    public function checkRules()
    {
        $this->hasInvalid = false;
        foreach ($this->items as $id=>$Item) {
            $Product = $Item->getProduct();
            $Rule = $Product->getRule();
            if ($Product->isPhysical()) {
                $status = $Rule->isOK($this->getShipto());
            } else {
                $status = $Rule->isOK(NULL);
            }
            if (!$status) {
                $Item->setInvalid(true);
                $this->hasInvalid = true;
            } else {
                $Item->setInvalid(false);
            }
        }
        return $this;
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
     * Get the balance due on the order.
     *
     * @return  float       Unpaid balance
     */
    public function getBalanceDue()
    {
        $due = $this->order_total - $this->_amt_paid - $this->getGC();
        $due = $this->getCurrency()->formatValue($due);
        return $due;
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
     * Check if this order requires a billing address to be included.
     *
     * @return  boolean     True if a billing address is required
     */
    public function requiresBillto()
    {
        if (
            !empty($this->pmt_method) &&
            Gateway::getInstance($this->pmt_method)->requiresBillto()
        ) {
            return true;
        }
        return false;
    }


    /**
     * Check if this order requires a shipping address to be included.
     * Physical and taxable items require a shipping address.
     *
     * @return  boolean     True if a shipping address is required
     */
    public function requiresShipto()
    {
        if ($this->hasPhysical() && Shipper::getInstance($this->shipper_id)->requiresShipto()) {
            // Have to have a physical shipping address for physical products.
            return true;
        }

        // May need a shipping address for sales tax if there are physical items.
        if (
            $this->hasTaxable() &&
            $this->getTotal() > 0 &&
            (
                $this->hasPhysical() ||
                Config::get('tax_nexus_virt') == Tax::TAX_DESTINATION
            )
        ) {
            return true;
        }
        return false;

        /*if (!$this->hasPhysical()) {
            return false;
        }
        if (
            (
                // Shippers normally need an address, but "will call" doesn't.
                Shipper::getInstance($this->shipper_id)->requiresShipto()
            )
            ||
            (
                $this->hasTaxable()
                $this->getTotal() > 0
            )
        ) {
            return true;
        }
        return false;*/
    }


    /**
     * Check if the record is "tainted" by values being changed.
     *
     * @return  boolean     True if tainted and needs to be saved
     */
    public function isTainted()
    {
        return $this->tainted;
    }


    /**
     * Taint this order, indicating that something has changed.
     *
     * @return  object  $this
     */
    public function Taint()
    {
        $this->tainted = true;
        return $this;
    }


    /**
     * Set the payment URL provided for invoices by the payment gateway.
     *
     * @param   string  $url    URL to payment page
     * @return  object  $this/
     */
    public function setPaymentUrl($url)
    {
        $this->setInfo('gw_pmt_url', $url);
        return $this;
    }


    /**
     * Get the URL to pay this invoice online.
     *
     * @return  string      Payment URL
     */
    public function getPaymentUrl()
    {
        return $this->getInfo('gw_pmt_url');
    }


    /**
     * Check if this order can be deleted.
     * Orders that have been assigned a sequence number can't be deleted.
     *
     * @return  boolean     True if deletion is OK, False if not
     */
    public function canDelete()
    {
        if (
            $this->isNew ||
            $this->order_seq ||
            $this->isFinal()
        ) {
            return false;
        }
        return true;
    }


    /**
     * Get the cancellation URL to pass to payment gateways.
     * This url will set the status from "pending" back to "cart".
     *
     * @return  string      Cancellation URL
     */
    public function cancelUrl()
    {
        return SHOP_URL . '/cart.php?cancel=' . urlencode($this->order_id) .
            '/' . urlencode($this->token);
    }


    /**
     * Set the order status to indicate that has been submitted for payment.
     * This is normally only used for Cart objects, but is included here to
     * allow the "Pay Now" button on pending orders to work.
     *
     * Pass $status = false to revert back to a cart, e.g. if the purchase
     * is cancelled.
     *
     * Also removes the cart_id cookie for anonymous users.
     *
     * @param   string  $status     Status to set, default is "pending"
     * @return  object  $this
     */
    public function setFinal($status='pending')
    {
        global $_TABLES, $LANG_SHOP, $_CONF;

        if ($this->isNew()) {
            SHOP_log("Cart ID $cart_id was not found", SHOP_LOG_DEBUG);
            // Cart not found, do nothing
            return $this;
        }

        if (
            $this->status != OrderState::PENDING &&
            $this->status != OrderState::CART
        ) {
            // Do nothing if already processing, shipped, etc.
            return $this;
        }

        $newstatus = $status == OrderState::PENDING ? OrderState::PENDING : OrderState::CART;
        $oldstatus = $this->status;

        if ($oldstatus != $newstatus) {
            // Finalize the gift card application if any part of the order
            // is paid by coupon.
            if ($this->getGC() > 0) {
                $this->setInfo(
                    'applied_gc',
                    \Shop\Products\Coupon::Apply($this->getGC(), $this->getUid(), $this)
                );
            }

            // Update the order status and date
            $this->setStatus($newstatus)->setOrderDate()->Save(false);

            SHOP_log(
                "Cart {$this->order_id} status changed from $oldstatus to $newstatus",
                SHOP_LOG_DEBUG
            );
        }
        Session::set('order_id', $this->getOrderID());
        return $this;
    }


    /**
     * Cancel an order payment and revert to `cart` status.
     *
     * @return  object  $this
     */
    public function cancelFinal()
    {
        // Get any coupons that were applied and restore their balances
        $cards = $this->getInfo('applied_gc');
        if (is_array($cards)) {
            foreach ($cards as $code=>$amount) {
                \Shop\Products\Coupon::Restore($code, $amount);
            }
        }

        // Set the order status back to "cart" and reset the token
        // to prevent duplication of this function.
        $this->setStatus(OrderState::CART)
             ->remInfo('applied_gc')
             ->setToken()
             ->Save(false);
        return $this;
    }


    /**
     * Add a session variable.
     *
     * @param   string  $key    Name of variable
     * @param   mixed   $value  Value to set
     */
    public static function XsetSession($key, $value)
    {
        if (!isset($_SESSION[self::$session_var])) {
            $_SESSION[self::$session_var] = array();
        }
        $_SESSION[self::$session_var][$key] = $value;
    }


    /**
     * Retrieve a session variable.
     *
     * @param   string  $key    Name of variable
     * @return  mixed       Variable value, or NULL if it is not set
     */
    public static function XgetSession($key)
    {
        if (isset($_SESSION[self::$session_var][$key])) {
            return $_SESSION[self::$session_var][$key];
        } else {
            return NULL;
        }
    }


    /**
     * Remove a session variable.
     *
     * @param   string  $key    Name of variable
     */
    public static function XclearSession($key=NULL)
    {
        if ($key === NULL) {
            unset($_SESSION[self::$session_var]);
        } else {
            unset($_SESSION[self::$session_var][$key]);
        }
    }


    /**
     * Check that the order status is at least a certain level.
     *
     * @param   string  $desired    Desired status
     * @return  boolean     True if the order is at or past the status
     */
    public function statusAtLeast($desired)
    {
        return OrderState::atLeast($desired, $this->getStatus());
    }


    /**
     * Check if an order is OK to be shipped.
     * Paid orders can always be shipped.
     * Other orders that are invoiced or in-process can also be shipped.
     *
     * @return  boolean     True if the order can be shipped, False if not.
     */
    public function okToShip()
    {
        if ($this->isPaid()) {
            return true;
        }

        switch ($this->status) {
        case OrderState::INVOICED;
        case OrderState::PROCESSING;
        case OrderState::SHIPPED;
        case OrderState::CLOSED;
            return true;
        default:
            return false;
        }
    }

}
