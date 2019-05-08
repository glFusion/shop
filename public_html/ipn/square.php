<?php
/**
 * IPN processor for Square notifications.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.6.0
 * @since       v0.6.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

// Get the complete IPN message prior to any processing
SHOP_log('Recieved Square IPN: ' . print_r($_GET, true), SHOP_LOG_DEBUG);

// Process IPN request
$ipn = \Shop\IPN::getInstance('square', $_GET);
if ($ipn->Process()) {
    echo COM_refresh(SHOP_URL . '/index.php?thanks=square');
} else {
    echo COM_refresh(SHOP_URL . '/index.php?msg=8');
}

?>
