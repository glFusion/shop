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
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use Shop\Log;
use Shop\Loggers\IPN as logIPN;
use Shop\Products\Coupon;
use Shop\Models\OrderStatus;
use Shop\Models\CustomInfo;
use Shop\Models\DataArray;
use Shop\Models\IPN as IPNModel;


// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}


/**
 * Class to deal with IPN transactions from a payment gateway.
 * @package shop
 */
class IPN
{
    // Deprecated constants required for upgrades.
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

    /** Payment date/time.
     * @var object */
    private $txn_date = NULL;

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

    /** Event or message type for logging. Normally `payment`.
     * @var string */
    private $event = 'payment';

    /** Holder for the complete IPN data array.
     * Used only for recording the raw data; no processing is done on this data
     * by the base class. Instantiated classes will use this to populate the
     * standard variables.
     * @var array
     */
    protected $ipn_data = NULL;

    /** Custom data that comes from the IPN provider, typically pass-through.
     * @var array
     */
    protected $custom = NULL;

    /** Shipping address information.
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
    protected $Order = NULL;

    /** Payment object.
     * @var object */
    protected $Payment = NULL;

    /** IPN Model object to hold key info in a standard layout.
     * @var object */
    protected $IPN = NULL;

    /** Record number of the IPN log entry.
     * @var integer */
    protected $ipnLogId = 0;


    /**
     * Set up variables received in the IPN message.
     * Stores the complete IPN message in ipn_data.
     * Must be called by the IPN processor's constructor after gw_id is set.
     *
     * @param   array   $A      $_POST'd variables from the gateway
     */
    public function __construct(?DataArray $A=NULL)
    {
        if ($A) {
            $this->ipn_data = $A;
        }
        $this->IPN = new IPNModel;
        $this->IPN['gw_name'] = $this->gw_id;
        $this->custom = new CustomInfo;
        // Create a gateway object to get some of the config values
        $this->GW = Gateway::getInstance($this->gw_id);
        // If the transaction date wasn't set by the handler,
        // make sure it's set here.
        $this->setTxnDate();
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
        $this->IPN['pmt_gross'] = $this->pmt_gross;
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
        return (float)$this->pmt_handling;
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
        $this->IPN->setUid($uid);
        $this->IPN->setCustom('uid', $uid);
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
        $this->IPN['txn_id'] = $txn_id;
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
     * Set the transaction date/time.
     *
     * @param   string|integer  $dt     Datetime string or timestamp
     * @return  object  $this
     */
    public function setTxnDate($dt='now')
    {
        global $_CONF;

        $this->txn_date = new \Date($dt, $_CONF['timezone']);
        $this->IPN['sql_date'] = $this->txn_date->toMySQL(true);
        return $this;
    }


    /**
     * Get the transaction date object.
     *
     * @return  object      Date object
     */
    public function getTxnDate()
    {
        return $this->txn_date;
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
        $this->IPN['payer_email'] = $email;
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
        $this->IPN['payer_name'] = $name;
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
    public function setStatus(string $status) : self
    {
        if (OrderStatus::isValid($status)) {
            $this->status = $status;
        } else {
            $this->status = 'unknown';
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
        if (
            !isset($args['item_id']) ||
            !isset($args['quantity']) ||
            !isset($args['price'])
        ) {
            return;
        }

        $args = new DataArray($args);

        // Separate the item ID and options to get pricing
        $tmp = explode('|', $args['item_id']);
        $P = Product::getByID($tmp[0], $this->custom);
        if ($P->isNew()) {
            Log::error("Product {$args['item_id']} not found in catalog");
            return;      // no product found to add
        }
        if (isset($tmp[1])) {
            $opts = explode(',', $tmp[1]);
        } else {
            $opts = array();
        }
        // If the product allows the price to be overridden, just take the
        // IPN-supplied price. This is the case for donations.
        //$overrides = $this->custom->toArray();
        $overrides = array();
        $overrides['price'] = $args->getFloat('price');
        $overrides['tax'] = $args->getFloat('tax');
        $price = $P->getPrice($opts, $args->getInt('quantity'), $overrides);

        $this->items[] = array(
            'item_id'   => $args->getString('item_id'),
            'item_number' => $tmp[0],
            'name'      => $args->getString('item_name'),
            'quantity'  => $args->getInt('quantity'),
            'price'     => $price,      // price including options
            'shipping'  => $args->getFloat('shipping'),
            'handling'  => $args->getFloat('handling'),
            //'tax'       => $tax,
            'taxable'   => $P->isTaxable() ? 1 : 0,
            'options'   => isset($tmp[1]) ? $tmp[1] : '',
            'extras'    => $args->getString('extras'),
            'overrides' => $overrides,
            'custom'    => $this->custom->toArray(),
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
        $order_id = $this->Order !== NULL ? $this->Order->getOrderId() : '';
        $ipn = new logIPN();
        $ipn->setOrderID($order_id)
            ->setRefID($this->txn_id)   // same as TxnID for IPN messages
            ->setTxnID($this->txn_id)
            ->setGateway($this->gw_id)
            ->setEvent($this->event)
            ->setVerified($verified)
            ->setData($this->ipn_data->toArray());
        $this->ipnLogId = $ipn->Write();
        return $this->ipnLogId;
    }


    /**
     * Checks that the transaction id is unique to prevent double counting.
     *
     * @return  boolean             True if unique, False otherwise
     */
    protected function isUniqueTxnId() : bool
    {
        global $_TABLES;

        if (Config::get('sys_test_ipn')) {
            // Special config value set only in config.php for IPN testing
            return true;
        }

        $db = Database::getInstance();
        // Count purchases with txn_id, if > 0
        $count = $db->getCount(
            $_TABLES['shop.ipnlog'],
            array('gateway', 'ref_id', 'event'),
            array($this->GW->getName(), $this->txn_id, $this->event),
            array(Database::STRING, Database::STRING, Database::STRING)
        );
        if ($count > 0) {
            Log::error("Received duplicate IPN {$this->txn_id} for {$this->gw_id}");
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
            Log::debug("OK: $msg");
            return true;
        } else {
            Log::error("Insufficient Funds: $msg");
            return false;
        }
    }


    /**
     * Handles the item purchases.
     * The purchase should already have been validated; this function simply
     * records the purchases. Purchased files will be emailed to the
     * customer by Order::Notify().
     *
     * @uses    self::createOrder()
     * @return  boolean     True if processed successfully, False if not
     */
    protected function handlePurchase()
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        $status = is_null($this->Order) ? $this->createOrder() : 0;
        if ($status) {
            Log::error('Error creating order: ' . print_r($status,true));
            return false;
        } else {
            $order_id  = $this->Order->getOrderId();
            // Now all of the items are in the order object, check for sufficient
            // funds. If OK, then save the order and call each handlePurchase()
            // for each item.
            if (!$this->isSufficientFunds()) {
                $logId = $this->gw_name . ' - ' . $this->txn_id;
                $this->handleFailure(
                    self::FAILURE_FUNDS,
                    "($logId) Insufficient/incorrect funds for purchase"
                );
                return false;
            }

            // Get the gift card amount applied to this order and save it with the order record.
            $coupons = \Shop\Products\Coupon::Apply(
                $this->Order->getGC(),
                $this->Order->getUid(),
                $this->Order
            );
            if ($coupons !== false) {
                $this->Order->setInfo('applied_gc', $coupons);
            } else {
                $user_bal = \Shop\Products\Coupon::getUserBalance($this->Order->getUid());
                $this->handleFailure(
                    self::FAILURE_FUNDS,
                    "Insufficient coupon balance " . $user_bal .
                    " for requested amount " . $this->Order->getGC()
                );
                return false;
            }

            // Log all non-payment credits applied to the order
            foreach ($this->credits as $key=>$val) {
                if ($val > 0) {
                    $this->Order->Log(
                        sprintf(
                            $LANG_SHOP['amt_paid_gw'],
                            $val,
                            isset($LANG_SHOP[$key]) ? $LANG_SHOP[$key] : 'Unknown'
                        )
                    );
                }
            }
            $this->Order->Save();
            $this->setOrderID($order_id);
            $this->recordPayment($order_id);

            // Handle the purchase for each order item
            $ipn_data = $this->ipn_data;
            $ipn_data['status'] = $this->status;
            $ipn_data['custom'] = (string)$this->custom;
            //$ipn_data['uid'] = $this->Order->getUid();
            //$ipn_data['sql_date'] = $_CONF['_now']->toMySQL(true);
            $this->IPN['uid'] = $this->Order->getUid();
            $this->Order->handlePurchase($this->IPN);
        }
        return true;
    }


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
        global $_TABLES;

        // See if an order already exists for this transaction.
        // If so, load it and update the status. If not, continue on
        // and create a new order
        $db = Database::getInstance();
        try {
            $order_id = $db->getItem(
                $_TABLES['shop.orders'],
                'order_id',
                array('pmt_txn_id' => $this->txn_id)
            );
        } catch (\Exception $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return 1;
        }

        if (!empty($order_id)) {
            $this->Order = Order::getInstance($order_id);
            if ($this->Order->getOrderID() != '') {
                $this->Order->setLogUser($this->GW->getDscp());
            }
            return 2;
        } else {
            // Need to create a new, empty order object
            $this->Order = Order::getInstance(0);
            if (isset($this->custom['ref_token']) && !empty($this->custom['ref_token'])) {
                $this->Order->setReferralToken($this->custom['ref_token']);
            }

            foreach ($this->items as $id=>$item) {
                $item = new DataArray($item);
                $option_desc = array();
                $P = Product::getByID($item->getString('item_id'), $this->custom);
                $item['short_description'] = $P->getShortDscp();
                if (!empty($item['options'])) {
                    // options is expected as CSV
                    try {
                        $data = $db->conn->executeQuery(
                            "SELECT attr_name, attr_value
                            FROM {$_TABLES['shop.prod_attr']}
                            WHERE attr_id IN (?)",
                            array($item['options']),
                            array(Database::PARAM_INT_ARRAY)
                        )->fetchAllAssociative();
                    } catch (\Exception $e) {
                        Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                        $data = false;
                    }
                    $opt_str = '';
                    if (is_array($data)) {
                        foreach ($data as $O) {
                            $opt_str .= ', ' . $O['attr_value'];
                            $option_desc[] = $O['attr_name'] . ': ' . $O['attr_value'];
                        }
                    }
                }

                // Get the product record and custom strings
                if (
                    isset($item['extras']['custom']) &&
                    is_array($item['extras']['custom']) &&
                    !empty($item['extras']['custom'])
                ) {
                    foreach ($item['extras']['custom'] as $cust_id=>$cust_val) {
                        $option_desc[] = $P->getCustom($cust_id) . ': ' . $cust_val;
                    }
                }
                $args = array(
                    'order_id' => $this->Order->getorderID(),
                    'product_id' => $item->getString('item_number'),
                    'description' => $item->getString('short_description'),
                    'quantity' => $item->getInt('quantity'),
                    'txn_type' => $this->custom['transtype'],
                    'txn_id' => $this->txn_id,
                    'status' => 'pending',
                    'token' => md5(time()),
                    'price' => $item['price'],
                    'taxable' => $P->isTaxable(),
                    'options' => $item['options'],
                    'options_text' => $option_desc,
                    'extras' => $item['extras'],
                    'shipping' => $item->getFloat('shipping'),
                    'handling' => $item->getFloat('handling'),
                    'paid' => SHOP_getVar($item['overrides'], 'price', 'float', $item['price']),
                );
                $this->Order->addItem(new DataArray($args));
            }   // foreach item
        }
        $this->Order->setUid($this->uid);
        $this->Order->setBuyerEmail($this->payer_email);
        $this->Order->setStatus('pending');
        if ($this->uid > 1) {
            $U = Customer::getInstance($this->uid);
            $this->Order->setBillto($U->getDefaultAddress('billto'));
        }

        // Get the billing and shipping addresses from the cart record,
        // if any.  There may not be a cart in the database if it was
        // removed by a previous IPN, e.g. this is the 'completed' message
        // and we already processed a 'pending' message
        $ShipTo = $this->shipto;
        if (!empty($ShipTo)) {
            $this->Order->setShipto($ShipTo);
        } elseif ($this->uid > 1) {
            $this->Order->setShipto($U->getDefaultAddress('shipto'));
        }

        // We're creating an order based on a single IPN message,
        // so just trust the numbers received.
        $this->Order->setPmtMethod($this->gw_id)
            ->setPmtTxnID($this->txn_id)
            ->setShipping($this->pmt_shipping)
            ->setHandling($this->pmt_handling)
            ->setBuyerEmail($this->payer_email)
            ->setLogUser($this->GW->getDscp());
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
        global $_TABLES, $_CONF, $LANG_SHOP;

        // Try to get original order information.  Use the "parent transaction"
        // or invoice number, if available from the IPN message
        $order_id = $this->getOrderId();
        if (empty($order_id)) {
            $parent_txn_id = $this->getParentTxnId();
            if ($parent_txn_id != '') {
                $parentPmt = Payment::getByReference($parent_txn_id);
                if ($parentPmt->getPmtId() > 0) {  // valid payment found
                    $order_id = $parentPmt->getOrderId();
                }
            }
        }

        if (!empty($order_id)) {
            $Order = Order::getInstance($order_id);
        }
        if (!$Order || $Order->isNew()) {
            return false;
        }

        // Figure out if the entire order was refunded
        $refund_amt = abs($this->getPmtGross());

        $item_total = 0;
        foreach ($Order->getItems() as $key=>$Item) {
            $item_total += $Item->getQuantity() * $Item->getPrice();
        }
        $item_total += $Order->miscCharges();

        if ($item_total <= $refund_amt) {
            // Completely refunded, let the items handle any refund actions.
            // None for catalog items since there's no inventory,
            // but plugin items may need to do something.
            foreach ($Order->getItems() as $key=>$OI) {
                $OI->getProduct()->handleRefund($OI, $this->IPN);
                // Don't care about the status, really.  May not even be
                // a plugin function to handle refunds
            }
            // Update the order status to Refunded
            $Order->updateStatus('refunded');
        }
        $this->recordPayment($order_id);
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
        Log::debug('IPN Debug: ' . $msg);
    }


    /**
     * Log an error message.
     * This just formats the message to indicate the gateway ID.
     *
     * @param   string  $str    Error message to log
     */
    protected function Error($str)
    {
        Log::error($this->gw_id. ' IPN Exception: ' . $str);
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
        $cls = __NAMESPACE__ . '\\Gateways\\' . $name . '\\ipn';
        if (class_exists($cls)) {
            return new $cls($vars);
        } else {
            Log::error(__METHOD__ . " - $cls doesn't exist");
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
                if (Coupon::verifyBalance($credit, $this->uid)) {
                    $retval[$key] = $credit;
                } else {
                    $gc_bal = Coupon::getUserBalance($this->uid);
                    Log::debug("Insufficient Gift Card Balance, need $credit, have $gc_bal");
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
        $this->addCredit('gc', $this->getCurrency()->RoundUp($this->Order->getGC()));
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

        $db = Database::getInstance();
        try {
            $db->conn->executeStatement(
                "TRUNCATE {$_TABLES['shop.ipnlog']}"
            );
        } catch (\Exception $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Count IPN records. Optionally provide a field name and value.
     *
     * @param   string  $id     DB field name
     * @param   mixed   $value  Value of DB field.
     * @return  integer     Count of matching records
     */
    public static function Count() : int
    {
        global $_TABLES;

        $db = Database::getInstance();
        return $db->getCount($_TABLES['shop.ipnlog']);
    }


    /**
     * Create a payment record for this IPN message.
     *
     * @return  object  New payment object
     */
    protected function recordPayment($order_id='')
    {
        global $LANG_SHOP;

        if ($this->ipnLogId == 0) {     // IPN not logged yet
            $this->Log();
        }
        $this->Payment = new Payment;
        $this->Payment->setRefID($this->getTxnId())
            ->setUid($this->getUid())
            ->setAmount($this->getPmtGross())
            ->setGateway($this->gw_id)
            ->setMethod($this->GW->getDisplayName())
            ->setComment($LANG_SHOP['ipn_pmt_comment'])
            ->setStatus($this->status)
            ->setOrderID($order_id);
        return $this->Payment->Save();
    }


    /**
     * Verify that the webhook message is valid and can be processed.
     * This stub function always returns true, derived classes should test
     * the actual IPN message.
     *
     * @return  boolean     True if valid, False if not.
     */
    public function Verify() : bool
    {
        return true;
    }


    /**
     * Set the event type, e.g. payment, cancelled, etc.
     * This is logged in the ipnlog table and used to help check for duplicate
     * messages.
     *
     * @param   string  $event  Event type supplied by the IPN message
     * @return  object  $this
     */
    public function setEvent($event)
    {
        $this->event = $event;
        return $this;
    }


    /**
     * Get the actual event code sent by the gateway.
     *
     * @return  string      Event code
     */
    public function getEvent()
    {
        return $this->event;
    }


    /**
     * Echo a response to the IPN request based on the status from Process().
     *
     * @param   boolean $status     Result from processing
     * @return  void
     */
    public function Response($status)
    {
        echo "Thanks";
        exit;
    }

}
