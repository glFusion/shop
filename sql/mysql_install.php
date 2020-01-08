<?php
/**
 * Database creation and update statements for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_TABLES, $_SQL, $SHOP_UPGRADE, $_SHOP_SAMPLEDATA;
$SHOP_UPGRADE = array();

$_SQL = array(
'shop.ipnlog' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.ipnlog']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_addr` varchar(15) NOT NULL,
  `ts` int(11) unsigned DEFAULT NULL,
  `verified` tinyint(1) DEFAULT '0',
  `txn_id` varchar(255) DEFAULT NULL,
  `gateway` varchar(25) DEFAULT NULL,
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
  `brand_id` int(11) unsigned NOT NULL DFAULT 0,
  `supplier_id` int(11) unsigned NOT NULL DFAULT 0,
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
  `tax_rate` decimal(6,4) NOT NULL DEFAULT  '0.0000',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `purchases_productid` (`product_id`),
  KEY `purchases_txnid` (`txn_id`)
) ENGINE=MyISAM",

'shop.images' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.images']} (
  `img_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `orderby` int(3) NOT NULL DEFAULT 999,
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
  UNIQUE KEY `pog_value` (`pog_id`, `pov_value`)
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
  `uid` int(11) NOT NULL DEFAULT '0',
  `order_date` int(11) unsigned NOT NULL DEFAULT '0',
  `last_mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `billto_id` int(11) unsigned NOT NULL DEFAULT '0',
  `billto_name` varchar(255) DEFAULT NULL,
  `billto_company` varchar(255) DEFAULT NULL,
  `billto_address1` varchar(255) DEFAULT NULL,
  `billto_address2` varchar(255) DEFAULT NULL,
  `billto_city` varchar(255) DEFAULT NULL,
  `billto_state` varchar(255) DEFAULT NULL,
  `billto_country` varchar(255) DEFAULT NULL,
  `billto_zip` varchar(40) DEFAULT NULL,
  `shipto_id` int(11) unsigned NOT NULL DEFAULT '0',
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
  `gross_items` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `net_nontax` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `net_taxable` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `order_total` decimal(12,4) unsigned DEFAULT '0.0000',
  `tax` decimal(9,4) unsigned DEFAULT NULL,
  `shipping` decimal(9,4) unsigned DEFAULT NULL,
  `handling` decimal(9,4) unsigned DEFAULT NULL,
  `by_gc` decimal(12,4) unsigned DEFAULT NULL,
  `status` varchar(25) DEFAULT 'pending',
  `pmt_method` varchar(20) DEFAULT NULL,
  `pmt_txn_id` varchar(255) DEFAULT NULL,
  `instructions` text,
  `token` varchar(20) DEFAULT NULL,
  `tax_rate` decimal(7,5) NOT NULL DEFAULT '0.00000',
  `info` text,
  `currency` varchar(5) NOT NULL DEFAULT 'USD',
  `order_seq` int(11) unsigned DEFAULT NULL,
  `shipper_id` int(3) unsigned DEFAULT '0',
  `discount_code` varchar(20) DEFAULT NULL,
  `discount_pct` decimal(4,2) DEFAULT '0.00',
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
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM",

'shop.orderstatus' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.orderstatus']}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orderby` int(3) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `name` varchar(20) NOT NULL,
  `notify_buyer` tinyint(1) NOT NULL DEFAULT '1',
  `notify_admin` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
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
  KEY `purchased` (`purchased`)
) ENGINE=MyIsam",

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
) ENGINE=MyIsam",

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
) ENGINE=MyIsam",

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
  PRIMARY KEY (`id`)
) ENGINE=MyIsam",
);

// Sample data to load up the Shop gateway configuration
$_SHOP_SAMPLEDATA = array(
    "INSERT INTO {$_TABLES['shop.categories']}
            (cat_id, parent_id, cat_name, description, grp_access, lft, rgt)
        VALUES
            (1, 0, 'Home', 'Root Category', 2, 1, 2)",
    "INSERT INTO {$_TABLES['shop.workflows']}
            (id, wf_name, orderby, enabled, can_disable)
        VALUES
            (1, 'viewcart', 10, 3, 0),
            (2, 'billto', 20, 0, 1),
            (3, 'shipto', 30, 2, 1)",
    "INSERT INTO {$_TABLES['shop.orderstatus']}
            (id, orderby, enabled, name, notify_buyer, notify_admin)
        VALUES
            (1, 10, 1, 'pending', 0, 0),
            (2, 20, 1, 'paid', 1, 1),
            (3, 30, 1, 'processing', 1, 0),
            (4, 40, 1, 'shipped', 1, 0),
            (5, 50, 1, 'closed', 0, 0),
            (6, 60, 1, 'refunded', 0, 0)",
    "INSERT INTO `{$_TABLES['shop.currency']}` VALUES
        ('AED','?.?','United Arab Emirates Dirham',784,'hidden',' ','before',2,0.00,',','.','Dirham','Fils',1.00000,'2014-01-03 20:51:17'),
    ('AFN','Af','Afghan Afghani',971,'hidden',' ','after',0,0.00,',','.','Afghani','Pul',1.00000,'2014-01-03 20:54:44'),
	('ANG','NAf.','Netherlands Antillean Guilder',532,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('AOA','Kz','Angolan Kwanza',973,'hidden',' ','after',2,0.00,',','.','Kwanza','Cêntimo',1.00000,'2014-01-03 20:54:44'),
	('ARM','m\$n','Argentine Peso Moneda Nacional',NULL,'hidden',' ','after',2,0.00,',','.','Peso','Centavos',1.00000,'2014-01-03 20:54:44'),
	('ARS','AR$','Argentine Peso',32,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('AUD','$','Australian Dollar',36,'before',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('AWG','Afl.','Aruban Florin',533,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('AZN','man.','Azerbaijanian Manat',NULL,'hidden',' ','after',2,0.00,',','.','New Manat','Q?pik',1.00000,'2014-01-03 20:54:44'),
	('BAM','KM','Bosnia-Herzegovina Convertible Mark',977,'hidden',' ','after',2,0.00,',','.','Convertible Marka','Fening',1.00000,'2014-01-03 20:54:44'),
	('BBD','Bds$','Barbadian Dollar',52,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BDT','Tk','Bangladeshi Taka',50,'hidden',' ','after',2,0.00,',','.','Taka','Paisa',1.00000,'2014-01-03 20:54:44'),
	('BGN','??','Bulgarian lev',975,'after',' ','hidden',2,0.00,',',',','Lev','Stotinka',1.00000,'2014-01-03 20:49:55'),
	('BHD','BD','Bahraini Dinar',48,'hidden',' ','after',3,0.00,',','.','Dinar','Fils',1.00000,'2014-01-03 20:54:44'),
	('BIF','FBu','Burundian Franc',108,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('BMD','BD$','Bermudan Dollar',60,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BND','BN$','Brunei Dollar',96,'hidden',' ','after',2,0.00,',','.','Dollar','Sen',1.00000,'2014-01-03 20:54:44'),
	('BOB','Bs','Bolivian Boliviano',68,'hidden',' ','after',2,0.00,',','.','Bolivianos','Centavo',1.00000,'2014-01-03 20:54:44'),
	('BRL','R$','Brazilian Real',986,'before',' ','hidden',2,0.00,'.',',','Reais','Centavo',1.00000,'2014-01-03 20:49:55'),
	('BSD','BS$','Bahamian Dollar',44,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BTN','Nu.','Bhutanese Ngultrum',64,'hidden',' ','after',2,0.00,',','.','Ngultrum','Chetrum',1.00000,'2014-01-03 20:54:44'),
	('BWP','BWP','Botswanan Pula',72,'hidden',' ','after',2,0.00,',','.','Pulas','Thebe',1.00000,'2014-01-03 20:54:44'),
	('BYR','???.','Belarusian ruble',974,'after',' ','hidden',0,0.00,',','.','Ruble',NULL,1.00000,'2014-01-03 20:49:48'),
	('BZD','BZ$','Belize Dollar',84,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('CAD','CA$','Canadian Dollar',124,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('CDF','CDF','Congolese Franc',976,'hidden',' ','after',2,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('CHF','Fr.','Swiss Franc',756,'hidden',' ','after',2,0.05,',','.','Franc','Rappen',1.00000,'2014-01-03 20:54:44'),
	('CLP','CL$','Chilean Peso',152,'hidden',' ','after',0,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CNY','¥','Chinese Yuan Renminbi',156,'before',' ','hidden',2,0.00,',','.','Yuan','Fen',1.00000,'2014-01-03 20:49:55'),
	('COP','$','Colombian Peso',170,'before',' ','hidden',0,0.00,'.',',','Peso','Centavo',1.00000,'2014-01-03 20:49:48'),
	('CRC','¢','Costa Rican Colón',188,'hidden',' ','after',0,0.00,',','.','Colón','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('CUC','CUC$','Cuban Convertible Peso',NULL,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CUP','CU$','Cuban Peso',192,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CVE','CV$','Cape Verdean Escudo',132,'hidden',' ','after',2,0.00,',','.','Escudo','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CZK','K?','Czech Republic Koruna',203,'after',' ','hidden',2,0.00,',',',','Koruna','Halé?',1.00000,'2014-01-03 20:49:55'),
	('DJF','Fdj','Djiboutian Franc',262,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('DKK','kr.','Danish Krone',208,'after',' ','hidden',2,0.00,',',',','Kroner','Øre',1.00000,'2014-01-03 20:49:55'),
	('DOP','RD$','Dominican Peso',214,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('DZD','DA','Algerian Dinar',12,'hidden',' ','after',2,0.00,',','.','Dinar','Santeem',1.00000,'2014-01-03 20:54:44'),
	('EEK','Ekr','Estonian Kroon',233,'hidden',' ','after',2,0.00,',',',','Krooni','Sent',1.00000,'2014-01-03 20:54:44'),
	('EGP','EG£','Egyptian Pound',818,'hidden',' ','after',2,0.00,',','.','Pound','Piastr',1.00000,'2014-01-03 20:54:44'),
	('ERN','Nfk','Eritrean Nakfa',232,'hidden',' ','after',2,0.00,',','.','Nakfa','Cent',1.00000,'2014-01-03 20:54:44'),
	('ETB','Br','Ethiopian Birr',230,'hidden',' ','after',2,0.00,',','.','Birr','Santim',1.00000,'2014-01-03 20:54:44'),
	('EUR','€','Euro',978,'after',' ','hidden',2,0.00,',',',','Euro','Cent',1.00000,'2014-01-03 20:49:55'),
	('FJD','FJ$','Fijian Dollar',242,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('FKP','FK£','Falkland Islands Pound',238,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('GBP','£','British Pound Sterling',826,'before',' ','hidden',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:49:55'),
	('GHS','GH?','Ghanaian Cedi',NULL,'hidden',' ','after',2,0.00,',','.','Cedi','Pesewa',1.00000,'2014-01-03 20:54:44'),
	('GIP','GI£','Gibraltar Pound',292,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('GMD','GMD','Gambian Dalasi',270,'hidden',' ','after',2,0.00,',','.','Dalasis','Butut',1.00000,'2014-01-03 20:54:44'),
	('GNF','FG','Guinean Franc',324,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('GTQ','GTQ','Guatemalan Quetzal',320,'hidden',' ','after',2,0.00,',','.','Quetzales','Centavo',1.00000,'2014-01-03 20:54:44'),
	('GYD','GY$','Guyanaese Dollar',328,'hidden',' ','after',0,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('HKD','HK$','Hong Kong Dollar',344,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('HNL','HNL','Honduran Lempira',340,'hidden',' ','after',2,0.00,',','.','Lempiras','Centavo',1.00000,'2014-01-03 20:54:44'),
	('HRK','kn','Croatian Kuna',191,'hidden',' ','after',2,0.00,',','.','Kuna','Lipa',1.00000,'2014-01-03 20:54:44'),
	('HTG','HTG','Haitian Gourde',332,'hidden',' ','after',2,0.00,',','.','Gourde','Centime',1.00000,'2014-01-03 20:54:44'),
	('HUF','Ft','Hungarian Forint',348,'after',' ','hidden',0,0.00,',',',','Forint',NULL,1.00000,'2014-01-03 20:49:48'),
	('IDR','Rp','Indonesian Rupiah',360,'hidden',' ','after',0,0.00,',','.','Rupiahs','Sen',1.00000,'2014-01-03 20:54:44'),
	('ILS','?','Israeli New Shekel',376,'before',' ','hidden',2,0.00,',','.','New Shekels','Agora',1.00000,'2014-01-03 20:49:55'),
	('INR','Rs','Indian Rupee',356,'hidden',' ','after',2,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('IRR','?','Iranian Rial',364,'after',' ','hidden',2,0.00,',','.','Toman','Rial',1.00000,'2014-01-03 20:49:55'),
	('ISK','Ikr','Icelandic Króna',352,'hidden',' ','after',0,0.00,',','.','Kronur','Eyrir',1.00000,'2014-01-03 20:54:44'),
	('JMD','J$','Jamaican Dollar',388,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('JOD','JD','Jordanian Dinar',400,'hidden',' ','after',3,0.00,',','.','Dinar','Piastr',1.00000,'2014-01-03 20:54:44'),
	('JPY','¥','Japanese Yen',392,'before',' ','hidden',0,0.00,',','.','Yen','Sen',1.00000,'2014-01-03 20:49:48'),
	('KES','Ksh','Kenyan Shilling',404,'hidden',' ','after',2,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('KGS','???','Kyrgyzstani Som',417,'after',' ','hidden',2,0.00,',','.','Som','Tyiyn',1.00000,'2014-01-03 20:49:55'),
	('KMF','CF','Comorian Franc',174,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('KRW','?','South Korean Won',410,'hidden',' ','after',0,0.00,',','.','Won','Jeon',1.00000,'2014-01-03 20:54:44'),
	('KWD','KD','Kuwaiti Dinar',414,'hidden',' ','after',3,0.00,',','.','Dinar','Fils',1.00000,'2014-01-03 20:54:44'),
	('KYD','KY$','Cayman Islands Dollar',136,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('KZT','??.','Kazakhstani tenge',398,'after',' ','hidden',2,0.00,',',',','Tenge','Tiyn',1.00000,'2014-01-03 20:49:55'),
	('LAK','?N','Laotian Kip',418,'hidden',' ','after',0,0.00,',','.','Kips','Att',1.00000,'2014-01-03 20:54:44'),
	('LBP','LB£','Lebanese Pound',422,'hidden',' ','after',0,0.00,',','.','Pound','Piastre',1.00000,'2014-01-03 20:54:44'),
	('LKR','SLRs','Sri Lanka Rupee',144,'hidden',' ','after',2,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('LRD','L$','Liberian Dollar',430,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('LSL','LSL','Lesotho Loti',426,'hidden',' ','after',2,0.00,',','.','Loti','Sente',1.00000,'2014-01-03 20:54:44'),
	('LTL','Lt','Lithuanian Litas',440,'hidden',' ','after',2,0.00,',','.','Litai','Centas',1.00000,'2014-01-03 20:54:44'),
	('LVL','Ls','Latvian Lats',428,'hidden',' ','after',2,0.00,',','.','Lati','Santims',1.00000,'2014-01-03 20:54:44'),
	('LYD','LD','Libyan Dinar',434,'hidden',' ','after',3,0.00,',','.','Dinar','Dirham',1.00000,'2014-01-03 20:54:44'),
	('MAD',' Dhs','Moroccan Dirham',504,'after',' ','hidden',2,0.00,',','.','Dirhams','Santimat',1.00000,'2014-01-03 20:49:55'),
	('MDL','MDL','Moldovan leu',498,'after',' ','hidden',2,0.00,',','.','Lei','bani',1.00000,'2014-01-03 20:49:55'),
	('MMK','MMK','Myanma Kyat',104,'hidden',' ','after',0,0.00,',','.','Kyat','Pya',1.00000,'2014-01-03 20:54:44'),
	('MNT','?','Mongolian Tugrik',496,'hidden',' ','after',0,0.00,',','.','Tugriks','Möngö',1.00000,'2014-01-03 20:54:44'),
	('MOP','MOP$','Macanese Pataca',446,'hidden',' ','after',2,0.00,',','.','Pataca','Avo',1.00000,'2014-01-03 20:54:44'),
	('MRO','UM','Mauritanian Ouguiya',478,'hidden',' ','after',0,0.00,',','.','Ouguiya','Khoums',1.00000,'2014-01-03 20:54:44'),
	('MTP','MT£','Maltese Pound',NULL,'hidden',' ','after',2,0.00,',','.','Pound','Shilling',1.00000,'2014-01-03 20:54:44'),
	('MUR','MURs','Mauritian Rupee',480,'hidden',' ','after',0,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('MXN','$','Mexican Peso',484,'before',' ','hidden',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:49:55'),
	('MYR','RM','Malaysian Ringgit',458,'before',' ','hidden',2,0.00,',','.','Ringgits','Sen',1.00000,'2014-01-03 20:49:55'),
	('MZN','MTn','Mozambican Metical',NULL,'hidden',' ','after',2,0.00,',','.','Metical','Centavo',1.00000,'2014-01-03 20:54:44'),
	('NAD','N$','Namibian Dollar',516,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('NGN','?','Nigerian Naira',566,'hidden',' ','after',2,0.00,',','.','Naira','Kobo',1.00000,'2014-01-03 20:54:44'),
	('NIO','C$','Nicaraguan Cordoba Oro',558,'hidden',' ','after',2,0.00,',','.','Cordoba','Centavo',1.00000,'2014-01-03 20:54:44'),
	('NOK','Nkr','Norwegian Krone',578,'hidden',' ','after',2,0.00,',',',','Krone','Øre',1.00000,'2014-01-03 20:54:44'),
	('NPR','NPRs','Nepalese Rupee',524,'hidden',' ','after',2,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('NZD','NZ$','New Zealand Dollar',554,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('PAB','B/.','Panamanian Balboa',590,'hidden',' ','after',2,0.00,',','.','Balboa','Centésimo',1.00000,'2014-01-03 20:54:44'),
	('PEN','S/.','Peruvian Nuevo Sol',604,'before',' ','hidden',2,0.00,',','.','Nuevos Sole','Céntimo',1.00000,'2014-01-03 20:49:55'),
	('PGK','PGK','Papua New Guinean Kina',598,'hidden',' ','after',2,0.00,',','.','Kina ','Toea',1.00000,'2014-01-03 20:54:44'),
	('PHP','?','Philippine Peso',608,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('PKR','PKRs','Pakistani Rupee',586,'hidden',' ','after',0,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('PLN','z?','Polish Z?oty',985,'after',' ','hidden',2,0.00,',',',','Z?otych','Grosz',1.00000,'2014-01-03 20:49:55'),
	('PYG','?','Paraguayan Guarani',600,'hidden',' ','after',0,0.00,',','.','Guarani','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('QAR','QR','Qatari Rial',634,'hidden',' ','after',2,0.00,',','.','Rial','Dirham',1.00000,'2014-01-03 20:54:44'),
	('RHD','RH$','Rhodesian Dollar',NULL,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('RON','RON','Romanian Leu',NULL,'hidden',' ','after',2,0.00,',','.','Leu','Ban',1.00000,'2014-01-03 20:54:44'),
	('RSD','din.','Serbian Dinar',NULL,'hidden',' ','after',0,0.00,',','.','Dinars','Para',1.00000,'2014-01-03 20:54:44'),
	('RUB','???.','Russian Ruble',643,'after',' ','hidden',2,0.00,',',',','Ruble','Kopek',1.00000,'2014-01-03 20:49:55'),
	('SAR','SR','Saudi Riyal',682,'hidden',' ','after',2,0.00,',','.','Riyals','Hallallah',1.00000,'2014-01-03 20:54:44'),
	('SBD','SI$','Solomon Islands Dollar',90,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SCR','SRe','Seychellois Rupee',690,'hidden',' ','after',2,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('SDD','LSd','Old Sudanese Dinar',736,'hidden',' ','after',2,0.00,',','.','Dinar','None',1.00000,'2014-01-03 20:54:44'),
	('SEK','kr','Swedish Krona',752,'after',' ','hidden',2,0.00,',',',','Kronor','Öre',1.00000,'2014-01-03 20:49:55'),
	('SGD','S$','Singapore Dollar',702,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SHP','SH£','Saint Helena Pound',654,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('SLL','Le','Sierra Leonean Leone',694,'hidden',' ','after',0,0.00,',','.','Leone','Cent',1.00000,'2014-01-03 20:54:44'),
	('SOS','Ssh','Somali Shilling',706,'hidden',' ','after',0,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('SRD','SR$','Surinamese Dollar',NULL,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SRG','Sf','Suriname Guilder',740,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('STD','Db','São Tomé and Príncipe Dobra',678,'hidden',' ','after',0,0.00,',','.','Dobra','Cêntimo',1.00000,'2014-01-03 20:54:44'),
	('SYP','SY£','Syrian Pound',760,'hidden',' ','after',0,0.00,',','.','Pound','Piastre',1.00000,'2014-01-03 20:54:44'),
	('SZL','SZL','Swazi Lilangeni',748,'hidden',' ','after',2,0.00,',','.','Lilangeni','Cent',1.00000,'2014-01-03 20:54:44'),
	('THB','?','Thai Baht',764,'hidden',' ','after',2,0.00,',','.','Baht','Satang',1.00000,'2014-01-03 20:54:44'),
	('TND','DT','Tunisian Dinar',788,'hidden',' ','after',3,0.00,',','.','Dinar','Millime',1.00000,'2014-01-03 20:54:44'),
	('TOP','T$','Tongan Pa?anga',776,'hidden',' ','after',2,0.00,',','.','Pa?anga','Senit',1.00000,'2014-01-03 20:54:44'),
	('TRY','TL','Turkish Lira',949,'after',' ','',2,0.00,'.',',','Lira','Kurus',1.00000,'2014-01-03 20:49:55'),
	('TTD','TT$','Trinidad and Tobago Dollar',780,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('TWD','NT$','New Taiwan Dollar',901,'hidden',' ','after',2,0.00,',','.','New Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('TZS','TSh','Tanzanian Shilling',834,'hidden',' ','after',0,0.00,',','.','Shilling','Senti',1.00000,'2014-01-03 20:54:44'),
	('UAH','???.','Ukrainian Hryvnia',980,'after',' ','hidden',2,0.00,',','.','Hryvnia','Kopiyka',1.00000,'2014-01-03 20:49:55'),
	('UGX','USh','Ugandan Shilling',800,'hidden',' ','after',0,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('USD','$','United States Dollar',840,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('UYU','\$U','Uruguayan Peso',858,'hidden',' ','after',2,0.00,',','.','Peso','Centésimo',1.00000,'2014-01-03 20:54:44'),
	('VEF','Bs.F.','Venezuelan Bolívar Fuerte',NULL,'hidden',' ','after',2,0.00,',','.','Bolivares Fuerte','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('VND','?','Vietnamese Dong',704,'after','','hidden',0,0.00,'.','.','Dong','Hà',1.00000,'2014-01-03 20:53:33'),
	('VUV','VT','Vanuatu Vatu',548,'hidden',' ','after',0,0.00,',','.','Vatu',NULL,1.00000,'2014-01-03 20:54:44'),
	('WST','WS$','Samoan Tala',882,'hidden',' ','after',2,0.00,',','.','Tala','Sene',1.00000,'2014-01-03 20:54:44'),
	('XAF','FCFA','CFA Franc BEAC',950,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('XCD','EC$','East Caribbean Dollar',951,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('XOF','CFA','CFA Franc BCEAO',952,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('XPF','CFPF','CFP Franc',953,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('YER','YR','Yemeni Rial',886,'hidden',' ','after',0,0.00,',','.','Rial','Fils',1.00000,'2014-01-03 20:54:44'),
	('ZAR','R','South African Rand',710,'before',' ','hidden',2,0.00,',','.','Rand','Cent',1.00000,'2014-01-03 20:49:55'),
	('ZMK','ZK','Zambian Kwacha',894,'hidden',' ','after',0,0.00,',','.','Kwacha','Ngwee',1.00000,'2014-01-03 20:54:44');",
        "INSERT INTO `{$_TABLES['shop.shipping']}`
            (id, module_code, name, min_units, max_units, rates)
        VALUES
            (0, 'usps', 'USPS Priority Flat Rate', 0.0001, 50.0000, '[{\"dscp\":\"Small\",\"units\":5,\"rate\":7.2},{\"dscp\":\"Medium\",\"units\":20,\"rate\":13.65},{\"dscp\":\"Large\",\"units\":50,\"rate\":18.9}]')",
);

$SHOP_UPGRADE['0.7.1'] = array(
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
    "ALTER TABLE {$_TABLES['shop.products']} ADD `brand_id` int(11) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `supplier_id` int(11) NOT NULL DEFAULT 0",
    // Note: Removal of the products `brand` field happens in upgrade.php after brand_id is populated
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
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY IF EXISTS `item_id`",
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
    "ALTER TABLE {$_TABLES['shop.products']} ADD `brand_id` int(11) unsigned NOT NULL DEFAULT 0 AFTER `max_ord_qty`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `supplier_id` int(11) unsigned NOT NULL DEFAULT 0 AFTER `brand_id`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `reorder` int(10) unsigned NOT NULL DEFAULT 0 after `onhand`",
);

$_SQL['shop.prod_opt_grps'] = $SHOP_UPGRADE['1.0.0'][0];
$_SQL['shop.oi_opts'] = $SHOP_UPGRADE['1.0.0'][1];
$_SQL['shop.shipments'] = $SHOP_UPGRADE['1.0.0'][2];
$_SQL['shop.shipment_items'] = $SHOP_UPGRADE['1.0.0'][3];
$_SQL['shop.shipment_packages'] = $SHOP_UPGRADE['1.0.0'][4];
$_SQL['shop.carrier_config'] = $SHOP_UPGRADE['1.0.0'][5];
$_SQL['shop.cache'] = $SHOP_UPGRADE['1.0.0'][6];
$_SQL['shop.tax_rates'] = $SHOP_UPGRADE['1.1.0'][0];
$_SQL['shop.discountcodes'] = $SHOP_UPGRADE['1.1.0'][1];
$_SQL['shop.prodXcat'] = $SHOP_UPGRADE['1.1.0'][2];
$_SQL['shop.product_variants'] = $SHOP_UPGRADE['1.1.0'][3];
$_SQL['shop.variantXopt'] = $SHOP_UPGRADE['1.1.0'][4];
$_SQL['shop.suppliers'] = $SHOP_UPGRADE['1.1.0'][5];

?>
