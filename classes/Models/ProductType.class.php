<?php
/**
 * Class to manage product types - Physical, Virtual, etc.
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
namespace Shop\Models;


/**
 * Class for product attribute groups.
 * @package shop
 */
class ProductType
{
    /** Physical item, may require shipping.
     */
    public const PHYSICAL = 1;

    /** Downloadable product, no shipping. Must have a file attached.
     */
    public const DOWNLOAD = 2;

    /** Other virtual product. Services, etc.
     */
    public const VIRTUAL = 4;

    /** Gift card or coupon.
     */
    public const COUPON = 8;

    /** Plugin product, not part of the Shop catalog.
     */
    public const PLUGIN = 16;


    public static function isPhysical($type)
    {
        return $type & self::PHYSICAL;
    }

    public static function isDownload($type)
    {
        return $type & self::DOWNLOAD;
    }

    public static function isVirtual($type)
    {
        return $type & self::VIRTUAL;
    }

    public static function isCoupon($type)
    {
        return $type & self::COUPON;
    }

    public static function isPlugin($type)
    {
        return $type & self::PLUGIN;
    }

}
