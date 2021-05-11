<?php
/**
 * Access to shopping cart - view, update, delete, etc.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner
 * @package     shop
 * @varsion     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Require core glFusion code */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !function_exists('SHOP_access_check') ||
    (!$_SHOP_CONF['anon_buy'] && COM_isAnonUser()) ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

$display = \Shop\Menu::siteHeader($LANG_SHOP['cart_blocktitle']);
$content = '';
$action = '';
$actionval = '';
$view = '';
$expected = array(
    // Actions
    'update', 'checkout', 'savebillto', 'saveshipto', 'delete', 'nextstep',
    'empty', 'save_viewcart', 'save_addresses', 'save_shipping', 'save_payment',
    // Views
    'editcart', 'viewcart', 'cancel', 'view', 'billto', 'shipto', 'addresses', 'shipping',
    'payment', 'confirm',
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
    COM_setArgNames(array('action', 'id', 'token'));
    $action = COM_getArgument('action');
}
if ($action == '') {
    // Still no defined action, set to "view"
    $action = 'view';
}

switch ($action) {
case 'save_viewcart':
    $Cart = Shop\Cart::getInstance();
    $Cart->Update($_POST);
    if (empty($Cart->getBuyerEmail())) {
        SHOP_setMsg($LANG_SHOP['err_missing_email'], 'error');
        COM_refresh(SHOP_URL . '/cart.php');
    }
    $wf = Shop\Workflow::getNextView('viewcart', $Cart);
    COM_refresh(SHOP_URL . '/cart.php?' . $wf->getName());
    break;

case 'update':
    Shop\Cart::getInstance()->Update($_POST);
    COM_refresh(SHOP_URL . '/cart.php');
    break;

case 'delete':
    // Delete a single item from the cart
    $id = COM_getArgument('id');
    \Shop\Cart::getInstance()->Remove($id);
    if (isset($_GET['return']) && !empty($_GET['return'])) {
        COM_refresh($_GET['return']);
    } else {
        COM_refresh(SHOP_URL . '/cart.php');
    }
    break;

case 'empty':
    // Remove all items from the cart
    \Shop\Cart::getInstance()->Clear();
    SHOP_setMsg($LANG_SHOP['cart_empty']);
    echo COM_refresh(SHOP_URL . '/index.php');
    break;

case 'save_payment':
    $gwname = SHOP_getVar($_POST, 'gateway');
    $Cart = \Shop\Cart::getInstance();
    $Cart->setGateway($gwname);
    $Cart->validateDiscountCode();

    // Validate the cart items
    $invalid = $Cart->updateItems();
    if (!empty($invalid)) {
        COM_refresh(SHOP_URL . '/cart.php');
    }

    // Check that the cart has items. Could be empty if the session or login
    // has expired.
    if (!$Cart->hasItems()) {
        SHOP_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
        exit;
    }
    if (empty($invalid)) {
        // Validate that all order fields are filled out. If not, then that is a
        // valid error and the error messages will be displayed upon return.
        $set_wf = $Cart->Validate();
        if (!empty($set_wf)) {
            if ($Cart->isTainted()) {
                // Save to keep the instructions, email, etc.
                $Cart->Save();
            }
            COM_refresh(SHOP_URL . '/cart.php?' . $set_wf);
        }
    }
    $Cart->Save(false);

    // Set the gateway as the customer's preferred
    if ($Cart->getUid() > 1) {
        Shop\Customer::getInstance()->setPrefGW($gwname)->saveUser();
    }
    COM_refresh(SHOP_URL . '/cart.php?confirm');
    break;

case 'checkout':
    // Set the gift card amount first as it will be overridden
    // if the _coupon gateway is selected
    $Cart = \Shop\Cart::getInstance();
    $Cart->validateDiscountCode();

    // Validate the cart items
    $invalid = $Cart->updateItems();
    if (!empty($invalid)) {
        COM_refresh(SHOP_URL . '/cart.php');
    }

    // Check that the cart has items. Could be empty if the session or login
    // has expired.
    if (!$Cart->hasItems()) {
        SHOP_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
        exit;
    }
/*
    $gateway = SHOP_getVar($_POST, 'gateway');
    if ($gateway !== '') {
        \Shop\Gateway::setSelected($gateway);
        $Cart->setGateway($gateway);
        Shop\Customer::getInstance('', $Cart->uid)
            ->setPrefGW($gateway)
            ->saveUser();
    }
    if (isset($_POST['by_gc'])) {
        // Has some amount paid by coupon
        $Cart->setGC($_POST['by_gc']);
    } elseif ($gateway == '_coupon') {
        // Entire order is paid by coupon
        $Cart->setGC(-1);
    } else {
        // No amount paid by coupon
        $Cart->setGC(0);
    }
 */
    /*if (isset($_POST['order_instr'])) {
        $Cart->setInstructions($_POST['order_instr']);
    }
    if (isset($_POST['payer_email']) && !empty($_POST['payer_email'])) {
        $Cart->setEmail($_POST['payer_email']);
    }*/
    /*if (isset($_POST['shipper_id'])) {
        $Cart->setShipper($_POST['shipper_id']);
    }*/

    // Final check that all items are valid. No return or error message
    // unless this is the only issue. This is the final step after viewing
    // the cart so there shouldn't be any changes.
    //$invalid = $Cart->updateItems();
    if (empty($invalid)) {
        // Validate that all order fields are filled out. If not, then that is a
        // valid error and the error messages will be displayed upon return.
        $errors = $Cart->Validate();
        if (!empty($errors)) {
            if ($Cart->isTainted()) {
                // Save to keep the instructions, email, etc.
                $Cart->Save();
            }
            COM_refresh(SHOP_URL . '/cart.php');
        }
    }
/*    if (isset($_POST['quantity'])) {
        // Update the cart quantities if coming from the cart view.
        // This also calls Save() on the cart
        $Cart->Update($_POST);
    } else {
        $Cart->Save();
    }
 */
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

case 'save_shipping':
    $method_id = SHOP_getVar($_POST, 'method_id', 'integer');
    $Cart = Shop\Cart::getInstance();
    $Cart->setShippingOption($method_id);
    /*$options = $Cart->getShippingOptions();
    if (is_array($options) && array_key_exists($method_id, $options)) {
        $shipper = $options[$method_id];
        // Have to convert some of the shipper fields
        $method = array(
            'shipper_id' => $shipper['shipper_id'],
            'cost' => $shipper['method_rate'],
            'svc_code' => $shipper['svc_code'],
            'title' => $shipper['method_name'],
        );
        $Cart->setShipper($method);
    }*/
    COM_refresh(SHOP_URL . '/cart.php?payment');
    break;

case 'save_addresses':
    $Cart = Shop\Cart::getInstance();
    foreach (array('billto', 'shipto') as $key) {
        $addr_id = SHOP_getVar($_POST, $key . '_id', 'integer');
        if ($addr_id > 0) {
            $Addr = Shop\Address::getInstance($addr_id);
            $Cart->setAddress($Addr, $key);
        }
    }
    $save = false;
    if (isset($_POST['order_instr'])) {
        $Cart->setInstructions($_POST['order_instr']);
        $save = true;
    }
    if (isset($_POST['buyer_email']) && !empty($_POST['buyer_email'])) {
        $Cart->setBuyerEmail($_POST['buyer_email']);
        $save = true;
    }
    if ($save) {
        $Cart->Save(false);
    }
    $wf = Shop\Workflow::getNextView('addresses', $Cart);
    COM_refresh(SHOP_URL . '/cart.php?' . $wf->getName());
    break;

case 'savebillto':
case 'saveshipto':
    $addr_type = substr($action, 4);   // get 'billto' or 'shipto'
    if ($actionval == 1 || $actionval == 2) {
        $addr = json_decode($_POST['addr'][$actionval], true);
    } else {
        $addr = $_POST;
    }
    $Address = new Shop\Address($addr);
    $status = $Address->isValid($addr);
    if ($status != '') {
        $content .= SHOP_errMsg($status, $LANG_SHOP['invalid_form']);
        $view = $addr_type;
        break;
    }
    $U = Shop\Customer::getInstance();
    if ($U->getUid() > 1) {      // only save addresses for logged-in users
        $data = $U->saveAddress($addr, $addr_type);
        if ($data[0] < 0) {
            if (!empty($data[1])) {
                $content .= SHOP_errorMessage(
                    $data[1],
                    'alert',
                    $LANG_SHOP['missing_fields']
                );
            }
            $view = $addr_type;
            break;
        } else {
            $_POST['useaddress'] = $data[0];
            $addr['addr_id'] = $data[0];
        }
    }
    $Cart = Shop\Cart::getInstance();
    $Cart->setAddress($addr, $addr_type);
    //$next_step = SHOP_getVar($_POST, 'next_step', 'integer');
    //$content = $Cart->getView($next_step);
    //$content = $Cart->getView(0);
    COM_refresh(SHOP_URL . '/cart.php');
    $view = 'none';
    break;

case 'nextstep':
    $next_step = SHOP_getVar($_POST, 'next_step', 'integer');
    $content = Shop\Cart::getInstance()->getView($next_step);
    $view = 'none';
    break;

default:
    //$view = 'view';
    $view = $action;
    break;
}

switch ($view) {
case 'none':
    break;

case 'addresses':
    $Cart = Shop\Cart::getInstance();
    if (!$Cart->hasItems() || !$Cart->canView()) {
        SHOP_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
    }
    $V = new Shop\Views\Cart;
    $content .= Shop\Menu::checkoutFlow($Cart, 'addresses');
    $content .= $V->withOrder($Cart)->addressSelection();
    break;

case 'billto':
case 'shipto':
    // Editing the previously-submitted billing or shipping info.
    // This is accessed from the final order confirmation page, so return
    // there after submission
    $step = 8;     // form will return to ($step + 1)
    $U = Shop\Customer::getInstance();
    if (isset($_POST['address'])) {
        $A = $_POST;
    } elseif ($view == 'billto') {
        $A = Shop\Cart::getInstance()->getBillto()->toArray();
    } else {
        $A = Shop\Cart::getInstance()->getShipto()->toArray();
    }
    $content .= Shop\Menu::checkoutFlow(Shop\Cart::getInstance(), 'addresses');
    $content .= $U->AddressForm($view, $A, $step);
    break;

case 'shipping':
    $Cart = Shop\Cart::getInstance();
    if (!$Cart->hasItems() || !$Cart->canView()) {
        SHOP_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
    }
    $addr_wf = Shop\Workflow::getInstance('addresses');
    if (!$addr_wf->isSatisfied($Cart)) {
        COM_refresh(SHOP_URL . '/cart.php?addresses');
    }
    $V = new Shop\Views\Cart;
    $content .= Shop\Menu::checkoutFlow($Cart, 'shipping');
    $content .= $V->withOrder($Cart)->shippingSelection();
    break;

case 'payment':
    $Cart = Shop\Cart::getInstance();
    $addr_wf = Shop\Workflow::getInstance('addresses');
    if (!$addr_wf->isSatisfied($Cart)) {
        COM_refresh(SHOP_URL . '/cart.php?addresses');
    }
    $ship_wf = Shop\Workflow::getInstance('shipping');
    if (!$ship_wf->isSatisfied($Cart)) {
        COM_refresh(SHOP_URL . '/cart.php?shipping');
    }
    if ($Cart->getPmtMethod() == '') {
        // Set the payment method to the customer's preferred method
        // if not already set.
        $Cart->setPmtMethod(
            Shop\Customer::getInstance()->getPrefGW()
        )->Save();
    }
    $V = new Shop\Views\Cart;
    $content .= Shop\Menu::checkoutFlow($Cart, 'payment');
    $content .= $V->withOrder($Cart)->paymentSelection();
    break;

case 'confirm':
    $Cart = Shop\Cart::getInstance();
    $wf_needed = Shop\Workflow::findFirstNeeded($Cart);
    if (!empty($wf_needed) && $wf_needed != 'confirm') {
        COM_refresh(SHOP_URL . '/cart.php?' . $wf_needed);
    }
    $V = new Shop\Views\Cart;
    $content .= Shop\Menu::checkoutFlow($Cart, 'confirm');
    $content .= $V->withOrder($Cart)->confirmCheckout();
    break;

case 'cancel':
    list($cart_id, $token) = explode('/', $actionval);
    if (!empty($cart_id)) {
        // Don't use getInstance() to avoid creating a new cart
        $Cart = new Shop\Cart($cart_id, false);
        if ($token == $Cart->getToken()) {
            // Only reset if the token is valid, then update the token to
            // invalidate further changes using this token.
            $Cart->cancelFinal();
        }
    }
    // fall through to view cart
case 'view':
case 'editcart':
default:
    SHOP_setUrl($_SERVER['REQUEST_URI']);
    $menu_opt = $LANG_SHOP['viewcart'];
    $Cart = \Shop\Cart::getInstance();
    $Cart->calcTotal();
    if ($view != 'viewcart' && $Cart->canFastCheckout()) {
        $V = new Shop\Views\Cart;
        $content .= Shop\Menu::checkoutFlow($Cart, 'confirm');
        $content .= $V->withOrder($Cart)->confirmCheckout();
        break;
    }
    // Validate the cart items
    $invalid = $Cart->updateItems();
    $Cart->validateDiscountCode();
    if (!empty($invalid)) {
        // Items have been removed, refresh to update and view the info msg.
        COM_refresh(SHOP_URL . '/cart.php');
    }
    if ($Cart->hasItems() && $Cart->canView()) {
        $content .= Shop\Menu::checkoutFlow($Cart, 'viewcart');
        $content .= $Cart->getView(0);
    } else {
        SHOP_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
        exit;
    }
    break;
}
$display .= \Shop\Menu::pageTitle('', 'cart');
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;
