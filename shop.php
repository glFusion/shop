<?php
/**
 * Global configuration items for the Shop plugin.
 * These are either static items, such as the plugin name and table
 * definitions, or are items that don't lend themselves well to the
 * glFusion configuration system, such as allowed file types.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_table_prefix, $_TABLES;

Shop\Config::set('pi_version', '1.4.1.10');
Shop\Config::set('gl_version', '2.0.0');

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
$_TABLES['shop.payments'] = $_SHOP_table_prefix . 'payments';
$_TABLES['shop.tax_rates'] = $_SHOP_table_prefix . 'tax_rates';
$_TABLES['shop.discountcodes'] = $_SHOP_table_prefix . 'discountcodes';
$_TABLES['shop.prodXcat'] = $_SHOP_table_prefix . 'prodXcat';
$_TABLES['shop.product_variants'] = $_SHOP_table_prefix . 'product_variants';
$_TABLES['shop.variantXopt'] = $_SHOP_table_prefix . 'variantXopt';
$_TABLES['shop.suppliers'] = $_SHOP_table_prefix . 'suppliers';
$_TABLES['shop.regions'] = $_SHOP_table_prefix . 'regions';
$_TABLES['shop.countries'] = $_SHOP_table_prefix . 'countries';
$_TABLES['shop.states'] = $_SHOP_table_prefix . 'states';
$_TABLES['shop.features'] = $_SHOP_table_prefix . 'features';
$_TABLES['shop.features_values'] = $_SHOP_table_prefix . 'features_values';
$_TABLES['shop.prodXfeat'] = $_SHOP_table_prefix . 'prodXfeat';
$_TABLES['shop.zone_rules'] = $_SHOP_table_prefix . 'zone_rules';
$_TABLES['shop.packages'] = $_SHOP_table_prefix . 'packages';
$_TABLES['shop.customerXgateway'] = $_SHOP_table_prefix . 'customerXgateway';
$_TABLES['shop.affiliate_sales'] = $_SHOP_table_prefix . 'affiliate_sales';
$_TABLES['shop.affiliate_saleitems'] = $_SHOP_table_prefix . 'affiliate_saleitems';
$_TABLES['shop.affiliate_payments'] = $_SHOP_table_prefix . 'affiliate_payments';
$_TABLES['shop.stock'] = $_SHOP_table_prefix . 'stock';
$_TABLES['shop.plugin_products'] = $_SHOP_table_prefix . 'plugin_products';
$_TABLES['shop.product_rules'] = $_SHOP_table_prefix . 'product_rules';
$_TABLES['shop.prodXcbox'] = $_SHOP_table_prefix . 'prodXcbox';
$_TABLES['shop.invoices'] = $_SHOP_table_prefix . 'invoices';

// Deprecate eventually
$_TABLES['shop.prod_attr']    = $_SHOP_table_prefix . 'product_attributes';
$_TABLES['shop.purchases']    = $_SHOP_table_prefix . 'purchases';
$_TABLES['shop.cache'] = $_SHOP_table_prefix . 'cache';

