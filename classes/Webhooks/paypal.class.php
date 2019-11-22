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

}

?>
