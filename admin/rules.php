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

$action = 'rules';     // Default if no correct view specified
$expected = array(
    // Actions to perform
    'rule_del', 'rule_add', 'rule_save', 'delbutton_x',
    // Views to display
    'rules', 'rule_edit',
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

switch ($action) {
case 'rule_add':
    $rule_id = SHOP_getVar($_POST, 'rule_id', 'integer', 0);
    if ($actionval > 0) {
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
    COM_refresh(SHOP_ADMIN_URL . '/rules.php');
    break;

case 'rule_del':
    if ($actionval) {
        Shop\Rules\Zone::deleteRule($actionval);
    }
    COM_refresh(SHOP_ADMIN_URL . '/rules.php');
    break;

case 'delbutton_x':
    if (is_array($_POST['delitem'])) {
        // Delete some checked options
        foreach ($_POST['delitem'] as $opt_id) {
            Shop\Rules\Zone::deleteRule($opt_id);
        }
    }
    COM_refresh(SHOP_ADMIN_URL . '/rules.php');
    break;

case 'rule_save':
    $rule_id = SHOP_getVar($_POST, 'rule_id', 'integer', 0);
    $Rule = Shop\Rules\Zone::getInstance($rule_id);
    if ($Rule->getID() > 0) {
        if (isset($_POST['region_del'])) {
            $Rule->del('region', $_POST['region_del']);
        }
        if (isset($_POST['country_del'])) {
            $Rule->del('country', $_POST['country_del']);
        }
        if (isset($_POST['state_del'])) {
            $Rule->del('state', $_POST['state_del']);
        }
    }
    $Rule->Save($_POST);
    COM_refresh(SHOP_ADMIN_URL . '/rules.php');
    break;

case 'rule_edit':
    $content .= Shop\Menu::adminRegions('rules');
    $content .= Shop\Rules\Zone::getInstance($actionval)->Edit();
    break;

case 'rules':
default:
    // Display the list of zone rules
    $content .= Shop\Menu::adminRegions($action);
    $content .= Shop\Rules\Zone::adminList();
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

?>
