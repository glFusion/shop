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
use Shop\Log;

// Get the complete IPN message prior to any processing
Log::write('shop_system', Log::DEBUG, "Received Hook:");
$json = file_get_contents('php://input');
Log::write('shop_system', Log::DEBUG, "INPUT: $json");
$WH = Shop\Webhook::getInstance('square');
if ($WH->Verify()) {
    $WH->Dispatch();
}
exit;
