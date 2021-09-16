<?php
/**
 * English language strings for the PayPal payment gateway.
 *
 * @author     Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2011-2019 Lee Garner <lee@leegarner.com>
 * @package    shop
 * @version    v0.7.0
 * @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

$LANG_SHOP_gateway = array(
'bus_prod_email' => 'Production Business E-Mail',
'bus_test_email' => 'Sandbox Business E-Mail',
'micro_prod_email' => 'Production Micro-Payment E-Mail',
'micro_test_email' => 'Sandbox Micro-Payment E-Mail',
'micro_threshold' => 'Micro-Payment Threshold',
'encrypt'       => 'Encrypt PayPal Buttons?',
'pp_cert'       => 'PayPal Public Certificate File Name',
'pp_cert_id'    => 'Your PayPal Certificate ID',
'micro_cert_id' => 'Micro-Payment Certificate ID',
'sandbox_main_cert' => 'Sandbox Certificate ID',
'sandbox_micro_cert' => 'Sandbox Micro-Payment Certificate ID',
'prv_key'       => 'Your Private Key File Name',
'pub_key'       => 'Your Public Key File Name',
'prod_url'      => 'PayPal Url - Production',
'sandbox_url'   => 'PayPal Url - Sandbox',
'test_mode'     => 'Testing (Sandbox) Mode?',
'ena_donations' => 'Enable Donations?',

'hlp_encrypt' => 'Select the buttons that are supported by this gateway. This is primarily meant to reduce clutter in blocks and product lists, where you may want only one gateway handling immediate purchases. In some cases, a gateway may not be able to handle a type of service, such as donations.',
'hlp_test_mode' => 'Check this box during testing. The Sandbox URL will be used instead of the Production URL.',
'hlp_micro_threshold' => 'Enter the threshold amount for micro-payments. Order amounts <b>below</b> this amount will be charged using your micro-payment account e-mail, orders totaling this amount or higher are charged using your regular e-mail.',
'hlp_prv_key' => 'Enter the filename for your private SSL key file. This file must be placed in your private/data/shop/keys directory and be readable by your web server. The security of your encrypted PayPal buttons depends upon this key remaining private.',
'hlp_pub_key' => 'Enter the filename for the public SSL key file that you&apos;ve provided to Paypal. This file must> be placed in your private/data/shop/keys directory.',
'hlp_ena_donations' => 'Check to enable donations via Paypal. Only certain types of organizations are eligible to receive donations. If unchecked, a normal &quot;Buy Now&quot; button is used.',
'hlp_enabled' => 'Check this box to indicate that this gateway is enabled for use.  If not checked, the PayPal gateway will not be used.',
'hlp_receiver_email' => 'This is the &quot;Regular&quot; account e-mail for PayPal. If you have only one account, this must contain it even if it is a &quot;micro-payment&quot; account.',
'hlp_micro_receiver_email' => 'This is your &quot;Micro-payment&quot; account e-mail, if you have one. If you don\'t have a micro-payment account, this should contain you regular account email.',
'hlp_endpoint' => 'Enter the PayPal URL, e.g. &quot;https://www.paypal.com&quot; (for production).',
'hlp_api_username' => 'Enter the API username associated with your application at Paypal. Required to use the Net Terms payment gateway.',
'hlp_api_password' => 'Enter the API password for your application. Required to use the Net Terms payment gateway.',
'hlp_pp_cert' => 'Enter filename for the public SSL certificate file that you\'ve downloaded from Paypal. This file must be placed in your private/data/shop/keys directory.',
'hlp_pp_cert_id' => 'Enter the certificate ID created by PayPal and assigned to your public key. This is required so that PayPal can decrypt your encrypted button data.',
'hlp_micro_cert_id' => 'If you use micro-payments, you must upload your public certificate to <b>both</b> accounts. Enter the certificate ID created by PayPal for your micro-payment account.',
);
?>
