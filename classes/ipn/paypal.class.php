<?php
/**
 * This file contains the Shop IPN class to deal with IPN transactions from Shop.
 * Based on the gl-shop Plugin for Geeklog CMS by Vincent Furia.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\ipn;

use \Shop\Cart;

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
class paypal extends \Shop\IPN
{
    /**
     * Constructor.  Set up variables received from Shop.
     *
     * @param   array   $A      $_POST'd variables from Shop
     */
    function __construct($A=array())
    {
        $this->gw_id = 'paypal';
        parent::__construct($A);

        $this
            ->setTxnId(SHOP_getVar($A, 'txn_id'))
            ->setEmail(SHOP_getVar($A, 'payer_email'))
            ->setPayerName(SHOP_getVar($A, 'first_name') .' '. SHOP_getVar($A, 'last_name'))
            ->setPmtGross(SHOP_getVar($A, 'mc_gross', 'float'))
            ->setPmtTax(SHOP_getVar($A, 'tax', 'float'))
            ->setCurrency(SHOP_getVar($A, 'mc_currency', 'string', 'Unk'))
            ->addCredit('discount', SHOP_getVar($A, 'discount', 'float', 0))
            ->setOrderId(SHOP_getVar($A, 'invoice'))
            ->setParentTxnId(SHOP_getVar($A, 'parent_txn_id'));

        // Check a couple of vars to see if a shipping address was supplied
        if (isset($A['address_street']) || isset($A['address_city'])) {
            $this->shipto = array(
                'name'      => SHOP_getVar($A, 'address_name'),
                'address1'  => SHOP_getVar($A, 'address_street'),
                'address2'  => '',
                'city'      => SHOP_getVar($A, 'address_city'),
                'state'     => SHOP_getVar($A, 'address_state'),
                'country'   => SHOP_getVar($A, 'address_country'),
                'zip'       => SHOP_getVar($A, 'address_zip'),
            );
        }

        // Set the custom data into an array.  If it can't be unserialized,
        // then treat it as a single value which contains only the user ID.
        if (isset($A['custom'])) {
            $this->custom = @unserialize(str_replace('\'', '"', $A['custom']));
            if (!$this->custom) {
                $this->custom = array('uid' => $A['custom']);
            }
        }
        $this->setUid(SHOP_getVar($this->custom, 'uid', 'integer', 1));

        // Set the IPN status to one of the standard values
        switch ($this->ipn_data['payment_status']) {
        case 'Pending':
            $this->setStatus(self::PENDING);
            break;
        case 'Completed':
            $this->setStatus(self::PAID);
            break;
        case 'Refunded':
            $this->setSTatus(self::REFUNDED);
            break;
        }

        switch (SHOP_getVar($A, 'txn_type')) {
        case 'web_accept':
        case 'send_money':
            $this
                ->setPmtShipping(SHOP_getVar($A, 'shipping', 'float'))
                ->setPmtHandling(SHOP_getVar($A, 'handling', 'float'));
            break;
        case 'cart':
            $this
                ->setPmtShipping(SHOP_getVar($A, 'mc_shipping', 'float'))
                ->setPmtHandling(SHOP_getVar($A, 'mc_handling', 'float'));
            break;
        }
    }


    /**
     * Verify the transaction with Shop.
     * Validate transaction by posting data back to the shop webserver.
     * The response from shop should include 'VERIFIED' on a line by itself.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    public function Verify()
    {
        // Even test transactions have to be unique
        if (!$this->isUniqueTxnId()) {
            return false;
        }

        if (
            $this->GW->isSandbox()
            && (
                isset($this->ipn_data['test_ipn'])      // added by Paypal
                || isset($this->ipn_data['x_test_ipn']) // added by local test page
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
            SHOP_log("IPN Verification returned $http_code", SHOP_LOG_ERROR);
        } elseif (strcmp($response, 'VERIFIED') != 0) {
            SHOP_log("IPN Verification reponse $response", SHOP_LOG_ERROR);
        } else {
            $verified = true;
        }
        return $verified;
    }


    /**
     * Confirms that payment status is complete.
     * Complete means not 'denied', 'failed', 'pending', etc.
     *
     * @param   string  $payment_status     Payment status to verify
     * @return  boolean                     True if complete, False otherwise
     */
    private function XXisStatusCompleted($payment_status)
    {
        return ($payment_status == 'Completed');
    }


    /**
     * Checks if payment status is reversed or refunded.
     * For example, some sort of cancelation.
     *
     * @param   string  $payment_status     Payment status to check
     * @return  boolean                     True if reversed or refunded
     */
    private function XXisStatusReversed($payment_status)
    {
        return ($payment_status == 'Reversed' || $payment_status == 'Refunded');
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
     * @uses    IPN::addItem()
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

        // Set the custom data field to the exploded value.  This has to
        // be done after Verify() or the Shop verification will fail.
        switch ($this->ipn_data['txn_type']) {
        case 'web_accept':  //usually buy now
        case 'send_money':  //usually donation/send money
            $item_number = SHOP_getVar($this->ipn_data, 'item_number');
            $quantity = SHOP_getVar($this->ipn_data, 'quantity', 'float');

            if (empty($item_number)) {
                $this->handleFailure(NULL, 'Missing Item Number in Buy-now process');
                return false;
            }
            if (empty($quantity)) {
                $quantity = 1;
            }
            $unit_price = $this->getPmtNet() / $quantity;
            $args = array(
                'item_id'   => $item_number,
                'quantity'  => $quantity,
                'price'     => $unit_price,
                'item_name' => SHOP_getVar($this->ipn_data, 'item_name', 'string', 'Undefined'),
                'shipping'  => $this->getPmtShipping(),
                'handling'  => $this->getPmtHandling(),
            );
            $this->addItem($args);

            SHOP_log("Net Settled: {$this->getPmtGross()} {$this->getCurrency()->code}", SHOP_LOG_DEBUG);
            $this->handlePurchase();
            break;

        case 'cart':
            // shopping cart
            // Create a cart and read the info from the cart table.
            $this->Order = $this->getOrder();
            if (!$this->Order || $this->Order->isNew) {
                $this->handleFailure(NULL, "Order ID {$this->order_id} not found for cart purchases");
                return false;
            }
            $this
                ->setPmtTax($this->Order->getInfo('tax'))
                ->setPmtShipping($this->Order->getInfo('shipping'))
                ->setPmtHandling($this->Order->getInfo('handling'));
            $Cart = $this->Order->getItems();
            if (empty($Cart)) {
                SHOP_log("Empty Cart for id {$this->Order->order_id}", SHOP_LOG_ERROR);
                return false;
            }

            foreach ($Cart as $item) {
                $item_id = $item->product_id;
                if ($item->options != '') {
                    $item_id .= '|' . $item->options;
                }
                $args = array(
                    'item_id'   => $item_id,
                    'quantity'  => $item->quantity,
                    'price'     => $item->price,
                    'item_name' => $item->getShortDscp(),
                    'shipping'  => $item->shipping,
                    'handling'  => $item->handling,
                    'tax'       => $item->tax,
                    'extras'    => $item->extras,
                );
                $this->addItem($args);
            }

            $payment_gross = $this->getPmtGross();
            SHOP_log("Received $payment_gross gross payment", SHOP_LOG_DEBUG);
            $this->handlePurchase();
            break;

        // other, unknown, unsupported
        default:
            switch (SHOP_getVar($this->ipn_data, 'reason_code')) {
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

        $this->Log(true);
        return true;
    }   // function Process

}

?>
