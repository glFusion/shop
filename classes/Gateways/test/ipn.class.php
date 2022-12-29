<?php
/**
 * IPN handler for the test payment gateway buy-now function.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner
 * @package     shop
 * @version     v1.6.0
 * @since       v1.6.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\test;
use Shop\Address;
use Shop\Models\OrderStatus;
use Shop\Models\DataArray;
use Shop\Log;
use Shop\Config;


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
        $this->gw_id = 'test';
        $A = new DataArray($_POST);
        parent::__construct($A);

        $this
            ->setTxnId($A->getString('txn_id'))
            ->setEvent($A->getString('cmd'))
            ->setEmail($A->getString('payer_email'))
            ->setPayerName(trim($A->getString('first_name') . ' ' . $A->getString('last_name')))
            ->setPmtGross($A->getFloat('pmt_gross'))
            ->setPmtTax($A->getFloat('tax'))
            ->setCurrency($A->getString('mc_currency', Config::get('currency')))
            ->addCredit('discount', $A->getFloat('discount'));

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
    }


    /**
     * Verify the transaction.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    public function Verify() : bool
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
                self::FAILURE_VERIFY,
                "($logId) Verification failed"
            );
            return false;
        }

        // Set the custom data field to the exploded value.  This has to
        // be done after Verify() or the Shop verification will fail.
        switch ($this->getEvent()) {
        case 'buy_now':  //usually buy now
            $price = $this->ipn_data->getFloat('pmt_gross');
            $this->setPmtGross($price);
            if (isset($this->ipn_data['item_number'])) {
                $this->addItem(array(
                    'item_id'   => $this->ipn_data->getString('item_number'),
                    'item_name' => $this->ipn_data->getString('item_name'),
                    'quantity'  => $this->ipn_data->getFloat('quantity'),
                    'price'     => $price,
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
        }

        return true;
    }


    /**
     * Echo a response to the IPN request based on the status from Process().
     *
     * @param   boolean $status     Result from processing
     * @return  void
     */
    public function Response($status)
    {
        $return = $this->ipn_data->getString('return');
        if (!empty($return)) {
            echo COM_refresh($return);
        } else {
            echo COM_refresh(Config::get('url') . '/index.php');
        }
    }

}
