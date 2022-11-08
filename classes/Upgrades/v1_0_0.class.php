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
use Shop\OrderItem;
use Shop\OrderItemOption;
use Shop\Log;


class v1_0_0 extends Upgrade
{
    private static $ver = '1.0.0';

    public static function upgrade()
    {
        global $_TABLES, $SHOP_UPGRADE, $_SHOP_CONF;

        if (!DB_checkTableExists('shop.prod_opt_grps')) {
            // Initial populate of the new attribute group table
            // The table won't exist yet, these statememts get appended
            // to the upgrade SQL.
            $SHOP_UPGRADE[self::$current_ver][] = "INSERT INTO {$_TABLES['shop.prod_opt_grps']} (pog_name) (SELECT DISTINCT attr_name FROM {$_TABLES['shop.prod_opt_vals']})";
            $SHOP_UPGRADE[self::$current_ver][] = "UPDATE {$_TABLES['shop.prod_opt_vals']} AS pov INNER JOIN (SELECT pog_id,pog_name FROM {$_TABLES['shop.prod_opt_grps']}) AS pog ON pov.attr_name=pog.pog_name SET pov.pog_id = pog.pog_id";
        }

        // This has to be done after updating the attribute group above
        if (self::tableHasColumn('shop.prod_opt_vals', 'attr_name')) {
            $SHOP_UPGRADE[self::$current_ver][] = "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP attr_name";
        }

        // Now that the pog_id field has been populated we can add the unique index.
        if (self::tableHasIndex('shop.prod_opt_vals', 'item_id')) {
            $SHOP_UPGRADE[self::$current_ver][] = "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY `item_id`";
        }

        $SHOP_UPGRADE[self::$current_ver][] = "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD UNIQUE `item_id` (`item_id`,`pog_id`,`pov_value`)";

        if (self::columnType('shop.sales', 'start') != 'datetime') {
            $tz_offset = $_CONF['_now']->format('P', true);
            $SHOP_UPGRADE[self::$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} ADD st_tmp datetime after `start`";
            $SHOP_UPGRADE[self::$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} ADD end_tmp datetime after `end`";
            $SHOP_UPGRADE[self::$current_ver][] = "UPDATE {$_TABLES['shop.sales']} SET
                st_tmp = convert_tz(from_unixtime(start), @@session.time_zone, '$tz_offset'),
                end_tmp = convert_tz(from_unixtime(end), @@session.time_zone, '$tz_offset')";
            $SHOP_UPGRADE[self::$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} DROP start, DROP end";
            $SHOP_UPGRADE[self::$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} CHANGE st_tmp start datetime NOT NULL DEFAULT '1970-01-01 00:00:00'";
            $SHOP_UPGRADE[self::$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} CHANGE end_tmp end datetime NOT NULL DEFAULT '9999-12-31 23:59:59'";
        }


        // Make a note if the OrderItemOptions table exists.
        // Will use this after all the other SQL updates are done if necessary.
        $populate_oi_opts = !DB_checkTableExists('shop.oi_opts');
        if (!self::doUpgradeSql(self::$ver)) return false;

        // Synchronize the options and custom fields from the orderitem into the
        // new ordeitem_options table. This should only be done once when the
        // oi_opts table is created. Any time after this update the required
        // source fields may be removed.
        if ($populate_oi_opts) {
            Log::write('system', Log::INFO, "Transferring orderitem options to orderitem_options table");
            $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                // Transfer the option info from numbered options.
                if (!empty($A['options'])) {
                    $opt_ids = explode(',', $A['options']);
                    $Item = OrderItem::fromArray($A);
                    foreach ($opt_ids as $opt_id) {
                        $OIO = new OrderItemOption();
                        $OIO->oi_id = $A['id'];
                        $OIO->setOpt($opt_id);
                        $OIO->Save();
                    }
                }
                // Now transfer custom text fields defined in the product.
                $extras = json_decode($A['extras'], true);
                if (isset($extras['custom']) && !empty($extras['custom'])) {
                    $values = $extras['custom'];
                    $P = Shop\Product::getByID($A['product_id']);
                    $names = explode('|', $P->custom);
                    foreach($names as $id=>$name) {
                        if (!empty($values[$id])) {
                            $OIO = new Shop\OrderItemOption();
                            $OIO->oi_id = $A['id'];
                            $OIO->setOpt(0, $name, $values[$id]);
                            $OIO->Save();
                        }
                    }
                }
            }
        }

        $update_addr = !array_key_exists('address1', $_SHOP_CONF);
        if ($update_addr) {
            // If the shop address hasn't been split into parts, save the current
            // values. They'll be deleted during SHOP_update_config()
            $shop_name = $_SHOP_CONF['shop_name'];
            $shop_addr = $_SHOP_CONF['shop_addr'];
            $shop_country = $_SHOP_CONF['shop_country'];
        }
        self::updateConfig();
        // After the config is updated, we have to split up the old single-line
        // shop address into reasonable components.
        if ($update_addr) {
            $c = \config::get_instance();
            $c->set('company', $shop_name, $_SHOP_CONF['pi_name']);
            $c->set('country', $shop_country, $_SHOP_CONF['pi_name']);
            // Try breaking up the address by common separators
            // Start by putting the address line into the first element in case
            // no delimiters are found.
            $addr_parts = array($shop_addr);
            foreach (array(',', '<br />', '<br/>', '<br>') as $sep) {
                if (strpos($shop_addr, $sep) > 0) {
                    $addr_parts = explode($sep, $shop_addr);
                    break;
                }
            }
            $c->set('address1', trim($addr_parts[0]), $_SHOP_CONF['pi_name']);
            if (isset($addr_parts[1])) {
                $c->set('city', trim($addr_parts[1]), $_SHOP_CONF['pi_name']);
            }
            if (isset($addr_parts[2])) {
                $c->set('state', trim($addr_parts[2]), $_SHOP_CONF['pi_name']);
            }
        }
        return self::setVersion(self::$ver);
    }

}
