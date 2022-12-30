<?php
/**
 * Common user-facing AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions. */
require_once '../lib-common.php';
use Shop\Log;
use Shop\Models\DataArray;
use Shop\Models\Request;

$Request = Request::getInstance();
// Make sure this is called via Ajax
if (!$Request->isAjax()) {
    COM_404();
}

$uid = (int)$_USER['uid'];

$action = $Request->getString('action');
$output = NULL;

switch ($action) {
case 'delAddress':          // Remove a shipping address
    if ($uid < 2) break;    // Not available to anonymous
    $status = Shop\Customer::getInstance($uid)->deleteAddress($Request->getInt('addr_id'));
    $output = array(
        'status'    => $status,
    );
    break;

case 'getAddressHTML':
    if ($uid < 2) break;
    $Cart = Shop\Cart::getInstance();
    $type = $Request->getString('type');
    $id = $Request->getInt('id');
    $Address = Shop\Customer::getInstance($uid)->getAddress($id);
    $output = array(
        'addr_text' => $Address->toHTML(),
    );
    if ($Address->getID() != $Cart->getAddress($type)->getID()) {
        $Cart->setAddress($Address, $type);
    }
    break;

case 'setShipper':
    $method_id = $Request->getInt('method_id');
    $Cart = Shop\Cart::getInstance();
    $Cart->setShippingOption($method_id);
    $output = array(
        'status' => true,
    );
    break;

case 'setGCamt':
    $is_checked = $Request->getString('checked');
    $Cart = Shop\Cart::getInstance();
    if ($is_checked == 'true') {
        $amount = $Request->getFloat('amount');
        $Cart->setByGC($amount);
    } else {
        $Cart->setByGC(0);
    }
    $Cart->Save(false);
    $output = array(
        'status' => true,
    );
    break;

case 'setGW':
    $gw_id = $Request->getString('gw_id');
    $unset_gc = $Request->getInt('unset_gc');
    $Cart = Shop\Cart::getInstance();
    $Cart->setGateway($gw_id);
    $Cart->Save(false);
    $output = array(
        'status' => true,
    );
    break;

case 'getAddress':
    if ($uid < 2) break;
    $Address = Shop\Customer::getInstance($uid)->getAddress($Request->getInt('id'));
    $output = $Address->toJSON();
    break;

case 'cartaddone':
    $oi_id = $Request->getInt('oi_id');
    $OI = Shop\OrderItem::getInstance($oi_id);
    if ($OI->getID() == $oi_id) {
        // Order item exists
        $qty = $OI->getQuantity();
        $OI->setQuantity($qty + $Request->getInt('qty'));
        $OI->Save();
        $Order = $OI->getOrder();
        $Order->refresh()->Save();
        $output = array(
            'new_oi_qty' => $OI->getQuantity(),
            'new_total' => $Order->getTotal(),
            'new_ext' => $OI->getPrice() * $OI->getQuantity(),
        );
    } else {
        // Error, orderitem doesn't exist
        $output = array(
            'status' => 'error',
        );
    }
    break;

case 'addcartitem':
    if (!isset($Request['item_number'])) {
        Log::error("Ajax addcartitem:: Missing Item Number");
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }
    $item_number = $Request['item_number'];     // isset ensured above
    $P = Shop\Product::getByID($item_number);
    if ($P->isNew()) {
        // Invalid product ID passed
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }
    $item_name = $Request->getString('item_name', $P->getName());
    $Cart = Shop\Cart::getInstance();
    /*$nonce = $Cart->makeNonce($item_number . $item_name);
    $supplied_nonce = $Request->getString('nonce');
    if ($supplied_nonce != $nonce) {
        Log::error("Bad nonce: {$supplied_nonce} for cart {$Cart->getOrderID()}, should be $nonce");
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }*/

    $req_qty = $Request->getInt('quantity', $P->getMinOrderQty());
    //$exp_qty = $Cart->getItem($item_number)->getQuantity() + $req_qty;
    $unique = $Request->getInt('_unique', $P->isUnique());
    if ($unique && $Cart->Contains($Request->getString('item_number')) !== false) {
        // Do nothing if only one item instance may be added
        $output = array(
            'content' => phpblock_shop_cart_contents(),
            'statusMessage' => 'Only one instance of this item may be added.',
            'ret_url' => $Request->getString('_ret_url'),
            'unique' => true,
        );
        break;
    }
    $args = new DataArray(array(
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

    $new_qty = $Cart->addItem($args);
    $msg = $LANG_SHOP['msg_item_added'];
    if ($new_qty === false) {
        $msg = $LANG_SHOP['out_of_stock'];
    } elseif ($new_qty < $req_qty) {
        // TODO: better handling of adjustments.
        // This really only handles changes to the initial qty.
        $msg .= ' ' . $LANG_SHOP['qty_adjusted'];
    }
    $output = array(
        'content' => phpblock_shop_cart_contents(),
        'statusMessage' => $msg,
        'ret_url' => $Request->getString('_ret_url'),
        'unique' => $unique ? true : false,
    );
    break;

case 'delcartitem':
    $oi_id = $Request->getInt('oi_id');
    if ($oi_id > 0) {
        \Shop\Cart::getInstance()->Remove($oi_id);
    }
    $output = array(
        'content' => phpblock_shop_cart_contents(),
    );
    break;

case 'setShipper':
    $cart_id = $Request->getString('cart_id');
    $method_id = $Request->getInt('shipper_id');
    $ship_methods = SESS_getVar('shop.shiprate.' . $cart_id);
    if (!isset($ship_methods[$method_id])) {
        $status = false;
        $method = NULL;
    } else {
        $method = $ship_methods[$method_id];
        $status = Shop\Cart::getInstance($cart_id)
            ->setShipper($method)
            ->Save();
        $status = true;
    }
    $output = array(
        'status' => $status,
        'method' => $method,
    );
    break;

case 'finalizecart':
    $cart_id = $Request->getString('cart_id');
    $Order = Shop\Order::getInstance($cart_id, 0);
    $status_msg = '';
    $status = false;
    if (!$Order->isNew()) {
        $status = Shop\Gateway::getInstance($Order->getPmtMethod())
            ->processOrder($Order);
        if (!$status) {
            $Order->setFinal();
        }
    }
    $output = array(
        'status' => $status,
        'statusMessage' => $status_msg,
    );
    break;

case 'redeem_gc':
    if (COM_isAnonUser()) {
        $A = array(
            'statusMessage' => $LANG_SHOP['gc_need_acct'],
            'html' => '',
            'status' => false,
        );
    } else {
        $code = $Request->getString('gc_code');
        $uid = $_USER['uid'];
        list($status, $status_msg) = Shop\Products\Coupon::Redeem($code, $uid);
        $gw = Shop\Gateway::getInstance('_coupon');
        $gw_radio = $gw->checkoutRadio($status == 0 ? true : false);
        $output = array (
            'statusMessage' => $status_msg,
            'html' => $gw_radio,
            'status' => $status,
        );
    }
    break;

case 'validateOpts':
    $item_number = $Request->getString('item_number');
    $qty = $Request->getInt('quantity', 1);
    $attribs = array('checkbox' => array());
    $PVI = new Shop\Models\ProductVariantInfo;
    $Extras = $Request->getArray('extras');
    if (isset($Extras['options'])) {
        $attribs['checkbox'] = $Extras['options'];
    }
    $Options = $Request->getArray('options');
    if (!empty($Options)) {
        // Checking a product that has options, see if the variant is in stock
        $PV = Shop\ProductVariant::getByAttributes($item_number, $Options);
        $PV->Validate($PVI, array(
            'quantity' => $qty,
        ) );
    } else {
        Shop\Product::getByID($item_number)->Validate($PVI, array(
            'quantity' => $qty,
            'checkbox' => $attribs['checkbox'],
        ) );
    }
    $output = $PVI->toArray();
    break;

case 'validateAddress':
    // Validate customer-entered addresses and present a popup selection
    // between the original and validated versions, if different.
    $output = array(
        'status'    => true,
        'form'      => '',
    );
    $A1 = new Shop\Address($Request->toArray());
    if (empty($A1->isValid())) {
        $A2 = $A1->Validate();
        if (!$A1->Matches($A2)) {
            $save_url = $Request->getString('save_url', SHOP_URL . '/cart.php');
            $return_url = $Request->getString('return', SHOP_URL . '/cart.php');
            $T = new Shop\Template;
            $T->set_file('popup', 'address_select.thtml');
            $T->set_var(array(
                'address1_html' => $A1->toHTML(),
                'address1_json' => htmlentities($A1->toJSON()),
                'address2_html' => $A2->toHTML(),
                'address2_json' => htmlentities($A2->toJSON()),
                'ad_type'       => $Request->getString('ad_type'),
//                'next_step'     => $Request->getString('next_step'),
                'save_url'      => $save_url,
                'return'        => $return_url,
                'save_btn_name' => $Request->getString('save_btn_name', 'save'),
            ) );
            $output['status']  = false;
            $output['form'] = $T->parse('output', 'popup');
        }
    }
    break;

case 'getStateOpts':
    $output = array(
        'status' => true,
        'opts' => Shop\State::optionList($Request->getString('country_iso')),
    );
    break;

case 'setDefAddr':
    $addr_id = $Request->getInt('addr_id');
    $uid = $Request->getInt('uid');
    if ($addr_id < 1 || $uid < 2) {
        $ouptut = array(
            'status' => 0,
            'statusMessage' => 'Invalid address or User ID given.',
        );
        break;
    }
    $type = $Request->getString('addr_type');
    $status = Shop\Address::getInstance($addr_id)
        ->setDefault($type)
        ->Save();
    $output = array(
        'status' => $status,
        'statusMessage' => $status ? 'Address Updated' : 'An error occurred',
    );
    break;

default:
    // Missing action, nothing to do
    break;
}

if ($output === NULL) {
    $ouptut = array('status' => false);
}

header('Content-Type: application/json');
header("Cache-Control: no-cache, must-revalidate");
//A date in the past
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
if (is_array($output)) {
    echo json_encode($output);
} else {
    echo $output;
}
