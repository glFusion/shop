<?php
/**
 * View and print orders.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Require core glFusion code */
require_once '../lib-common.php';
use Shop\Models\Request;

if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

$Request = Request::getInstance()->withArgNames(array('mode', 'id', 'token'));;
$page_title = '';
$action = '';
$actionval = '';
$view = '';

$mode = $Request->getString('mode');
$id = $Request->getString('id');
$token = $Request->getString('token');
if (empty($mode) && !empty($id)) {
    $mode = 'view';
}
$content = '';

switch ($mode) {
case 'view':
    // View a completed order record
    $order = \Shop\Order::getInstance($id);
    if ($order->canView($token)) {
        $View = new Shop\Views\Invoice();
        $content .= $View->withOrder($order)->withToken($token)->Render();
    } else {
        COM_404();
    }
    break;

case 'pdforder':
    $order = Shop\Order::getInstance($id);
    if ($order->canView($token)) {
        $View = new Shop\Views\Invoice();
        $content = $View->withOrderId($id)->withToken($token)->withOutput('pdf')->Render();
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

