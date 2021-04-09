<?php
/**
 * Webhook handler for the Free gateway.
 * This gateway handles orders that have no amount due, such as free downloads.
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
namespace Shop\Gateways\free;
use Shop\Config;
use Shop\Order;
use Shop\Payment;


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
        $this->setSource('free');

        // Load the payload into the blob property for later use in Verify().
        $this->setData($_POST);
        $this->blob = json_encode($_POST);
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
        $this->setEvent('free_order');
        $this->setOrderID(SHOP_getVar($this->getData(), 'order_id'));
        $this->setID(SHOP_getVar($this->getData(), 'txn_id'));

        if (!$this->isUniqueTxnId()) {
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

        // Verify that this is a free order.
        if ($this->Order->getTotal() > .0001) {
            SHOP_log("Order {$this->getOrderId()} is not a free order");
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
        case 'free_order':
            $status = false;
            $status = $this->handlePurchase();
            if ($status) {
                $this->Order->updatePmtStatus()
                    ->Log(
                        sprintf($LANG_SHOP['amt_paid_gw'],
                            0,
                            $this->getSource()
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
