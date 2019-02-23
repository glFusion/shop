<?php
/**
 * View information related to a user's account, e.g. Cart, History, etc.
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

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !isset($_SHOP_CONF) ||
    !in_array($_SHOP_CONF['pi_name'], $_PLUGINS) ||
    COM_isAnonUser() ||     // Anonymous has nothing to view on the account page
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

// Import plugin-specific functions
USES_shop_functions();

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
if (empty($mode)) {
    $mode = 'orderhist';
}
$content = '';

switch ($mode) {
case 'couponlog':
    $content .= \Shop\SHOP_userMenu($mode);
    $content .= \Shop\CouponLog();
    $menu_opt = $LANG_SHOP['gc_activity'];
    $page_title = $LANG_SHOP['gc_activity'];
    break;

case 'cart':
    echo "DEPRECATED";die;
    SHOP_setUrl($_SERVER['request_uri']);
    if (!empty($id)) {
        \Shop\Cart::setFinal($id, false);
        COM_refresh(SHOP_URL. '/index.php?view=cart');
    }
    $menu_opt = $LANG_SHOP['viewcart'];
    $Cart = \Shop\Cart::getInstance();
    if ($Cart->hasItems() && $Cart->canView()) {
        $content .= $Cart->getView(0);
    } else {
        COM_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
        exit;
    }
    break;

case 'orderhist':
case 'history':
default:
    SHOP_setUrl($_SERVER['request_uri']);
    $content .= \Shop\SHOP_userMenu($mode);
    $content .= \Shop\listOrders();
    $menu_opt = $LANG_SHOP['purchase_history'];
    $page_title = $LANG_SHOP['purchase_history'];
    break;
}

$display = \Shop\siteHeader();
$T = SHOP_getTemplate('shop_title', 'title');
$T->set_var(array(
    'title' => isset($page_title) ? $page_title : '',
    'is_admin' => plugin_ismoderator_shop(),
) );
$display .= $T->parse('', 'title');
$display .= $content;
$display .= \Shop\siteFooter();
echo $display;

?>
