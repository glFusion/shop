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
    'delete',
    // Views to display
    'packinglist', 'edit', 'shipments', 'list',
);
list($action, $actionval) = $Request->getAction($expected, 'shipments');
$view = $action;

switch ($action) {
case 'delete':
    $S = new Shop\Shipment($actionval);
    $S->Delete();
    $url = SHOP_getUrl(SHOP_ADMIN_URL . '/shipments.php');
    echo COM_refresh($url);
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'packinglist':
    $PL = new Shop\Views\PackingList();
    $PL->withOutput('pdf')->withShipmentId($actionval)->Render();
    break;

case 'edit':
    $shipment_id = (int)$actionval;
    if ($shipment_id > 0) {
        if (isset($Request['ret_url'])) {
            SHOP_setUrl($Request->getString('ret_url'));
        }
        $S = new Shop\Shipment($shipment_id);
        $V = new Shop\Views\ShipmentForm($S->getOrderID());
        $content = $V->withShipmentId($shipment_id)->Render();
    }
    break;

case 'list':
case 'shipments':
default:
    // View admin list of shipments
    SHOP_setUrl();
    if (!empty($actionval)) {   // getting shipments for a single order
        $Order = Shop\Order::getInstance($actionval);
        $content .= Shop\Menu::viewOrder($view, $Order);
    } else {                    // getting all shipments
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
