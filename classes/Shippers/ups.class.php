<?php
/**
 * UPS shipper class.
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

class ups extends \Shop\Shipper
{
    /**
     * Get the package tracking URL for a given tracking number.
     *
     * @return  string  Package tracing URL
     */
    public function getTrackingUrl($track_num)
    {
        $locale = urlencode($_CONF['locale']);
        return "https://www.ups.com/track?tracknum={$track_num}/trackdetails";
    }
}

?>