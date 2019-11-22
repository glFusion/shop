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
        $this->setTimestamp();
        $this->setData(json_decode($blob, true));

        // Check that the blob was decoded successfully.
        // If so, extract the key fields and set Webhook variables.
        $data = $this->getData();
        if ($data) {        // Indicates that the blob was decoded
            $this->setID(SHOP_getVar($this->getData(), 'id'));
            $this->setEvent(SHOP_getVar($this->getData(), 'event_type'));
            $resource = SHOP_getVar($this->getData(), 'resource', 'array', NULL);
            if  ($resource) {
                $invoice = SHOP_getVar($resource, 'invoice', 'array', NULL);
                if ($invoice) {
                    $detail = SHOP_getVar($invoice, 'detail', 'array', NULL);
                    if ($detail) {
                        $this->setOrderID(SHOP_getVar($detail, 'invoice_number'));
                    }
                    $payments = SHOP_getVar($invoice, 'payments', 'array', NULL);
                    if ($payments) {
                        $this->setPayment($payments['paid_amount']['value']);
                    }
                }
            }
        }
        $this->setHeaders();
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
        $gw = \Shop\Gateway::getInstance($this->getSource());
        $body = array(
            'transmission_id' => $this->getHeader('Paypal-Transmission-Id'),
            'transmission_time' => $this->getHeader('Paypal-Transmission-Time'),
            'cert_url' => $this->getHeader('Paypal-Cert-Url'),
            'auth_algo' => $this->getHeader('Paypal-Auth-Algo'),
            'transmission_sig' => $this->getHeader('Paypal-Transmission-Sig'),
            'webhook_id' => $gw->getWebhookID(), //'7AL053045J1030934',
            'webhook_event' => $this->getData(),
        );
        $body = json_encode($body);
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
        if ($code != 200) {
            $status = false;
        } else {
            $result = @json_decode($result, true);
            if (!$result) {
                COM_errorLog("Paypal WebHook verification result: Code $code, Data " . print_r($result,true));
                $status = false;
            } else {
                $status - SHOP_getVar($result, 'verification_status') == 'SuCCESS' ? true : false;
            }
        }
        $this->setVerified($status);
        return $status;
    }

}

?>
