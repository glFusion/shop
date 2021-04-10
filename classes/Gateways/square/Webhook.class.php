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
namespace Shop\Gateways\square;
use Shop\Payment;
use Shop\Order;
use Shop\Gateway;
use Shop\Currency;
use Shop\Models\OrderState;


/**
 * Square webhook class.
 * @package shop
 */
class Webhook extends \Shop\Webhook
{
    /**
     * Set up the webhook data from the supplied JSON blob.
     *
     * @param   string  $blob   JSON data
     */
    public function __construct($blob='')
    {
        $this->setSource('square');
        // Load the payload into the blob property for later use in Verify().
        if (isset($_POST['headers'])) {
            $this->blob = base64_decode($_POST['vars']);
            $this->setHeaders(json_decode(base64_decode($_POST['headers']),true));
        } else {
            $this->blob = file_get_contents('php://input');
            $this->setHeaders(NULL);
        }
        SHOP_log('Got Square Webhook: ' . $this->blob, SHOP_LOG_DEBUG);
        SHOP_log('Got Square Headers: ' . var_export($_SERVER,true), SHOP_LOG_DEBUG);
        $this->setTimestamp();
        $this->setData(json_decode($this->blob));
    }


    /**
     * Perform the necessary actions based on the webhook.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
        // Be optimistic. Also causes a synthetic 200 return for unhandled events.
        $retval = true;
        $object = $this->getData()->data->object;
        if (!$object) {
            return false;
        }

        // If not unique, return true since it's doesn't need to be resent
        if (!$this->isUniqueTxnId()) {
            return true;
        }

        switch ($this->getEvent()) {
        case 'invoice.payment_made':
            break;      // deprecated
            $invoice = SHOP_getVar($object, 'invoice', 'array', NULL);
            if ($invoice) {
                $ord_id = SHOP_getVar($invoice, 'invoice_number', 'string', NULL);
                $status = SHOP_getVar($invoice, 'status', 'string', NULL);
                if ($ord_id) {
                    $this->setOrderID($ord_id);
                    $Order = Order::getInstance($ord_id);
                    if (!$Order->isNew()) {
                        $pmt_req = SHOP_getVar($invoice, 'payment_requests', 'array', NULL);
                        if ($pmt_req && isset($pmt_req[0])) {
                            $bal_due = $Order->getBalanceDue();
                            $next_pmt = SHOP_getVar($invoice, 'next_payment_amount_money', 'array', NULL);
                            $sq_bal_due = Currency::getInstance($next_pmt['currency'])
                                ->fromInt($next_pmt['amount']);
                            if ($status == 'PAID') {
                                // Invoice is paid in full by this payment
                                $amt_paid = $bal_due;
                            } elseif ($status == 'PARTIALLY_PAID') {
                                // Have to figure out the amount of this payment by deducting
                                // the "next payment amount"
                                $amt_paid = (float)($Order->getTotal() - $sq_bal_due);
                            } else {
                                $amt_paid = 0;
                            }
                            if ($amt_paid > 0) {
                                $Pmt = Payment::getByReference($this->getID());
                                if ($Pmt->getPmtID() == 0) {
                                    $Pmt->setRefID($this->getID())
                                        ->setAmount($amt_paid)
                                        ->setGateway($this->getSource())
                                        ->setMethod($this->GW->getDscp())
                                        ->setComment('Webhook ' . $this->getData()->event_id)
                                        ->setOrderID($this->getOrderID());
                                    $retval = $Pmt->Save();
                                }
                            }
                        }
                    }
                }
            }
            break;
        case 'payment':
        case 'payment.created':
            $payment = $object->payment;
            $ref_id = $payment->id;
            if ($payment) {
                $amount_money = $payment->amount_money->amount;
                if (
                    $amount_money > 0 &&
                    ( $payment->status == 'APPROVED' || $payment->status == 'COMPLETED')
                ) {
                    $currency = $payment->amount_money->currency;
                    $this_pmt = Currency::getInstance($currency)->fromInt($amount_money);
                    $order_id = $payment->order_id;
                    $sqOrder = $this->GW->getOrder($order_id);
                    $this->setOrderID($sqOrder->getResult()->getOrder()->getReferenceId());
                    $Order = Order::getInstance($this->getOrderID());
                    if (!$Order->isNew()) {
                        $Pmt = Payment::getByReference($ref_id);
                        if ($Pmt->getPmtID() == 0) {
                            $Pmt->setRefID($ref_id)
                                ->setAmount($this_pmt)
                                ->setGateway($this->getSource())
                                ->setMethod($this->GW->getDscp())
                                ->setComment('Webhook ' . $this->getID())
                                ->setComplete($payment->status == 'COMPLETED')
                                ->setStatus($payment->status)
                                ->setOrderID($this->getOrderID())
                                ->Save();
                        }
                        if ($Pmt->isComplete()) {
                            // Process if fully paid. May be "Approved" so
                            // wait for a payment.update before processing.
                            $retval = $this->handlePurchase();
                            $this->setVerified(true);
                        }
                    }
                }
                $this->logIPN();
            }
            break;

        case 'payment.updated':
            $payment = $object->payment;
            if ($payment->id) {
                $ref_id = $payment->id;
                if (
                    $payment->status == 'COMPLETED' ||
                    $payment->status == 'CAPTURED'
                ) {
                    $Pmt = Payment::getByReference($ref_id);
                    $Cur = Currency::getInstance($payment->total_money->currency);
                    if ($Pmt->getPmtID() == 0) {
                        SHOP_log("Payment not found: " . var_export($data,true));
                    } elseif (
                        $payment->total_money->amount == $Cur->toInt($Pmt->getAmount())
                    ) {
                        $Pmt->setComplete(1)->setStatus($payment->status)->Save();
                        $this->Order = Order::getInstance($Pmt->getOrderID());
                        $this->handlePurchase();    // process if fully paid
                        $this->setVerified(true);
                        $this->setOrderID($Pmt->getOrderID());
                    }
                    $retval = true;
                }
                $this->logIPN();
            }
            break;

        case 'invoice.created':
            $invoice = $object->invoice;
            if ($invoice) {
                $inv_num = $invoice->invoice_number;
                if (!empty($inv_num)) {
                    $Order = Order::getInstance($inv_num);
                    if (!$Order->isNew()) {
                        $this->setOrderID($inv_num);
                        SHOP_log("Invoice created for {$this->getOrderID()}", SHOP_LOG_DEBUG);
                        // Always OK to process for a Net-30 invoice
                        $this->handlePurchase();
                        //$terms_gw = \Shop\Gateway::create($Order->getPmtMethod());
                        //$Order->updateStatus($terms_gw->getConfig('after_inv_status'));
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
     * Verify that the webhook is valid.
     *
     * @return  boolean     True if valid, False if not.
     */
    public function Verify()
    {
        global $_CONF;

        // Check that the blob was decoded successfully.
        // If so, extract the key fields and set Webhook variables.
        $data = $this->getData();
        if (!is_object($data) || !$data->event_id) {
            return false;
        }
        $this->setID($data->event_id);
        $this->setEvent($data->type);
        $this->GW = Gateway::getInstance($this->getSource());
        if (!$this->GW) {
            return false;
        }
        return true;      // used during testing to bypass verification

        $gw = \Shop\Gateway::create($this->getSource());
        $notificationSignature = $this->getHeader('X-Square-Signature');
        $notificationUrl = $_CONF['site_url'] . '/shop/hooks/webhook.php?_gw=square';
        $stringToSign = $notificationUrl . $this->blob;
        $webhookSignatureKey = $gw->getConfig('webhook_sig_key');

        // Generate the HMAC-SHA1 signature of the string
        // signed with your webhook signature key
        $hash = hash_hmac('sha1', $stringToSign, $webhookSignatureKey, true);
        $generatedSignature = base64_encode($hash);
        SHOP_log("Generated Signature: " . $generatedSignature, SHOP_LOG_DEBUG);
        SHOP_log("Received Signature: " . $notificationSignature, SHOP_LOG_DEBUG);
        // Compare HMAC-SHA1 signatures.
        return hash_equals($generatedSignature, $notificationSignature);
    }

}
