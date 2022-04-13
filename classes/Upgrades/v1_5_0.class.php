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
use Shop\OrderItem;
use Shop\Product;
use Shop\Config;

class v1.5.0 extends Upgrade
{
    private static $ver = '1.5.0';

    public static function upgrade()
    {
        global $_TABLES, $SHOP_UPGRADE;

        $c = \config::get_instance();

        $admin_email = Config::get('admin_email_addr');
        $shop_email = Config::get('shop_email');
        if (empty($shop_email) && !empty($admin_email)) {
            $c->set('shop_email', $admin_mail, Config::PI_NAME)
        }
        return self::setVersion(self::$ver);
    }

}
