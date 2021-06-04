<?php
/**
 * Show affiliate sales lists.
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

/** Require core glFusion code */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check() ||
    !Shop\Config::get('aff_enabled')
) {
    COM_404();
    exit;
}

if (COM_isAnonUser()) {
    // Anon users can't apply gift cards to their account.
    echo \Shop\Menu::siteHeader();
    echo SEC_loginForm();
    echo \Shop\Menu::siteFooter();
    exit;
}
$content = '';

// Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
COM_setArgNames(array('mode', 'id', 'register'));
foreach (array('mode', 'id') as $varname) {
    if (isset($_GET[$varname])) {
        $$varname = COM_applyFilter($_GET[$varname]);
    } else {
        $$varname = COM_getArgument($varname);
    }
}
if (empty($mode)) {
    $mode = 'redeem';
}

switch ($mode) {
case 'register':
    if (COM_isAnonUser()) {
        COM_404();  // todo, redirect to login?
    }
    $Aff = new Shop\Affiliate();
    if ($Aff->isEligible()) {
        $content .= $Aff->getRegistrationForm();
    } else {
        // Registration only open to all users, or customers
        // who have actually placed orders
        COM_404();
    }
    break;

case 'sales':
default:
    $content .= Shop\Affiliate::userList();
    break;
}

$display = \Shop\Menu::siteHeader($LANG_SHOP['aff_sales']);
$display .= \Shop\Menu::PageTitle($LANG_SHOP['aff_sales']);
$display .= \Shop\Menu::User('affiliate');
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;
