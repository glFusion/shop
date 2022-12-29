<?php
/**
* Download page for files purchased using the shop plugin.
* No other files will be accessable via this script.
* Based on the PayPal Plugin for Geeklog CMS by Vincent Furia.
*
* @author       Lee Garner <lee@leegarner.com>
* @author       Vincent Furia <vinny01 AT users DOT sourceforge DOT net>
* @copyright    Copyright (c) 2009-2022 Lee Garner
* @copyright    Copyright (c) 2005-2006 Vincent Furia
* @package      shop
* @version      v1.5.0
* @since        v0.7.0
* @license      http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
* @filesource
*/

/** Import core glFusion libraries */
require_once('../lib-common.php');
use glFusion\Database\Database;
use Shop\Log;
use Shop\Config;
use Shop\Models\Request;

// Make sure the plugin is available
if (!function_exists('SHOP_access_check') || !Config::get('shop_enabled')) {
    COM_404();
    exit;
}

// Sanitize the product ID and token
$Request = Request::getInstance();
$id = $Request->getInt('id');
$token = $Request->getString('token');

// Need to have one or the other, prefer token
if (
    (empty($token) && $id == 0) ||
    !SHOP_isMinVersion()
) {
    COM_404();
    exit;
}

// Get product by token
// Also check for a product ID
$db = Database::getInstance();
$qb = $db->conn->createQueryBuilder();
$qb->select('prod.id', 'prod.file', 'prod.prod_type')
   ->from($_TABLES['shop.orderitems'], 'item')
   ->leftjoin('item', $_TABLES['shop.products'], 'prod', 'prod.id = item.product_id')
   ->where('item.token = :token')
   ->andWhere('(item.expiration = 0 OR item.expiration > :timestamp)')
   ->setParameter('token', $token, Database::STRING)
   ->setParameter('timestamp', time(), Database::INTEGER);
if ($id > 0) {
    $qb->andWhere('item.product_id = :item_id')
       ->setParameter('item_id', $id, Database::INTEGER);
}
try {
    $A = $qb->execute()->fetchAssociative();
} catch (\Exception $e) {
    Log::system(Log::ERROR, __FILE__.'/'.__LINE__. ': ' . $e->getMessage());
    $A = false;
}
//  If a file was found, do the download.
//  Otherwise refresh to the home page and log it.
if (is_array($A) && !empty($A['file'])) {
    $filespec = $_SHOP_CONF['download_path'] . '/' . $A['file'];
    $DL = new Shop\UploadDownload();
    $DL->setAllowAnyMimeType(true);
    $DL->setPath($_SHOP_CONF['download_path']);
    $DL->downloadFile($A['file']);

    // Check for errors
    if ($DL->areErrors()) {
        $errs = $DL->printErrors(false);
        Log::error(
            "{$_USER['username']} tried to download " .
            "the file with id {$id} but for some reason could not."
        );
        Log::error("SHOP-DWNLD: $errs");
        echo COM_refresh($_CONF['site_url']);
    } else {
        Log::error(
            "{$_USER['username']} successfully downloaded "
            . "the file with id {$id}."
        );
    }
} else {
    Log::error(
        "{$_USER['username']}/{$_USER['uid']} " .
        "tried to download the file with id {$id} " .
        "but this is not a downloadable file."
    );
    echo COM_refresh($_CONF['site_url']. '/index.php?msg=07&plugin=shop');
}
