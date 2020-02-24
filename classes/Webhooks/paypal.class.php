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
namespace Shop\Webhooks;
use Shop\Payment;
use Shop\Order;

/**
 * Paypal webhook class.
 * @package shop
 */
class paypal extends \Shop\Webhook
{
    /**
     * Set up the webhook data from the supplied JSON blob.
     *
     * @param   string  $blob   JSON data
     */
    public function __construct($blob)
    {
        $this->setSource('paypal');
        $this->setHeaders();
        $this->setTimestamp();
        $this->setData(json_decode($blob, true));
        //COM_errorLog('HEADERS: ' . var_export($this->getHeader(),true));
        //COM_errorLog('DATA: ' . $blob);

        // Check that the blob was decoded successfully.
        // If so, extract the key fields and set Webhook variables.
        $data = $this->getData();
        if ($data) {     // Indicates that the blob was decoded
            $this->setID(SHOP_getVar($this->getData(), 'id'));
            $this->setEvent(SHOP_getVar($this->getData(), 'event_type'));
        }
    }


    /**
     * Perform the necessary actions based on the webhook.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
            $resource = SHOP_getVar($this->getData(), 'resource', 'array', NULL);
            if  ($resource) {
                $invoice = SHOP_getVar($resource, 'invoice', 'array', NULL);
                if ($invoice) {
                    $detail = SHOP_getVar($invoice, 'detail', 'array', NULL);
                    if ($detail) {
                        $this->setOrderID(SHOP_getVar($detail, 'reference'));
                    }
                }
            }
        switch ($this->getEvent()) {
        case self::EV_PAYMENT:
            if ($invoice) {
                $payments = SHOP_getVar($invoice, 'payments', 'array', NULL);
                if ($payments) {
                    // Get just the latest payment.
                    // If there are multiple payments for the order, all are included.
                    $payment = array_pop($payments['transactions']);
                    $Pmt = new Payment;
                    $Pmt->setRefID($this->getID())
                        ->setAmount($payment['amount']['value'])
                        ->setGateway($this->getSource())
                        ->setMethod($payment['method'])
                        ->setComment($payment['note'])
                        ->setOrderID($this->getOrderID());
                    return $Pmt->Save();
                }
            }
            break;

        case self::EV_CREATED:
            COM_errorLog("Invlice created for {$this->getOrderID()}");
            $Order = Order::getInstance($this->getOrderID());
            if (!$Order->isNew()) {
                $Order->updateStatus('invoiced');
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
            return self::EV_PAYMENT;
            break;
        case 'INVOICING.INVOICE.CREATED':
            return self::EV_CREATED;
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
        //COM_errorLog("In webhook verify");
        $gw = \Shop\Gateway::getInstance($this->getSource());
        //COM_errorLog("GW is " . print_r($gw,true));
//        var_dump($this->getHeader());die;
        $body = array(
            'transmission_id' => $this->getHeader('Paypal-Transmission-Id'),
            'transmission_time' => $this->getHeader('Paypal-Transmission-Time'),
            'cert_url' => $this->getHeader('Paypal-Cert-Url'),
            'auth_algo' => $this->getHeader('Paypal-Auth-Algo'),
            'transmission_sig' => $this->getHeader('Paypal-Transmission-Sig'),
            'webhook_id' => '7AL053045J1030934',
            //'webhook_id' => $gw->getWebhookID(), //'7AL053045J1030934',
            'webhook_event' => $this->getData(),
        );
        //var_dump($body);die;
        $body = json_encode($body, JSON_UNESCAPED_SLASHES);
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
            var_dump($code);
            var_dump($result);
            $status = false;
        } else {
            $result = @json_decode($result, true);
            if (!$result) {
                COM_errorLog("Paypal WebHook verification result: Code $code, Data " . print_r($result,true));
                $status = false;
            } else {
                var_dump($result);
                $status = SHOP_getVar($result, 'verification_status') == 'SUCCESS' ? true : false;
            }
        }
        $this->setVerified($status);
        return $status;
    }

}

?>
