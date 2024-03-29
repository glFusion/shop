<?php
/**
 * Interface to add a cart item from another plugin link or form.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions. */
require_once '../lib-common.php';
use Shop\Log;
use Shop\Product;
use Shop\Models\DataArray;

$Request = Shop\Models\Request::getInstance();

// Figure out where to redirect after adding to cart.
// Look for a parameter, then the referrer page, and finally back to
// the Shop homepage if nothing else is defined.
$ret_url = $Request->getString('ret', $_SERVER['HTTP_REFERER']);
if (empty($ret_url)) {
    $ret_url = SHOP_URL . '/index.php';
}

// Get the parameters from the form or URL.
// Item ID is required so check that it's present, and log an error and redirect
// if not supplied.
$item_number = $Request->getString('item');
if (empty($item_number)) {
    COM_setMsg('Missing item number', 'error');
    echo COM_refresh($ret_url);
}

// Set other fixed parameters.
$price = $Request->getFloat('p');
$sku = $Request->getString('sku');
$qty = $Request->getInt('q');
$shipping = $Request->getFloat('ship');
$shipping_units = $Request->getFloat('su');
$taxable = $Request->getInt('tax');
$uid = (int)$_USER['uid'];
$dscp = $Request->getString('d');
$Product = Product::getByID($item_number);

// Get product options. May come from a form field, or from GET vars.
// Form field will have an "options" variable with "o" for text and "p" for price.
// Parameters will have only one "p" for price and multiple "o" for text.
$reqOptions = $Request->getArray('options', NULL);
if (is_array($reqOptions)) {
    foreach ($reqOptions as $option) {
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
} elseif (isset($Request['o']) && is_array($Request['o'])) {
        // Received 'o' arguments via URL
    foreach ($Request->getArray('o') as $name=>$val) {
        $options[$name] = $val;
    }
}

$Cart = Shop\Cart::getInstance();
$args = new DataArray(array(
    'item_number'   => $item_number,     // isset ensured above
    'item_name'     => $sku,
    'short_dscp'    => $Request->getString('d'),
    'quantity'      => $qty,
    'options'       => array(),
    'extras'        => array(
        'special' => $Request->getArray('e'),
    ),
    'options_text'  => $options,
    'taxable'       => $taxable ? 1 : 0,
    'shipping'      => $shipping,
    'shipping_units' => $shipping_units,
) );
if ($price !== NULL) {
    // Only override the price if supplied, do not use "0"
    $args['price'] = $price;
    $args['override'] = true;
}
$new_qty = $Cart->addItem($args);
Log::write('shop_system', Log::DEBUG, "Adding $item_number, qty $new_qty");
$msg = $LANG_SHOP['msg_item_added'];
if ($new_qty === false) {
    $msg = $LANG_SHOP['out_of_stock'];
} elseif ($new_qty < $qty) {
    // This really only handles changes to the initial qty.
    $msg .= ' ' . $LANG_SHOP['qty_adjusted'];
}
$Cart->saveIfTainted();
COM_setMsg($msg);
echo COM_refresh($ret_url);
