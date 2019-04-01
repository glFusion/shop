<?php
/**
*   This file contains the Square IPN class.
*   It is used with orders that have zero balances and thus don't go through
*   an actual payment processor.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner
*   @package    shop
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Shop\ipn;

use \Shop\Cart;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 *  Class to provide IPN for internal-only transactions,
 *  such as zero-balance orders.
 *
 *  @since 0.6.0
 *  @package shop
 */
class square extends \Shop\IPN
{
    /**
    *   Constructor.
    *   Fake payment gateway variables.
    *
    *   @param  array   $A      $_POST'd variables from Shop
    */
    function __construct($A=array())
    {
        global $_USER, $_CONF;

        $this->gw_id = 'square';
        parent::__construct($A);

        $order_id = SHOP_getVar($A, 'referenceId');
        $this->pmt_gross = 0;
        $this->pmt_fee = 0;

        if (!empty($order_id)) {
            //$this->Order = Cart::getInstance(0, $order_id);
            $this->Order = $this->getOrder(0, $order_id);
        }
        if ($this->Order->isNew) return NULL;

        $this->order_id = $this->Order->order_id;
        $this->txn_id = SHOP_getVar($A, 'transactionId');
        $billto = $this->Order->getAddress('billto');
        $shipto = $this->Order->getAddress('shipto');
        if (empty($shipto)) $shipto = $billto;

        $this->payer_email = $this->Order->buyer_email;
        $this->payer_name = $_USER['fullname'];
        $this->pmt_date = $_CONF['_now']->toMySQL(true);
        $this->gw_name = $this->gw->Name();;
        $this->status = $status;
        $this->currency = $C->code;

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

        // Set the custom data into an array.  If it can't be unserialized,
        // then treat it as a single value which contains only the user ID.
        if (isset($A['custom'])) {
            $this->custom = @unserialize(str_replace('\'', '"', $A['custom']));
            if (!$this->custom) {
                $this->custom = array('uid' => $A['custom']);
            }
        }
        $this->custom = array(
            'transtype' => $this->gw->Name(),
            'uid'       => $this->Order->uid,
            'by_gc'     => $this->Order->getInfo()['apply_gc'],
        );

        foreach ($this->Order->Cart() as $idx=>$item) {
            $args = array(
                'item_id'   => $item->product_id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'item_name' => $item->getShortDscp(),
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
    }


    /**
    *   Verify the transaction.
    *   This just checks that a valid cart_id was received along with other
    *   variables.
    *
    *   @return boolean         true if successfully validated, false otherwise
    */
    private function Verify()
    {
        // Gets the transaction via the Square API to get the real values.
        $trans = $this->gw->getTransaction($this->txn_id);
        COM_errorLog(var_export($trans,true));
        $status = 'pending';
        if ($trans) {
            // Get through the top-level array var
            $trans= SHOP_getVar($trans, 'transaction', 'array');
            if (empty($trans)) return false;

            $tenders = SHOP_getVar($trans, 'tenders', 'array');
            if (empty($tenders)) return false;

            $order_id = SHOP_getVar($trans, 'reference_id');
            if (empty($order_id)) return false;

            $status = 'paid';
            foreach ($tenders as $tender) {
                if ($tender['card_details']['status'] == 'CAPTURED') {
                    $C = \Shop\Currency::getInstance($tender['amount_money']['currency']);
                    $this->pmt_gross += $C->fromInt($tender['amount_money']['amount']);
                    $this->pmt_fee += $C->fromInt($tender['processing_fee_money']['amount']);
                } else {
                    $status = 'pending';
                }
            }
        }
        $this->status = $status;
        return true;


        if ($this->Cart === NULL) {
            COM_errorLog("No cart provided");
            return false;
        }

        // Order total must be zero to use the internal gateway
        $info = $this->Cart->getInfo();
        var_dump($info);die;
        $by_gc = SHOP_getVar($info, 'apply_gc', 'float');
        $total = SHOP_getVar($info, 'final_total', 'float');
        if ($by_gc < $total) return false;
        if (!Coupon::verifyBalance($by_gc, $this->uid)) {
            return false;
        }
        $this->status = 'paid';
        return true;
    }


    /**
    *   Confirms that payment status is complete.
    *   (not 'denied', 'failed', 'pending', etc.)
    *
    *   @param  string  $payment_status     Payment status to verify
    *   @return boolean                     True if complete, False otherwise
    */
/*    private function isStatusCompleted($payment_status)
    {
        return ($payment_status == 'Completed');
    }
 */

    /**
    *   Checks if payment status is reversed or refunded.
    *   For example, some sort of cancelation.
    *
    *   @param  string  $payment_status     Payment status to check
    *   @return boolean                     True if reversed or refunded
    */
/*    private function isStatusReversed($payment_status)
    {
        return ($payment_status == 'Reversed' || $payment_status == 'Refunded');
    }
 */

    /**
    *   Process an incoming IPN transaction
    *   Do the following:
    *       1. Verify IPN
    *       2. Log IPN
    *       3. Check that transaction is complete
    *       4. Check that transaction is unique
    *       5. Check for valid receiver email address
    *       6. Process IPN
    *
    *   @uses   IPN::AddItem()
    *   @uses   IPN::handleFailure()
    *   @uses   IPN::handlePurchase()
    *   @uses   IPN::isUniqueTxnId()
    *   @uses   IPN::isSufficientFunds()
    *   @uses   IPN::Log()
    *   @uses   Verify()
    *   @param  array   $in     POST variables of transaction
    *   @return boolean true if processing valid and completed, false otherwise
    */
    public function Process()
    {
        // If no data has been received, then there's nothing to do.
        if (empty($this->ipn_data))
            return false;

        // Add the item to the array for the order creation.
        // IPN item numbers are indexes into the cart, so get the
        // actual product ID from the cart
        foreach ($this->Cart as $idx=>$item) {
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

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(
                IPN_FAILURE_VERIFY,
                "($logId) Verification failed"
            );
            return false;
        } else {
            $logId = $this->Log(true);
        }
        return $this->handlePurchase();
    }   // function Process

}

?>
