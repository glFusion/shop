<?php
/**
 * US Postal Service shipper class to get shipping rates.
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

class fedex extends \Shop\Shipper
{
    /**
     * Get the shipper's name for display.
     *
     * @return  string  Shipper name
     */
    public static function getCarrierName()
    {
        return 'FedEx';
    }


    /**
     * Get the package tracking URL for a given tracking number.
     *
     * @return  string  Package tracing URL
     */
    public function getTrackingUrl($track_num)
    {
        return 'https://www.fedex.com/apps/fedextrack/index.html?trackingnumbers=' . urlencode($track_num);
    }
}

?>
