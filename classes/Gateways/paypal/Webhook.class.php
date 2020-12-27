<?php
/**
 * Paypal Webhook class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\paypal;
use Shop\Payment;
use Shop\Order;
use Shop\Models\OrderState;

/**
 * Paypal webhook class.
 * @package shop
 */
class Webhook extends \Shop\Webhook
{
    /** Un-decoded original payload contents.
     * @var string */
    private $blob = '';


    /**
     * Set up the webhook data from the supplied JSON blob.
     *
     * @param   string  $blob   JSON data
     */
    public function __construct($blob='')
    {
        $this->setSource('paypal');

        // Load the payload into the blob property for later use in Verify().
        if (isset($_POST['headers'])) {
            $this->blob = base64_decode($_POST['vars']);
            $this->setHeaders(json_decode(base64_decode($_POST['headers']),true));
        } else {
            $this->blob = file_get_contents('php://input');
            $this->setHeaders(NULL);
        }
        SHOP_log("Paypal Webook Payload: " . $this->blob, SHOP_LOG_DEBUG);
        SHOP_log("Paypal Webhook Headers: " . var_export($_SERVER,true), SHOP_LOG_DEBUG);

        $this->setTimestamp();
        $this->setData(json_decode($this->blob, true));
    }


    /**
     * Perform the necessary actions based on the webhook.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
        $resource = SHOP_getVar($this->getData(), 'resource', 'array', NULL);
        if ($resource) {
            $invoice = SHOP_getVar($resource, 'invoice', 'array', NULL);
            if ($invoice) {
                $detail = SHOP_getVar($invoice, 'detail', 'array', NULL);
                if ($detail) {
                    $this->setOrderID(SHOP_getVar($detail, 'reference'));
                }
            }
        }
        switch ($this->getEvent()) {
        case self::EV_INV_PAYMENT:
            if ($invoice) {
                $payments = SHOP_getVar($invoice, 'payments', 'array', NULL);
                if (is_array($payments) && !empty($payments)) {
                    // Get just the latest payment.
                    // If there are multiple payments for the order, all are included.
                    $payment = array_pop($payments['transactions']);
                    if (is_array($payment)) {
                        $ref_id = $payment['payment_id'];
                       // Get the payment by reference ID to make sure it's unique
                        $Pmt = Payment::getByReference($ref_id);
                        if ($Pmt->getPmtID() == 0) {
                            $Pmt->setRefID($ref_id)
                                ->setAmount($payment['amount']['value'])
                                ->setGateway($this->getSource())
                                ->setMethod($payment['method'])
                                ->setComment('Webhook ' . $this->getID())
                                ->setOrderID($this->getOrderID());
                            return $Pmt->Save();
                        }
                        $this->setID($ref_id);  // use the payment ID
                        $this->logIPN();
                     }
                }
            }
            break;

        case self::EV_INV_CREATED:
            SHOP_log("Invoice created for {$this->getOrderID()}", SHOP_LOG_DEBUG);
            $Order = Order::getInstance($this->getOrderID());
            if (!$Order->isNew()) {
                $terms_gw = \Shop\Gateway::create($Order->getPmtMethod());
                $Order->updateStatus($terms_gw->getConfig('after_inv_status'));
            }
            break;

        case self::EV_PAYMENT:
            var_dump($this->getData());die;
            SHOP_log("Payment received for {$this->getOrderID()}", SHOP_LOG_DEBUG);
            if (isset($resource['amount']['value'])) {
                $pmt_gross = (float)$resource['amount']['value'];
                $ref_id = $resource['id'];
                // Get the payment by reference ID to make sure it's unique
                $Pmt = Payment::getByReference($ref_id);
                if ($Pmt->getPmtID() == 0) {
                    $Pmt->setRefID($ref_id)
                        ->setAmount($pmt_gross)
                        ->setGateway($this->getSource())
                        ->setMethod($this->getSource())
                        ->setComment('Webhook ' . $this->getID())
                        ->setOrderID($this->getOrderID());
                    return $Pmt->Save();
                }
                $this->setID($ref_id);  // use the payment ID
                $this->logIPN();
            }
            break;
        }
        return false;
    }


    /**
     * Get the standard event type string based on the webhook-specific string.
     *
     * @return  string      Standard string for the plugin to use
     */
    public function getEvent()
    {
        switch ($this->whEvent) {
        case 'INVOICING.INVOICE.PAID':
            return self::EV_INV_PAYMENT;
            break;
        case 'INVOICING.INVOICE.CREATED':
            return self::EV_INV_CREATED;
            break;
        case 'CHECKOUT.ORDER.APPROVED':
            return 'approved';
            break;
        case 'PAYMENT.CAPTURE.COMPLETED':
            return self::EV_PAYMENT;
            break;
        default:
            return self::EV_UNDEFINED;
            break;
        }
    }


    /**
     * Verify that the webhook is valid.
     *
     * @return  boolean     True if valid, False if not.
     */
    public function Verify()
    {
        // Check that the blob was decoded successfully.
        // If so, extract the key fields and set Webhook variables.
        $data = $this->getData();
        if ($data) {     // Indicates that the blob was decoded
            $this->setID(SHOP_getVar($data, 'id'));
            $this->setEvent(SHOP_getVar($data, 'event_type'));
        } else {
            return false;
        }

        if (isset($_GET['testhook'])) {
            $this->setVerified(true);
            return true;
        }

        $gw = \Shop\Gateway::getInstance($this->getSource());
        $body = '{
            "auth_algo": "' . $this->getHeader('Paypal-Auth-Algo') . '",
            "cert_url": "' . $this->getHeader('Paypal-Cert-Url') . '",
            "transmission_id": "' . $this->getHeader('Paypal-Transmission-Id') . '",
            "transmission_sig": "'. $this->getHeader('Paypal-Transmission-Sig') . '",
            "transmission_time": "'. $this->getHeader('Paypal-Transmission-Time') . '",
            "webhook_id": "' . $gw->getWebhookID() . '",
            "webhook_event": ' . $this->blob . '
        }';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gw->getApiUrl() . '/v1/notifications/verify-webhook-signature');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $gw->getBearerToken(),
        ) ); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $status = false;
        if ($code != 200) {
            SHOP_log("Error $code : $result");
            $status = false;
        } else {
            $result = @json_decode($result, true);
            if (!$result) {
                SHOP_log("Error: Code $code, Data " . print_r($result,true));
                $status = false;
            } else {
                SHOP_log("Result " . print_r($result,true), SHOP_LOG_DEBUG);
                $status = SHOP_getVar($result, 'verification_status') == 'SUCCESS' ? true : false;
            }
        }
        $this->setVerified($status);
        return $status;
    }

}
