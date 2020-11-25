<?php
/**
 * Admin index page for the shop plugin.
 * By default, lists products available for editing.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      Vincent Furia <vinny01@users.sourceforge.net>
 * @copyright   Copyright (c) 2009-2020 Lee Garner
 * @copyright   Copyright (c) 2005-2006 Vincent Furia
 * @package     shop
 * @version     v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import Required glFusion libraries */
require_once('../../../lib-common.php');

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !isset($_SHOP_CONF) ||
    !in_array($_SHOP_CONF['pi_name'], $_PLUGINS) ||
    !SHOP_access_check('shop.admin')
) {
    COM_404();
    exit;
}

require_once('../../auth.inc.php');
USES_lib_admin();

$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($_REQUEST['msg'])) $msg[] = $_REQUEST['msg'];

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = $_SHOP_CONF['adm_def_view'];
$expected = array(
    // Actions to perform
    'deleteproduct', 'deletecatimage', 'deletecat',
    'saveproduct', 'savecat', 'pov_save', 'pov_del', 'resetbuttons',
    'carrier_save', 'pv_save', 'pv_del', 'pv_del_bulk',
    'attrcopy', 'pov_move', 'wfmove', 'pv_move',
    'prod_clone', 'runreport', 'configreport', 'sendcards', 'purgecache',
    'delsale', 'savesale', 'purgecarts', 'saveshipper', 'updcartcurrency',
    'delcode', 'savecode', 'save_sup',
    'migrate_pp', 'purge_trans', 'pog_del', 'pog_move', 'pog_save',
    'addshipment', 'updateshipment', 'del_shipment', 'delshipping',
    'importtaxexec', 'savetaxrate', 'deltaxrate', 'statcomment',
    'prod_bulk_save', 'pv_bulk_save', 'prod_bulk_del',
    'ft_save', 'ft_del', 'ft_move',
    'savepayment', 'delpayment',
    // Views to display
    'history', 'orders', 'ipnlog', 'editproduct', 'editcat', 'categories',
    'pov_edit', 'other',
    'carrier_config',
    'opt_grp', 'pog_edit', 'carriers',
    'wfadmin', 'order', 'reports', 'coupons', 'sendcards_form',
    'sales', 'editsale', 'editshipper', 'shipping', 'ipndetail',
    'codes', 'editcode', 'pv_edit', 'pv_bulk',
    'shiporder', 'editshipment', 'shipment_pl', 'order_pl',
    'shipments', 'ord_ship', 'ord_pmts', 'newpayment',
    'importtaxform', 'taxrates', 'edittaxrate', 'suppliers', 'edit_sup',
    'prod_bulk_frm','pv_edit_bulk', 'variants', 'options',
    'regions', 'countries', 'states',
    'features', 'ft_view', 'ft_edit',
    'products',
);
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
$mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
$view = 'products';     // Default if no correct view specified

switch ($action) {
case 'statcomment':         // update comment and status
    $order_id = SHOP_getVar($_POST, 'order_id');
    $notify = SHOP_getVar($_POST, 'notify', 'integer', 0);
    $comment = SHOP_getVar($_POST, 'comment');
    $newstatus = SHOP_getVar($_POST, 'newstatus');
    if (!empty($order_id)) {
        $Ord = Shop\Order::getInstance($order_id);
        $Ord->updateStatus($newstatus, true, false);
        $Ord->Log($comment);
        if ($notify) {
            $Ord->Notify($newstatus, $comment, $notify, false);
        }
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?order=' . $order_id);
    break;

case 'prod_clone':
    $P = new \Shop\Product($_REQUEST['id']);
    $P->Duplicate();
    COM_refresh(SHOP_ADMIN_URL.'/index.php?products');
    break;

case 'deleteproduct':
    $P = \Shop\Product::getByID($_REQUEST['id']);
    if (!\Shop\Product::isUsed($_REQUEST['id'])) {
        $P->Delete();
    } else {
        COM_setMsg(sprintf($LANG_SHOP['no_del_item'], $P->name), 'error');
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?products');
    break;

case 'delshipping':
    if (Shop\Shipper::Delete($actionval)) {
        COM_setMsg($LANG_SHOP['msg_deleted']);
    } else {
        COM_setMsg($LANG_SHOP['error']);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?shipping');
    break;

case 'deletecatimage':
    $id = SHOP_getVar($_GET, 'cat_id', 'integer');
    if ($id > 0) {
        $C = new \Shop\Category($id);
        $C->deleteImage();
        $view = 'editcat';
        $_REQUEST['id'] = $id;
    } else {
        $view = 'categories';
    }
    break;

case 'deletecat':
    $C = \Shop\Category::getInstance($_REQUEST['cat_id']);
    if ($C->getParentID() == 0) {
        COM_setMsg($LANG_SHOP['dscp_root_cat'], 'error');
    } elseif (\Shop\Category::isUsed($_REQUEST['cat_id'])) {
        COM_setMsg(sprintf($LANG_SHOP['no_del_cat'], $C->getName()), 'error');
    } else {
        $C->Delete();
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?categories');
    break;

case 'saveproduct':
    $url = SHOP_getUrl(SHOP_ADMIN_URL . '/index.php');
    $P = new \Shop\Product($_POST['id']);
    if (!$P->Save($_POST)) {
        $msg = $P->PrintErrors();
        if ($msg != '') {
            COM_setMsg($msg, 'error');
        }
    }
    COM_refresh($url);
    break;

case 'savecat':
    $C = new \Shop\Category($_POST['cat_id']);
    if (!$C->Save($_POST)) {
        $content .= COM_showMessageText($C->PrintErrors());
        $view = 'editcat';
    } else {
        $view = 'categories';
    }
    break;

case 'ft_save':
    $FT = new Shop\Feature($_POST['ft_id']);
    if (!$FT->Save($_POST)) {
        $content .= COM_showMessageText($LANG_SHOP['invalid_form']);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?features');
    break;

case 'pog_save':
    $POG = new \Shop\ProductOptionGroup($_POST['pog_id']);
    if (!$POG->Save($_POST)) {
        $content .= COM_showMessageText($LANG_SHOP['invalid_form']);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?opt_grp=x');
    break;

case 'pv_save':
    $from = SESS_getVar('shop.pv_view');
    $pv_id = SHOP_getVar($_POST, 'pv_id', 'integer');
    Shop\ProductVariant::getInstance($pv_id)->Save($_POST);
    if ($from == 'pv_bulkedit') {
        $item_id = SHOP_getVar($_POST, 'item_id', 'integer');
        COM_refresh(SHOP_ADMIN_URL . '/index.php?pv_bulkedit&item_id=' . $item_id);
    } else {
        $item_id = SHOP_getVar($_POST, 'pv_item_id', 'integer');
        COM_refresh(SHOP_ADMIN_URL . '/index.php?editproduct&tab=variants&id=' . $item_id);
    }
    break;

case 'pov_save':
    $Opt = new \Shop\ProductOptionValue($_POST['pov_id']);
    if (!$Opt->Save($_POST)) {
        $content .= COM_showMessageText($LANG_SHOP['invalid_form']);
    }
    if (isset($_POST['pov_id']) && !empty($_POST['pov_id'])) {
        // Updating an existing option, return to the list
        COM_refresh(SHOP_ADMIN_URL . '/index.php?options=x');
    } else {
        COM_refresh(SHOP_ADMIN_URL . '/index.php?pov_edit=x&pog_id=' . $Opt->getGroupID());
    }
    break;

case 'pv_del_bulk':
    $ids = SHOP_getVar($_POST, 'pv_bulk_id', 'array');
    foreach ($ids as $id) {
        Shop\ProductVariant::Delete($id);
    }
    Shop\Cache::clear('products');
    Shop\Cache::clear('options');
    COM_refresh(
        SHOP_ADMIN_URL . '/index.php?pv_bulk&item_id=' . SHOP_getVar($_GET, 'item_id', 'integer')
    );
    break;

case 'pv_del':
    $from = SESS_getVar('shop.pv_view');
    Shop\ProductVariant::Delete($_REQUEST['pv_id']);
    if ($from === 'pv_bulk') {
        COM_refresh(SHOP_ADMIN_URL . '/index.php?pv_bulk&item_id=' . $_REQUEST['item_id']);
    } else {
        COM_refresh(SHOP_ADMIN_URL . '/index.php?editproduct&tab=variants&id=' . $_REQUEST['item_id']);
    }
    exit;
    break;

case 'rule_add':
    $rule_id = SHOP_getVar($_POST, 'rule_id', 'integer', 0);
    if ($rule_id > 0) {
        switch ($actionval) {
        case 'region':
        case 'country':
        case 'state':
            Shop\Rules\Zone::getInstance($rule_id)
                ->add($actionval, SHOP_getVar($_POST, $actionval . '_id', 'array', array()))
                ->Save();
            break;
        }
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?' . http_build_query($_GET));
    break;

case 'ft_del':
    Shop\Feature::Delete($_REQUEST['ft_id']);
    $view = 'features';
    break;

case 'pog_del':
    Shop\ProductOptionGroup::Delete($_REQUEST['pog_id']);
    $view = 'opt_grp';
    break;

case 'pov_del':
    // opt_id could be via $_GET or $_POST
    Shop\ProductOptionValue::Delete($_REQUEST['opt_id']);
    $view = 'options';
    break;

case 'resetbuttons':
    DB_query("TRUNCATE {$_TABLES['shop.buttons']}");
    COM_setMsg($LANG_SHOP['buttons_purged']);
    COM_refresh(SHOP_ADMIN_URL . '/index.php?other=x');
    break;

case 'updcartcurrency':
    $updated = 0;
    $Carts = \Shop\Cart::getAll();    // get all carts
    $convert = SHOP_getVar($_POST, 'conv_cart_curr', 'integer', 0);
    foreach ($Carts as $Cart) {         // loop through all
        if ($Cart->currency == $_SHOP_CONF['currency']) {
            continue;
        }
        if ($convert == 1) {
            $Cart->convertCurrency();
        } else {
            // Just changing the currency code.
            $Cart->currency = $_SHOP_CONF['currency'];
            $Cart->Save();
        }
        $updated++;
    }
    COM_setMsg(sprintf($LANG_SHOP['x_carts_updated'], $updated));
    COM_refresh(SHOP_ADMIN_URL . '/index.php?other=x');
    break;

case 'migrate_pp':
    if (Shop\MigratePP::doMigration()) {
        COM_setMsg($LANG_SHOP['migrate_pp_ok']);
    } else {
        COM_setMsg($LANG_SHOP['migrate_pp_error'], 'error');
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?other=x');
    break;

case 'purge_trans':
    // Purge all transactions. Shop must be disabled.
    // Intended to purge test data prior to going live.
    if (!$_SHOP_CONF['shop_enabled']) {
        \Shop\Order::Purge();
        \Shop\IPN::Purge();
        \Shop\Products\Coupon::Purge();
        \Shop\Shipment::Purge();
        \Shop\Cache::clear();
        COM_setMsg($LANG_SHOP['trans_purged']);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?other=x');
    break;

case 'purgecarts':
    \Shop\Cart::Purge();
    COM_setMsg($LANG_SHOP['carts_purged']);
    COM_refresh(SHOP_ADMIN_URL . '/index.php?other=x');
    break;

case 'saveshipper':
    $id = SHOP_getVar($_POST, 'id', 'integer');
    $S = new \Shop\Shipper($id);
    $S->Save($_POST);
    COM_refresh(SHOP_ADMIN_URL . '/index.php?shipping=x');
    break;

case 'carrier_save':
    // Save a shipping carrier configuration
    $Shipper = Shop\Shipper::getByCode($_POST['carrier_code']);
    $status = false;
    if ($Shipper !== NULL) {
        $status = $Shipper->saveConfig($_POST);
    }
    if ($status) {
        COM_setMsg($LANG_SHOP['msg_updated']);
    } else {
        COM_setMsg($LANG_SHOP['err_msg'], 'error');
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?carriers');
    break;

case 'ft_move':
    $ft_id = SHOP_getVar($_GET, 'id', 'integer', 0);
    if ($ft_id > 0) {
        Shop\Feature::moveRow($ft_id, $actionval);
    }
    $view = 'features';
    break;

case 'pog_move':
    $og_id = SHOP_getVar($_GET, 'id', 'integer');
    if ($og_id > 0) {
        Shop\ProductOptionGroup::moveRow($og_id, $actionval);
    }
    $view = 'opt_grp';
    break;

case 'pv_move':
    $pv_id = SHOP_getVar($_GET, 'id', 'integer');
    $prod_id = SHOP_getVar($_GET, 'prod_id', 'integer');
    if ($pv_id > 0) {
        $PV = new \Shop\ProductVariant($pv_id);
        $PV->moveRow($actionval);
    }
    COM_refresh(SHOP_ADMIN_URL . "/index.php?editproduct=x&id={$PV->getItemID()}");
    break;

case 'pov_move':
    $opt_id = SHOP_getVar($_GET, 'id', 'integer');
    if ($opt_id > 0) {
        $Opt = new \Shop\ProductOptionValue($opt_id);
        $Opt->moveRow($actionval);
    }
    $view = 'options';
    break;

case 'wfmove':
    switch ($_GET['type']) {
    case 'workflow':
        \Shop\Workflow::moveRow($_GET['id'], $actionval);
        break;
    case 'orderstatus':
        \Shop\OrderStatus::moveRow($_GET['id'], $actionval);
        break;
    }
    $view = 'wfadmin';
    break;

case 'attrcopy':
    // Copy options from a product to another product or category
    $src_prod = (int)$_POST['src_prod'];
    $dest_prod = (int)$_POST['dest_prod'];
    $dest_cat = (int)$_POST['dest_cat'];
    $del_existing = isset($_POST['del_existing_attr']) ? true : false;
    $done_prods = array();

    // Nothing to do if no source product selected
    if ($src_prod < 1) {
        COM_refresh(SHOP_ADMIN_URL . '/index.php?variants');
    }

    // Copy product options to all products in a category.
    // Ignore the source product, which may or may not be in the category.
    if ($dest_cat > 0) {
        // Get all products in the category
        $Products = Shop\Product::getIDsByCategory($dest_cat);
        foreach ($Products as $dst_id) {
            if ($dst_id == $src_prod) {
                continue;
            }
            $done_prods[] = $dst_id;    // track for later
            Shop\ProductVariant::cloneProduct($src_prod, $dst_id, $del_existing);
        }
    }

    // If a target product was selected, it's not the same as the source, and hasn't
    // already been done as part of the category, then update the target product also.
    if ($dest_prod > 0 && $dest_prod != $src_prod && !in_array($dest_prod, $done_prods)) {
        Shop\ProductVariant::cloneProduct($src_prod, $dest_prod, $del_existing);
    }
    \Shop\Cache::clear();
    COM_refresh(SHOP_ADMIN_URL . '/index.php?variants');
    break;

case 'runreport':
    $reportname = SHOP_getVar($_POST, 'reportname');
    if ($reportname != '') {
        $R = \Shop\Report::getInstance($reportname);
        if ($R) {
            $content .- $R->Render();
        }
    }
    break;

case 'sendcards':
    $amt = SHOP_getVar($_POST, 'amount', 'float');
    $uids = SHOP_getVar($_POST, 'groupmembers', 'string');
    $gid = SHOP_getVar($_POST, 'group_id', 'int');
    $exp = SHOP_getVar($_POST, 'expires', 'string');
    $no_exp = SHOP_getVar($_POST, 'no_exp', 'integer', 0);
    if ($no_exp == 1) {
        $exp = \Shop\Products\Coupon::MAX_EXP;
    }
    $status = LGLIB_invokeService('shop', 'sendcards',
        array(
            'amount'    => $amt,
            'members'   => $uids,
            'group_id'  => $gid,
            'expires'   => $exp,
            'notify'    => true,
        ),
        $output,
        $errs
    );

    if (empty($errs)) {
        COM_setMsg(count($output) . ' coupons sent');
    } else {
        $msg = '<ul><li>' . implode('</li><li>', $errs) . '</li></ul>';
        COM_setMsg($msg, 'error', true);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?sendcards_form=x');
    break;

case 'purgecache':
    \Shop\Cache::clear();
    COM_setMsg($LANG_SHOP['cache_purged']);
    COM_refresh(SHOP_ADMIN_URL . '/index.php?other=x');
    break;

case 'savesale':
    $D = new \Shop\Sales($_POST['id']);
    if (!$D->Save($_POST)) {
        COM_setMsg($LANG_SHOP['msg_nochange']);
        COM_refresh(SHOP_ADMIN_URL . '/index.php?editsale&id=' . $D->id);
    } else {
        COM_setMsg($LANG_SHOP['msg_updated']);
        COM_refresh(SHOP_ADMIN_URL . '/index.php?sales');
    }
    exit;
    break;

case 'save_sup':
    // Save a supplier/brand record
    $Sup = new Shop\Supplier($_POST['sup_id']);
    if ($Sup->Save($_POST)) {
        COM_setMsg($LANG_SHOP['msg_updated']);
        COM_refresh(SHOP_ADMIN_URL . '/index.php?suppliers');
    } else {
        $msg = '';
        foreach ($Sup->getErrors() as $err) {
            $msg .= '<li>' . $err . '</li>' . LB;
        }
        if (!empty($msg)) {
            $msg = '<ul>' . $msg . '</ul>';
        } else {
            $msg = $LANG_SHOP['msg_nochange'];
        }
        COM_setMsg($msg, 'error', true);
        COM_refresh(SHOP_ADMIN_URL . '/index.php?edit_sup=' . $Sup->getID());
    }
    break;

case 'savecode':
    $C = new Shop\DiscountCode($_POST['code_id']);
    if (!$C->Save($_POST)) {
        //COM_setMsg($LANG_SHOP['msg_nochange']);
        COM_refresh(SHOP_ADMIN_URL . '/index.php?editcode&code_id=' . $C->getCodeID());
    } else {
        COM_setMsg($LANG_SHOP['msg_updated']);
        COM_refresh(SHOP_ADMIN_URL . '/index.php?codes');
    }
    exit;
    break;

case 'delsale':
    $id = SHOP_getVar($_REQUEST, 'id', 'integer', 0);
    if ($id > 0) {
        \Shop\Sales::Delete($id);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?sales');
    break;

case 'delcode':
    $id = SHOP_getVar($_REQUEST, 'code_id', 'integer', 0);
    if ($id > 0) {
        \Shop\DiscountCode::Delete($id);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?codes');
    break;

case 'updateshipment':
    $shipment_id = SHOP_getVar($_POST, 'shipment_id', 'integer');
    if ($shipment_id > 0) {
        $S = new Shop\Shipment($shipment_id);
        $S->Save($_POST);
    }
    $url = SHOP_getUrl(SHOP_ADMIN_URL . '/index.php?shipments');
    COM_refresh($url);
    break;

case 'del_shipment':
    echo "del_shipment deprecated";die;
    $S = new Shop\Shipment($actionval);
    $S->Delete();
    $url = SHOP_getUrl(SHOP_ADMIN_URL . '/index.php?shipments');
    COM_refresh($url);
    break;

case 'addshipment':
    $S = Shop\Shipment::create($_POST['order_id']);
    if ($S->Save($_POST)) {
        COM_refresh(SHOP_getUrl(SHOP_ADMIN_URL . '/index.php?order=' . $S->getOrderID()));
    } else {
        COM_setMsg("Error Adding Shipment, see the error log");
        COM_refresh(SHOP_ADMIN_URL . '/index.php?shiporder=x&order_id=' . urlencode($_POST['order_id']));
    }
    break;

case 'importtaxexec':
    $content .= COM_showMessageText(Shop\Tax\table::Import());
    $view = 'taxrates';
    break;

case 'savetaxrate':
    $code = $_POST['old_code'];
    $status = Shop\Tax\table::Save($_POST);
    if (!$status) {
        COM_setMsg("Error Saving tax", true);
    }
    if (empty($_POST['old_code'])) {
        COM_refresh(SHOP_ADMIN_URL . '/index.php?edittaxrate');
    } else {
        COM_refresh(SHOP_ADMIN_URL . '/index.php?taxrates');
    }
    break;

case 'deltaxrate':
    $code = $_REQUEST['code'];
    Shop\Tax\table::Delete($code);
    $view = 'taxrates';
    break;

case 'prod_bulk_save':
    if (Shop\Product::BulkUpdateDo($_POST)) {
        COM_setMsg($LANG_SHOP['msg_updated']);
    } else {
        COM_setMsg($LANG_SHOP['error']);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?products');
    break;

case 'prod_bulk_del':
    $prod_ids = SHOP_getVar($_POST, 'prod_bulk', 'array', array());
    $mag = $LANG_SHOP['msg_updated'];   // assume success
    foreach ($prod_ids as $id) {
        if (!Shop\Product::getById($id)->Delete()) {
            $msg = $LANG_SHOP['msg_some_not_del'];
        }
    }
    COM_setMsg($msg);
    COM_refresh(SHOP_ADMIN_URL . '/index.php?products');
    break;

case 'pv_bulk_save':
    if (Shop\ProductVariant::BulkUpdateDo($_POST)) {
        COM_setMsg($LANG_SHOP['msg_updated']);
    } else {
        COM_setMsg($LANG_SHOP['error']);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?variants');
    break;

case 'savepayment':
    echo "payments deprecated in index.php";die;
    $Pmt = Shop\Payment::getInstance($_POST['pmt_id']);
    $Pmt->setAmount($_POST['amount'])
        ->setMethod($_POST['gw_id'])
        ->setGateway($_POST['gw_id'])
        ->setRefID($_POST['ref_id'])
        ->setOrderID($_POST['order_id'])
        ->setUID($_USER['uid'])
        ->setIsMoney(isset($_POST['is_money']) ? 1 : 0)
        ->setComment($_POST['comment']);
    $Pmt->Save();
    COM_refresh(SHOP_ADMIN_URL . '/index.php?ord_pmts=' . $_POST['order_id']);
    break;

case 'delpayment':
    echo "payments deprecated in index.php";die;
    Shop\Payment::delete($actionval);
    COM_refresh(SHOP_ADMIN_URL . '/index.php?ord_pmts=' . $_GET['ord_pmts']);
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'history':
    $content .= \Shop\history(true);
    break;

case 'orders':
    $content .= Shop\Menu::adminOrders($view);
    $R = \Shop\Report::getInstance('orderlist');
    if ($R !== NULL) {
        $R->setAdmin(true);
        // Params usually from GET but could be POSTed time period
        $R->setParams($_REQUEST);
        $content .= $R->Render();
    }
    break;

case 'coupons':
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Products\Coupon::adminList();
    break;

case 'order':
    $order = \Shop\Order::getInstance($actionval);
    $V = (new \Shop\Views\Invoice)->withOrderId($actionval)->setAdmin(true);
    $content .= Shop\Menu::viewOrder($view, $order);
    $content .= $V->Render();
    break;

case 'ipndetail':
    $val = NULL;
    foreach (array('id', 'txn_id') as $key) {
        if (isset($_GET[$key])) {
            $val = $_GET[$key];
            break;
        }
    }
    if ($val !== NULL) {
        $content .= \Shop\Report::getInstance('ipnlog')->RenderDetail($val, $key);
        break;
    }
    break;

case 'ipnlog':
    echo "IPNLOG DEPRECATED";die;
    $op = isset($_REQUEST['op']) ? $_REQUEST['op'] : 'all';
    $log_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $txn_id = isset($_REQUEST['txn_id']) ?
                    COM_applyFilter($_REQUEST['txn_id']) : '';
    switch ($op) {
    case 'single':
        $val = NULL;
        foreach (array('id', 'txn_id') as $key) {
            if (isset($_REQUEST['id'])) {
                $val = $_REQUEST['id'];
                break;
            }
        }
        if ($val !== NULL) {
            $content .= \Shop\Report::getInstance('ipnlog')->RenderDetail($val, $key);
            break;
        }
        // If $val was not found, default to the ipn log list
    default:
        $content .= SHOP_adminlist_IPNLog();
        break;
    }
    break;

case 'editproduct':
    SESS_setVar('shop.pv_view', 'editproduct');
    $id = SHOP_getVar($_REQUEST, 'id', 'integer');
    $tab = SHOP_getVar($_GET, 'tab');
    $P = new \Shop\Product($id);
    if ($id == 0 && isset($_POST['short_description'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $P->SetVars($_POST);
    }
    $content .= $P->showForm(0, $tab);
    break;

case 'editcat':
    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $C = new \Shop\Category($id);
    if ($id == 0 && isset($_POST['description'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $C->SetVars($_POST);
    }
    $content .= $C->showForm();
    break;

case 'categories':
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Category::adminList();
    $view = 'products';     // to set the active menu
    break;

case 'sales':
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Sales::adminList();
    $view = 'products';   // cheating, to get the active menu set
    break;

case 'codes':
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\DiscountCode::adminList();
    $view = 'products';   // cheating, to get the active menu set
    break;

case 'options':
    $content .= Shop\Menu::adminCatalog('options');
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked options
        foreach ($_POST['delitem'] as $opt_id) {
            \Shop\ProductOptionValue::Delete($opt_id);
        }
    }
    $content .= Shop\ProductOptionValue::adminList();
    $view = 'products';     // to set the active menu
    break;

case 'opt_grp':
    $content .= Shop\Menu::adminCatalog('opt_grp');
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked option groups
        foreach ($_POST['delitem'] as $og_id) {
            \Shop\ProductOptionGroup::Delete($og_id);
        }
    }
    $content .= Shop\ProductOptionGroup::adminList();
    $view = 'products';   // cheating, to get the active menu set
    break;

case 'shipping':
    $content .= Shop\Menu::adminShipping($view);
    $content .= Shop\Shipper::adminList();
    break;

case 'carriers':
    $content .= Shop\Menu::adminShipping($view);
    $content .= Shop\Shipper::carrierList();
    break;

case 'variants':
    $content .= Shop\Menu::adminCatalog('variants');
case 'pv_bulk':
    SESS_setVar('shop.pv_view', $view);
    $prod_id = SHOP_getVar($_GET, 'item_id', 'integer');
    $content .= Shop\ProductVariant::adminList($prod_id, true);
    break;

case 'pv_edit':
    $pv_id = SHOP_getVar($_GET, 'pv_id', 'integer');
    $content .= Shop\Menu::adminCatalog($view);
    $Var = new Shop\ProductVariant($pv_id);
    if ($Var->getID() == 0) {
        // For a new variant, force the item ID to be set
        $Var->setItemID(SHOP_getVar($_GET, 'item_id', 'integer'));
    }
    $content .= $Var->Edit();
    break;

case 'pov_edit':
    $opt_id = SHOP_getVar($_GET, 'opt_id', 'integer');
    $content .= Shop\Menu::adminCatalog($view);
    $Opt = new Shop\ProductOptionValue($opt_id);
    if ($Opt->getID() == 0) {
        $Opt->setGroupID(SHOP_getVar($_GET, 'pog_id', 'integer'));
    }
    $content .= $Opt->Edit();
    break;

case 'pog_edit':
    $og_id = SHOP_getVar($_GET, 'pog_id');
    $OG = new \Shop\ProductOptionGroup($og_id);
    $content .= Shop\Menu::adminCatalog($view);
    $content .= $OG->Edit();
    break;

case 'editsale':
    $id = SHOP_getVar($_GET, 'id', 'integer', 0);
    $D = new \Shop\Sales($id);
    $content .= $D->Edit();
    break;

case 'editcode':
    $id = SHOP_getVar($_GET, 'code_id', 'integer', 0);
    $C = new \Shop\DiscountCode($id);
    $content .= $C->Edit();
    break;

case 'other':
    $T = new Shop\Template;
    $T->set_file('funcs', 'other_functions.thtml');
    $T->set_var(array(
        'admin_url' => SHOP_ADMIN_URL . '/index.php',
        'can_migrate_pp' => Shop\MigratePP::canMigrate(),
        'can_purge_trans' => !$_SHOP_CONF['shop_enabled'],
    ) );
    $T->parse('output', 'funcs');
    $content = $T->finish($T->get_var('output'));
    break;

case 'sendcards_form':
    $T = new Shop\Template;
    $T->set_file('cards', 'send_cards.thtml');
    $sql = "SELECT uid,fullname FROM {$_TABLES['users']}
                WHERE status > 0 AND uid > 1";
    $res = DB_query($sql, 1);
    $included = '';
    $excluded = '';
    if ($_SHOP_CONF['gc_exp_days'] > 0) {
        $period = 'P' . (int)$_SHOP_CONF['gc_exp_days'] . 'D';
        $dt = new \Date('now', $_CONF['timezone']);
        $dt->add(new DateInterval($period));
        $expires = $dt->format('Y-m-d');
    } else {
        $expires = \Shop\Products\Coupon::MAX_EXP;
    }
    $tmp = array();
    while ($A = DB_fetchArray($res, false)) {
        $excluded .= "<option value=\"{$A['uid']}\">{$A['fullname']}</option>\n";
    }
    $T->set_var(array(
        'excluded' => $excluded,
        'grp_select' => COM_optionList($_TABLES['groups'],
                            'grp_id,grp_name', '', 1),
        'expires' => $expires,
    ) );
    $T->parse('output', 'cards');
    $content = $T->finish($T->get_var('output'));
    break;

case 'gwadmin':
    echo "$view deprecated";die;
    $content .= Shop\Gateway::adminList();
    break;

case 'gwedit':
    echo "$view deprecated";die;
    $gw = \Shop\Gateway::getInstance($_GET['gw_id']);
    if ($gw !== NULL) {
        $content .= $gw->Configure();
    }
    break;

case 'carrier_config':
    $Shipper = \Shop\Shipper::getByCode($actionval);
    $content .= Shop\Menu::adminShipping('carriers');
    if ($Shipper !== NULL) {
        $content .= $Shipper->Configure();
    }
    break;

case 'wfadmin':
    $content .= Shop\Workflow::adminList();
    $content .= Shop\OrderStatus::adminList();
    break;

case 'reports':
    $content .= \Shop\Report::getList();
    break;

case 'configreport':
    $R = \Shop\Report::getInstance($actionval);
    if ($R !== NULL) {
        $content .= $R->showForm();
    }
    break;

case 'editshipper':
    $S = new \Shop\Shipper($actionval);
    $content .= Shop\Menu::adminShipping('shipping');
    $content .= $S->Edit();
    break;

case 'editshipment':
    $shipment_id = (int)$actionval;
    if ($shipment_id > 0) {
        if (isset($_REQUEST['ret_url'])) {
            SHOP_setUrl($_REQUEST['ret_url']);
        }
        $S = new Shop\Shipment($shipment_id);
        $V = new Shop\Views\ShipmentForm($S->getOrderID());
        $V->withShipmentID($shipment_id);
        $content = $V->Render();
    }
    break;

case 'payments':
case 'ord_pmts':
    echo "payments deprecated in index.php";die;
    // View payments on an order
    if ($actionval != 'x') {
        $Order = Shop\Order::getInstance($actionval);
        $content .= Shop\Menu::viewOrder($view, $Order);
    } else {
        $content .= Shop\Menu::adminOrders($view);
    }
    $content .= Shop\Payment::adminList($actionval);
    break;

case 'shipments':
case 'ord_ship':
    // View admin list of shipments
    SHOP_setUrl();
    if ($actionval != 'x') {
        $Order = Shop\Order::getInstance($actionval);
        $content .= Shop\Menu::viewOrder($view, $Order);
    } else {
        $content .= Shop\Menu::adminOrders($view);
    }
    $content .= Shop\Shipment::adminList($actionval);
    if ($view == 'shipments') {
        $view = 'orders';       // to set the active top-level menu
    }
    break;

case 'shiporder':
    if (isset($_GET['ret_url'])) {
        SHOP_setUrl($_GET['ret_url']);
    }
    $V = new Shop\Views\ShipmentForm($_GET['order_id']);
    $content .= $V->Render();
    /*$Ord = Shop\Order::getInstance($_GET['order_id']);
    if (!$Ord->isNew()) {
        $content .= $Ord->View('shipment');
    }*/
    break;

case 'order_pl':
    echo $view . " DEPRECATED";die;
    // Get the packing list for an entire order.
    // This is expected to be shown in a _blank browser window/tab.
    $PL = new Shop\Views\OrderPL($actionval);
    if ($PL->canView()) {
        echo $PL->Render();
        exit;
    } else {
        COM_404();
    }
    break;

case 'shipment_pl':
    echo "shipment_pl deprecated";die;
    if ($actionval == 'x') {
        $shipments = SHOP_getVar($_POST, 'shipments', 'array');
    } else {
        $shipments = $actionval;
    }
    $PL = new Shop\Views\Shipment();
    $PL->asPackingList()->withOutput('pdf')->withShipmentId($shipments)->Render();
    break;

case 'taxrates':
    $T = new Shop\Template;
    $T->set_file('tpl', 'upload_tax.thtml');
    $T->set_var(array(
        'admin_url' => SHOP_ADMIN_URL . '/index.php',
        'can_migrate_pp' => Shop\MigratePP::canMigrate(),
        'can_purge_trans' => !$_SHOP_CONF['shop_enabled'],
    ) );
    $T->parse('output', 'tpl');
    $content .= $T->finish($T->get_var('output'));
    $content .= Shop\Tax\table::adminList();
    break;

case 'edittaxrate':
    $content .= Shop\Tax\table::Edit(SHOP_getVar($_GET, 'code'));
    break;

case 'suppliers':
    // Display an admin list of supplier/brand records
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Supplier::adminList();
    break;

case 'rules':
    // Display the list of zone rules
    $content .= Shop\Menu::adminRegions($view);
    $content .= Shop\Rules\Zone::adminList();
    break;

case 'features':
    // Display the list of features
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Feature::adminList();
    break;

case 'rule_edit':
    $content .= Shop\Menu::adminRegions('rules');
    $content .= Shop\Rules\Zone::getInstance($actionval)->Edit();
    break;

case 'ft_edit':
    $Ft = new Shop\Feature($actionval);
    $content .= $Ft->Edit();
    break;

case 'edit_sup':
    // Edit a supplier or brand record
    $Sup = new Shop\Supplier($actionval);
    $content .= $Sup->Edit();
    break;

case 'pv_edit_bulk':
    // Bulk update variants - price, weight, shipping, etc.
    $pv_ids = SHOP_getVar($_POST, 'pv_bulk_id', 'array');
    if (!empty($pv_ids)) {
        $content .= Shop\ProductVariant::bulkEdit($pv_ids);
    }
    break;

case 'prod_bulk_frm':
    // Bulk update product attributes
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Product::BulkUpdateForm($_POST['prod_bulk']);
    break;

case 'editregion':
    echo "$view deprecated";die;
    $region_id = (int)$actionval;
    $content .= Shop\Menu::adminRegions('regions');
    $content .= Shop\Region::getInstance($region_id)->Edit();
    break;

case 'editcountry':
    echo "$view deprecated";die;
    $country_id = (int)$actionval;
    $content .= Shop\Menu::adminRegions('countries');
    $content .= Shop\Country::getInstance($country_id)->Edit();
    break;

case 'editstate':
    echo "$view deprecated";die;
    $state_id = (int)$actionval;
    $content .= Shop\Menu::adminRegions('states');
    $content .= Shop\State::getInstance($state_id)->Edit();
    break;

case 'countries':
    echo "$view deprecated";die;
    $region_id = SHOP_getVar($_GET, 'region_id', 'integer', 0);
    $content .= Shop\Menu::adminRegions($view);
    $content .= Shop\Country::adminList($region_id);
    break;

case 'states':
    echo "$view deprecated";die;
    $country_id = SHOP_getVar($_GET, 'country_id', 'integer', 0);
    $content .= Shop\Menu::adminRegions($view);
    $content .= Shop\State::adminList($country_id);
    break;

case 'regions':
    echo "$view deprecated";die;
    $content .= Shop\Menu::adminRegions($view);
    $content .= Shop\Region::adminList();
    break;

case 'newpayment':
    echo "deprecated";die;
    $Pmt = new Shop\Payment;
    $Pmt->setOrderID($actionval);
    $content .= $Pmt->pmtForm();
    break;

case 'none':
    // Content provided by an action above, don't show anything here
    break;

default:
    SHOP_setUrl();
    $view = 'products';     // to set the active menu
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Product::adminList(SHOP_getVar($_GET, 'cat_id', 'integer'));
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
exit;

?>
