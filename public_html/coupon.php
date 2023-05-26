<?php
/**
 * Handle redeeming gift codes.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Require core glFusion code */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check()
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

$Request = Shop\Models\Request::getInstance();
$Request->withArgNames(array('mode', 'id'));

switch ($Request->getString('mode', 'redeem')) {
case 'redeem':
default:
    // Submit a gift code for redemption
    $C = \Shop\Currency::getInstance();
    $T = new Shop\Template;
    $T->set_file('tpl', 'apply_gc.thtml');
    if (empty($_SHOP_CONF['gc_mask'])) {
        $maxlen = (int)$_SHOP_CONF['gc_length'];
    } else {
        $maxlen = strlen($_SHOP_CONF['gc_mask']);
    }
    $T->set_var(array(
        'gc_bal' => $C->Format(\Shop\Products\Coupon::getUserBalance($_USER['uid'])),
        'code' => $Request->getString('id'),
        'maxlen' => $maxlen,
    ) );
    $content .= $T->finish($T->parse('output', 'tpl'));
    break;
}

$display = \Shop\Menu::siteHeader();
$display .= \Shop\Menu::PageTitle($LANG_SHOP['apply_gc']);
$display .= \Shop\Menu::User();
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;

?>
