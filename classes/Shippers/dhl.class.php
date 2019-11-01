<?php
/**
 * DHL shipper class.
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

class dhl extends \Shop\Shipper
{
    /**
     * Get the shipper's name for display.
     *
     * @return  string  Shipper name
     */
    public static function getCarrierName()
    {
        return 'DHL Worldwide';
    }


    /**
     * Get the package tracking URL for a given tracking number.
     *
     * @return  string  Package tracing URL
     */
    public function _getTrackingUrl($track_num)
    {
        return "https://www.logistics.dhl/us-en/home/tracking/tracking-freight.html?submit=1&tracking-id={$track_num}";
    }
}

?>
