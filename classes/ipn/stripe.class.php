<?php
/**
 * This file contains the Stripe IPN class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner
 * @package     shop
 * @version     v0.7.1
 * @since       v0.7.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\ipn;
use Shop\Cart;
use Shop\Models\OrderState;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 *  Class to provide IPN for internal-only transactions,
 *  such as zero-balance orders.
 *
 *  @package shop
 */
class stripe extends \Shop\IPN
{
    /** Event object obtained from the IPN payload.
     * @var object */
    private $_event;

    /** Payment Intent object obtained from the ID in the Event object.
     * @var object */
    private $_payment;


    /**
     * Constructor.
     *
     * @param   string  $A      Payload provided by Stripe
     */
    function __construct($A=array())
    {
        global $_USER, $_CONF;

        $this->gw_id = 'stripe';
        parent::__construct();  // construct without IPN data.

        $this->_event = $A;        
        $session = $this->_event->data->object;
        $order_id = $session->client_reference_id;
        $this->ipn_data['order_id'] = $order_id;
        $this->setTxnId($session->payment_intent);

        if (!empty($order_id)) {
            $this->Order = $this->getOrder($order_id);
        }
        if (!$this->Order || $this->Order->isNew()) {
            // Invalid order specified, nothing can be done.
            return NULL;
        }

        $this->setOrderId($this->Order->getOrderID());
        $billto = $this->Order->getAddress('billto');
        $shipto = $this->Order->getAddress('shipto');
        if (empty($shipto) && !empty($billto)) {
            $shipto = $billto;
        }

        $this
            ->setEmail($this->Order->getBuyerEmail())
            ->setPayerName($_USER['fullname'])
            ->setGwName($this->GW->getName())
            ->setStatus(OrderState::PENDING);

        $this->shipto = array(
            'name'      => SHOP_getVar($shipto, 'name'),
            'company'   => SHOP_getVar($shipto, 'company'),
            'address1'  => SHOP_getVar($shipto, 'address1'),
            'address2'  => SHOP_getVar($shipto, 'address2'),
            'city'      => SHOP_getVar($shipto, 'city'),
            'state'     => SHOP_getVar($shipto, 'state'),
            'country'   => SHOP_getVar($shipto, 'country'),
            'zip'       => SHOP_getVar($shipto, 'zip'),
        );

        $this->custom = array(
            'transtype' => $this->GW->getName(),
            'uid'       => $this->Order->getUid(),
            'by_gc'     => $this->Order->getInfo()['apply_gc'],
        );

        foreach ($this->Order->getItems() as $idx=>$item) {
            $args = array(
                'item_id'   => $item->getProductID(),
                'quantity'  => $item->getQuantity(),
                'price'     => $item->getNetPrice(),
                'item_name' => $item->getDscp(),
                'shipping'  => $item->getShipping(),
                'handling'  => $item->getHandling(),
                'extras'    => $item->getExtras(),
            );
            $this->addItem($args);
        }
    }


    /**
     * Verify the transaction.
     * This just checks that a valid cart_id was received along with other
     * variables.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    private function Verify()
    {
        // Get the payment intent from Stripe
        $trans = $this->GW->getPayment($this->getTxnId());
        $this->_payment = $trans;

        if (!$trans || $trans->status != 'succeeded') {
            // Payment verification failed.
            SHOP_log('ipn\stripe::Verify() failed', SHOP_LOG_DEBUG);
            return false;
        }
        $this->ipn_data['txn'] = $trans;

        // Verification succeeded, get payment info.
        $this
            ->setStatus(OrderState::PAID)
            ->setCurrency($trans->currency)
            ->setPmtGross($this->getCurrency()->fromInt($trans->amount_received));

        $session = $this->_event->data->object;
        $pmt_shipping = 0;
        $pmt_tax = 0;
        foreach ($session->display_items as $item) {
            if ($item->custom->name == '__tax') {
                $pmt_tax += $this->getCurrency()->fromInt($item->amount);
            } elseif ($item->custom->name == '__shipping') {
                $pmt_shipping += $this->getCurrency()->fromInt($item->amount);
            } elseif ($item->custom->name == '__gc') {
                // TODO when Stripe supports coupons
                $this->addCredit('gc', $item->amount);
            }
        }

        $this
            ->setPmtTax($pmt_tax)
            ->setPmtShipping($pmt_shipping);
        $this->ipn_data['pmt_shipping'] = $this->getPmtShipping();
        $this->ipn_data['pmt_tax'] = $this->getPmtTax();
        $this->ipn_data['pmt_gross'] = $this->getPmtGross();
        $this->ipn_data['status'] = $this->getStatus();  // to get into handlePurchase()
        SHOP_log("Stripe transaction verified OK", SHOP_LOG_DEBUG);
        return true;
    }


    /**
     * Process an incoming IPN transaction.
     * Do the following:
     *  - Verify IPN
     *  - Log IPN
     *  - Check that transaction is complete
     *  - Check that transaction is unique
     *  - Check for valid receiver email address
     *  - Process IPN
     *
     * @uses   IPN::AddItem()
     * @uses   IPN::handleFailure()
     * @uses   IPN::handlePurchase()
     * @uses   IPN::isUniqueTxnId()
     * @uses   IPN::Log()
     * @uses   Verify()
     * @param  array   $in     POST variables of transaction
     * @return boolean true if processing valid and completed, false otherwise
     */
    public function Process()
    {
        // Backward compatibility, get custom data into IPN for plugin
        // products.
        $this->ipn_data['custom'] = $this->custom;

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(
                self::FAILURE_VERIFY,
                "($logId) Verification failed"
            );
            return false;
        } elseif (!$this->isUniqueTxnId()) {
            SHOP_log("Duplicate Txn ID " . $this->getTxnId());
            $logId = $this->Log(false);
            return false;
        } else {
            $logId = $this->Log(true);
        }

        // If no data has been received, then there's nothing to do.
        if (empty($this->_payment)) {
            SHOP_log("Empty payment received");
            return false;
        }

        // Add the item to the array for the order creation.
        // IPN item numbers are indexes into the cart, so get the
        // actual product ID from the cart
        foreach ($this->Order->getItems() as $idx=>$item) {
            $args = array(
                'item_id'   => $item->getID(),
                'quantity'  => $item->getQuantity(),
                'price'     => $item->getPrice(),
                'item_name' => $item->getDscp(),
                'shipping'  => $item->getShipping(),
                'handling'  => $item->getHandling(),
                'extras'    => $item->getExtras(),
            );
            $this->addItem($args);
        }
        return $this->handlePurchase();
    }

}

?>
