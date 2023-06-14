<?php
/**
 * Manage payments.
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
    'delete', 'savepayment', 'delpayment',
    // Views to display
    'edit', 'payments', 'list', 'newpayment', 'pmtdetail', 'ipndetail',
    'webhooks',
);
list($action, $actionval) = $Request->getAction($expected, 'payments');
$view = $action;
$mainview = '';   // default main menu selection

switch ($action) {
case 'savepayment':
    $Pmt = Shop\Payment::getInstance($Request->getInt('pmt_id'));
    $Pmt->setAmount($Request->getFloat('amount'))
        ->setMethod($Request->getString('gw_id'))
        ->setGateway($Request->getString('gw_id'))
        ->setRefID($Request->getString('ref_id'))
        ->setOrderID($Request->getString('order_id'))
        ->setUID((int)$_USER['uid'])
        ->setIsMoney($Request->getInt('is_money', 0))
        ->setComment($Request->getString('comment'));
    $Pmt->Save();
    echo COM_refresh(SHOP_ADMIN_URL . '/payments.php?payments=' . $Request->getString('order_id');
    break;

case 'delete':
case 'delpayment':
    Shop\Payment::delete($actionval);
    echo COM_refresh(SHOP_ADMIN_URL . '/payments.php?payments=' . $Request->getString('order_id'));
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'newpayment':
    $Pmt = new Shop\Payment;
    $Pmt->setOrderID($actionval);
    $content .= $Pmt->pmtForm();
    break;

case 'ipndetail':
    $val = $Request->getString('id', $Request->getString('txn_id', NULL));
    if ($val !== NULL) {
        $content .= \Shop\Report::getInstance('ipnlog')->RenderDetail($val, $key);
        break;
    }
    break;

case 'pmtdetail':
    $val = $Request->getInt('pmt_id');
    if ($val > 0) {
        $content .= \Shop\Report::getInstance('payment')->RenderDetail($val);
        break;
    }
    break;

case 'webhooks':
    $R = Shop\Report::getInstance('ipnlog');
    if ($actionval != 'x') {
        $R->withOrderId($actionval);
        $Order = Shop\Order::getInstance($actionval);
        $content .= Shop\Menu::viewOrder($view, $Order);
    } else {
        $content .= Shop\Menu::adminOrders($view);
    }
    $content .= $R->Render();
    break;

case 'list':
case 'payments':
default:
    // View payments on an order
    if ($actionval != 'x') {
        $Order = Shop\Order::getInstance($actionval);
        $content .= Shop\Menu::viewOrder($view, $Order);
    } else {
        $content .= Shop\Menu::adminOrders($view);
    }
    $content .= Shop\Payment::adminList($actionval);
    break;
}
$display = COM_siteHeader();
$display .= \Shop\Menu::Admin($mainview);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;
