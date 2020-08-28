<?php
/**
 * Square Webhook class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Webhooks;
use Shop\Payment;
use Shop\Order;
use Shop\Gateway;
use Shop\Currency;
use Shop\Models\OrderState;


/**
 * Square webhook class.
 * @package shop
 */
class square extends \Shop\Webhook
{
    private $body = '';

    /**
     * Set up the webhook data from the supplied JSON blob.
     *
     * @param   string  $blob   JSON data
     */
    public function __construct($blob)
    {
        $this->setSource('square');
        $this->setHeaders();
        $this->setTimestamp();
        $this->setData(json_decode($blob, true));
        $this->body = $blob;    // Need the original for verification

        // Check that the blob was decoded successfully.
        // If so, extract the key fields and set Webhook variables.
        $data = $this->getData();
        if ($data) {     // Indicates that the blob was decoded
            if (isset($data['data']) && is_array($data['data'])) {
                $this->setID(SHOP_getVar($data, 'event_id'));
                $this->setEvent(SHOP_getVar($data, 'type'));
            }
        }
    }



    /**
     * Perform the necessary actions based on the webhook.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
        $retval = false;        // be pessimistic

        $data = SHOP_getVar($this->getData(), 'data', 'array', NULL);
        if  ($data) {
            $object = SHOP_getVar($data, 'object', 'array', NULL);
            if (!$object) {
                return false;
            }
        }
        $GW = Gateway::getInstance($this->getSource());
        if (!$GW) {
            return false;
        }

        switch ($this->getEvent()) {
        case self::EV_INV_PAYMENT:
            $invoice = SHOP_getVar($object, 'invoice', 'array', NULL);
            if ($invoice) {
                $ord_id = SHOP_getVar($invoice, 'invoice_number', 'string', NULL);
                $status = SHOP_getVar($invoice, 'status', 'string', NULL);
                if ($ord_id && $status == 'PAID') {
                    $Order = Order::getInstance($ord_id);
                    if (!$Order->isNew()) {
                        $this->setOrderID($ord_id);
                        $pmt_req = SHOP_getVar($invoice, 'payment_requests', 'array', NULL);
                        if ($pmt_req && isset($pmt_req[0])) {
                            $total_money = SHOP_getVar($pmt_req[0], 'total_completed_amount_money', 'array', NULL);
                            if ($total_money) {
                                $amt = SHOP_getVar($total_money, 'amount', 'string', NULL);
                                $cur_code = SHOP_getVar($total_money, 'currency', 'string', NULL);
                                $amt_paid = Currency::getInstance($cur_code)->fromInt($amt);
                                $this->logIPN();
                                $Pmt = new Payment;
                                $Pmt->setRefID($this->getID())
                                    ->setAmount($amt_paid)
                                    ->setGateway($this->getSource())
                                    ->setMethod("Online")
                                    ->setOrderID($this->getOrderID());
                                $retval = $Pmt->Save();
                            }
                        }
                    }
                }
            }
            break;
        case self::EV_PAYMENT:
            break;  // not currently enabled
            $payment = SHOP_getVar($object, 'payment', 'array', NULL);
            if ($payment) {
                $status = SHOP_getVar($payment, 'status', 'string', NULL);
                $amt_money = SHOP_getVar($payment, 'amount_money', 'array', NULL);
                if (!$amt_money) {
                    return false;
                }
                $order_id = SHOP_getVar($payment, 'order_id', 'string', NULL);
                if (!$order_id) {
                    return false;
                }
                $sqOrder = $GW->getOrder($order_id);
                COM_errorLog($sqOrder);die;
            }
            break;

        case self::EV_INV_CREATED:
            $invoice = SHOP_getVar($object, 'invoice', 'array', NULL);
            if ($invoice) {
                $inv_num = SHOP_getVar($invoice, 'invoice_number', 'string', NULL);
                $sq_ord_id = SHOP_getVar($invoice, 'order_id', 'string', NULL);
                if (!empty($inv_num)) {
                    $Order = Order::getInstance($inv_num);
                    if (!$Order->isNew()) {
                        $this->setOrderID($inv_num);
                        SHOP_log("Invoice created for {$this->getOrderID()}", SHOP_LOG_DEBUG);
                        $Order->updateStatus(OrderState::INVOICED);
                        $retval = true;
                    } else {
                        SHOP_log("Order number '$inv_num' not found for Square invoice");
                    }
                } 
            }
            break;
        }
        return $retval;
    }


    /**
     * Get the standard event type string based on the webhook-specific string.
     *
     * @return  string      Standard string for the plugin to use
     */
    public function getEvent()
    {
        switch ($this->whEvent) {
        case 'invoice.payment_made':
            return self::EV_INV_PAYMENT;
            break;
        case 'payment':
            return self::EV_PAYMENT;
            break;
        case 'invoice.created':
            return self::EV_INV_CREATED;
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
        global $_CONF;

        $gw = \Shop\Gateway::getInstance($this->getSource());
        $notificationSignature = $this->getHeader('X-Square-Signature');
        $notificationUrl = $_CONF['site_url'] . '/shop/hooks/square.php';
        $stringToSign = $notificationUrl . $this->body;
        $webhookSignatureKey = $gw->getConfig('webhook_sig_key');

        // Generate the HMAC-SHA1 signature of the string
        // signed with your webhook signature key
        $hash = hash_hmac('sha1', $stringToSign, $webhookSignatureKey, true);
        $generatedSignature = base64_encode($hash);
        // Compare HMAC-SHA1 signatures.
        return hash_equals($generatedSignature, $notificationSignature);
    }

}

?>
