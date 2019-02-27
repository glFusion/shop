<?php
/**
 * IPN processor for Authorize.Net notifications.
 * Converts received data into a standard $_POST-style array.
 * Handles either Silent URL or Webhook notifications.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2018 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

if (empty($_POST)) {
    // Received a webhook, a single parameter string
    $json = file_get_contents('php://input');
    SHOP_debug("WEBHOOK: $json", 'debug_ipn');
    $post= json_decode($json,true);
} elseif (isset($_POST['shop_test_ipn'])) {
    // Received POST, testing only. Must already be complete
    $post = $_POST;
    SHOP_debug("TEST: " . var_export($post,true), 'debug_ipn');
} else {
    // Received a Silent URL post. Convert to webhook format.
    SHOP_debug("Silent URL: " . var_export($_POST,true), 'debug_ipn');
    switch (SHOP_getVar($_POST, 'x_type')) {
    case 'auth_capture':
        $eventtype = 'net.authorize.payment.authcapture.created';
        break;
    default:
        $eventtype = 'undefined';
        break;
    }
    $post = array(
        'notificationId'    => 'unused',
        'eventtype'     => $eventtype,
        'eventDate'     => date('Y-m-d\TH:i:s\Z'),
        'webhookId'     => 'unused',
        'payload'       => array(
            'responseCode'  => SHOP_getVar($_POST, 'x_response_code', 'integer'),
            'authCode'  => SHOP_getVar($_POST, 'x_auth_code'),
            'avsResponse'   => SHOP_getVar($_POST, 'x_avs_code'),
            'authAmount'    => SHOP_getVar($_POST, 'x_amount', 'float'),
            'entityName'    => 'transaction',
            'id'        => SHOP_getVar($_POST, 'x_trans_id'),
        ),
    );
}

if ($_SHOP_CONF['debug_ipn'] == 1) {
    // Get the complete IPN message prior to any processing
    COM_errorLog("Recieved IPN:", 1);
    COM_errorLog(var_export($post, true), 1);
}

// Process IPN request
$ipn = \Shop\IPN::getInstance('authorizenet', $post);
$ipn->Process();

// Finished (this isn't necessary...but heck...why not?)
echo "Thanks";

?>
