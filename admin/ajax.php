<?php
/**
 * Common admistrative AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
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
    exit;
}

switch ($_POST['action']) {
case 'opt_orderby_opts':
    $og_id = SHOP_getVar($_POST, 'og_id', 'integer', 0);
    $item_id = SHOP_getVar($_POST, 'item_id', 'integer', 0);
    $selected = SHOP_getVar($_POST, 'selected', 'integer', 0);
    $retval = Shop\Attribute::getOrderbyOpts($item_id, $og_id, $selected);
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
        } elseif ($ord->updateStatus($newstatus)) {
            $L = $ord->getLastLog();
            if (!empty($L)) {
                // Add flag to indicate whether to update on-screen log
                $L['showlog'] = $showlog;
                $L['newstatus'] = $newstatus;
            }
        }
        header('Content-Type: applicsation/json');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        echo json_encode($L);
        break;
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

    case 'attribute':
        switch ($_POST['type']) {
        case 'enabled':
            $newval = \Shop\Attribute::toggleEnabled($_POST['oldval'], $_POST['id']);
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

    header('Content-Type: applicsation/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
    break;
}

?>
