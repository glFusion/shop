<?php
/**
 * View information related to a user's account, e.g. Cart, History, etc.
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

use Shop\Models\ProductType;
use Shop\Models\OrderStatus;
use Shop\Models\DataArray;
use Shop\Models\Request;
use Shop\Template;

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}
$Request = Request::getInstance();

// For anonymous, this may be a valid selection coming from an email link.
// Put up a message indicating that they need to log in.
if (COM_isAnonUser()) {
    SESS_setVar('login_referer', $_CONF['site_url'] . $_SERVER['REQUEST_URI']);
    SHOP_setMsg($LANG_SHOP['gc_need_acct']);
    echo COM_refresh($_CONF['site_url'] . '/users.php?mode=login');
    exit;
}

// Get the mode first from possibly posted variables
$expected = array(
    // Actions
    'saveaddr', 'savevalidated', 'delbutton_x',
    // Views
    'orderhist', 'addresses', 'editaddr',
);
list($mode, $actionval) = $Request->getAction($expected);

if ($mode == '') {
    // Retrieve and sanitize input variables.
    COM_setArgNames(array('mode', 'id'));
    foreach (array('mode', 'id') as $varname) {
        if (isset($Request[$varname])) {
            $$varname = COM_applyFilter($Request[$varname]);
        } else {
            $$varname = COM_getArgument($varname);
        }
    }
}
if (empty($mode)) {
    $mode = 'orderhist';
}

$page_title = '';
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
    $content .= '<h2>' . $LANG_SHOP['gc_bal'] . ': ' . \Shop\Currency::getInstance()->Format($gc_bal) . '</h2>';
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
    $code = $Request->getString('code');
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
    SHOP_setMsg($msg, $type, $persist);
    echo COM_refresh(COM_buildUrl(
        SHOP_URL . '/account.php?mode=couponlog'
    ) );
    break;

case 'delbutton_x':
    // Deleting multiple addresses at once
    if (isset($Request['delitem']) && is_array($Request['delitem'])) {
        Shop\Address::deleteMulti($Request['delitem']);
    }
    echo COM_refresh(SHOP_URL . '/account.php?addresses');
    break;

case 'deladdr':
    // Delete one single address
    Shop\Address::deleteMulti(array($id));
    echo COM_refresh(SHOP_URL . '/account.php?addresses');
    break;

case 'savevalidated':
case 'saveaddr':
    if ($actionval == 1 || $actionval == 2) {
        $addr_vars = json_decode($Request['addr'][$actionval], true);
        $addr_vars = new DataArray($addr_vars);
    } else {
        $addr_vars = $Request;
    }
    if (isset($addr_vars['addr_id'])) {
        $id = $addr_vars['addr_id'];
    } elseif (isset($addr_vars['id'])) {
        $id = $addr_vars['id'];
    }
    $Addr = Shop\Address::getInstance($id);
    $status = $Addr->setVars($addr_vars)
                   ->isValid(ProductType::PHYSICAL);
    if ($status != '') {
        $content .= Shop\Menu::User('addresses');
        $content .= COM_showMessageText(
            $LANG_SHOP['missing_fields'] . $status,
            $LANG_SHOP['invalid_form'],
            true,
            'error'
        );
        $content .= $Addr->Edit();
        break;
    }
    $status = $Addr->Save();
    if ($status > 0) {
        SHOP_setMsg("Address saved");
    } else {
        SHOP_setMsg("Saving address failed");
    }
    echo COM_refresh(Shop\URL::get($Request->getString('return')));
    break;

case 'editaddr':
    $Addr = Shop\Address::getInstance($id);
    if ($id > 0 && $Addr->getUid() != $_USER['uid']) {
        echo COM_refresh(SHOP_URL . '/account.php?addresses');
    }
    $content .= Shop\Menu::User('none');
    $content .= $Addr->Edit();
    break;

case 'addresses':
    SHOP_setUrl($_SERVER['REQUEST_URI']);
    $content .= Shop\Menu::User($mode);
    if ($_USER['uid'] > 1) {
        $content .= Shop\Address::adminList($_USER['uid']);
    } else {
        $content .= ' not available';
    }
    /*
    SHOP_setUrl($_SERVER['REQUEST_URI']);
    $content .= Shop\Menu::User($mode);
    $A = Shop\Customer::getInstance()->getAddresses();
    $T = new Template;
    $T->set_file('list', 'acc_addresses.thtml');
    $T->set_var('uid', $_USER['uid']);
    $T->set_block('list', 'Addresses', 'aRow');
    foreach ($A as $Addr) {
        $T->set_var(array(
            'addr_id' => $Addr->getID(),
            'address' => $Addr->toText('all', ', '),
            'def_billto' => $Addr->isDefaultBillto(),
            'def_shipto' => $Addr->isDefaultShipto(),
        ) );
        $T->parse('aRow', 'Addresses', true);
    }
    $T->parse('output', 'list');
    $content .= $T->finish($T->get_var('output'));
*/
    break;

case 'orderhist':
case 'history':
default:
    if (COM_isAnonUser()) {
        SESS_setVar('login_referer', $_CONF['site_url'] . $_SERVER['REQUEST_URI']);
        SHOP_setMsg($LANG_SHOP['gc_need_acct']);
        echo COM_refresh($_CONF['site_url'] . '/users.php?mode=login');
        exit;
    }

    SHOP_setUrl($_SERVER['REQUEST_URI']);
    $content .= \Shop\Menu::User($mode);
    $R = \Shop\Report::getInstance('orderlist');
    $R->setAdmin(false);
    $R->setParams();
    $R->setEmail($_USER['email']);
    $R->setAllowedStatuses(array_keys(OrderStatus::getCustomerViewable()));
    $content .= $R->Render();
    $menu_opt = $LANG_SHOP['purchase_history'];
    $page_title = $LANG_SHOP['purchase_history'];
    break;
}

$display = \Shop\Menu::siteHeader($LANG_SHOP['my_account']);
$display .= \Shop\Menu::pageTitle($LANG_SHOP['my_account'], 'account');
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;

