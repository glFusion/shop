<?php
/**
 * Webhook handler for the Coupon gateway.
 * This gateway handles orders paid in full by gift card.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner
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
use Shop\Products\Coupon;
use Shop\Log;


/**
 * Internal Webhook Processor.
 * @package shop
 */
class Webhook extends \Shop\Webhook
{
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
        $this->blob = json_encode($_POST);
        $this->setData(json_decode($this->blob));   // converts to stdClass object
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
    public function Verify() : bool
    {
        $this->setEvent('gc_payment');
        $this->setOrderID($this->whData->order_id);
        $this->setID($this->whData->txn_id);
        $retval = true;

        if (!$this->isUniqueTxnId()) {
            // Duplicate transaction, not an error.
            return true;
        }

        // Get the Shop order record and make sure it's valid.
        $this->Order = Order::getInstance($this->getOrderId());
        if ($this->Order->isNew()) {
            Log::write('shop_system', Log::ERROR, "Order {$this->getOrderId()} not found");
            $retval = false;
        }

        // Verify that the user has a sufficient coupon balance,
        $bal_due = $this->Order->getBalanceDue();
        if (!Coupon::verifyBalance($bal_due, $this->Order->getUid())) {
            Log::write('shop_system', Log::ERROR, "Insufficient coupon balance for order {$this->getOrderId()}");
            $retval = false;
        }

        if ($retval) {
            $this->setVerified(true);
        } else {
            echo COM_refresh(Config::get('url'));
        }
        return $retval;
    }


    /**
     * Process the transaction.
     * Verifies that the transaction is valid, then records the purchase and
     * notifies the buyer and administrator
     *
     * @uses    self::Verify()
     */
    public function Dispatch() : bool
    {
        global $LANG_SHOP;

        // Log the message here to be sure it's logged.
        $LogID = $this->logIPN();

        switch ($this->getEvent()) {
        case 'gc_payment':
            $status = false;
            // Set the amount paid by coupon and verify that the entire order
            // can be paid by coupon (no excluded items).
            $bal_due = $this->Order->getBalanceDue();
            $this->Order->setGC($bal_due);
            if ($this->Order->getGC() < $bal_due) {
                Log::write('shop_system', Log::ERROR, "Error, order {$this->getOrderId()} cannot be fully paid by coupon.");
                $this->Order->setGC(0);
                return false;
            }
            $status = Coupon::Apply($bal_due, $this->Order->getUid(), $this->Order);
            if ($status !== false) {
                $Pmt = Payment::getByReference($this->getID());
                if ($Pmt->getPmtID() == 0) {
                    $Pmt->setRefID($this->getID())
                        ->setAmount($bal_due)
                        ->setGateway($this->getSource())
                        ->setMethod($this->GW->getDscp())
                        ->setComment('Webhook ' . $this->getID())
                        ->setComplete(1)
                        ->setStatus('paid')
                        ->setOrderID($this->getOrderID());
                    $retval = $Pmt->Save();
                }
            } else {
                return false;
            }

            $status = $this->handlePurchase();
            if ($status) {
                $this->Order->updatePmtStatus()
                    ->Log(
                        sprintf($LANG_SHOP['amt_paid_gw'],
                            $bal_due,
                            $this->GW->getDscp()
                        )
                );
                SHOP_setMsg($LANG_SHOP['thanks_title']);
            } else {
                SHOP_setMsg($LANG_SHOP['pmt_error']);
                return false;
            }
            echo COM_refresh(SHOP_URL . '/index.php');
            break;
        }
        return true;
    }


    /**
     * Redirect or display output upon completion.
     * This webhook is called directly by the buyer, so redirect back to
     * the Shop homepage.
     */
    public function redirectAfterCompletion() : void
    {
        echo COM_refresh(SHOP_URL . '/index.php');
    }

}
