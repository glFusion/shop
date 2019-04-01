<?php
/**
 * Base class for handling IPN messages.
 * Each IPN handler receives its data in a unique way, which it is responsible
 * for putting into this class's pp_data array which holds common, standard
 * data elements.
 *
 * The derived class may implement a "Process" function, or other master
 * control.  The protected functions here are available for derived classes,
 * or they may implement their own methods for handlePurchase(),
 * createOrder(), etc.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      Vincent Furia <vinny01@users.sourceforge.net>
 * @copyright   Copyright (c) 2009-2018 Lee Garner
 * @copyright   Copyright (c) 2005-2006 Vincent Furia
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

// Just for E_ALL. If "testing" isn't defined, define it.
if (!isset($_SHOP_CONF['sys_test_ipn'])) $_SHOP_CONF['sys_test_ipn'] = false;

// Define failure reasons- maybe delete if not needed for all gateways
define('IPN_FAILURE_UNKNOWN', 0);
define('IPN_FAILURE_VERIFY', 1);
define('IPN_FAILURE_COMPLETED', 2);
define('IPN_FAILURE_UNIQUE', 3);
define('IPN_FAILURE_EMAIL', 4);
define('IPN_FAILURE_FUNDS', 5);


/**
 * Class to deal with IPN transactions from a payment gateway.
 * @package shop
 */
class IPN
{
    /** Standard IPN data items required for all IPN types.
     * @var array */
//    var $pp_data = array();
    private $properties = array();

    /**
     * Holder for the complete IPN data array.
     * Used only for recording the raw data; no processing is done on this data
     * by the base class. Instantiated classes will use this to populate the
     * standard variables.
     * @var array
     */
    protected $ipn_data = array();

    /**
     * Custom data that comes from the IPN provider, typically pass-through.
     * @var array
     */
    protected $custom = array();

    /**
     * Shipping address information.
     * @var array
     */
    protected $shipto = array();

    /** Array of items purchased.
     * Extracted from $ipn_data by the derived IPN processor class.
     * @var array */
    protected $items = array();

    /** ID of payment gateway, e.g. 'shop' or 'amazon'.
     * @var string */
    public $gw_id;

    /** Instance of the appropriate gateway object.
    * @var object */
    protected $gw;

    /** Accumulator for credits applied to orders.
     * @var array */
    protected $credits = array();


    /**
     * This is just a holder for the current date in SQL format,
     * so we don't have to rely on the database's NOW() function.
     * @var string */
    //var $sql_date;

    /** Order object.
     * @var object */
    protected $Order;

    /** Cart object.
    * @var object */
    protected $Cart;

    /**
     * Set up variables received in the IPN message.
     * Stores the complete IPN message in ipn_data.
     * Must be called by the IPN processor's constructor after gw_id is set.
     *
     * @param   array   $A      $_POST'd variables from the gateway
     */
    public function __construct($A=array())
    {
        global $_SHOP_CONF;

        if (is_array($A)) {
            $this->ipn_data = $A;
        }

        // Make sure values are defined
        $this->sql_date = SHOP_now()->toMySQL();

        // Create a gateway object to get some of the config values
        $this->gw = Gateway::getInstance($this->gw_id);
    }


    /**
     * Set a property value.
     * These are mostly values obtained from the IPN message.
     * This also provides a partial list of variables that are expected for every IPN.
     *
     * @param   string  $key    Property name
     * @param   mixed   $val    Property value
     */
    public function __set($key, $val)
    {
        switch ($key) {
        case 'pmt_gross':       // Gross (total) payment amt
            $this->properties[$key] = (float)$val;
            break;
        case 'pmt_shipping':    // Shipping payment (included in  gross)
        case 'pmt_handling':    // Handling payment (included in gross)
        case 'pmt_tax':         // Tax payment (included in gross)
        case 'pmt_net':         // Net payment for order items
        case 'total_credit':    // total payment, coupons, discounts, etc.
            $this->properties[$key] = (float)$val;
            break;

        case 'uid':             // ID of user submitting the payment
            $this->properties[$key] = (int)$val;
            break;

        case 'txn_id':          // IPN transaction ID
        case 'payer_email':     // Payer email address
        case 'payer_name':      // Payer name
        case 'pmt_date':        // Payment date
        case 'sql_date':        // Payment date (SQL format)
        case 'gw_name':         // Name of gateway used for payment
        case 'currency':        // Currenc code of payment
        case 'order_id':        // Internal order ID
            $this->properties[$key] = trim($val);
            break;

        case 'status':          // Payment status, certain values allowed
            switch ($val) {
            case 'pending':
            case 'paid':
            case 'refunded':
                $this->properties[$key] = $val;
                break;
            default:
                $this->properties[$key] = 'unknown';
                break;
            }
            break;
        }

/*
            'shipto'        => array(),
            'custom'        => array(),
            'pmt_other'     => array(),     // pmt-equivalents, coupons, discounts, etc.
        );*/

    }


    /**
     * Get a value from the properties array, or NULL if not defined.
     *
     * @param   string  $key    Name of property
     * @return  mixed       Value of property, NULL if undefined
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    /**
     * Check if a key variable is empty.
     * Used because empty() doesn't work right with __set() and __get().
     *
     * @param   string  $key    Variable name
     * @return  boolean     True if the var is empty, False if not.
     */
    protected function isEmpty($key)
    {
        if (empty($this->properties[$key])) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Add an item from the IPN message to our $items array.
     *
     * @param   array   $args   Array of arguments
     */
    protected function AddItem($args)
    {
        // Minimum required arguments: item, quantity, unit price
        if (!isset($args['item_id']) || !isset($args['quantity']) || !isset($args['price'])) {
            return;
        }

        // Separate the item ID and options to get pricing
        $tmp = explode('|', $args['item_id']);
        $P = Product::getInstance($tmp[0], $this->custom);
        if ($P->isNew) {
            COM_errorLog("Product {$args['item_id']} not found in catalog");
            return;      // no product found to add
        }
        if (isset($tmp[1])) {
            $opts = explode(',', $tmp[1]);
        } else {
            $opts = array();
        }
        // If the product allows the price to be overridden, just take the
        // IPN-supplied price. This is the case for donations.
        $overrides = $this->custom;
        $overrides['price'] = $args['price'];
        $price = $P->getPrice($opts, $args['quantity'], $overrides);

        $this->items[] = array(
            'item_id'   => $args['item_id'],
            'item_number' => $tmp[0],
            'name'      => isset($args['item_name']) ? $args['item_name'] : '',
            'quantity'  => $args['quantity'],
            'price'     => $price,      // price including options
            'shipping'  => isset($args['shipping']) ? $args['shipping'] : 0,
            'handling'  => isset($args['handling']) ? $args['handling'] : 0,
            //'tax'       => $tax,
            'taxable'   => $P->taxable ? 1 : 0,
            'options'   => isset($tmp[1]) ? $tmp[1] : '',
            'extras'    => isset($args['extras']) ? $args['extras'] : '',
            'overrides' => $overrides,
        );
    }


    /**
     * Log an instant payment notification.
     *
     * Logs the incoming IPN (serialized) along with the time it arrived,
     * the originating IP address and whether or not it has been verified
     * (caller specified).  Also inserts the txn_id separately for
     * look-up purposes.
     *
     * @param   boolean $verified   true if verified, false otherwise
     * @return  integer             Database ID of log record
     */
    protected function Log($verified = false)
    {
        global $_SERVER, $_TABLES;

        // Change $verified into format for database
        if ($verified) {
            $verified = 1;
        } else {
            $verified = 0;
        }

        // Log to database
        $sql = "INSERT INTO {$_TABLES['shop.ipnlog']} SET
                ip_addr = '{$_SERVER['REMOTE_ADDR']}',
                ts = UNIX_TIMESTAMP(),
                verified = $verified,
                txn_id = '" . DB_escapeString($this->txn_id) . "',
                gateway = '{$this->gw_id}',
                ipn_data = '" . DB_escapeString(serialize($this->ipn_data)) . '\'';
        // Ignore DB error in order to not block IPN
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Shop\IPN::Log() SQL error: $sql", 1);
        }
        return DB_insertId();
    }


    /**
     * Checks that the transaction id is unique to prevent double counting.
     *
     * @return  boolean             True if unique, False otherwise
     */
    protected function isUniqueTxnId()
    {
        global $_TABLES, $_SHOP_CONF;
        if ($_SHOP_CONF['sys_test_ipn']) return true;

        // Count purchases with txn_id, if > 0
        $count = DB_count($_TABLES['shop.ipnlog'], 'txn_id', $this->txn_id);
        if ($count > 0) {
            COM_errorLog("Received duplicate IPN {$this->txn_id} for {$this->gw_id}");
            return false;
        } else {
            return true;
        }
    }


    /**
     * Check that provided funds are sufficient to cover the cost of the purchase.
     *
     * @return boolean                 True for sufficient funds, False if not
     */
    protected function isSufficientFunds()
    {
        $Cur = \Shop\Currency::getInstance();
        $total_credit = $this->calcTotalCredit();
        $credit = $this->getCredit();
        // Compare total order amount to gross payment.  The ".0001" is to help
        // kill any floating-point errors. Include any discount.
        $total_order = $this->Order->getTotal();
        $msg = $Cur->FormatValue($this->pmt_gross) . ' received plus ' .
            $Cur->FormatValue($credit) .' credit, require ' .
            $Cur->FormatValue($total_order);
        if ($total_order <= $total_credit + .0001) {
            SHOP_debug("OK: $msg", 'debug_ipn');
            return true;
        } else {
            SHOP_debug("Insufficient Funds: $msg", 'debug_ipn');
            return false;
        }
    }


    /**
     * Handles the item purchases.
     * The purchase should already have been validated; this function simply
     * records the purchases.  Purchased files will be emailed to the
     * customer by Order::Notify().
     *
     * @uses    self::createOrder()
     * @return  boolean     True if processed successfully, False if not
     */
    protected function handlePurchase()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP;

        // For each item purchased, create an order item
        foreach ($this->items as $id=>$item) {
            $P = Product::getInstance($item['item_number']);
            if ($P->isNew) {
                $this->Error("Item {$item['item_number']} not found - txn " .
                        $this->txn_id);
                continue;
            }

            $this->items[$id]['prod_type'] = $P->prod_type;
            SHOP_debug("Shop item " . $item['item_number'], 'debug_ipn');

            // If it's a downloadable item, then get the full path to the file.
            if ($P->file != '') {
                $this->items[$id]['file'] = $_SHOP_CONF['download_path'] . $P->file;
                $token_base = $this->txn_id . time() . rand(0,99);
                $token = md5($token_base);
                $this->items[$id]['token'] = $token;
            } else {
                $token = '';
            }
            if (is_numeric($P->expiration) && $P->expiration > 0) {
                $this->items[$id]['expiration'] = $P->expiration;
            }

            // If a custom name was supplied by the gateway's IPN processor,
            // then use that.  Otherwise, plug in the name from inventory or
            // the plugin, for the notification email.
            if (empty($item['name'])) {
                $this->items[$id]['name'] = $P->short_description;
            }
        }   // foreach item

        $status = is_null($this->Order) ? $this->createOrder() : 0;
        if ($status == 0) {
            // Now all of the items are in the order object, check for sufficient
            // funds. If OK, then save the order and call each handlePurchase()
            // for each item.
            if (!$this->isSufficientFunds()) {
                $logId = $this->gw_name . ' - ' . $this->txn_id;
                $this->handleFailure(IPN_FAILURE_FUNDS,
                        "($logId) Insufficient/incorrect funds for purchase");
                return false;
            }

            // Get the gift card amount applied to this order and save it with the order record.
            $by_gc = $this->getCredit('gc');
            $this->Order->by_gc = $by_gc;
            Coupon::Apply($by_gc, $this->Order->uid, $this->Order);

            // Log all non-payment credits applied to the order
            foreach ($this->credits as $key=>$val) {
                $this->Order->Log(
                    sprintf(
                        $LANG_SHOP['amt_paid_gw'],
                        $val,
                        SHOP_getVar($LANG_SHOP, $key, 'string', 'Unknown')
                    )
                );
            }

            $this->Order->pmt_method = $this->gw_id;
            $this->Order->pmt_txn_id = $this->txn_id;
            $this->Order->Save();
            $this->Order->updateStatus($this->status, 'IPN: ' . $this->gw->Description());

            // Handle the purchase for each order item
            foreach ($this->Order->getItems() as $item) {
                $item->getProduct()->handlePurchase($item, $this->Order, $this->pp_data);
            }
            $this->Order->Log(sprintf(
                $LANG_SHOP['amt_paid_gw'],
                $this->pmt_gross,
                $this->gw->DisplayName()
            ));
        } else {
            COM_errorLog('Error creating order: ' . print_r($status,true));
        }

        return true;
    }  // function handlePurchase


    /**
     * Create and populate an Order record for this purchase.
     * Gets the billto and shipto addresses from the cart, if any.
     * Items are saved in the purchases table by handlePurchase().
     * The order is not saved to the database here. Only after checking
     * for sufficient funds is the records saved.
     *
     * This function is called only by our own handlePurchase() function,
     * but is made "protected" so a derived class can use it if necessary.
     *
     * @return  integer     Zero for success, non-zero on error
     */
    protected function createOrder()
    {
        global $_TABLES, $_SHOP_CONF;

        // See if an order already exists for this transaction.
        // If so, load it and update the status. If not, continue on
        // and create a new order
        $order_id = DB_getItem($_TABLES['shop.orders'], 'order_id',
            "pmt_txn_id='" . DB_escapeString($this->txn_id) . "'");
        if (!empty($order_id)) {
            $this->Order = Order::getInstance($order_id);
            if ($this->Order->order_id != '') {
                $this->Order->log_user = $this->gw->Description();
                //$this->Order->updateStatus($this->status);
            }
            return 2;
        }

        // Need to create a new, empty order object
        $this->Order = new Order();

        if ($this->order_id != '') {
            $this->Cart = new Cart($this->order_id);
            if (!$this->Cart->hasItems()) {
                if (!$_SHOP_CONF['sys_test_ipn']) {
                    return 1; // shouldn't normally be empty except during testing
                }
            }
        } else {
            $this->Cart = NULL;
        }

        $this->Order->uid = $this->uid;
        $this->Order->buyer_email = $this->payer_email;
        $this->Order->status = 'pending';
        if ($uid > 1) {
            $U = new UserInfo($uid);
        }

        // Get the billing and shipping addresses from the cart record,
        // if any.  There may not be a cart in the database if it was
        // removed by a previous IPN, e.g. this is the 'completed' message
        // and we already processed a 'pending' message
        $BillTo = '';
        if ($this->Cart) {
            $BillTo = $this->Cart->getAddress('billto');
            $this->Order->instructions = $this->Cart->getInstructions();
        }
        if (empty($BillTo) && $uid > 1) {
            $BillTo = $U->getDefaultAddress('billto');
        }
        if (is_array($BillTo)) {
            $this->Order->setBilling($BillTo);
        }

        $ShipTo = $this->shipto;
        if (empty($ShipTo)) {
            if ($this->Cart) $ShipTo = $this->Cart->getAddress('shipto');
            if (empty($ShipTo) && $uid > 1) {
                $ShipTo = $U->getDefaultAddress('shipto');
            }
        }
        if (is_array($ShipTo)) {
            $this->Order->setShipping($ShipTo);
        }
        if (isset($this->shipto['phone'])) {
            $this->Order->phone = $this->shipto['phone'];
        }
        $this->Order->pmt_method = $this->gw_id;
        $this->Order->pmt_txn_id = $this->txn_id;
        $this->Order->shipping = $this->pmt_shipping;
        $this->Order->handling = $this->pmt_handling;
        $this->Order->buyer_email = $this->payer_email;
        $this->Order->log_user = $this->gw->Description();

        $this->Order->items = array();
        foreach ($this->items as $id=>$item) {
            $options = DB_escapeString($item['options']);
            $option_desc = array();
            //$tmp = explode('|', $item['item_number']);
            //list($item_number,$options) =
            //if (is_numeric($item_number)) {
            $P = Product::getInstance($item['item_id'], $this->custom);
            $item['short_description'] = $P->short_description;
            if (!empty($options)) {
                // options is expected as CSV
                $sql = "SELECT attr_name, attr_value
                        FROM {$_TABLES['shop.prod_attr']}
                        WHERE attr_id IN ($options)";
                $optres = DB_query($sql);
                $opt_str = '';
                while ($O = DB_fetchArray($optres, false)) {
                    $opt_str .= ', ' . $O['attr_value'];
                    $option_desc[] = $O['attr_name'] . ': ' . $O['attr_value'];
                }
            }

            // Get the product record and custom strings
            if (isset($item['extras']['custom']) &&
                    is_array($item['extras']['custom']) &&
                    !empty($item['extras']['custom'])) {
                foreach ($item['extras']['custom'] as $cust_id=>$cust_val) {
                    $option_desc[] = $P->getCustom($cust_id) . ': ' . $cust_val;
                }
            }
            $args = array(
                'order_id' => $this->Order->order_id,
                'product_id' => $item['item_number'],
                'description' => $item['short_description'],
                'quantity' => $item['quantity'],
                'txn_type' => $this->custom['transtype'],
                'txn_id' => $this->txn_id,
                'status' => 'pending',
                'token' => md5(time()),
                'price' => $item['price'],
                'taxable' => $P->taxable,
                'options' => $options,
                'options_text' => $option_desc,
                'extras' => $item['extras'],
                'shipping' => SHOP_getVar($item, 'shipping', 'float'),
                'handling' => SHOP_getVar($item, 'handling', 'float'),
                'paid' => SHOP_getVar($item['overrides'], 'price', 'float', $item['price']),
            );
            $this->Order->addItem($args);
        }   // foreach item
        $this->Order->Save();
        return 0;
    }


    /**
     * Process a refund.
     * If a purchase is completely refunded, then call the plugins to
     * handle the refund.  Otherwise, do nothing; partial refunds need to
     * be handled manually.
     *
     * @todo: handle partial refunds
     */
    protected function handleRefund()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP;

        return true;

        // Try to get original order information.  Use the "parent transaction"
        // or invoice number, if available from the IPN message
        if ($this->order_id !== NULL) {
            $order_id = $this->order_id;
        } else {
            $order_id = DB_getItem($_TABLES['shop.orders'], 'order_id',
                "pmt_txn_id = '" . DB_escapeString($this->parent_txn_id)
                . "'");
        }

        $Order = Order::getInstance($order_id);
        if ($Order->order_id == '') {
            return false;
        }

        // Figure out if the entire order was refunded
        $refund_amt = abs((float)$this->pmt_gross);

        $item_total = 0;
        foreach ($Order->items as $key => $data) {
            $item_total += $data['quantity'] * $data['price'];
        }
        $item_total += $Order->miscCharges();

        if ($item_total == $refund_amt) {
            // Completely refunded, let the items handle any refund
            // actions.  None for catalog items (since there's no inventory,
            // but plugin items may need to do something.
            foreach ($Order->items as $key=>$data) {
                $P = Product::getInstance($data['product_id'], $this->custom);
                // Don't care about the status, really.  May not even be
                // a plugin function to handle refunds
                $P->handleRefund($Order, $this->ipn_data);
            }
            // Update the order status to Refunded
            $Order->updateStatus($LANG_SHOP['orderstatus']['refunded']);
        }
    }  // function handleRefund


    /**
     * Handle a subscription payment. (Not implemented yet).
     *
     * @todo Implement handleSubscription
     */
    /*private function handleSubscription()
    {
        $this->handleFailure(IPN_FAILURE_UNKNOWN, "Subscription not handled");
    }*/


    /**
     * Handle a Donation payment (Not implemented).
     *
     * @todo Implement handleDonation
     */
    /*private function handleDonation()
    {
        $this->handleFailure(IPN_FAILURE_UNKNOWN, "Donation not handled");
    }*/


    /**
     * Handle what to do in the event of a purchase/IPN failure.
     *
     * This method does some basic failure handling.  For anything more
     * advanced it is recommend you override this method.
     *
     * @param   integer $type   Type of failure that occurred
     * @param   string  $msg    Failure message
     */
    protected function handleFailure($type = IPN_FAILURE_UNKNOWN, $msg = '')
    {
        // Log the failure to glFusion's error log
        $this->Error($this->gw_id . '-IPN: ' . $msg, 1);
    }


    /**
     * Debugging function. Dumps variables to error log.
     *
     * @param   mixed   $var    Data to log
     */
    protected function debug($var)
    {
        $msg = print_r($var, true);
        COM_errorLog('IPN Debug: ' . $msg, 1);
    }


    /**
     * Log an error message.
     * This just formats the message to indicate the gateway ID.
     *
     * @param   string  $str    Error message to log
     */
    protected function Error($str)
    {
        COM_errorLog($this->gw_id. ' IPN Exception: ' . $str, 1);
    }


    /**
     * Instantiate and return an IPN class.
     *
     * @param   string  $name   Gateway name, e.g. shop
     * @param   array   $vars   Gateway variables to be passed to the IPN
     * @return  object          IPN handler object
     */
    public static function getInstance($name, $vars=array())
    {
        static $ipns = array();
        if (!array_key_exists($name, $ipns)) {
            $cls = __NAMESPACE__ . '\\ipn\\' . $name;
            if (class_exists($cls)) {
                $ipns[$name] = new $cls($vars);
            } else {
                $ipns[$name] = NULL;
            }
        }
        return $ipns[$name];
    }


    /**
     * Calculate the total credit amount after all payments, discounts, etc.
     */
    protected function calcTotalCredit()
    {
        return $this->pmt_gross + $this->getCredit();
    }


    /**
     * Add a credit amount to the credits array.
     *
     * @param   string  $name   Name of credit, to accumulate
     * @param   float   $amount Amount to add
     */
    protected function addCredit($name, $amount)
    {
        if ($amount != 0) {
            $this->credits[$name] = (float)$amount;
        }
    }


    /**
     * Get the amount of a particular credit type.
     *
     * @param   string  $key    Key to credit
     * @return  float       Credit amount
     */
    protected function getCredit($key=NULL)
    {
        $retval = 0;
        if ($key === NULL) {
            foreach ($this->credits as $key=>$credit) {
                $retval += (float)$this->verifyCredit($key, $credit);
            }
        } elseif (array_key_exists($key, $this->credits)) {
            $retval = (float)$this->verifyCredit($key, $credit);
        }
        return $retval;
    }


    /**
     * Verify a credit amount.
     * Checks that a requested credit amount is still valid, e.g. has not
     * expired or otherwise been used.
     * Currently only applies to Gift Cards.
     *
     * @param   string  $key    Key name for credit, e.g. "gc" for gift card
     * @return  float   Amount paid by gift card
     */
    protected function verifyCredit($key)
    {
        static $retval = array();

        if (array_key_exists($key, $retval)) {
            // Already verified this credit amount.
            return $retval[$key];
        }
        if (
            !array_key_exists($key, $this->credits) ||
            $this->credits[$key] < .0001
        ) {
            // The requested credit isn't available.
            $retval[$key] = 0;
        } else {
            $credit = (float)$this->credits[$key];
            switch ($key) {
            case 'gc':
                if (Coupon::verifyBalance($by_gc, $this->uid)) {
                    $retval[$key] = $credit;
                } else {
                    $gc_bal = Coupon::getUserBalance($this->uid);
                    COM_errorLog("Insufficient Gift Card Balance, need $by_gc, have $gc_bal");
                    $retval[$key] = 0;
                }
                break;
            default:
                $retval[$key] = $credit;
                break;;
            }
        }
        return $retval[$key];
    }


    /**
     * Get an order object for this payment.
     * Sets any credits included in the order record.
     *
     * @param   integer $uid    User ID, if supplied
     * @param   string  $order_id   Order ID gleaned from the IPN message
     * @return  object      Order object
     */
    protected function getOrder($uid, $order_id)
    {
        $this->Order = Cart::getInstance($uid, $order_id);
        $this->addCredit('gc', $this->Order->getInfo('apply_gc'));
        return $this->Order;
    }

}   // class IPN

?>
