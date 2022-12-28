<?php
/**
 * Admin index page for the shop plugin zone rules.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import Required glFusion libraries */
require_once('../../../lib-common.php');

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check('shop.admin')
) {
    COM_404();
    exit;
}
require_once('../../auth.inc.php');
USES_lib_admin();
$Request = Shop\Models\Request::getInstance();
$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($Request['msg'])) $msg[] = $Request->getString('msg');

$expected = array(
    // Actions to perform
    'rule_del', 'rule_add', 'rule_save', 'delbutton_x',
    'pr_save', 'pr_del',
    // Views to display
    'zr_list', 'zr_edit', 'pr_list', 'pr_edit',
);
list($action, $actionval) = $Request->getAction($expected, 'pr_list');

switch ($action) {
case 'rule_add':
    $rule_id = $Request->getInt('rule_id');
    if ($actionval > 0) {
        switch ($actionval) {
        case 'region':
        case 'country':
        case 'state':
            Shop\Rules\Zone::getInstance($rule_id)
                ->add($actionval, $Request->getArray($actionval . '_id'))
                ->Save();
            break;
        }
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/rules.php');
    break;

case 'rule_del':
    if ($actionval) {
        Shop\Rules\Zone::deleteRule($actionval);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/rules.php?zr_list');
    break;

case 'delbutton_x':
    $items = $Request->getArray('delitem');
    if (!empty($items)) {
        // Delete some checked options
        foreach ($items as $opt_id) {
            Shop\Rules\Zone::deleteRule($opt_id);
        }
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/rules.php');
    break;

case 'rule_save':
    $rule_id = $Request->getInt('rule_id');
    $Rule = Shop\Rules\Zone::getInstance($rule_id);
    if ($Rule->getID() > 0) {
        if (isset($Request['region_del'])) {
            $Rule->del('region', $Request->getArray('region_del'));
        }
        if (isset($Request['country_del'])) {
            $Rule->del('country', $Request->getArray('country_del'));
        }
        if (isset($Request['state_del'])) {
            $Rule->del('state', $Request->getArray('state_del'));
        }
    }
    $Rule->Save($Request);
    echo COM_refresh(SHOP_ADMIN_URL . '/rules.php?zr_list');
    break;

case 'zr_edit':
    $content .= Shop\Menu::adminRules('zr_list');
    $content .= Shop\Rules\Zone::getInstance($actionval)->Edit();
    break;

case 'pr_edit':
    $actionval = (int)$actionval;
    $PC = new Shop\Rules\Product($actionval);
    $content .= Shop\Menu::adminRules('pr_list');
    $content .= $PC->Edit();
    break;

case 'pr_save':
    $PC = new Shop\Rules\Product($Request->getInt('pr_id'));
    if ($PC->Save($Request)) {
        COM_setMsg($LANG_SHOP['item_updated']);
    } else {
        COM_setMsg($LANG_SHOP['item_upd_err']);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/rules.php?pr_list');
    break;

case 'pr_del':
    if (isset($Request['delbutton_x']) && is_array($actionval)) {
        foreach ($actionval as $val) {
        Shop\Rules\Product::Delete((int)$val);
        }
    } elseif ($actionval > 0) {
        Shop\Rules\Product::Delete((int)$actionval);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/rules.php?pr_list');
    exit;
    break;

case 'zr_list':
    // Display the list of zone rules
    $content .= Shop\Menu::adminRules($action);
    $content .= Shop\Rules\Zone::adminList();
    break;

case 'pr_list':
default:
    $content .= Shop\Menu::adminRules($action);
    $content .= Shop\Rules\Product::adminList();
    break;
}

$display = COM_siteHeader();
$display .= \Shop\Menu::Admin($action);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;
exit;

