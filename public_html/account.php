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
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

// For anonymous, this may be a valid selection coming from an email link.
// Put up a message indicating that they need to log in.
if (COM_isAnonUser()) {
    SESS_setVar('login_referer', $_CONF['site_url'] . $_SERVER['REQUEST_URI']);
    COM_setMsg($LANG_SHOP['gc_need_acct']);
    COM_refresh($_CONF['site_url'] . '/users.php?mode=login');
    exit;
}

$page_title = '';
$action = '';
$actionval = '';
$view = '';

// Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
COM_setArgNames(array('mode', 'id'));
foreach (array('mode', 'id') as $varname) {
    if (isset($_POST[$varname])) {
        $$varname = COM_applyFilter($_POST[$varname]);
    } elseif (isset($_GET[$varname])) {
        $$varname = COM_applyFilter($_GET[$varname]);
    } else {
        $$varname = COM_getArgument($varname);
    }
}
if (empty($mode)) {
    $mode = 'orderhist';
}
$content = '';

switch ($mode) {
case 'couponlog':
    // If gift cards are disabled, then this is an invalid URL.
    if (!$_SHOP_CONF['gc_enabled']) {
        COM_404();
        exit;
    }
    $content .= \Shop\Menu::User($mode);
    $content .= '<p>';
    $gc_bal = \Shop\Products\Coupon::getUserBalance();
    $content .= $LANG_SHOP['gc_bal'] . ': ' . \Shop\Currency::getInstance()->Format($gc_bal);
    $url = \Shop\Products\Coupon::redemptionUrl();
    $content .= '&nbsp;&nbsp;' . COM_createLink(
        $LANG_SHOP['add_gc'],
        $url,
        array(
            'class' => 'uk-button uk-button-success uk-button-mini',
        )
    );
    $content .= '</p>';
    $R = \Shop\Report::getInstance('coupons');
    $R->setUid();
    $content .= $R->Render();
    $menu_opt = $LANG_SHOP['gc_activity'];
    $page_title = $LANG_SHOP['gc_activity'];
    break;

case 'redeem':
    // If gift cards are disabled, then this is an invalid URL.
    if (!$_SHOP_CONF['gc_enabled']) {
        COM_404();
        exit;
    }
    // Using REQUEST here since this could be from a link in an email or from
    // the apply_gc form
    $code = SHOP_getVar($_POST, 'code');
    $uid = $_USER['uid'];
    list($status, $msg) = \Shop\Products\Coupon::Redeem($code, $uid);
    if ($status > 0) {
        $persist = true;
        $type = 'error';
    } else {
        $persist = false;
        $type = 'info';
    }
    // Redirect back to the provided view, or to the default page
    COM_setMsg($msg, $type, $persist);
    COM_refresh(COM_buildUrl(
        SHOP_URL . '/account.php?mode=couponlog'
    ) );
    break;

case 'orderhist':
case 'history':
default:
    SHOP_setUrl($_SERVER['request_uri']);
    $content .= \Shop\Menu::User($mode);
    $R = \Shop\Report::getInstance('orderlist');
    $R->setAdmin(false);
    $R->setParams($_POST);
    $R->setAllowedStatuses(array('invoiced','paid','processing','closed','refunded'));
    $content .= $R->Render();
    $menu_opt = $LANG_SHOP['purchase_history'];
    $page_title = $LANG_SHOP['purchase_history'];
    break;
}

$display = \Shop\Menu::siteHeader();
$display .= \Shop\Menu::pageTitle($page_title, 'account');
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;

?>
