<?php
/**
 * Webhook handler for the Internal gateway.
 * This gateway handles test and internal orders that do not use an actual
 * payment gateway.
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
namespace Shop\Gateways\_internal;
use Shop\Config;
use Shop\Order;
use Shop\Payment;
use Shop\Models\OrderState;
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
        $this->setSource('_internal');

        // Load the payload into the blob property for later use in Verify().
        $this->blob = json_encode($_POST);
        $this->setData($_POST);
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
        $this->setEvent(SHOP_getVar($this->getData(), 'status'));
        $this->setOrderID(SHOP_getVar($this->getData(), 'order_id'));
        $this->setID(SHOP_getVar($this->getData(), 'txn_id'));

        if (!$this->isUniqueTxnId()) {
            Log::write('shop_system', Log::ERROR, "Duplicate transaction ID {$this->getID()}");
            return false;
        }

        // Log the message here to be sure it's logged.
        $LogID = $this->logIPN();

        // Get the Shop order record and make sure it's valid.
        $this->Order = Order::getInstance($this->getOrderId());
        if ($this->Order->isNew()) {
            Log::write('shop_system', Log::ERROR, "Order {$this->getOrderId()} not found");
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
     */
    public function Dispatch()
    {
        global $LANG_SHOP;

        switch ($this->getEvent()) {
        case 'paid':
            $status = false;
            $this->setPayment(SHOP_getVar($this->getData(), 'pmt_gross', 'float'));
            if ($this->isSufficientFunds()) {
                $Pmt = Payment::getByReference($this->getID());
                if ($Pmt->getPmtID() == 0) {
                    $Pmt->setRefID($this->getID())
                        ->setAmount($this->getPayment())
                        ->setGateway($this->getSource())
                        ->setMethod($this->getSource())
                        ->setComment('Webhook ' . $this->getID())
                        ->setStatus($this->getEvent())
                        ->setOrderID($this->getOrderID())
                        ->Save();
                    $status = $this->handlePurchase();
                }
            }
            if ($status) {
                SHOP_setMsg($LANG_SHOP['thanks_title']);
            } else {
                SHOP_setMsg($LANG_SHOP['pmt_error']);
            }
            break;
        }
        return true;
    }


    /**
     * Redirect or display output upon completion.
     * This webhook is called directly by the buyer, so redirect back to
     * the Shop homepage.
     */
    public function redirectAfterCompletion()
    {
        COM_refresh(SHOP_URL . '/index.php');
    }

}
