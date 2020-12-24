<?php
/**
 * This file contains the IPN processor for Coingate.
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
namespace Shop\ipn;
use Shop\Cart;
use Shop\Config;
use Shop\Models\OrderState;


/**
 * Coingate IPN Processor.
 * @package shop
 */
class coingate extends \Shop\IPN
{
    /**
     * Constructor.
     * Most of the variables for this IPN come from the transaction,
     * which is retrieved in Verify().
     *
     * @param   array   $A  Array of IPN data
     */
    public function __construct($A=array())
    {
        $this->gw_id = 'coingate';
        $A = $_POST;
        parent::__construct($A);

        $this->setTxnId(SHOP_getVar($A, 'id'));
        $this->setOrderId(SHOP_getVar($A, 'order_id'));
        $this->setGwName($this->gw_id);
        $this->setStatus(SHOP_getVar($this->ipn_data, 'status'));
    }


    /**
     * Process the transaction.
     * Verifies that the transaction is valid, then records the purchase and
     * notifies the buyer and administrator
     *
     * @uses    self::Verify()
     * @uses    BaseIPN::isUniqueTxnId()
     * @uses    BaseIPN::handlePurchase()
     */
    public function Process()
    {
        if (!$this->Verify()) {
            return false;
        }

        $LogID = $this->Log(true);
        if ($this->getStatus() == 'paid') {
            // If paid, set the payment amount and handle the purchase
            SHOP_log("Received {$this->getPmtGross()} gross payment", SHOP_LOG_DEBUG);
            return $this->handlePurchase();
        } else {
            // Treat non-paid status as valid message since it passed Verify()
            // but do nothing with it.
            return true;
        }
    }


    /**
     * Verify that the message is valid and can be processed.
     * Checks key elements of the order and its status.
     *
     * @return  boolean     True if valid, False if not
     */
    public function Verify()
    {
        $this->setEvent(SHOP_getVar($this->ipn_data, 'status'));
        if (!$this->isUniqueTxnId()) {
            return false;
        }
        $this->Order = $this->getOrder($this->getOrderId());
        $token = SHOP_getVar($this->ipn_data, 'token');
        if ($token != $this->Order->getToken()) {
            echo $token . "<br />\n";
            echo $this->Order->getToken();die;
            return false;
        }
        $gworder = $this->GW->findOrder($this->getTxnId());
        if (!$gworder || $gworder->status != $this->getStatus()) {
            return false;
        }
        switch ($this->getEvent()) {
        case 'pending':
            $this->Order->setStatus(OrderState::PENDING)->Save(false);
            break;
        case 'paid':
            $this->setPmtGross(SHOP_getVar($this->ipn_data, 'price_amount', 'float'));
            if ($this->Order->getBalanceDue() <= $this->getPmtGross()) {
                $this->Order->setStatus(OrderState::PROCESSING)->Save(false);
            }
            break;
        case 'invalid':
        case 'expired':
        case 'canceled':
            // Order was marked invalid by the buyer or expired.
            // Set the status and return "false" to prevent further processing.
            echo "Here";die;
            $this->Order->setStatus(OrderState::CANCELED)->Save(false);
            break;
        default:
            return false;
            break;
        }
        return true;
    }

}
