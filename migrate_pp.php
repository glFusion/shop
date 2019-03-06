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
            COM_errorLog(__FUNCTION__ . ': Could not include ' . $pp_path);
            return false;
        }
    }

    // Must have at least version 0.6.0 of the Paypal plugin
    if (
        !isset($_PP_CONF['pi_version']) ||
        !COM_checkVersion($_PP_CONF['pi_version'], '0.6.0')
    ) {
        COM_errorLog(__FUNCTION__ . ': Paypal version not found or less than 0.6.0');
        return false;
    }

    // Include the Paypal SQL updates to execute those done since version 0.6.0,    // if not already at 0.6.1. Have to include all the 0.6.1 update SQL here.
    if (!COM_checkVersion($_PP_CONF['pi_version'], '0.6.1')) {
        $PP_UPGRADE = array(
            "ALTER TABLE {$_TABLES['paypal.prod_attr']} CHANGE orderby orderby int(3)",
            "ALTER TABLE {$_TABLES['paypal.orders']} ADD currency varchar(5) NOT NULL DEFAULT 'USD'",
            "ALTER TABLE {$_TABLES['paypal.orders']} ADD order_seq int(11) UNSIGNED",
            "ALTER TABLE {$_TABLES['paypal.orders']} ADD UNIQUE (order_seq)",
            "SET @i:=0",
            "UPDATE {$_TABLES['paypal.orders']} SET order_seq = @i:=@i+1
                WHERE status NOT IN ('cart','pending') ORDER BY order_date ASC",
        );
        if (_SHOPtableHasColumn('paypal.orders', 'order_date_old')) {
            // This may have been left over from the 0.6.0 upgrade
            $PP_UPGRADE[] = "ALTER TABLE {$_TABLES['paypal.orders']} DROP order_date_old";
        }
        foreach ($PP_UPGRADE as $sql) {
            DB_query($sql, 1);
        }
    }

    // Perform the migration
    $tables = array(
        'address', 'buttons', 'categories', 'coupon_log', 'coupons',
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

    // Clear out the Shop tables and insert data from Paypal
    $sql[] = "TRUNCATE $shop; INSERT INTO $shop (SELECT * FROM $pp)";

    // Execute the SQL to import Paypal. Quit at the first error.
    foreach ($sql as $s) {
        DB_query($s, 1);
        if (DB_error()) {
            COM_errorLog(__FUNCTION__ . ": SQL error: $s");
            return false;
        }
    }

    // Copy images and other assets
    $dirs = array(
        $_CONF['path'] . 'data/paypal/files' => $_CONF['path'] . 'data/shop/files',
        $_CONF['path'] . 'data/paypal/keys' => $_CONF['path'] . 'data/shop/keys',
        $_CONF['path_html'] . 'paypal/images/products' => $_CONF['path_html'] . 'shop/images/products',
        $_CONF['path_html'] . 'paypal/images/categories' => $_CONF['path_html'] . 'shop/images/categories',
        $_CONF['path_html'] . 'paypal/images/gateways' => $_CONF['path_html'] . 'shop/images/gateways',
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
