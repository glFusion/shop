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

$action = 'packages';     // Default if no correct view specified
$expected = array(
    // Actions to perform
    'pkgsave', 'pkgdelete',
    // Views to display
    'pkglist', 'pkgedit',
);
foreach($expected as $provided) {
    if (isset($Request[$provided])) {
        $action = $provided;
        $actionval = $Request->getString($provided);
        break;
    }
}

switch ($action) {
case 'pkgdelete':
    Shop\Package::Delete($actionval);
    echo COM_refresh(SHOP_ADMIN_URL . '/packages.php');
    break;

case 'pkgsave':
    // Save a payment gateway configuration
    $Pkg = \Shop\Package::getInstance($Request->getInt('pkg_id'));
    if ($Pkg !== NULL) {
        $status = $Pkg->Save($Request);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/packages.php');
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
