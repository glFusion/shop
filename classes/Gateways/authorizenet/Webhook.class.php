<?php
/**
 * This file contains the IPN processor for Authorize.Net.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2013-2020 Lee Garner
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\authorizenet;
use Shop\Order;
use Shop\Gateway;
use Shop\Payment;
use Shop\Models\OrderState;


/**
 * Authorize.Net IPN Processor.
 * @package shop
 */
class Webhook extends \Shop\Webhook
{
    private $status = self::EV_UNDEFINED;

    /**
     * Constructor.
     * Most of the variables for this IPN come from the transaction,
     * which is retrieved in Verify().
     *
     * @param   array   $A  Array of IPN data
     */
    function __construct($A=array())
    {
        $this->gw_id = 'authorizenet';
        $this->GW = Gateway::getInstance($this->gw_id);

        if (empty($_POST)) {
            // Received a webhook, a single parameter string
            $json = file_get_contents('php://input');
            SHOP_log("WEBHOOK: $json", SHOP_LOG_DEBUG);
            $A = json_decode($json);
        } elseif (isset($_POST['shop_test_ipn'])) {
            // Received POST, testing only. Must already be complete
            $A  = json_decode(json_encode($_POST)); // convert to object
            SHOP_log("TEST: " . var_export($_POST,true), SHOP_LOG_DEBUG);
        } else {
            // Received a Silent URL POST. Convert to webhook format.
            SHOP_log("Silent URL: " . var_export($_POST,true), SHOP_LOG_DEBUG);
            switch (SHOP_getVar($_POST, 'x_type')) {
            case 'auth_capture':
                $eventtype = 'net.authorize.payment.authcapture.created';
                break;
            default:
                $eventtype = 'undefined';
                break;
            }
            $A = array(
                'notificationId'    => 'unused',
                'eventtype'     => $eventtype,
                'eventDate'     => date('Y-m-d\TH:i:s\Z'),
                'webhookId'     => 'unused',
                'payload'       => array(
                    'responseCode'  => SHOP_getVar($_POST, 'x_response_code', 'integer'),
                    'authCode'  => SHOP_getVar($_POST, 'x_auth_code'),
                    'avsResponse'   => SHOP_getVar($_POST, 'x_avs_code'),
                    'authAmount'    => SHOP_getVar($_POST, 'x_amount', 'float'),
                    'entityName'    => 'transaction',
                    'id'        => SHOP_getVar($_POST, 'x_trans_id'),
                ),
            );
    
            if (
                //OrderState::PAID != $this->getStatus() ||
                !$this->isUniqueTxnId()
            ) {
                SHOP_log(
                    "Process Failed: status = " . $this->getStatus() .
                    ', not unique txn_id or verification failed'
                );
                return false;
            }
            $A = json_decode(json_encode($A));  // convert to object
        }
        $this->setData($A);

        // Get the needed values from the Webhook payload
        $payload = $A->payload;
        $this
            ->setID($payload->id)
            ->setEvent($A->eventType)
            ->setSource($this->GW->getName())
            ->setPayment($payload->authAmount)
            ->setOrderId($payload->invoiceNumber);

        /*switch($A->eventType) {
        case 'net.authorize.payment.authcapture.created':
            $this->setEvent($A->eventType);
            break;
        default:
            break;
        }*/
    }


    /**
     * Process the transaction.
     * Verifies that the transaction is valid, then records the purchase and
     * notifies the buyer and administrator
     *
     * @uses    self::Verify()
     * @uses    Webhook::handlePurchase()
     */
    public function Dispatch()
    {
        $retval = false;

        // Log the IPN.  Verified is 'true' if we got this far.
        $LogID = $this->logIPN();

        switch ($this->getEvent()) {
        case 'net.authorize.payment.authcapture.created':
            $this->status = self::EV_PAYMENT;
            SHOP_log("Received {$this->getPayment()} gross payment", SHOP_LOG_DEBUG);
            $Pmt = Payment::getByReference($this->getID());
            if ($Pmt->getPmtID() == 0) {
                $Pmt->setRefID($this->getID())
                    ->setAmount($this->getPayment())
                    ->setGateway($this->getSource())
                    ->setMethod($this->GW->getDscp())
                    ->setComment('Webhook ' . $this->getData()->notificationId)
                    ->setComplete(1)
                    ->setUid($this->Order->getUid())
                    ->setStatus($this->status)
                    ->setOrderID($this->getOrderID());
                if ($Pmt->Save()) {
                    $retval = $this->handlePurchase();
                }
            }
            break;
        }
        return $retval;
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
        if (isset($this->getData()->shop_test_ipn)) {
            // Use the order ID provided in the constructor to get the order
            $this->Order = Order::getInstance($this->getOrderId());
            SHOP_log("Testing IPN, automatically returning true");
            return true;
        }

        if (empty($this->getID())) {
            SHOP_log("Authorize.net IPN: txn_id is empty", SHOP_LOG_ERROR);
            return false;
        }
        $json = array(
            'getTransactionDetailsRequest' => array(
                'merchantAuthentication' => array(
                    'name' => $this->GW->getApiLogin(),
                    'transactionKey' => $this->GW->getTransKey(),
                ),
                //'refId' => $this->order_id,
                'transId' => $this->getID(),
            ),
        );
        $jsonEncoded = json_encode($json);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://apitest.authorize.net/xml/v1/request.api');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonEncoded);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            SHOP_log("Error received during authorize.net verification: code $code", SHOP_LOG_ERROR);
            return false;
        }
        $bom = pack('H*','EFBBBF');
        $result = preg_replace("/^$bom/", '', $result);
        $json = json_decode($result, true);
        if (!$json) {
            SHOP_log("Error decoding authorize.net verification JSON: $result", SHOP_LOG_ERROR);
            return false;
        }

        // Check return fields against known values
        $trans = SHOP_getVar($json, 'transaction', 'array', NULL);
        if (!$trans) {
            SHOP_log("Transaction not found during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }
        if (SHOP_getVar($trans, 'transId') != $this->getID()) {
            SHOP_log("Transaction ID mismatch during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }
        if (SHOP_getVar($trans, 'responseCode', 'integer') != 1) {
            SHOP_log("Transaction response code not found during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }
        if (SHOP_getVar($trans, 'settleAmount', 'float') != $this->getPayment()) {
            SHOP_log("Settlement amount not found during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }

        $order = SHOP_getVar($trans, 'order', 'array');
        if (empty($order)) {
            SHOP_log("Order ID not found during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }
        $this->setOrderId(SHOP_getVar($order, 'invoiceNumber'));
        $this->Order = Order::getInstance($this->getOrderId());
        if (!$this->Order) {
            SHOP_log("Order ID $order invalid during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }

        // All conditions met
        return true;
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
            ->setID('40018916851')
            ->setPayment(23.77);
        return $this->Verify();
    }

}
