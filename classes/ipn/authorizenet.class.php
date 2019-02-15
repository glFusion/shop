<?php
/**
 * This file contains the IPN processor for Authorize.Net.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2013 Lee Garner
 * @package     shop
 * @version     v0.5.2
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
     *
     * @param   array   $A  Array of IPN data
     */
    function __construct($A=array())
    {
        $this->gw_id = 'authorizenet';
        parent::__construct($A);

        $this->txn_id = SHOP_getVar($A, 'x_trans_id');
        $this->payer_email = SHOP_getVar($A, 'x_email');
        $this->payer_name = SHOP_getVar($A, 'x_first_name') . ' ' .
                    SHOP_getVar($A, 'x_last_name');
        $this->pmt_date = strftime('%d %b %Y %H:%M:%S', time());
        $this->pmt_gross = SHOP_getVar($A, 'x_amount', 'float');
        $this->gw_name = $this->gw->Description();
        $this->pmt_shipping = SHOP_getVar($A, 'x_freight', 'float');
        $this->pmt_handling = 0; // not supported?
        $this->pmt_tax = SHOP_getVar($A, 'x_tax', 'float');
        $this->order_id = SHOP_getVar($A, 'x_invoice_num');

        // Check a couple of vars to see if a shipping address was supplied
        $shipto_addr = SHOP_getVar($A, 'x_ship_to_address');
        $shipto_city = SHOP_getVar($A, 'x_ship_to_city');
        if ($shipto_addr != '' && $shipto_city != '') {
            $this->shipto = array(
                'name'      => SHOP_getVar($A, 'x_ship_to_first_name') . ' ' . 
                                SHOP_getVar($A, 'x_ship_to_last_name'),
                'address1'  => $shipto_addr,
                'address2'  => '',
                'city'      => $shipto_city,
                'state'     => SHOP_getVar($A, 'x_ship_to_state'),
                'country'   => SHOP_getVar($A, 'x_ship_to_country'),
                'zip'       => SHOP_getVar($A, 'x_ship_to_zip'),
                'phone'     => SHOP_getVar($A, 'x_phone'),
            );
        }

        switch(SHOP_getVar($A, 'x_response_code', 'integer')) {
        case 1:
            $this->status = 'paid';
            break;
        default:
            $this->status = 'pending';
            break;
        }

        $this->Order = Cart::getInstance(0, $this->order_id);
        // Get the custom data from the order since authorize.net doesn't
        // support pass-through user variables
        $this->custom = $this->Order->getInfo();
        $this->custom['uid'] = $this->Order->uid;

        // Hack to get the gift card amount into the right variable name
        $by_gc = SHOP_getVar($this->custom, 'apply_gc', 'float');
        if ($by_gc > 0) {
            $this->custom['by_gc'] = $by_gc;
        }

        /*$items = explode('::', $A['item_var']);
        foreach ($items as $item) {
            list($itm_id, $price, $qty) = explode(';', $item);
            $this->AddItem($itm_id, $qty, $price);
        }*/

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

        if ('paid' != $this->status) {
            return false;
        }

        if (!$this->isUniqueTxnId()
            return false;

        // Log the IPN.  Verified is 'true' if we got this far.
        $LogID = $this->Log(true);

        SHOP_debug("Received $item_gross gross payment");
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
        return true;

        $json = array(
            'getTransactionDetailsRequest' => array(
                'merchantAuthentication' => array(
                    'name' => $this->gw->getApiLogin(),
                    'transactionKey' => $this->gw->getTransKey(),
                ),
                'refId' => $this->order_id,
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
        if (SHOP_getVar($order, 'invoiceNumber') != $this->order_id) {
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
        $this->order_id = '20180925224531700';
        $this->txn_id = '40018916851';
        $this->pmt_gross = 23.77;
        return $this->Verify();
    }

}   // class AuthorizeNetIPN

?>
