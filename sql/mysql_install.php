<?php
/**
 * Database creation and update statements for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_TABLES, $_SQL, $SHOP_UPGRADE, $_SHOP_SAMPLEDATA;
include_once __DIR__ . '/mysql_sample_data.php';

$SHOP_UPGRADE = array();

$_SQL = array(
'shop.ipnlog' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.ipnlog']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_addr` varchar(15) NOT NULL,
  `ts` int(11) unsigned DEFAULT NULL,
  `verified` tinyint(1) DEFAULT '0',
  `txn_id` varchar(255) DEFAULT NULL,
  `gateway` varchar(25) DEFAULT NULL,
  `event` varchar(40) DEFAULT 'payment',
  `ipn_data` text NOT NULL,
  `order_id` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipnlog_ts` (`ts`),
  KEY `ipnlog_txnid` (`txn_id`)
) ENGINE=MyISAM",

'shop.products' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.products']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `description` text,
  `keywords` varchar(255) DEFAULT '',
  `price` decimal(12,4) unsigned DEFAULT NULL,
  `prod_type` tinyint(2) DEFAULT '0',
  `file` varchar(255) DEFAULT NULL,
  `expiration` int(11) DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT '1',
  `featured` tinyint(1) unsigned DEFAULT '0',
  `dt_add` datetime NOT NULL,
  `views` int(4) unsigned DEFAULT '0',
  `comments_enabled` tinyint(1) DEFAULT '0',
  `rating_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `buttons` text,
  `rating` double(6,4) NOT NULL DEFAULT '0.0000',
  `votes` int(11) unsigned NOT NULL DEFAULT '0',
  `weight` decimal(9,4) DEFAULT '0.0000',
  `taxable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `shipping_type` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `shipping_amt` decimal(9,4) unsigned NOT NULL DEFAULT '0.0000',
  `shipping_units` decimal(9,4) unsigned NOT NULL DEFAULT '0.0000',
  `show_random` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `show_popular` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `options` text,
  `track_onhand` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `onhand` int(10) unsigned NOT NULL DEFAULT '0',
  `reorder` int(10) unsigned NOT NULL DEFAULT '0',
  `oversell` tinyint(1) NOT NULL DEFAULT '0',
  `qty_discounts` text,
  `custom` varchar(255) NOT NULL DEFAULT '',
  `avail_beg` date DEFAULT '1900-01-01',
  `avail_end` date DEFAULT '9999-12-31',
  `brand` varchar(255) NOT NULL DEFAULT '',
  `min_ord_qty` int(3) NOT NULL DEFAULT 1,
  `max_ord_qty` int(3) NOT NULL DEFAULT 0,
  `brand_id` int(11) unsigned NOT NULL DEFAULT 0,
  `supplier_id` int(11) unsigned NOT NULL DEFAULT 0,
  `supplier_ref` varchar(64) NOT NULL DEFAULT '',
  `lead_time` varchar(64) NOT NULL DEFAULT '',
  `def_pv_id` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `zone_rule` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `products_name` (`name`),
  KEY `products_price` (`price`),
  KEY `avail_beg` (`avail_beg`),
  KEY `avail_end` (`avail_end`)
) ENGINE=MyISAM",

'shop.orderitems' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.orderitems']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(40) NOT NULL,
  `product_id` varchar(128) NOT NULL,
  `variant_id` int(11) unsigned NOT NULL DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `txn_id` varchar(128) DEFAULT '',
  `txn_type` varchar(255) DEFAULT '',
  `expiration` int(11) unsigned NOT NULL DEFAULT '0',
  `base_price` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `price` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `qty_discount` decimal(5,2) NOT NULL DEFAULT '0.00',
  `net_price` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `taxable` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `token` varchar(40) NOT NULL DEFAULT '',
  `options` varchar(40) DEFAULT '',
  `options_text` text,
  `extras` text,
  `shipping` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `handling` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `tax` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `tax_rate` decimal(6,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `purchases_productid` (`product_id`),
  KEY `purchases_txnid` (`txn_id`)
) ENGINE=MyISAM",

'shop.images' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.images']} (
  `img_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `orderby` int(3) NOT NULL DEFAULT '999',
  `filename` varchar(255) DEFAULT NULL,
  `nonce` varchar(20) DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`img_id`),
  KEY `idxProd` (`product_id`,`img_id`)
) ENGINE=MyISAM",

'shop.categories' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.categories']} (
  `cat_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` smallint(5) unsigned DEFAULT '0',
  `cat_name` varchar(128) DEFAULT '',
  `description` text,
  `enabled` tinyint(1) unsigned DEFAULT '1',
  `grp_access` mediumint(8) unsigned NOT NULL DEFAULT '1',
  `image` varchar(255) DEFAULT '',
  `google_taxonomy` text,
  `lft` smallint(5) unsigned NOT NULL DEFAULT '0',
  `rgt` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`cat_id`),
  KEY `idxName` (`cat_name`,`cat_id`),
  KEY `cat_lft` (`lft`),
  KEY `cat_rgt` (`rgt`)
) ENGINE=MyISAM",

'shop.prod_opt_vals' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.prod_opt_vals']}` (
  `pov_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pog_id` int(11) unsigned NOT NULL DEFAULT '0',
  `item_id` int(11) unsigned DEFAULT NULL,
  `pov_value` varchar(64) DEFAULT NULL,
  `orderby` int(3) NOT NULL DEFAULT '0',
  `pov_price` decimal(9,4) DEFAULT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `sku` varchar(8) DEFAULT NULL,
  PRIMARY KEY (`pov_id`),
  UNIQUE KEY `pog_value` (`pog_id`,`pov_value`),
  UNIQUE `item_id` (`item_id`,`pog_id`,`pov_value`)
) ENGINE=MyISAM",

'shop.buttons' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.buttons']}` (
  `pi_name` varchar(20) NOT NULL DEFAULT 'shop',
  `item_id` varchar(40) NOT NULL,
  `gw_name` varchar(10) NOT NULL DEFAULT '',
  `btn_key` varchar(20) NOT NULL,
  `button` text,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pi_name`,`item_id`,`gw_name`,`btn_key`)
) ENGINE=MyISAM",

'shop.orders' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.orders']}` (
  `order_id` varchar(40) NOT NULL,
  `uid` int(11) unsigned NOT NULL DEFAULT 0,
  `order_date` int(11) unsigned NOT NULL DEFAULT 0,
  `last_mod` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `billto_id` int(11) unsigned NOT NULL DEFAULT 0,
  `billto_name` varchar(255) DEFAULT NULL,
  `billto_company` varchar(255) DEFAULT NULL,
  `billto_address1` varchar(255) DEFAULT NULL,
  `billto_address2` varchar(255) DEFAULT NULL,
  `billto_city` varchar(255) DEFAULT NULL,
  `billto_state` varchar(255) DEFAULT NULL,
  `billto_country` varchar(255) DEFAULT NULL,
  `billto_zip` varchar(40) DEFAULT NULL,
  `shipto_id` int(11) unsigned NOT NULL DEFAULT 0,
  `shipto_name` varchar(255) DEFAULT NULL,
  `shipto_company` varchar(255) DEFAULT NULL,
  `shipto_address1` varchar(255) DEFAULT NULL,
  `shipto_address2` varchar(255) DEFAULT NULL,
  `shipto_city` varchar(255) DEFAULT NULL,
  `shipto_state` varchar(255) DEFAULT NULL,
  `shipto_country` varchar(255) DEFAULT NULL,
  `shipto_zip` varchar(40) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `gross_items` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `net_nontax` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `net_taxable` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `order_total` decimal(12,4) unsigned DEFAULT 0.0000,
  `tax` decimal(9,4) unsigned DEFAULT NULL,
  `shipping` decimal(9,4) unsigned DEFAULT NULL,
  `handling` decimal(9,4) unsigned DEFAULT NULL,
  `by_gc` decimal(12,4) unsigned DEFAULT NULL,
  `status` varchar(25) DEFAULT 'pending',
  `pmt_method` varchar(20) DEFAULT NULL,
  `pmt_dscp` varchar(255) DEFAULT '',
  `pmt_txn_id` varchar(255) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `token` varchar(20) DEFAULT NULL,
  `tax_rate` decimal(7,5) NOT NULL DEFAULT 0.00000,
  `info` text DEFAULT NULL,
  `currency` varchar(5) NOT NULL DEFAULT 'USD',
  `order_seq` int(11) unsigned DEFAULT NULL,
  `shipper_id` int(3) DEFAULT -1,
  `discount_code` varchar(20) DEFAULT NULL,
  `discount_pct` decimal(4,2) DEFAULT 0.00,
  `tax_shipping` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `tax_handling` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `shipping_method` varchar(20) DEFAULT NULL,
  `shipping_dscp` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `order_seq` (`order_seq`),
  KEY `order_date` (`order_date`)
) ENGINE=MyISAM",

'shop.address' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.address']}` (
  `addr_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `zip` varchar(40) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `billto_def` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `shipto_def` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`addr_id`),
  KEY `uid` (`uid`,`zip`)
) ENGINE=MyISAM",

'shop.userinfo' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.userinfo']}` (
  `uid` int(11) unsigned NOT NULL,
  `cart` text,
  `pref_gw` varchar(12) NOT NULL DEFAULT '',
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM",

'shop.gateways' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.gateways']}` (
  `id` varchar(25) NOT NULL,
  `orderby` int(3) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `description` varchar(255) DEFAULT NULL,
  `config` text,
  `services` varchar(255) DEFAULT NULL,
  `grp_access` int(3) unsigned NOT NULL DEFAULT '2',
  PRIMARY KEY (`id`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM",

'shop.workflows' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.workflows']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `wf_name` varchar(40) DEFAULT NULL,
  `orderby` int(2) DEFAULT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `can_disable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `orderby` (`orderby`),
  KEY `key_name` (`wf_name`)
) ENGINE=MyISAM",

'shop.orderstatus' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.orderstatus']}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orderby` int(3) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `name` varchar(20) NOT NULL,
  `notify_buyer` tinyint(1) NOT NULL DEFAULT '1',
  `notify_admin` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM",

'shop.order_log' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.order_log']}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ts` int(11) unsigned DEFAULT NULL,
  `order_id` varchar(40) DEFAULT NULL,
  `username` varchar(60) NOT NULL DEFAULT '',
  `message` text,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM",

'shop.currency' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.currency']}` (
  `code` varchar(3) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `numeric_code` int(4) DEFAULT NULL,
  `symbol_placement` varchar(10) DEFAULT NULL,
  `symbol_spacer` varchar(2) DEFAULT ' ',
  `code_placement` varchar(10) DEFAULT 'after',
  `decimals` int(3) DEFAULT '2',
  `rounding_step` float(5,2) DEFAULT '0.00',
  `thousands_sep` varchar(2) DEFAULT ',',
  `decimal_sep` varchar(2) DEFAULT '.',
  `major_unit` varchar(20) DEFAULT NULL,
  `minor_unit` varchar(20) DEFAULT NULL,
  `conversion_rate` float(7,5) DEFAULT '1.00000',
  `conversion_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`)
) ENGINE=MyISAM",

'shop.coupons' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.coupons']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(128) NOT NULL,
  `amount` decimal(12,4) unsigned NOT NULL DEFAULT '0.0000',
  `balance` decimal(12,4) unsigned NOT NULL DEFAULT '0.0000',
  `buyer` int(11) unsigned NOT NULL DEFAULT '0',
  `redeemer` int(11) unsigned NOT NULL DEFAULT '0',
  `purchased` int(11) unsigned NOT NULL DEFAULT '0',
  `redeemed` int(11) unsigned NOT NULL DEFAULT '0',
  `expires` date DEFAULT '9999-12-31',
  `status` varchar(10) NOT NULL DEFAULT 'valid',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `owner` (`redeemer`,`balance`,`expires`),
  KEY `purchased` (`purchased`),
  KEY `key_expires` (`expires`)
) ENGINE=MyISAM",

'shop.coupon_log' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.coupon_log']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `code` varchar(128) NOT NULL,
  `ts` int(11) unsigned DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `amount` float(8,2) DEFAULT NULL,
  `msg` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `code` (`code`)
) ENGINE=MyISAM",

'shop.sales' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.sales']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40) DEFAULT NULL,
  `item_type` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `start` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `end` datetime NOT NULL DEFAULT '9999-12-31 23:59:59',
  `discount_type` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `amount` decimal(6,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_type` (`item_type`,`item_id`)
) ENGINE=MyISAM",

'shop.shipping' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.shipping']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `module_code` varchar(10) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `min_units` int(11) unsigned NOT NULL DEFAULT '0',
  `max_units` int(11) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `valid_from` int(11) unsigned NOT NULL DEFAULT '0',
  `valid_to` int(11) unsigned NOT NULL DEFAULT '2145902399',
  `use_fixed` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `rates` text,
  `grp_access` int(3) unsigned NOT NULL DEFAULT '2',
  `req_shipto` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `tax_loc` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `free_threshold` decimal(9,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM",

'shop.suppliers' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.suppliers']}` (
  `sup_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(127) DEFAULT NULL,
  `company` varchar(127) NOT NULL DEFAULT '',
  `address1` varchar(127) NOT NULL DEFAULT '',
  `address2` varchar(127) NOT NULL DEFAULT '',
  `city` varchar(127) NOT NULL DEFAULT '',
  `state` varchar(127) NOT NULL DEFAULT '',
  `country` varchar(127) NOT NULL DEFAULT '',
  `zip` varchar(40) NOT NULL DEFAULT '',
  `phone` varchar(40) NOT NULL DEFAULT '',
  `is_supplier` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `is_brand` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `dscp` text,
  `lead_time` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`sup_id`),
  KEY `is_supplier` (`is_supplier`,`name`),
  KEY `is_brand` (`is_brand`,`name`)
) ENGINE=MyISAM",

'shop.product_variants' => "CREATE TABLE {$_TABLES['shop.product_variants']} (
  `pv_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(11) unsigned NOT NULL DEFAULT 0,
  `sku` varchar(64) DEFAULT NULL DEFAULT 0,
  `price` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `weight` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `shipping_units` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `track_onhand` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `onhand` int(10) NOT NULL DEFAULT 0,
  `reorder` int(10) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `supplier_ref` varchar(64) NOT NULL DEFAULT '',
  `img_ids` text NOT NULL DEFAULT '',
  `dscp` text NOT NULL,
  `orderby` int(4) NOT NULL DEFAULT 9999,
  PRIMARY KEY (`pv_id`),
  KEY `prod_id` (`item_id`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM",

'shop.states' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.states']}` (
  `state_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` int(11) unsigned NOT NULL DEFAULT '0',
  `state_name` varchar(64) NOT NULL DEFAULT '',
  `iso_code` varchar(10) NOT NULL DEFAULT '',
  `state_enabled` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`state_id`),
  UNIQUE KEY `country_state` (`country_id`,`iso_code`),
  KEY `state_enabled` (`state_enabled`)
) ENGINE=MyISAM",
'shop.tax_rates' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.tax_rates']}` (
  `code` varchar(25) NOT NULL,
  `country` varchar(3) DEFAULT NULL,
  `state` varchar(10) DEFAULT NULL,
  `zip_from` varchar(10) DEFAULT NULL,
  `zip_to` varchar(10) DEFAULT NULL,
  `region` varchar(128) DEFAULT NULL,
  `combined_rate` float(7,5) NOT NULL DEFAULT '0.00000',
  `state_rate` float(7,5) NOT NULL DEFAULT '0.00000',
  `county_rate` float(7,5) NOT NULL DEFAULT '0.00000',
  `city_rate` float(7,5) NOT NULL DEFAULT '0.00000',
  `special_rate` float(7,5) NOT NULL DEFAULT '0.00000',
  PRIMARY KEY (`code`),
  KEY `country_zipcode` (`country`,`zip_from`),
  KEY `location` (`country`,`state`,`zip_from`),
  KEY `zip_from` (`zip_from`),
  KEY `zip_to` (`zip_to`)
) ENGINE=MyISAM",

'shop.cache' => "CREATE TABLE `{$_TABLES['shop.cache']}` (
  `cache_key` varchar(127) NOT NULL,
  `expires` int(11) unsigned NOT NULL DEFAULT '0',
  `data` mediumtext,
  `tags` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`cache_key`),
  KEY (`expires`)
) ENGINE=MyISAM",
);

$SHOP_UPGRADE['0.7.1'] = array(
    "RENAME TABLE {$_TABLES['shop.purchases']} TO {$_TABLES['shop.orderitems']}",
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `valid_from` int(11) unsigned NOT NULL DEFAULT '0' AFTER `enabled`",
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `valid_to` int(11) unsigned NOT NULL DEFAULT '2145902399' AFTER `valid_from`",
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `use_fixed` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `valid_to`",
    "ALTER TABLE {$_TABLES['shop.orderitems']} DROP `status`",
    "ALTER TABLE {$_TABLES['shop.ipnlog']} ADD order_id varchar(40)",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `shipper_id` int(3) UNSIGNED DEFAULT '0' AFTER `order_seq`",
);
$SHOP_UPGRADE['1.0.0'] = array(
    "CREATE TABLE `{$_TABLES['shop.prod_opt_grps']}` (
      `pog_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `pog_type` varchar(11) NOT NULL DEFAULT 'select',
      `pog_name` varchar(40) NOT NULL DEFAULT '',
      `pog_orderby` tinyint(2) DEFAULT '0',
      PRIMARY KEY (`pog_id`),
      UNIQUE KEY `pog_name` (`pog_name`),
      KEY `orderby` (`pog_orderby`,`pog_name`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.oi_opts']}` (
      `oio_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `oi_id` int(11) unsigned NOT NULL,
      `pog_id` int(11) unsigned NOT NULL DEFAULT '0',
      `pov_id` int(11) unsigned NOT NULL DEFAULT '0',
      `oio_name` varchar(40) DEFAULT NULL,
      `oio_value` varchar(40) DEFAULT NULL,
      `oio_price` decimal(9,4) NOT NULL DEFAULT '0.0000',
      PRIMARY KEY (`oio_id`),
      UNIQUE KEY `key1` (`oi_id`,`pog_id`,`pov_id`,`oio_name`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.shipments']}` (
      `shipment_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `order_id` varchar(40) DEFAULT NULL,
      `ts` int(11) unsigned DEFAULT NULL,
      `comment` text,
      `shipping_address` text,
      PRIMARY KEY (`shipment_id`),
      KEY `order_id` (`order_id`,`ts`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.shipment_items']}` (
      `si_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `shipment_id` int(11) unsigned NOT NULL DEFAULT '0',
      `orderitem_id` int(11) unsigned NOT NULL DEFAULT '0',
      `quantity` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`si_id`),
      KEY `shipment_id` (`shipment_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.shipment_packages']}` (
      `pkg_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `shipment_id` int(11) unsigned NOT NULL DEFAULT '0',
      `shipper_id` int(11) unsigned NOT NULL DEFAULT '0',
      `shipper_info` varchar(255) DEFAULT NULL,
      `tracking_num` varchar(80) DEFAULT NULL,
      PRIMARY KEY (`pkg_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE {$_TABLES['shop.carrier_config']} (
      `code` varchar(10) NOT NULL,
      `data` text,
      PRIMARY KEY (`code`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.cache']}` (
      `cache_key` varchar(127) NOT NULL,
      `expires` int(11) unsigned NOT NULL DEFAULT '0',
      `data` mediumtext,
      PRIMARY KEY (`cache_key`),
      KEY (`expires`)
    ) ENGINE=MyISAM",
    "ALTER TABLE {$_TABLES['shop.sales']} CHANGE `start` `start` datetime NOT NULL DEFAULT '1970-01-01 00:00:00'",
    "ALTER TABLE {$_TABLES['shop.address']} CHANGE id addr_id int(11) unsigned NOT NULL auto_increment",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `brand` varchar(255) NOT NULL DEFAULT ''",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `min_ord_qty` int(3) NOT NULL DEFAULT 1",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `max_ord_qty` int(3) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `grp_access` int(3) UNSIGNED NOT NULL default 2",
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `module_code` varchar(10) AFTER `id`",
    "ALTER TABLE {$_TABLES['shop.orderitems']} CHANGE  price price  decimal(9,4) NOT NULL default  0",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD base_price decimal(9,4) NOT NULL default 0 AFTER expiration",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD qty_discount decimal(5,2) NOT NULL default 0 AFTER price",
    "ALTER TABLE {$_TABLES['shop.categories']} ADD google_taxonomy text AFTER `image`",
    "ALTER TABLE {$_TABLES['shop.images']} ADD `nonce` varchar(20) DEFAULT NULL",
    "ALTER TABLE {$_TABLES['shop.images']} ADD `last_update` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    "RENAME TABLE {$_TABLES['shop.prod_attr']} TO {$_TABLES['shop.prod_opt_vals']}",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} CHANGE attr_id pov_id int(11) unsigned NOT NULL AUTO_INCREMENT",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} CHANGE attr_value pov_value varchar(64) DEFAULT NULL",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} CHANGE attr_price pov_price decimal(9,4) DEFAULT NULL",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD `pog_id` int(11) UNSIGNED NOT NULL AFTER `pov_id`",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD `sku` varchar(8) DEFAUlt NULL",
    "ALTER TABLE {$_TABLES['shop.coupons']} DROP PRIMARY KEY",
    "ALTER TABLE {$_TABLES['shop.coupons']} ADD UNIQUE KEY `code` (`code`)",
    "ALTER TABLE {$_TABLES['shop.coupons']} ADD `id` int(11) unsigned NOT NULL auto_increment PRIMARY KEY FIRST",
    "ALTER TABLE {$_TABLES['shop.coupons']} ADD `status` varchar(10) NOT NULL DEFAULT 'valid'",
    "ALTER TABLE {$_TABLES['shop.gateways']} ADD `grp_access` int(3) UNSIGNED NOT NULL default 2",
    "ALTER TABLE {$_TABLES['shop.images']} ADD `orderby` int(3) NOT NULL default 999 AFTER `product_id`",
);

$SHOP_UPGRADE['1.1.0'] = array(
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.tax_rates']}` (
      `code` varchar(25) NOT NULL,
      `country` varchar(3) DEFAULT NULL,
      `state` varchar(10) DEFAULT NULL,
      `zip_from` varchar(10) DEFAULT NULL,
      `zip_to` varchar(10) DEFAULT NULL,
      `region` varchar(40) DEFAULT NULL,
      `combined_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      `state_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      `county_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      `city_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      `special_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      PRIMARY KEY (`code`),
      KEY `country_zipcode` (`country`,`zip_from`),
      KEY `location` (`country`,`state`,`zip_from`),
      KEY `zip_from` (`zip_from`),
      KEY `zip_to` (`zip_to`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.discountcodes']}` (
      `code_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `code` varchar(80) NOT NULL DEFAULT '',
      `percent` decimal(4,2) unsigned NOT NULL DEFAULT '0.00',
      `start` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
      `end` datetime NOT NULL DEFAULT '9999-12-31 23:59:59',
      `min_order` decimal(9,4) unsigned NOT NULL DEFAULT '0.0000',
      PRIMARY KEY (`code_id`),
      UNIQUE KEY `code` (`code`),
      KEY `bydate` (`start`,`end`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.prodXcat']}` (
      `product_id` int(11) unsigned NOT NULL,
      `cat_id` int(11) unsigned NOT NULL,
      PRIMARY KEY (`product_id`,`cat_id`),
      KEY `cat_id` (`cat_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.product_variants']}` (
      `pv_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `item_id` int(11) unsigned NOT NULL,
      `sku` varchar(64) DEFAULT NULL,
      `price` decimal(9,4) NOT NULL DEFAULT '0.0000',
      `weight` decimal(12,4) NOT NULL DEFAULT '0.0000',
      `shipping_units` decimal(9,4) NOT NULL DEFAULT '0.0000',
      `onhand` int(10) NOT NULL DEFAULT '0',
      `reorder` int(10) NOT NULL DEFAULT '0',
      `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
      PRIMARY KEY (`pv_id`),
      KEY `prod_id` (`item_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.variantXopt']}` (
      `pv_id` int(11) unsigned NOT NULL DEFAULT '0',
      `pov_id` int(11) unsigned NOT NULL DEFAULT '0',
      PRIMARY KEY (`pv_id`,`pov_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.suppliers']}` (
      `sup_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(127) DEFAULT NULL,
      `company` varchar(127) NOT NULL DEFAULT '',
      `address1` varchar(127) NOT NULL DEFAULT '',
      `address2` varchar(127) NOT NULL DEFAULT '',
      `city` varchar(127) NOT NULL DEFAULT '',
      `state` varchar(127) NOT NULL DEFAULT '',
      `country` varchar(127) NOT NULL DEFAULT '',
      `zip` varchar(40) NOT NULL DEFAULT '',
      `phone` varchar(40) NOT NULL DEFAULT '',
      `is_supplier` tinyint(1) unsigned NOT NULL DEFAULT '1',
      `is_brand` tinyint(1) unsigned NOT NULL DEFAULT '0',
      `dscp` text,
      PRIMARY KEY (`sup_id`),
      KEY `is_supplier` (`is_supplier`,`name`),
      KEY `is_brand` (`is_brand`,`name`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.regions']}` (
      `region_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `region_code` int(4) unsigned NOT NULL DEFAULT '0',
      `region_name` varchar(64) NOT NULL DEFAULT '',
      `region_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
      PRIMARY KEY (`region_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.countries']}` (
      `country_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `region_id` int(11) unsigned NOT NULL DEFAULT '0',
      `country_code` int(4) unsigned NOT NULL DEFAULT '0',
      `currency_code` varchar(4) NOT NULL DEFAULT '',
      `alpha2` varchar(2) NOT NULL DEFAULT '',
      `alpha3` varchar(3) NOT NULL DEFAULT '',
      `country_name` varchar(127) NOT NULL DEFAULT '',
      `dial_code` int(4) unsigned NOT NULL DEFAULT '0',
      `country_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
      PRIMARY KEY (`country_id`),
      UNIQUE KEY `alpha2` (`alpha2`),
      KEY `zone_id` (`region_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.states']}` (
      `state_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `country_id` int(11) unsigned NOT NULL DEFAULT '0',
      `state_name` varchar(64) NOT NULL DEFAULT '',
      `iso_code` varchar(10) NOT NULL DEFAULT '',
      `state_enabled` tinyint(1) unsigned NOT NULL DEFAULT '0',
      PRIMARY KEY (`state_id`)
    ) ENGINE=MyISAM",
    "ALTER TABLE {$_TABLES['shop.address']} ADD phone varchar(20) AFTER zip",
    "ALTER TABLE {$_TABLES['shop.userinfo']} ADD `pref_gw` varchar(12) NOT NULL DEFAULT ''",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD dc_price decimal(9,4) NOT NULL DEFAULT 0 after qty_discount",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD `variant_id` int(11) unsigned NOT NULL DEFAULT '0' AFTER product_id",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD `net_price` decimal(9,4) NOT NULL DEFAULT '0.0000' AFTER qty_discount",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD `tax_rate` decimal(6,4) NOT NULL DEFAULT  '0.0000' AFTER `tax`",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `gross_items` decimal(12,4) NOT NULL DEFAULT '0.0000' AFTER buyer_email",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `net_nontax` decimal(12,4) NOT NULL DEFAULT '0.0000' AFTER gross_items",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `net_taxable` decimal(12,4) NOT NULL DEFAULT '0.0000' AFTER net_nontax",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `order_total` decimal(12,4) unsigned DEFAULT '0.0000' AFTER net_taxable",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `discount_code` varchar(20) DEFAULT NULL AFTER shipper_id",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `discount_pct` decimal(4,2) DEFAULT '0.00' AFTER discount_code",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY `item_id`",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP `item_id`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `reorder` int(10) unsigned NOT NULL DEFAULT 0 after `onhand`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `brand_id` int(11) NOT NULL DEFAULT 0 AFTER max_ord_qty",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `supplier_id` int(11) NOT NULL DEFAULT 0 AFTER brand_id",
    // Note: Removal of the products `brand` field happens in upgrade.php after brand_id is populated
    $_SHOP_SAMPLEDATA['shop.regions'],       // these may need to change if data changes
    $_SHOP_SAMPLEDATA['shop.countries'],
    $_SHOP_SAMPLEDATA['shop.states'],
    "INSERT IGNORE INTO {$_TABLES['shop.currency']} VALUES
        ('AMD','֏','Armenian Dram',51,'after','','hidden',2,0.00,',','.','Dram','Luma',1.00000,'2020-01-14 20:55:42'),
        ('KHR','៛','Cambidian Riel',116,'after','','hidden',0,0.00,',','.','Riel','Sen',1.00000,'2020-01-14 21:35:05'),
        ('SVC','₡','Colón',222,'after','','hidden',2,0.00,',','.','Colón','Centavo',1.00000,'2020-01-14 21:35:42'),
        ('GEL','₾','Georgian Lari',981,'after','','hidden',2,0.00,',','.','Lari','Tetri',1.00000,'2020-01-14 21:35:55'),
        ('GGP','£','Guernsey Pound',0,'after','','hidden',2,0.00,',','.','Pound','Penny',1.00000,'2020-01-14 21:31:50'),
        ('IQD','ﺩ.ﻉ','Iraqi Dinar',368,'after','','hidden',4,0.00,',','.','Dinar','Fils',1.00000,'2020-01-14 21:38:15'),
        ('KPW','₩','North Korean Won',408,'after','','hidden',2,0.00,',','.','Won','Chon',1.00000,'2020-01-14 21:35:29'),
        ('MKD','ден','Macenoaian Denar',807,'after','','hidden',2,0.00,',','.','Denar','Deni',1.00000,'2020-01-14 21:31:50'),
        ('MGA','Ar','Madagascar Ariary',969,'after','','hidden',2,0.20,',','.','Ariary','Iaimbilanja',1.00000,'2020-01-14 21:39:25'),
        ('MWK','K','Malawian Kwacha',454,'after','','hidden',2,0.00,',','.','Kwacha','Tambaia',1.00000,'2020-01-14 21:40:06'),
        ('OMR','R.O.','Omani Rial',512,'after','','hidden',2,0.00,',','.','Rial','Baisa',1.00000,'2020-01-14 21:40:25'),
        ('MVR','Rf','Maldivian Rufiyaa',462,'after','','hidden',2,0.00,',','.','Rufiyaa','Laari',1.00000,'2020-01-14 21:48:21'),
        ('MTL','₤','Maltese Lira',470,'after','','hidden',2,0.00,',','.','Lira','Cent',1.00000,'2020-01-14 21:47:43'),
        ('RWF','FRw','Rwandan franc',646,'after','','hidden',2,0.00,',','.','Franc','Centime',1.00000,'2020-01-14 21:49:47'),
        ('SDG','ﺝ.ﺱ','Sudanese pound',646,'after','','hidden',2,0.00,',','.','Pound','Qirsh',1.00000,'2020-01-14 21:50:55'),
        ('TJS','SM','Tajikistani somoni',972,'after','','hidden',2,0.00,',','.','Somoni','Diram',1.00000,'2020-01-14 21:52:24'),
        ('UZS','s\'om','Uzbekistani Soʻm',860,'after','','hidden',0,0.00,',','.','S\'om','',1.00000,'2020-01-14 21:54:47'),
        ('ZWB','$','Zimbabwean Bonds',0,'before','','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2020-01-14 21:58:16'),
        ('TMT','T','Turkmenistan Manat',934,'after','','hidden',2,0.00,',','.','Manat','Teňňe',1.00000,'2020-01-14 22:00:46'),
        ('ALL','ALL','Albanian Lek',8,'after','','hidden',2,0.00,',','.','Lek','Qindarka',1.00000,'2020-01-14 22:02:28')",
);
$SHOP_UPGRADE['1.2.0'] = array(
    "CREATE TABLE `{$_TABLES['shop.features']}` (
      `ft_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `ft_name` varchar(40) NOT NULL DEFAULT '',
      `orderby` int(5) NOT NULL DEFAULT '9999',
      PRIMARY KEY (`ft_id`),
      KEY `feat_name` (`ft_name`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.features_values']}` (
      `fv_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `ft_id` int(11) unsigned NOT NULL DEFAULT '0',
      `fv_value` varchar(40) NOT NULL DEFAULT '',
      PRIMARY KEY (`fv_id`),
      UNIQUE KEY `id_txt` (`ft_id`,`fv_value`),
      KEY `feat_id` (`ft_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.prodXfeat']}` (
      `prod_id` int(11) unsigned NOT NULL DEFAULT '0',
      `ft_id` int(11) unsigned NOT NULL DEFAULT '0',
      `fv_id` int(11) unsigned NOT NULL DEFAULT '0',
      `fv_text` varchar(40) DEFAULT NULL,
      PRIMARY KEY (`prod_id`,`ft_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.zone_rules']}` (
      `rule_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
      `rule_name` varchar(64) NOT NULL DEFAULT '',
      `rule_dscp` text,
      `allow` tinyint(1) unsigned NOT NULL DEFAULT '0',
      `regions` text,
      `countries` text,
      `states` text,
      PRIMARY KEY (`rule_id`)
    ) ENGINE=MyISAM",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `supplier_ref` varchar(64) NOT NULL DEFAULT '' AFTER `supplier_id`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `lead_time` varchar(64) NOT NULL DEFAULT '' AFTER `supplier_ref`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `def_pv_id` tinyint(1) unsigned NOT NULL DEFAULT '0'",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `zone_rule` int(11) unsigned NOT NULL DEFAULT '0'",
    "ALTER TABLE {$_TABLES['shop.suppliers']} ADD `lead_time` varchar(64) NOT NULL DEFAULT '' AFTER `dscp`",
    "ALTER TABLE {$_TABLES['shop.product_variants']} ADD `supplier_ref` varchar(64) NOT NULL DEFAULT '' AFTER `enabled`",
    "ALTER TABLE {$_TABLES['shop.product_variants']} ADD `img_ids` text NOT NULL DEFAULT '' AFTER `supplier_ref`",
    "ALTER TABLE {$_TABLES['shop.product_variants']} ADD `dscp` text NOT NULL DEFAULT '' AFTER `img_ids`",
    "ALTER TABLE  {$_TABLES['shop.states']} ADD UNIQUE KEY `country_state` (`country_id`, `iso_code`)",
    "ALTER TABLE  {$_TABLES['shop.states']} ADD KEY `state_enabled` (`state_enabled`)",
);
$SHOP_UPGRADE['1.3.0'] = array(
    "CREATE TABLE {$_TABLES['shop.packages']} (
      `pkg_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `units` float DEFAULT NULL,
      `max_weight` float DEFAULT NULL,
      `width` float DEFAULT NULL,
      `height` float DEFAULT NULL,
      `length` float DEFAULT NULL,
      `dscp` varchar(255) DEFAULT NULL,
      `containers` text DEFAULT NULL,
      PRIMARY KEY (`pkg_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.payments']}` (
      `pmt_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `pmt_order_id` varchar(40) DEFAULT NULL,
      `pmt_ts` int(11) unsigned DEFAULT NULL,
      `is_money` tinyint(1) unsigned NOT NULL DEFAULT 1,
      `pmt_gateway` varchar(12) DEFAULT NULL,
      `pmt_amount` decimal(12,4) DEFAULT NULL,
      `pmt_ref_id` varchar(255) DEFAULT NULL,
      `pmt_method` varchar(32) DEFAULT NULL,
      `pmt_comment` text,
      `pmt_uid` int(11) unsigned NOT NULL DEFAULT '0',
      PRIMARY KEY (`pmt_id`),
      KEY `order_id` (`pmt_order_id`)
    ) ENGINE=MyISAM",
    $_SHOP_SAMPLEDATA['shop.packages'],
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `tax_shipping` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `discount_pct`",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `tax_handling` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `tax_shipping`",
    "ALTER TABLE {$_TABLES['shop.orders']} CHANGE `shipper_id` `shipper_id` int(3) DEFAULT -1",
    "ALTER TABLE {$_TABLES['shop.states']} ADD `tax_shipping` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `state_enabled`",
    "ALTER TABLE {$_TABLES['shop.states']} ADD `tax_handling` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `tax_shipping`",
    "ALTER TABLE {$_TABLES['shop.orderstatus']} ADD UNIQUE KEY (`name`)",
    "ALTER TABLE {$_TABLES['shop.orderstatus']} CHANGE orderby orderby int(3) NOT NULL DEFAULT 999",
    "INSERT IGNORE INTO {$_TABLES['shop.orderstatus']}
        (`name`, `notify_buyer`, `notify_admin`)
        VALUES ('invoiced', 0, 0)",
    "ALTER TABLE {$_TABLES['shop.coupons']} ADD KEY `key_expires` (expires)",
    "UPDATE {$_TABLES['shop.orders']} SET status='processing' WHERE status='paid'",
    "DELETE FROM {$_TABLES['shop.orderstatus']} WHERE name = 'paid'",
    "ALTER TABLE {$_TABLES['shop.ipnlog']} ADD `event` varchar(40) DEFAULT 'payment' after `gateway`",
    "UPDATE {$_TABLES['shop.products']} SET avail_end = '9999-12-31' WHERE avail_end = '0000-00-00'",
    "ALTER TABLE {$_TABLES['shop.workflows']} ADD KEY `key_name` (`wf_name`)",
    "ALTER TABLE {$_TABLES['shop.orderitems']} DROP `dc_pricd`",
    "ALTER TABLE {$_TABLES['shop.shipping']}
        ADD `req_shipto` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `grp_access`",
    "ALTER TABLE {$_TABLES['shop.shipping']}
        ADD `tax_loc` tinyint(1) unsigned NOT NULL DEFAULT '0'",
    "ALTER TABLE {$_TABLES['shop.tax_rates']}
        CHANGE region region varchar(128)",
    "ALTER TABLE {$_TABLES['shop.orders']}
        ADD shipping_method varchar(20) DEFAULT NULL AFTER tax_handling",
    "ALTER TABLE {$_TABLES['shop.orders']}
        ADD shipping_dscp varchar(20) DEFAULT NULL AFTER shipping_method",
    "ALTER TABLE {$_TABLES['product_variants']}
        ADD `track_onhand` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER shipping_units",
    "ALTER TABLE {$_TABLES['shop.shipping']}
        ADD free_threshold decimal(9,4) NOT NULL DEFAULT 0 AFTER tax_loc",
    "ALTER TABLE {$_TABLES['shop.cache']}
        ADD tags varchar(255) NOT NULL DEFAULT '' AFTER `data`",
    "ALTER TABLE {$_TABLES['shop.product_variants']}
        ADD orderby int(4) NOT NULL DEFAULT 9999 AFTER dscp",
    "ALTER TABLE {$_TABLES['shop.product_variants']}
        ADD KEY `orderby` (`orderby`)",
    "ALTER TABLE {$_TABLES['shop.product_variants']}
        CHANGE `img_ids` `img_ids` text NOT NULL DEFAULT '',
        CHANGE `item_id` `item_id` int(11) unsigned NOT NULL DEFAULT 0",

    // Sync order totals
    "UPDATE {$_TABLES['shop.orders']} SET order_total = net_nontax + net_taxable + tax + shipping + handling",
);

// These tables were added as part of upgrades and can reference the upgrade
// until the schema changes.
$_SQL['shop.prod_opt_grps'] = $SHOP_UPGRADE['1.0.0'][0];
$_SQL['shop.oi_opts'] = $SHOP_UPGRADE['1.0.0'][1];
$_SQL['shop.shipments'] = $SHOP_UPGRADE['1.0.0'][2];
$_SQL['shop.shipment_items'] = $SHOP_UPGRADE['1.0.0'][3];
$_SQL['shop.shipment_packages'] = $SHOP_UPGRADE['1.0.0'][4];
$_SQL['shop.carrier_config'] = $SHOP_UPGRADE['1.0.0'][5];
$_SQL['shop.tax_rates'] = $SHOP_UPGRADE['1.1.0'][0];
$_SQL['shop.discountcodes'] = $SHOP_UPGRADE['1.1.0'][1];
$_SQL['shop.prodXcat'] = $SHOP_UPGRADE['1.1.0'][2];
$_SQL['shop.variantXopt'] = $SHOP_UPGRADE['1.1.0'][4];
$_SQL['shop.regions'] = $SHOP_UPGRADE['1.1.0'][6];
$_SQL['shop.countries'] = $SHOP_UPGRADE['1.1.0'][7];
$_SQL['shop.features'] = $SHOP_UPGRADE['1.2.0'][0];
$_SQL['shop.features_values'] = $SHOP_UPGRADE['1.2.0'][1];
$_SQL['shop.prodXfeat'] = $SHOP_UPGRADE['1.2.0'][2];
$_SQL['shop.zone_rules'] = $SHOP_UPGRADE['1.2.0'][3];
$_SQL['shop.payments'] = $SHOP_UPGRADE['1.3.0'][1];
$_SQL['shop.packages'] = $SHOP_UPGRADE['1.3.0'][0];

?>
