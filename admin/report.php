<?php
/**
 * Run reports.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2018 Lee Garner
 * @package     shop
 * @version     v0.7.0
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
    'updstatus',
    // Views to display
    'pdfpl', 'pdforder', 'shipment_pl',
    'configure', 'run', 'report', 'list',
);
list($action, $actionval) = $Request->getAction($expected);
$view = 'list';

switch ($action) {
case 'updstatus':
    echo "remove reports.php updatestatus";die;
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
                Log::write('shop_system', Log::INFO, "Updated order $order_id from {$oldstatus[$order_id]} to $newstatus");
            }
        }
    }
    $actionval = $Request->getString('run');
    if ($actionval != '') {
        $view = 'run';
    }
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'configure':
    $R = \Shop\Report::getInstance($actionval);
    if ($R !== NULL) {
        $R->setAdmin(true);
        if ($R->hasForm()) {
            $content .= $R->Configure();
        } else {
            $content .= $R->Render();
        }
    }
    break;

case 'run':
    $R = \Shop\Report::getInstance($actionval);
    if ($R !== NULL) {
        $R->setAdmin(true);
        $R->setParams();
        $content .= $R->Render();
    }
    break;

case 'pdfpl':
    if ($actionval == 'x') {
        $orders = $Request->getArray('orders');
    } else {
        $orders = $actionval;
    }
    $View = new Shop\Views\Invoice;
    $View
        ->withOrderIds($orders)
        ->asPackingList()
        ->withOutput('pdf')
        ->Render();
    break;
case 'pdforder':
    if ($actionval == 'x') {
        $orders = $Request->getArray('orders');
    } else {
        $orders = $actionval;
    }
    $View = new Shop\Views\Invoice;
    $View->withOrderIds($orders)->withOutput('pdf')->Render();
    break;
    \Shop\Order::printPDF($orders, $view);
    break;

case 'list':
default:
    $content .= \Shop\Report::getList();
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
