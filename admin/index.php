<?php
/**
 * Admin index page for the shop plugin.
 * By default, lists products available for editing.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      Vincent Furia <vinny01@users.sourceforge.net>
 * @copyright   Copyright (c) 2009-2019 Lee Garner
 * @copyright   Copyright (c) 2005-2006 Vincent Furia
 * @package     shop
 * @version     v1.0.0
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
    'gwmove', 'gwsave', 'wfmove', 'gwinstall', 'gwdelete',
    'carrier_save',
    'attrcopy', 'pov_move',
    'dup_product', 'runreport', 'configreport', 'sendcards', 'purgecache',
    'delsale', 'savesale', 'purgecarts', 'saveshipper', 'updcartcurrency',
    'migrate_pp', 'purge_trans', 'pog_del', 'pog_move', 'pog_save',
    'addshipment', 'updateshipment', 'del_shipment', 'delshipping',
    // Views to display
    'history', 'orders', 'ipnlog', 'editproduct', 'editcat', 'categories',
    'options', 'pov_edit', 'other', 'products', 'gwadmin', 'gwedit', 'carrier_config',
    'opt_grp', 'pog_edit', 'carriers',
    'wfadmin', 'order', 'reports', 'coupons', 'sendcards_form',
    'sales', 'editsale', 'editshipper', 'shipping', 'ipndetail',
    'shiporder', 'editshipment', 'shipment_pl', 'order_pl', 'shipments', 'ord_ship',
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
case 'dup_product':
    $P = new \Shop\Product($_REQUEST['id']);
    $P->Duplicate();
    echo COM_refresh(SHOP_ADMIN_URL.'/index.php');
    break;

case 'deleteproduct':
    $P = \Shop\Product::getByID($_REQUEST['id']);
    if (!\Shop\Product::isUsed($_REQUEST['id'])) {
        $P->Delete();
    } else {
        COM_setMsg(sprintf($LANG_SHOP['no_del_item'], $P->name), 'error');
    }
    echo COM_refresh(SHOP_ADMIN_URL);
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
    $id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
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
    if ($C->parent_id == 0) {
        COM_setMsg($LANG_SHOP['dscp_root_cat'], 'error');
    } elseif (\Shop\Category::isUsed($_REQUEST['cat_id'])) {
        COM_setMsg(sprintf($LANG_SHOP['no_del_cat'], $C->cat_name), 'error');
    } else {
        $C->Delete();
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/index.php?categories');
    break;

case 'saveproduct':
    $url = SHOP_getUrl(SHOP_ADMIN_URL);
    $P = new \Shop\Product($_POST['id']);
    if (!$P->Save($_POST)) {
        $msg = $P->PrintErrors();
        if ($msg != '') {
            COM_setMsg($msg, 'error');
        }
    }
    echo COM_refresh($url);
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

case 'pog_save':
    $POG = new \Shop\ProductOptionGroup($_POST['og_id']);
    if (!$POG->Save($_POST)) {
        $content .= COM_showMessageText($LANG_SHOP['invalid_form']);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?opt_grp=x');
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
        COM_refresh(SHOP_ADMIN_URL . '/index.php?pov_edit=x&item_id=' . $_POST['item_id'] . '&pog_id=' . $Opt->getGroupID());
    }
    break;

case 'pog_del':
    Shop\ProductOptionGroup::Delete($_REQUEST['og_id']);
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

case 'gwinstall':
    $gwname = $_GET['gwname'];
    $gw = \Shop\Gateway::getInstance($gwname);
    if ($gw !== NULL) {
        if ($gw->Install()) {
            $msg[] = "Gateway \"$gwname\" installed successfully";
        } else {
            $msg[] = "Failed to install the \"$gwname\" gateway";
        }
    }
    $view = 'gwadmin';
    break;

case 'gwdelete':
    $gw = \Shop\Gateway::getInstance($_GET['id']);
    if ($gw !== NULL) {
        $status = $gw->Remove();
    }
    $view = 'gwadmin';
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

case 'gwsave':
    // Save a payment gateway configuration
    $gw = \Shop\Gateway::getInstance($_POST['gw_id']);
    if ($gw !== NULL) {
        $status = $gw->SaveConfig($_POST);
    }
    $view = 'gwadmin';
    break;

case 'pog_move':
    $og_id = SHOP_getVar($_GET, 'id', 'integer');
    if ($og_id > 0) {
        $OG = new \Shop\ProductOptionGroup($og_id);
        $OG->moveRow($actionval);
    }
    $view = 'opt_grp';
    break;

case 'pov_move':
    $opt_id = SHOP_getVar($_GET, 'id', 'integer');
    if ($opt_id > 0) {
        $Opt = new \Shop\ProductOptionValue($opt_id);
        $Opt->moveRow($actionval);
    }
    $view = 'options';
    break;

case 'gwmove':
    \Shop\Gateway::moveRow($_GET['id'], $actionval);
    $view = 'gwadmin';
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
    if ($src_prod < 1) break;

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
            Shop\ProductOptionValue::cloneProduct($src_prod, $dst_id, $del_existing);
        }
    }

    // If a target product was selected, it's not the same as the source, and hasn't
    // already been done as part of the category, then update the target product also.
    if ($dest_prod > 0 && $dest_prod != $src_prod && !in_array($dest_prod, $done_prods)) {
        Shop\ProductOptionValue::cloneProduct($src_prod, $dest_prod, $del_existing);
    }
    \Shop\Cache::clear();
    echo COM_refresh(SHOP_ADMIN_URL . '/index.php?options=x');
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
    if (!empty($uids)) {
        $uids = explode('|', $uids);
    } else {
        $uids = array();
    }
    if ($gid > 0) {
        $sql = "SELECT ug_uid FROM {$_TABLES['group_assignments']}
                WHERE ug_main_grp_id = $gid AND ug_uid > 1";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $uids[] = $A['ug_uid'];
        }
    }
    $errs = array();
    if ($amt < .01) $errs[] = $LANG_SHOP['err_gc_amt'];
    if (empty($uids)) $errs[] = $LANG_SHOP['err_gc_nousers'];
    if (empty($errs)) {
        foreach ($uids as $uid) {
            $code = \Shop\Products\Coupon::Purchase($amt, $uid, $exp);
            $email = DB_getItem($_TABLES['users'], 'email', "uid = $uid");
            if (!empty($email)) {
                \Shop\Products\Coupon::Notify($code, $email, $amt, '', '', $exp);
            }
        }
        COM_setMsg(count($uids) . ' coupons sent');
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

case 'delsale':
    $id = SHOP_getVar($_REQUEST, 'id', 'integer', 0);
    if ($id > 0) {
        \Shop\Sales::Delete($id);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?sales');
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
    $S = new Shop\Shipment($actionval);
    $S->Delete();
    $url = SHOP_getUrl(SHOP_ADMIN_URL . '/index.php?shipments');
    COM_refresh($url);
    break;

case 'addshipment':
    $S = Shop\Shipment::create($_POST['order_id']);
    if ($S->Save($_POST)) {
        COM_refresh(SHOP_getUrl(SHOP_ADMIN_URL . '/index.php?order=' . $S->order_id));
    } else {
        COM_setMsg("Error Adding Shipment, see the error log");
        COM_refresh(SHOP_ADMIN_URL . '/index.php?shiporder=x&order_id=' . urlencode($_POST['order_id']));
    }
    break;

default:
    $view = $action;
    break;
}

//SHOP_log('Admin view: ' . $action, SHOP_LOG_DEBUG);
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
    $content = Shop\Products\Coupon::adminList();
    break;

case 'order':
    $order = \Shop\Order::getInstance($actionval);
    $order->setAdmin(true);
    $content .= Shop\Menu::viewOrder($view, $order);
    $content .= $order->View('adminview');
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
    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $P = new \Shop\Product($id);
    if ($id == 0 && isset($_POST['short_description'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $P->SetVars($_POST);
    }
    $content .= $P->showForm();
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
    $content .= Shop\Shipper::carrierLIst();
    break;

case 'pov_edit':
    $opt_id = SHOP_getVar($_GET, 'opt_id', 'integer');
    $content .= Shop\Menu::adminCatalog($view);
    $Opt = new Shop\ProductOptionValue($opt_id);
    if ($opt_id == 0) {
        $Opt->setGroupID(SHOP_getVar($_GET, 'pog_id', 'integer'));
        $Opt->setItemID(SHOP_getVar($_GET, 'item_id', 'integer'));
    }
    $content .= $Opt->Edit();
    break;

case 'pog_edit':
    $og_id = SHOP_getVar($_GET, 'og_id');
    $OG = new \Shop\ProductOptionGroup($og_id);
    $content .= Shop\Menu::adminCatalog($view);
    $content .= $OG->Edit();
    break;

case 'editsale':
    $id = SHOP_getVar($_GET, 'id', 'integer', 0);
    $D = new \Shop\Sales($id);
    $content .= $D->Edit();
    break;

case 'other':
    $T = SHOP_getTemplate('other_functions', 'funcs');
    $T->set_var(array(
        'admin_url' => SHOP_ADMIN_URL . '/index.php',
        'can_migrate_pp' => Shop\MigratePP::canMigrate(),
        'can_purge_trans' => !$_SHOP_CONF['shop_enabled'],
    ) );
    $T->parse('output', 'funcs');
    $content = $T->finish($T->get_var('output'));
    break;

case 'sendcards_form':
    $T = SHOP_getTemplate('send_cards', 'cards');
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
    $content .= Shop\Gateway::adminList();
    break;

case 'gwedit':
    $gw = \Shop\Gateway::getInstance($_GET['gw_id']);
    if ($gw !== NULL) {
        $content .= $gw->Configure();
    }
    break;

case 'carrier_config':
    $Shipper = \Shop\Shipper::getByCode($actionval);
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
    $content .= $S->Edit();
    break;

case 'editshipment':
    $shipment_id = (int)$actionval;
    if ($shipment_id > 0) {
        if (isset($_REQUEST['ret_url'])) {
            SHOP_setUrl($_REQUEST['ret_url']);
        }
        $S = new Shop\Shipment($shipment_id);
        $V = new Shop\Views\Shipment($S->order_id);
        $V->setShipmentID($shipment_id);
        $content = $V->Render($action);
    }
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
    $V = new Shop\Views\Shipment($_GET['order_id']);
    $content .= $V->Render($action);
    /*
    $Ord = Shop\Order::getInstance($_GET['order_id']);
    if (!$Ord->isNew) {
        $content .= $Ord->View('shipment');
    }*/
    break;

case 'order_pl':
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
    if ($actionval == 'x') {
        $shipments = SHOP_getVar($_POST, 'shipments', 'array');
    } else {
        $shipments = $actionval;
    }
    Shop\Views\ShipmentPL::printPDF($shipments, $view);
    break;

default:
    SHOP_setUrl();
    $view = 'products';     // to set the active menu
    $cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Product::adminList($cat_id);
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
