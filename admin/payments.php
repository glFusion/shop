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
    !isset($_SHOP_CONF) ||
    !in_array($_SHOP_CONF['pi_name'], $_PLUGINS) ||
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
$action = 'payments';
$actionval = 'x';
$expected = array(
    // Actions to perform
    'delete', 'savepayment', 'delpayment',
    // Views to display
    'edit', 'payments', 'list', 'ord_pmts', 'newpayment', 'ipndetail',
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
case 'savepayment':
    $Pmt = Shop\Payment::getInstance($_POST['pmt_id']);
    $Pmt->setAmount($_POST['amount'])
        ->setMethod($_POST['gw_id'])
        ->setGateway($_POST['gw_id'])
        ->setRefID($_POST['ref_id'])
        ->setOrderID($_POST['order_id'])
        ->setUID($_USER['uid'])
        ->setIsMoney(isset($_POST['is_money']) ? 1 : 0)
        ->setComment($_POST['comment']);
    $Pmt->Save();
    COM_refresh(SHOP_ADMIN_URL . '/payments.php?ord_pmts=' . $_POST['order_id']);
    break;

case 'delete':
case 'delpayment':
    Shop\Payment::delete($actionval);
    COM_refresh(SHOP_ADMIN_URL . '/payments.php?ord_pmts=' . $_GET['ord_pmts']);
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
    $val = SHOP_getVar($_GET, 'pmt_id', 'integer');
    if ($val > 0) {
        $content .= \Shop\Report::getInstance('payment')->RenderDetail($val);
        break;
    }
    break;

case 'list':
case 'payments':
case 'ord_pmts':
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
$display .= \Shop\Menu::Admin('orders');
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;

?>
