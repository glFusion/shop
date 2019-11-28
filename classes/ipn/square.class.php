<?php
/**
 * This file contains the Square IPN class.
 * It is used with orders that have zero balances and thus don't go through
 * an actual payment processor.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
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

/**
 *  Class to provide IPN for internal-only transactions,
 *  such as zero-balance orders.
 *
 *  @package shop
 */
class square extends \Shop\IPN
{
    /**
     * Constructor.
     *
     * @param   array   $A      $_POST'd variables from Shop
     */
    function __construct($A=array())
    {
        global $_USER, $_CONF;

        $this->gw_id = 'square';
        parent::__construct($A);

        $order_id = SHOP_getVar($A, 'referenceId');

        if (!empty($order_id)) {
            $this->Order = $this->getOrder($order_id);
        }
        if (!$this->Order || $this->Order->isNew) return NULL;

        $this->setOrderId($this->Order->order_id)
            ->setTxnId(SHOP_getVar($A, 'transactionId'))
            ->setEmail($this->Order->buyer_email)
            ->setPayerName($_USER['fullname'])
            ->setStatus($status);
        $this->gw_name = $this->GW->getName();;

        $billto = $this->Order->getAddress('billto');
        $shipto = $this->Order->getAddress('shipto');
        if (empty($shipto) && !empty($billto)) $shipto = $billto;

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
        /*if (isset($A['custom'])) {
            $this->custom = @unserialize(str_replace('\'', '"', $A['custom']));
            if (!$this->custom) {
                $this->custom = array('uid' => $A['custom']);
            }
        }*/
        $this->custom = array(
            'transtype' => $this->GW->getName(),
            'uid'       => $this->Order->uid,
            'by_gc'     => $this->Order->getInfo()['apply_gc'],
        );

        foreach ($this->Order->getItems() as $idx=>$item) {
            $args = array(
                'item_id'   => $item->product_id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'item_name' => $item->getShortDscp(),
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
    }


    /**
     * Verify the transaction.
     * This just checks that a valid cart_id was received along with other
     * variables.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    private function Verify()
    {
        // Gets the transaction via the Square API to get the real values.
        SHOP_log("transaction ID: " . $this->getTxnId());
        $trans = $this->GW->getTransaction($this->getTxnId());

        SHOP_log(var_export($trans,true), SHOP_LOG_DEBUG);
        $this->status = 'pending';
        if ($trans) {
            // Get through the top-level array var
            $trans= SHOP_getVar($trans, 'transaction', 'array');
            if (empty($trans)) return false;

            $tenders = SHOP_getVar($trans, 'tenders', 'array');
            if (empty($tenders)) return false;

            $order_id = SHOP_getVar($trans, 'reference_id');
            if (empty($order_id)) return false;

            $this->setStatus(self::PAID);
            $pmt_gross = 0;
            foreach ($tenders as $tender) {
                if ($tender['card_details']['status'] == 'CAPTURED') {
                    $C = \Shop\Currency::getInstance($tender['amount_money']['currency']);
                    $pmt_gross += $C->fromInt($tender['amount_money']['amount']);
                    //$pmt_fee += $C->fromInt($tender['processing_fee_money']['amount']);
                } else {
                    $this->setStatus(self::PENDING);
                }
            }
            $this->setPmtGross($pmt_gross);
        }
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
     * Process an incoming IPN transaction.
     * Do the following:
     *  - Verify IPN
     *  - Log IPN
     *  - Check that transaction is complete
     *  - Check that transaction is unique
     *  - Check for valid receiver email address
     *  - Process IPN
     *
     * @uses   IPN::addItem()
     * @uses   IPN::handleFailure()
     * @uses   IPN::handlePurchase()
     * @uses   IPN::isUniqueTxnId()
     * @uses   IPN::isSufficientFunds()
     * @uses   IPN::Log()
     * @uses   Verify()
     * @param  array   $in     POST variables of transaction
     * @return boolean true if processing valid and completed, false otherwise
     */
    public function Process()
    {
        // If no data has been received, then there's nothing to do.
        if (empty($this->ipn_data)) {
            return false;
        }
        // Backward compatibility, get custom data into IPN for plugin
        // products.
        $this->ipn_data['custom'] = $this->custom;

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
            $this->addItem($args);
            $total_shipping += $item->shipping;
            $total_handling += $item->handling;
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
        return $this->handlePurchase();
    }   // function Process

}

?>
