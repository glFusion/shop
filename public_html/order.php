<?php
/**
 * View and print orders.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Require core glFusion code */
require_once '../lib-common.php';

if (
    !isset($_SHOP_CONF) ||
    !in_array($_SHOP_CONF['pi_name'], $_PLUGINS) ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

$page_title = '';
$action = '';
$actionval = '';
$view = '';

// Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
COM_setArgNames(array('mode', 'id', 'token'));

if (isset($_GET['mode'])) {
    $mode = COM_applyFilter($_GET['mode']);
} else {
    $mode = COM_getArgument('mode');
}
if (isset($_GET['id'])) {
    $id = COM_sanitizeID($_GET['id']);
} else {
    $id = COM_applyFilter(COM_getArgument('id'));
}
if (isset($_GET['token'])) {
    $token = COM_sanitizeID($_GET['token']);
} else {
    $token = COM_applyFilter(COM_getArgument('token'));
}
if (empty($mode) && !empty($id)) {
    $mode = 'view';
}
$content = '';

switch ($mode) {
case 'view':
    // View a completed order record
    $order = \Shop\Order::getInstance($id);
    if ($order->canView($token)) {
        $content .= $order->View();
    } else {
        COM_404();
    }
    break;

case 'packinglist':
case 'print':
    // Display a printed order or packing list and exit.
    // This is expected to be shown in a _blank browser window/tab.
    $order = \Shop\Order::getInstance($id);
    if ($order->canView($token)) {
        echo $order->View($mode);
        exit;
    } else {
        COM_404();
    }
    break;
}

$display = \Shop\Menu::siteHeader();
$display .= \Shop\Menu::pageTitle($page_title);
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;

?>
