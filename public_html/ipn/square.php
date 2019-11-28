<?php
/**
 * IPN processor for Square notifications.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

// Get the complete IPN message prior to any processing
SHOP_log('Recieved Square IPN: ' . var_export($_GET, true), SHOP_LOG_DEBUG);

// Process IPN request
$ipn = \Shop\IPN::getInstance('square', $_GET);
if ($ipn->Process()) {
    echo COM_refresh(SHOP_URL . '/index.php?thanks=square');
} else {
    echo COM_refresh(SHOP_URL . '/index.php?msg=8');
}

?>
