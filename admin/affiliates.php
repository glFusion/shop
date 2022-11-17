<?php
/**
 * Admin page for managing Affiliates
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2022 Lee Garner
 * @package     shop
 * @version     v1.5.0
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
use Shop\Affiliate;
use Shop\Models\AffiliatePayment;
$Request = Shop\Models\Request::getInstance();
$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($Request['msg'])) $msg[] = $Request->getString('msg');

$action = 'affiliates';     // Default if no correct view specified
$expected = array(
    // Actions to perform
    'do_payout', 'reject', 'approve',
    // Views to display
    'affiliates', 'payout',
);
list($action, $actionval) = $Request->getAction($expected);
switch ($action) {
case 'approve':
    if (isset($Request['aff_uid'])) {
        Affiliate::Restore($Request->get('aff_uid'));
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/affiliates.php');
    break;

case 'reject':
    if (isset($Request['aff_uid'])) {
        Affiliate::Reject($Request->get('aff_uid'));
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/affiliates.php');
    break;

case 'do_payout':
    AffiliatePayment::generate($Request->getInt('aff_uid'));
    AffiliatePayment::process();
    echo COM_refresh(SHOP_ADMIN_URL . '/affiliates.php');
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
    $uid = $Request->getInt('uid');
    if ($uid > 0) {
        $content .= Shop\Affiliate::userList($uid);
    } else {
        $content .= Shop\Affiliate::adminList();
    }
    break;
}

$display = \Shop\Menu::siteHeader($LANG_SHOP['affiliates']);
$display .= \Shop\Menu::Admin('affiliates');
$display .= \Shop\Menu::adminAffiliates($action);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;
exit;

