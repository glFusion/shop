<?php
/**
 * Interface to add a cart item from another plugin link
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions. */
require_once '../lib-common.php';

$ret_url = isset($_GET['ret']) ? $_GET['ret'] : $_SERVER['HTTP_REFERER'];
$uid = (int)$_USER['uid'];
if (!isset($_GET['item'])) {
    SHOP_log("Ajax addcartitem:: Missing Item Number", SHOP_LOG_ERROR);
    COM_refresh($ret_url);
    exit;
}

$item_number = SHOP_getVar($_GET, 'item', 'string', '');   // isset ensured above
if (empty($item_number)) {
    COM_setMsg('Missing item number', 'error');
    COM_refresh($ret_url);
}
$sku = SHOP_getVar($_GET, 'sku', 'string');
$Cart = Shop\Cart::getInstance();
$qty = SHOP_getVar($_GET, 'q', 'integer', 1);
$price = SHOP_getVar($_GET, 'p', 'float', NULL);

$args = array(
    'item_number'   => $item_number,     // isset ensured above
    'item_name'     => $sku,
    'short_dscp'    => SHOP_getVar($_GET, 'd', 'string', ''),
    'quantity'      => $qty,
    'options'       => SHOP_getVar($_GET, 'o', 'array', array()),
    'extras'        => array(
        'special' => SHOP_getVar($_GET, 'e', 'array', array())
    ),
    'tax'           => SHOP_getVar($_GET, 'tax', 'float', 0),
);
if ($price !== NULL) {
    $args['price'] = $price;
    $args['override'] = true;
}
$new_qty = $Cart->addItem($args);
SHOP_log("Adding $item_number, qty $new_qty", SHOP_LOG_DEBUG);
$msg = $LANG_SHOP['msg_item_added'];
if ($new_qty === false) {
    $msg = $LANG_SHOP['out_of_stock'];
} elseif ($new_qty < $qty) {
    // TODO: better handling of adjustments.
    // This really only handles changes to the initial qty.
    $msg .= ' ' . $LANG_SHOP['qty_adjusted'];
}
COM_setMsg($msg);
COM_refresh($ret_url);
