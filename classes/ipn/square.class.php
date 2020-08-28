<?php
/**
 * This file contains the Square IPN class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\ipn;
use Shop\Cart;
use Shop\Models\OrderState;


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
        if (!$this->Order || $this->Order->isNew()) return NULL;

        $this->setOrderId($this->Order->getOrderID())
            ->setTxnId(SHOP_getVar($A, 'transactionId'))
            ->setEmail($this->Order->getBuyerEmail());
            //->setPayerName($_USER['fullname']);
            //->setStatus($status);
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

        $total_shipping = 0;
        $total_handling = 0;
        foreach ($this->Order->getItems() as $idx=>$item) {
            $args = array(
                'item_id'   => $item->getProductID(),
                'quantity'  => $item->getQuantity(),
                'price'     => $item->getNetPrice(),
                'item_name' => $item->getDscp(),
                'shipping'  => $item->getShipping(),
                'handling'  => $item->getHandling(),
                'extras'    => $item->getExtras(),
            );
            $this->addItem($args);
            $total_shipping += $item->getShipping();
            $total_handling += $item->getHandling();
        }
        $this->setPmtShipping($total_shipping)
            ->setPmtHandling($total_handling);
    }


    /**
     * Verify the transaction.
     * Checks that a valid, unique transaction was received and checks the
     * payment values.
     *
     * @return  boolean     True if successfully validated, false otherwise
     */
    private function Verify()
    {
        // Even test transactions have to be unique
        if (!$this->isUniqueTxnId()) {
            return false;
        }

        // Retrieve the transaction from Square.
        $trans = $this->GW->getTransaction($this->getTxnId());
        //SHOP_log(var_export($trans,true), SHOP_LOG_DEBUG);
        $this->status = 'pending';
        if ($trans) {
            // Get values from the returned array
            // Must have orders=>0=>(tenders,reference_id)
            $order = $trans->getOrders()[0];
            /*$trans = SHOP_getVar($trans->getOrders()[0], 'orders', 'array');
            if (empty($trans) || !isset($trans[0])) {
                return false;
            }
            $order = $trans[0];*/
            $tenders = $order->getTenders();
            if (empty($tenders)) return false;
            $order_id = $order->getReferenceId();
            if (empty($order_id)) return false;

            $this->setStatus(OrderState::PAID);
            $total_paid = 0;
            $pmt_gross = 0;
            foreach ($tenders as $tender) {
                if ($tender->getCardDetails()->getStatus() == 'CAPTURED') {
                    $C = \Shop\Currency::getInstance($tender->getAmountMoney()->getCurrency());
                    // Set $pmt_gross each time to capture the last amount paid.
                    $pmt_gross = $C->fromInt($tender->getAmountMoney()->getAmount());
                    $total_paid += $pmt_gross;
                } else {
                    $this->setStatus(OrderState::PENDING);
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
        $total_shipping = 0;
        $total_handling = 0;
        foreach ($this->Order->getItems() as $idx=>$item) {
            $args = array(
                'item_id'   => $item->getProductID(),
                'quantity'  => $item->getQuantity(),
                'price'     => $item->getPrice(),
                'item_name' => $item->getDscp(),
                'shipping'  => $item->getShipping(),
                'handling'  => $item->getHandling(),
                'extras'    => $item->getExtras(),
            );
            $this->addItem($args);
            $total_shipping += $item->getShipping();
            $total_handling += $item->getHandling();
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
