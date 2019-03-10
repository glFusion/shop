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
 * @version     v0.7.0
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
USES_shop_functions();
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
    'deleteproduct', 'deletecatimage', 'deletecat', 'delete_img',
    'saveproduct', 'savecat', 'saveopt', 'deleteopt', 'resetbuttons',
    'gwmove', 'gwsave', 'wfmove', 'gwinstall', 'gwdelete',
    'attrcopy', 'attrmove',
    'dup_product', 'runreport', 'configreport', 'sendcards', 'purgecache',
    'deldiscount', 'savediscount', 'purgecarts', 'saveshipping', 'updcartcurrency',
    'migrate_pp',
    // Views to display
    'history', 'orderhist', 'ipnlog', 'editproduct', 'editcat', 'catlist',
    'attributes', 'editattr', 'other', 'productlist', 'gwadmin', 'gwedit',
    'wfadmin', 'order', 'reports', 'coupons', 'sendcards_form',
    'sales', 'editdiscount', 'editshipping', 'shipping',
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
$view = 'productlist';

switch ($action) {
case 'dup_product':
    $P = new \Shop\Product($_REQUEST['id']);
    $P->Duplicate();
    echo COM_refresh(SHOP_ADMIN_URL.'/index.php');
    break;

case 'deleteproduct':
    $P = \Shop\Product::getInstance($_REQUEST['id']);
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
        $view = 'catlist';
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
    echo COM_refresh(SHOP_ADMIN_URL . '/index.php?catlist');
    break;

case 'delete_img':
    $img_id = (int)$_REQUEST['img_id'];
    \Shop\Product::deleteImage($img_id);
    $view = 'editproduct';
    break;

case 'saveproduct':
    $P = new \Shop\Product($_POST['id']);
    if (!$P->Save($_POST)) {
        $content .= \Shop\SHOP_errMsg($P->PrintErrors());
        $view = 'editproduct';
    }
    break;

case 'savecat':
    $C = new \Shop\Category($_POST['cat_id']);
    if (!$C->Save($_POST)) {
        $content .= COM_showMessageText($C->PrintErrors());
        $view = 'editcat';
    } else {
        $view = 'catlist';
    }
    break;

case 'saveopt':
    $Attr = new \Shop\Attribute($_POST['attr_id']);
    if (!$Attr->Save($_POST)) {
        $content .= COM_showMessageText($LANG_SHOP['invalid_form']);
    }
    if (isset($_POST['attr_id']) && !empty($_POST['attr_id'])) {
        // Updating an existing option, return to the list
        COM_refresh(SHOP_ADMIN_URL . '/index.php?attributes=x');
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
    include_once SHOP_PI_PATH . '/migrate_pp.php';
    if (SHOP_migrate_pp()) {
        COM_setMsg($LANG_SHOP['migrate_pp_ok']);
    } else {
        COM_setMsg($LANG_SHOP['migrate_pp_error'], 'error');
    }
    COM_refresh(SHOP_ADMIN_URL . '/index.php?other=x');
    break;

case 'purgecarts':
    \Shop\Cart::Purge();
    COM_setMsg($LANG_SHOP['carts_purged']);
    COM_refresh(SHOP_ADMIN_URL . '/index.php?other=x');
    break;

case 'saveshipping':
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
    // Copy attributes from a product to another product or category
    $src_prod = (int)$_POST['src_prod'];
    $dest_prod = (int)$_POST['dest_prod'];
    $dest_cat = (int)$_POST['dest_cat'];
    $del_existing = isset($_POST['del_existing_attr']) ? true : false;
    $done_prods = array();

    // Nothing to do if no source product selected
    if ($src_prod < 1) break;

    // Copy product attributes to all products in a category.
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
    echo COM_refresh(SHOP_ADMIN_URL . '/index.php?attributes=x');
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
            $code = \Shop\Coupon::Purchase($amt, $uid, $exp);
            $email = DB_getItem($_TABLES['users'], 'email', "uid = $uid");
            if (!empty($email)) {
                \Shop\Coupon::Notify($code, $email, $amt, '', $exp);
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

case 'savediscount':
    $D = new \Shop\Sales($_POST['id']);
    if (!$D->Save($_POST)) {
        COM_setMsg($LANG_SHOP['msg_nochange']);
        COM_refresh(SHOP_ADMIN_URL . '/index.php?editdiscount&id=' . $D->id);
    } else {
        COM_setMsg($LANG_SHOP['msg_updated']);
        COM_refresh(SHOP_ADMIN_URL . '/index.php?sales');
    }
    exit;
    break;

case 'deldiscount':
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

//SHOP_debug('Admin view: ' . $action);
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
    $content .= SHOP_couponlist();
    break;

case 'order':
    $order = \Shop\Order::getInstance($actionval);
    $order->setAdmin(true);
    $content .= $order->View('adminview');
    break;

case 'ipnlog':
    $op = isset($_REQUEST['op']) ? $_REQUEST['op'] : 'all';
    $log_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $txn_id = isset($_REQUEST['txn_id']) ?
                    COM_applyFilter($_REQUEST['txn_id']) : '';
    switch ($op) {
    case 'single':
        $content .= \Shop\ipnlogSingle($log_id, $txn_id);
        break;
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

case 'catlist':
    $content .= SHOP_adminlist_Category();
    break;

case 'sales':
    $content .= SHOP_adminlist_Sales();
    break;

case 'attributes':
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked attributes
        foreach ($_POST['delitem'] as $attr_id) {
            \Shop\Attribute::Delete($attr_id);
        }
    }
    $content .= SHOP_adminlist_Attributes();
    break;

case 'shipping':
    $content .= SHOP_adminlist_Shippers();
    break;

case 'editattr':
    $attr_id = SHOP_getVar($_GET, 'attr_id');
    $Attr = new \Shop\Attribute($attr_id);
    $content .= $Attr->Edit();
    break;

case 'editdiscount':
    $id = SHOP_getVar($_GET, 'id', 'integer', 0);
    $D = new \Shop\Sales($id);
    $content .= $D->Edit();
    break;

case 'other':
    $T = SHOP_getTemplate('other_functions', 'funcs');
    $can_migrate_pp = (
        is_file($_CONF['path'] . '/plugins/paypal/paypal.php') &&
        !\Shop\Order::haveOrders() &&
        !\Shop\Product::haveProducts()
    );
    $T->set_var(array(
        'admin_url' => SHOP_ADMIN_URL . '/index.php',
        'can_migrate_pp' => $can_migrate_pp,
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
        $expires = \Shop\Coupon::MAX_EXP;
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
    $content .= SHOP_adminList_Gateway();
    break;

case 'gwedit':
    $gw = \Shop\Gateway::getInstance($_GET['gw_id']);
    if ($gw !== NULL) {
        $content .= $gw->Configure();
    }
    break;

case 'wfadmin':
    $content .= SHOP_adminlist_Workflow();
    $content .= SHOP_adminlist_OrderStatus();
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

case 'editshipping':
    $S = new \Shop\Shipper($actionval);
    $content .= $S->Edit();
    break;

default:
    $view = 'productlist';
    $cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
    $content .= SHOP_adminlist_Product($cat_id);
    break;
}

$display = COM_siteHeader();
$display .= \Shop\Menu::Admin($view);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    /*$display .= COM_startBlock('Message');
    $display .= $messages;
    $display .= COM_endBlock();*/
    $display .= COM_showMessageText($messages);
}

$display .= $content;
$display .= COM_siteFooter();
echo $display;
exit;


/**
 * Product Admin List View.
 *
 * @param   integer $cat_id     Optional category ID to limit listing
 * @return  string      HTML for the product list.
 */
function SHOP_adminlist_Product($cat_id=0)
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

    $display = '';
    $sql = "SELECT
                p.id, p.name, p.short_description, p.description, p.price,
                p.prod_type, p.enabled, p.featured,
                c.cat_id, c.cat_name
            FROM {$_TABLES['shop.products']} p
            LEFT JOIN {$_TABLES['shop.categories']} c
                ON p.cat_id = c.cat_id";

    $header_arr = array(
        array('text' => 'ID',
                'field' => 'id', 'sort' => true),
        array('text' => $LANG_ADMIN['edit'],
                'field' => 'edit', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_ADMIN['copy'],
                'field' => 'copy', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_SHOP['enabled'],
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_SHOP['featured'],
                'field' => 'featured', 'sort' => true,
                'align' => 'center'),
        array('text' => $LANG_SHOP['product'],
                'field' => 'name', 'sort' => true),
        array('text' => $LANG_SHOP['description'],
                'field' => 'short_description', 'sort' => true),
        array('text' => $LANG_SHOP['category'],
                'field' => 'cat_name', 'sort' => true),
        array('text' => $LANG_SHOP['price'],
                'field' => 'price', 'sort' => true, 'align' => 'right'),
        array('text' => $LANG_SHOP['prod_type'],
                'field' => 'prod_type', 'sort' => true),
        array('text' => $LANG_ADMIN['delete'] .
                    '&nbsp;<i class="uk-icon uk-icon-question-circle tooltip" title="' .
                    $LANG_SHOP_HELP['hlp_prod_delete'] . '"></i>',
                'field' => 'delete', 'sort' => false,
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'id',
            'direction' => 'asc');

    $display .= COM_startBlock('', '',
                    COM_getBlockTemplate('_admin_block', 'header'));
    $display .= COM_createLink($LANG_SHOP['new_product'],
        SHOP_ADMIN_URL . '/index.php?editproduct=x',
        array(
            'class' => 'uk-button uk-button-success',
            'style' => 'float:left',
        )
    );

    if ($cat_id > 0) {
        $def_filter = "WHERE c.cat_id='$cat_id'";
    } else {
        $def_filter = 'WHERE 1=1';
    }
    $query_arr = array('table' => 'shop.products',
        'sql' => $sql,
        'query_fields' => array('p.name', 'p.short_description',
                            'p.description', 'c.cat_name'),
        'default_filter' => $def_filter,
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => SHOP_ADMIN_URL . '/index.php',
    );
    $cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
    $filter = $LANG_SHOP['category'] . ': <select name="cat_id"
            onchange="javascript: document.location.href=\'' .
                SHOP_ADMIN_URL .
                '/index.php?view=prodcts&amp;cat_id=\'+' .
                'this.options[this.selectedIndex].value">' .
        '<option value="0">' . $LANG_SHOP['all'] . '</option>' . LB .
        COM_optionList($_TABLES['shop.categories'], 'cat_id, cat_name',
                $cat_id, 1) .
        "</select>" . LB;

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_productlist',
            __NAMESPACE__ . '\getAdminField_Product',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Get an individual field for the history screen.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getAdminField_Product($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

    $retval = '';

    switch($fieldname) {
    case 'copy':
        $retval .= COM_createLink('<i class="uk-icon uk-icon-clone tooltip" title="' . $LANG_ADMIN['copy'] . '"></i>',
                SHOP_ADMIN_URL . "/index.php?dup_product=x&amp;id={$A['id']}"
        );
        break;

    case 'edit':
        $retval .= COM_createLink('<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
            SHOP_ADMIN_URL . "/index.php?editproduct=x&amp;id={$A['id']}"
        );
        break;

    case 'delete':
        if (!\Shop\Product::isUsed($A['id'])) {
            $retval .= COM_createLink('<i class="uk-icon uk-icon-trash uk-text-danger tooltip" title="' . $LANG_ADMIN['delete'] . '"></i>',
                    SHOP_ADMIN_URL. '/index.php?deleteproduct=x&amp;id=' . $A['id'],
                array(
                    'onclick'=>'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['q_del_item'],
                )
            );
        } else {
            $retval = '';
        }
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"enabled\",".
                "\"product\");' />" . LB;
        break;

    case 'featured':
        if ($fieldvalue == '1') {
            $switch = ' checked="checked"';
            $enabled = 1;
        } else {
            $switch = '';
            $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togfeatured{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"featured\",".
                "\"product\");' />" . LB;
        break;

    case 'name':
        $retval = COM_createLink(
            $fieldvalue,
            SHOP_ADMIN_URL . '/report.php?run=itempurchase&item_id=' . $A['id'],
            array(
                'class' => 'tooltip',
                'title' => $LANG_SHOP['item_history'],
            )
         );
        break;

    case 'prod_type':
        if (isset($LANG_SHOP['prod_types'][$A['prod_type']])) {
            $retval = $LANG_SHOP['prod_types'][$A['prod_type']];
        } else {
            $retval = '';
        }
        break;

    case 'cat_name':
        $retval = COM_createLink($fieldvalue,
                SHOP_ADMIN_URL . '/index.php?cat_id=' . $A['cat_id']);
        break;

    case 'short_description':
        $retval = COM_createLink(
            $fieldvalue,
            SHOP_URL . '/detail.php?id=' . $A['id'],
            array(
                'class' => 'tooltip',
                'title' => $LANG_SHOP['see_details'],
            )
            );
        break;

    case 'price':
        $retval = \Shop\Currency::getInstance()->formatValue($fieldvalue);
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
 * Get the to-do list to display at the top of the admin screen.
 * There's probably a less sql-expensive way to do this.
 *
 * @return  array   Array of strings (the to-do list)
 */
function SHOP_adminTodo()
{
    global $_TABLES, $LANG_SHOP;

    $todo = array();
    if (DB_count($_TABLES['shop.products']) == 0)
        $todo[] = $LANG_SHOP['todo_noproducts'];

    if (DB_count($_TABLES['shop.gateways'], 'enabled', 1) == 0)
        $todo[] = $LANG_SHOP['todo_nogateways'];

    return $todo;
}


/**
 * Displays the list of ipn history from the log stored in the database
 *
 * @return string HTML string containing the contents of the ipnlog
 */
function SHOP_adminlist_IPNLog()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

    $display = '';
    $sql = "SELECT * FROM {$_TABLES['shop.ipnlog']} ";

    $header_arr = array(
        array('text' => 'ID',
                'field' => 'id', 'sort' => true),
        array('text' => $LANG_SHOP['ip_addr'],
                'field' => 'ip_addr', 'sort' => false),
        array('text' => $LANG_SHOP['datetime'],
                'field' => 'ts', 'sort' => true),
        array('text' => $LANG_SHOP['verified'],
                'field' => 'verified', 'sort' => true),
        array('text' => $LANG_SHOP['txn_id'],
                'field' => 'txn_id', 'sort' => true),
        array('text' => $LANG_SHOP['gateway'],
                'field' => 'gateway', 'sort' => true),
    );

    $defsort_arr = array('field' => 'ts',
            'direction' => 'desc');

    $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'shop.ipnlog',
        'sql' => $sql,
        'query_fields' => array('ip_addr', 'txn_id'),
        'default_filter' => 'WHERE 1=1',
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => SHOP_ADMIN_URL . '/index.php?ipnlog=x',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_ipnlog',
            __NAMESPACE__ . '\getAdminField_IPNLog',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Get an individual field for the IPN Log screen.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getAdminField_IPNLog($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP, $_TABLES;

    $retval = '';
    static $Dt = NULL;
    if ($Dt === NULL) $Dt = new Date('now', $_CONF['timezone']);

    switch($fieldname) {
    case 'id':
        $retval = COM_createLink($fieldvalue,
                SHOP_ADMIN_URL .
                '/index.php?ipnlog=x&amp;op=single&amp;id=' . $A['id']);
        break;

    case 'verified':
        $retval = $fieldvalue > 0 ? 'True' : 'False';
        break;

    case 'txn_id':
        $retval = COM_createLink($fieldvalue,
                SHOP_ADMIN_URL .
                '/index.php?ipnlog=x&amp;op=single&amp;txn_id=' . $fieldvalue);
        break;

    case 'ts':
        $Dt->setTimestamp((int)$fieldvalue);
        $retval = SHOP_dateTooltip($Dt);
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
 * Category Admin List View.
 *
 * @return  string      HTML for the category listing
 */
function SHOP_adminlist_Category()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

    $display = '';
    $sql = "SELECT
                cat.cat_id, cat.cat_name, cat.description, cat.enabled,
                cat.grp_access, parent.cat_name as pcat
            FROM {$_TABLES['shop.categories']} cat
            LEFT JOIN {$_TABLES['shop.categories']} parent
            ON cat.parent_id = parent.cat_id";

    $header_arr = array(
        array('text' => 'ID',
                'field' => 'cat_id', 'sort' => true),
        array('text' => $LANG_ADMIN['edit'],
                'field' => 'edit', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_SHOP['enabled'],
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_SHOP['category'],
                'field' => 'cat_name', 'sort' => true),
        array('text' => $LANG_SHOP['description'],
                'field' => 'description', 'sort' => false),
        array('text' => $LANG_SHOP['parent_cat'],
                'field' => 'pcat', 'sort' => true),
        array('text' => $LANG_SHOP['visible_to'],
                'field' => 'grp_access', 'sort' => false),
        array('text' => $LANG_ADMIN['delete'] .
                    '&nbsp;<i class="uk-icon uk-icon-question-circle tooltip" title="' .
                    $LANG_SHOP_HELP['hlp_cat_delete'] . '"></i>',
                'field' => 'delete', 'sort' => false,
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'cat_id',
            'direction' => 'asc');

    $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
    $display .= COM_createLink($LANG_SHOP['new_category'],
        SHOP_ADMIN_URL . '/index.php?editcat=x',
        array(
            'class' => 'uk-button uk-button-success',
            'style' => 'float:left',
        )
    );

    $query_arr = array('table' => 'shop.categories',
        'sql' => $sql,
        'query_fields' => array('cat.cat_name', 'cat.description'),
        'default_filter' => 'WHERE 1=1',
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => SHOP_ADMIN_URL . '/index.php?catlist=x',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_catlist',
            __NAMESPACE__ . '\getAdminField_Category',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Get an individual field for the category admin list.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getAdminField_Category($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP, $_TABLES, $LANG_ADMIN;

    $retval = '';
    static $grp_names = array();
    static $now = NULL;
    if ($now === NULL) $now = SHOP_now()->format('Y-m-d');

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink('<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_SHOP['edit'] . '"></i>',
            SHOP_ADMIN_URL . "/index.php?editcat=x&amp;id={$A['cat_id']}"
        );
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
            $switch = ' checked="checked"';
            $enabled = 1;
        } else {
            $switch = '';
            $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['cat_id']}\"
                onclick='SHOP_toggle(this,\"{$A['cat_id']}\",\"enabled\",".
                "\"category\");' />" . LB;
        break;

    case 'grp_access':
        $fieldvalue = (int)$fieldvalue;
        if (!isset($grp_names[$fieldvalue])) {
            $grp_names[$fieldvalue] = DB_getItem($_TABLES['groups'], 'grp_name',
                        "grp_id = $fieldvalue");
        }
        $retval = $grp_names[$fieldvalue];
        break;

    case 'delete':
        if (!\Shop\Category::isUsed($A['cat_id'])) {
            $retval .= COM_createLink('<i class="uk-icon uk-icon-trash uk-text-danger tooltip"></i>',
                SHOP_ADMIN_URL. '/index.php?deletecat=x&amp;cat_id=' . $A['cat_id'],
                array(
                    'onclick'=>"return confirm('{$LANG_SHOP['q_del_item']}');",
                    'title' => $LANG_ADMIN['delete'],
                    'data-uk-tooltip' => '',
                )
            );
        }
        break;

    case 'description':
        $retval = strip_tags($fieldvalue);
        if (utf8_strlen($retval) > 80) {
            $retval = substr($retval, 0, 80 ) . '...';
        }
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
 * Displays the list of product attributes.
 *
 * @return  string  HTML string containing the contents of the ipnlog
 */
function SHOP_adminlist_Attributes()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

    $sql = "SELECT a.*, p.name AS prod_name
            FROM {$_TABLES['shop.prod_attr']} a
            LEFT JOIN {$_TABLES['shop.products']} p
            ON a.item_id = p.id
            WHERE 1=1 ";

    if (isset($_POST['product_id']) && $_POST['product_id'] != '0') {
        $sel_prod_id = (int)$_POST['product_id'];
        $sql .= "AND p.id = '$sel_prod_id' ";
    } else {
        $sel_prod_id = '';
    }

    $header_arr = array(
        array(
            'text' => 'ID',
            'field' => 'attr_id',
            'sort' => true,
        ),
        array(
            'text' => $LANG_SHOP['edit'],
            'field' => 'edit',
            'sort' => false,
            'align' => 'center',
        ),
        array(
            'text' => $LANG_SHOP['enabled'],
            'field' => 'enabled',
            'sort' => false,
            'align' => 'center',
        ),
        array(
            'text' => $LANG_SHOP['product'],
            'field' => 'prod_name',
            'sort' => true,
        ),
        array(
            'text' => $LANG_SHOP['attr_name'],
            'field' => 'attr_name',
            'sort' => true,
        ),
        array(
            'text' => $LANG_SHOP['attr_value'],
            'field' => 'attr_value',
            'sort' => true,
        ),
        array(
            'text'  => $LANG_SHOP['orderby'],
            'field' => 'orderby',
            'align' => 'center',
            'sort'  => true,
        ),
        array(
            'text' => $LANG_SHOP['attr_price'],
            'field' => 'attr_price',
            'align' => 'right',
            'sort' => true,
        ),
        array(
            'text' => $LANG_ADMIN['delete'],
            'field' => 'delete',
            'sort' => 'false',
            'align' => 'center',
        ),
    );

    $defsort_arr = array(
            'field' => 'prod_name,attr_name,orderby',
            'direction' => 'ASC');

    $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
    $display .= COM_createLink($LANG_SHOP['new_attr'],
        SHOP_ADMIN_URL . '/index.php?editattr=0',
        array(
            'style' => 'float:left;',
            'class' => 'uk-button uk-button-success',
        )
    );
    $product_selection = COM_optionList($_TABLES['shop.products'], 'id, name', $sel_prod_id);
    $filter = "{$LANG_SHOP['product']}: <select name=\"product_id\"
        onchange=\"this.form.submit();\">
        <option value=\"0\">-- Any --</option>\n" .
        $product_selection .
        "</select>&nbsp;\n";

    $query_arr = array('table' => 'shop.prod_attr',
        'sql' => $sql,
        'query_fields' => array('p.name', 'attr_name', 'attr_value'),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => SHOP_ADMIN_URL . '/index.php?attributes=x',
    );

    $options = array('chkdelete' => true, 'chkfield' => 'attr_id');

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_attrlist',
            __NAMESPACE__ . '\getAdminField_Attribute',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, '');

    // Create the "copy attributes" form at the bottom
    $T = SHOP_getTemplate('copy_attributes_form', 'copy_attr_form');
    $T->set_var(array(
        'src_product'       => $product_selection,
        'product_select'    => COM_optionList($_TABLES['shop.products'], 'id, name'),
        'cat_select'        => COM_optionList($_TABLES['shop.categories'], 'cat_id,cat_name'),
        'uikit'     => $_SYSTEM['framework'] == 'uikit' ? 'true' : '',
    ) );
    $display .= $T->parse('output', 'copy_attr_form');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Displays the list of product attributes.
 *
 * @return  string  HTML string containing the contents of the ipnlog
 */
function SHOP_adminlist_Shippers()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

    $sql = "SELECT * FROM {$_TABLES['shop.shipping']}";

    $header_arr = array(
        array(
            'text'  => 'ID',
            'field' => 'id',
            'sort'  => true,
        ),
        array(
            'text'  => $LANG_SHOP['edit'],
            'field' => 'edit',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => $LANG_SHOP['enabled'],
            'field' => 'enabled',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => $LANG_SHOP['name'],
            'field' => 'name',
        ),
    );

    $defsort_arr = array(
        'field' => 'name',
        'direction' => 'ASC',
    );

    $query_arr = array(
        'table' => 'shop.shipping',
        'sql' => $sql,
        'query_fields' => array(),
        'default_filter' => '',
    );

    $text_arr = array(
        //'has_extras' => true,
        'form_url' => SHOP_ADMIN_URL . '/index.php?shipping=x',
    );

    $options = array('chkdelete' => true, 'chkfield' => 'id');
    $filter = '';
    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
    $display .= COM_createLink($LANG_SHOP['new_ship_method'],
        SHOP_ADMIN_URL . '/index.php?editshipping=0',
        array('class' => 'uk-button uk-button-success')
    );
    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_shiplist',
            __NAMESPACE__ . '\getAdminField_Shipper',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, '');
    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Get an individual field for the options admin list.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getAdminField_Attribute($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink(
            '<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
            SHOP_ADMIN_URL . "/index.php?editattr=x&amp;attr_id={$A['attr_id']}"
        );
        break;

    case 'orderby':
        $retval = COM_createLink(
                '<i class="uk-icon uk-icon-arrow-up"></i>',
                SHOP_ADMIN_URL . '/index.php?attrmove=up&id=' . $A['attr_id']
            ) .
            COM_createLink('<i class="uk-icon uk-icon-arrow-down"></i>',
                SHOP_ADMIN_URL . '/index.php?attrmove=down&id=' . $A['attr_id']
            );
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['attr_id']}\"
                onclick='SHOP_toggle(this,\"{$A['attr_id']}\",\"enabled\",".
                "\"attribute\");' />" . LB;
        break;

    case 'delete':
        $retval .= COM_createLink(
            '<i class="uk-icon uk-icon-trash uk-text-danger tooltip" title="' . $LANG_ADMIN['delete'] . '"></i>',
            SHOP_ADMIN_URL. '/index.php?deleteopt=x&amp;attr_id=' . $A['attr_id'],
            array(
                'onclick'=>'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                'title' => 'Delete this item',
            )
        );
        break;

    case 'attr_price':
        $retval = \Shop\Currency::getInstance()->FormatValue($fieldvalue);
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
 * Get an individual field for the shipping profiles.
 *
 * @param  string  $fieldname  Name of field (from the array, not the db)
 * @param  mixed   $fieldvalue Value of the field
 * @param  array   $A          Array of all fields from the database
 * @param  array   $icon_arr   System icon array (not used)
 * @return string              HTML for field display in the table
 */
function getAdminField_Shipper($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink(
            '<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
            SHOP_ADMIN_URL . "/index.php?editshipping={$A['id']}"
        );
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"enabled\",".
                "\"shipping\");' />" . LB;
        break;

    case 'delete':
        $retval .= COM_createLink(
            '<i class="uk-icon uk-icon-trash uk-text-danger tooltip" title="' . $LANG_ADMIN['delete'] . '"></i>',
            SHOP_ADMIN_URL. '/index.php?delshipping=x&amp;id=' . $A['id'],
            array(
                'onclick'=>'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                'title' => 'Delete this item',
            )
        );
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
 * Payment Gateway Admin View.
 *
 * @return  string      HTML for the gateway listing
 */
function SHOP_adminList_Gateway()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN,
            $LANG32;

    $sql = "SELECT * FROM {$_TABLES['shop.gateways']}";
    $to_install = \Shop\Gateway::getUninstalled();

    $header_arr = array(
        array(
            'text'  => $LANG_ADMIN['edit'],
            'field' => 'edit',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => $LANG_SHOP['orderby'],
            'field' => 'orderby',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => 'ID',
            'field' => 'id',
            'sort'  => true,
        ),
        array(
            'text'  => $LANG_SHOP['description'],
            'field' => 'description',
            'sort'  => true,
        ),
        array(
            'text'  => $LANG_SHOP['enabled'],
            'field' => 'enabled',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => $LANG_ADMIN['delete'],
            'field' => 'delete',
            'sort'  => 'false',
            'align' => 'center',
        ),
    );

    $defsort_arr = array(
        'field' => 'orderby',
        'direction' => 'ASC',
    );

    $display = COM_startBlock(
        '', '',
        COM_getBlockTemplate('_admin_block', 'header')
    );

    $query_arr = array(
        'table' => 'shop.gateways',
        'sql' => $sql,
        'query_fields' => array('id', 'description'),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => SHOP_ADMIN_URL . '/index.php?gwadmin=x',
    );

    if (!isset($_REQUEST['query_limit'])) {
        $_GET['query_limit'] = 20;
    }

    $display .= ADMIN_list(
        $_SHOP_CONF['pi_name'] . '_gwlist',
        __NAMESPACE__ . '\getAdminField_Gateway',
        $header_arr, $text_arr, $query_arr, $defsort_arr,
        '', '', '', ''
    );

    if (!empty($to_install)) {
        $display .= $LANG_SHOP['gw_notinstalled'] . ':<br />';
        foreach ($to_install as $name=>$gw) {
                $display .= $gw->Description() . '&nbsp;&nbsp;<a href="' .
                    SHOP_ADMIN_URL. '/index.php?gwinstall=x&gwname=' .
                    urlencode($name) . '">' . $LANG32[22] . '</a><br />' . LB;
        }
    }
    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Get an individual field for the options admin list.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getAdminField_Gateway($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink(
            '<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
            SHOP_ADMIN_URL . "/index.php?gwedit=x&amp;gw_id={$A['id']}"
        );
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"{$fieldname}\",".
                "\"gateway\");' />" . LB;
        break;

    case 'orderby':
        $retval = COM_createLink(
                '<i class="uk-icon uk-icon-arrow-up"></i>',
                SHOP_ADMIN_URL . '/index.php?gwmove=up&id=' . $A['id']
            ) .
            COM_createLink(
                '<i class="uk-icon uk-icon-arrow-down"></i>',
                SHOP_ADMIN_URL . '/index.php?gwmove=down&id=' . $A['id']
            );
        break;

    case 'delete':
        $retval = COM_createLink(
            '<i class="uk-icon uk-icon-trash uk-text-danger tooltip" title="' . $LANG_ADMIN['delete'] . '"></i>',
            SHOP_ADMIN_URL. '/index.php?gwdelete=x&amp;id=' . $A['id'],
            array(
                'onclick'=>'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
            )
        );
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
 * Workflow Admin List View.
 *
 * @return  string      HTML for the product list.
 */
function SHOP_adminlist_Workflow()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

    $extra = array(
        'rec_type'  => 'workflow',
    );
    $sql = "SELECT *, 'workflow' AS rec_type
            FROM {$_TABLES['shop.workflows']}";

    $header_arr = array(
        array(
            'text' => $LANG_SHOP['name'],
            'field' => 'wf_name',
            'sort' => false,
        ),
        array(
            'text' => $LANG_SHOP['enabled'],
            'field' => 'wf_enabled',
            'sort' => false,
        ),
    );

    $defsort_arr = array(
        'field'     => 'id',
        'direction' => 'ASC',
    );

    $display = COM_startBlock('', '',
                    COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array(
        'table' => 'shop.workflows',
        'sql' => $sql,
        'query_fields' => array('wf_name'),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => SHOP_ADMIN_URL . '/index.php',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= "<h2>{$LANG_SHOP['workflows']}</h2>\n";
    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_workflowlist',
            __NAMESPACE__ . '\getAdminField_Workflow',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', $extra, '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Sale Pricing Admin List View.
 *
 * @return  string      HTML for the product list.
 */
function SHOP_adminlist_Sales()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

    $sql = "SELECT *
            FROM {$_TABLES['shop.sales']}";

    $header_arr = array(
        array(
            'text' => $LANG_ADMIN['edit'],
            'field' => 'edit',
            'align' => 'center',
        ),
        array(
            'text' => $LANG_SHOP['item_type'],
            'field' => 'item_type',
            'sort' => false,
        ),
        array(
            'text' => $LANG_SHOP['name'],
            'field' => 'name',
            'sort' => false,
        ),
        array(
            'text' => $LANG_SHOP['product'],
            'field' => 'item_id',
            'sort' => false,
        ),
        array(
            'text' => $LANG_SHOP['amount'] . '/' . $LANG_SHOP['percent'],
            'field' => 'amount',
            'sort' => false,
            'align' => 'center',
        ),
        array(
            'text' => $LANG_SHOP['start'],
            'field' => 'start',
            'sort' => true,
        ),
        array(
            'text' => $LANG_SHOP['end'],
            'field' => 'end',
            'sort' => true,
        ),
        array(
            'text' => $LANG_ADMIN['delete'],
            'field' => 'delete',
            'align' => 'center',
        ),
    );

    $defsort_arr = array(
        'field' => 'start',
        'direction' => 'ASC',
    );

    $display = COM_startBlock(
        '', '',
        COM_getBlockTemplate('_admin_block', 'header')
    );

    $query_arr = array(
        'table' => 'shop.sales',
        'sql' => $sql,
        'query_fields' => array(),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => SHOP_ADMIN_URL . '/index.php',
    );

    if (!isset($_REQUEST['query_limit'])) {
        $_GET['query_limit'] = 20;
    }

    $display .= '<div>' . COM_createLink($LANG_SHOP['new_sale'],
        SHOP_ADMIN_URL . '/index.php?editdiscount=x',
        array('class' => 'uk-button uk-button-success')
    ) . '</div>';
    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_discountlist',
            __NAMESPACE__ . '\getAdminField_Sales',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Order Status Admin List View.
 *
 * @return  string      HTML for the product list.
 */
function SHOP_adminlist_OrderStatus()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

    $extra = array(
        'rec_type'  => 'orderstatus',
    );
    $sql = "SELECT * FROM {$_TABLES['shop.orderstatus']}";

    $header_arr = array(
        array(
            'text'  => $LANG_SHOP['name'],
            'field' => 'name',
            'sort'  => false,
        ),
        array(
            'text'  => $LANG_SHOP['enabled'],
            'field' => 'enabled',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => $LANG_SHOP['notify_buyer'],
            'field' => 'notify_buyer',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => $LANG_SHOP['notify_admin'],
            'field' => 'notify_admin',
            'sort'  => false,
            'align' => 'center',
        ),
    );

    $defsort_arr = array(
        'field'     => 'id',
        'direction' => 'ASC',
    );

    $display = COM_startBlock(
        '', '', COM_getBlockTemplate('_admin_block', 'header')
    );

    $query_arr = array(
        'table' => 'shop.orderstatus',
        'sql' => $sql,
        'query_fields' => array('name'),
        'default_filter' => 'WHERE id > 1',
    );

    $text_arr = array(
        'has_extras' => false,
        'has_limit' => true,    // required, or default_filter is ignored
        'form_url' => SHOP_ADMIN_URL . '/index.php',
    );

    $display .= "<h2>{$LANG_SHOP['statuses']}</h2>\n";
    $display .= $LANG_SHOP['admin_hdr_wfstatus'] . "\n";
    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_statuslist',
            __NAMESPACE__ . '\getAdminField_Workflow',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', $extra, '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Get an individual field for the Sales admin list.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getAdminField_Sales($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;
    static $Cur = NULL;
    static $Dt = NULL;
    if ($Cur === NULL) $Cur = \Shop\Currency::getInstance();
    if ($Dt === NULL) $Dt = new Date('now', $_CONF['timezone']);
    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval = COM_createLink('<i class="uk-icon uk-icon-edit"></i>',
                SHOP_ADMIN_URL . '/index.php?editdiscount&id=' . $A['id']
        );
        break;

    case 'delete':
        $retval = COM_createLink('<i class="uk-icon uk-icon-trash uk-text-danger"></i>',
                SHOP_ADMIN_URL . '/index.php?deldiscount&id=' . $A['id'],
                array(
                    'onclick'=>'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                )
        );
        break;

    case 'start':
    case 'end':
        $Dt->setTimestamp((int)$fieldvalue);
        $retval = SHOP_dateTooltip($Dt);
        break;

    case 'item_id':
        switch ($A['item_type']) {
        case 'product':
            $P = \Shop\Product::getInstance($fieldvalue);
            if ($P) {
                $retval = $P->short_description;
            } else {
                $retval = 'Unknown';
            }
            break;
        case 'category':
            if ($fieldvalue == 0) {     // root category
                $retval = $LANG_SHOP['home'];
            } else {
                $C = \Shop\Category::getInstance($fieldvalue);
                $retval = $C->cat_name;
            }
            break;
        default;
            $retval = '';
            break;
        }
        break;

    case 'amount':
        switch ($A['discount_type']) {
        case 'amount':
            $retval = $Cur->format($fieldvalue);
            break;
        case 'percent':
            $retval = $fieldvalue . ' %';
            break;
        }
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }
    return $retval;
}



/**
 * Get an individual field for the options admin list.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getAdminField_Workflow($fieldname, $fieldvalue, $A, $icon_arr, $extra)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP;

    $retval = '';
    $rec_type = $extra['rec_type'];

    switch($fieldname) {
    case 'wf_enabled':
        $fieldvalue = $A['enabled'];
        if ($A['can_disable'] == 1) {
            $retval = "<select id=\"sel{$fieldname}{$A['id']}\" name=\"{$fieldname}_sel\" " .
                "onchange='SHOPupdateSel(this,\"{$A['id']}\",\"enabled\", \"workflow\");'>" . LB;
            foreach ($LANG_SHOP['wf_statuses'] as $val=>$str) {
                $sel = $fieldvalue == $val ? 'selected="selected"' : '';
                $retval .= "<option value=\"{$val}\" $sel>{$str}</option>" . LB;
            }
            $retval .= '</select>' . LB;
        } else {
            $retval = $LANG_SHOP['required'];
        }
        break;

    case 'enabled':
    case 'notify_buyer':
    case 'notify_admin':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"{$fieldname}_check\"
                id=\"tog{$fieldname}{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"{$fieldname}\",".
                "\"{$rec_type}\");' />" . LB;
        break;

    case 'wf_name':
        $retval = $LANG_SHOP[$fieldvalue];
        break;

    case 'name':
        $retval = SHOP_getVar($LANG_SHOP['orderstatus'], $fieldvalue, 'string', $fieldvalue);
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
 * Display the purchase history for coupons.
 *
 * @param   mixed   $item_id    Numeric or string item ID
 * @return  string      Display HTML
 */
function SHOP_couponlist()
{
    global $_TABLES, $LANG_SHOP, $_SHOP_CONF;

    $filt_sql = '';
    if (isset($_GET['filter']) && isset($_GET['value'])) {
        switch ($_GET['filter']) {
        case 'buyer':
        case 'redeemer':
            $filt_sql = "WHERE `{$_GET['filter']}` = '" . DB_escapeString($_GET['value']) . "'";
            break;
        }
    }
    $sql = "SELECT * FROM {$_TABLES['shop.coupons']} $filt_sql";

    $header_arr = array(
        array(
            'text' => $LANG_SHOP['code'],
            'field' => 'code',
            'sort' => true,
        ),
        array(
            'text' => $LANG_SHOP['purch_date'],
            'field' => 'purchased',
            'sort' => true,
        ),
        array(
            'text' => $LANG_SHOP['amount'],
            'field' => 'amount',
            'sort' => false,
            'align' => 'right',
        ),
        array(
            'text' => $LANG_SHOP['balance'],
            'field' => 'balance',
            'sort' => false,
            'align' => 'right',
        ),
        array(
            'text' => $LANG_SHOP['buyer'],
            'field' => 'buyer',
            'sort' => true,
        ),
        array(
            'text' => $LANG_SHOP['redeemer'],
            'field' => 'redeemer',
            'sort' => true,
        ),
    );

    $defsort_arr = array(
        'field' => 'purchased',
        'direction' => 'DESC',
    );

    $query_arr = array(
        'table' => 'shop.coupons',
        'sql' => $sql,
        'query_fields' => array(),
        'default_filter' => '',
    );

    $text_arr = array();
    $text_arr = array(
        'has_extras' => false,
        'form_url' => SHOP_ADMIN_URL . '/index.php?coupons=x',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display = COM_startBlock('', '',
                    COM_getBlockTemplate('_admin_block', 'header'));
    $display .= '<h2>' . $LANG_SHOP['couponlist'] . '</h2>';
    $display .= '<div>' . COM_createLink($LANG_SHOP['send_giftcards'],
        SHOP_ADMIN_URL . '/index.php?sendcards_form=x',
        array('class' => 'uk-button uk-button-primary')
    ) . '</div>';
    $display .= ADMIN_list($_SHOP_CONF['pi_name'] . '_couponlist',
            __NAMESPACE__ . '\getAdminField_coupons',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');
    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Get an individual field for the coupon listing.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getAdminField_coupons($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP;

    $retval = '';
    static $username = array();
    static $Cur = NULL;
    static $Dt = NULL;
    if ($Dt === NULL) $Dt = new Date('now', $_CONF['timezone']);
    if ($Cur === NULL) $Cur = \Shop\Currency::getInstance();

    switch($fieldname) {
    case 'buyer':
    case 'redeemer':
        if (!isset($username[$fieldvalue])) {
            $username[$fieldvalue] = COM_getDisplayName($fieldvalue);
        }
        $retval = COM_createLink($username[$fieldvalue],
            SHOP_ADMIN_URL . "/index.php?coupons=x&filter=$fieldname&value=$fieldvalue",
            array(
                'title' => 'Click to filter by ' . $fieldname,
                'class' => 'tooltip',
            )
        );
        break;

    case 'amount':
    case 'balance':
        $retval = $Cur->FormatValue($fieldvalue);
        break;

    case 'purchased':
    case 'redeemed':
        $Dt->setTimestamp((int)$fieldvalue);
        $retval = SHOP_dateTooltip($Dt);
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


?>
