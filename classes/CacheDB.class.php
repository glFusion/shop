<?php
/**
 * Class to implement a simple database cache.
 * Used for simple temporary data storage for certain data types. Intended to
 * cache API responses for performance and cost savings, so it does not use
 * the standard Cache class for glfusion 2.0.0+.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.0
 * @since       v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Cache informataion in a DB table.
 * @package shop
 */
class CacheDB
{
    /**
     * Read data from cache.
     *
     * @param   string  $key    Cache key of item
     * @return  object|null     Tracking object, NULL if not found
     */
    public static function get($key)
    {
        global $_TABLES;

        $key = DB_escapeString($key);
        $exp = time();
        $data = DB_getItem(
            $_TABLES['shop.cache'],
            'data',
            "cache_key = '$key' AND expires >= $exp"
        );
        if ($data !== NULL) {
            $data = @unserialize($data);
        }
        return $data;
    }


    /**
     * Set the current Tracking object into cache.
     *
     * @param   string  $key    Cache key of item
     * @param   string  $data   Data to store
     * @param   array   $tags   Tags to associate with the data
     * @param   integer $exp_seconds    Seconds befor expiration
     */
    public function set($key, $data, $exp_seconds = 86400)
    {
        global $_TABLES;

        $key = DB_escapeString($key);
        $data = DB_escapeString(serialize($data));
        $exp = time() + $exp_seconds;
        $sql = "INSERT IGNORE INTO {$_TABLES['shop.cache']} SET
            cache_key = '$key',
            expires = $exp,
            data = '$data'
            ON DUPLICATE KEY UPDATE
                expires = $exp,
                data = '$data'";
            DB_query($sql);
    }


    /**
     * Clear the entire cache table.
     */
    public static function clear()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['shop.cache']}");
    }

}

?>
