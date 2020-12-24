<?php
/**
 * This file contains the IPN processor for Paylike.
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
use Shop\Models\CustomInfo;


/**
 * Paylike IPN Processor.
 * @package shop
 */
class paylike extends \Shop\IPN
{
    /** Transaction data holder.
     * @var object */
    private $txn = NULL;
i

    /**
     * Constructor.
     * Most of the variables for this IPN come from the transaction,
     * which is retrieved in Verify().
     *
     * @param   array   $A  Array of IPN data
     */
    function __construct($A=array())
    {
        $this->gw_id = 'paylike';
        $A = $_GET;
        parent::__construct($A);

        $this->setTxnId(SHOP_getVar($A, 'txn_id'));
        $this->setOrderId(SHOP_getVar($A, 'order_id'));
        $this->gw_name = $this->GW->getDscp();
        $this->ipn_data['status'] = 'authorized';
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
        if (
            $this->Verify() ||
            $this->isUniqueTxnId()
        ) {
            $this->Capture();

            // Log the IPN.  Verified is 'true' if we got this far.
            $LogID = $this->Log(true);

            SHOP_log("Received {$this->getPmtGross()} gross payment", SHOP_LOG_DEBUG);
            if ($this->handlePurchase()) {
                COM_refresh(Config::get('url') . '?thanks=' . $this->gw_name);
            }
        } else {
                SHOP_log(
                    "Process Failed: status = " . $this->getStatus() .
                    ', not unique txn_id or verification failed'
                );
                COM_setMsg('There was an error processing your order');
                COM_refresh(Config::get('url'));
        }
    }


    /**
     * Verify the transaction with Authorize.Net
     * Validate transaction by posting data back to the webserver.
     * Checks that a valid response is received and that key fields match the
     * IPN message.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    public function Verify()
    {
        if (isset($this->ipn_data['shop_test_ipn'])) {
            // Use the order ID provided in the constructor to get the order
            $this->Order = $this->getOrder($this->getOrderId());
            SHOP_log("Testing IPN, automatically returning true");
            return true;
        }

        if (empty($this->getTxnId())) {
            SHOP_log("{$this->gw_name} IPN: txn_id is empty", SHOP_LOG_ERROR);
            return false;
        }
        try {
            $this->txn = $this->GW->getTransaction($this->getTxnId());
        } catch (Exception $e) {
            // catch errors thrown by Paylike API for invalid requests
            SHOP_log("{$this->gw_name} IPN transaction ID {$this->getTxnId()} is invalid", SHOP_LOG_ERROR);
            return false;
        }

        $custom = SHOP_getVar($this->txn, 'custom', 'array');
        $order_id = SHOP_getVar($custom, 'order_id');
        if (empty($order_id)) {
            SHOP_log("Order ID not found during {$this->gw_name} verification.", SHOP_LOG_ERROR);
            return false;
        }
        $this->setOrderId($order_id);
        $this->Order = $this->getOrder($this->getOrderId());
        if (!$this->Order) {
            SHOP_log("Order ID $order invalid during {$this->gw_name} verification.", SHOP_LOG_ERROR);
            return false;
        }

        // Payment amount in the transaction is integer
        $this->setPmtGross(SHOP_getVar($this->txn, 'amount', 'float') / 100);
        if ($this->Order->getTotal() > $this->getPmtGross()) {
            SHOP_log("{$this->gw_name} txn {$this->txn_id} amt {$this->getPmtGross()} is insufficient for order {$this->Order->getOrderId()}", SHOP_LOG_ERROR);
            return false;
        }

        // Get the custom data from the order.
        $this->custom = new CustomInfo($this->Order->getInfo());
        $this->custom['uid'] = $this->Order->uid;
        $this->ipn_data['custom'] = $this->custom->encode();

        // All conditions met
        return true;
    }


    private function Capture()
    {
        // amounts in the txn array are integer (amunt * 100)
        $amt_pending = SHOP_getVar($this->txn, 'pendingAmount', 'integer');
        $curr_code = SHOP_getVar($this->txn, 'currency');

        try {
            $status = $this->GW->captureTransaction($this->getTxnId(), array(
                'amount'   => $amt_pending,
                'currency' => $curr_code,
            ) );
        } catch (Exception $e) {
            // catch errors thrown by Paylike API for invalid requests
            $status = false;
            SHOP_log("{$this->gw_name} Capture transaction ID {$this->getTxnId()} failed", SHOP_LOG_ERROR);
        }
        return $status;
    }


    /**
     * Test function that can be run from a script.
     * Adjust params as needed to test the Verify() function
     *
     * @return  boolean     Results from Verify()
     */
    public function testVerify()
    {
        $this->setOrderId('20180925224531700')
            ->setTxnId('40018916851')
            ->setPmtGross(23.77);
        return $this->Verify();
    }

}
