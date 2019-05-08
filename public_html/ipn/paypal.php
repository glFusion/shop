<?php
/**
 * IPN processor for Shop notifications.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2018 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

// Get the complete IPN message prior to any processing
SHOP_log("Recieved IPN:", SHOP_LOG_DEBUG);
SHOP_log(var_export($_POST, true), SHOP_LOG_DEBUG);

// Process IPN request
$ipn = \Shop\IPN::getInstance('paypal', $_POST);
$ipn->Process();

// Finished (this isn't necessary...but heck...why not?)
echo "Thanks";

?>
