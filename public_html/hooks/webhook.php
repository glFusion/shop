<?php
/**
 * Webhook endpoint.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';
use Shop\Log;

$gw_name = SHOP_getVar($_GET, '_gw');
if (empty($gw_name)) {
    $log_level = Log::ALERT;
    Log::write('shop_system', Log::ALERT, "Gateway not specified in Webhook message data");
} else {
    $log_level = Log::DEBUG;
    Log::write('shop_system', Log::DEBUG, "Received $gw_name Webhook:");
}

// Log everything before instantiating the webhook handler in case
// something goes wrong later.
Log::write('shop_system', $log_level, "Got Webhook Headers: " . var_export($_SERVER,true));
Log::write('shop_system', $log_level, "Got Webhook GET: " . var_export($_GET, true));
Log::write('shop_system', $log_level, "Got Webhook POST: " . var_export($_POST, true));
Log::write('shop_system', $log_level, "Got php:://input: " . var_export(@file_get_contents('php://input'), true));

// Get the complete IPN message prior to any processing
$status = true;
if (!empty($gw_name)) {
    $WH = Shop\Webhook::getInstance($gw_name);
    if ($WH) {
        if ($WH->Verify()) {
            $status = $WH->Dispatch();
        } else {
            Log::write('shop_system', Log::ERROR, "Webhook verification failed for $gw_name");
            $status = false;
        }
        $WH->redirectAfterCompletion();
    } else {
        Log::write('shop_system', Log::ERROR, "Invalid gateway '$gw_name' requested for webhook");
        $status = false;
    }
}
if ($status) {
    header("HTTP/1.0 200 OK");
    echo "Completed.";
} else {
    header("HTTP/1.0 500 Internal Error");
    echo "An error occurred";
}
exit;
