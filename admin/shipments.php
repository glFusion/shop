<?php
/**
 * Manage shipments.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner
 * @package     shop
 * @version     v1.2.3
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

$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($_REQUEST['msg'])) $msg[] = $_REQUEST['msg'];

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = 'shipments';
$actionval = 'x';
$expected = array(
    // Actions to perform
    'delete',
    // Views to display
    'packinglist', 'edit', 'shipments', 'list',
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
    echo COM_refresh($url);
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
                Log::write('shop_system', Log::INFO, "Updated order $order_id from {$oldstatus[$order_id]} to $newstatus");
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
case 'packinglist':
    if ($actionval == 'x') {
        $shipments = SHOP_getVar($_POST, 'shipments', 'array');
    } else {
        $shipments = $actionval;
    }
    $PL = new Shop\Views\PackingList();
    $PL->withOutput('pdf')->withShipmentId($shipments)->Render();
    break;

case 'edit':
    $shipment_id = (int)$actionval;
    if ($shipment_id > 0) {
        if (isset($_REQUEST['ret_url'])) {
            SHOP_setUrl($_REQUEST['ret_url']);
        }
        $S = new Shop\Shipment($shipment_id);
        $V = new Shop\Views\ShipmentForm($S->getOrderID());
        $content = $V->withShipmentId($shipment_id)->Render();
        //$content = $V->Render($action);
    }
    break;

case 'list':
case 'shipments':
default:
    // View admin list of shipments
    SHOP_setUrl();
    if ($actionval != 'x') {
        $Order = Shop\Order::getInstance($actionval);
        $content .= Shop\Menu::viewOrder($view, $Order);
    } else {
        $content .= Shop\Menu::adminOrders($view);
    }
    $content .= Shop\Shipment::adminList($actionval);
    if ($view == 'shipments') {
        $view = 'orders';       // to set the active top-level menu
    }
    break;
}
$display = COM_siteHeader();
$display .= \Shop\Menu::Admin('reports');
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;

?>
