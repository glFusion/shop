<?php
/**
 * Product detail display for the Shop plugin.
 * This page's only job is to display the product detail.  This is to help
 * with SEO and uses rewritten urls.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
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
    !function_exists('SHOP_access_check') ||    // first ensure plugin is installed
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

$Request = Shop\Models\Request::getInstance()
    ->withArgNames(array('id', 'oi_id', 'query'));
$id = $Request->getString('item_id');
if (!empty($id)) {
    // Force reading the item by it's record ID regardless of the use_sku setting.
    // Set $_SHOP_CONF['use_sku'] to false to get the Product class to use the ID.
    Shop\Config::set('use_sku', false);
} else {
    $id = $Request->getString('id');
}

$oi_id = $Request->getInt('oi_id');
$query = $Request->getString('query');

$content = '';
$breadcrumbs = '';
if (!empty($id)) {
    $P = \Shop\Product::getInstance($id);
    if ($P->verifyID($id) && $P->hasAccess()) {
        $breadcrumbs = $P->Breadcrumbs();
        $P->setQuery($query);
        $P->setOrderItem($oi_id);
        $content .= $P->Detail();
    }
}
if (empty($content)) {
    SHOP_setMsg($LANG_SHOP['item_not_found']);
    echo COM_refresh(SHOP_URL);
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
$display = \Shop\Menu::siteHeader($P->getShortDscp());
$display .= \Shop\Menu::pageTitle();
if (!empty($msg)) {
    //msg block
    $display .= COM_startBlock('','','blockheader-message.thtml');
    $display .= $msg;
    $display .= COM_endBlock('blockfooter-message.thtml');
}
$display .= $breadcrumbs;
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;

?>
