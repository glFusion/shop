<?php
/**
 * Webhook handler for Paypal.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 20192020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

// Get the complete IPN message prior to any processing
SHOP_log("Recieved Hook:", SHOP_LOG_DEBUG);
$json = file_get_contents('php://input');
//$json = @json_decode($json,true);
//COM_errorLog($json);
SHOP_log(var_export($json,true), SHOP_LOG_DEBUG);
//exit;
SHOP_log("HEADERS: " . var_export($_SERVER,true), SHOP_LOG_DEBUG);
$WH = new Shop\Webhooks\paypal($json);
$WH->setHeaders();
if ($WH->Verify()) {
    $WH->Dispatch();
}
exit;
?>
