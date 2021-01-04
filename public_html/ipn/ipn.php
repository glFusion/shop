<?php
/**
 * Generic IPN message endpoint.
 * Extracting data from $_GET, $_POST or `php://input` is left to the IPN
 * handler.
 * The only required $_GET parameter is `_gw` to specify the
 * gateway/ipn handler name.
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

$gateway = SHOP_getVar($_GET, '_gw');

if (empty($gateway)) {
    SHOP_log("Gateway not specified in IPN message data");
    $log_level = SHOP_LOG_ALERT;
} else {
    $log_level = SHOP_LOG_DEBUG;
}

// Debug logging
SHOP_log("Got IPN GET: " . var_export($_GET, true), $log_level);
SHOP_log("Got IPN POST: " . var_export($_POST, true), $log_level);
SHOP_log("Got php:://input: " . var_export(@file_get_contents('php://input'), true), $log_level);

// Instantiate without data.
// It's the gateway's job to retrieve from $_GET, $_POST, etc.
if (!empty($gateway)) {
    $IPN = \Shop\IPN::getInstance($gateway);
    if ($IPN) {
        $IPN->Response($IPN->Process());
    }
}

?>
