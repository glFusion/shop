<?php
/**
 * IPN processor for Stripe notifications.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v0.7.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once '../../lib-common.php';

// Instantiate the gateway to load the needed API key.
$GW = Shop\Gateway::getInstance('stripe');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$payload = @file_get_contents('php://input');
SHOP_log('Recieved Stripe IPN: ' . print_r($payload, true), SHOP_LOG_DEBUG);

$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $GW->getWebhookSecret()
    );
    COM_errorLog("created event");
} catch(\UnexpectedValueException $e) {
    // Invalid payload
    SHOP_log("Unexpected Value received from Stripe");
    http_response_code(400); // PHP 5.4 or greater
    exit();
} catch(\Stripe\Error\SignatureVerification $e) {
    // Invalid signature
    SHOP_log("Invalid Stripe signature received");
    http_response_code(400); // PHP 5.4 or greater
    exit();
}

// Handle the checkout.session.completed event
if ($event->type == 'checkout.session.completed') {
    // Fulfill the purchase...
    $ipn = \Shop\IPN::getInstance('stripe', $event);
    if ($ipn) {
        $ipn->Process();
    } else {
        SHOP_log('Stripe IPN processor not found');
    }
}

http_response_code(200); // PHP 5.4 or greater

?>
