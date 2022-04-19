<?php
/**
 * English language strings for the Terms payment gateway.
 *
 * @author     Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package    shop
 * @version    v1.3.0
 * @since       v1.3.0
 * @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

$LANG_SHOP_gateway = array(
    'gw_dscp' => 'Net Terms',
    'gw_instr'  => 'The Net Terms gateway uses an actual gateway to submit online invoices.',
    'invoice_created' => 'An invoice has been created for your order and will be emailed to you.',
    'invoice_error' => 'There was an error creating your invoice.',
    'after_inv_status' => 'Order Status after Invoicing',

    'hlp_gateway' => 'Select the payment gateway which will be used to process invoices.',
    'hlp_net_days' => 'Enter the number of days for terms, e.g. &quot;30&quot; for &quot;Net 30 Days&quot;.',
    'hlp_after_inv_status' => 'Set the order to this status after an invoice is successfully created. Virtual-only orders will be set to &quot;shipped&quot;.',
    'config_instr' => 'The access control for this gateway supercedes that of the underlying Gateway',
);
?>
