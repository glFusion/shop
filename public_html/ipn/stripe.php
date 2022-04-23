<?php
/**
 * IPN processor for Stripe notifications.
 * Deprecated as of v1.3.0, included here for backwards compatibility.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 * @deprecated  v1.3.0
 */

require_once '../../lib-common.php';
use Shop\Log;

$gw_name = SHOP_getVar($_GET, '_gw');
if (!empty($gw_name)) {
    $WH = Shop\Webhook::getInstance($gw_name);
    if ($WH && $WH->Verify()) {
        $WH->Dispatch();
    } else {
        Log::write('shop_system', Log::ERROR, "Webhook verification failed for $gw_name");
    }
}

http_response_code(200);

