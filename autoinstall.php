<?php
/**
 * Automatic installation functions for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.2
 * @since       v0.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include plugin configuration */
require_once __DIR__  . '/functions.inc';
/** Include database queries */
require_once __DIR__ . '/sql/mysql_install.php';
/** Include default values */
require_once __DIR__ . '/install_defaults.php';
use glFusion\Database\Database;
use glFusion\Log\Log;

global $_CONF;
$language = $_CONF['language'];
if (!is_file(__DIR__  . '/language/' . $language . '.php')) {
    $language = 'english';
}
require_once __DIR__ . '/language/' . $language . '.php';
global $LANG_SHOP, $_SQL, $_SHOP_CONF, $_TABLES;

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
        'type' => 'feature',
        'feature' => 'shop.admin',
        'desc' => 'Ability to administer the Shop plugin',
        'variable' => 'admin_feature_id',
    ),
    array(
        'type' => 'feature',
        'feature' => 'shop.user',
        'desc' => 'Ability to buy via the Shop plugin',
        'variable' => 'user_feature_id',
    ),
    array(
        'type' => 'feature',
        'feature' => 'shop.view',
        'desc' => 'Ability to view Shop products',
        'variable' => 'view_feature_id',
    ),
    array(
        'type' => 'mapping',
        'findgroup' => 'Root',
        'feature' => 'admin_feature_id',
        'log' => 'Adding Admin feature to the Root group',
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
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_random',
        'title' => 'Random Product',
        'phpblockfn' => 'phpblock_shop_random',
        'block_type' => 'phpblock',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_categories',
        'title' => 'Product Categories',
        'phpblockfn' => 'phpblock_shop_categories',
        'block_type' => 'phpblock',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_featured',
        'title' => 'Featured Products',
        'phpblockfn' => 'phpblock_shop_featured',
        'block_type' => 'phpblock',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_popular',
        'title' => 'Popular',
        'phpblockfn' => 'phpblock_shop_popular',
        'block_type' => 'phpblock',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_recent',
        'title' => 'Newest Items',
        'phpblockfn' => 'phpblock_shop_recent',
        'block_type' => 'phpblock',
        'is_enabled' => 0,
    ),
    array(
        'type' => 'block',
        'name' => 'shop_cart',
        'title' => 'Shopping Cart',
        'phpblockfn' => 'phpblock_shop_cart',
        'block_type' => 'phpblock',
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
    'shipment_packages', 'carrier_config',// 'cache',
    // v1.1.0
    'tax_rates', 'prodXcat', 'product_variants', 'variantXopt', 'suppliers',
    'discountcodes', 'regions', 'countries', 'states',
    // v1.2.0
    'features', 'features_values', 'prodXfeat', 'zone_rules',
    // v1.3.0
    'packages', 'payments', 'customerXgateway',
    'affiliate_sales', 'affiliate_saleitems', 'affiliate_payments',
    // v1.4.0
    'stock', 'plugin_products',
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

    Log::write('system', Log::INFO, "Attempting to install the $pi_display_name plugin");

    $ret = INSTALLER_install($INSTALL_plugin[$pi_name]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
 * Loads the configuration records for the Online Config Manager.
 *
 * @return  boolean     true = proceed with install, false = an error occured
 */
function plugin_load_configuration_shop()
{
    return plugin_initconfig_shop();
}


/**
 * Plugin-specific post-installation function.
 * - Creates the file download path and working area.
 * - Migrates configurations from the Paypal plugin, if installed and up to date.
 * - No longer automatically migrates Paypal data since the currency may not be configured.
 */
function plugin_postinstall_shop($upgrade=false)
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
        Log::write('system', Log::INFO, "Creating $path");
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        if (!is_writable($path)) {
            Log::write('system', Log::ERROR, "Cannot write to $path");
        }
    }

    // Copy static "not available" product image
    if (!is_file($_SHOP_CONF['image_dir'] . '/notavailable.jpg')) {
        copy(
            __DIR__ . '/data/images/products/notavailable.jpg',
            $_SHOP_CONF['image_dir'] . '/notavailable.jpg'
        );
    }

    // Create an empty log file
    if (!file_exists($_SHOP_CONF['logfile'])) {
        $fp = fopen($_SHOP_CONF['logfile'], "w+");
        if (!$fp) {
            Log::write('system', Log::INFO, "Failed to create logfile {$_SHOP_CONF['logfile']}", 1);
        } else {
            fwrite($fp, "*** Logfile Created ***\n");
            fclose($fp);
        }
    }
    if (!is_writable($_SHOP_CONF['logfile'])) {
        Log::write('system', Log::INFO, "Can't write to {$_SHOP_CONF['logfile']}", 1);
    }

    if (!$upgrade) {        // only do these for initial installations.
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
            $db = Database::getInstance();
            Log::write('system', Log::INFO, "Loading sample data");
            foreach ($_SHOP_SAMPLEDATA as $sql) {
                try {
                    $db->conn->executeStatement($sql);
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
                }
            }
        }
    }
}

