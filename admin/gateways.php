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
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check('shop.admin')
) {
    COM_404();
    exit;
}

require_once('../../auth.inc.php');
USES_lib_admin();
use Shop\Gateway;
use Shop\GatewayManager;
$Request = Shop\Models\Request::getInstance();
$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($Request['msg'])) $msg[] = $Request->getString('msg');

$action = 'gwadmin';     // Default if no correct view specified
$expected = array(
    // Actions to perform
    'gwmove', 'gwsave', 'gwinstall', 'gwdelete', 'gwupload', 'gwupgrade',
    // Views to display
    'gwadmin', 'gwedit',
);
foreach($expected as $provided) {
    if (isset($Request[$provided])) {
        $action = $provided;
        $actionval = $Request->getRaw($provided);
        break;
    }
}

switch ($action) {
case 'gwupgrade':
    $GW = Gateway::getInstance($actionval);
    if ($GW->doUpgrade()) {
        SHOP_setMsg($LANG_SHOP['upgrade_ok']);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwupload':
    $status = GatewayManager::upload();
    if ($status) {
        SHOP_setMsg("The gateway was successfully uploaded");
    } else {
        SHOP_setMsg("The gateway could not be uploaded");
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwinstall':
    $gwname = $Request->getString('gwname');
    $gw = Gateway::create($gwname);
    if ($gw !== NULL) {
        if ($gw->Install()) {
            $msg[] = "Gateway \"$gwname\" installed successfully";
        } else {
            $msg[] = "Failed to install the \"$gwname\" gateway";
        }
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwdelete':
    $gw = Gateway::getInstance($Request->getString('id'));
    if ($gw !== NULL) {
        $gw->ClearButtonCache();
    }
    $status = Gateway::Remove($Request->getString('id'));
    echo COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwsave':
    // Save a payment gateway configuration
    $gw = Gateway::getInstance($Request->getString('gw_id'));
    if ($gw !== NULL) {
        $status = $gw->saveConfig($Request);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwmove':
    Gateway::moveRow($Request->getString('id'), $actionval);
    echo COM_refresh(SHOP_ADMIN_URL . '/gateways.php');
    break;

case 'gwedit':
    $gw = Gateway::getInstance($Request->getString('gw_id'));
    if ($gw !== NULL) {
        $content .= $gw->Configure();
    }
    break;

case 'gwadmin':
default:
    $content .= GatewayManager::adminList();
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
