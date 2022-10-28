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
    !function_exists('SHOP_access_check') ||    // first ensure plugin is installed
    !SHOP_access_check()
) {
    COM_404();
    exit;
}
use Shop\Config;
use Shop\Models\Request;

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
$Request = Request::getInstance();
foreach($expected as $provided) {
    if (isset($Request[$provided])) {
        $action = $provided;
        $actionval = $Request[$provided];
        break;
    }
}
$id = $Request->getString('id');
$content = '';

switch ($action) {
case 'thanks':
    // Allow for no thanksVars function
    $parts = explode('/', $actionval);
    $gw_name = $parts[0];
    $order_id = isset($parts[1]) ? $parts[1] : '';
    $token = isset($parts[2]) ? $parts[2] : '';
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
            'payment_date'  => $Request->getString('payment_date'),
            'currency'      => $Request->getString('mc_currency'),
            'mc_gross'      => $Request->getFloat('mc_gross'),
            'shop_url'      => Config::get('url'),
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
    $gw_name = UrlArgs->getString('gateway', 'check');
    $gw = \Shop\Gateway::getInstance($gw_name);
    if ($gw !== NULL && $gw->allowNoIPN()) {
        $output = $gw->handlePurchase($Request->toArray());  // TODO: change to $Request native
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
    if (isset($Request['address'])) {
        $A = $Request->getArray('address');
    } elseif ($view == 'billto') {
        $A = Shop\Cart::getInstance()->getBillto()->toArray();
    } else {
        $A = Shop\Cart::getInstance()->getShipto()->toArray();
    }
    $content .= $U->AddressForm($view, $A, $step);
    break;

case 'pidetail':
    // Show detail for a plugin item wrapped in the catalog layout
    $item = explode(':', $actionval);
    $status = PLG_callFunctionForOnePlugin(
        'service_getDetailPage_' . $item[0],
        array(
            1 => array('item_id' => $actionval),
            2 => &$output,
            3 => &$svc_msg,
        )
    );
    if ($status != PLG_RET_OK) {
        $output = $LANG_SHOP['item_not_found'];
    }
    $content .= Shop\Menu::pageTitle();
    $content .= $output;
    break;

case 'orderhist':
    $Report = Shop\Report::getInstance('orderlist');
    $Report->setUid();
    $content = $Report->Render();
    break;

case 'products':
default:
    SHOP_setUrl();
    if (Shop\Config::get('catalog_enabled')) {
        $cat_id = $Request->getRaw('category');
        $brand_id = $Request->getInt('brand');
        $Cat = new Shop\Views\Catalog;
        if (isset($_REQUEST['query']) && !isset($_REQUEST['clearsearch'])) {
            $Cat->withQuery($_REQUEST['query']);
        }
        $content .= $Cat->setCatID($cat_id)
            ->setBrandID($brand_id)
            ->defaultCatalog();
    } else {
        COM_404();
    }
    break;

case 'none':
    // Add nothing, useful if the view is handled by the action above
    break;
}
$display = \Shop\Menu::siteHeader();
$display .= \Shop\Menu::pageTitle($page_title);
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;
exit;
