<?php
/**
 * This file contains the Shop IPN class to deal with IPN transactions from Shop.
 * Based on the gl-shop Plugin for Geeklog CMS by Vincent Furia.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\ipn;

use \Shop\Cart;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Define failure reasons */
define('SHOP_FAILURE_UNKNOWN', 0);
define('SHOP_FAILURE_VERIFY', 1);
define('SHOP_FAILURE_COMPLETED', 2);
define('SHOP_FAILURE_UNIQUE', 3);
define('SHOP_FAILURE_EMAIL', 4);
define('SHOP_FAILURE_FUNDS', 5);

/**
 * Class to deal with IPN transactions from Shop.
 * @since   v0.5.0
 * @package shop
 */
class paypal extends \Shop\IPN
{
    /**
     * Constructor.  Set up variables received from Shop.
     *
     * @param   array   $A      $_POST'd variables from Shop
     */
    function __construct($A=array())
    {
        $this->gw_id = 'paypal';
        parent::__construct($A);

        $this->txn_id = SHOP_getVar($A, 'txn_id');
        $this->payer_email = SHOP_getVar($A, 'payer_email');
        $this->payer_name = SHOP_getVar($A, 'first_name') .' '. SHOP_getVar($A, 'last_name');
        $this->pmt_date = SHOP_getVar($A, 'payment_date');
        $this->pmt_gross = SHOP_getVar($A, 'mc_gross', 'float');
        $this->pmt_tax = SHOP_getVar($A, 'tax', 'float');
        $this->gw_desc = $this->gw->Description();
        $this->gw_name = $this->gw->Name();
        $this->currency = SHOP_getVar($A, 'mc_currency', 'string', 'Unk');
        $this->addCredit('discount', SHOP_getVar($A, 'discount', 'float', 0));
        if (isset($A['invoice'])) {
            $this->order_id = $A['invoice'];
        }

        //if (isset($A['parent_txn_id']))
        //    $this->parent_txn_id = $A['parent_txn_id'];

        // Check a couple of vars to see if a shipping address was supplied
        if (isset($A['address_street']) || isset($A['address_city'])) {
            $this->shipto = array(
                'name'      => SHOP_getVar($A, 'address_name'),
                'address1'  => SHOP_getVar($A, 'address_street'),
                'address2'  => '',
                'city'      => SHOP_getVar($A, 'address_city'),
                'state'     => SHOP_getVar($A, 'address_state'),
                'country'   => SHOP_getVar($A, 'address_country'),
                'zip'       => SHOP_getVar($A, 'address_zip'),
            );
        }

        // Set the custom data into an array.  If it can't be unserialized,
        // then treat it as a single value which contains only the user ID.
        if (isset($A['custom'])) {
            $this->custom = @unserialize(str_replace('\'', '"', $A['custom']));
            if (!$this->custom) {
                $this->custom = array('uid' => $A['custom']);
            }
        }
        $this->uid = SHOP_getVar($this->custom, 'uid', 'integer', 1);

        switch ($this->ipn_data['payment_status']) {
        case 'Pending':
            $this->status = 'pending';
            break;
        case 'Completed':
            $this->status = 'paid';
            break;
        case 'Refunded':
            $this->status = 'refunded';
            break;
        }
        $this->ipn_data['status'] = $this->status;  // to get into handlePurchase()

        switch (SHOP_getVar($A, 'txn_type')) {
        case 'web_accept':
        case 'send_money':
            $this->pmt_shipping = SHOP_getVar($A, 'shipping', 'float');
            $this->pmt_handling = SHOP_getVar($A, 'handling', 'float');
            break;
        case 'cart':
            $this->pmt_shipping = SHOP_getVar($A, 'mc_shipping', 'float');
            $this->pmt_handling = SHOP_getVar($A, 'mc_handling', 'float');
            break;
        }
    }


    /**
     * Verify the transaction with Shop.
     * Validate transaction by posting data back to the shop webserver.
     * The response from shop should include 'VERIFIED' on a line by itself.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    private function Verify()
    {
        if ($this->gw->getConfig('test_mode') && isset($this->ipn_data['test_ipn'])) {
            return true;
        }

        // Default verification to false
        $verified = false;

        // read the post from PayPal system and add 'cmd'
        $req = 'cmd=_notify-validate';

        // re-encode the transaction variables to be verified
        foreach ($this->ipn_data as $key => $value) {
            $value = urlencode($value);
            $req .= "&$key=$value";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->gw->getPostBackUrl());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        $SHOP_response = curl_exec($ch);
        curl_close($ch);
        if (strcmp($SHOP_response, 'VERIFIED') == 0) $verified = true;
        return $verified;
    }


    /**
     * Confirms that payment status is complete.
     * Complete means not 'denied', 'failed', 'pending', etc.
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
     * Process an incoming IPN transaction.
     * Do the following:
     * - Verify IPN
     * - Log IPN
     * - Check that transaction is complete
     * - Check that transaction is unique
     * - Check for valid receiver email address
     * - Process IPN
     *
     * @uses    IPN::AddItem()
     * @uses    IPN::handleFailure()
     * @uses    IPN::handlePurchase()
     * @uses    IPN::isUniqueTxnId()
     * @uses    IPN::isSufficientFunds()
     * @uses    IPN::Log()
     * @uses    Verify()
     * @return  boolean true if processing valid and completed, false otherwise
     */
    public function Process()
    {
        // If no data has been received, then there's nothing to do.
        if (empty($this->ipn_data)) {
            return false;
        }

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(
                SHOP_FAILURE_VERIFY,
                "($logId) Verification failed"
            );
            return false;
        } else {
            $logId = $this->Log(true);
        }

        // Set the custom data field to the exploded value.  This has to
        // be done after Verify() or the Shop verification will fail.
        //$this->custom = $this->custom;
        switch ($this->ipn_data['txn_type']) {
        case 'web_accept':  //usually buy now
        case 'send_money':  //usually donation/send money
            $item_number = SHOP_getVar($this->ipn_data, 'item_number');
            $quantity = SHOP_getVar($this->ipn_data, 'quantity', 'float');
            $fees_paid = $this->pmt_tax + $this->pmt_shipping + $this->pmt_handling;

            if (empty($item_number)) {
                $this->handleFailure(NULL, 'Missing Item Number in Buy-now process');
                return false;
            }
            if (empty($quantity)) {
                $quantity = 1;
            }
            $this->pmt_net = $this->pmt_gross - $fees_paid;
            $unit_price = $this->pmt_gross / $quantity;
            $args = array(
                'item_id'   => $item_number,
                'quantity'  => $quantity,
                'price'     => $unit_price,
                'item_name' => SHOP_getVar($this->ipn_data, 'item_name', 'string', 'Undefined'),
                'shipping'  => $this->pmt_shipping,
                'handling'  => $this->pmt_handling,
            );
            $this->AddItem($args);

            SHOP_debug("Net Settled: $payment_gross $this->currency", 'debug_ipn');
            $this->handlePurchase();
            break;

        case 'cart':
            // shopping cart
            // Create a cart and read the info from the cart table.
            $this->Order = $this->getOrder($this->order_id);
            if ($this->Order->isNew) {
                $this->handleFailure(NULL, "Order ID {$this->order_id} not found for cart purchases");
                return false;
            }
            $this->pmt_tax = (float)$this->Order->getInfo('tax');
            $this->pmt_shipping = (float)$this->Order->getInfo('shipping');
            $this->pmt_handling = (float)$this->Order->getInfo('handling');
            $fees_paid = 0;
            $Cart = $this->Order->getItems();
            if (empty($Cart)) {
                COM_errorLog("Shop\\shop_ipn::Process() - Empty Cart for id {$this->Order->order_id}");
                return false;
            }

            foreach ($Cart as $item) {
                $item_id = $item->product_id;
                if ($item->options != '') {
                    $item_id .= '|' . $item->options;
                }
                $args = array(
                        'item_id'   => $item_id,
                        'quantity'  => $item->quantity,
                        'price'     => $item->price,
                        'item_name' => $item->getShortDscp(),
                        'shipping'  => $item->shipping,
                        'handling'  => $item->handling,
                        'tax'       => $item->tax,
                        'extras'    => $item->extras,
                    );
                $this->AddItem($args);
            }

            $payment_gross = SHOP_getVar($this->ipn_data, 'mc_gross', 'float') - $fees_paid;
            SHOP_debug("Received $payment_gross gross payment", 'debug_ipn');
            $this->handlePurchase();
            break;

        // other, unknown, unsupported
        default:
            switch (SHOP_getVar($this->ipn_data, 'reason_code')) {
            case 'refund':
                $this->handleRefund();
                break;
            default:
                $this->handleFailure(
                    SHOP_FAILURE_UNKNOWN,
                    "($logId) Unknown transaction type"
                );
                return false;
                break;
            }
            break;
        }

        return true;
    }   // function Process

}   // class shop_ipn

?>
