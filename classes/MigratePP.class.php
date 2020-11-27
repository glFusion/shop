<?php
/**
 * Migrate data from the Paypal plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
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
        if (!self::migrateUserinfo()) {
            // @since v1.1.0 to get cart info
            return false;
        }
        if (!self::createVariants()) {
            // @since v1.1.0 to create variants from option groups and values
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
            "INSERT INTO {$_TABLES['shop.products']} (
                `id`, `name`, `short_description`, `description`, `keywords`,
                `price`, `prod_type`, `file`, `expiration`, `enabled`,
                `featured`, `dt_add`, `views`, `comments_enabled`, `rating_enabled`,
                `buttons`, `rating`, `votes`, `weight`, `taxable`, `shipping_type`,
                `shipping_amt`, `shipping_units`, `show_random`, `show_popular`,
                `options`, `track_onhand`, `onhand`, `oversell`, `qty_discounts`,
                `custom`, `avail_beg`, `avail_end`,
                `brand`, `min_ord_qty`, `max_ord_qty`
            ) SELECT
                `id`, `name`, `short_description`, `description`, `keywords`,
                `price`, `prod_type`, `file`, `expiration`, `enabled`,
                `featured`, `dt_add`, `views`, `comments_enabled`, `rating_enabled`,
                `buttons`, `rating`, `votes`, `weight`, `taxable`, `shipping_type`,
                `shipping_amt`, `shipping_units`, `show_random`, `show_popular`,
                `options`, `track_onhand`, `onhand`, `oversell`, `qty_discounts`,
                `custom`, `avail_beg`, `avail_end`,
                '' as brand, 1 as min_ord_qty, 0 as max_ord_qty
            FROM {$_TABLES['paypal.products']}",
            "TRUNCATE {$_TABLES['shop.prodXcat']}",
            "INSERT IGNORE INTO {$_TABLES['shop.prodXcat']} (product_id, cat_id)
                SELECT id, cat_id FROM {$_TABLES['paypal.products']}",
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
        $add_flds = '';
        // If not at Paypal 0.6.1, add dummy currency and order sequence values
        if (!COM_checkVersion($_PP_CONF['pi_version'], '0.6.1')) {
            $currency = "'{$_SHOP_CONF['currency']}'";
            $order_seq = "NULL";
        } else {
            $currency = '`currency`';
            $order_seq = '`order_seq`';
        }
        $sql = array(
            "TRUNCATE {$_TABLES['shop.orders']}",
            "INSERT INTO {$_TABLES['shop.orders']} (
                order_id, uid, order_date, last_mod, billto_id,
                billto_name, billto_company, billto_address1, billto_address2,
                billto_city, billto_state, billto_country, billto_zip,
                shipto_id, shipto_name, shipto_company, shipto_address1,
                shipto_address2, shipto_city, shipto_state, shipto_country, shipto_zip,
                phone, buyer_email, gross_items, net_nontax, net_taxable, order_total,
                tax, shipping, handling, by_gc, status, pmt_method, pmt_txn_id,
                instructions, token, tax_rate, info, currency, order_seq,
                shipper_id, discount_code, discount_pct
            ) SELECT
                order_id, uid, order_date, last_mod, billto_id,
                billto_name, billto_company, billto_address1, billto_address2,
                billto_city, billto_state, billto_country, billto_zip,
                shipto_id, shipto_name, shipto_company, shipto_address1,
                shipto_address2, shipto_city, shipto_state, shipto_country, shipto_zip,
                phone, buyer_email,
                0 as gross_items, 0 as net_nontax, 0 as net_taxable, 0 as order_total,
                tax, shipping, handling, by_gc, status, pmt_method, pmt_txn_id,
                instructions, token, tax_rate, info, $currency, $order_seq,
                0 as shipper_id, '' as discount_code, 0 as discount_pct
            FROM {$_TABLES['paypal.orders']}",
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
                    $OIO->setOpt($opt_id);
                    $OIO->Save();
                }
            }
            // Now transfer custom text fields defined in the product.
            $extras = json_decode($A['extras'], true);
            if (isset($extras['custom']) && !empty($extras['custom'])) {
                $values = $extras['custom'];
                $P = \Shop\Product::getByID($A['product_id']);
                $names = explode('|', $P->getCustom());
                foreach($names as $id=>$name) {
                    if (!empty($values[$id])) {
                        $OIO = new \Shop\OrderItemOption();
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
    private static function migrateOptionValues()
    {
        global $_TABLES;

        COM_errorLog("Migrating Option Values ...");
        if (self::_tableHasIndex('shop.prod_opt_vals', 'item_id')) {
            self::_dbExecute("ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY `item_id`");
        }
        if (self::_tableHasIndex('shop.prod_opt_vals', 'pog_value')) {
            // Drop key so duplicate values can be created, it will be
            // replaced in createVariants()
            self::_dbExecute("ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY `pog_value`");
        }
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.prod_opt_vals']}",
            "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD attr_name varchar(40)",
            "INSERT INTO {$_TABLES['shop.prod_opt_vals']}
                SELECT  attr_id as pov_id, 0 as pog_id, item_id, attr_value as pov_value,
                orderby, attr_price as pov_price, enabled, '' as sku, attr_name
                FROM {$_TABLES['paypal.prod_attr']}"
        ) );
    }


    /**
     * Create product variants based on the option values.
     * Public so it can be called from upgrade.php.
     * This function will always return true since creating the variants is
     * convenient but not critical.
     *
     * @return  boolean     True on success, False on failure
     */
    public static function createVariants()
    {
        global $_TABLES;

        COM_errorLog("Creating product variants and trimming option Values ...");
        // Upgrades to use the new product variants.
        // TODO: drop item_id column, after creating Variant records
        $r_allvals = self::_dbExecute("SELECT * FROM {$_TABLES['shop.prod_opt_vals']}");
        $allvals = array();
        while ($A = DB_fetchArray($r_allvals, false)) {
            if (!isset($allvals[$A['pog_id']][$A['pov_value']])) {
                $allvals[$A['pog_id']][$A['pov_value']] = array(
                    'items' => array(),
                    'ids' => array(),
                    'new_id' => 0,
                );
            }
            $allvals[$A['pog_id']][$A['pov_value']]['ids'][] = $A['pov_id'];
            $allvals[$A['pog_id']][$A['pov_value']]['items'][] = $A['item_id'];
        }
        self::_dbExecute("TRUNCATE {$_TABLES['shop.product_variants']}");
        self::_dbExecute("TRUNCATE {$_TABLES['shop.variantXopt']}");
        // This index was deleted if existing in migrateOptionValues()...
        self::_dbExecute("ALTER TABLE {$_TABLES['shop.prod_opt_vals']}
            ADD UNIQUE `pog_value` (`pog_id`, `pov_value`)");
        foreach ($allvals as $pog_id=>$vals) {
            foreach ($vals as $val=>$info) {
                $pov_ids = implode(',', $info['ids']);
                $allvals[$pog_id][$val]['new_id'] = DB_getItem(
                    $_TABLES['shop.prod_opt_vals'],
                    'pov_id',
                    "pog_id = {$pog_id} AND pov_id IN ($pov_ids)"
                );
            }
        }
        // Cycle through again with the new IDs, collect the items
        // and create the variants.
        $items = array();
        foreach ($allvals as $pog_id=>$vals) {
            foreach ($vals as $val) {
                foreach ($val['items'] as $item_id) {
                    if (!isset($items[$item_id])) {
                        $items[$item_id] = array(
                            'item_id' => $item_id,
                            'groups' => array(),
                        );
                    }
                    $items[$item_id]['groups'][$pog_id][] = $val['new_id'];
                }
            }
        }

        // Now create the variants. Set the qty on hand to the item's qty,
        // otherwise items won't be shown in the catalog at all.
        foreach ($items as $item_id=>$opts) {
            $opts['onhand'] = Product::getById($item_id)->getOnhand();
            $PV = new ProductVariant;
            $PV->saveNew($opts);
        }

        // Now reorder the option values since there may be duplicate
        // orderby values due to table reduction.
        $sql = "SELECT pog_id FROM {$_TABLES['shop.prod_opt_grps']}";
        $res = self::_dbExecute($sql);
        while ($A = DB_fetchArray($res, false)) {
            ProductOptionValue::reOrder($A['pog_id']);
        }
        return true;

     }


    /**
     * Create the option groups from the names of existing product options.
     * Uses the `attr_name` column to get the option group for each option value,
     * then drops that column.
     *
     * @return  boolean     True on success, False on failure
     */
    private static function migrateOptionGroups()
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
            // Safe since it was deleted in migrateOptionValues() above:
            "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD UNIQUE `item_id` (`item_id`,`pog_id`,`pov_value`)",
        ) );
    }


    /**
     * Migrate shipping information. Shop plugin adds several fields.
     *
     * @return  boolean     True on success, False on failure
     */
    private static function migrateShipping()
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
    private static function migrateGateways()
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
    private static function migrateImages()
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
    private static function migrateIPNLog()
    {
        global $_TABLES;

        COM_errorLog("Migrating IPN Log ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.ipnlog']}",
            "INSERT INTO {$_TABLES['shop.ipnlog']}
                SELECT
                    id, ip_addr, ts, verified, txn_id, gateway, '', ipn_data, ''
                FROM {$_TABLES['paypal.ipnlog']}",
        ) );
    }


    /**
     * Migrate Addresses.
     * Shop plugin changes the "id" field to "addr_id".
     *
     * @return  boolean     True on success, False on failure
     */
    private static function migrateAddress()
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
     * Migrate customer information - cart and preferred gateway.
     *
     * @return  boolean     True on success, False on failure
     */
    private static function migrateUserinfo()
    {
        global $_TABLES;

        COM_errorLog("Migrating User Information ...");
        return self::_dbExecute(array(
            "TRUNCATE {$_TABLES['shop.userinfo']}",
            "INSERT INTO {$_TABLES['shop.userinfo']}
                (uid, cart, pref_gw)
            SELECT uid, cart, '' FROM {$_TABLES['paypal.userinfo']}",
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
     * Returns the last query result otherwise, so call this with a single
     * query to get the resource for further use.
     *
     * @param   array       $sql_arr    Array of sql statements
     * @return  boolean     True on success, False on failure
     */
    private static function _dbExecute($sql_arr)
    {
        if (!is_array($sql_arr)) {
            $sql_arr = array($sql_arr);
        }
        foreach ($sql_arr as $sql) {
            COM_errorLog(".... executing sql: $sql");
            $retval = DB_query($sql, 1);
            if (DB_error()) {
                $retval = false;
                break;
            }
        }
        return $retval;
    }


    /**
     * Check if a table has a specific index defined.
     *
     * @param   string  $table      Key into `$_TABLES` array
     * @param   string  $idx_name   Index name
     * @return  integer     Number of rows (fields) in the index
     */
    private static function _tableHasIndex($table, $idx_name)
    {
        global $_TABLES;

        $sql = "SHOW INDEX FROM {$_TABLES[$table]}
            WHERE key_name = '" . DB_escapeString($idx_name) . "'";
        $res = DB_query($sql);
        return DB_numRows($res);
    }


    /**
     * Convert the gateway configurations from earlier version to 1.3.0.
     * This allows the tabbed interface for test and prod environments.
     */
    public static function gwConvertConfig130()
    {
        global $_TABLES;

        foreach (array('authorizenet', 'paypal', 'square', 'stripe') as $gw) {
            $cfg = DB_getItem($_TABLES['shop.gateways'], 'config', "id='$gw'");
            $cfg = unserialize($cfg);
            if (!$cfg || isset($cfg['prod']) || isset($cfg['test'])) {
                // Skip if already done
                continue;
            }
            $fn = 'cfg_convert_' . $gw;
            if (method_exists(__CLASS__, $fn)) {
                $cfgFields = self::$fn($cfg);
                $config = DB_escapeString(serialize($cfgFields));
                DB_query("UPDATE {$_TABLES['shop.gateways']} SET
                    config = '$config'
                    WHERE id='$gw'");
            }
        }
    }


    /**
     * Convert the gateway config for authorize.net to 1.3.0
     *
     * @param   array   $cfg    Original config values
     * @return  array           New config values
     */
    private static function cfg_convert_authorizenet($cfg)
    {
        $cfgFields= array(
            'prod' => array(
                'api_login'    => $cfg['prod_api_login'],
                'trans_key'    => $cfg['prod_trans_key'],
                'hash_key'     => $cfg['prod_hash_key'],
            ),
            'test' => array(
                'api_login'    => $cfg['test_api_login'],
                'trans_key'    => $cfg['test_trans_key'],
                'hash_key'     => $cfg['test_hash_key'],
            ),
            'global' => array(
                'test_mode' => $cfg['test_mode'],
            ),
        );
        return $cfgFields;
    }


    /**
     * Convert the gateway config for Stripe to 1.3.0
     *
     * @param   array   $cfg    Original config values
     * @return  array           New config values
     */
    private static function cfg_convert_stripe($cfg)
    {
        $cfgFields = array(
            'prod' => array(
                'pub_key'  => $cfg['pub_key_prod'],
                'sec_key'  => $cfg['sec_key_prod'],
                'hook_sec' => $cfg['hook_sec_prod'],
            ),
            'test' => array(
                'pub_key'  => $cfg['pub_key_test'],
                'sec_key'  => $cfg['sec_key_test'],
                'hook_sec' => $cfg['hook_sec_test'],
            ),
            'global' => array(
                'test_mode' => $cfg['test_mode'],
            ),
        );
        return $cfgFields;
    }


    /**
     * Convert the gateway config for Square to 1.3.0
     *
     * @param   array   $cfg    Original config values
     * @return  array           New config values
     */
    private function cfg_convert_square($cfg)
    {
        $cfgFields = array(
            'prod' => array(
                'loc_id'   => $cfg['sb_loc_id'],
                'appid'    => $cfg['sb_appid'],
                'token'    => $cfg['sb_token'],
            ),
            'test' => array(
                'loc_id'     => $cfg['prod_loc_id'],
                'appid'      => $cfg['prod_appid'],
                'token'      => $cfg['prod_token'],
            ),
            'global' => array(
                'test_mode'  => $cfg['test_mode'],
            ),
        );
        return $cfgFields;
    }


    /**
     * Convert the gateway config for Paypal to 1.3.0
     *
     * @param   array   $cfg    Original config values
     * @return  array           New config values
     */
    private static function cfg_convert_paypal($cfg)
    {
        $cfgFields= array(
            'prod' => array(
                'receiver_email'    => $cfg['bus_prod_email'],
                'micro_receiver_email'  => $cfg['micro_prod_email'],
                'micro_cert_id'     => $cfg['micro_cert_id'],
                'endpoint'          => $cfg['prod_url'],
                'webhook_id'   => '',
                'pp_cert'           => $cfg['pp_cert'],
                'pp_cert_id'        => $cfg['pp_cert_id'],
                'micro_cert'        => $cfg['micro_cert'],
                'micro_cert_id'     => $cfg['micro_cert_id'],
                'api_username'      => '',
                'api_password'      => '',
            ),
            'test' => array(
                'receiver_email'    => $cfg['bus_test_email'],
                'micro_receiver_email'  => $cfg['micro_test_email'],
                'pp_cert'           => $cfg['pp_cert'],
                'pp_cert_id'        => $cfg['pp_cert_id'],
                'micro_cert'        => $cfg['micro_cert'],
                'micro_cert_id'     => $cfg['micro_cert_id'],
                'webhook_id' => '',
                'api_username'      => '',
                'api_password'      => '',
            ),
            'global' => array(
                'micro_threshold'   => $cfg['micro_threshold'],
                'encrypt'           => $cfg['encrypt'],
                'prv_key'           => $cfg['prv_key'],
                'pub_key'           => $cfg['pub_key'],
                'test_mode'         => $cfg['test_mode'],
            ),
        );
        return $cfgFields;
    }

}

?>
