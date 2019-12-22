<?php
/**
 * Global configuration items for the Shop plugin.
 * These are either static items, such as the plugin name and table
 * definitions, or are items that don't lend themselves well to the
 * glFusion configuration system, such as allowed file types.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_table_prefix, $_TABLES;
global $_SHOP_CONF;

$_SHOP_CONF['pi_name']            = 'shop';
$_SHOP_CONF['pi_display_name']    = 'Shop';
$_SHOP_CONF['pi_version']         = '1.1.0';
$_SHOP_CONF['gl_version']         = '1.7.0';
$_SHOP_CONF['pi_url']             = 'http://www.glfusion.org';

$_SHOP_table_prefix = $_DB_table_prefix . 'shop_';

$_TABLES['shop.ipnlog']       = $_SHOP_table_prefix . 'ipnlog';
$_TABLES['shop.products']     = $_SHOP_table_prefix . 'products';
$_TABLES['shop.downloads']    = $_SHOP_table_prefix . 'downloads';
$_TABLES['shop.orderitems']   = $_SHOP_table_prefix . 'orderitems';
$_TABLES['shop.images']       = $_SHOP_table_prefix . 'images';
$_TABLES['shop.categories']   = $_SHOP_table_prefix . 'categories';
$_TABLES['shop.prod_opt_vals'] = $_SHOP_table_prefix . 'product_option_values';
$_TABLES['shop.address']      = $_SHOP_table_prefix . 'address';
$_TABLES['shop.orders']       = $_SHOP_table_prefix . 'orders';
$_TABLES['shop.userinfo']     = $_SHOP_table_prefix . 'userinfo';
$_TABLES['shop.buttons']      = $_SHOP_table_prefix . 'buttons';
$_TABLES['shop.gateways']     = $_SHOP_table_prefix . 'gateways';
$_TABLES['shop.workflows']    = $_SHOP_table_prefix . 'workflows';
$_TABLES['shop.orderstatus']  = $_SHOP_table_prefix . 'order_status';
$_TABLES['shop.order_log']    = $_SHOP_table_prefix . 'order_log';
$_TABLES['shop.currency']     = $_SHOP_table_prefix . 'currency';
$_TABLES['shop.coupons']      = $_SHOP_table_prefix . 'coupons';
$_TABLES['shop.coupon_log']   = $_SHOP_table_prefix . 'coupon_log';
$_TABLES['shop.sales']        = $_SHOP_table_prefix . 'sales';
$_TABLES['shop.shipping']     = $_SHOP_table_prefix . 'shipping';
$_TABLES['shop.prod_opt_grps'] = $_SHOP_table_prefix . 'product_option_groups';
$_TABLES['shop.oi_opts']      = $_SHOP_table_prefix . 'orderitem_options';
$_TABLES['shop.shipments']    = $_SHOP_table_prefix . 'shipments';
$_TABLES['shop.shipment_items']   = $_SHOP_table_prefix . 'shipment_items';
$_TABLES['shop.shipment_packages']   = $_SHOP_table_prefix . 'shipment_packages';
$_TABLES['shop.carrier_config'] = $_SHOP_table_prefix . 'carrier_config';
$_TABLES['shop.cache'] = $_SHOP_table_prefix . 'cache';
$_TABLES['shop.tax_rates'] = $_SHOP_table_prefix . 'tax_rates';
$_TABLES['shop.groups'] = $_SHOP_table_prefix . 'groups';

// Deprecate eventually
$_TABLES['shop.prod_attr']    = $_SHOP_table_prefix . 'product_attributes';

// Other relatively static values;
$_SHOP_CONF['logfile'] = "{$_CONF['path']}/logs/{$_SHOP_CONF['pi_name']}_downloads.log";
$_SHOP_CONF['tmpdir'] = "{$_CONF['path']}/data/{$_SHOP_CONF['pi_name']}/";
$_SHOP_CONF['download_path'] = "{$_SHOP_CONF['tmpdir']}files/";
$_SHOP_CONF['image_dir']  = "{$_SHOP_CONF['tmpdir']}images/products";
$_SHOP_CONF['catimgpath']  = "{$_SHOP_CONF['tmpdir']}images/categories";
$_SHOP_CONF['order_tn_size'] = 65;

/**
 * Allowed extensions for downloads.
 * Make sure that every downloadable file extension is included in this list.
 * For security you may want to remove unused file extensions.  Also try
 * to avoid php and phps.
 * NOTE: extensions must be defined in `$_CONF['path']/system/classes/downloader.class.php`
 * to be listed here.
 */
$_SHOP_CONF['allowedextensions'] = array (
    'tgz'  => 'application/x-gzip-compressed',
    'gz'   => 'application/x-gzip-compressed',
    'zip'  => 'application/x-zip-compresseed',
    'tar'  => 'application/x-tar',
    'php'  => 'text/plain',
    'phps' => 'text/plain',
    'txt'  => 'text/plain',
    'html' => 'text/html',
    'htm'  => 'text/html',
    'bmp'  => 'image/bmp',
    'ico'  => 'image/bmp',
    'gif'  => 'image/gif',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/x-png',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'pdf'  => 'application/pdf',
    'swf'  => 'application/x-shockwave-flash',
    'doc'  => 'application/msword',
    'xls'  => 'application/vnd.ms-excel',
    'exe'  => 'application/octet-stream'
);

/**
 * Indicate which buttons will be checked by default for new products.
 */
$_SHOP_CONF['buttons'] = array(
    'buy_now'   => 1,
    'donation'  => 0,
);

?>
