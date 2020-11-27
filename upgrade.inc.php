<?php
/**
 * Upgrade routines for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.1
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

global $_CONF, $_SHOP_CONF;

/** Include the table creation strings */
require_once __DIR__ . "/sql/mysql_install.php";

/**
 * Perform the upgrade starting at the current version.
 *
 * @param   boolean $dvlp   True for development update, ignore errors
 * @return  boolean     True on success, False on failure
 */
function SHOP_do_upgrade($dvlp = false)
{
    global $_TABLES, $_CONF, $_SHOP_CONF, $shopConfigData, $SHOP_UPGRADE, $_PLUGIN_INFO, $_DB_name;

    $pi_name = $_SHOP_CONF['pi_name'];
    if (isset($_PLUGIN_INFO[$pi_name])) {
        $current_ver = $_PLUGIN_INFO[$pi_name]['pi_version'];
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_shop();

    if (!COM_checkVersion($current_ver, '0.7.1')) {
        $current_ver = '0.7.1';
        // See if the shipper_id column is already in place. If not then
        // the shipper info will be moved from the info array to the new column
        // after it is created.
        $set_shippers = _SHOPtableHasColumn('shop.orders', 'shipper_id') ? false : true;
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;
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
        if (!SHOP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.0.0')) {
        $current_ver = '1.0.0';

        if (!DB_checkTableExists('shop.prod_opt_grps')) {
            // Initial populate of the new attribute group table
            // The table won't exist yet, these statememts get appended
            // to the upgrade SQL.
            $SHOP_UPGRADE[$current_ver][] = "INSERT INTO {$_TABLES['shop.prod_opt_grps']} (pog_name) (SELECT DISTINCT attr_name FROM {$_TABLES['shop.prod_opt_vals']})";
            $SHOP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['shop.prod_opt_vals']} AS pov INNER JOIN (SELECT pog_id,pog_name FROM {$_TABLES['shop.prod_opt_grps']}) AS pog ON pov.attr_name=pog.pog_name SET pov.pog_id = pog.pog_id";
        }
        // This has to be done after updating the attribute group above
        if (_SHOPtableHasColumn('shop.prod_opt_vals', 'attr_name')) {
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP attr_name";
        }
        // Now that the pog_id field has been populated we can add the unique index.
        if (_SHOPtableHasIndex('shop.prod_opt_vals', 'item_id')) {
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY `item_id`";
        }
        $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD UNIQUE `item_id` (`item_id`,`pog_id`,`pov_value`)";

        if (_SHOPcolumnType('shop.sales', 'start') != 'datetime') {
            $tz_offset = $_CONF['_now']->format('P', true);
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} ADD st_tmp datetime after `start`";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} ADD end_tmp datetime after `end`";
            $SHOP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['shop.sales']} SET
                st_tmp = convert_tz(from_unixtime(start), @@session.time_zone, '$tz_offset'),
                end_tmp = convert_tz(from_unixtime(end), @@session.time_zone, '$tz_offset')";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} DROP start, DROP end";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} CHANGE st_tmp start datetime NOT NULL DEFAULT '1970-01-01 00:00:00'";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} CHANGE end_tmp end datetime NOT NULL DEFAULT '9999-12-31 23:59:59'";
        }


        // Make a note if the OrderItemOptions table exists.
        // Will use this after all the other SQL updates are done if necessary.
        $populate_oi_opts = !DB_checkTableExists('shop.oi_opts');
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;

        // Synchronize the options and custom fields from the orderitem into the
        // new ordeitem_options table. This should only be done once when the
        // oi_opts table is created. Any time after this update the required
        // source fields may be removed.
        if ($populate_oi_opts) {
            COM_errorLog("Transferring orderitem options to orderitem_options table");
            $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                // Transfer the option info from numbered options.
                if (!empty($A['options'])) {
                    $opt_ids = explode(',', $A['options']);
                    $Item = new Shop\OrderItem($A);
                    foreach ($opt_ids as $opt_id) {
                        $OIO = new Shop\OrderItemOption();
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
        SHOP_update_config();
        // After the config is updated, we have to split up the old single-line
        // shop address into reasonable components.
        if ($update_addr) {
            $c = config::get_instance();
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
        if (!SHOP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.1.0')) {
        $current_ver = '1.1.0';

        if (_SHOPtableHasColumn('shop.products', 'cat_id')) {
            $SHOP_UPGRADE[$current_ver][] = "INSERT IGNORE INTO {$_TABLES['shop.prodXcat']}
                (product_id, cat_id)
                SELECT id, cat_id FROM {$_TABLES['shop.products']}";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.products']}
                DROP cat_id";
        }
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;

        if (_SHOPtableHasColumn('shop.products', 'brand')) {
            // Update brand_id and supplier_id fields, and drop brand field
            // Must be done after other SQL updates to the products table.
            $brands = array();
            $sql = "SELECT id, brand FROM {$_TABLES['shop.products']} WHERE brand <> ''";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                if (!isset($brands[$A['brand']])) {
                    $brands[$A['brand']] = array(
                        'sup_id' => 0,
                        'items' => array(),
                    );
                }
                $brands[$A['brand']]['items'][] = (int)$A['id'];
            }
            foreach ($brands as $brand=>$items) {
                $Sup = new Shop\Supplier;
                if ($Sup->setCompany($brand)
                    ->setIsBrand(1)
                    ->setIsSupplier(0)
                    ->Save()) {
                    $brands[$brand]['sup_id'] = $Sup->getID();
                }
            }
            // update schema
            foreach ($brands as $brand=>$data) {
                if ($data['sup_id'] == 0) {
                    // Saving the supplier failed
                    continue;
                }
                $sup_id = (int)$data['sup_id'];
                $prod_ids = implode(',', $data['items']);
                $sql = "UPDATE {$_TABLES['shop.products']}
                    SET brand_id = $sup_id
                    WHERE id in ($prod_ids)";
                DB_query($sql);
            }
            DB_query("ALTER TABLE {$_TABLES['shop.products']} DROP `brand`");
        }

        if (_SHOPtableHasIndex('shop.prod_opt_vals', 'pog_value') != 2) {
            // Upgrades to use the new product variants.
            Shop\MigratePP::createVariants();
        }
        if (!is_dir($_SHOP_CONF['tmpdir'] . '/images/brands')) {
            mkdir($_SHOP_CONF['tmpdir'] . '/images/brands', true);
        }
        if (!SHOP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.1.2')) {
        $current_ver = '1.1.2';
        if (!_SHOPtableHasColumn('shop.address', 'phone')) {
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.address']}
                ADD `phone` varchar(20) DEFAULT NULL after `zip`";
        }
        if (!_SHOPtableHasColumn('shop.orderitems', 'dc_price')) {
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.orderitems']}
                ADD dc_price decimal(9,4) NOT NULL DEFAULT 0 after qty_discount";
        }
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SHOP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.2.0')) {
        $current_ver = '1.2.0';
        if (!_SHOPtableHasColumn('shop.products', 'brand_id')) {
            array_splice(
                $SHOP_UPGRADE[$current_ver],
                "ALTER TABLE {$_TABLES['shop.products']} ADD `brand_id` int(11) NOT NULL DEFAULT 0 after max_ord_qty"
            );
        }
        if (!_SHOPtableHasColumn('shop.products', 'supplier_id')) {
            array_splice(
                $SHOP_UPGRADE[$current_ver],
                "ALTER TABLE {$_TABLES['shop.products']} ADD `supplier_id` int(11) NOT NULL DEFAULT 0 AFTER brand_id"
            );
        }
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;
        // Load the variant descriptions into the new field.
        // Must be done after executing the upgrade SQL.
        $sql = "SELECT * FROM {$_TABLES['shop.product_variants']}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            Shop\ProductVariant::getInstance($A)
                ->loadOptions()
                ->makeDscp()
                ->Save();
        }
        if (!SHOP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.2.2')) {
        $current_ver = '1.2.2';

        // Add the unique item_id index back to the option values table.
        // Was added during previous upgrades but not new installations.
        if (!_SHOPtableHasIndex('shop.prod_opt_vals', 'item_id')) {
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.prod_opt_vals']}
                ADD UNIQUE `item_id` (`item_id`,`pog_id`,`pov_value`)";
        }
        if (!_SHOPtableHasColumn('shop.products', 'brand_id')) {
            array_splice(
                $SHOP_UPGRADE[$current_ver],
                "ALTER TABLE {$_TABLES['shop.products']} ADD `brand_id` int(11) NOT NULL DEFAULT 0"
            );
        }
        if (!_SHOPtableHasColumn('shop.products', 'supplier_id')) {
            array_splice(
                $SHOP_UPGRADE[$current_ver],
                "ALTER TABLE {$_TABLES['shop.products']} ADD `supplier_id` int(11) NOT NULL DEFAULT 0 AFTER brand_id"
            );
        }

        // Update the state tables for taxing S&H only if not
        // already done
        if (!_SHOPtableHasColumn('shop.states', 'tax_shipping')) {
            // US states that tax shipping and handling
            $SHOP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['shop.states']} s
                INNER JOIN {$_TABLES['shop.countries']} c
                    ON c.country_id = s.country_id
                SET s.tax_shipping = 1, s.tax_handling = 1
                WHERE c.alpha2 = 'US' AND s.iso_code in (
                    'AR', 'CA', 'CT', 'FL', 'GA', 'HI', 'IL', 'IA', 'KS',
                    'KY', 'MD', 'MA', 'MS', 'MO', 'NE', 'NJ', 'NM', 'NY',
                    'NC', 'ND', 'OH', 'PA', 'RI', 'SD', 'TN', 'TX', 'VT',
                    'WA', 'WV', 'WI', 'DC'
                )";
            // US states that tax only handling
            $SHOP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['shop.states']} s
                INNER JOIN {$_TABLES['shop.countries']} c
                    ON c.country_id = s.country_id
                SET s.tax_handling = 1
                WHERE c.alpha2 = 'US' AND s.iso_code in (
                    'AZ', 'MD', 'NV', 'VA'
                )";
        }
        // Update the payment method full description.
        // Saved with the order in case the gateway is changed later.
        if (!_SHOPtableHasColumn('shop.orders', 'pmt_dscp')) {
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.orders']}
                ADD pmt_dscp varchar(255) DEFAULT '' AFTER pmt_method";
            $sql = "SELECT order_id, pmt_method, info FROM {$_TABLES['shop.orders']}";
            $res = DB_query($sql);
            $gw_dscp = array();
            while ($A = DB_fetchArray($res, false)) {
                $info = @unserialize($A['info']);
                if (!$info) {
                    continue;
                }
                $pmt_method = isset($info['gateway']) ? $info['gateway'] : $A['pmt_method'];
                $pmt_method = DB_escapeString($pmt_method);
                if (!isset($gw_dscp[$pmt_method])) {
                    $gw = Shop\Gateway::getInstance($pmt_method);
                    if ($gw && $gw->isValid()) {
                        $gw_dscp[$pmt_method] = DB_escapeString($gw->getDscp());
                    } else {
                        $gw_dscp[$pmt_method] = DB_escapeString($pmt_method);
                    }
                }
                $SHOP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['shop.orders']} SET
                    pmt_dscp = '{$gw_dscp[$pmt_method]}',
                    pmt_method = '$pmt_method'
                    WHERE order_id = '{$A['order_id']}'";
            }
        }
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;
        // Convert the gateway configurations
        Shop\MigratePP::gwConvertConfig130();
        if (!SHOP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.3.0')) {
        $current_ver = '1.3.0';
        $upd_shipping = !_SHOPtableHasColumn('shop.orders', 'shipping_method');
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;
        if ($upd_shipping) {
            // Now update the shipping_method and shipping_dscp fields
            // using defaults from the shipping table.
            $sql = "UPDATE {$_TABLES['shop.orders']} orders
                LEFT JOIN {$_TABLES['shop.shipping']} shipping
                ON shipping.id = orders.shipper_id
                SET
                    orders.shipping_method = shipping.module_code,
                    orders.shipping_dscp = shipping.name
                WHERE orders.shipper_id > 0";
            DB_query($sql);
        }
        if (!SHOP_do_set_version($current_ver)) return false;
    }

    // Make sure paths and images are created.
    require_once __DIR__ . '/autoinstall.php';
    plugin_postinstall_shop(true);

    // Check and set the version if not already up to date.
    // For updates with no SQL changes
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!SHOP_do_set_version($installed_ver)) return false;
        $current_ver = $installed_ver;
    }
    \Shop\Cache::clear();
    SHOP_remove_old_files();
    CTL_clearCache();   // clear cache to ensure CSS updates come through
    SHOP_log("Successfully updated the {$_SHOP_CONF['pi_display_name']} Plugin", SHOP_LOG_INFO);
    // Set a message in the session to replace the "has not been upgraded" message
    COM_setMsg("Shop Plugin has been updated to $current_ver", 'info', 1);
    return true;
}


/**
 * Actually perform any sql updates.
 * Gets the sql statements from the $UPGRADE array defined (maybe)
 * in the SQL installation file.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   boolean $ignore_error   True to ignore SQL errors.
 * @return  boolean     True on success, False on failure
 */
function SHOP_do_upgrade_sql($version, $ignore_error = false)
{
    global $_TABLES, $_SHOP_CONF, $SHOP_UPGRADE, $_DB_dbms, $_VARS;

    // If no sql statements passed in, return success
    if (
        !isset($SHOP_UPGRADE[$version]) ||
        !is_array($SHOP_UPGRADE[$version])
    ) {
        return true;
    }

    if (
        $_DB_dbms == 'mysql' &&
        isset($_VARS['database_engine']) &&
        $_VARS['database_engine'] == 'InnoDB'
    ) {
        $use_innodb = true;
    } else {
        $use_innodb = false;
    }

    // Execute SQL now to perform the upgrade
    SHOP_log("--- Updating Shop to version $version", SHOP_LOG_INFO);
    foreach($SHOP_UPGRADE[$version] as $sql) {
        if ($use_innodb) {
            $sql = str_replace('MyISAM', 'InnoDB', $sql);
        }

        SHOP_log("Shop Plugin $version update: Executing SQL => $sql", SHOP_LOG_INFO);
        try {
            DB_query($sql, '1');
            if (DB_error()) {
                // check for error here for glFusion < 2.0.0
                SHOP_log('SQL Error during update', SHOP_LOG_INFO);
                //if (!$ignore_error) return false;
            }
        } catch (Exception $e) {
            SHOP_log('SQL Error ' . $e->getMessage(), SHOP_LOG_INFO);
            //if (!$ignore_error) return false;
        }
    }
    SHOP_log("--- Shop plugin SQL update to version $version done", SHOP_LOG_INFO);
    return true;
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
 */
function SHOP_do_set_version($ver)
{
    global $_TABLES, $_SHOP_CONF, $_PLUGIN_INFO;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '$ver',
            pi_gl_version = '{$_SHOP_CONF['gl_version']}',
            pi_homepage = '{$_SHOP_CONF['pi_url']}'
        WHERE pi_name = '{$_SHOP_CONF['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        SHOP_log("Error updating the {$_SHOP_CONF['pi_display_name']} Plugin version", SHOP_LOG_INFO);
        return false;
    } else {
        SHOP_log("{$_SHOP_CONF['pi_display_name']} version set to $ver", SHOP_LOG_INFO);
        // Set in-memory config vars to avoid tripping SHOP_isMinVersion();
        $_SHOP_CONF['pi_version'] = $ver;
        $_PLUGIN_INFO[$_SHOP_CONF['pi_name']]['pi_version'] = $ver;
        return true;
    }
}


/**
 * Update the plugin configuration
 */
function SHOP_update_config()
{
    USES_lib_install();

    require_once __DIR__ . '/install_defaults.php';
    _update_config('shop', $shopConfigData);
}

/**
 * Remove a file, or recursively remove a directory.
 *
 * @param   string  $dir    Directory name
 */
function SHOP_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . '/' . $object)) {
                    SHOP_rmdir($dir . '/' . $object);
                } else {
                    @unlink($dir . '/' . $object);
                }
            }
        }
        @rmdir($dir);
    } elseif (is_file($dir)) {
        @unlink($dir);
    }
}


/**
 * Remove deprecated files
 * Errors in unlink() and rmdir() are ignored.
 */
function SHOP_remove_old_files()
{
    global $_CONF;

    $paths = array(
        // private/plugins/shop
        __DIR__ => array(
            // 0.7.1
            'shop_functions.inc.php',
            // 1.0.0
            'classes/ProductImage.class.php',
            'classes/Attribute.class.php',
            'classes/AttributeGroup.class.php',
            'classes/UserInfo.class.php',
            'templates/attribute_form.thtml',
            // 1.3.0
            'vendor/square/connect',
            'Autoload.class.php',
            // 1.3.2
            'language/english.php',
            'language/german.php',
            'language/german_formal.php',
            'classes/CacheDB.class.php',
        ),
        // public_html/shop
        $_CONF['path_html'] . 'shop' => array(
            // 1.2.0
            'js/country_state.js',
            'docs/english/attribute_form.html',
	    'js/toggleEnabled.js',
        ),
        // admin/plugins/shop
        $_CONF['path_html'] . 'admin/plugins/shop' => array(
        ),
    );

    foreach ($paths as $path=>$files) {
        foreach ($files as $file) {
            SHOP_log("removing $path/$file");
            SHOP_rmdir("$path/$file");
        }
    }
}


/**
 * Check if a column exists in a table
 *
 * @param   string  $table      Table Key, defined in shop.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _SHOPtableHasColumn($table, $col_name)
{
    global $_TABLES;

    $col_name = DB_escapeString($col_name);
    $res = DB_query("SHOW COLUMNS FROM {$_TABLES[$table]} LIKE '$col_name'");
    return DB_numRows($res) == 0 ? false : true;
}


/**
 * Get the datatype for a specific column.
 *
 * @param   string  $table      Table Key, defined in shop.php
 * @param   string  $col_name   Column name to check
 * @return  string      Column datatype
 */
function _SHOPcolumnType($table, $col_name)
{
    global $_TABLES, $_DB_name;

    $retval = '';
    $sql = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_schema = '{$_DB_name}'
        AND table_name = '{$_TABLES[$table]}'
        AND COLUMN_NAME = '$col_name'";
    $res = DB_query($sql,1);
    if ($res) {
        $A = DB_fetchArray($res, false);
        $retval = $A['DATA_TYPE'];
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
function _SHOPtableHasIndex($table, $idx_name)
{
    global $_TABLES;

    $sql = "SHOW INDEX FROM {$_TABLES[$table]}
        WHERE key_name = '" . DB_escapeString($idx_name) . "'";
    $res = DB_query($sql);
    return DB_numRows($res);
}

?>
