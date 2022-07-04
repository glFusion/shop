<?php
/**
 * Common user-facing AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions. */
require_once '../lib-common.php';
use Shop\Log;

// Make sure this is called via Ajax
if (!COM_isAjax()) {
    COM_404();
}

$uid = (int)$_USER['uid'];
$action = SHOP_getVar($_GET, 'action');
$output = NULL;

switch ($action) {
case 'delAddress':          // Remove a shipping address
    if ($uid < 2) break;    // Not available to anonymous
    $status = Shop\Customer::getInstance($uid)->deleteAddress($_GET['addr_id']);
    $output = array(
        'status'    => $status,
    );
    break;

case 'getAddressHTML':
    if ($uid < 2) break;
    $Cart = Shop\Cart::getInstance();
    $type = SHOP_getVar($_GET, 'type');
    $id = SHOP_getVar($_GET, 'id');
    $Address = Shop\Customer::getInstance($uid)->getAddress($id);
    $output = array(
        'addr_text' => $Address->toHTML(),
    );
    if ($Address->getID() != $Cart->getAddress($type)->getID()) {
        $Cart->setAddress($Address->toArray(), $type);
    }
    break;

case 'setShipper':
    $method_id = SHOP_getVar($_POST, 'method_id', 'integer');
    $Cart = Shop\Cart::getInstance();
    $Cart->setShippingOption($method_id);
    $output = array(
        'status' => true,
    );
    break;

case 'setGCamt':
    $is_checked = SHOP_getVar($_POST, 'checked', 'string');
    $Cart = Shop\Cart::getInstance();
    if ($is_checked == 'true') {
        $amount = SHOP_getVar($_POST, 'amount', 'float');
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
    $gw_id = SHOP_getVar($_POST, 'gw_id');
    $unset_gc = SHOP_getVar($_POST, 'unset_gc', 'bool');
    $Cart = Shop\Cart::getInstance();
    $Cart->setGateway($gw_id);
    $Cart->Save(false);
    $output = array(
        'status' => true,
    );
    break;

case 'getAddress':
    if ($uid < 2) break;
    $Address = Shop\Customer::getInstance($uid)->getAddress($_GET['id']);
    $output = $Address->toJSON();
    break;

case 'cartaddone':
    $OI = Shop\OrderItem::getInstance($_POST['oi_id']);
    if ($OI->getID() == $_POST['oi_id']) {
        $qty = $OI->getQuantity();
        $OI->setQuantity($qty + (int)$_POST['qty']);
        $OI->Save();
        $Order = $OI->getOrder();
        $Order->refresh()->Save();
        $output = array(
            'new_oi_qty' => $OI->getQuantity(),
            'new_total' => $Order->getTotal(),
            'new_ext' => $OI->getPrice() * $OI->getQuantity(),
        );
    } else {
        $output = array(
            'status' => 'error',
        );
    }
    break;

case 'addcartitem':
    if (!isset($_POST['item_number'])) {
        Log::write('shop_system', Log::ERROR, "Ajax addcartitem:: Missing Item Number");
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }
    $item_number = $_POST['item_number'];     // isset ensured above
    $P = Shop\Product::getByID($item_number);
    if ($P->isNew()) {
        // Invalid product ID passed
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }
    $item_name = SHOP_getVar($_POST, 'item_name', 'string', $P->getName());
    $Cart = Shop\Cart::getInstance();
    $nonce = $Cart->makeNonce($item_number . $item_name);
    if (!isset($_POST['nonce']) || $_POST['nonce'] != $nonce) {
        Log::write('shop_system', Log::ERROR, "Bad nonce: {$_POST['nonce']} for cart {$Cart->getOrderID()}, should be $nonce");
        echo json_encode(array('content' => '', 'statusMessage' => ''));
        exit;
    }

    $req_qty = SHOP_getVar($_POST, 'quantity', 'integer', $P->getMinOrderQty());
    //$exp_qty = $Cart->getItem($item_number)->getQuantity() + $req_qty;
    $unique = SHOP_getVar($_POST, '_unique', 'integer', $P->isUnique());
    if ($unique && $Cart->Contains($_POST['item_number']) !== false) {
        // Do nothing if only one item instance may be added
        $output = array(
            'content' => phpblock_shop_cart_contents(),
            'statusMessage' => 'Only one instance of this item may be added.',
            'ret_url' => SHOP_getVar($_POST, '_ret_url', 'string', ''),
            'unique' => true,
        );
        break;
    }
    $args = array(
        'item_number'   => $item_number,     // isset ensured above
        'item_name'     => $item_name,
        'short_dscp'    => SHOP_getVar($_POST, 'short_dscp', 'string', $P->getDscp()),
        'quantity'      => $req_qty,
        'price'         => $P->getPrice(),
        'options'       => SHOP_getVar($_POST, 'options', 'array'),
        //'cboptions'     => SHOP_getVar($_POST, 'cboptions', 'array'),
        'extras'        => SHOP_getVar($_POST, 'extras', 'array'),
        'tax'           => SHOP_getVar($_POST, 'tax', 'float'),
    );

    $new_qty = $Cart->addItem($args);
    Log::write('shop_system', Log::DEBUG, "Adding $item_number, qty $new_qty");
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
        'ret_url' => SHOP_getVar($_POST, '_ret_url', 'string', ''),
        'unique' => $unique ? true : false,
    );
    break;

case 'delcartitem':
    $oi_id = SHOP_getVar($_GET, 'oi_id', 'integer');
    if ($oi_id > 0) {
        \Shop\Cart::getInstance()->Remove($oi_id);
    }
    $output = array(
        'content' => phpblock_shop_cart_contents(),
    );
    break;

case 'setShipper':
    $cart_id = SHOP_getVar($_POST, 'cart_id');
    $method_id = (int)$_POST['shipper_id'];
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
    $cart_id = SHOP_getVar($_POST, 'cart_id');
    $Order = Shop\Order::getInstance($cart_id, 0);
    $status_msg = '';
    $status = false;
    if (!$Order->isNew()) {
        $status = Shop\Gateway::getInstance($Order->getPmtMethod())
            ->processOrder($cart_id);
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
        $code = SHOP_getVar($_POST, 'gc_code');
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
    $qty = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
    $attribs = array('checkbox' => array());
    $PVI = new Shop\Models\ProductVariantInfo;
    if (isset($_GET['extras']['options'])) {
        $attribs['checkbox'] = $_GET['extras']['options'];
    }
    if (isset($_GET['options']) && is_array($_GET['options'])) {
        // Checking a product that has options, see if the variant is in stock
        $PV = Shop\ProductVariant::getByAttributes($_GET['item_number'], $_GET['options']);
        $PV->Validate($PVI, array(
            'quantity' => $qty,
        ) );
    } else {
        Shop\Product::getByID($_GET['item_number'])->Validate($PVI, array(
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
    $A1 = new Shop\Address($_POST);
    if (empty($A1->isValid())) {
        $A2 = $A1->Validate();
        if (!$A1->Matches($A2)) {
            $save_url = SHOP_getVar($_POST, 'save_url', 'string,', SHOP_URL . '/cart.php');
            $return_url = SHOP_getVar($_POST, 'return', 'string,', SHOP_URL . '/cart.php');
            $T = new Shop\Template;
            $T->set_file('popup', 'address_select.thtml');
            $T->set_var(array(
                'address1_html' => $A1->toHTML(),
                'address1_json' => htmlentities($A1->toJSON(false)),
                'address2_html' => $A2->toHTML(),
                'address2_json' => htmlentities($A2->toJSON(false)),
                'ad_type'       => $_POST['ad_type'],
//                'next_step'     => $_POST['next_step'],
                'save_url'      => $save_url,
                'return'        => $return_url,
                'save_btn_name' => SHOP_getVar($_POST, 'save_btn_name', 'string,', 'save'),
            ) );
            $output['status']  = false;
            $output['form'] = $T->parse('output', 'popup');
        }
    }
    break;

case 'getStateOpts':
    $output = array(
        'status' => true,
        'opts' => Shop\State::optionList(
            SHOP_getVar($_GET, 'country_iso', 'string', '')
        ),
    );
    break;

case 'setDefAddr':
    $addr_id = SHOP_getVar($_POST, 'addr_id', 'integer');
    $uid = SHOP_getVar($_POST, 'uid', 'integer');
    if ($addr_id < 1 || $uid < 2) {
        $ouptut = array(
            'status' => 0,
            'statusMessage' => 'Invalid address or User ID given.',
        );
        break;
    }
    $type = SHOP_getVar($_POST, 'addr_type');
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
