<?php
/**
 * Admin page for managing Affiliates
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner
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
use Shop\Models\AffiliatePayment;

$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($_REQUEST['msg'])) $msg[] = $_REQUEST['msg'];

$action = 'affiliates';     // Default if no correct view specified
$expected = array(
    // Actions to perform
    'do_payout', 
    // Views to display
    'affiliates', 'payout',
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
case 'do_payout':
    AffiliatePayment::generate($_POST['aff_uid']);
    AffiliatePayment::process();
    COM_refresh(SHOP_ADMIN_URL . '/affiliates.php');
    break;

case 'payout':
    if (isset($_REQUEST['method'])) {
        $method = $_REQUEST['method'];
    } else {
        $method = 'all';
    }
    $content .= Shop\Affiliate::adminList($method);
    break;

case 'affiliates':
default:
    $content .= Shop\Affiliate::adminList();
    break;
}

$display = COM_siteHeader();
$display .= \Shop\Menu::Admin('affiliates');
$display .= \Shop\Menu::adminAffiliates($action);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;
exit;

?>
