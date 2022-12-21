<?php
/**
 * Create a catalog feed.
 * Requires anonymous access to the Shop and only displays publicly-available items.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Require core glFusion code */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !isset($_SHOP_CONF) ||
    !in_array($_SHOP_CONF['pi_name'], $_PLUGINS) ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

COM_setArgNames(array('type'));
if (isset($_GET['type'])) {
    $type = $_GET['type'];
} else {
    $type = COM_getArgument('type');
}

$Feed = new Shop\Feeds\Catalog($type);
echo $Feed->Render();
exit;
