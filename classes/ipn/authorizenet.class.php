<?php
/**
 * This file contains the IPN processor for Authorize.Net.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2013-2019 Lee Garner
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\ipn;

use \Shop\Cart;

/**
 * Authorize.Net IPN Processor.
 * @since   v0.5.3
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
        $payload = SHOP_getVar($A, 'payload', 'array', array());
        $this->txn_id = SHOP_getVar($payload, 'id');
        $this->pmt_date = strftime('%d %b %Y %H:%M:%S', time());
        $this->pmt_gross = SHOP_getVar($payload, 'authAmount', 'float');
        $this->gw_name = $this->gw->Description();

        switch(SHOP_getVar($A, 'eventType')) {
        case 'net.authorize.payment.authcapture.created':
            $this->status = 'paid';
            break;
        default:
            $this->status = 'pending';
            break;
        }
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
            'paid' != $this->status ||
            !$this->isUniqueTxnId()
        ) {
            return false;
        }

        // Log the IPN.  Verified is 'true' if we got this far.
        $LogID = $this->Log(true);

        SHOP_debug("Received $item_gross gross payment", 'debug_ipn');
        if ($this->isSufficientFunds()) {
            $this->handlePurchase();
            return true;
        } else {
            return false;
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
    private function Verify()
    {
        //return true;

        if ($this->isEmpty('txn_id')) {
            return false;
        }
        $json = array(
            'getTransactionDetailsRequest' => array(
                'merchantAuthentication' => array(
                    'name' => $this->gw->getApiLogin(),
                    'transactionKey' => $this->gw->getTransKey(),
                ),
                //'refId' => $this->order_id,
                'transId' => $this->txn_id,
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
            return false;
        }
        $bom = pack('H*','EFBBBF');
        $result = preg_replace("/^$bom/", '', $result);
        $result = json_decode($result, true);

        // Check return fields against known values
        $trans = SHOP_getVar($result, 'transaction', 'array', NULL);
        if (!$trans) return false;

        if (SHOP_getVar($trans, 'transId') != $this->txn_id) {
            return false;
        }
        if (SHOP_getVar($trans, 'responseCode', 'integer') != 1) {
            return false;
        }
        if (SHOP_getVar($trans, 'settleAmount', 'float') != $this->pmt_gross) {
            return false;
        }

        $order = SHOP_getVar($trans, 'order', 'array');
        if (empty($order)) return false;
        $this->order_id = SHOP_getVar($order, 'invoiceNumber');
        $this->Order = $this->getOrder($this->order_id);

        // Get the custom data from the order since authorize.net doesn't
        // support pass-through user variables
        $this->custom = $this->Order->getInfo();
        $this->custom['uid'] = $this->Order->uid;

        // Hack to get the gift card amount into the right variable name
        /*$by_gc = SHOP_getVar($this->custom, 'apply_gc', 'float');
        if ($by_gc > 0) {
            $this->custom['by_gc'] = $by_gc;
            $this->addCredit('gc', $by_gc);
        }*/
        $shipping = SHOP_getVar($trans, 'shipping', 'array');
        $this->pmt_shipping = SHOP_getVar($shipping, 'amount', 'float');
        $tax = SHOP_getVar($trans, 'tax', 'array');
        $this->pmt_tax = SHOP_getVar($tax, 'amount', 'float');

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
        $this->order_id = '20180925224531700';
        $this->txn_id = '40018916851';
        $this->pmt_gross = 23.77;
        return $this->Verify();
    }

}   // class AuthorizeNetIPN

?>
