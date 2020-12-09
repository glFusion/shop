<?php
/**
 * Admin page for managing Shop package definitions.
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

$action = 'packages';     // Default if no correct view specified
$expected = array(
    // Actions to perform
    'pkgsave', 'pkgdelete',
    // Views to display
    'pkglist', 'pkgedit',
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
case 'pkgdelete':
    Shop\Package::Delete($actionval);
    COM_refresh(SHOP_ADMIN_URL . '/packages.php');
    break;

case 'pkgsave':
    // Save a payment gateway configuration
    $Pkg = \Shop\Package::getInstance($_POST['pkg_id']);
    if ($Pkg !== NULL) {
        $status = $Pkg->Save($_POST);
    }
    COM_refresh(SHOP_ADMIN_URL . '/packages.php');
    break;

case 'pkgedit':
    $Pkg = \Shop\Package::getInstance((int)$actionval);
    if ($Pkg !== NULL) {
        $content .= $Pkg->Edit();
    }
    break;

case 'packages':
default:
    $content .= Shop\Package::adminList();
    break;
}

$display = COM_siteHeader();
$display .= \Shop\Menu::Admin('shipping');
$display .= \Shop\Menu::adminShipping('packages');
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;
exit;

?>
