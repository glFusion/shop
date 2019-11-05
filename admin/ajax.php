<?php
/**
 * Common admistrative AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
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
switch ($action) {
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
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
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
                'img_url'   => Shop\Product::getImage($filename)['url'],
                'thumb_url' => Shop\Product::getThumb($filename)['url'],
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
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
    break;

case 'opt_orderby_opts':
    // Get the attrubute "orderby" options when the attribute group or item ID
    // is changed.
    $og_id = SHOP_getVar($_POST, 'og_id', 'integer', 0);
    $item_id = SHOP_getVar($_POST, 'item_id', 'integer', 0);
    $selected = SHOP_getVar($_POST, 'selected', 'integer', 0);
    $retval = Shop\ProductOptionValue::getOrderbyOpts($item_id, $og_id, $selected);
    echo $retval;
    exit;

case 'updatestatus':
    if (!empty($_POST['order_id']) &&
        !empty($_POST['newstatus'])) {
        $newstatus = $_POST['newstatus'];
        $order_id = $_POST['order_id'];
        $showlog = $_POST['showlog'] == 1 ? 1 : 0;
        $ord = \Shop\Order::getInstance($order_id);
        if ($ord->isNew)  {     // non-existant order
            $L = array(
                'showlog' => 0,
            );
        } else {
            $L['showlog'] = $showlog;
            $L['ts'] = $_CONF['_now']->format($_SHOP_CONF['datetime_fmt'], true);
            $L['order_id'] = $order_id;
            $L['username'] = COM_getDisplayName($_USER['uid']) . ' (' . $_USER['uid'] . ')';
            $L['statusMessage'] = NULL;
            $oldstatus = $ord->status;
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
        header('Content-Type: application/json');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        echo json_encode($L);
        break;
    }
    break;

case 'delimage_cat':
    // Delete a product image from the product edit form.
    $cat_id = SHOP_getVar($_POST, 'cat_id', 'integer', 0);
    $nonce = SHOP_getVar($_POST, 'nonce');
    $arr = array(
        'cat_id'    => $cat_id,
        'status'    => \Shop\Images\Category::DeleteImage($cat_id, $nonce),
    );
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($arr);
    break;

case 'delimage':
    // Delete a product image from the product edit form.
    $img_id = SHOP_getVar($_POST, 'img_id', 'integer', 0);
    $arr = array(
        'img_id'    => $img_id,
        'status'    => \Shop\Images\Product::DeleteImage($img_id),
    );
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($arr);
    break;

case 'add_tracking':
    $retval = array('status' => false);
    $shipment_id = SHOP_getVar($_POST, 'shipment_id', 'integer');
    if ($shipment_id > 0) {
        $SP = new Shop\ShipmentPackage();
        if ($SP->Save($_POST)) {
            if ($SP->shipper_id > 0) {
                $shipper_code = Shop\Shipper::getInstance($SP->shipper_id)->code;
                $tracking_url = Shop\Shipper::getInstance($SP->shipper_id)->getTrackingUrl($SP->tracking_num);
            } else {
                $shipper_code = '';
                $tracking_url = '';
            }
            $retval = array(
                'status'        => true,
                'shipper_id'    => $SP->shipper_id,
                'pkg_id'        => $SP->pkg_id,
                'shipper_name'  => $SP->shipper_info,
                'tracking_num'  => $SP->tracking_num,
                'shipper_code'  => $shipper_code,
                'tracking_url'  => $tracking_url,
            );
        } else {
            $retval['statusMessage'] = $LANG_SHOP['err_invalid_form'];
        }
    } else {
        $retval['statusMessage'] = $LANG_SHOP['err_invalid_form'];
    }
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
    break;

case 'del_tracking':
    $pkg_id = SHOP_getVar($_POST, 'pkg_id', 'integer');
    if ($pkg_id > 0) {
        Shop\ShipmentPackage::Delete($pkg_id);
    }
    $retval = array(
        'status' => true,
    );
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
    break;

case 'void':
    $item_id = $_POST['item_id'];
    $newval = $_POST['newval'];     // should be "void" or "valid"
    COM_errorLog(print_r($_POST,true));
    switch ($_POST['component']) {
    case 'coupon':
        $status = Shop\Products\Coupon::Void($item_id, $newval);
        break;
    }
    if ($status) {
        $next_val = $newval == 'void' ? 'valid' : 'void';
        $confirm_txt = $next_val == 'valid' ? $LANG_SHOP['q_confirm_unvoid'] : $LANG_SHOP['q_confirm_void'];
        $retval = array(
            'status' => $status,
            'statusMessage' => $LANG_SHOP['msg_updated'],
            'text' => SHOP_getVar($LANG_SHOP, $newval, 'string', 'Unknown'),
            'newclass' => $newval == 'void' ? 'uk-button-danger' : 'uk-button-success',
            'onclick_val' => $next_val,
            'confirm_txt' => $confirm_txt,
        );
    } else {
        $retval = array(
            'status' => $status,
            'statusMessage' => $LANG_SHOP['err_msg'],
            'text' => $LANG_SHOP['valid'],
        );
    }
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
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
            $newval = \Shop\OrderStatus::Toggle($_POST['id'], $field, $_POST['oldval']);
            break;
        default:
            exit;
        }
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
    );
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
    break;
}

?>
