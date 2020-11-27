<?php
/**
 * Admin page for managing Shop payment gateways.
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

$action = 'gwadmin';     // Default if no correct view specified
$expected = array(
    // Actions to perform
    'gwmove', 'gwsave', 'gwinstall', 'gwdelete',
    // Views to display
    'gwadmin', 'gwedit',
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
case 'gwinstall':
    $gwname = $_GET['gwname'];
    $class = 'Shop\\Gateways\\' . $gwname;
    $gw = new $class;
    if ($gw !== NULL) {
        if ($gw->Install()) {
            $msg[] = "Gateway \"$gwname\" installed successfully";
        } else {
            $msg[] = "Failed to install the \"$gwname\" gateway";
        }
    }
    COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwdelete':
    $gw = \Shop\Gateway::getInstance($_GET['id']);
    if ($gw !== NULL) {
        $status = $gw->Remove();
    }
    COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwsave':
    // Save a payment gateway configuration
    $gw = \Shop\Gateway::getInstance($_POST['gw_id']);
    if ($gw !== NULL) {
        $status = $gw->SaveConfig($_POST);
    }
    COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwmove':
    \Shop\Gateway::moveRow($_GET['id'], $actionval);
    COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwedit':
    $gw = \Shop\Gateway::getInstance($_GET['gw_id']);
    if ($gw !== NULL) {
        $content .= $gw->Configure();
    }
    break;

case 'gwadmin':
default:
    $content .= Shop\Gateway::adminList();
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
