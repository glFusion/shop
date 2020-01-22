<?php
/**
 * Automatic installation functions for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v0.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include plugin configuration */
require_once __DIR__  . '/shop.php';
/** Include database queries */
require_once __DIR__ . '/sql/mysql_install.php';
/** Include default values */
require_once __DIR__ . '/install_defaults.php';

$language = $_CONF['language'];
if (!is_file(__DIR__  . '/language/' . $language . '.php')) {
    $language = 'english';
}
require_once __DIR__ . '/language/' . $language . '.php';
global $LANG_SHOP;

/** Plugin installation options */
$INSTALL_plugin['shop'] = array(
    'installer' => array(
        'type' => 'installer',
        'version' => '1',
        'mode' => 'install',
    ),
    'plugin' => array(
        'type' => 'plugin',
        'name' => $_SHOP_CONF['pi_name'],
        'ver' => $_SHOP_CONF['pi_version'],
        'gl_ver' => $_SHOP_CONF['gl_version'],
        'url' => $_SHOP_CONF['pi_url'],
        'display' => $_SHOP_CONF['pi_display_name'],
    ),
    array(
        'type' => 'group',
        'group' => 'shop Admin',
        'desc' => 'Users in this group can administer the Shop plugin',
        'variable' => 'admin_group_id',
        'admin' => true,
        'addroot' => true,
    ),
    array(
        'type' => 'feature',
        'feature' => 'shop.admin',
        'desc' => 'Ability to administer the Shop plugin',
        'variable' => 'admin_feature_id',
    ),
    array(
        'type' => 'feature',
        'feature' => 'shop.user',
        'desc' => 'Ability to use the Shop plugin',
        'variable' => 'user_feature_id',
    ),

    array(
        'type' => 'feature',
        'feature' => 'shop.view',
        'desc' => 'Ability to view Shop entries',
        'variable' => 'view_feature_id',
    ),
    array('type' => 'mapping',
        'group' => 'admin_group_id',
        'feature' => 'admin_feature_id',
        'log' => 'Adding feature to the admin group',
    ),
    array(
        'type' => 'mapping',
        'findgroup' => 'All Users',
        'feature' => 'view_feature_id',
        'log' => 'Adding feature to the All Users group',
    ),
    array(
        'type' => 'mapping',
        'findgroup' => 'Logged-in Users',
        'feature' => 'user_feature_id',
        'log' => 'Adding feature to the Logged-in Users group',
    ),
    array(
        'type' => 'block',
        'name' => 'shop_search',
        'title' => 'Catalog Search',
        'phpblockfn' => 'phpblock_shop_search',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_random',
        'title' => 'Random Product',
        'phpblockfn' => 'phpblock_shop_random',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_categories',
        'title' => 'Product Categories',
        'phpblockfn' => 'phpblock_shop_categories',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_featured',
        'title' => 'Featured Products',
        'phpblockfn' => 'phpblock_shop_featured',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_popular',
        'title' => 'Popular',
        'phpblockfn' => 'phpblock_shop_popular',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_recent',
        'title' => 'Newest Items',
        'phpblockfn' => 'phpblock_shop_recent',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_cart',
        'title' => 'Shopping Cart',
        'phpblockfn' => 'phpblock_shop_cart',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
        'blockorder' => 5,
        'onleft' => 1,
        'is_enabled' => 1,
    ),
);

$tables = array(
    'products', 'categories', 'orderitems', 'ipnlog', 'orders', 'sales',
    'prod_opt_vals', 'images', 'gateways', 'address', 'userinfo', 'workflows',
    'buttons', 'orderstatus', 'order_log', 'currency', 'coupons', 'coupon_log',
    'shipping',
    // v1.0.0
    'oi_opts', 'prod_opt_grps', 'shipments', 'shipment_items',
    'shipment_packages', 'carrier_config', 'cache',
    // v1.1.0
    'tax_rates', 'prodXcat', 'product_variants', 'variantXopt', 'suppliers',
    'discountcodes', 'regions', 'countries', 'states',
);
foreach ($tables as $table) {
    $INSTALL_plugin['shop'][] = array(
        'type' => 'table',
        'table' => $_TABLES['shop.' . $table],
        'sql' => $_SQL['shop.'. $table],
    );
}

/**
*   Puts the datastructures for this plugin into the glFusion database
*   Note: Corresponding uninstall routine is in functions.inc
*
*   @return boolean     True if successful False otherwise
*/
function plugin_install_shop()
{
    global $INSTALL_plugin, $_SHOP_CONF, $_PLUGIN_INFO, $_PLUGINS;

    $pi_name            = $_SHOP_CONF['pi_name'];
    $pi_display_name    = $_SHOP_CONF['pi_display_name'];

    COM_errorLog("Attempting to install the $pi_display_name plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$pi_name]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
*   Loads the configuration records for the Online Config Manager
*
*   @return boolean true = proceed with install, false = an error occured
*/
function plugin_load_configuration_shop()
{
    global $_CONF, $_SHOP_CONF, $_TABLES;

    // Get the group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id',
            "grp_name='{$_SHOP_CONF['pi_name']} Admin'");

    return plugin_initconfig_shop($group_id);
}


/**
 * Plugin-specific post-installation function.
 * - Creates the file download path and working area.
 * - Migrates configurations from the Paypal plugin, if installed and up to date.
 * - No longer automatically migrates Paypal data since the currency may not be configured.
 */
function plugin_postinstall_shop()
{
    global $_CONF, $_SHOP_CONF, $_SHOP_DEFAULTS, $_SHOP_SAMPLEDATA, $_TABLES, $_PLUGIN_INFO;

    // Create the working directory.  Under private/data by default
    // 0.5.0 - download path moved under tmpdir, so both are created
    //      here.
    $paths = array(
        $_SHOP_CONF['tmpdir'],
        $_SHOP_CONF['tmpdir'] . 'keys',
        $_SHOP_CONF['tmpdir'] . 'cache',
        $_SHOP_CONF['download_path'],
        $_SHOP_CONF['image_dir'],
        $_SHOP_CONF['catimgpath'],
        $_SHOP_CONF['tmpdir'] . '/images/brands',
    );
    foreach ($paths as $path) {
        COM_errorLog("Creating $path", 1);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        if (!is_writable($path)) {
            COM_errorLog("Cannot write to $path", 1);
        }
    }

    // Copy static "not available" product image
    copy(
        __DIR__ . '/data/images/products/notavailable.jpg',
        $_SHOP_CONF['image_dir'] . '/notavailable.jpg'
    );

    // Create an empty log file
    if (!file_exists($_SHOP_CONF['logfile'])) {
        $fp = fopen($_SHOP_CONF['logfile'], "w+");
        if (!$fp) {
            COM_errorLog("Failed to create logfile {$_SHOP_CONF['logfile']}", 1);
        } else {
            fwrite($fp, "*** Logfile Created ***\n");
            fclose($fp);
        }
    }

    if (!is_writable($_SHOP_CONF['logfile'])) {
        COM_errorLog("Can't write to {$_SHOP_CONF['logfile']}", 1);
    }

    // If the Paypal plugin is installed, migrate configuration data from it.
    // This does not require the Paypal plugin to be installed.
    if (
        array_key_exists('paypal', $_PLUGIN_INFO) &&
        COM_checkVersion($_PLUGIN_INFO['paypal']['pi_version'], '0.6.0')
    ) {
        // Migrate plugin configuration
        global $_PP_CONF;
        if (is_array($_PP_CONF)) {
            $c = config::get_instance();
            $shop_conf = $c->get_config('shop');
            foreach ($_PP_CONF as $key=>$val) {
                if (
                    $key == 'enable_svc_funcs' ||
                    !array_key_exists($key, $shop_conf)
                ) {
                    // skip config items that should not be migrated.
                    continue;
                }
                $c->set($key, $val, 'shop');
            }
        }
    }

    // Load the sample data. This can be replaced by Paypal data later.
    if (is_array($_SHOP_SAMPLEDATA)) {
        COM_errorLog("Loading sample data");
        foreach ($_SHOP_SAMPLEDATA as $sql) {
            DB_query($sql, 1);
            if (DB_error()) {
                COM_errorLog("Sample Data SQL Error: $sql", 1);
            }
        }
    }

    // Set the shop Admin ID
    $gid = (int)DB_getItem(
        $_TABLES['groups'],
        'grp_id',
        "grp_name='{$_SHOP_CONF['pi_name']} Admin'"
    );
    if ($gid < 1) {
        $gid = 1;        // default to Root if shop group not found
    }
    DB_query("INSERT INTO {$_TABLES['vars']} SET name='shop_gid', value=$gid");
}

?>
