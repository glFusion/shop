<?php
/**
 * Order confirmation gateway.
 * Some payment gateways require processing before sending the buyer to the
 * payment page.
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
require_once '../lib-common.php';
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}
use Shop\Models\Token;
use Shop\Models\Request;
$Request = Request::getInstance();

// Get the order and make sure it's valid. Also it must not be "final".
$order_id = $Request->getString('order_id');

if (!empty($order_id)) {
    $secret = $Request->getString('secret');
    if (!empty($secret)) {
        $secret = Token::decrypt($secret);
    }
    $Order = Shop\Order::getInstance($order_id);
    if (!$Order->isNew() && $Order->getSecret() == $secret) {
        $GW = Shop\Gateway::getInstance($Order->getPmtMethod());
        $Order->setFinal();
        $redirect = $GW->confirmOrder($Order);
        if (!empty($redirect)) {
            echo COM_refresh($redirect);
        } else {
            echo COM_refresh(Shop\Config::get('url'));
        }
    }
} else {
    SHOP_setMsg("There was an error processing your order");
    echo COM_refresh(Shop\Config::get('url') . '/cart.php');
}
