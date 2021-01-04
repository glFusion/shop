<?php
/**
 * This file contains the Stripe IPN class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\stripe;
use Shop\Cart;
use Shop\Gateway;
use Shop\Models\OrderState;
use Shop\Models\CustomInfo;


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
class ipn extends \Shop\IPN
{
    /** Event object obtained from the IPN payload.
     * @var object */
    private $_event;

    /** Payment Intent object obtained from the ID in the Event object.
     * @var object */
    private $_payment;

    /** Override the IPN filename, Stripe needs further processing for now.
     * @var string */
    protected $ipn_filename = 'stripe.php';


    /**
     * Constructor.
     *
     * @param   array   $A  Payload provided by Stripe
     */
    function __construct($A=array())
    {
        global $_USER, $_CONF;

        $this->gw_id = 'stripe';
        parent::__construct();  // construct without IPN data.

        // Instantiate the gateway to load the needed API key.
        $GW = Gateway::getInstance('stripe');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $payload = @file_get_contents('php://input');
        SHOP_log('Recieved Stripe IPN: ' . var_export($payload, true), SHOP_LOG_DEBUG);
        $event = null;

        require_once __DIR__ . '/vendor/autoload.php';
        try {
            \Stripe\Stripe::setApiKey($GW->getSecretKey());
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $GW->getWebhookSecret()
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            SHOP_log("Unexpected Value received from Stripe");
            http_response_code(400); // PHP 5.4 or greater
            exit;
        } catch(\Stripe\Error\SignatureVerification $e) {
            // Invalid signature
            SHOP_log("Invalid Stripe signature received");
            http_response_code(400); // PHP 5.4 or greater
            exit;
        }

        if (empty($event)) {
            return;
        }

        $this->_event = $event;
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
        $billto = $this->Order->getBillto();
        $shipto = $this->Order->getShipto();
        if (empty($shipto->getID()) && !empty($billto->getID())) {
            $shipto = $billto;
        }

        $this
            ->setEmail($this->Order->getBuyerEmail())
            ->setPayerName($_USER['fullname'])
            ->setGwName($this->GW->getName())
            ->setStatus(OrderState::PENDING);

        $this->shipto = $shipto->toArray();
        $this->custom = new CustomInfo(array(
            'transtype' => $this->GW->getName(),
            'uid'       => $this->Order->getUid(),
            'by_gc'     => $this->Order->getInfo()['apply_gc'],
        ) );
    }


    /**
     * Verify the transaction.
     * This just checks that a valid cart_id was received along with other
     * variables.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    public function Verify()
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
     *  - Check that transaction is complete
     *  - Check that transaction is unique
     *  - Check for valid receiver email address
     *  - Process IPN
     *
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
        // Handle the checkout.session.completed event
        if ($this->_event->type != 'checkout.session.completed') {
            return false;
        }

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
        return $this->handlePurchase();
    }

}
