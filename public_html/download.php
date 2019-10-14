<?php
/**
* Download page for files purchased using the shop plugin.
* No other files will be accessable via this script.
* Based on the PayPal Plugin for Geeklog CMS by Vincent Furia.
*
* @author       Lee Garner <lee@leegarner.com>
* @author       Vincent Furia <vinny01 AT users DOT sourceforge DOT net>
* @copyright    Copyright (c) 2009-2019 Lee Garner
* @copyright    Copyright (c) 2005-2006 Vincent Furia
* @package      shop
* @version      v1.0.0
* @since        v0.7.0
* @license      http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
* @filesource
*/

/** Import core glFusion libraries */
require_once('../lib-common.php');

// Sanitize the product ID and token
$id = SHOP_getVar($_GET, 'id', 'int');
$token = SHOP_getVar($_GET, 'token');

// Need to have one or the other, prefer token
if (empty($token) && $id == 0) {
    COM_404();
    exit;
}

// Get product by token
// Also check for a product ID
$id = SHOP_getVar($_REQUEST, 'id', 'int');
if ($id > 0) {
    $id_sql = "item.product_id = '$id' AND ";
} else {
    $id_sql = '';
}
$sql = "SELECT prod.id, prod.file, prod.prod_type
        FROM {$_TABLES['shop.orderitems']} AS item
        LEFT JOIN {$_TABLES['shop.products']} AS prod
            ON prod.id = item.product_id
        WHERE $id_sql item.token = '$token'
        AND (item.expiration = 0 OR item.expiration > '" . SHOP_now()->toUnix() . "')";
//echo $sql;die;
$res = DB_query($sql);
$A = DB_fetchArray($res, false);
//  If a file was found, do the download.
//  Otherwise refresh to the home page and log it.
if (is_array($A) && !empty($A['file'])) {
    $filespec = $_SHOP_CONF['download_path'] . '/' . $A['file'];
    $DL = new Shop\UploadDownload();
    $DL->setAllowAnyMimeType(true);
    //$DL->setAllowedMimeTypes();
    $logfile = $_SHOP_CONF['logfile'];
    if (!file_exists($logfile)) {
        $fp = fopen($logfile, "w+");
        if (!$fp) {
            SHOP_log("Failed to create $logfile", SHOP_LOG_ERROR);
        } else {
            fwrite($fp, "**** Created Logfile ***\n");
        }
    }
    if (file_exists($logfile)) {
        $DL->setLogFile($_CONF['path'] . 'logs/error.log');
        $DL->setLogging(true);
    } else {
        $DL->setLogginf(false);
    }
    //$DL->setAllowedExtensions($_SHOP_CONF['allowedextensions']);
    $DL->setPath($_SHOP_CONF['download_path']);
    $DL->downloadFile($A['file']);

    // Check for errors
    if ($DL->areErrors()) {
        $errs = $DL->printErrors(false);
        SHOP_log("SHOP-DWNLD: {$_USER['username']} tried to download " .
            "the file with id {$id} but for some reason could not",
            SHOP_LOG_ERROR
        );
        SHOP_log("SHOP-DWNLD: $errs", SHOP_LOG_ERROR);
        echo COM_refresh($_CONF['site_url']);
    }

    $DL->_logItem('Download Success',
            "{$_USER['username']} successfully downloaded "
            . "the file with id {$id}.");
} else {
    SHOP_log("SHOP-DWNLD: {$_USER['username']}/{$_USER['uid']} " .
            "tried to download the file with id {$id} " .
            "but this is not a downloadable file", SHOP_LOG_ERROR);
    echo COM_refresh($_CONF['site_url']. '/index.php?msg=07&plugin=shop');
}

?>
