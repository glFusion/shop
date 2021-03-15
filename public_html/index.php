<?php
/**
 * Public index page for users of the shop plugin.
 *
 * By default displays available products along with links to purchase history
 * and detailed product views.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      Vincent Furia <vinny01@users.sourceforge.net
 * @copyright   Copyright (c) 2009-2020 Lee Garner
 * @copyright   Copyright (c) 2005-2006 Vincent Furia
 * @package     shop
 * @version     v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Require core glFusion code */
require_once '../lib-common.php';

// Ensure sufficient privleges and dependencies to read this page
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

$display = \Shop\Menu::siteHeader();
$page_title = '';
$action = '';
$actionval = '';
$view = '';
$expected = array(
    // Actions
    'updatecart', 'checkout', 'searchcat',
    'savebillto', 'saveshipto',
    'emptycart', 'delcartitem',
    'addcartitem', 'addcartitem_x', 'checkoutcart',
    'processorder', 'thanks', 'action',
    // 'setshipper',
    // Views
    'products', 'order', 'view', 'detail', 'printorder',
    'orderhist', 'packinglist',
    'couponlog', 'category',
    'cart', 'pidetail', 'viewcart',
);
$action = 'products';    // default view
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
if (isset($_POST['id'])) {
    $id = $_POST['id'];
} elseif (isset($_GET['id'])) {
    $id = $_GET['id'];
} else {
    $id = '';
}
$content = '';

switch ($action) {
case 'updatecart':
    echo "depreacated";die;
    \Shop\Cart::getInstance()->Update($_POST);
    $view = 'cart';
    break;

case 'checkout':
    echo "depreacated";die;
    // Set the gift card amount first as it will be overridden
    // if the _coupon gateway is selected
    $Cart = \Shop\Cart::getInstance();
    $gateway = SHOP_getVar($_POST, 'gateway');
    if ($gateway !== '') {
        $Cart->setGateway($gateway);
    }
    if ($gateway !== '') $Cart->setGateway($gateway);
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
    echo "deprecated";die;
    $addr_type = substr($action, 4);   // get 'billto' or 'shipto'
    $status = \Shop\Customer::isValidAddress($_POST);
    if ($status != '') {
        $content .= SHOP_errMsg($status, $LANG_SHOP['invalid_form']);
        $view = $addr_type;
        break;
    }
    $U = \Shop\Customer::getInstance();
    if ($U->getUid() > 1) {      // only save addresses for logged-in users
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
    //$view = \Shop\Workflow::getNextView($addr_type);
    \Shop\Cart::getInstance()->setAddress($_POST, $addr_type);
    $next_step = SHOP_getVar($_POST, 'next_step', 'integer');
    $content = \Shop\Cart::getInstance()->getView($next_step);
    $view = 'none';
    break;

case 'addcartitem':
case 'addcartitem_x':   // using the image submit button, such as Shop's
    echo "depreacated";die;
    $view = 'products';
    if (isset($_POST['_unique']) && $_POST['_unique'] &&
            \Shop\Cart::getInstance()->Contains($_POST['item_number']) !== false) {
        break;
    }
    \Shop\Cart::getInstance()->addItem(array(
        'item_number' => isset($_POST['item_number']) ? $_POST['item_number'] : '',
        'item_name' => isset($_POST['item_name']) ? $_POST['item_name'] : '',
        'description' => isset($$_POST['item_descr']) ? $_POST['item_descr'] : '',
        'quantity' => isset($_POST['quantity']) ? (float)$_POST['quantity'] : 1,
        'price' => isset($_POST['amount']) ? $_POST['amount'] : 0,
        'options' => isset($_POST['options']) ? $_POST['options'] : array(),
        'extras' => isset($_POST['extras']) ? $_POST['extras'] : array(),
    ) );
    if (isset($_POST['_ret_url'])) {
        COM_refresh($_POST['_ret_url']);
        exit;
    } elseif (SHOP_is_plugin_item($$_POST['item_number'])) {
        COM_refresh(SHOP_URL . '/index.php');
        exit;
    } else {
        COM_refresh(SHOP_URL.'/detail.php?id='.$_POST['item_number']);
        exit;
    }
    break;

case 'delcartitem':
    echo "depreacated";die;
    \Shop\Cart::getInstance()->Remove($_GET['id']);
    $view = 'cart';
    break;

case 'emptycart':
    echo "depreacated";die;
    \Shop\Cart::getInstance()->Clear();
    COM_setMsg($LANG_SHOP['cart_empty']);
    echo COM_refresh(SHOP_URL . '/index.php');
    break;

case 'thanks':
    // Allow for no thanksVars function
    $parts = explode('/', $actionval);
    $gw_name = $parts[0];
    $order_id = isset($parts[1]) ? $parts[1] : '';
    $toke = isset($parts[2]) ? $parts[2] : '';
    $message = $LANG_SHOP['thanks_title'];
    if (!empty($gw_name)) {
        $gw = Shop\Gateway::getInstance($gw_name);
        if ($gw !== NULL) {
            $tVars = $gw->thanksVars();
            if (!empty($tVars)) {
                $T = Shop\Template::getByLang();
                $T->set_file('msg', 'thanks_for_order.thtml');
                $T->set_var('site_name', $_CONF['site_name']);
                foreach ($tVars as $name=>$val) {
                    $T->set_var($name, $val);
                }
                $message = $T->parse('output', 'msg');
            }

            // Update the cart to "pending" at this point if not already
            // done via payment notification.
            if (!empty($order_id) && !empty($token)) {
                $Order = Shop\Order::getInstance($order_id);
                // Since this is public-facing, make sure it only updates from
                // 'cart' to 'pending'
                if (
                    $Order->getStatus() == 'cart' &&
                    $Order->canView($token)
                ) {
                    $Order->updateStatus('pending', false, false);
                }
            }
        }
    }
    //$Cart = Shop\Cart::getInstance();   // generate a new cart
    $content .= COM_showMessageText($message, $LANG_SHOP['thanks_title'], true, 'success');
    $view = 'products';
    break;

case 'action':      // catch all the "?action=" urls
    switch ($actionval) {
    case 'thanks':
        $T = Shop\Template::getByLang();
        $T->set_file('msg', 'thanks_for_order.thtml');
        $T->set_var(array(
            'site_name'     => $_CONF['site_name'],
            'payment_date'  => $_POST['payment_date'],
            'currency'      => $_POST['mc_currency'],
            'mc_gross'      => $_POST['mc_gross'],
            'shop_url'    => $_SHOP_CONF['shop_url'],
        ) );
        $content .= COM_showMessageText($T->parse('output', 'msg'),
                    $LANG_SHOP['thanks_title'], true);
        $view = 'products';
        break;
    }
    break;

case 'view':            // "?view=" url passed in
    $view = $actionval;
    break;

case 'processorder':
    // Process the order, similar to what an IPN would normally do.
    // This is for internal, manual processes like C.O.D. or Prepayment orders
    $gw_name = SHOP_getVar($_POST, 'gateway', 'string', 'check');
    $gw = \Shop\Gateway::getInstance($gw_name);
    if ($gw !== NULL && $gw->allowNoIPN()) {
        $output = $gw->handlePurchase($_POST);
        if (!empty($output)) {
            $content .= $output;
            $view = 'none';
            break;
        }
        $view = 'thanks';
        \Shop\Cart::getInstance()->Clear(false);
    }
    $view = 'products';
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
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
    $content .= $U->AddressForm($view, $A, $step);
    break;

case 'order':
    echo "deprecated";die;
    // View a completed order record
    $order = \Shop\Order::getInstance($actionval);
    if ($order->canView()) {
        $content .= $order->View();
    } else {
        COM_404();
    }
    break;

case 'packinglist':
case 'printorder':
    echo "deprecated";die;
    // Display a printed order or packing list and exit.
    // This is expected to be shown in a _blank browser window/tab.
    $order = \Shop\Order::getInstance($actionval);
    if ($order->canView()) {
        echo $order->View($view);
        exit;
    } else {
        COM_404();
    }
    break;

case 'vieworder':
    echo "deprecated";die;
    if ($_SHOP_CONF['anon_buy'] == 1 || !COM_isAnonUser()) {
        \Shop\Cart::setSession('prevpage', $view);
        $content .= \Shop\Cart::getInstance()->View($view);
        $page_title = $LANG_SHOP['vieworder'];
    } else {
        COM_404();
    }
    break;

case 'pidetail':
    // Show detail for a plugin item wrapped in the catalog layout
    $item = explode(':', $actionval);
    $status = LGLIB_invokeService($item[0], 'getDetailPage',
        array(
            'item_id' => $actionval,
        ),
        $output,
        $svc_msg
    );
    if ($status != PLG_RET_OK) {
        $output = $LANG_SHOP['item_not_found'];
    }
    $content .= Shop\Menu::pageTitle();
    $content .= $output;
    break;

case 'cart':
case 'viewcart':
    // If a cart ID is supplied, probably coming from a cancelled purchase.
    // Restore cart since the payment was not processed.
    echo "DEPRECATED";die;
    SHOP_setUrl($_SERVER['request_uri']);
    $cid = SHOP_getVar($_REQUEST, 'cid');
    if (!empty($cid)) {
        Shop\Cart::getInstance($cid)->setFinal('cart');
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

case 'checkoutcart':
    echo "DEPRECATED";die;
    $content .= \Shop\Cart::getInstance()->View(5);
    break;

case 'orderhist':
    $Report = Shop\Report::getInstance('orderlist');
    $Report->setUid();
    $content = $Report->Render();
    break;

case 'products':
default:
    SHOP_setUrl();
    $cat_id = SHOP_getVar($_REQUEST, 'category', 'mixed');
    $brand_id = SHOP_getVar($_REQUEST, 'brand', 'integer');
    $Cat = new Shop\Catalog;
    if (isset($_REQUEST['query']) && !isset($_REQUEST['clearsearch'])) {
        $Cat->withQuery($_REQUEST['query']);
    }
    $content .= $Cat->setCatID($cat_id)
        ->setBrandID($brand_id)
        ->defaultCatalog();
    break;

case 'none':
    // Add nothing, useful if the view is handled by the action above
    break;
}

$display .= \Shop\Menu::pageTitle($page_title);
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;
exit;

?>
