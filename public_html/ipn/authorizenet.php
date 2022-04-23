<?php
/**
 * IPN endpoint for Authorize.Net. Included for backwards compatibility.
 * Authorize.Net does not support $_GET arguments in the webhook.
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

/** Import core glFusion functions */
require_once '../../lib-common.php';
use Shop\Log;

$gw_name = 'authorizenet';
if (empty($gw_name)) {
    $log_level = Log::ALERT;
    Log::write('shop_system', $log_level, "Gateway not specified in Webhook message data");
} else {
    $log_level = Log::DEBUG;
    Log::write('shop_system', $log_level, "Received $gw_name Webhook:");
}

// Log everything before instantiating the webhook handler in case
// something goes wrong later.
Log::write('shop_system', $log_level, "Got Webhook Headers: " . var_export($_SERVER,true));
Log::write('shop_system', $log_level, "Got Webhook GET: " . var_export($_GET, true));
Log::write('shop_system', $log_level, "Got Webhook POST: " . var_export($_POST, true));
Log::write('shop_system', $log_level, "Got php:://input: " . var_export(@file_get_contents('php://input'), true));

// Get the complete IPN message prior to any processing
if (!empty($gw_name)) {
    $WH = Shop\Webhook::getInstance($gw_name);
    if ($WH && $WH->Verify()) {
        $WH->Dispatch();
    } else {
        Log::write('shop_system', $log_level, "Webhook verification failed for $gw_name");
    }
}
echo "Completed.";
exit;
