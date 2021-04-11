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
use Shop\Order;
use Shop\Gateway;
use Shop\Currency;
use Shop\Payment;
use Shop\Models\OrderState;
use Shop\Models\CustomInfo;


// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * Class to provide webhook for the Stripe payment processor.
 * @package shop
 */
class Webhook extends \Shop\Webhook
{
    /** Payment Intent object obtained from the ID in the Event object.
     * @var object */
    private $_payment;

    /**
     * Constructor.
     *
     * @param   array   $A  Payload provided by Stripe
     */
    function __construct($A=array())
    {
        global $_USER, $_CONF;

        $this->setSource('stripe');
        // Instantiate the gateway to load the needed API key.
        $this->GW = Gateway::getInstance('stripe');
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
        $event = NULL;
        if (isset($_POST['vars'])) {
            $payload = base64_decode($_POST['vars']);
            $sig_header = $_POST['HTTP_STRIPE_SIGNATURE'];
            $event = json_decode($payload);
        } elseif (isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $payload = @file_get_contents('php://input');
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        } else {
            $payload = '';
            $sig_header = 'invalid';
        }
        SHOP_log('Recieved Stripe Webhook: ' . var_export($payload, true), SHOP_LOG_DEBUG);
        SHOP_log('Sig Key: ' . var_export($sig_header, true), SHOP_LOG_DEBUG);

        if ($sig_header == 'invalid') {
            return false;
        }

        $this->blob = $payload;

        if ($event === NULL) {  // to skip test data from $_POST
            require_once __DIR__ . '/vendor/autoload.php';
            try {
                \Stripe\Stripe::setApiKey($this->GW->getSecretKey());
                $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, $this->GW->getWebhookSecret()
                );
            } catch(\UnexpectedValueException $e) {
                // Invalid payload
                SHOP_log("Unexpected Value received from Stripe");
                return false;
                //http_response_code(400); // PHP 5.4 or greater
                //exit;
            } catch(\Stripe\Error\SignatureVerification $e) {
                // Invalid signature
                SHOP_log("Invalid Stripe signature received");
                return false;
                //http_response_code(400); // PHP 5.4 or greater
                //exit;
            }
        }
        if (empty($event)) {
            SHOP_log("Unable to create Stripe webhook event");
            return false;
        }
        $this->setData($event);
        $this->setEvent($this->getData()->type);
        $this->setVerified(true);
        SHOP_log("Stripe webhook verified OK", SHOP_LOG_DEBUG);
        return true;
    }


    /**
     * Perform the necessary actions based on the webhook.
     * At this point all required objects should be valid.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
        $retval = false;        // be pessimistic

        switch ($this->getEvent()) {
        case 'invoice.created':
            // Invoice was created. As a net-terms customer, the order
            // can be processed.
            if (!isset($this->getData()->data->object->metadata->order_id)) {
                SHOP_log("Order ID not found in invoice metadata");
                return false;
            }
            $this->setOrderID($this->getData()->data->object->metadata->order_id);
            $this->Order = Order::getInstance($this->getOrderID());
            if ($this->Order->isNew()) {
                SHOP_log("Invalid Order ID received in webhook");
                return false;
            }
            $this->Order->setGatewayRef($this->getData()->data->object->id)
                        ->setInfo('terms_gw', $this->GW->getName())
                        ->Save();
            if ($this->Order->statusAtLeast(OrderState::PROCESSING)) {
                SHOP_log("Order " . $this->Order->getOrderId() . " was already invoiced and processed");
            }

            // Invoice created successfully
            $retval = $this->handlePurchase($this->Order);
            break;
        case 'invoice.payment_succeeded': 
            // Invoice payment notification
            if (!isset($this->getData()->data->object->metadata->order_id)) {
                SHOP_log("Order ID not found in invoice metadata");
                return false;
            }

            if (!isset($this->getData()->data->object->payment_intent)) {
                SHOP_log("Payment Intent value not include in webhook");
                return false;
            }
            $Payment = $this->getData()->data->object;
            $this->setID($Payment->payment_intent);
            if (!$this->isUniqueTxnId()) {
                SHOP_log("Duplicate Stripe Webhook received: " . $this->getData()->id);
                return false;
            }

            $this->setOrderID($this->getData()->data->object->metadata->order_id);
            $this->Order = Order::getInstance($this->getOrderID());
            if ($this->Order->isNew()) {
                SHOP_log("Invalid Order ID received in webhook");
                return false;
            }
            $amt_paid = $Payment->amount_paid;
            if ($amt_paid > 0) {
                $this->setID($Payment->payment_intent);
                $currency = $Payment->currency;
                $this_pmt = Currency::getInstance($currency)->fromInt($amt_paid);
                $Pmt = Payment::getByReference($this->getID());
                if ($Pmt->getPmtID() == 0) {
                    $Pmt->setRefID($this->getID())
                        ->setAmount($this_pmt)
                        ->setGateway($this->getSource())
                        ->setMethod($this->GW->getDscp())
                        ->setComment('Webhook ' . $this->getData()->id)
                        ->setComplete(1)
                        ->setStatus($this->getData()->type)
                        ->setOrderID($this->getOrderID());
                    $retval = $Pmt->Save();
                }
                $this->logIPN();
            }
            break;
        case 'checkout.session.completed':
            // Immediate checkout notification
            if (!isset($this->getData()->data->object->client_reference_id)) {
                SHOP_log("Order ID not found in invoice metadata");
                return false;
            }
            if (!isset($this->getData()->data->object->payment_intent)) {
                SHOP_log("Payment Intent value not include in webhook");
                return false;
            }

            $Payment = $this->getData()->data->object;
            $this->setID($Payment->payment_intent);
            if (!$this->isUniqueTxnId()) {
                SHOP_log("Duplicate Stripe Webhook received: " . $this->getData()->id);
                return false;
            }

            $this->setOrderID($this->getData()->data->object->client_reference_id);
            $this->Order = Order::getInstance($this->getOrderID());
            if ($this->Order->isNew()) {
                SHOP_log("Invalid Order ID received in webhook");
                return false;
            }
            $amt_paid = $Payment->amount_total;
            if ($amt_paid > 0) {
                $this->setID($Payment->payment_intent);
                $currency = $Payment->currency;
                $this_pmt = Currency::getInstance($currency)->fromInt($amt_paid);
                $this->Payment = Payment::getByReference($this->getID());
                if ($this->Payment->getPmtID() == 0) { 
                    $this->Payment->setRefID($this->getID())
                        ->setAmount($this_pmt)
                        ->setGateway($this->getSource())
                        ->setMethod($this->GW->getDscp())
                        ->setComment('Webhook ' . $this->getData()->id)
                        ->setComplete(1)
                        ->setStatus($this->getData()->type)
                        ->setOrderID($this->getOrderID());
                    $this->Payment->Save();
                    $retval = true;
                }
                $retval = $this->handlePurchase($this->Order);
                $this->logIPN();
            }
            break;
        default:
            SHOP_log("Unhandled Stripe event {$this->getData()->type} received", SHOP_LOG_DEBUG);
            $retval = true;     // OK, just some other event received
            break;
        }
        return $retval;
    }

}
