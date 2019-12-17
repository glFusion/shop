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
        // Clear out the Shop tables and insert data from Paypal.
        // These tables have the same schema between Paypal 0.6.0 and Shop.
        $tables = array(
            'coupon_log',
            'order_log', 'orderstatus',
            'userinfo',
            'workflows', 'currency',
        );
        foreach ($tables as $table) {
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
        if (!self::migrateOptionValues()) {
            return false;
        }
        if (!self::migrateOptionGroups()) {
            return false;
        }
        if (!self::migrateShipping()) {
            return false;
        }
        if (!self::migrateGateways()) {
            return false;
        }
        if (!self::migrateIPNLog()) {
            return false;
        }
        if (!self::migrateAddress()) {
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

        COM_errorLog("-- Migrating table $shop_tbl");
        $shop_tbl = $_TABLES['shop.' . $shop_tbl];
        $pp_tbl = $_TABLES['paypal.' . $pp_tbl];
        return self::_dbExecute(array(
            "TRUNCATE $shop_tbl",
            "INSERT INTO $shop_tbl (SELECT * FROM $pp_tbl)",
        ) );
    }


    /**
     * Migrate Coupons from Paypal to Shop.
     * Adds the `status` field for Shop 1.0.0, default to `valid`.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateCoupons()
    {
        global $_TABLES;

        COM_errorLog("Migrating Coupons ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.coupons']}",
            "INSERT INTO {$_TABLES['shop.coupons']}
                SELECT *, 'valid' as status
                FROM {$_TABLES['paypal.coupons']}",
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

        COM_errorLog("Migrating Product Categories ...");
        $sql = array(
            "TRUNCATE {$_TABLES['shop.categories']}",
            "INSERT INTO {$_TABLES['shop.categories']} (
                cat_id, parent_id, cat_name, description,
                enabled, grp_access, image, google_taxonomy,
                lft, rgt
            ) SELECT
                cat_id, parent_id, cat_name, description,
                enabled, grp_access, image, '' as google_taxonomy,
                lft, rgt
            FROM {$_TABLES['paypal.categories']}",
        );
        return self::_dbExecute($sql);
    }


    /**
     * Migrate catalog products from Paypal to Shop.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateProducts()
    {
        global $_TABLES;

        COM_errorLog("Migrating Products ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.products']}",
            "INSERT INTO {$_TABLES['shop.products']}
                SELECT *, '' as brand, 1 as min_ord_qty, 0 as max_ord_qty
                FROM {$_TABLES['paypal.products']}",
        ) );
    }


    /**
     * Migrate Orders. Adds the shipper_id field, but there's no value to apply.
     * Also for Paypal < v0.6.1:
     * - Uses the global currency
     * - Creates and sequences the order_seq field
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateOrders()
    {
        global $_TABLES, $_PP_CONF, $_SHOP_CONF;

        COM_errorLog("Migrating Orders ...");
        $add_flds = ',0 as shipper_id'; // Needed for both Paypal 0.6.0 and 0.6.1
        // If not at Paypal 0.6.1, add a dummy order sequence value
        if (!COM_checkVersion($_PP_CONF['pi_version'], '0.6.1')) {
            // Needed for Paypal 0.6.0 only
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

        COM_errorLog("Migrating Order Items ...");
        // This version renames the "purchases" table to "orderitems".
        // Adds: qty_discounts, base_price
        // Removes: status
        $shop = $_TABLES['shop.orderitems'];
        $pp = $_TABLES['paypal.purchases'];
        return self::_dbExecute(array(
            "TRUNCATE $shop",
            "INSERT INTO $shop
                (id, order_id, product_id, description, quantity, txn_id,
                txn_type, expiration, base_price, price, qty_discount,
                taxable, token, options, options_text,
                extras, shipping, handling, tax)
            SELECT
                id, order_id, product_id, description, quantity, txn_id,
                txn_type, expiration, price, price, 0,
                taxable, token, options, options_text,
                extras, shipping, handling, tax
            FROM $pp",
        ) );
    }


    /**
     * Migrate Sale Pricing table.
     * Changes the date format from integer timestamp to datetime.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function migrateSales()
    {
        global $_TABLES, $_CONF;

        COM_errorLog("Migrating Sale Pricing ...");
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
     * Migrate product attributes. Adding the og_id field.
     * Adds the `attr_name` column for use by migrateOptionGroups().
     * Drops the unique item_id key until it can be added back by migrateOptionGroups().
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateOptionValues()
    {
        global $_TABLES;

        COM_errorLog("Migrating Option Values ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.prod_opt_vals']}",
            "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD attr_name varchar(40)",
            "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY `item_id`",
            "INSERT INTO {$_TABLES['shop.prod_opt_vals']}
                SELECT  attr_id as pov_id, 0 as pog_id, item_id, attr_value as pov_value,
                orderby, attr_price as pov_price, enabled, '' as sku, attr_name
                FROM {$_TABLES['paypal.prod_attr']}"
        ) );
    }


    /**
     * Create the option groups from the names of existing product options.
     * Uses the `attr_name` column to get the option group for each option value,
     * then drops that column.
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateOptionGroups()
    {
        global $_TABLES;

        // Initial populate of the new attribute group table, after the main migration.
        COM_errorLog("Migrating Option Groups ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.prod_opt_grps']}",
            "INSERT INTO {$_TABLES['shop.prod_opt_grps']} (pog_name)
                (SELECT DISTINCT attr_name FROM {$_TABLES['paypal.prod_attr']})",
            "UPDATE {$_TABLES['shop.prod_opt_vals']} AS pov INNER JOIN
                (SELECT pog_id,pog_name FROM {$_TABLES['shop.prod_opt_grps']}) AS pog ON pov.attr_name=pog.pog_name
                SET pov.pog_id = pog.pog_id",
            "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP attr_name",
            "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD UNIQUE `item_id` (`item_id`,`pog_id`,`pov_value`)",
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

        COM_errorLog("Migrating Shipping ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.shipping']}",
            "INSERT INTO {$_TABLES['shop.shipping']} (
                id, module_code,
                name, min_units, max_units, enabled,
                valid_from,
                valid_to,
                use_fixed,
                rates,
                grp_access
            ) SELECT
                id, '' as module_code,
                name, min_units, max_units, enabled,
                0 as valid_from,
                unix_timestamp('2037-12-31') as valid_to,
                0 as use_fixed,
                rates,
                2 as grp_access
            FROM {$_TABLES['paypal.shipping']}",
        ) );
    }


    /**
     * Migrate Gateway information.
     * Shop plugin adds the grp_access field in v1.0.0.
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateGateways()
    {
        global $_TABLES;

        COM_errorLog("Migrating Payment Gateways ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.gateways']}",
            "INSERT INTO {$_TABLES['shop.gateways']}
                SELECT *, 2 as grp_access
                FROM {$_TABLES['paypal.gateways']}",
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
                (img_id, product_id, filename, last_update)
            SELECT img_id, product_id, filename, '2018-01-01 00:00:00'
                FROM {$_TABLES['paypal.images']}",
        ) );
    }

    /**
     * Migrate Gateway information.
     * Shop plugin adds the grp_access field in v1.0.0.
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateIPNLog()
    {
        global $_TABLES;

        COM_errorLog("Migrating IPN Log ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.ipnlog']}",
            "INSERT INTO {$_TABLES['shop.ipnlog']}
                SELECT *, '' as order_id
                FROM {$_TABLES['paypal.ipnlog']}",
        ) );
    }


    /**
     * Migrate Addresses.
     * Shop plugin changes the "id" field to "addr_id".
     *
     * @return  boolean     True on success, False on failure
     */
    public function migrateAddress()
    {
        global $_TABLES;

        COM_errorLog("Migrating Addreses ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.address']}",
            "INSERT INTO {$_TABLES['shop.address']} (
                addr_id, uid, name, company, address1, address2,
                city, state, country, zip,
                billto_def, shipto_def
            ) SELECT id, uid, name, company, address1, address2,
                city, state, country, zip,
                billto_def, shipto_def
            FROM {$_TABLES['paypal.address']}",
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

        // Check that the Paypal plugin exists on the filesystem, and that
        // there are no orders or products entered in Shop.
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

        // Verify that the Paypal plugin is installed.
        if (!DB_checkTableExists('paypal.products')) {
            SHOP_log('Paypal tables may be missing', SHOP_LOG_ERROR);
            return false;
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
