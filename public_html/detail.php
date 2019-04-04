<?php
/**
 * Product detail display for the PayPal plugin.
 * This page's only job is to display the product detail.  This is to help
 * with SEO and uses rewritten urls.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2011 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     0.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Require core glFusion code */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !isset($_SHOP_CONF) ||
    !in_array($_SHOP_CONF['pi_name'], $_PLUGINS) ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

COM_setArgNames(array('id'));
if (isset($_GET['id'])) {
    $id = COM_sanitizeID($_GET['id']);
} else {
    $id = COM_applyFilter(COM_getArgument('id'));
}

$display = \Shop\Menu::siteHeader();
$T = SHOP_getTemplate('shop_title', 'title');
$display .= $T->parse('', 'title');
if (!empty($msg)) {
    //msg block
    $display .= COM_startBlock('','','blockheader-message.thtml');
    $display .= $msg;
    $display .= COM_endBlock('blockfooter-message.thtml');
}

$content = '';
$breadcrumbs = '';
if (!empty($id)) {
    $P = \Shop\Product::getInstance($id);
    if ($P->id == $id && $P->hasAccess()) {
        $breadcrumbs = \Shop\Category::Breadcrumbs($P->cat_id);
        $content .= $P->Detail();
    }
}
if (empty($content)) {
    COM_setMsg($LANG_SHOP['item_not_found']);
    COM_refresh(SHOP_getUrl());
}
if (empty($breadcrumbs)) {
    // Hack to change the link text depending on the return URL
    $url = SHOP_getUrl('xxx');
    if ($url == 'xxx') {
        $url = SHOP_URL;
        $text = $LANG_SHOP['back_to_catalog'];
    } else {
        // use the url obtained from SHOP_getUrl()
        $text = $LANG_SHOP['go_back'];
    }
    $breadcrumbs = COM_createLink($text, $url);
}
SHOP_setUrl();
$display .= $breadcrumbs;
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;

?>
