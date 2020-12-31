<?php
/**
 * Webhook handler for Paypal.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

$gw_name = SHOP_getVar($_GET, '_gw');
if (empty($gw_name)) {
    SHOP_log("Gateway not specified in Webhook message data");
    SHOP_log("Got Webhook GET: " . var_export($_GET, true));
    SHOP_log("Got Webhook POST: " . var_export($_POST, true));
    SHOP_log("Got php:://input: " . var_export(@file_get_contents('php://input'), true));
    $log_level = SHOP_LOG_ALERT;
    exit;
}

// Get the complete IPN message prior to any processing
SHOP_log("Recieved $gw_name Webhook:", SHOP_LOG_DEBUG);
$WH = Shop\Webhook::getInstance($gw_name);
if ($WH->Verify()) {
    $WH->Dispatch();
}
echo "Completed.";
exit;
?>
