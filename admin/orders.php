<?php
/**
 * Manage orders.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2023 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v1.2.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import Required glFusion libraries */
require_once('../../../lib-common.php');
use Shop\Log;

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check('shop.admin')
) {
    COM_404();
    exit;
}

require_once('../../auth.inc.php');
USES_lib_admin();
$Request = Shop\Models\Request::getInstance();
$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($Request['msg'])) $msg[] = $Request->getString('msg');

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$expected = array(
    // Actions to perform
    'delete', 'oi_update', 'oi_delete', 'uid_update', 'oi_add',
    // Views to display
    'packinglist', 'edit', 'shipments', 'list', 'order',
    'oi_add_form', 'ord_addr_update',
);
list($action, $actionval) = $Request->getAction($expected, 'orders');
$view = $action;

switch ($action) {
case 'delete':
    $S = new Shop\Shipment($actionval);
    $S->Delete();
    $url = SHOP_getUrl(SHOP_ADMIN_URL . '/shipments.php');
    echo COM_refresh($url);
    break;

case 'updstatus':
    $newstatus = $Request->getString('newstatus');
    if ($newstatus == '') {
        break;
    }
    $orders = $Request->getArray('orders');
    $oldstatus = $Request->getArray('oldstatus');
    foreach ($orders as $id=>$order_id) {
        if (!isset($oldstatus[$order_id]) || $oldstatus[$order_id] != $newstatus) {
            $Order = Shop\Order::getInstance($order_id);
            if (!$Order->isNew) {
                $Order->updateStatus($newstatus);
                Log::info("Updated order $order_id from {$oldstatus[$order_id]} to $newstatus");
            }
        }
    }
    $actionval = $Request->getString('run');
    if ($actionval != '') {
        $view = 'run';
    }
    break;

case 'oi_add_form':
    $item_id = $Request->getInt('item_id');
    if (!empty($item_id)) {
        $P = Shop\Product::getByID($item_id);
        $P->isAdminAdding = true;
        $content .= $P->withOrderId($Request->getString('order_id'))->Detail();
    }
    break;

case 'ord_addr_update':
    $save = false;
    $order_id = $Request->getString('order_id');
    $addr_type = $Request->getString('ad_type');
    $Order = Shop\Order::getInstance($order_id);
    if ($Order->getOrderId() == $order_id) {
        $Addr = new Shop\Address;
        $Addr->fromArray($Request->toArray());
        if ($addr_type == 'billto') {
            $Orig = $Order->getBillto();
            if (!$Addr->Matches($Orig)) {
                $Order->setBillto($Addr);
                $Order->Save();
            }
        } elseif ($addr_type == 'shipto') {
            $Orig = $Order->getShipto();
            if (!$Addr->Matches($Orig)) {
                $Order->setShipto($Addr);
                $Order->Save();
            }
        }
    }
    echo COM_refresh(Shop\Config::get('admin_url') . '/orders.php?order=' . $order_id);
    break;

case 'oi_add':      // Add an item to a customer's order
    $order_id = $Request->getString('order_id');
    $item_number = $Request->getString('item_number');
    $P = Shop\Product::getByID($item_number);
    if ($P->isNew()) {
        // Invalid product ID passed
        //echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }
    $item_name = $Request->getString('item_name', $P->getName());
    $Order = Shop\Order::getInstance($order_id);
    $req_qty = $Request->getInt('quantity', $P->getMinOrderQty());
    $unique = $Request->getInt('_unique', $P->isUnique());
    if ($unique && $Order->Contains($Request->getString('item_number')) !== false) {
        // Do nothing if only one item instance may be added
        /*$output = array(
            'content' => phpblock_shop_cart_contents(),
            'statusMessage' => 'Only one instance of this item may be added.',
            'ret_url' => $Request->getString('_ret_url'),
            'unique' => true,
        );*/
        break;
    }

    $args = new Shop\Models\DataArray(array(
        'item_number'   => $item_number,     // isset ensured above
        'item_name'     => $item_name,
        'short_dscp'    => $Request->getString('short_dscp', $P->getDscp()),
        'quantity'      => $req_qty,
        'price'         => $P->getPrice(),
        'options'       => $Request->getArray('options'),
        //'cboptions'     => $Request->getArray('cboptions'),
        'extras'        => $Request->getArray('extras'),
        'tax'           => $Request->getFloat('tax'),
    ));

    $new_qty = $Order->addItemFromForm($args);
    $msg = $LANG_SHOP['msg_item_added'];
    if ($new_qty === false) {
        $msg = $LANG_SHOP['out_of_stock'];
    } elseif ($new_qty < $req_qty) {
        // TODO: better handling of adjustments.
        // This really only handles changes to the initial qty.
        $msg .= ' ' . $LANG_SHOP['qty_adjusted'];
    }
    echo COM_refresh(Shop\Config::get('admin_url') . '/orders.php?order=' . $order_id);
    break;

case 'oi_update':
    $item_id = $Request->getInt('item_number');
    $order_id = $Request->getString('order_id');
    $oi_id = $Request->getInt('oi_id');
    $OI = new Shop\OrderItem($oi_id);
    if ($OI->getId() > 0) {
        Shop\OrderItemOption::deleteItem($oi_id);
        $OI->setOptionsFromPOV($Request->getArray('options'));
        $OI->setExtras($Request->getArray('extras'));
        $OI->setBasePrice($Request->getFloat('price'));
        $OI->setQuantity($Request->getFloat('quantity'), $Request->getFloat('price'));
        $OI->setSku();
        $OI->Save();
        foreach ($OI->getOptions() as $idx=>$OIO) {
            $OIO->saveIfTainted();
        }
        // Now update the order for totals, etc.
        $Order = new Shop\Order($order_id);
        if ($Order->getOrderID() == $order_id) {
            $Order->Save();
        }
    }
    echo COM_refresh(Shop\Config::get('admin_url') . '/orders.php?order=' . $order_id);
    break;

case 'oi_delete':
    $OI = new Shop\OrderItem($actionval);
    if ($OI->getId() == $actionval) {
        $OI->Delete();
        $Order = new Shop\Order($OI->getOrderId());
        if ($Order->getOrderID() == $OI->getOrderId()) {
            $Order->Save();
        }
    }
    echo COM_refresh(Shop\Config::get('admin_url') . '/orders.php?order=' . $OI->getOrderId());
    break;

case 'uid_update':
    $Order = Shop\Order::getInstance($Request->getString('order_id'));
    $Order->setUid($Request->getInt('uid'))->Save();
    echo COM_refresh(Shop\Config::get('admin_url') . '/orders.php?order=' . $Request->getString('order_id'));
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'order':
    $Order = \Shop\Order::getInstance($actionval);
    if (!$Order->isNew()) {
        $V = (new \Shop\Views\Invoice)->withOrderId($actionval)->setAdmin(true);
        $content .= Shop\Menu::viewOrder($view, $Order);
        $content .= $V->Render();
    } else {
        $content .= SHOP_errorMessage($LANG_SHOP['item_not_found'], 'error');
    }
    break;

case 'packinglist':
    if ($actionval == 'x') {
        $shipments = $Request->getArray('shipments');
    } else {
        $shipments = $actionval;
    }
    $PL = new Shop\Views\PackingList();
    $PL->withOutput('pdf')->withShipmentId($shipments)->Render();
    break;

case 'list':
case 'orders':
default:
    $content .= Shop\Menu::adminOrders($view);
    $R = \Shop\Report::getInstance('orderlist');
    if ($R !== NULL) {
        $R->setAdmin(true);
        $R->setParams();
        $content .= $R->Render();
    }
    break;
}
$display = COM_siteHeader();
$display .= \Shop\Menu::Admin($view);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;
