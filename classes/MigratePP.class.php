<?php
/**
 * Migrate data from the Paypal plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Include plugin configuration
 */
require_once __DIR__  . '/../shop.php';

/**
 * Class to migrate data from Paypal 0.6.0 or 0.6.1 to the current version of Shop
 * @package Shop
 */
class MigratePP
{

    /**
     * Perform all data migrations and copy data files from Paypal to Shop.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function doMigration()
    {
        global $_PP_CONF;

        if (!self::canMigrate() || !self::prepare()) {
            return false;
        }

        // Perform the migration. Don't migrate the button cache.
        // Clear out the Shop tables and insert data from Paypal
        $tables = array(
            'address', 'coupon_log', 'coupons',
            'gateways', 'ipnlog', 'order_log', 'orderstatus',
            'userinfo',
            'workflows', 'currency',
        );
        foreach ($tables as $table) {
            COM_errorLog("-- Migrating table $table");
            if (!self::migrateTable($table)) {
                return false;
            }
        }
        if (!self::migrateCategories()) {
            return false;
        }
        if (!self::migrateProducts()) {
            return false;
        }
        if (!self::migrateOrders()) {
            return false;
        }
        if (!self::migrateOrderItems()) {
            return false;
        }
        if (!self::migrateOrderItemOptions()) {
            return false;
        }
        if (!self::migrateSales()) {
            return false;
        }
        if (!self::migrateImages()) {
            return false;
        }
        if (!self::migrateAttributes()) {
            return false;
        }
        if (!self::migrateAttributeGroups()) {
            return false;
        }
        if (!self::migrateShipping()) {
            return false;
        }
        return true;
    }


    /**
     * Check the version of the Paypal plugin installed.
     * If OK, perform non-database migrations.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function prepare()
    {
        global $_CONF, $_PP_CONF, $_SHOP_CONF;

        // Copy images and other assets
        $dirs = array(
            $_CONF['path'] . 'data/paypal/files' => $_CONF['path'] . 'data/shop/files',
            $_CONF['path'] . 'data/paypal/keys' => $_CONF['path'] . 'data/shop/keys',
            $_CONF['path_html'] . 'paypal/images/products' => $_SHOP_CONF['image_dir'],
            $_CONF['path_html'] . 'paypal/images/categories' => $_SHOP_CONF['catimgpath'],
        );
        foreach ($dirs as $src=>$dst) {
            $handle = opendir($src);
            while (false !== ($file = readdir($handle))) {
                if ($file != '.' && $file != '..' && !is_dir($src . '/' . $file)) {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
            closedir($handle);
        }

        return true;
    }


    /**
     * Migrate a table from Paypal to Shop.
     * Assumes both tables have identical schemas.
     * Allows for different table keys.
     *
     * @param   string  $shop_tbl   Shop table name
     * @param   string  $pp_tbl     Paypal table name, Shop name if empty
     * @return  boolean     True on success, False on failure
     */
    public static function migrateTable($shop_tbl, $pp_tbl='')
    {
        global $_TABLES;

        if ($pp_tbl == '') {
            $pp_tbl = $shop_tbl;
        }

        $shop_tbl = $_TABLES['shop.' . $shop_tbl];
        $pp_tbl = $_TABLES['paypal.' . $pp_tbl];
        return self::_dbExecute(array(
            "TRUNCATE $shop_tbl",
            "INSERT INTO $shop_tbl (SELECT * FROM $pp_tbl)",
        ) );
    }


    /**
     * Migrate catalog categories from Paypal to Shop.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateCategories()
    {
        global $_TABLES;

        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.categories']}",
            "INSERT INTO {$_TABLES['shop.categories']}
                SELECT *, '' as google_taxonomy
                FROM {$_TABLES['paypal.categories']}",
        ) );
    }


    /**
     * Migrate catalog products from Paypal to Shop.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateProducts()
    {
        global $_TABLES;

        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.products']}",
            "INSERT INTO {$_TABLES['shop.products']}
                SELECT *, '' as brand
                FROM {$_TABLES['paypal.products']}",
        ) );
    }


    /**
     * Migrate Order. Adds the shipper_id field, but there's no value to apply.
     * Also for Paypal < v0.6.1:
     * - Uses the global currency
     * - Creates and sequences the order_seq field
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateOrders()
    {
        global $_TABLES, $_PP_CONF, $_SHOP_CONF;

        $add_flds = ',0 as shipper_id'; // Needed for both Paypal 0.6.0 and 0.6.1
        // If not at Paypal 0.6.1, add a dummy order sequence value
        if (!COM_checkVersion($_PP_CONF['pi_version'], '0.6.1')) {
            $add_flds .= ",NULL as order_seq, '{$_SHOP_CONF['currency']}' as currency";
        }
        $sql = array(
            "TRUNCATE {$_TABLES['shop.orders']}",
            "INSERT INTO {$_TABLES['shop.orders']} SELECT * $add_flds FROM {$_TABLES['paypal.orders']}",
        );
        // If not at paypal 0.6.1, then create the order sequence values.
        if (!COM_checkVersion($_PP_CONF['pi_version'], '0.6.1')) {
            $sql[] = "SET @i:=0";
            $sql[] = "UPDATE {$_TABLES['shop.orders']} SET order_seq = @i:=@i+1
                WHERE status NOT IN ('cart','pending') ORDER BY order_date ASC";
        }
        return self::_dbExecute($sql);
    }


    /**
     * Migrate Order Items. Adds the qty_discount field not found in Paypal.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateOrderItems()
    {
        global $_TABLES;

        // This version renames the "purchases" table to "orderitems" and adds
        // a qty_discount field.
        $shop = $_TABLES['shop.orderitems'];
        $pp = $_TABLES['paypal.purchases'];
        return self::_dbExecute(array(
            "TRUNCATE $shop",
            "INSERT INTO $shop (SELECT *, 0 as qty_disccount FROM $pp)",
        ) );
    }


    /**
     * Migrate Order Items.
     * Changes the date format from integer timestamp to datetime.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateSales()
    {
        global $_TABLES, $_CONF;

        // Shop 1.0.0 changes the dates used in the Sales table.
        $tz_offset = $_CONF['_now']->format('P', true);
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.sales']}",
            "INSERT INTO {$_TABLES['shop.sales']}
                (id, name, item_type, item_id, start, end, discount_type, amount)
                SELECT id, name, item_type, item_id,
                    convert_tz(from_unixtime(start), @@session.time_zone, '$tz_offset'),
                    convert_tz(from_unixtime(end), @@session.time_zone, '$tz_offset'),
                    discount_type, amount
                FROM {$_TABLES['paypal.sales']}"
        ) );
    }


    /**
     * Migrate Order Item Options.
     * Transfer the order item options into the new orderitem_options table.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateOrderItemOptions()
    {
        global $_TABLES;

        COM_errorLog("Transferring orderitem options to orderitem_options table");
        $sql = "SELECT * FROM {$_TABLES['paypal.purchases']}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            // Transfer the option info from numbered options.
            if (!empty($A['options'])) {
                $opt_ids = explode(',', $A['options']);
                $Item = new \Shop\OrderItem($A);
                foreach ($opt_ids as $opt_id) {
                    $OIO = new \Shop\OrderItemOption();
                    $OIO->oi_id = $A['id'];
                    $OIO->setOpt($opt_id);
                    $OIO->Save();
                }
            }
            // Now transfer custom text fields defined in the product.
            $extras = json_decode($A['extras'], true);
            if (isset($extras['custom']) && !empty($extras['custom'])) {
                $values = $extras['custom'];
                $P = \Shop\Product::getByID($A['product_id']);
                $names = explode('|', $P->custom);
                foreach($names as $id=>$name) {
                    if (!empty($values[$id])) {
                        $OIO = new \Shop\OrderItemOption();
                        $OIO->oi_id = $A['id'];
                        $OIO->setOpt(0, $name, $values[$id]);
                        $OIO->Save();
                    }
                }
            }
        }
        return true;
    }


    /**
     * Migrate product attributes. Adding the ag_id field.
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateAttributes()
    {
        global $_TABLES;

        return self::_dbExecute(
            "TRUNCATE {$_TABLES['shop.prod_attr']}",
            "INSERT INTO {$_TABLES['shop.prod_attr']}
                SELECT *, 0 as ag_id FROM {$_TABLES['paypal.prod_attr']}"
        );
    }


    /**
     * Create the attribute groups from the names of existing product attributes.
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateAttributeGroups()
    {
        global $_TABLES;
    
        // Initial populate of the new attribute group table, after the main migration.
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.attr_grp']}",
            "INSERT INTO {$_TABLES['shop.attr_grp']} (ag_name) 
                (SELECT DISTINCT attr_name FROM {$_TABLES['paypal.prod_attr']})",
            "UPDATE {$_TABLES['shop.prod_attr']} AS pa INNER JOIN
                (SELECT ag_id,ag_name FROM {$_TABLES['shop.attr_grp']}) AS ag ON pa.attr_name=ag.ag_name
                SET pa.ag_id = ag.ag_id",
        ) );
    }


    /**
     * Migrate shipping information. Shop plugin adds several fields.
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateShipping()
    {
        global $_TABLES;

        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.shipping']}",
            "INSERT INTO {$_TABLES['shop.shipping']}
                SELECT *, 0 as valid_from, unix_timestamp('2037-12-31') as valid_to,
                0 as use_fixed, 2 as auth_grp FROM {$_TABLES['paypal.shipping']}",
        ) );
    }


    /**
     * Migrate shipping information. Shop plugin adds several fields.
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateImages()
    {
        global $_TABLES;

        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.images']}",
            "INSERT INTO {$_TABLES['shop.images']}
                SELECT *, NULL as nonce
                FROM {$_TABLES['paypal.images']}",
        ) );
    }


    /**
     * Check if the migration from Paypal can be performed.
     * Requires the PP plugin files and there can be no orders or products
     * already entered in Shop.
     *
     * @return  boolean     True if migration can be done, False if not
     */
    public static function canMigrate()
    {
        global $_CONF, $_PP_CONF;

        $pp_path = __DIR__ . '/../../paypal/paypal.php';
        if (
            !is_file($pp_path) ||
            \Shop\Order::haveOrders() ||
            \Shop\Product::haveProducts()
        ) {
            return false;
        }

        // Include the paypal table definitions, if not already included
        if (!isset($_PP_CONF)) {
            require_once $pp_path;
            if (!isset($_PP_CONF)) {
                SHOP_log('Could not include ' . $pp_path, SHOP_LOG_ERROR);
                return false;
            }
        }

        // Must have at least version 0.6.0 of the Paypal plugin
        if (
            !isset($_PP_CONF['pi_version']) ||
            !COM_checkVersion($_PP_CONF['pi_version'], '0.6.0')
        ) {
            SHOP_log('Paypal version not found or less than 0.6.0', SHOP_LOG_ERROR);
            return false;
        }

        return true;
    }


    /**
     * Execute one or more database queries.
     * Sets the return to False if any query fails but continues with others.
     *
     * @param   array       $sql_arr    Array of sql statements
     * @return  boolean     True on success, False on failure
     */
    private static function _dbExecute($sql_arr)
    {
        $retval = true;     // assume success
        if (!is_array($sql_arr)) {
            $sql_arr = array($sql_arr);
        }
        foreach ($sql_arr as $sql) {
            COM_errorLog(".... executing sql: $sql");
            DB_query($sql, 1);
            if (DB_error()) {
                $retval = false;
            }
        }
        return $retval;
    }

}

?>
