<?php
/**
 * This is the internal test gateway's IPN processor.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\_internal;
use Shop\Cart;
use Shop\Models\OrderState;


// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

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
        $this->gw_id = '_internal';
        $A = $_POST;
        parent::__construct($A);

        $this
            ->setTxnId(SHOP_getVar($A, 'txn_id'))
            ->setPmtGross(SHOP_getVar($A, 'pmt_gross', 'float'));

        // Check a couple of vars to see if a shipping address was supplied
        if (isset($A['address']) || isset($A['city'])) {
            $this->shipto = array(
                'name'      => SHOP_getVar($A, 'first_name') . ' ' . SHOP_getVar($A, 'last_name'),
                'address1'  => SHOP_getVar($A, 'address1'),
                'address2'  => '',
                'city'      => SHOP_getVar($A, 'city'),
                'state'     => SHOP_getVar($A, 'state'),
                'country'   => SHOP_getVar($A, 'country'),
                'zip'       => SHOP_getVar($A, 'zip'),
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
        return true;
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

        // Set the custom data field to the exploded value.  This has to
        // be done after Verify() or the Shop verification will fail.
        $tax = LGLIB_getVar($this->ipn_data, 'tax', 'float');
        $shipping = LGLIB_getVar($this->ipn_data, 'shipping', 'float');
        $handling = LGLIB_getVar($this->ipn_data, 'handling', 'float');
        $price = (float)$this->ipn_data['pmt_gross'] - $tax - $shipping - $handling;
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
        SHOP_log("Net Settled: {$this->getPmtGross()} {$this->getCurrency()->getCode()}", SHOP_LOG_DEBUG);
        $this->handlePurchase();
        $this->Log(true);
        COM_setMsg('Thank youi for your order.');
        COM_refresh(SHOP_URL . '/index.php');
        return true;
    }

}
