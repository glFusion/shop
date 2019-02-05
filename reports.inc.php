<?php
/**
 * Reports available for the Paypal plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016 Lee Garner <lee@leegarner.com>
 * @package     paypal
 * @version     v0.5.8
 * @since       v0.5.8
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}

/**
 * Present a list of available reports.
 *
 * @return  string  HTML for report selection list
 */
function PAYPAL_reportsList()
{
    global $LANG_PP;

    foreach ($LANG_PP['reports_avail'] as $key => $descrip) {
        $retval .= '<div><a href="' . PAYPAL_ADMIN_URL .
            '/index.php?configreport=' . $key . '">' . $descrip . '</a></div>';
    }
    return $retval;
}

?>
