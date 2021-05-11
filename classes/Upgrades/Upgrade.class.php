<?php
/**
 * Upgrade routines for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Upgrades;
use Shop\Cache;
use Shop\Gateway;


/** Include the table creation strings */
require_once __DIR__ . "/../../sql/mysql_install.php";

/**
 * Perform the upgrade starting at the current version.
 *
 * @param   boolean $dvlp   True for development update, ignore errors
 * @return  boolean     True on success, False on failure
 */
class Upgrade
{
    /** Plugin name, for consistency.
     * @var string */
    protected static $pi_name = 'shop';

    /** Deployed code version, or target upgrade version.
     * @var string */
    protected static $code_ver;

    /** Currently-installed version.
     * @var string */
    protected static $current_ver;

    /** Flag to indicate a development upgrade.
     * Causes SQL errors to be ignored.
     * @var boolean */
    protected static $dvlp = false;


    /**
     * Perform all upgrades to get from $current_ver to $code_ver.
     *
     * @param   boolean $dvlp   True for a development upgrade
     * @return  boolean     True on success, False on first error
     */
    public static function doUpgrade($dvlp = false)
    {
        global $_PLUGIN_INFO;

        if (isset($_PLUGIN_INFO[self::$pi_name])) {
            self::$current_ver = $_PLUGIN_INFO[self::$pi_name]['pi_version'];
        } else {
            return false;
        }
        self::$code_ver = plugin_chkVersion_shop();
        self::$dvlp = $dvlp;

        if (!COM_checkVersion(self::$current_ver, '0.7.1')) {
            if (!v0_7_1::upgrade()) return false;
        }

        if (!COM_checkVersion(self::$current_ver, '1.0.0')) {
            if (!v1_0_0::upgrade()) return false;
        }

        if (!COM_checkVersion(self::$current_ver, '1.3.0')) {
            if (!v1_3_0::upgrade()) return false;
        }

        if (!COM_checkVersion(self::$current_ver, '1.3.1')) {
            if (!v1_3_1::upgrade()) return false;
        }

        // Make sure paths and images are created.
        require_once __DIR__ . '/../../autoinstall.php';
        plugin_postinstall_shop(true);

        // Check and set the version if not already up to date.
        // For code-only updates with no SQL changes
        if (!COM_checkVersion(self::$current_ver, self::$code_ver)) {
            if (!self::setVersion(self::$code_ver)) return false;
            self::$current_ver = self::$code_ver;
        }

        // Clear caches, update the configuration options, delete old files.
        Cache::clear();
        self::updateConfig();
        Gateway::UpgradeAll(self::$current_ver);
        self::removeOldFiles();
        CTL_clearCache();   // clear cache to ensure CSS updates come through
        SHOP_log("Successfully updated the {$_SHOP_CONF['pi_display_name']} Plugin", SHOP_LOG_INFO);
        // Set a message in the session to replace the "has not been upgraded" message
        SHOP_setMsg("Shop Plugin has been updated to " . self::$current_ver, 'info', 1);
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
    public static function doUpgradeSql($version, $ignore_error = false)
    {
        global $_TABLES, $_SHOP_CONF, $SHOP_UPGRADE, $_DB_dbms, $_VARS;

        // If no sql statements passed in, return success
        if (
            !isset($SHOP_UPGRADE[$version]) ||
            !is_array($SHOP_UPGRADE[$version])
        ) {
            return true;
        }

        // Figure out if we need to change MyISAM to InnoDB in the statements.
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
                // If using InnoDB, change the Engine in the statement.
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
    public static function setVersion($ver)
    {
        global $_TABLES, $_SHOP_CONF, $_PLUGIN_INFO;

        // now update the current version number.
        $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '$ver',
            pi_gl_version = '{$_SHOP_CONF['gl_version']}',
            pi_homepage = '{$_SHOP_CONF['pi_url']}'
            WHERE pi_name = '" . self::$pi_name . "'";

        $res = DB_query($sql, 1);
        if (DB_error()) {
            SHOP_log("Error updating the {$_SHOP_CONF['pi_display_name']} Plugin version", SHOP_LOG_INFO);
            return false;
        } else {
            SHOP_log("{$_SHOP_CONF['pi_display_name']} version set to $ver", SHOP_LOG_INFO);
            // Set in-memory config vars to avoid tripping SHOP_isMinVersion();
            $_SHOP_CONF['pi_version'] = $ver;
            $_PLUGIN_INFO[self::$pi_name]['pi_version'] = $ver;
            return true;
        }
    }


    /**
     * Update the plugin configuration
     */
    public static function updateConfig()
    {
        global $shopConfigData;
        USES_lib_install();

        require_once __DIR__ . '/../../install_defaults.php';
        _update_config('shop', $shopConfigData);
    }

    /**
     * Remove a file, or recursively remove a directory.
     *
     * @param   string  $dir    Directory name
     */
    public static function delPath($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . '/' . $object)) {
                        self::delPath($dir . '/' . $object);
                        @rmdir($dir . '/' . $object);
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
     * Remove deprecated files.
     * Errors in unlink() and rmdir() are ignored.
     * @todo: the arrays should probably be moved to version classes.
     */
    public static function removeOldFiles()
    {
        global $_CONF;

        $paths = array(
            // private/plugins/shop
            SHOP_PI_PATH => array(
                // 0.7.1
                'shop_functions.inc.php',
                // 1.0.0
                'classes/ProductImage.class.php',
                'classes/Attribute.class.php',
                'classes/AttributeGroup.class.php',
                'classes/UserInfo.class.php',
                'templates/attribute_form.thtml',
                // 1.3.0
                'vendor',
                'Autoload.class.php',
                'language/english.php',
                'language/german.php',
                'language/german_formal.php',
                'classes/CacheDB.class.php',
                'classes/DBO.class.php',
                'classes/Webhooks',
                // 1.3.x    Future, these may be needed for a bit
                //'classes/ipn',
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
                self::delPath("$path/$file");
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
    public static function tableHasColumn($table, $col_name)
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
    public static function columnType($table, $col_name)
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
    public static function tableHasIndex($table, $idx_name)
    {
        global $_TABLES;

        $sql = "SHOW INDEX FROM {$_TABLES[$table]}
            WHERE key_name = '" . DB_escapeString($idx_name) . "'";
        $res = DB_query($sql);
        return DB_numRows($res);
    }

}
