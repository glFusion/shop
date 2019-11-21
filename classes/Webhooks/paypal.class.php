<?php
namespace Shop\Webhooks;

class paypal extends \Shop\Webhook
{
    public function __construct($blob)
    {
        $this->setSource('paypal');
        $this->setTimestamp();
        $this->setData(json_decode($blob, true));
        if ($this->getData()) {
            $this->setID(SHOP_getVar($this->getData(), 'id'));
            $this->setEvent(SHOP_getVar($this->getData(), 'event_type'));
        }
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

    public function getEvent()
    {
        switch ($this->whEvent) {
        case 'INVOICING.INVOICE.PAID':
            return 'invoice_paid';
            break;
        default:
            return 'undefined';
            break;
        }
    }

}

?>
