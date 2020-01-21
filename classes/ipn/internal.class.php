<?php
/**
 * This file contains the Dummy IPN class.
 * It is used with orders that have zero balances and thus don't go through
 * an actual payment processor.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\ipn;

use \Shop\Cart;
use \Shop\Currency;
use \Shop\Coupon;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * Class to provide IPN for internal-only transactions, such as zero-balance orders.
 *
 * @package shop
 */
class internal extends \Shop\IPN
{
    /** Indicate if this is "buy_now" or "cart".
     * @var string */
    private $ipn_type;


    /**
     * Constructor.
     * Fake payment gateway variables.
     *
     * @param   array   $A      $_POST'd variables from Shop
     */
    function __construct($A=array())
    {
        global $_USER;

        $this->gw_id = '_internal';
        parent::__construct($A);

        // Get the IPN type, default to "cart" for backward compatibility
        $this->ipn_type = SHOP_getVar($this->ipn_data, 'ipn_type', 'string', 'cart');
        $this->setPmtGross($this->ipn_data['pmt_gross'])
            ->setPmtTax(SHOP_getVar($this->ipn_data, 'tax', 'float'))
            ->setPmtShipping(SHOP_getVar($this->ipn_data, 'shipping', 'float'))
            ->setPmtHandling(SHOP_getVar($this->ipn_data, 'handling', 'float'))
            ->setCurrency()
            ->setTxnId(SHOP_getVar($this->ipn_data, 'txn_id', 'string'));
        $this->gw_name = $this->GW->getName();
        $this->gw_desc = $this->GW->getDscp();

        // Set the custom data into an array. If it can't be unserialized,
        // then treat it as a single value which contains only the user ID.
        if (isset($A['custom'])) {
            $this->custom = @unserialize(str_replace('\'', '"', $A['custom']));
            if (!$this->custom) {
                $this->custom = array('uid' => $A['custom']);
            }
        }
        $this->setUid($this->custom['uid'])
            ->setStatus(self::PAID);
    }


    /**
     * Verify the transaction.
     * This just checks that a valid cart_id was received along with other
     * variables.
     *
     * @return  boolean         True if successfully validated, false otherwise
     */
    private function Verify()
    {
        switch($this->ipn_type) {
        case 'cart':
            if ($this->Order === NULL) {
                SHOP_log("No cart provided", SHOP_LOG_ERROR);
                return false;
            }
            break;
        case 'buy_now':
            $this->setTxnId(uniqid() . rand(100,999));
            $this->createOrder();
            $this->Order->setInfo('gateway', 'test');
            break;
        }

        $info = $this->Order->getInfo();
        $uid = $this->Order->uid;
        $gateway = SHOP_getVar($info, 'gateway');
        $total = $this->Order->getTotal();
        $by_gc = SHOP_getVar($info, 'by_gc', 'float', NULL);
        switch ($gateway) {
        case '_coupon':
            // Order total must be zero to use the coupon gateway in full
            if (is_null($by_gc)) {
                $by_gc = \Shop\Products\Coupon::getUserBalance($uid);
            }
            if ($by_gc < $total) return false;
            // This only handles fully-paid items
            $this->setPmtGross(0);
            $this->addCredit('gc', min($by_gc, $total));
            break;
        case 'test':
            $this->addCredit('gc', SHOP_getVar($info, 'apply_gc', 'float'));
            break;
        }
        $this->setStatus(self::PAID);
        return true;
    }


    /**
     * Confirms that payment status is complete.
     * (not 'denied', 'failed', 'pending', etc.)
     *
     * @param   string  $payment_status     Payment status to verify
     * @return  boolean                     True if complete, False otherwise
     */
    private function isStatusCompleted($payment_status)
    {
        return ($payment_status == 'Completed');
    }


    /**
     * Checks if payment status is reversed or refunded.
     * For example, some sort of cancelation.
     *
     * @param   string  $payment_status     Payment status to check
     * @return  boolean                     True if reversed or refunded
     */
    private function isStatusReversed($payment_status)
    {
        return ($payment_status == 'Reversed' || $payment_status == 'Refunded');
    }


    /**
     * Process an incoming IPN transaction
     * Do the following:
     *  - Verify IPN
     *  - Log IPN
     *  - Check that transaction is complete
     *  - Check that transaction is unique
     *  - Check for valid receiver email address
     *  - Process IPN
     *
     * @uses    IPN::addItem()
     * @uses    IPN::handleFailure()
     * @uses    IPN::handlePurchase()
     * @uses    IPN::isUniqueTxnId()
     * @uses    IPN::Log()
     * @uses    self::Verify()
     * @param   array   $in     POST variables of transaction
     * @return  boolean True if processing valid and completed, false otherwise
     */
    public function Process()
    {
        // If no data has been received, then there's nothing to do.
        if (empty($this->ipn_data)) {
            return false;
        }

        // Make sure this transaction hasn't already been counted.
        if (!$this->isUniqueTxnId()) {
            return false;
        }
        $custom = SHOP_getVar($this->ipn_data, 'custom');
        $this->custom = @unserialize($custom);

        switch ($this->ipn_data['cmd']) {
        case 'buy_now':
            $item_number = SHOP_getVar($this->ipn_data, 'item_number');
            $quantity = SHOP_getVar($this->ipn_data, 'quantity', 'float');
            if (empty($item_number)) {
                $this->handleFailure(NULL, 'Missing Item Number in Buy-now process');
                return false;
            }
            if (empty($quantity)) {
                $quantity = 1;
            }
            $unit_price = $this->getPmtGross() / $quantity;
            $args = array(
                'item_id'   => $item_number,
                'quantity'  => $quantity,
                'price'     => $unit_price,
                'item_name' => SHOP_getVar($this->ipn_data, 'item_name', 'string', 'Undefined'),
                'shipping'  => $this->getPmtShipping(),
                'handling'  => $this->getPmtHandling(),
            );
            $this->addItem($args);

            SHOP_log("Net Settled: {$this->getPmtGross()} {$this->getCurrency()->code}", SHOP_LOG_DEBUG);
            break;

        default:
            $cart_id = SHOP_getVar($this->ipn_data, 'cart_id');
            if (!empty($cart_id)) {
                $this->Order = $this->getOrder($cart_id);
            }
            if (!$this->Order) return NULL;

            $billto = $this->Order->getAddress('billto');
            $shipto = $this->Order->getAddress('shipto');
            if (empty($shipto) && !empty($billto)) $shipto = $billto;
            if (COM_isAnonUser()) $_USER['email'] = '';
            $this->setEmail(SHOP_getVar($this->ipn_data, 'payer_email', 'string', $_USER['email']));
            $this->setPayerName(trim(SHOP_getVar($this->ipn_data, 'name') .' '. SHOP_getVar($this->ipn_data, 'last_name')));
            if ($this->getPayerName() == '') {
                $this->setPayerName($_USER['fullname']);
            }
            $this
                ->setOrderID($this->Order->order_id)
                ->setTxnId(SHOP_getVar($this->ipn_data, 'txn_id'))
                ->setPmtTax($this->Order->getInfo('tax'))
                ->setStatus(SHOP_getVar($this->ipn_data, 'payment_status'));
            //$this->gw_name = 'Internal IPN';
            //$this->gw_desc = 'Internal IPN';

            $this->shipto = array(
                'name'      => SHOP_getVar($shipto, 'name'),
                'company'   => SHOP_getVar($shipto, 'company'),
                'address1'  => SHOP_getVar($shipto, 'address1'),
                'address2'  => SHOP_getVar($shipto, 'address2'),
                'city'      => SHOP_getVar($shipto, 'city'),
                'state'     => SHOP_getVar($shipto, 'state'),
                'country'   => SHOP_getVar($shipto, 'country'),
                'zip'       => SHOP_getVar($shipto, 'zip'),
            );

            break;
        }

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(
                self::FAILURE_VERIFY,
                "($logId) Verification failed"
            );
            return false;
        } else {
            $logId = $this->Log(true);
        }

        $Cart = $this->Order->getItems();
        if (empty($Cart)) {
            SHOP_log("Empty Cart for id {$this->Order->cartID()}", SHOP_LOG_ERROR);
            return false;
        }
        $total_shipping = 0;
        $total_handling = 0;

        // Add the item to the array for the order creation.
        // IPN item numbers are indexes into the cart, so get the
        // actual product ID from the cart
        foreach ($Cart as $idx=>$item) {
            $item_id = $item->product_id;
            if ($item->options != '') {
                $item_id .= '|' . $item->options;
            }
            $args = array(
                'item_id'   => $item_id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'item_name' => $item->name,
                'shipping'  => $item->shipping,
                'handling'  => $item->handling,
                'extras'    => $item->extras,
            );
            $this->addItem($args);
            $total_shipping += $item->shipping;
            $total_handling += $item->handling;
        }
        $this->setPmtShipping($total_shipping)
            ->setPmtHandling($total_handling);
        return $this->handlePurchase();
    }

}   // class \Shop\ipn\internal

?>
