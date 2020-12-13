<?php
/**
 * Class to handle custom order information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class for product view type.
 * @package shop
 */
class URL
{
    /** Array of key=>URL values.
     * @var array */
    private static $urls = array(
        'addresses' => '/account.php?addresses',
        'cart_addresses' => '/cart.php?addresses',
    );


    /**
     * Get the full URL for a keyed value, or a default if not found.
     *
     * @param   string  $key    Key into the private $urls array
     * @param   string  $default    Default URL to return if key not found
     * @return  string      URL from the array or default
     */
    public static function get($key, $default='')
    {
        if (isset(self::$urls[$key])) {
            return SHOP_URL . self::$urls[$key];
        } else {
            return SHOP_URL . $default;
        }
    }

}
