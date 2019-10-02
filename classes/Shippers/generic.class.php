<?php
/**
 * Generic shipper class for user-defined shippers.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Shippers;

class generic extends \Shop\Shipper
{
    /**
     * Get the package tracking URL for a given tracking number.
     * This class just returns an empty string.
     *
     * @return  string  Empty tracking URL
     */
    public static function getTrackingUrl($track_num)
    {
        return '';
    }

}
?>
