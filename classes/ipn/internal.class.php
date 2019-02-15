<?php
/**
 * This file contains the Dummy IPN class.
 * It is used with orders that have zero balances and thus don't go through
 * an actual payment processor.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner
 * @package     shop
 * @version     v0.6.0
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
 * @since   v0.6.0
 * @package shop
 */
class internal extends \Shop\IPN
{
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

        $cart_id = SHOP_getVar($A, 'cart_id');
        if (!empty($cart_id)) {
            $this->Order = Cart::getInstance(0, $cart_id);
        }
        if (!$this->Order) return NULL;

        $billto = $this->Order->getAddress('billto');
        $shipto = $this->Order->getAddress('shipto');
        if (empty($shipto)) $shipto = $billto;
        if (COM_isAnonUser()) $_USER['email'] = '';

        $this->payer_email = SHOP_getVar($A, 'payer_email', 'string', $_USER['email']);
        $this->payer_name = trim(SHOP_getVar($A, 'name') .' '. SHOP_getVar($A, 'last_name'));
        if ($this->payer_name == '') {
            $this->payer_name = $_USER['fullname'];
        }
        $this->order_id = $this->Order->order_id;
        $this->txn_id = SHOP_getVar($A, 'txn_id');
        $this->pmt_date = SHOP_now()->toMySQL(true);
        $this->pmt_gross = $this->Order->getTotal();
        $this->pmt_tax = $this->Order->getInfo('tax');
        $this->gw_desc = 'Internal IPN';
        $this->gw_name = 'Internal IPN';
        $this->pmt_status = SHOP_getVar($A, 'payment_status');
        $this->currency = Currency::getInstance()->code;


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

        // Set the custom data into an array. If it can't be unserialized,
        // then treat it as a single value which contains only the user ID.
        if (isset($A['custom'])) {
            $this->custom = @unserialize(str_replace('\'', '"', $A['custom']));
            if (!$this->custom) {
                $this->custom = array('uid' => $A['custom']);
            }
        }
        $this->uid = $this->custom['uid'];
        $this->pmt_status = 'paid';
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
        if ($this->Order === NULL) {
            COM_errorLog("No cart provided");
            return false;
        }

        $info = $this->Order->getInfo();
        $uid = $this->Order->uid;
        $gateway = SHOP_getVar($info, 'gateway');
        $total = $this->Order->getTotal();
        switch ($gateway) {
        case '_coupon':
            // Order total must be zero to use the coupon gateway in full
            if (is_null($by_gc)) {
                $by_gc = Coupon::getUserBalance($uid);
            }
            if ($by_gc < $total) return false;
            // This only handles fully-paid items
            $this->pmt_gross = 0;
            $this->addCredit('gc', min($by_gc, $total));
            break;
        case 'test':
            $this->addCredit('gc', SHOP_getVar($info, 'apply_gc', 'float'));
            $this->pmt_gross = SHOP_getVar($_POST, 'pmt_gross', 'float');
            break;
        }
        $this->status = 'paid';
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
     * @uses    BaseIPN::AddItem()
     * @uses    BaseIPN::handleFailure()
     * @uses    BaseIPN::handlePurchase()
     * @uses    BaseIPN::isUniqueTxnId()
     * @uses    BaseIPN::isSufficientFunds()
     * @uses    BaseIPN::Log()
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

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(IPN_FAILURE_VERIFY,
                            "($logId) Verification failed");
            return false;
        } else {
            $logId = $this->Log(true);
        }

        $Cart = $this->Order->Cart();
        if (empty($Cart)) {
            COM_errorLog("Shop\\internal_ipn::Process() - Empty Cart for id {$this->Order->cartID()}");
            return false;
        }
        $items = array();
        $total_shipping = 0;
        $total_handling = 0;

        // Add the item to the array for the order creation.
        // IPN item numbers are indexes into the cart, so get the
        // actual product ID from the cart
        foreach ($Cart as $idx=>$item) {
            $args = array(
                'item_id'   => $item->item_id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'item_name' => $item->name,
                'shipping'  => $item->shipping,
                'handling'  => $item->handling,
                'extras'    => $item->extras,
            );
            $this->AddItem($args);
            $total_shipping += $item->shipping;
            $total_handling += $item->handling;
        }
        $this->pmt_shipping = $total_shipping;
        $this->pmt_handling = $total_handling;
        return $this->handlePurchase();
    }   // function Process

}   // class \Shop\ipn\internal

?>
