<?php
/**
 * Handle redeeming gift codes
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
    !isset($_SHOP_CONF) ||
    !in_array($_SHOP_CONF['pi_name'], $_PLUGINS)
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
COM_setArgNames(array('mode', 'id'));
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
case 'redeem':
default:
    // Submit a gift code for redemption
    $C = \Shop\Currency::getInstance();
    $T = SHOP_getTemplate('apply_gc', 'tpl');
    if (empty($_SHOP_CONF['gc_mask'])) {
        $maxlen = (int)$_SHOP_CONF['gc_length'];
    } else {
        $maxlen = strlen($_SHOP_CONF['gc_mask']);
    }
    $T->set_var(array(
        'gc_bal' => $C->format(\Shop\Coupon::getUserBalance($_USER['uid'])),
        'code' => $id,
        'maxlen' => $maxlen,
    ) );
    $content .= $T->finish($T->parse('output', 'tpl'));
    break;
}

USES_shop_functions();
$display = \Shop\siteHeader();
$display .= \Shop\Menu::PageTitle($LANG_SHOP['apply_gc']);
$display .= \Shop\Menu::User();
$T = new \Template(SHOP_PI_PATH . '/templates');
$T->set_file('title', 'shop_title.thtml');
$T->set_var(array(
    'title' => isset($page_title) ? $page_title : '',
    'is_admin' => plugin_ismoderator_shop(),
) );
$display .= $T->parse('', 'title');
$display .= $content;
$display .= \Shop\siteFooter();
echo $display;

?>
