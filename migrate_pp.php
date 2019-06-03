<?php
/**
 * Migrate data from the Paypal plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include plugin configuration */
require_once __DIR__  . '/shop.php';

/**
 * Migrate the Paypal data into the Shop plugin.
 * Imports all table data, but does not migrate configurations.
 *
 * @return  boolean     True on success, False on error
 */
function SHOP_migrate_pp()
{
    global $_CONF, $_SHOP_CONF, $_TABLES, $_PP_CONF;

    // Include the paypal table definitions, if not already included
    $pp_path = __DIR__ . '/../paypal/paypal.php';
    if (!isset($_PP_CONF)) {
        if (is_file($pp_path)) {
            require_once $pp_path;
        } else {
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

    // Include the Paypal SQL updates to execute those done since version 0.6.0,
    // if not already at 0.6.1. Have to include all the 0.6.1 update SQL here.
    $PP_UPGRADE = array();
    if (!COM_checkVersion($_PP_CONF['pi_version'], '0.6.1')) {
        $PP_UPGRADE = array(
            "ALTER TABLE {$_TABLES['paypal.prod_attr']} CHANGE orderby orderby int(3) NOT NULL DEFAULT '0'",
            "ALTER TABLE {$_TABLES['paypal.orders']} ADD currency varchar(5) NOT NULL DEFAULT 'USD'",
            "ALTER TABLE {$_TABLES['paypal.orders']} ADD order_seq int(11) UNSIGNED",
            "ALTER TABLE {$_TABLES['paypal.orders']} ADD UNIQUE (order_seq)",
            "SET @i:=0",
            "UPDATE {$_TABLES['paypal.orders']} SET order_seq = @i:=@i+1
                WHERE status NOT IN ('cart','pending') ORDER BY order_date ASC",
            // This may have been left over from the 0.6.0 upgrade
            "ALTER TABLE {$_TABLES['paypal.orders']} DROP order_date_old",
        );
    }
    // These are updates to the schema since Shop v0.7.0
    $PP_UPGRADE[] = "ALTER TABLE {$_TABLES['paypal.orders']} ADD `shipper_id` int(3) UNSIGNED DEFAULT '0' AFTER `order_seq`",
    foreach ($PP_UPGRADE as $sql) {
        // Ignore errors since we can't be sure which of these have already been done.
        DB_query($sql, 1);
        if (DB_error()) {
            SHOP_log("Non-fatal error runing $sql", SHOP_LOG_WARNING);
        }
    }

    // Perform the migration. Don't migrate the button cache.
    // Clear out the Shop tables and insert data from Paypal
    $tables = array(
        'address', 'categories', 'coupon_log', 'coupons',
        'gateways', 'images', 'ipnlog', 'order_log', 'orderstatus',
        'prod_attr', 'products', 'sales', 'shipping', 'userinfo',
        'workflows', 'currency', 'orders',
    );

    $sql = array();
    foreach ($tables as $tbl) {
        $shop = $_TABLES['shop.' . $tbl];
        $pp = $_TABLES['paypal.' . $tbl];
        $sql[] = "TRUNCATE $shop; INSERT INTO $shop (SELECT * FROM $pp)";
    }
    // This version renames the "purchases" table to "orderitems"
    $shop = $_TABLES['shop.orderitems'];
    $pp = $_TABLES['paypal.purchases'];
    $sql[] = "TRUNCATE $shop; INSERT INTO $shop (SELECT * FROM $pp)";

    // Execute the SQL to import Paypal. Quit at the first error.
    foreach ($sql as $s) {
        DB_query($s, 1);
        if (DB_error()) {
            SHOP_log("SQL error: $s", SHOP_LOG_ERROR);
            return false;
        }
    }

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

    // All migrations done, return True
    return true;
}


/**
 * Check if a column exists in a table.
 * This is borrowed from upgrade.inc.php since these files should never be
 * included at the same time.
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

?>
