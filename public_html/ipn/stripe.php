<?php
/**
 * IPN processor for Stripe notifications.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v0.7.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once '../../lib-common.php';

$ipn = \Shop\IPN::getInstance('stripe');
if ($ipn) {
    $ipn->Process();
} else {
    SHOP_log('Stripe IPN processor not found');
}
http_response_code(200);

?>
