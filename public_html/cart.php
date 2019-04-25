<?php
/**
 * Access to shopping cart - view, update, delete, etc.
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

$content = '';
$action = '';
$actionval = '';
$view = '';
$expected = array(
    // Actions
    'update', 'checkout', 'savebillto', 'saveshipto', 'delete',
    'empty',
    // Views
    'view',
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
if ($action == '') {
    // Not defined in $_POST or $_GET
    // Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
    COM_setArgNames(array('action', 'id'));
    $action = COM_getArgument('action');
}
switch ($action) {
case 'update':
    \Shop\Cart::getInstance()->Update($_POST);
    COM_refresh(SHOP_URL . '/cart.php');
    break;

case 'delete':
    // Delete a single item from the cart
    $id = COM_getArgument('id');
    \Shop\Cart::getInstance()->Remove($id);
    COM_refresh(SHOP_URL . '/cart.php');
    break;

case 'empty':
    // Remove all items from the cart
    \Shop\Cart::getInstance()->Clear();
    COM_setMsg($LANG_SHOP['cart_empty']);
    echo COM_refresh(SHOP_URL . '/index.php');
    break;

case 'checkout':
    // Set the gift card amount first as it will be overridden
    // if the _coupon gateway is selected
    $Cart = \Shop\Cart::getInstance();

    // Validate the cart items
    $invalid = $Cart->Validate();
    if (!empty($invalid)) {
        COM_refresh(SHOP_URL . '/cart.php');
    }

    $gateway = SHOP_getVar($_POST, 'gateway');
    if ($gateway !== '') {
        \Shop\Gateway::setSelected($gateway);
        $Cart->setGateway($gateway);
    }
    if (isset($_POST['by_gc'])) {
        $Cart->setGC($_POST['by_gc']);
    } elseif ($gateway == '_coupon') {
        $Cart->setGC(-1);
    } else {
        $Cart->setGC(0);
    }

    if (isset($_POST['order_instr'])) {
        $Cart->instructions = $_POST['order_instr'];
    }
    if (isset($_POST['payer_email'])) {
        $Cart->buyer_email = $_POST['payer_email'];
    }
    if (isset($_POST['shipper_id'])) {
        $Cart->setShipper($_POST['shipper_id']);
    }
    if (isset($_POST['quantity'])) {
        // Update the cart quantities if coming from the cart view.
        // This also calls Save() on the cart
        $Cart->Update($_POST);
    } else {
        $Cart->Save();
    }
    // See what workflow elements we already have.
    $next_step = SHOP_getVar($_POST, 'next_step', 'integer', 0);
    if ($_SHOP_CONF['anon_buy'] == 1 || !COM_isAnonUser()) {
        $view = 'none';
        $content .= $Cart->getView($next_step);
        break;
    } else {
        $content .= SEC_loginRequiredForm();
        $view = 'none';
    }
    break;

case 'savebillto':
case 'saveshipto':
    $addr_type = substr($action, 4);   // get 'billto' or 'shipto'
    $status = \Shop\UserInfo::isValidAddress($_POST);
    if ($status != '') {
        $content .= SHOP_errMsg($status, $LANG_SHOP['invalid_form']);
        $view = $addr_type;
        break;
    }
    $U = \Shop\UserInfo::getInstance();
    if ($U->uid > 1) {      // only save addresses for logged-in users
        $addr_id = $U->saveAddress($_POST, $addr_type);
        if ($addr_id[0] < 0) {
            if (!empty($addr_id[1]))
                $content .= SHOP_errorMessage($addr_id[1], 'alert',
                        $LANG_SHOP['missing_fields']);
            $view = $addr_type;
            break;
        } else {
            $_POST['useaddress'] = $addr_id[0];
        }
    }
    $Cart = \Shop\Cart::getInstance();
    $Cart->setAddress($_POST, $addr_type);
    $next_step = SHOP_getVar($_POST, 'next_step', 'integer');
    $content = $Cart->getView($next_step);
    $view = 'none';
    break;

default:
    $view = 'view';
    break;
}

switch ($view) {
case 'none':
    break;

case 'view':
default:
    $id = COM_getArgument('id');
    SHOP_setUrl($_SERVER['request_uri']);
    if (!empty($id)) {
        \Shop\Cart::setFinal($id, false);
        COM_refresh(SHOP_URL. '/index.php?view=cart');
    }
    $menu_opt = $LANG_SHOP['viewcart'];
    $Cart = \Shop\Cart::getInstance();

    // Validate the cart items
    $invalid = $Cart->Validate();
    if (!empty($invalid)) {
        // Items have been removed, refresh to update and view the info msg.
        COM_refresh(SHOP_URL . '/cart.php');
    }

    if ($Cart->hasItems() && $Cart->canView()) {
        $content .= $Cart->getView(0);
    } else {
        COM_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
        exit;
    }
    break;
}

$display = \Shop\Menu::siteHeader();
$T = SHOP_getTemplate('shop_title', 'title');
$T->set_var(array(
    'title' => isset($page_title) ? $page_title : '',
    'is_admin' => plugin_ismoderator_shop(),
) );
$display .= $T->parse('', 'title');
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;

?>
