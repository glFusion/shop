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

// Debug logging
Log::debug("Got IPN GET: " . var_export($_GET, true));
Log::debug("Got IPN POST: " . var_export($_POST, true));
Log::debug("Got php:://input: " . var_export(@file_get_contents('php://input'), true));

// Process IPN request
$IPN = \Shop\IPN::getInstance('paypal', $_POST);
$IPN->Process();

// Finished (this isn't necessary...but heck...why not?)
echo "Thanks";
