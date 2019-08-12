<?php
/**
 * Common user-facing AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions. */
require_once '../lib-common.php';

$uid = (int)$_USER['uid'];
$action = SHOP_getVar($_GET, 'action');
switch ($action) {
case 'delAddress':          // Remove a shipping address
    if ($uid < 2) break;    // Not available to anonymous
    $U = Shop\UserInfo::getInstance($uid);
    $U->deleteAddress($_GET['id']);
    break;

case 'getAddress':
    if ($uid < 2) break;
    $A = Shop\UserInfo::getInstance($uid)->getAddress($_GET['id']);
    //$res = DB_query("SELECT * FROM {$_TABLES['shop.address']} WHERE id=$id",1);
    //$A = DB_fetchArray($res, false);
    break;

case 'addcartitem':
    if (!isset($_POST['item_number'])) {
        SHOP_log("Ajax addcartitem:: Missing Item Number", SHOP_LOG_ERROR);
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }
    $item_number = $_POST['item_number'];     // isset ensured above
    $P = Shop\Product::getByID($item_number);
    if ($P->isNew) {
        // Invalid product ID passed
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }
    $item_name = SHOP_getVar($_POST, 'item_name', 'string', $P->getName());
    $Cart = Shop\Cart::getInstance();
    $nonce = $Cart->makeNonce($item_number . $item_name);
    if (!isset($_POST['nonce']) || $_POST['nonce'] != $nonce) {
        SHOP_log("Bad nonce: {$_POST['nonce']} for cart {$Cart->order_id}, should be $nonce", SHOP_LOG_ERROR);
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }

    $unique = SHOP_getVar($_POST, '_unique', 'integer', $P->isUnique());
    if ($unique && $Cart->Contains($_POST['item_number']) !== false) {
        // Do nothing if only one item instance may be added
        break;
    }
    $args = array(
        'item_number'   => $item_number,     // isset ensured above
        'item_name'     => $item_name,
        'short_dscp'    => SHOP_getVar($_POST, 'short_dscp', 'string', $P->getDscp()),
        'quantity'      => SHOP_getVar($_POST, 'quantity', 'int', 1),
        'price'         => $P->getPrice(),
        'options'       => SHOP_getVar($_POST, 'options', 'array'),
        'extras'        => SHOP_getVar($_POST, 'extras', 'array'),
        'tax'           => SHOP_getVar($_POST, 'tax', 'float'),
    );
    $Cart->addItem($args);
    $A = array(
        'content' => phpblock_shop_cart_contents(),
        'statusMessage' => $LANG_SHOP['msg_item_added'],
        'ret_url' => isset($_POST['_ret_url']) && !empty($_POST['_ret_url']) ?
                $_POST['_ret_url'] : '',
        'unique' => $unique ? true : false,
    );
    echo json_encode($A);
    exit;
    break;

case 'finalizecart':
    $cart_id = SHOP_getVar($_POST, 'cart_id');
    $status = Shop\Cart::setFinal($cart_id);
    $A = array(
        'status' => $status,
    );
    echo json_encode($A);
    exit;
    break;

case 'redeem_gc':
    if (COM_isAnonUser()) {
        $A = array(
            'statusMessage' => $LANG_SHOP['gc_need_acct'],
            'html' => '',
            'status' => false,
        );
    } else {
        $code = SHOP_getVar($_POST, 'gc_code');
        $uid = $_USER['uid'];
        list($status, $status_msg) = Shop\Products\Coupon::Redeem($code, $uid);
        $gw = Shop\Gateway::getInstance('_coupon');
        $gw_radio = $gw->checkoutRadio($status == 0 ? true : false);
        $A = array (
            'statusMessage' => $status_msg,
            'html' => $gw_radio,
            'status' => $status,
        );
    }
    echo json_encode($A);
    exit;
default:
    // Missing action, nothing to do
    break;
}

?>
