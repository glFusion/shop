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
use Shop\Models\Request;
$Request = Request::getInstance();

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
list($action, $actionval) = $Request->getAction($expected);

if ($action == '') {
    // Not defined in URL arguments
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
    $Cart->Update($Request);
    if (empty($Cart->getBuyerEmail())) {
        SHOP_setMsg($LANG_SHOP['err_missing_email'], 'error');
        echo COM_refresh(SHOP_URL . '/cart.php');
    }
    $wf = Shop\Workflow::getNextView('viewcart', $Cart);
    echo COM_refresh(SHOP_URL . '/cart.php?' . $wf->getName());
    break;

case 'update':
    Shop\Cart::getInstance()->Update($Request);
    echo COM_refresh(SHOP_URL . '/cart.php');
    break;

case 'delete':
    // Delete a single item from the cart
    $id = COM_getArgument('id');
    \Shop\Cart::getInstance()->Remove($id);
    $return_url = $Request->getString('return');
    if (!empty($return_url)) {
        echo COM_refresh($return_url);
    } else {
        echo COM_refresh(SHOP_URL . '/cart.php');
    }
    break;

case 'empty':
    // Remove all items from the cart
    \Shop\Cart::getInstance()->Clear();
    SHOP_setMsg($LANG_SHOP['cart_empty']);
    echo COM_refresh(SHOP_URL . '/index.php');
    break;

case 'save_payment':
    $gwname = $Request->getString('gateway');
    $Cart = \Shop\Cart::getInstance();
    $Cart->setGateway($gwname);
    $Cart->validateDiscountCode();

    // Validate the cart items
    $invalid = $Cart->updateItems();
    if (!empty($invalid)) {
        echo COM_refresh(SHOP_URL . '/cart.php');
    }

    // Check that the cart has items. Could be empty if the session or login
    // has expired.
    if (!$Cart->hasItems()) {
        SHOP_setMsg($LANG_SHOP['cart_empty']);
        echo COM_refresh(SHOP_URL . '/index.php');
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
            echo COM_refresh(SHOP_URL . '/cart.php?' . $set_wf);
        }
    }
    $Cart->Save(false);

    // Set the gateway as the customer's preferred
    if ($Cart->getUid() > 1) {
        Shop\Customer::getInstance()->setPrefGW($gwname)->saveUser();
    }
    echo COM_refresh(SHOP_URL . '/cart.php?confirm');
    break;

case 'checkout':
    // Set the gift card amount first as it will be overridden
    // if the _coupon gateway is selected
    $Cart = \Shop\Cart::getInstance();
    $Cart->validateDiscountCode();

    // Validate the cart items
    $invalid = $Cart->updateItems();
    if (!empty($invalid)) {
        echo COM_refresh(SHOP_URL . '/cart.php');
    }

    // Check that the cart has items. Could be empty if the session or login
    // has expired.
    if (!$Cart->hasItems()) {
        SHOP_setMsg($LANG_SHOP['cart_empty']);
        echo COM_refresh(SHOP_URL . '/index.php');
        exit;
    }
/*
    $gateway = $Request->getString('gateway');
    if ($gateway !== '') {
        \Shop\Gateway::setSelected($gateway);
        $Cart->setGateway($gateway);
        Shop\Customer::getInstance('', $Cart->uid)
            ->setPrefGW($gateway)
            ->saveUser();
    }
    if (isset($Request['by_gc'])) {
        // Has some amount paid by coupon
        $Cart->setGC($Request->getInt('by_gc');
    } elseif ($gateway == '_coupon') {
        // Entire order is paid by coupon
        $Cart->setGC(-1);
    } else {
        // No amount paid by coupon
        $Cart->setGC(0);
    }
 */
    /*if (isset($Request['order_instr'])) {
        $Cart->setInstructions($Request->getString('order_instr']);
    }
    $payer_email = $Request->getString('payer_email');
    if (!empty($payer_email)) {
        $Cart->setEmail($payer_email);
    }*/
    /*if (isset($Request['shipper_id'])) {
        $Cart->setShipper($Request->getInt('shipper_id'));
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
            echo COM_refresh(SHOP_URL . '/cart.php');
        }
    }
/*    if (isset($Request['quantity'])) {
        // Update the cart quantities if coming from the cart view.
        // This also calls Save() on the cart
        $Cart->Update($Request);
    } else {
        $Cart->Save();
    }
 */
    // See what workflow elements we already have.
    $next_step = $Request->getInt('next_step');
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
    $method_id = $Request->getInt('method_id');
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
    echo COM_refresh(SHOP_URL . '/cart.php?payment');
    break;

case 'save_addresses':
    $Cart = Shop\Cart::getInstance();
    if (isset($Request['is_anon'])) {
        $Shipto = new Shop\Address;
        $Shipto->fromArray($Request->toArray(), 'shipto');
        $Shipto->setID(-1);
        $Cart->setAddress($Shipto, 'shipto');
        if (isset($Request['shipto_is_billto'])) {
            $Cart->setAddress($Shipto, 'billto');
        } else {
            $Billto = new Shop\Address;
            $Billto->fromArray($Request->toArray(), 'billto');
            $Billto->setID(-1);
            $Cart->setAddress($Billto, 'billto');
        }
    } else {
        foreach (array('billto', 'shipto') as $key) {
            $addr_id = $Request->getInt($key . '_id');
            if ($addr_id > 0) {
                $Addr = Shop\Address::getInstance($addr_id);
                $Cart->setAddress($Addr, $key);
            }
        }
    }
    $save = false;
    if (isset($Request['order_instr'])) {
        $Cart->setInstructions($Request['order_instr']);
        $save = true;
    }
    $buyer_email = $Request->getString('buyer_email');
    if (!empty($buyer_email)) {
        $Cart->setBuyerEmail($buyer_email);
        $save = true;
    }
    if ($save) {
        $Cart->Save(false);
    }
    $wf = Shop\Workflow::getNextView('addresses', $Cart);
    echo COM_refresh(SHOP_URL . '/cart.php?' . $wf->getName());
    break;

case 'savebillto':
case 'saveshipto':
    echo "here";die;
    $addr_type = substr($action, 4);   // get 'billto' or 'shipto'
    if ($actionval == 1 || $actionval == 2) {
        $addr = json_decode($Request['addr'][$actionval], true);  // todo
    } else {
        $addr = $Request->toArray();
    }
    $Address = new Shop\Address($addr);
    $status = $Address->isValid($addr);
    if ($status != '') {
        $content .= SHOP_errorMessage($status, 'danger', $LANG_SHOP['invalid_form']);
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
            $Request['useaddress'] = $data[0];
            $addr['addr_id'] = $data[0];
        }
    }
    $Cart = Shop\Cart::getInstance();
    $Cart->setAddress($addr, $addr_type);
    //$next_step = $Request->getInt('next_step');
    //$content = $Cart->getView($next_step);
    //$content = $Cart->getView(0);
    echo COM_refresh(SHOP_URL . '/cart.php');
    $view = 'none';
    break;

case 'nextstep':
    $next_step = $Request->getInt('next_step');
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
        echo COM_refresh(SHOP_URL . '/index.php');
    }
    $V = new Shop\Views\Cart;
    $content .= Shop\Menu::checkoutFlow($Cart, 'addresses');
    $content .= $V->withOrder($Cart)->addressSelection();
    break;

case 'savevalidated':
case 'saveaddr':
    if ($actionval == 1 || $actionval == 2) {
        $addr_vars = json_decode($Request['addr'][$actionval], true); // todo
    } else {
        $addr_vars = $Request->toArray();
    }
    if (isset($addr_vars['addr_id'])) {
        $id = $addr_vars['addr_id'];
    } elseif (isset($addr_vars['id'])) {
        $id = $addr_vars['id'];
    }

    $Addr = Shop\Address::getInstance($id);
    $status = $Addr->setVars($addr_vars)
                   ->isValid();
    if ($status != '') {
        $content .= COM_showMessageText(
            $status,
            $LANG_SHOP['invalid_form'],
            true,
            'error'
        );
        $content .= $Addr->Edit();
        break;
    }
    if (!COM_isAnonUser()) {
        $status = $Addr->Save();
        if ($status > 0) {
            SHOP_setMsg("Address saved");
        } else {
            SHOP_setMsg("Saving address failed");
        }
    }
    $Cart = Shop\Cart::getInstance();
    echo COM_refresh(Shop\URL::get($Request->getString('return')));
    break;

case 'editaddr':
    $Addr = Shop\Address::getInstance($id);
    if ($id > 0 && $Addr->getUid() != $_USER['uid']) {
        echo "her";die;
        echo COM_refresh(SHOP_URL . '/account.php?addresses');
    }
    $content .= Shop\Menu::User('none');
    $content .= $Addr->Edit();
    break;


case 'billto':
case 'shipto':
    // Editing the previously-submitted billing or shipping info.
    // This is accessed from the final order confirmation page, so return
    // there after submission
    $step = 8;     // form will return to ($step + 1)
    $U = Shop\Customer::getInstance();
    if (isset($Request['address'])) {
        $A = $Request->toArray();
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
        echo COM_refresh(SHOP_URL . '/index.php');
    }
    $addr_wf = Shop\Workflow::getInstance('addresses');
    if (!$addr_wf->isSatisfied($Cart)) {
        echo COM_refresh(SHOP_URL . '/cart.php?addresses');
    }
    $V = new Shop\Views\Cart;
    $content .= Shop\Menu::checkoutFlow($Cart, 'shipping');
    $content .= $V->withOrder($Cart)->shippingSelection();
    break;

case 'payment':
    $Cart = Shop\Cart::getInstance();
    $addr_wf = Shop\Workflow::getInstance('addresses');
    if (!$addr_wf->isSatisfied($Cart)) {
        echo COM_refresh(SHOP_URL . '/cart.php?addresses');
    }
    $ship_wf = Shop\Workflow::getInstance('shipping');
    if (!$ship_wf->isSatisfied($Cart)) {
        echo COM_refresh(SHOP_URL . '/cart.php?shipping');
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
        echo COM_refresh(SHOP_URL . '/cart.php?' . $wf_needed);
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
        echo COM_refresh(SHOP_URL . '/cart.php');
    }
    if ($Cart->hasItems() && $Cart->canView()) {
        $content .= Shop\Menu::checkoutFlow($Cart, 'viewcart');
        $content .= $Cart->getView(0);
    } else {
        SHOP_setMsg($LANG_SHOP['cart_empty']);
        echo COM_refresh(SHOP_URL . '/index.php');
        exit;
    }
    break;
}
$display = \Shop\Menu::siteHeader($LANG_SHOP['cart_blocktitle']);
$display .= \Shop\Menu::pageTitle('', 'cart');
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;
