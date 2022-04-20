<?php
/**
 * IPN processor for Shop notifications.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';
use Shop\Log;

// Get the complete IPN message prior to any processing
Log::write('shop_system', Log::DEBUG, "Received IPN:");
Log::write('shop_system', Log::DEBUG, var_export($_POST, true));

// Process IPN request
$ipn = \Shop\IPN::getInstance('paypal', $_POST);
$ipn->Process();

// Finished (this isn't necessary...but heck...why not?)
echo "Thanks";

