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
USES_shop_functions();
USES_lib_admin();

$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($_REQUEST['msg'])) $msg[] = $_REQUEST['msg'];

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = '';
$expected = array(
    // Views to display
    'pdfpl', 'pdforder',
    'configure', 'run', 'report', 'list',
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
$view = 'list';

switch ($action) {
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
        // Params usually from GET but could be POSTed time period
        $R->setParams($_REQUEST);
        $content .= $R->Render();
    }
    break;

case 'pdfpl':
case 'pdforder':
    $orders = SHOP_getVar($_POST, 'orders', 'array');
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
