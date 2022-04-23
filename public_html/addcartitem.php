<?php
/**
 * Interface to add a cart item from another plugin link or form.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v1.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions. */
require_once '../lib-common.php';
use Shop\Log;

// Figure out where to redirect after adding to cart.
// Look for a parameter, then the referrer page, and finally back to
// the Shop homepage if nothing else is defined.
if (isset($_POST['ret'])) {
    $ret_url = $_POST['ret'];
} elseif (isset($_GET['ret'])) {
    $ret_url = $_GET['ret'];
} else {
    $ret_url = $_SERVER['HTTP_REFERER'];
}
if (empty($ret_url)) {
    $ret_url = SHOP_URL . '/index.php';
}

// Get the parameters from the form or URL.
// Item ID is required so check that it's present, and log an error and redirect
// if not supplied.
if (isset($_POST['item'])) {
    $opts = $_POST;
} elseif (isset($_GET['item'])) {
    $opts = $_GET;
} else {
    Log::write('shop_system', Log::ERROR, "Ajax addcartitem:: Missing Item Number");
    COM_refresh($ret_url);
}

// Set other fixed parameters.
$item_number = SHOP_getVar($opts, 'item', 'string', '');   // isset ensured above
$price = SHOP_getVar($opts, 'p', 'float');      // may be NULL
$sku = SHOP_getVar($opts, 'sku', 'string', '');
$qty = SHOP_getVar($opts, 'q', 'integer', 1);
$shipping = SHOP_getVar($opts, 'ship', 'float', 0);
$shipping_units = SHOP_getVar($opts, 'su', 'float', 0);
$taxable = SHOP_getVar($opts, 'tax', 'integer', 0);
$uid = (int)$_USER['uid'];

// Get product options. May come from a form field, or from GET vars.
// Form field will have an "options" variable with "o" for text and "p" for price.
// Parameters will have only one "p" for price and multiple "o" for text.
$options = array();
if (isset($opts['options'])) {
    // Get values from form fields.
    if (!is_array($opts['options'])) {
        $opts['options'] = array($opts['options']);
    }
    foreach ($opts['options'] as $option) {
        parse_str($option, $x);
        if (isset($x['p'])) {
            // get incremental price for the option
            $price += (float)$x['p'];
        }
        if (isset($x['o']) && is_array($x['o'])) {
            // get option names and descriptions
            foreach ($x['o'] as $name=>$val) {
                $options[$name] = $val;
            }
        }
    }
} elseif (isset($opts['o']) && is_array($opts['o'])) {
    // Received 'o' arguments via URL
    foreach ($opts['o'] as $name=>$val) {
        $options[$name] = $val;
    }
}

if (empty($item_number)) {
    COM_setMsg('Missing item number', 'error');
    COM_refresh($ret_url);
}
$Cart = Shop\Cart::getInstance();

$args = array(
    'item_number'   => $item_number,     // isset ensured above
    'item_name'     => $sku,
    'short_dscp'    => SHOP_getVar($opts, 'd', 'string', ''),
    'quantity'      => $qty,
    'options'       => array(),
    'extras'        => array(
        'special' => SHOP_getVar($opts, 'e', 'array', array())
    ),
    'options_text'  => $options,
    'taxable'       => $taxable ? 1 : 0,
    'shipping'      => $shipping,
    'shipping_units' => $shipping_units,
);
if ($price !== NULL) {
    // Only override the price if supplied, do not use "0"
    $args['price'] = $price;
    $args['override'] = true;
}
$new_qty = $Cart->addItem($args);
Log::write('log_system', Log::DEBUG, "Adding $item_number, qty $new_qty");
$msg = $LANG_SHOP['msg_item_added'];
if ($new_qty === false) {
    $msg = $LANG_SHOP['out_of_stock'];
} elseif ($new_qty < $qty) {
    // This really only handles changes to the initial qty.
    $msg .= ' ' . $LANG_SHOP['qty_adjusted'];
}
$Cart->saveIfTainted();
COM_setMsg($msg);
COM_refresh($ret_url);
