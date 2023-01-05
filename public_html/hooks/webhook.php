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

$gw_name = '';
COM_setArgNames(array('_gw', 'testhook'));
if (isset($_GET['_gw'])) {
    $gw_name = $_GET['_gw'];
} else {
    $gw_name = COM_getArgument('_gw');
}

if (empty($gw_name)) {
    $log_fn = 'Shop\Log::alert';
    $log_level = Log::ALERT;
    $log_fn("Gateway not specified in Webhook message data");
} else {
    $log_fn = 'Shop\Log::debug';
    $log_level = Log::DEBUG;
    $log_fn("Received $gw_name Webhook:");
}

// Log everything before instantiating the webhook handler in case
// something goes wrong later.
$log_fn("Got Webhook Headers: " . var_export($_SERVER,true));
$log_fn("Got Webhook GET: " . var_export($_GET, true));
$log_fn("Got Webhook POST: " . var_export($_POST, true));
$log_fn("Got php:://input: " . var_export(@file_get_contents('php://input'), true));

// Get the complete IPN message prior to any processing
$status = true;
if (!empty($gw_name)) {
    $WH = Shop\Webhook::getInstance($gw_name);
    if ($WH) {
        if ($WH->Verify()) {
            $status = $WH->Dispatch();
            Log::debug('Webhook Dispatch status: ' . var_export($status,true));
        } else {
            Log::error("Webhook verification failed for $gw_name");
            $status = false;
        }
        $WH->redirectAfterCompletion();
    } else {
        Log::error("Invalid gateway '$gw_name' requested for webhook");
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
