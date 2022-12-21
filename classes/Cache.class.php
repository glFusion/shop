<?php
/**
 * Class to cache DB and web lookup results.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
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


    /**
     * Update the cache.
     * Adds an array of tags including the plugin name.
     *
     * @param   string  $key    Item key
     * @param   mixed   $data   Data, typically an array
     * @param   mixed   $tag    Tag, or array of tags
     * @param   integer $cache_mins Cache minutes, default = 24 hours
     * @return  boolean     True on success, False on error
     */
    public static function set($key, $data, $tags='', $cache_mins=1440)
    {
        if (!is_array($tags)) {
            $tags = array($tags);
        }
        $ttl = (int)$cache_mins * 60;   // convert to seconds

        // Always make sure the base tag is included
        $tags[] = self::TAG;
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
        return \glFusion\Cache\Cache::getInstance()->delete($key);
    }


    /**
     * Completely clear the cache.
     * Called after upgrade and during plugin removal.
     *
     * @param   array   $tag    Optional array of tags, base tag used if undefined
     * @return  boolean     True on success, False on error
     */
    public static function clear($tags = array())
    {
        if (!is_array($tags)) {
            $tags = array($tags);
        }
        if (!empty($tag)) {
            if (!is_array($tags)) $tags = array($tags);
        }
        $tags[] = self::TAG;
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

}
