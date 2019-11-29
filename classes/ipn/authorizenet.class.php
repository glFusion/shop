<?php
/**
 * This file contains the IPN processor for Authorize.Net.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2013-2019 Lee Garner
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\ipn;

use \Shop\Cart;

/**
 * Authorize.Net IPN Processor.
 * @package shop
 */
class authorizenet extends \Shop\IPN
{
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
        parent::__construct($A);

        // Get the needed values from the Webhook payload
        $payload = SHOP_getVar($A, 'payload', 'array');
        $this
            ->setTxnId(SHOP_getVar($payload, 'id'))
            ->setPmtGross(SHOP_getVar($payload, 'authAmount', 'float'))
            ->setOrderId(SHOP_getVar($payload, 'invoiceNumber'));
        $this->gw_name = $this->GW->getDscp();

        switch(SHOP_getVar($A, 'eventType')) {
        case 'net.authorize.payment.authcapture.created':
            $this->setStatus(self::PAID);
            break;
        default:
            $this->setStatus(self::PENDING);
            break;
        }
        $this->ipn_data['status'] = $this->getStatus();
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
            !$this->Verify() ||
            self::PAID != $this->getStatus() ||
            !$this->isUniqueTxnId()
        ) {
            SHOP_log(
                "Process Failed: status = " . $this->getStatus() .
                ', not unique txn_id or verification failed'
            );
            return false;
        }

        // Log the IPN.  Verified is 'true' if we got this far.
        $LogID = $this->Log(true);

        SHOP_log("Received $item_gross gross payment", SHOP_LOG_DEBUG);
        return $this->handlePurchase();
    }


    /**
     * Verify the transaction with Authorize.Net
     * Validate transaction by posting data back to the webserver.
     * Checks that a valid response is received and that key fields match the
     * IPN message.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    private function Verify()
    {
        /*if (isset($this->ipn_data['shop_test_ipn'])) {
            // Use the order ID provided in the constructor to get the order
            $this->Order = $this->getOrder($this->getOrderId());
            SHOP_log("Testing IPN, automatically returning true");
            return true;
    }*/

        //if ($this->isEmpty('txn_id')) {
        if (empty($this->getTxnId())) {
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
                'transId' => $this->getTxnId(),
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
        if (SHOP_getVar($trans, 'transId') != $this->getTxnId()) {
            SHOP_log("Transaction ID mismatch during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }
        if (SHOP_getVar($trans, 'responseCode', 'integer') != 1) {
            SHOP_log("Transaction response code not found during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }
        if (SHOP_getVar($trans, 'settleAmount', 'float') != $this->getPmtGross()) {
            SHOP_log("Settlement amount not found during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }

        $order = SHOP_getVar($trans, 'order', 'array');
        if (empty($order)) {
            SHOP_log("Order ID not found during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }
        $this->setOrderId(SHOP_getVar($order, 'invoiceNumber'));
        $this->Order = $this->getOrder($this->getOrderId());
        if (!$this->Order) {
            SHOP_log("Order ID $order invalid during authorize.net verification.", SHOP_LOG_ERROR);
            return false;
        }

        // Get the custom data from the order since authorize.net doesn't
        // support pass-through user variables
        $this->custom = $this->Order->getInfo();
        $this->custom['uid'] = $this->Order->uid;
        $this->ipn_data['custom'] = $this->custom;

        // Hack to get the gift card amount into the right variable name
        /*$by_gc = SHOP_getVar($this->custom, 'apply_gc', 'float');
        if ($by_gc > 0) {
            $this->custom['by_gc'] = $by_gc;
            $this->addCredit('gc', $by_gc);
        }*/
        $shipping = SHOP_getVar($trans, 'shipping', 'array');
        $this->setPmtShipping(SHOP_getVar($shipping, 'amount', 'float'));
        $tax = SHOP_getVar($trans, 'tax', 'array');
        $this->setPmtTax(SHOP_getVar($tax, 'amount', 'float'));

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
            ->setTxnId('40018916851')
            ->setPmtGross(23.77);
        return $this->Verify();
    }

}   // class AuthorizeNetIPN

?>
