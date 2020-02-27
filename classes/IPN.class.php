<?php
/**
 * Base class for handling IPN messages.
 * Each IPN handler receives its data in a unique way, which it is responsible
 * for putting into this class's ipn_data array which holds common, standard
 * data elements.
 *
 * The derived class may implement a "Process" function, or other master
 * control.  The protected functions here are available for derived classes,
 * or they may implement their own methods for handlePurchase(),
 * createOrder(), etc.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      Vincent Furia <vinny01@users.sourceforge.net>
 * @copyright   Copyright (c) 2009-2020 Lee Garner
 * @copyright   Copyright (c) 2005-2006 Vincent Furia
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
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


/**
 * Class to deal with IPN transactions from a payment gateway.
 * @package shop
 */
class IPN
{
    const PAID = 'paid';
    const PENDING = 'pending';
    const REFUNDED = 'refunded';
    const CLOSED = 'closed';

    const FAILURE_UNKNOWN = 0;
    const FAILURE_VERIFY = 1;
    const FAILURE_COMPLETED = 2;
    const FAILURE_UNIQUE = 3;
    const FAILURE_EMAIL = 4;
    const FAILURE_FUNDS = 5;


    /** Gross payment amount, including shipping, handling, tax.
     * @var float */
    private $pmt_gross = 0;

    /** Shipping charge paid.
     * @var float */
    private $pmt_shipping = 0;

    /** Handling charge paid.
     * @var float */
    private $pmt_handling = 0;

    /** Tax amount paid.
     * @var float */
    private $pmt_tax = 0;

    /** Total credit applied. Includes gross payment and any discounts/coupons.
     * @var float */
    private $total_credit = 0;

    /** User ID submitting the payment.
     * @var integer */
    private $uid = 0;

    /** Payment transaction ID.
     * @var string */
    private $txn_id = '';

    /** Payer email.
     * @var string */
    private $payer_email = '';

    /** Payer's name.
     * @var string */
    private $payer_name = '';

    /** Gateway Name.
     * @var string */
    private $gw_name = '';

    /** Currency Code.
     * @var string */
    private $currency = NULL;

    /** Order ID of order being paid.
     * @var string */
    private $order_id = '';

    /** Parent Transaction ID. Needed for refunds and adjustments.
     * @var string */
    private $parent_txn_id = '';

    /** Payment status string.
     * @var string */
    private $status = '';

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
    public $gw_id = '';

    /** Instance of the appropriate gateway object.
    * @var \Shop\Gateway */
    protected $GW = NULL;

    /** Accumulator for credits applied to orders.
     * @var array */
    protected $credits = array();

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

        // Create a gateway object to get some of the config values
        $this->GW = Gateway::getInstance($this->gw_id);
    }


    /**
     * Set the gross payment amount.
     *
     * @param   float   $amount Gross Payment Amount
     * @return  object  $this
     */
    public function setPmtGross($amount)
    {
        $this->pmt_gross = (float)$amount;
        return $this;
    }


    /**
     * Get the gross payment amount.
     *
     * @return  float   Gross Payment Amount
     */
    public function getPmtGross()
    {
        return $this->pmt_gross;
    }


    /**
     * Set the shipping payment amount.
     *
     * @param   float   $amount Shipping Payment Amount
     * @return  object  $this
     */
    public function setPmtShipping($amount)
    {
        $this->pmt_shipping = (float)$amount;
        return $this;
    }


    /**
     * Get the shipping payment amount.
     *
     * @return  float   Shipping Payment Amount
     */
    public function getPmtShipping()
    {
        return $this->pmt_shipping;
    }


    /**
     * Set the handling payment amount.
     *
     * @param   float   $amount Handling Payment Amount
     * @return  object  $this
     */
    public function setPmtHandling($amount)
    {
        $this->pmt_handling = (float)$amount;
        return $this;
    }


    /**
     * Get the handling payment amount.
     *
     * @return  float   Handling Payment Amount
     */
    public function getPmtHandling()
    {
        $this->pmt_handling = (float)$amount;
        return $this;
    }


    /**
     * Set the tax payment amount.
     *
     * @param   float   $amount Tax Payment Amount
     * @return  object  $this
     */
    public function setPmtTax($amount)
    {
        $this->pmt_tax = (float)$amount;
        return $this;
    }


    /**
     * Get the tax payment amount.
     *
     * @return  float   Tax Payment Amount
     */
    public function getPmtTax()
    {
        return $this->pmt_tax;
    }


    /**
     * Get the fees paid, e.g. tax, shipping, handling.
     *
     * @return  float   Total non-product fees paid
     */
    public function getPmtFees()
    {
        return $this->getPmtHandling() + $this->getPmtShipping() + $this->getPmtTax();
    }


    /**
     * Get the net payment amount.
     *
     * @return  float   Net Payment Amount
     */
    public function getPmtNet()
    {
        return $this->getPmtGross() - $this->getPmtFees();
    }


    /**
     * Set the total credit amount.
     *
     * @param   float   $amount Total credit applied
     * @return  object  $this
     */
    public function setTotalCredit($amount)
    {
        $this->total_credit = (float)$amount;
        return $this;
    }


    /**
     * Get the total credit amount.
     *
     * @return  float   Total credit applied
     */
    public function getTotalCredit()
    {
        return $this->total_credit;
    }


    /**
     * Set the ID of the paying user.
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
     * Get the ID of the paying user.
     *
     * @return  integer User ID
     */
    public function getUid()
    {
        return $this->uid;
    }


    /**
     * Set the transaction ID.
     *
     * @param   string  $txn_id Transaction ID
     * @return  object  $this
     */
    public function setTxnId($txn_id)
    {
        $this->txn_id = $txn_id;
        return $this;
    }


    /**
     * Get the transaction ID.
     *
     * @return  string  Transaction ID
     */
    public function getTxnId()
    {
        return $this->txn_id;
    }


    /**
     * Set the parent transaction ID.
     *
     * @param   string  $txn_id Parent Transaction ID
     * @return  object  $this
     */
    public function setParentTxnId($txn_id)
    {
        $this->parent_txn_id = $txn_id;
        return $this;
    }


    /**
     * Get the parent transaction ID.
     *
     * @return  string  Parent Transaction ID
     */
    public function getParentTxnId()
    {
        return $this->parent_txn_id;
    }


    /**
     * Set the payer's email address.
     *
     * @param   string  $email  Payer's email address
     * @return  object  $this
     */
    public function setEmail($email)
    {
        $this->payer_email = $email;
        return $this;
    }


    /**
     * Get the payer's email address.
     *
     * @return  string  Payer's email address
     */
    public function getEmail()
    {
        return $this->payer_email;
    }


    /**
     * Set the payer's name.
     *
     * @param   string  $name   Payer's name
     * @return  object  $this
     */
    public function setPayerName($name)
    {
        $this->payer_name = $name;
        return $this;
    }


    /**
     * Get the payer's name.
     *
     * @return  string  Payer's name
     */
    public function getPayerName()
    {
        return $this->payer_name;
    }


    /**
     * Set the gateway name.
     *
     * @param   string  $name   Gateway name
     * @return  object  $this
     */
    public function setGwName($name)
    {
        $this->gw_name = $name;
        return $this;
    }


    /**
     * Get the gateway name.
     *
     * @return  string  Gateway name
     */
    public function getGwName()
    {
        return $this->GW->getName();
    }


    /**
     * Get the gateway object for this IPN message.
     *
     * @return  object      IPN object
     */
    public function getGW()
    {
        return $this->GW;
    }


    /**
     * Set the payment currency object.
     *
     * @param   string  $code   Currency code, empty for site default
     * @return  object  $this
     */
    public function setCurrency($code='')
    {
        $this->currency = Currency::getInstance(strtoupper($code));
        return $this;
    }


    /**
     * Get the payment currency object.
     *
     * @return string  Currency code
     */
    public function getCurrency()
    {
        if ($this->currency === NULL) {
            $this->setCurrency();
        }
        return $this->currency;
    }


    /**
     * Set the order ID.
     *
     * @param   string  $order_id   Order ID
     * @return  object  $this
     */
    public function setOrderId($order_id)
    {
        $this->order_id = $order_id;
        return $this;
    }


    /**
     * Get the order ID.
     *
     * @return  string  Order ID
     */
    public function getOrderId()
    {
        return $this->order_id;
    }


    /**
     * Set the payment status.
     *
     * @param   string  $status Status string
     * @return  object  $this
     */
    public function setStatus($status)
    {
        switch ($status) {
        case self::PENDING:
        case self::PAID:
        case self::REFUNDED:
        case self::CLOSED:
            $this->status = $status;
            break;
        default:
            $this->status = 'unknown';
            break;
        }
        return $this;
    }


    /**
     * Get the payment status.
     *
     * @return  string  Status string
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * Add an item from the IPN message to our $items array.
     *
     * @param   array   $args   Array of arguments
     */
    protected function addItem($args)
    {
        // Minimum required arguments: item, quantity, unit price
        if (!isset($args['item_id']) || !isset($args['quantity']) || !isset($args['price'])) {
            return;
        }

        // Separate the item ID and options to get pricing
        $tmp = explode('|', $args['item_id']);
        $P = Product::getByID($tmp[0], $this->custom);
        if ($P->isNew) {
            SHOP_log("Product {$args['item_id']} not found in catalog", SHOP_LOG_ERROR);
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
                ipn_data = '" . DB_escapeString(serialize($this->ipn_data)) . "'";
        if ($this->Order !== NULL) {
            $sql .= ", order_id = '" . DB_escapeString($this->Order->getOrderId()) . "'";
        }
        // Ignore DB error in order to not block IPN
        DB_query($sql, 1);
        if (DB_error()) {
            SHOP_log("Shop\IPN::Log() SQL error: $sql", SHOP_LOG_ERROR);
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

        if (isset($_SHOP_CONF['sys_test_ipn']) && $_SHOP_CONF['sys_test_ipn']) {
            // Special config value set only in config.php for IPN testing
            return true;
        }

        // Count purchases with txn_id, if > 0
        $count = DB_count($_TABLES['shop.ipnlog'], 'txn_id', $this->txn_id);
        if ($count > 0) {
            SHOP_log("Received duplicate IPN {$this->txn_id} for {$this->gw_id}", SHOP_LOG_ERROR);
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
        $Cur = $this->getCurrency();
        $total_credit = $this->calcTotalCredit();
        $credit = $this->getCredit();
        // Compare total order amount to gross payment.  The ".0001" is to help
        // kill any floating-point errors. Include any discount.
        if (!$this->Order) {
            return false;
        }
        $total_order = $this->Order->getTotal();
        $msg = $Cur->FormatValue($this->getPmtGross()) . ' received plus ' .
            $Cur->FormatValue($credit) .' credit, require ' .
            $Cur->FormatValue($total_order);
        if ($total_order <= $total_credit + .0001) {
            SHOP_log("OK: $msg", SHOP_LOG_DEBUG);
            return true;
        } else {
            SHOP_log("Insufficient Funds: $msg", SHOP_LOG_ERROR);
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
            $P = Product::getByID($item['item_number']);
            if ($P->isNew) {
                $this->Error("Item {$item['item_number']} not found - txn " .
                        $this->getTxnId());
                continue;
            }

            $this->items[$id]['prod_type'] = $P->prod_type;
            SHOP_log("Shop item " . $item['item_number'], SHOP_LOG_DEBUG);

            // If it's a downloadable item, then get the full path to the file.
            if ($P->file != '') {
                $this->items[$id]['file'] = $_SHOP_CONF['download_path'] . $P->file;
                $token_base = $this->getTxnId() . time() . rand(0,99);
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
                $this->handleFailure(self::FAILURE_FUNDS,
                        "($logId) Insufficient/incorrect funds for purchase");
                return false;
            }

            // Get the gift card amount applied to this order and save it with the order record.
            $by_gc = $this->getCredit('gc');
            $this->Order->by_gc = $by_gc;
            \Shop\Products\Coupon::Apply($by_gc, $this->Order->uid, $this->Order);

            // Log all non-payment credits applied to the order
            foreach ($this->credits as $key=>$val) {
                if ($val > 0) {
                    $this->Order->Log(
                        sprintf(
                            $LANG_SHOP['amt_paid_gw'],
                            $val,
                            SHOP_getVar($LANG_SHOP, $key, 'string', 'Unknown')
                        )
                    );
                }
            }

            $this->Order->pmt_method = $this->gw_id;
            $this->Order->pmt_txn_id = $this->txn_id;
            $this->Order->Save();

            // Handle the purchase for each order item
            $ipn_data = $this->ipn_data;
            $ipn_data['status'] = $this->status;
            $ipn_data['custom'] = $this->custom;
            foreach ($this->Order->getItems() as $item) {
                $item->getProduct()->handlePurchase($item, $this->Order, $ipn_data);
            }
            if ($this->pmt_gross > 0) {
                $this->Order->Log(sprintf(
                    $LANG_SHOP['amt_paid_gw'],
                    $this->pmt_gross,
                    $this->GW->getDisplayName()
                ));
            }
        } else {
            SHOP_log('Error creating order: ' . print_r($status,true), SHOP_LOG_ERROR);
            return false;
        }

        // Update the status last since it sends the notification.
        $this->Order->updateStatus($this->status, 'IPN: ' . $this->GW->getDscp());
        $this->recordPayment();
        if ($this->status == 'paid' && $this->Order->isDownloadOnly()) {
            // If this paid order has only downloadable items, them mark
            // it closed since there's no further action needed.
            // Notification should have been done above, set notify to false to
            // avoid duplicates.
            $this->setStatus(self::CLOSED);
            $this->Order->updateStatus($this->status, 'IPN: ' . $this->GW->getDscp(), false);
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
        $order_id = DB_getItem(
            $_TABLES['shop.orders'],
            'order_id',
            "pmt_txn_id='" . DB_escapeString($this->txn_id) . "'"
        );
        if (!empty($order_id)) {
            $this->Order = Order::getInstance($order_id);
            if ($this->Order->order_id != '') {
                $this->Order->log_user = $this->GW->getDscp();
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
            $U = Customer::getInstance($uid);
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
        $this->Order->log_user = $this->GW->getDscp();

        $this->Order->items = array();
        foreach ($this->items as $id=>$item) {
            $options = DB_escapeString($item['options']);
            $option_desc = array();
            //$tmp = explode('|', $item['item_number']);
            //list($item_number,$options) =
            //if (is_numeric($item_number)) {
            $P = Product::getByID($item['item_id'], $this->custom);
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

        // Try to get original order information.  Use the "parent transaction"
        // or invoice number, if available from the IPN message
        $order_id = $this->getOrderId();
        if (empty($order_id)) {
            $parent_txn = $this->getParentTxnId();
            if ($parent_txn != '') {
                $order_id = DB_getItem(
                    $_TABLES['shop.orders'],
                    'order_id',
                    "pmt_txn_id = '" . DB_escapeString($parent_txn_id) . "'"
                );
            }
        }

        if (!empty($order_id)) {
            $Order = Order::getInstance($order_id);
        }
        if (!$Order || $Order->isNew) {
            return false;
        }

        // Figure out if the entire order was refunded
        $refund_amt = abs($this->getPmtGross());

        $item_total = 0;
        foreach ($Order->getItems() as $key=>$Item) {
            $item_total += $Item->quantity * $Item->price;
        }
        $item_total += $Order->miscCharges();

        if ($item_total == $refund_amt) {
            // Completely refunded, let the items handle any refund actions.
            // None for catalog items since there's no inventory,
            // but plugin items may need to do something.
            foreach ($Order->getItems() as $key=>$data) {
                $P = Product::getByID($data['product_id'], $this->custom);
                // Don't care about the status, really.  May not even be
                // a plugin function to handle refunds
                $P->handleRefund($Order, $this->ipn_data);
            }
            // Update the order status to Refunded
            $Order->updateStatus('refunded');
        }
        $msg = sprintf($LANG_SHOP['refunded_x'], $this->getCurrency()->Format($refund_amt));
        $Order->Log($msg);
    }


    /**
     * Handle a subscription payment. (Not implemented yet).
     *
     * @todo Implement handleSubscription
     */
    /*private function handleSubscription()
    {
        $this->handleFailure(self::FAILURE_UNKNOWN, "Subscription not handled");
    }*/


    /**
     * Handle a Donation payment (Not implemented).
     *
     * @todo Implement handleDonation
     */
    /*private function handleDonation()
    {
        $this->handleFailure(self::FAILURE_UNKNOWN, "Donation not handled");
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
    protected function handleFailure($type = self::FAILURE_UNKNOWN, $msg = '')
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
        SHOP_log('IPN Debug: ' . $msg, SHOP_LOG_DEBUG);
    }


    /**
     * Log an error message.
     * This just formats the message to indicate the gateway ID.
     *
     * @param   string  $str    Error message to log
     */
    protected function Error($str)
    {
        SHOP_log($this->gw_id. ' IPN Exception: ' . $str, SHOP_LOG_ERROR);
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
        $cls = __NAMESPACE__ . '\\ipn\\' . $name;
        if (class_exists($cls)) {
            return new $cls($vars);
        } else {
            SHOP_log("IPN::getInstance() - $cls doesn't exist");
            return NULL;
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
        return $this;
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
            $retval = (float)$this->verifyCredit($key);
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
                if (\Shop\Products\Coupon::verifyBalance($by_gc, $this->uid)) {
                    $retval[$key] = $credit;
                } else {
                    $gc_bal = \Shop\Products\Coupon::getUserBalance($this->uid);
                    SHOP_log("Insufficient Gift Card Balance, need $by_gc, have $gc_bal", SHOP_LOG_DEBUG);
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
     * Credits are forced to round up to the next decimal value.
     *
     * @param   string  $order_id   Order ID gleaned from the IPN message
     * @return  object      Order object
     */
    protected function getOrder($order_id = NULL)
    {
        if ($order_id === NULL) {
            $order_id = $this->order_id;
        }
        $this->Order = Order::getInstance($order_id);
        $this->addCredit('gc', $this->getCurrency()->RoundUp($this->Order->getInfo('apply_gc')));
        return $this->Order;
    }


    /**
     * Get the URL to a single IPN detail record.
     *
     * @param   string|integer $val Key value
     * @param   string  $key    DB key name, "id" (default) or "txn_id"
     * @return  string      URL to detail display
     */
    public static function getDetailUrl($val, $key='id')
    {
        switch ($key) {
        case 'txn_id':
        case 'id':
            break;      // key already valid
        default:
            $key = 'id';    // invalid, set to default
            break;
        }
        $url = SHOP_ADMIN_URL . '/index.php?ipndetail=x&' . $key . '=' . $val;
        return $url;
     }


    /**
     * Purge all IPN logs from the database.
     * No safety check or confirmation is done; that should be done before
     * calling this function.
     */
    public static function Purge()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['shop.ipnlog']}");
    }


    /**
     * Count IPN records. Optionally provide a field name and value.
     *
     * @param   string  $id     DB field name
     * @param   mixed   $value  Value of DB field.
     * @return  integer     Count of matching records
     */
    public static function Count($id='', $value='')
    {
        global $_TABLES;
        return DB_count($_TABLES['shop.ipnlog'], $id, $value);
    }


    /**
     * Create a payment record for this IPN message.
     *
     * @return  object  New payment object
     */
    protected function recordPayment()
    {
        global $LANG_SHOP;

        $Pmt = new Payment;
        $Pmt->setRefID($this->getTxnId())
            ->setAmount($this->getPmtGross())
            ->setGateway($this->gw_id)
            ->setMethod($this->GW->getDisplayName())
            ->setComment($LANG_SHOP['ipn_pmt_comment'])
            ->setOrderID($this->Order->getOrderId());
        return $Pmt->Save();
    }

}   // class IPN

?>
