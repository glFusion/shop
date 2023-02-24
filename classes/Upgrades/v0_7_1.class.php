<?php
/**
 * Upgrade to version 1.3.0
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Upgrades;


class v0_7_1 extends Upgrade
{
    public static function upgrade()
    {
        global $_TABLES;

        self::$ver = '0.7.1';

        // See if the shipper_id column is already in place. If not then
        // the shipper info will be moved from the info array to the new column
        // after it is created.
        $set_shippers = !self::tableHasColumn('shop.orders', 'shipper_id');
        if (!self::doUpgradeSql(self::$ver)) {
            return false;
        }

        if ($set_shippers) {
            // Need to copy the shipper_id value from the info section to the
            // new DB field.
            $sql = "SELECT order_id, info FROM {$_TABLES['shop.orders']}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $info = @unserialize($A['info']);
                if ($info !== false && isset($info['shipper_id'])) {
                    $shipper_id = (int)$info['shipper_id'];
                    unset($info['shipper_id']);
                    unset($info['shipper_name']);
                    $info = @serialize($info);
                    $sql = "UPDATE {$_TABLES['shop.orders']} SET
                            shipper_id = $shipper_id,
                            info = '" . DB_escapeString($info) . "'
                        WHERE order_id = '" . DB_escapeString($A['order_id']) ."'";
                    DB_query($sql);
                }
            }
        }
        return self::setVersion(self::$ver);
    }

}
