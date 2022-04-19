<?php
/**
 * Webhook endpoint.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

$gw_name = SHOP_getVar($_GET, '_gw');
if (empty($gw_name)) {
    $log_level = SHOP_LOG_ALERT;
    SHOP_log("Gateway not specified in Webhook message data", $log_level);
} else {
    $log_level = SHOP_LOG_DEBUG;
    SHOP_log("Received $gw_name Webhook:", $log_level);
}

// Log everything before instantiating the webhook handler in case
// something goes wrong later.
SHOP_log("Got Webhook Headers: " . var_export($_SERVER,true), $log_level);
SHOP_log("Got Webhook GET: " . var_export($_GET, true), $log_level);
SHOP_log("Got Webhook POST: " . var_export($_POST, true), $log_level);
SHOP_log("Got php:://input: " . var_export(@file_get_contents('php://input'), true), $log_level);

// Get the complete IPN message prior to any processing
$status = true;
if (!empty($gw_name)) {
    $WH = Shop\Webhook::getInstance($gw_name);
    if ($WH) {
        if ($WH->Verify()) {
            $status = $WH->Dispatch();
        } else {
            SHOP_log("Webhook verification failed for $gw_name");
            $status = false;
        }
        $WH->redirectAfterCompletion();
    } else {
        SHOP_log("Invalid gateway '$gw_name' requested for webhook");
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
