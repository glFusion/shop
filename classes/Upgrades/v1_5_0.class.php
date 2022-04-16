<?php
/**
 * Upgrade to version 1.5.0.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Upgrades;
use Shop\Config;
use Shop\OrderItem;
use Shop\ProductVariant;


class v1_5_0 extends Upgrade
{
    private static $ver = '1.5.0';

    public static function upgrade()
    {
        global $_TABLES, $SHOP_UPGRADE;

        $admin_email = Config::get('admin_email_addr');
        $shop_email = Config::get('shop_email');
        if (empty($shop_email) && !empty($admin_email)) {
            $c = \config::get_instance();
            $c->set('shop_email', $admin_mail, Config::PI_NAME);
        }
        if (!self::doUpgradeSql(self::$ver, self::$dvlp)) {
            return false;
        }
        $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}";
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $weight = 0;
                $OI = OrderItem::fromArray($A);
                $weight = (float)$OI->getProduct()->getWeight();
                if ($OI->getVariantId() > 0) {
                    $weight += (float)ProductVariant::getInstance($OI->getVariantId())->getWeight();
                }
                if ($weight > 0) {
                    DB_query(
                        "UPDATE {$_TABLES['shop.orderitems']}
                        SET shipping_weight = $weight
                        WHERE id = " . $OI->getId()
                    );
                }
            }
        }
        return self::setVersion(self::$ver);
    }

}
