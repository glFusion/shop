<?php
/**
 * Class to cache DB and web lookup results.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class for Shop Cache.
 * @package shop
 */
class Cache
{
    /** Base tag added to all cache item IDs.
     * @const string */
    const TAG = 'shop';

    /** Minimum glFusion version that supports caching.
     * @const string */
    const MIN_GVERSION = '2.0.0';

    /**
     * Update the cache.
     * Adds an array of tags including the plugin name.
     *
     * @param   string  $key    Item key
     * @param   mixed   $data   Data, typically an array
     * @param   mixed   $tag    Tag, or array of tags
     * @param   integer $cache_mins Cache minutes
     * @return  boolean     True on success, False on error
     */
    public static function set($key, $data, $tag='', $cache_mins=1440)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES;
            $key = DB_escapeString($key);
            $data = DB_escapeString(@serialize($data));
            if (is_array($tag)) {
                $tag = implode('|', $tag);
            }
            $tags = DB_escapeString($tag);
            $exp = time() + ($cache_mins * 60);
            $sql = "INSERT IGNORE INTO {$_TABLES['shop.cache']} SET
                cache_key = '$key',
                expires = $exp,
                data = '$data',
                tags = '$tag'
                ON DUPLICATE KEY UPDATE
                    expires = $exp,
                    data = '$data',
                    tags = '$tags'";
            DB_query($sql);
            return;
        }

        $ttl = (int)$cache_mins * 60;   // convert to seconds
        // Always make sure the base tag is included
        $tags = array(self::TAG);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        $key = self::makeKey($key);
        return \glFusion\Cache\Cache::getInstance()
            ->set($key, $data, $tags, $ttl);
    }


    /**
     * Delete a single item from the cache by key.
     *
     * @param   string  $key    Base key, e.g. item ID
     * @return  boolean     True on success, False on failure
     */
    public static function delete($key)
    {
        $key = self::makeKey($key);
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES;
            DB_delete($_TABLES['shop.cache'], 'key', DB_escapeString($key));
            return;
        }
        return \glFusion\Cache\Cache::getInstance()->delete($key);
    }


    /**
     * Completely clear the cache.
     * Called after upgrade and during plugin removal.
     *
     * @param   array   $tag    Optional array of tags, base tag used if undefined
     * @return  boolean     True on success, False on error
     */
    public static function clear($tag = array())
    {
        $tags = array(self::TAG);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES;
            $wheres = array();
            foreach ($tags as $tag) {
                $wheres[] = "tags LIKE '%" . DB_escapeString($tag) . "%'";
            }
            $where = implode(' OR ', $wheres);
            $sql = "DELETE FROM {$_TABLES['shop.cache']} WHERE ($where);";
            DB_query($sql);
            return;
        }

        return \glFusion\Cache\Cache::getInstance()->deleteItemsByTagsAll($tags);
    }


    /**
     * Create a unique cache key.
     * Intended for internal use, but public in case it is needed.
     *
     * @param   string  $key    Base key, e.g. Item ID
     * @return  string          Encoded key string to use as a cache ID
     */
    public static function makeKey($key)
    {
        // Just generate a simple string key
        return self::TAG . '_' . $key;
    }


    /**
     * Get a specific item from cache.
     *
     * @param   string  $key    Key to retrieve
     * @return  mixed       Value of key, or NULL if not found
     */
    public static function get($key)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES;
            $key = DB_escapeString($key);
            $exp = time();
            $data = DB_getItem(
                $_TABLES['shop.cache'],
                'data',
                "cache_key = '$key' AND expires >= $exp"
            );
            if ($data) {
                $data = @unserialize($data);
            }
            if (!$data) {
                $data = NULL;   // convert false to NULL for consistency
            }
            return $data;
        }
        $key = self::makeKey($key);
        if (\glFusion\Cache\Cache::getInstance()->has($key)) {
            return \glFusion\Cache\Cache::getInstance()->get($key);
        } else {
            return NULL;
        }
    }


    /**
     * Wrapper function to remove an order and its related items from cache.
     *
     * @param   string  $order_id   ID of order to remove
     */
    public static function deleteOrder($order_id)
    {
        self::delete('order_' . $order_id);
        self::delete('items_order_' . $order_id);
        self::delete('shipping_order_' . $order_id);
    }

    /**
     * Expire the general cache.
     * Used only for glFusion < 2.0.0.
     */
    public static function expire()
    {
        global $_TABLES;

        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            $sql = "DELETE FROM {$_TABLES['shop.cache']}
                WHERE expires < " . time();
            DB_query($sql);
        }
    }

}   // class Shop\Cache

?>
