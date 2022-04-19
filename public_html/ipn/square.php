<?php
/**
 * Webhook handler for Square. Included as IPN endpoint for compatibility
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

/** Import core glFusion functions */
require_once '../../lib-common.php';

// Get the complete IPN message prior to any processing
SHOP_log("Received Hook:", SHOP_LOG_DEBUG);
$json = file_get_contents('php://input');
SHOP_log("INPUT: $json", SHOP_LOG_DEBUG);
/*COM_errorLog("POST: " . var_export($_POST,true));
COM_errorLog("GET: " . var_export($_GET,true));
COM_errorLog("HEADERS: " . var_export($_SERVER,true));*/
$WH = Shop\Webhook::getInstance('square');
if ($WH->Verify()) {
    $WH->Dispatch();
}
exit;
