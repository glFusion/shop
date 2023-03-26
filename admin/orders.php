<?php
/**
 * Manage orders.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2023 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v1.2.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import Required glFusion libraries */
require_once('../../../lib-common.php');
use Shop\Log;

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check('shop.admin')
) {
    COM_404();
    exit;
}

require_once('../../auth.inc.php');
USES_lib_admin();
$Request = Shop\Models\Request::getInstance();
$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($Request['msg'])) $msg[] = $Request->getString('msg');

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$expected = array(
    // Actions to perform
    'delete', 'oi_update', 'oi_delete', 'uid_update',
    // Views to display
    'packinglist', 'edit', 'shipments', 'list', 'order',
    'oi_edit',
);
list($action, $actionval) = $Request->getAction($expected, 'orders');
$view = $action;

switch ($action) {
case 'delete':
    $S = new Shop\Shipment($actionval);
    $S->Delete();
    $url = SHOP_getUrl(SHOP_ADMIN_URL . '/shipments.php');
    echo COM_refresh($url);
    break;

case 'updstatus':
    $newstatus = $Request->getString('newstatus');
    if ($newstatus == '') {
        break;
    }
    $orders = $Request->getArray('orders');
    $oldstatus = $Request->getArray('oldstatus');
    foreach ($orders as $id=>$order_id) {
        if (!isset($oldstatus[$order_id]) || $oldstatus[$order_id] != $newstatus) {
            $Order = Shop\Order::getInstance($order_id);
            if (!$Order->isNew) {
                $Order->updateStatus($newstatus);
                Log::info("Updated order $order_id from {$oldstatus[$order_id]} to $newstatus");
            }
        }
    }
    $actionval = $Request->getString('run');
    if ($actionval != '') {
        $view = 'run';
    }
    break;

case 'oi_update':
    $item_id = $Request->getInt('item_number');
    $order_id = $Request->getString('order_id');
    $OI = new Shop\OrderItem($Request->getInt('oi_id'));
    if ($OI->getId() > 0) {
        $OI->setOptionsFromPOV($Request->getArray('options'));
        $OI->setExtras($Request->getArray('extras'));
        $OI->setBasePrice($Request->getFloat('price'));
        $OI->setQuantity($Request->getFloat('quantity'), $Request->getFloat('price'));
        $OI->setSku();
        $OI->Save();
        // Now update the order for totals, etc.
        $Order = new Shop\Order($order_id);
        if ($Order->getOrderID() == $order_id) {
            $Order->Save();
        }
    }
    echo COM_refresh(Shop\Config::get('admin_url') . '/orders.php?order=' . $order_id);
    break;

case 'oi_delete':
    $OI = new Shop\OrderItem($actionval);
    if ($OI->getId() == $actionval) {
        $OI->Delete();
        $Order = new Shop\Order($OI->getOrderId());
        if ($Order->getOrderID() == $OI->getOrderId()) {
            $Order->Save();
        }
    }
    echo COM_refresh(Shop\Config::get('admin_url') . '/orders.php?order=' . $OI->getOrderId());
    break;

case 'uid_update':
    $Order = Shop\Order::getInstance($Request->getString('order_id'));
    $Order->setUid($Request->getInt('uid'))->Save();
    echo COM_refresh(Shop\Config::get('admin_url') . '/orders.php?order=' . $Request->getString('order_id'));
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'order':
    $Order = \Shop\Order::getInstance($actionval);
    if (!$Order->isNew()) {
        $V = (new \Shop\Views\Invoice)->withOrderId($actionval)->setAdmin(true);
        $content .= Shop\Menu::viewOrder($view, $Order);
        $content .= $V->Render();
    } else {
        $content .= SHOP_errorMessage($LANG_SHOP['item_not_found'], 'error');
    }
    break;

case 'packinglist':
    if ($actionval == 'x') {
        $shipments = $Request->getArray('shipments');
    } else {
        $shipments = $actionval;
    }
    $PL = new Shop\Views\PackingList();
    $PL->withOutput('pdf')->withShipmentId($shipments)->Render();
    break;

case 'list':
case 'orders':
default:
    $content .= Shop\Menu::adminOrders($view);
    $R = \Shop\Report::getInstance('orderlist');
    if ($R !== NULL) {
        $R->setAdmin(true);
        $R->setParams();
        $content .= $R->Render();
    }
    break;
}
$display = COM_siteHeader();
$display .= \Shop\Menu::Admin($view);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;
