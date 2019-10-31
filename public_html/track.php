<?php
/**
 * Public page to show shipment tracking information from carrier APIs.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion functions */
require_once '../lib-common.php';

// Get the shipper code and tracking number.
// Check that both are provided.
$shipper = SHOP_getVar($_GET, 'shipper');
$tracking_num = SHOP_getVar($_GET, 'tracking');
if (empty($shipper) || empty($tracking_num)) {
    echo "No tracking information found";
    exit;
}

$cls = 'Shop\\Shippers\\' . $shipper;
if (class_exists($cls)) {
    $Shipper = new $cls;
    $tracking = $Shipper->getTracking($tracking_num);
    echo $tracking->getDisplay();
} else {
    echo "No tracking information found";
}
?>
