<?php
/**
 * Manage orders.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner
 * @package     shop
 * @version     v1.3.1
 * @since       v1.2.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import Required glFusion libraries */
require_once('../../../lib-common.php');

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

$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($_REQUEST['msg'])) $msg[] = $_REQUEST['msg'];

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = 'orders';
$actionval = 'x';
$expected = array(
    // Actions to perform
    'delete',
    // Views to display
    'packinglist', 'edit', 'shipments', 'list', 'order',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}
$view = $action;

switch ($action) {
case 'delete':
    $S = new Shop\Shipment($actionval);
    $S->Delete();
    $url = SHOP_getUrl(SHOP_ADMIN_URL . '/shipments.php');
    COM_refresh($url);
    break;

case 'updstatus':
    $newstatus = SHOP_getVar($_POST, 'newstatus');
    if ($newstatus == '') {
        break;
    }
    $orders = SHOP_getVar($_POST, 'orders', 'array');
    $oldstatus = SHOP_getVar($_POST, 'oldstatus', 'array');
    foreach ($orders as $id=>$order_id) {
        if (!isset($oldstatus[$order_id]) || $oldstatus[$order_id] != $newstatus) {
            $Order = Shop\Order::getInstance($order_id);
            if (!$Order->isNew) {
                $Order->updateStatus($newstatus);
                SHOP_log("Updated order $order_id from {$oldstatus[$order_id]} to $newstatus", SHOP_LOG_INFO);
            }
        }
    }
    $actionval = SHOP_getVar($_REQUEST, 'run');
    if ($actionval != '') {
        $view = 'run';
    }
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'order':
    $order = \Shop\Order::getInstance($actionval);
    $V = (new \Shop\Views\Invoice)->withOrderId($actionval)->setAdmin(true);
    $content .= Shop\Menu::viewOrder($view, $order);
    $content .= $V->Render();
    break;

case 'packinglist':
    if ($actionval == 'x') {
        $shipments = SHOP_getVar($_POST, 'shipments', 'array');
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
        // Params usually from GET but could be POSTed time period
        $R->setParams($_REQUEST);
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
