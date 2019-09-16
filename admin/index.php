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
$action = '';
$expected = array(
    // Actions to perform
    'deleteproduct', 'deletecatimage', 'deletecat',
    'saveproduct', 'savecat', 'saveopt', 'deleteopt', 'resetbuttons',
    'gwmove', 'gwsave', 'wfmove', 'gwinstall', 'gwdelete',
    'attrcopy', 'attrmove',
    'dup_product', 'runreport', 'configreport', 'sendcards', 'purgecache',
    'delsale', 'savesale', 'purgecarts', 'saveshipper', 'updcartcurrency',
    'migrate_pp', 'purge_trans', 'ag_del', 'ag_move', 'ag_save',
    // Views to display
    'history', 'orderhist', 'ipnlog', 'editproduct', 'editcat', 'categories',
    'attributes', 'editattr', 'other', 'products', 'gwadmin', 'gwedit',
    'attr_grp', 'ag_edit',
    'wfadmin', 'order', 'reports', 'coupons', 'sendcards_form',
    'sales', 'editsale', 'editshipper', 'shipping', 'ipndetail',
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
$view = 'products';

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

case 'ag_save':
    $AG = new \Shop\AttributeGroup($_POST['ag_id']);
    if (!$AG->Save($_POST)) {
        $content .= COM_showMessageText($LANG_SHOP['invalid_form']);
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?attr_grp=x');
    break;

case 'saveopt':
    $Attr = new \Shop\Attribute($_POST['attr_id']);
    if (!$Attr->Save($_POST)) {
        $content .= COM_showMessageText($LANG_SHOP['invalid_form']);
    }
    if (isset($_POST['attr_id']) && !empty($_POST['attr_id'])) {
        // Updating an existing option, return to the list
        COM_refresh(SHOP_ADMIN_URL . '/index.php?options=x');
    } else {
        COM_refresh(SHOP_ADMIN_URL . '/index.php?editattr=x&item_id=' . $_POST['item_id']);
    }
    break;

case 'deleteopt':
    // attr_id could be via $_GET or $_POST
    $Attr = new \Shop\Attribute($_REQUEST['attr_id']);
    $Attr->Delete();
    $view = 'attributes';
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

case 'gwsave':
    // Save a payment gateway configuration
    $gw = \Shop\Gateway::getInstance($_POST['gw_id']);
    if ($gw !== NULL) {
        $status = $gw->SaveConfig($_POST);
    }
    $view = 'gwadmin';
    break;

case 'ag_move':
    $ag_id = SHOP_getVar($_GET, 'id', 'integer');
    if ($og_id > 0) {
        $AG = new \Shop\AttributeGroup($ag_id);
        $AG->moveRow($actionval);
    }
    $view = 'attr_grp';
    break;

case 'attrmove':
    $attr_id = SHOP_getVar($_GET, 'id', 'integer');
    if ($attr_id > 0) {
        $Attr = new \Shop\Attribute($attr_id);
        $Attr->moveRow($actionval);
    }
    $view = 'attributes';
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
        $res = DB_query("SELECT id FROM {$_TABLES['shop.products']}
                WHERE cat_id = $dest_cat
                AND id <> $src_prod");
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $dest_prod = (int)$A['id'];
                $done_prods[] = $dest_prod;     // track for later
                if ($del_existing) {
                    DB_delete($_TABLES['shop.prod_attr'], 'item_id', $dest_prod);
                }
                $sql = "INSERT IGNORE INTO {$_TABLES['shop.prod_attr']}
                SELECT NULL, $dest_prod, attr_name, attr_value, orderby, attr_price, enabled
                FROM {$_TABLES['shop.prod_attr']}
                WHERE item_id = $src_prod";
                DB_query($sql);
            }
        }
    }

    // If a target product was selected, it's not the same as the source, and hasn't
    // already been done as part of the category, then update the target product also.
    if ($dest_prod > 0 && $dest_prod != $src_prod && !in_array($dest_prod, $done_prods)) {
        if ($del_existing) {
            DB_delete($_TABLES['shop.prod_attr'], 'item_id', $dest_prod);
        }
        $sql = "INSERT IGNORE INTO {$_TABLES['shop.prod_attr']}
            SELECT NULL, $dest_prod, attr_name, attr_value, orderby, attr_price, enabled
            FROM {$_TABLES['shop.prod_attr']}
            WHERE item_id = $src_prod";
        DB_query($sql);
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

default:
    $view = $action;
    break;
}

//SHOP_log('Admin view: ' . $action, SHOP_LOG_DEBUG);
switch ($view) {
case 'history':
    $content .= \Shop\history(true);
    break;

case 'orderhist':
    // Show all purchases
    if (isset($_POST['upd_orders']) && is_array($_POST['upd_orders'])) {
        $i = 0;
        foreach ($_POST['upd_orders'] as $order_id) {
            if (!isset($_POST['newstatus'][$order_id]) ||
                !isset($_POST['oldstatus'][$order_id]) ||
                $_POST['newstatus'][$order_id] == $_POST['oldstatus'][$order_id]) {
                continue;
            }
            $ord = new \Shop\Order($order_id);
            $ord->updateStatus($_POST['newstatus'][$order_id]);
            $i++;
        }
        $msg[] = sprintf($LANG_SHOP['updated_x_orders'], $i);
    }
    $uid = isset($_REQUEST['uid']) ? $_REQUEST['uid'] : 0;
    $content .= \Shop\listOrders(true, $uid);
    break;

case 'coupons':
    $content = Shop\Products\Coupon::adminList();
    break;

case 'order':
    $order = \Shop\Order::getInstance($actionval);
    $order->setAdmin(true);
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
    $view = 'products';
    break;

case 'sales':
    $content .= Shop\Menu::adminCatalog($view);
    $content .= Shop\Sales::adminList();
    $view = 'products';   // cheating, to get the active menu set
    break;

case 'attributes':
    $content .= Shop\Menu::adminCatalog('attributes');
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked options 
        foreach ($_POST['delitem'] as $attr_id) {
            \Shop\Attribute::Delete($attr_id);
        }
    }
    $content .= Shop\Attribute::adminList();
    $view = 'products';
    break;

case 'attr_grp':
    $content .= Shop\Menu::adminCatalog('attr_grp');
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked option groups
        foreach ($_POST['delitem'] as $og_id) {
            \Shop\AttributeGroup::Delete($og_id);
        }
    }
    $content .= Shop\AttributeGroup::adminList();
    $view = 'products';   // cheating, to get the active menu set
    break;

case 'shipping':
    $content .= Shop\Shipper::adminList();
    break;

case 'editattr':
    $attr_id = SHOP_getVar($_GET, 'attr_id', 'integer');
    $content .= Shop\Menu::adminCatalog($view);
    $Attr = new Shop\Attribute($attr_id);
    if ($attr_id == 0) {
        $Attr->item_id = SHOP_getVar($_GET, 'item_id', 'integer');
    }
    $content .= $Attr->Edit();
    break;

case 'ag_edit':
    $ag_id = SHOP_getVar($_GET, 'ag_id');
    $AG = new \Shop\AttributeGroup($ag_id);
    $content .= Shop\Menu::adminCatalog($view);
    $content .= $AG->Edit();
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

default:
    SHOP_setUrl();
    $view = 'products';
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
