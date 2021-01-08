<?php
/**
 * Webhook handler for the Coupon gateway.
 * This gateway handles orders paid in full by gift card.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\_coupon;
use Shop\Config;
use Shop\Order;
use Shop\Payment;
use Shop\Models\OrderState;
use Shop\Products\Coupon;


/**
 * Internal Webhook Processor.
 * @package shop
 */
class Webhook extends \Shop\Webhook
{
    private $blob = '';

    /**
     * Constructor.
     * Most of the variables for this IPN come from the transaction,
     * which is retrieved in Verify().
     *
     * @param   array   $A  Array of IPN data
     */
    public function __construct($blob='')
    {
        $this->setSource('_coupon');

        // Load the payload into the blob property for later use in Verify().
        $this->blob = $_POST;
        $this->setHeaders(NULL);
        $this->setTimestamp();
        $this->GW = \Shop\Gateway::getInstance($this->getSource());
    }


    /**
     * Verify that the message is valid and can be processed.
     * Checks key elements of the order and its status.
     *
     * @return  boolean     True if valid, False if not
     */
    public function Verify()
    {
        $this->setEvent('gc_payment');
        $this->setOrderID(SHOP_getVar($this->blob, 'order_id'));
        $this->setID(SHOP_getVar($this->blob, 'txn_id'));

        if (!$this->isUniqueTxnId()) {
            SHOP_log("Duplicate transaction ID {$this->getID()}");
            return false;
        }

        // Log the message here to be sure it's logged.
        $LogID = $this->logIPN();

        // Get the Shop order record and make sure it's valid.
        $this->Order = Order::getInstance($this->getOrderId());
        if ($this->Order->isNew()) {
            SHOP_log("Order {$this->getOrderId()} not found");
            return false;
        }

        // Verify that the user has a sufficient coupon balance,
        $bal_due = $this->Order->getBalanceDue();
        if (!Coupon::verifyBalance($bal_due, $this->Order->getUid())) {
            SHOP_log("Insufficient coupon balance for order {$this->getOrderId()}");
            return false;
        }

        return true;
    }


    /**
     * Process the transaction.
     * Verifies that the transaction is valid, then records the purchase and
     * notifies the buyer and administrator
     *
     * @uses    self::Verify()
     * @uses    BaseIPN::handlePurchase()
     */
    public function Dispatch()
    {
        global $LANG_SHOP;

        switch ($this->getEvent()) {
        case 'gc_payment':
            $status = false;
            // Set the amount paid by coupon and verify that the entire order
            // can be paid by coupon (no excluded items).
            $bal_due = $this->Order->getBalanceDue();
            $this->Order->setGC($bal_due);
            if ($this->Order->getGC() < $bal_due) {
                SHOP_log("Error, order {$this->getOrderId()} cannot be fully paid by coupon.");
                $this->Order->setGC(0);
                return false;
            }

            $status = $this->handlePurchase($this->Order);
            if ($status) {
                $this->Order->updatePmtStatus()
                    ->Log(
                        sprintf($LANG_SHOP['amt_paid_gw'],
                            $bal_due,
                            $this->getGateway()
                        )
                );
                COM_setMsg($LANG_SHOP['thanks_title']);
            } else {
                COM_setMsg($LANG_SHOP['pmt_error']);
            }
            COM_refresh(SHOP_URL . '/index.php');
            break;
        }
        return true;
    }

}
