<?php
/**
 * Upgrade to version 1.3.1
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Upgrades;

class v1_3_1 extends Upgrade
{
    public static function upgrade()
    {
        global $_TABLES;

        self::$ver = '1.3.1';

        if (!DB_checkTableExists('shop.stock')) {
            // Creating the stock table, append the SQL to load it.
            // ... Add stock levels for products with no variants
            self::addSql(
                "INSERT INTO {$_TABLES['shop.stock']} (
                SELECT 0, p.id, 0, p.onhand, 0, p.reorder FROM {$_TABLES['shop.products']} p
                LEFT OUTER JOIN {$_TABLES['shop.product_variants']} pv
                    ON p.id = pv.item_id
                WHERE pv.pv_id IS NULL
                GROUP BY p.id)"
            );
            // ... Add stock levels for each variant
            self::addSql("INSERT INTO {$_TABLES['shop.stock']}
                (SELECT 0, item_id, pv_id, onhand, 0, reorder FROM {$_TABLES['shop.product_variants']})"
            );
            // ... Only AFTER loading the stock table, drop the old fields from
            // the product table.
            self::addSql(
                "ALTER TABLE {$_TABLES['shop.products']} DROP onhand, DROP reorder"
            );

            // Using this as a key to figure out if this is the first time.
            // Swap the tax_loc field values for shippers.
            self::addSql(
                "UPDATE {$_TABLES['shop.shipping']} SET tax_loc = 2 WHERE tax_loc = 1"
            );
            self::addSql(
                "UPDATE {$_TABLES['shop.shipping']} SET tax_loc = 1 WHERE tax_loc = 0"
            );
            self::addSql(
                "UPDATE {$_TABLES['shop.shipping']} SET tax_loc = 0 WHERE tax_loc = 2"
            );
        }

        if (!self::doUpgradeSql(self::$ver, self::$dvlp)) {
            return false;
        }
        return self::setVersion(self::$ver);
    }

}
