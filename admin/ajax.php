<?php
/**
 * Common admistrative AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2023 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only.  It's called by Javascript,
// so don't try to display a message
if (!plugin_ismoderator_shop()) {
    COM_errorLog("User {$_USER['username']} tried to illegally access the shop admin ajax function.");
    $retval = array(
        'status' => false,
        'statusMessage' => $LANG_SHOP['access_denied'],
    );
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
    exit;
}

$Request = Shop\Models\Request::getInstance();
$action = $Request->getString('action');
$title = NULL;      // title attribute to be set
switch ($action) {
case 'newPXF':      // add a product->feature mapping
    $prod_id = $Request->getInt('prod_id');
    $ft_id = $Request->getInt('ft_id');
    $fv_id = $Request->getInt('fv_id');
    $fv_text = $Request->getString('fv_text');
    if (
        $prod_id > 0 &&
        $ft_id > 0 &&
        ($fv_id > 0 || !empty($fv_text))
    ) {
        if (!empty($fv_text)) {
            $fv_id = 0;
        }
        $retval = array(
            'status' => true, //Shop\Feature::getInstance($ft_id)->addProduct($prod_id, $fv_id, $fv_text),
            'ft_name' => Shop\Feature::getInstance($ft_id)->getName(),
            'ft_val' => $ft_id,
            'ft_opts' => Shop\Feature::optionList($ft_id),
            'fv_opts' => Shop\FeatureValue::optionList($ft_id, $fv_id),
            'fv_custom' => $fv_text,
        );
    } else {
        $retval = array(
            'status' => false,
        );
    }
    break;

case 'getFVopts':
    $ft_id = $Request->getInt('ft_id');
    $fv_id = $Request->getInt('fv_id');
    if ($ft_id > 0) {
        $retval = array(
            'status' => true,
            'options' => COM_optionList(
                $_TABLES['shop.features_values'],
                'fv_id,fv_value',
                1,
                $fv_id,
                "ft_id = $ft_id"
            ),
        );
    } else {
        $retval = array(
            'status' => false,
            'options' => '',
        );
    }
    break;

case 'delFV':
    $retval = array(
        'status' => false,
        'statusMessage' => 'An error occurred',
    );
    $fv_id = $Request->getInt('fv_id');
    if ($fv_id > 0) {
        $retval = array(
            'status' => Shop\FeatureValue::Delete($fv_id),
            'statusMessage' => 'Record Deleted',
        );
    }
    break;

case 'newFV':       // Add a new feature value
    $retval = array(
        'status' => false,
        'statusMessage' => 'An error occurred',
        'fv_id' => 0,
        'fv_text' => '',
    );
    $ft_id = $Request->getInt('ft_id');
    $fv_text = $Request->getString('fv_text');
    if (!empty($fv_text)) {
        $FV = new Shop\FeatureValue;
        $FV->setValue($fv_text)
           ->setFeatureID($ft_id);
        $status = $FV->Save();
        if ($status) {
            $retval['status'] = true;
            $retval['statusMessage'] = 'Success';
            $retval['fv_id'] = $FV->getID();
            $retval['fv_text'] = $FV->getValue();
        }
    } else {
        $status['statusMessage'] = 'Feature Value Text cannot be empty';
    }
    break;

case 'orderImages':
    $retval = array(
        'status' => false,
        'statusMessage' => 'Not implemented',
    );
    break;

case 'dropupload_cat':
    // Handle a drag-and-drop image upload for categories
    $cat_id = $Request->getInt('cat_id');
    $nonce = $Request->getString('nonce');
    $retval = array(
        'status'    => true,    // assume OK
        'statusMessage' => '',
        'filenames' => array(),
    );

    // Handle image uploads.  This is done last because we need
    // the product id to name the images filenames.
    if (!empty($_FILES['files'])) {
        $sent = count($_FILES['files']['name']);
        $U = new Shop\Images\Category($cat_id, 'files');
        $U->setNonce($nonce);
        $filenames = $U->uploadFiles();
        $processed = count($filenames);
        // Only one filename here, this to get the image id also
        foreach ($filenames as $filename) {
            $retval['filenames'][] = array(
                'img_url'   => Shop\Images\Category::getUrl($filename)['url'],
                'thumb_url' => Shop\Images\Category::getThumbUrl($filename)['url'],
                'filename'  => $filename,
            );
            break;      // There should be only one image for categories
        }
        $msg = '<ul>';
        foreach ($U->getErrors() as $err) {
            $msg .= '<li>' . $err . '</li>';
        }
        $msg .= '<li>' . sprintf($LANG_SHOP['x_of_y_uploaded'], $processed, $sent) . '</li>';
        $msg .= '</ul>';
        $retval['statusMessage'] = $msg;
        Shop\Cache::clear('shop.categories');
    } else {
        $retval['status'] = false;
        $retval['statusMessage'] = $LANG_SHOP['no_files_uploaded'];
    }
    break;

case 'dropupload':
    // Handle a drag-and-drop image upload
    $item_id = $Request->getInt('item_id');
    $nonce = $Request->getString('nonce');
    $retval = array(
        'status'    => true,    // assume OK
        'statusMessage' => '',
        'filenames' => array(),
    );

    // Handle image uploads.  This is done last because we need
    // the product id to name the images filenames.
    if (!empty($_FILES['files'])) {
        $sent = count($_FILES['files']['name']);
        $U = new Shop\Images\Product($item_id, 'files');
        $U->setNonce($nonce);
        $filenames = $U->uploadFiles();
        $processed = count($filenames);
        // Only one filename here, this to get the image id also
        foreach ($filenames as $img_id=>$filename) {
            $retval['filenames'][] = array(
                'img_url'   => Shop\Images\Product::getUrl($filename)['url'],
                'thumb_url' => Shop\Images\Product::getThumbUrl($filename)['url'],
                'img_id' => $img_id,
            );
        }
        $msg = '<ul>';
        foreach ($U->getErrors() as $err) {
            $msg .= '<li>' . $err . '</li>';
        }
        $msg .= '<li>' . sprintf($LANG_SHOP['x_of_y_uploaded'], $processed, $sent) . '</li>';
        $msg .= '</ul>';
        $retval['statusMessage'] = $msg;
        Shop\Cache::clear('shop.products');
    } else {
        $retval['status'] = false;
        $retval['statusMessage'] = $LANG_SHOP['no_files_uploaded'];
    }
    break;

case 'opt_orderby_opts':
    // Get the attrubute "orderby" options when the attribute group or item ID
    // is changed.
    $og_id = $Request->getInt('og_id');
    $selected = $Request->getInt('selected');
    $retval = Shop\ProductOptionValue::getOrderbyOpts($og_id, $selected);
    // This function gets JSON from the object.
    echo $retval;
    exit;

case 'updatestatus':
    $newstatus = $Request->getString('newstatus');
    $order_id = $Request->getString('order_id');
    if (!empty($order_id) && !empty($newstatus)) {
        $showlog = $Request->getInt('showlog');
        $ord = \Shop\Order::getInstance($order_id);
        if ($ord->isNew())  {     // non-existant order
            $L = array(
                'showlog' => 0,
            );
        } else {
            $L['showlog'] = $showlog;
            $L['ts'] = $_CONF['_now']->format($_SHOP_CONF['datetime_fmt'], true);
            $L['order_id'] = $order_id;
            $L['username'] = COM_getDisplayName($_USER['uid']) . ' (' . $_USER['uid'] . ')';
            $L['statusMessage'] = NULL;
            $oldstatus = $ord->getStatus();
            if ($ord->updateStatus($newstatus) != $oldstatus) {
                $L['newstatus'] = $newstatus;
                $L['statusMessage'] = sprintf($LANG_SHOP['status_changed'], $oldstatus, $newstatus);
                $L['message'] = $L['statusMessage'];
            }
            $comment = $Request->getString('comment');
            if (!empty($comment && $ord->Log($comment))) {
                $L['comment'] = $comment;
                if (empty($L['statusMessage'])) {
                    $L['statusMessage'] = 'Comment Added';
                }
            }
        }
        $retval = $L;
        break;
    }
    exit;       // do nothing if nothing was logged
    break;

case 'delimage_cat':
    // Delete a product image from the product edit form.
    $cat_id = $Request->getInt('cat_id');
    $nonce = $Request->getString('nonce');
    $retval = array(
        'cat_id'    => $cat_id,
        'status'    => \Shop\Images\Category::DeleteImage($cat_id, $nonce),
    );
    break;

case 'delimage':
    // Delete a product image from the product edit form.
    $img_id = $Request->getInt('img_id');
    $retval = array(
        'img_id'    => $img_id,
        'status'    => \Shop\Images\Product::DeleteImage($img_id),
    );
    break;

case 'setDefImg':
    // Set an image as the default.
    $img_id = $Request->getInt('img_id');
    $prod_id = $Request->getInt('prod_id');
    $retval = array(
        'img_id'    => $img_id,
        'status'    => \Shop\Images\Product::setAsDefault($img_id, $prod_id),
    );
    break;

case 'add_tracking':
    $retval = array('status' => false);
    $shipment_id = $Request->getInt('shipment_id');
    if ($shipment_id > 0) {
        $SP = new Shop\ShipmentPackage();
        if ($SP->Save($Request)) {
            if ($SP->getShipperID() > 0) {
                $shipper_code = Shop\Shipper::getInstance($SP->getShipperID())->getCode();
                $tracking_url = Shop\Shipper::getInstance($SP->getShipperID())->getTrackingUrl($SP->getTrackingNumber());
            } else {
                $shipper_code = '';
                $tracking_url = '';
            }
            $retval = array(
                'status'        => true,
                'shipper_id'    => $SP->getShipperID(),
                'pkg_id'        => $SP->getID(),
                'shipper_name'  => $SP->getShipperInfo(),
                'tracking_num'  => $SP->getTrackingNumber(),
                'shipper_code'  => $shipper_code,
                'tracking_url'  => $tracking_url,
            );
        } else {
            $retval['statusMessage'] = $LANG_SHOP['err_invalid_form'];
        }
    } else {
        $retval['statusMessage'] = $LANG_SHOP['err_invalid_form'];
    }
    break;

case 'del_tracking':
    $pkg_id = $Request->getInt('pkg_id');
    if ($pkg_id > 0) {
        Shop\ShipmentPackage::Delete($pkg_id);
    }
    $retval = array(
        'status' => true,
    );
    break;

case 'void':
    $item_id = $Request->getInt('item_id');
    $newval = $Request->getString('newval');    // should be "void" or "valid"
    switch ($Request->getString('component')) {
    case 'coupon':
        $status = Shop\Products\Coupon::Void($item_id, $newval);
        break;
    }
    if ($status) {
        if ($newval == Shop\Products\Coupon::VOID) {
            $next_val = Shop\Products\Coupon::VALID;
            $confirm_txt = $LANG_SHOP['q_confirm_unvoid'];
            $title = $LANG_SHOP['unvoid_item'];
            $btn_cls = 'uk-button-danger';
        } else {
            $next_val = Shop\Products\Coupon::VOID;
            $confirm_txt = $LANG_SHOP['q_confirm_void'];
            $title = $LANG_SHOP['void_item'];
            $btn_cls = 'uk-button-success';
        }
        $retval = array(
            'status'        => $status,
            'statusMessage' => $LANG_SHOP['msg_updated'],
            'text'          => isset($LANG_SHOP[$newval]) ? $LANG_SHOP[$newval] : 'Unknown',
            'newclass'      => $btn_cls,
            'onclick_val'   => $next_val,
            'confirm_txt'   => $confirm_txt,
            'title'         => $title,
        );
    } else {
        $retval = array(
            'status' => $status,
            'statusMessage' => $LANG_SHOP['err_msg'],
            'text' => $LANG_SHOP['valid'],
        );
    }
    break;

case 'getUnpaidOrders':
    // Get all unpaid orders for a specific user ID
    $uid = $Request->getInt('uid');
    $Orders = Shop\Order::getUnpaid($uid);
    $retval = array();
    foreach ($Orders as $Order) {
        $retval[$Order->getID()] = array(
            'total' => $Order->getTotal(),
            'amt_paid' => $Order->getAmountPaid(),
        );
    }
    break;

case 'uid_edit':
    $T = new Shop\Template('admin/order');
    $T->set_file('form', 'uid_edit.thtml');
    $T->set_var(array(
        'order_id' => $Request->getString('order_id'),
        'purch_name' => $Request->getString('name'),
        'purch_uid' => $Request->getInt('uid'),
        'buyer_select' => Shop\Customer::getOptionList($Request->getInt('uid')),
    ) );
    $T->parse('output', 'form');
    $retval = array(
        'status' => true,
        'form' => $T->finish($T->get_var('output')),
    );
    break;

case 'oi_add':          // select an item to add to a customer's order
    $T = new Shop\Template('admin/order');
    $T->set_file('form', 'oi_add.thtml');
    $T->set_var(array(
        'order_id' => $Request->getString('order_id'),
        'product_select' => Shop\Product::getOptionList($Request->getInt('uid')),
    ) );
    $T->parse('output', 'form');
    $retval = array(
        'status' => true,
        'form' => $T->finish($T->get_var('output')),
    );
    break;

case 'oi_edit':
    $OI = new Shop\OrderItem($Request->getInt('oi_id'));
    $retval = array(
        'status' => true,       // maybe check form validity later
        'form' => $OI->edit(),
    );
    break;

case 'ord_instr_edit':
    $order_id = $Request->getString('order_id');
    $Order = Shop\Order::getInstance($order_id);
    if ($Order->getOrderId() == $order_id) {
        $T = new Shop\Template('admin/order');
        $T->set_file('form', 'instr_edit.thtml');
        $T->set_var(array(
            'order_id' => $Request->getString('order_id'),
            'instructions' => $Order->getInstructions(),
        ) );
        $T->parse('output', 'form');
        $retval = array(
            'status' => true,
            'form' => $T->finish($T->get_var('output')),
        );
    } else {
        $retval = array(
            'status' => false,
            'form' => '',
        );
    }
    break;

case 'ord_addr_edit':
    $retval = array(
        'status' => false,
        'title' => $LANG_ADMIN['edit'] . ': ',
        'form' => '',
    );
    $addr_type = $Request->getString('type');
    $order_id = $Request->getString('order_id');
    $Order = Shop\Order::getInstance($order_id);
    if ($Order->getOrderId() == $order_id) {
        $Addr = $Order->getAddress($addr_type);
        $T = new Shop\Template('admin/order');
        $T->set_file('form', 'addr_edit.thtml');
        $T->set_var(array(
            'addr_id' => -1,         // customizing the address
            'name' => $Addr->getName(),
            'company' => $Addr->getCompany(),
            'address1' => $Addr->getAddress1(),
            'address2' => $Addr->getAddress2(),
            'city' => $Addr->getCity(),
            'state' => $Addr->getState(),
            'zip' => $Addr->getPostal(),
            'country_options' => Shop\Country::optionList($Addr->getCountry()),
            'state_options' => Shop\State::optionList($Addr->getCountry(), $Addr->getState()),
            'phone' => $Addr->getPhone(),
            'pi_admin_url' => Shop\Config::get('admin_url'),
            'order_id' => $order_id,
            'ad_type' => $addr_type,
        ) );
        $T->parse('output', 'form');
        $retval['status'] = true;
        $retval['form'] = $T->finish($T->get_var('output'));
        $retval['title'] .= $LANG_SHOP[$addr_type];
    }
    break;

case 'toggle':
    $oldval = $Request->getInt('oldval');
    $id = $Request->getInt('id');
    $type = $Request->getString('type');
    $component = $Request->getString('component');
    switch ($component) {
    case 'product':
        switch ($type) {
        case 'enabled':
            $newval = \Shop\Product::toggleEnabled($oldval, $id);
            break;

        case 'featured':
            $newval = \Shop\Product::toggleFeatured($oldval, $id);
            break;
         default:
            exit;
        }
        break;

    case 'category':
        switch ($type) {
        case 'enabled':
            $newval = \Shop\Category::toggleEnabled($oldval, $id);
            break;
         default:
            exit;
        }
        break;

    case 'option':
        switch ($type) {
        case 'enabled':
            $newval = \Shop\ProductOptionValue::toggleEnabled($oldval, $id);
            break;
         default:
            exit;
        }
        break;

    case 'variant':
        switch ($type) {
        case 'enabled':
            $newval = \Shop\ProductVariant::toggleEnabled($oldval, $id);
            break;
         default:
            exit;
        }
        break;

    case 'shipping':
        switch ($type) {
        case 'enabled':
            $newval = \Shop\Shipper::toggleEnabled($oldval, $id);
            break;
         default:
            exit;
        }
       break;

    case 'gateway':
        $id = $Request->getString('id');
        switch ($type) {
        case 'enabled':
            $newval = \Shop\Gateway::toggleEnabled($oldval, $id);
            if ($newval == 1) {
                $title = $LANG_SHOP['ck_to_disable'];
            } else {
                $title = $LANG_SHOP['ck_to_enable'];
            }
            break;
        case 'buy_now':
            $newval = \Shop\Gateway::toggleBuyNow($oldval, $id);
            break;
        case 'donation':
            $newval = \Shop\Gateway::toggleDonation($oldval, $id);
            break;
        default:
            exit;
        }
        break;

    case 'workflow':
        $wf = \Shop\Workflow::getInstance($id);
        if (!$wf) break;
        $newval = $oldval;
        $Request['oldval'] = $wf->enabled;
        switch ($type) {
        case 'enabled':
            $newval = \Shop\Workflow::setValue($id, $type, $newval);
            break;
        default:
            exit;
        }
        break;

    case 'orderstatus':
        switch ($type) {
        case 'enabled':
        case 'notify_buyer':
        case 'notify_admin':
        case 'order_valid':
        case 'order_closed':
        case 'aff_eligible':
        case 'cust_viewable':
            $newval = Shop\Models\OrderStatus::Toggle($oldval, $type, $id);
            break;
        default:
            exit;
        }
        break;

    case 'zone_rule':
        $newval = Shop\Rules\Zone::Toggle($oldval, $type, $id);
        break;

    case 'supplier':
        $newval = Shop\Supplier::Toggle($oldval, $type, $id);
        break;

    case 'region':
        $newval = Shop\Region::Toggle($oldval, $type, array($id));
        break;

    case 'country':
        $newval = \Shop\Country::Toggle($oldval, $type, array($id));
        break;

    case 'state':
        $newval = \Shop\State::Toggle($oldval, $type, array($id));
        break;

    case 'pi_product':
        $newval = \Shop\Products\Plugin::Toggle($oldval, $type, $id);
        break;

    default:
        exit;
    }

    // Common output for all toggle functions.
    $retval = array(
        'id'    => $id,
        'type'  => $type,
        'component' => $component,
        'newval'    => $newval,
        'statusMessage' => $newval != $oldval ?
            $LANG_SHOP['msg_updated'] : $LANG_SHOP['msg_nochange'],
        'title' => $title,
    );
    break;

case 'getCarrierPkgInfo':
    $retval = array(
        'carrier_id' => '',
        'pkg_codes' => array(),
        'svc_codes' => array(),
        'status' => false,
    );
    $carrier_id = $Request->getInt('carrier_id');
    if ($carrier_id != '') {
        $Carrier = Shop\Shipper::getByCode($carrier_id);
        if ($Carrier) {
            foreach ($Carrier->getPackageCodes() as $key=>$dscp) {
                $retval['pkg_codes'][$key] = $dscp;
            }
            foreach ($Carrier->getServiceCodes() as $key=>$dscp) {
                $retval['svc_codes'][$key] = $dscp;
            }
            $retval['carrier_id'] = $carrier_id;
            $retval['status'] = true;
        }
    }
    break;
}

// Return the $retval array as a JSON string
header('Content-Type: application/json');
header("Cache-Control: no-cache, must-revalidate");
//A date in the past
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
echo json_encode($retval);
exit;

