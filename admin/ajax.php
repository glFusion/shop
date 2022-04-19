<?php
/**
 * Common admistrative AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
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
    COM_accessLog("User {$_USER['username']} tried to illegally access the shop admin ajax function.");
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

if (isset($_POST['action'])) {
    $action = $_POST['action'];
} elseif (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    $action = '';
}
$title = NULL;      // title attribute to be set
switch ($action) {
case 'newPXF':      // add a product->feature mapping
    $prod_id = SHOP_getVar($_POST, 'prod_id', 'integer', 0);
    $ft_id = SHOP_getVar($_POST, 'ft_id', 'integer', 0);
    $fv_id = SHOP_getVar($_POST, 'fv_id', 'integer', 0);
    $fv_text = SHOP_getVar($_POST, 'fv_text', 'string', '');
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
    $ft_id = SHOP_getVar($_POST, 'ft_id', 'integer', 0);
    $fv_id = SHOP_getVar($_POST, 'fv_id', 'integer', 0);
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
    $fv_id = SHOP_getVar($_POST, 'fv_id', 'integer', 0);
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
    $ft_id = SHOP_getVar($_POST, 'ft_id', 'integer', 0);
    $fv_text = SHOP_getVar($_POST, 'fv_text');
    if (!empty($fv_text)) {
        $FV = new Shop\FeatureValue();
        $FV->setValue($fv_text)
            ->setFeatureID($ft_id);
        $status = $FV->Save();
        if ($status) {
            $retval['status'] = true;
            $retval['statusMessage'] = 'Success';
            $retval['fv_id'] = $FV->getID();
            $retval['fv_text'] = $FV->getValue();
        }
    }
    break;

case 'orderImages':
    $retval = array(
        'status' => false,
        'statusMessage' => 'Not implemented',
    );
    /*$retval = array(
        'status' => true,
    );
    Shop\Images\Product::updateOrder($_POST['ordering']);
    Shop\Cache::clear('products');
     */
    break;

case 'dropupload_cat':
    // Handle a drag-and-drop image upload for categories
    $cat_id = SHOP_getVar($_POST, 'cat_id', 'integer', 0);
    $nonce = SHOP_getVar($_POST, 'nonce', 'string');
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
        Shop\Cache::clear('categories');
    } else {
        $retval['status'] = false;
        $retval['statusMessage'] = $LANG_SHOP['no_files_uploaded'];
    }
    break;

case 'dropupload':
    // Handle a drag-and-drop image upload
    $item_id = SHOP_getVar($_POST, 'item_id', 'integer', 0);
    $nonce = SHOP_getVar($_POST, 'nonce', 'string');
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
        Shop\Cache::clear('products');
    } else {
        $retval['status'] = false;
        $retval['statusMessage'] = $LANG_SHOP['no_files_uploaded'];
    }
    break;

case 'opt_orderby_opts':
    // Get the attrubute "orderby" options when the attribute group or item ID
    // is changed.
    $og_id = SHOP_getVar($_POST, 'og_id', 'integer', 0);
    $selected = SHOP_getVar($_POST, 'selected', 'integer', 0);
    $retval = Shop\ProductOptionValue::getOrderbyOpts($og_id, $selected);
    // This function gets JSON from the object.
    echo $retval;
    exit;

case 'updatestatus':
    if (!empty($_POST['order_id']) &&
        !empty($_POST['newstatus'])) {
        $newstatus = $_POST['newstatus'];
        $order_id = $_POST['order_id'];
        $showlog = $_POST['showlog'] == 1 ? 1 : 0;
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
            $comment = SHOP_getVar($_POST, 'comment');
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
    $cat_id = SHOP_getVar($_POST, 'cat_id', 'integer', 0);
    $nonce = SHOP_getVar($_POST, 'nonce');
    $retval = array(
        'cat_id'    => $cat_id,
        'status'    => \Shop\Images\Category::DeleteImage($cat_id, $nonce),
    );
    break;

case 'delimage':
    // Delete a product image from the product edit form.
    $img_id = SHOP_getVar($_POST, 'img_id', 'integer', 0);
    $retval = array(
        'img_id'    => $img_id,
        'status'    => \Shop\Images\Product::DeleteImage($img_id),
    );
    break;

case 'setDefImg':
    // Set an image as the default.
    $img_id = SHOP_getVar($_POST, 'img_id', 'integer', 0);
    $prod_id = SHOP_getVar($_POST, 'prod_id', 'integer', 0);
    $retval = array(
        'img_id'    => $img_id,
        'status'    => \Shop\Images\Product::setAsDefault($img_id, $prod_id),
    );
    break;

case 'add_tracking':
    $retval = array('status' => false);
    $shipment_id = SHOP_getVar($_POST, 'shipment_id', 'integer');
    if ($shipment_id > 0) {
        $SP = new Shop\ShipmentPackage();
        if ($SP->Save($_POST)) {
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
    $pkg_id = SHOP_getVar($_POST, 'pkg_id', 'integer');
    if ($pkg_id > 0) {
        Shop\ShipmentPackage::Delete($pkg_id);
    }
    $retval = array(
        'status' => true,
    );
    break;

case 'void':
    $item_id = $_POST['item_id'];
    $newval = $_POST['newval'];     // should be "void" or "valid"
    switch ($_POST['component']) {
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
            'text'          => SHOP_getVar($LANG_SHOP, $newval, 'string', 'Unknown'),
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
    $uid = (int)$_POST['uid'];
    $Orders = Shop\Order::getUnpaid($uid);
    $retval = array();
    foreach ($Orders as $Order) {
        $retval[$Order->getID()] = array(
            'total' => $Order->getTotal(),
            'amt_paid' => $Order->getAmountPaid(),
        );
    }
    break;

case 'toggle':
    switch ($_POST['component']) {
    case 'product':
        switch ($_POST['type']) {
        case 'enabled':
            $newval = \Shop\Product::toggleEnabled($_POST['oldval'], $_POST['id']);
            break;

        case 'featured':
            $newval = \Shop\Product::toggleFeatured($_POST['oldval'], $_POST['id']);
            break;
         default:
            exit;
        }
        break;

    case 'category':
        switch ($_POST['type']) {
        case 'enabled':
            $newval = \Shop\Category::toggleEnabled($_POST['oldval'], $_POST['id']);
            break;
         default:
            exit;
        }
        break;

    case 'option':
        switch ($_POST['type']) {
        case 'enabled':
            $newval = \Shop\ProductOptionValue::toggleEnabled($_POST['oldval'], $_POST['id']);
            break;
         default:
            exit;
        }
       break;

    case 'variant':
        switch ($_POST['type']) {
        case 'enabled':
            $newval = \Shop\ProductVariant::toggleEnabled($_POST['oldval'], $_POST['id']);
            break;
         default:
            exit;
        }
       break;

    case 'shipping':
        switch ($_POST['type']) {
        case 'enabled':
            $newval = \Shop\Shipper::toggleEnabled($_POST['oldval'], $_POST['id']);
            break;
         default:
            exit;
        }
       break;

    case 'gateway':
        switch ($_POST['type']) {
        case 'enabled':
            $newval = \Shop\Gateway::toggleEnabled($_POST['oldval'], $_POST['id']);
            if ($newval == 1) {
                $title = $LANG_SHOP['ck_to_disable'];
            } else {
                $title = $LANG_SHOP['ck_to_enable'];
            }
            break;
        case 'buy_now':
            $newval = \Shop\Gateway::toggleBuyNow($_POST['oldval'], $_POST['id']);
            break;
        case 'donation':
            $newval = \Shop\Gateway::toggleDonation($_POST['oldval'], $_POST['id']);
            break;
        default:
            exit;
        }
        break;

    case 'workflow':
        $field = $_POST['type'];
        $wf = \Shop\Workflow::getInstance($_POST['id']);
        if (!$wf) break;
        $newval = $_POST['oldval'];
        $_POST['oldval'] = $wf->enabled;
        switch ($field) {
        case 'enabled':
            $newval = \Shop\Workflow::setValue($_POST['id'], $field, $newval);
            break;
        default:
            exit;
        }
        break;

    case 'orderstatus':
        $field = $_POST['type'];
        switch ($field) {
        case 'enabled':
        case 'notify_buyer':
        case 'notify_admin':
            $newval = Shop\OrderStatus::Toggle($_POST['oldval'], $field, $_POST['id']);
            break;
        default:
            exit;
        }
        break;

    case 'zone_rule':
        $newval = Shop\Rules\Zone::Toggle($_POST['oldval'], $_POST['type'], $_POST['id']);
        break;

    case 'supplier':
        $newval = Shop\Supplier::Toggle($_POST['oldval'], $_POST['type'], $_POST['id']);
        break;

    case 'region':
        $newval = Shop\Region::Toggle($_POST['oldval'], $_POST['type'], $_POST['id']);
        break;

    case 'country':
        $newval = \Shop\Country::Toggle($_POST['oldval'], $_POST['type'], $_POST['id']);
        break;

    case 'state':
        $newval = \Shop\State::Toggle($_POST['oldval'], $_POST['type'], $_POST['id']);
        break;

    case 'pi_product':
        $newval = \Shop\Products\Plugin::Toggle($_POST['oldval'], $_POST['type'], $_POST['id']);
        break;

    default:
        exit;
    }

    // Common output for all toggle functions.
    $retval = array(
        'id'    => $_POST['id'],
        'type'  => $_POST['type'],
        'component' => $_POST['component'],
        'newval'    => $newval,
        'statusMessage' => $newval != $_POST['oldval'] ?
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
    $carrier_id = $_POST['carrier_id'];
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

?>
