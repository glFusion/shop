<?php
/**
 * Upgrade to version 1.4.1.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Upgrades;
use Shop\OrderItem;
use Shop\Product;

class v1_4_1 extends Upgrade
{
    private static $ver = '1.4.1';

    public static function upgrade()
    {
        global $_TABLES, $SHOP_UPGRADE;

        // Set the shipping units value into each order item.
        // Must update the schema first, so check to see if the field exists
        // and save a flag if it doesn't.
        $populate_units = self::tableHasColumn('shop.orderitems', 'shipping_units');
        if (!self::doUpgradeSql(self::$ver, self::$dvlp)) {
            return false;
        }
        if ($populate_units) {
            SHOP_log("Adding shipping_units to order items for catalog items", SHOP_LOG_INFO);
            $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}";
            $res = DB_query($sql, 1);
            if ($res && DB_numRows($res) > 0) {
                while ($A = DB_fetchArray($res, false)) {
                    $OI = Shop\OrderItem::fromArray($A);
                    $OI->setShippingUnits(
                        $OI->getProduct()->getTotalShippingUnits($OI->getVariantId())
                    )->Save();
                }
            }
        }
        return self::setVersion(self::$ver);
    }

}
