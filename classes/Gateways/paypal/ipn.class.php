<?php
/**
 * This file contains the Shop IPN class to deal with IPN transactions from Shop.
 * Based on the gl-paypal Plugin for Geeklog CMS by Vincent Furia.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\paypal;
use Shop\Cart;
use Shop\Address;
use Shop\Models\OrderStatus;
use Shop\Models\DataArray;
use Shop\Log;


// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Define failure reasons */
define('SHOP_FAILURE_UNKNOWN', 0);
define('SHOP_FAILURE_VERIFY', 1);
define('SHOP_FAILURE_COMPLETED', 2);
define('SHOP_FAILURE_UNIQUE', 3);
define('SHOP_FAILURE_EMAIL', 4);
define('SHOP_FAILURE_FUNDS', 5);

/**
 * Class to deal with IPN transactions from Shop.
 * @package shop
 */
class ipn extends \Shop\IPN
{
    /**
     * Constructor.  Set up variables received from Shop.
     *
     * @param   array   $A      $_POST'd variables from Shop
     */
    function __construct($A=array())
    {
        $this->gw_id = 'paypal';
        $A = new DataArray($_POST);
        parent::__construct($A);

        $this
            ->setTxnId($A->getString('txn_id'))
            ->setEmail($A->getString('payer_email'))
            ->setPayerName(trim($A->getString('first_name') . ' ' . $A->getString('last_name')))
            ->setPmtGross($A->getFloat('mc_gross'))
            ->setPmtTax($A->getFloat('tax'))
            ->setCurrency($A->getString('mc_currency', 'Unk'))
            ->addCredit('discount', $A->getFloat('discount'))
            ->setOrderId($A->getString('invoice'))
            ->setParentTxnId($A->getString('parent_txn_id'));

        // Check a couple of vars to see if a shipping address was supplied
        if (isset($A['address_street']) || isset($A['address_city'])) {
            $this->shipto = array(
                'name'      => $A->getString('address_name'),
                'address1'  => $A->getString('address_street'),
                'address2'  => '',
                'city'      => $A->getString('address_city'),
                'state'     => $A->getString('address_state'),
                'country'   => $A->getString('address_country'),
                'zip'       => $A->getString('address_zip'),
            );
        }

        // Set the custom data into an array. If it can't be unserialized,
        // then treat it as a single value which contains only the user ID.
        if (isset($A['custom'])) {
            $this->custom->decode($A['custom']);
            if (!isset($this->custom['uid'])) {
                $this->custom['uid'] = (int)$A['custom'];
            }
        }
        // If the user ID is still not set, use anonymous
        if (!isset($this->custom['uid'])) {
            $this->custom['uid'] = 1;
        }
        $this->setUid($this->custom['uid']);

        // Set the IPN status to one of the standard values
        switch ($this->ipn_data->getString('payment_status')) {
        case 'Pending':
            $this->setStatus(OrderStatus::PENDING);
            break;
        case 'Completed':
            $this->setStatus(OrderStatus::PAID);
            break;
        case 'Refunded':
            $this->setStatus(OrderStatus::REFUNDED);
            $this->ipn_data['txn_type'] = 'refund';
            $this->setEvent('refund');
            break;
        }

        switch ($A->getString('txn_type')) {
        case 'web_accept':
        case 'send_money':
            $this->setPmtShipping($A->getFloat('shipping'))
                 ->setPmtHandling($A->getFloat('handling'));
            break;
        case 'cart':
            $this->setPmtShipping($A->getFloat('mc_shipping'))
                 ->setPmtHandling($A->getFloat('mc_handling'));
            break;
        default:
            $this->setParentTxnId($A['parent_txn_id']);
            break;
        }
    }


    /**
     * Verify the transaction with Paypal.
     * Validate transaction by posting data back to the shop webserver.
     * The response from shop should include 'VERIFIED' on a line by itself.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    public function Verify() : bool
    {
        // Even test transactions have to be unique
        if (!$this->isUniqueTxnId()) {
            return false;
        }

        if (
            $this->GW->isSandbox()
            && (
                isset($this->ipn_data['test_ipn'])      // added by Paypal
                ||
                isset($this->ipn_data['x_test_ipn']) // added by local test page
            )
        ) {
            return true;
        }

        // Default verification to false
        $verified = false;

        // read the post from PayPal system and add 'cmd'
        $req = 'cmd=_notify-validate';

        // re-encode the transaction variables to be verified
        foreach ($this->ipn_data as $key => $value) {
            $value = urlencode($value);
            $req .= "&$key=$value";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->GW->getPostBackUrl());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code != 200) {
            Log::error("IPN Verification returned $http_code");
        } elseif (strcmp($response, 'VERIFIED') != 0) {
            Log::error("IPN Verification reponse $response");
        } else {
            $verified = true;
        }
        return $verified;
    }


    /**
     * Process an incoming IPN transaction.
     * Do the following:
     * - Verify IPN
     * - Log IPN
     * - Check that transaction is complete
     * - Check that transaction is unique
     * - Check for valid receiver email address
     * - Process IPN
     *
     * @uses    IPN::handleFailure()
     * @uses    IPN::handlePurchase()
     * @uses    IPN::isUniqueTxnId()
     * @uses    IPN::Log()
     * @uses    Verify()
     * @return  boolean true if processing valid and completed, false otherwise
     */
    public function Process()
    {
        // If no data has been received, then there's nothing to do.
        if (empty($this->ipn_data)) {
            return false;
        }

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(
                SHOP_FAILURE_VERIFY,
                "($logId) Verification failed"
            );
            return false;
        }
        $this->Log(true);

        // Set the custom data field to the exploded value.  This has to
        // be done after Verify() or the Shop verification will fail.
        switch ($this->ipn_data->getString('txn_type')) {
        case 'web_accept':  //usually buy now
        case 'send_money':  //usually donation/send money
            $tax = $this->ipn_data->getFloat('tax');
            $shipping = $this->ipn_data->getFloat('shipping');
            $handling = $this->ipn_data->getFloat('handling');
            $price = (float)$this->ipn_data['mc_gross'] - $tax - $shipping - $handling;
            if (isset($this->ipn_data['item_number'])) {
                $this->addItem(array(
                    'item_id'   => $this->ipn_data['item_number'],
                    'item_name' => $this->ipn_data['item_name'],
                    'quantity'  => $this->ipn_data['quantity'],
                    'price'     => $price,
                    'tax'       => $tax,
                    'shipping'  => $shipping,
                    'handling'  => $handling,
                ));
            }
            Log::debug("Net Settled: {$this->getPmtGross()} {$this->getCurrency()->getCode()}");
            $this->handlePurchase();    // Order gets created here.

            // Set the billing and shipping address to at least get the name,
            // if not already set.
            $Address = new Address;
            $Address->fromArray(array(
                'id' => -1,
                'name' => trim($this->ipn_data->getString('first_name') . ' ' . $this->ipn_data->getString('last_name')),
                'country' => $this->ipn_data->getString('residence_country'),
            ) );
            if ($this->Order->getBillto()->getID() == 0) {
                $this->Order->setBillto($Address);
            }
            if ($this->Order->getShipto()->getID() == 0) {
                $this->Order->setBillto($Address);
            }
            break;

        case 'cart':
            // shopping cart
            // Create a cart and read the info from the cart table.
            $this->Order = $this->getOrder();
            if (!$this->Order || $this->Order->isNew()) {
                $this->handleFailure(NULL, "Order ID {$this->order_id} not found for cart purchases");
                return false;
            }
            $this
                ->setPmtTax($this->Order->getTax())
                ->setPmtShipping($this->Order->getShipping())
                ->setPmtHandling($this->Order->getHandling());
            $Cart = $this->Order->getItems();
            if (empty($Cart)) {
                Log::error("Empty Cart for id {$this->Order->getOrderID()}");
                return false;
            }

            // Set the billing and shipping address to at least get the name,
            // if not already set.
            $Address = new Address;
            $Address->fromArray(array(
                'id' => -1,
                'name' => trim($this->ipn_data->getString('first_name') . ' ' . $this->ipn_data->getString('last_name')),
                'address1' => $this->ipn_data->getString('address_street'),
                'city' => $this->ipn_data->getString('address_city'),
                'state' => $this->ipn_data->getString('address_state'),
                'zip' => $this->ipn_data->getString('address_zip'),
                'country' => $this->ipn_data->getString('residence_country'),
            ) );
            if ($this->Order->getBillto()->getID() == 0) {
                $this->Order->setBillto($Address);
            }
            if ($this->Order->getShipto()->getID() == 0) {
                $this->Order->setBillto($Address);
            }

            $payment_gross = $this->getPmtGross();
            Log::debug("Received $payment_gross gross payment");
            $this->handlePurchase();
            break;

        // other, unknown, unsupported
        default:
            switch ($this->ipn_data->getString('reason_code')) {
            case 'refund':
                $this->handleRefund();
                break;
            default:
                $this->handleFailure(
                    SHOP_FAILURE_UNKNOWN,
                    "($logId) Unknown transaction type"
                );
                return false;
                break;
            }
            break;
        }

        return true;
    }

}
