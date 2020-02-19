<?php
/**
 * Web service functions for the Shop plugin.
 * This is used to supply Shop functions to other plugins.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
 * Create the payment buttons for an external item.
 * Creates the requested buy_now button type and, if requested,
 * an add_cart button.
 *
 * All gateways that have the 'external' service enabled as well as the
 * requested button type will provide a button.
 *
 * $args['btn_type'] can be empty or not set, to create only an Add to Cart
 * button.  $args['add_cart'] must still be set in this case.  If neither
 * button type is requested, an empty array is returned.
 *
 * Provided $args should include at least:
 *      'item_number', 'item_name', 'price', 'quantity', and 'item_type'
 * $args['btn_type'] should reflect the type of immediate-purchase button
 * desired.  $args['add_cart'] simply needs to be set to get an add-to-cart
 * button.
 *
 * @uses    Gateway::ExternalButton()
 * @param   array   $args       Array of item information
 * @param   array   $output     Pointer to output array
 * @param   array   $svc_msg    Unused
 * @return  integer             Status code
 */
function service_genButton_shop($args, &$output, &$svc_msg)
{
    global $_CONF, $_SHOP_CONF;

    $Cart = Shop\Cart::getInstance();
    $btn_type = isset($args['btn_type']) ? $args['btn_type'] : 'buy_now';
    $output = array();

    // Create the immediate purchase button, if requested.  As soon as a
    // gateway supplies the requested button type, break from the loop.
    if (!empty($btn_type)) {
        foreach (Shop\Gateway::getall() as $gw) {
            if ($gw->Supports('external') && $gw->Supports($btn_type)) {
                //$output[] = $gw->ExternalButton($args, $btn_type);
                $P = Shop\Product::getByID($args['item_number']);
                $output[] = $gw->ProductButton($P);
            }
        }
    }

    // Now create an add-to-cart button, if requested.
    if (isset($args['add_cart']) && $args['add_cart'] && $_SHOP_CONF['ena_cart'] == 1) {
        if (!isset($args['item_type'])) $args['item_type'] = SHOP_PROD_VIRTUAL;
        $btn_cls = 'orange';
        $btn_disabled = '';
        $unique = isset($args['unique']) ? 1 : 0;
        if ($unique) {
            // If items may only be added to the cart once, check that
            // this one isn't already there
            if ($Cart->Contains($args['item_number']) !== false) {
                $btn_cls = 'grey';
                $btn_disabled = 'disabled="disabled"';
            }
        }
        $T = SHOP_getTemplate('btn_add_cart', 'cart', 'buttons');
        $T->set_var(array(
            'item_name'     => $args['item_name'],
            'item_number'   => $args['item_number'],
            'short_description' => $args['short_description'],
            'amount'        => $args['amount'],
            'pi_url'        => SHOP_URL,
            'item_type'     => $args['item_type'],
            'have_tax'      => isset($args['tax']) ? 'true' : '',
            'tax'           => isset($args['tax']) ? $args['tax'] : 0,
            'quantity'      => isset($args['quantity']) ? $args['quantity'] : '',
            '_ret_url'      => isset($args['_ret_url']) ? $args['_ret_url'] : '',
            '_unique'       => $unique,
            'frm_id'        => md5($args['item_name'] . rand()),
            'btn_cls'       => $btn_cls,
            'btn_disabled'  => $btn_disabled,
            'nonce'         => $Cart->makeNonce($args['item_number'] . $args['item_name']),
        ) );
        $output['add_cart'] = $T->parse('', 'cart');
    }
    return PLG_RET_OK;
}


/**
 * Return the configured currency.
 * This is service function to allow other plugins to find out what
 * currency we accept. Sets `$output` to the currency string.
 *
 * @param   array   $args       Array of args (not used)
 * @param   string  $output     Variable to receive output
 * @param   string  $svc_msg    Not used
 * @return  integer     PLG_RET_OK value
 */
function service_getCurrency_shop($args, &$output, &$svc_msg)
{
    global $_SHOP_CONF;

    $output = $_SHOP_CONF['currency'];
    return PLG_RET_OK;
}


/**
 * Return the configured currency.
 * This is an API function to allow other plugins to find out what
 * currency we accept. Sets `$output` to the currency string.
 *
 * @return  string      Our configured currency code.
 */
function plugin_getCurrency_shop()
{
    global $_SHOP_CONF;
    return $_SHOP_CONF['currency'];
}


/**
 * API function to return the url to a Shop item.
 * This returns the url to a Shop-controlled item, such as the
 * IPN transaction data.  This is meant to provide a backlink for other
 * plugins to use with their products.
 *
 * @param   array   $args       Array of item information, at least 'type'
 * @param   array   &$output    Pointer to output array
 * @param   array   &$svc_msg   Unused
 * @return  integer             Status code
 */
function service_getUrl_shop($args, &$output, &$svc_msg)
{
    if (!is_array($args)) {
        $args = array('type' => $args);
    }

    $type = isset($args['type']) ? $args['type'] : '';
    $url = '';

    switch ($type) {
    case 'ipn':
        $id = isset($args['id']) ? $args['id'] : '';
        if ($id != '') {
            $url = Shop\IPN::getDetailUrl($id);
        }
        break;
    case 'checkout':
    case 'cart':
        $url = SHOP_URL . '/cart.php';
        break;
    }

    if (!empty($url)) {
        $output = $url;
        return PLG_RET_OK;
    } else {
        $output = '';
        return PLG_RET_ERROR;
    }
}


/**
 * Allow a plugin to push an item into the cart.
 * If $args['unique'] is not True, the item will be added to the cart
 * or the quantity updated if the item already exists. Setting the unique
 * flag prevents the item from being updated at all if it exists in the cart.
 *
 * @param   array   $args   Array of item information
 * @param   mixed   &$output    Output data
 * @param   mixed   &$svc_msg   Service message
 * @return  integer     Status code
 */
function service_addCartItem_shop($args, &$output, &$svc_msg)
{
    if (!is_array($args) || !isset($args['item_number']) || empty($args['item_number'])) {
        return PLG_RET_ERROR;
    }

    $Cart = Shop\Cart::getInstance();
    $price = 0;
    foreach (array('amount', 'price') as $s) {
        if (isset($args[$s])) {
            $price = $args[$s];
            break;
        }
    }
    $dscp = '';
    foreach (array('short_description', 'description', 'dscp') as $s) {
        if (isset($args[$s])) {
            $dscp = $args[$s];
            break;
        }
    }
    $item_number = '';
    foreach (array('item_number', 'product_id', 'item_id') as $s) {
        if (isset($args[$s])) {
            $item_number = $args[$s];
            break;
        }
    }
    if ($item_number == '') {
        $svc_msg = 'Missing item number';
        return PLG_RET_ERROR;
    }

    // Force the price if requested by the caller
    $override = isset($args['override']) && $args['override'] ? true : false;
    $cart_args = array(
        'item_number'   => $item_number,
        'quantity'      => SHOP_getVar($args, 'quantity', 'float', 1),
        'item_name'     => SHOP_getVar($args, 'item_name', 'string'),
        'price'         => $price,
        'short_description' => $dscp,
        'options'       => SHOP_getVar($args, 'options', 'array'),
        'extras'        => SHOP_getVar($args, 'extras', 'array'),
        'override'      => $override,
        'uid'           => SHOP_getVar($args, 'uid', 'int', 1),
    );
    if (isset($args['tax'])) {      // tax element not set at all if not present
        $cart_args['tax'] = $args['tax'];
    }

    // If the "unique" flag is present, then only update specific elements
    // included in the "updates" array. If there are no specific updates, then
    // do nothing.
    if (
        SHOP_getVar($args, 'unique', 'boolean', false) &&
        $Cart->Contains($item_number) !== false
    ) {
        // If the item exists, don't add it, but check if there's an update
        if (isset($args['update']) && is_array($args['update'])) {
            // Collect the updated field=>value pairs to send to updateItem()
            $updates = array();
            foreach ($args['update'] as $fld) {
                $updates[$fld] = $args[$fld];
            }
            $Cart->updateItem($item_number, $updates);
        }
    } else {
        $Cart->addItem($cart_args);
    }
    return PLG_RET_OK;
}


/**
 * Return a simple "checkout" button.
 * Take optional "text" and "color" arguments.
 *
 * @param   array   $args       Array of options.
 * @param   mixed   &$output    Output data
 * @param   mixed   &$svc_msg   Service message
 * @return  integer     Status code
 */
function service_btnCheckout_shop($args, &$output, &$svc_msg)
{
    global $LANG_SHOP;

    if (!is_array($args)) $args = array($args);
    $text = isset($args['text']) ? $args['text'] : $LANG_SHOP['checkout'];
    $color = isset($args['color']) ? $args['color'] : 'green';
    $output = '<a href="' . SHOP_URL . '/index.php?checkout=x"><button type="button" id="ppcheckout" class="shopButton ' . $color . '">'
        . $text . '</button></a>';
    return PLG_RET_OK;
}


/**
 * Get a formatted amount according to the configured currency.
 * Accepts an array of "amount" => value, or single value as first argument.
 * Sets $output to the formatted amount.
 *
 * @param   array   $args   Array of "amount" => amount value
 * @param   mixed   &$output    Output data
 * @param   mixed   &$svc_msg   Service message
 * @return  integer     Status code
 */
function service_formatAmount_shop($args, &$output, &$svc_msg)
{
    global $_SHOP_CONF;

    if (is_array($args)) {
        $amount = SHOP_getVar($args, 'amount', 'float');
        $symbol = SHOP_getVar($args, 'symbol', 'boolean', true);
    } else {
        $amount = (float)$args;
        $symbol = true;
    }
    $output = Shop\Currency::getInstance()->Format($amount, $symbol);
    return PLG_RET_OK;
}


/**
 * Send gift cards to one or more site members.
 * This is to allow other plugins to send coupons as rewards for filling out
 * a quiz, for instance.
 * Args should contain:
 *   - amount - required
 *   - members - array of user IDs, and/or
 *   - group_id - glFusion group ID
 *   - expires - Expiration date, or number of days
 *   - notify - True to send notification, False or missing to not
 *
 * @param   array   $args   Array of arguments - amount, users, expiration
 * @param   mixed   &$output    Output data
 * @param   mixed   &$svc_msg   Service message
 * @return  integer     Status code
 */
function service_sendcards_shop($args, &$output, &$svc_msg)
{
    global $_TABLES, $_SHOP_CONF;

    $output = array();
    $svc_msg = array();
    if (!$_SHOP_CONF['gc_enabled'] || !$_SHOP_CONF['shop_enabled']) {
        $svc_msg[] = 'Shop or Gift Cards not enabled';
        return PLG_RET_PERMISSION_DENIED;
    }

    $amt = SHOP_getVar($args, 'amount', 'float');
    $uids = SHOP_getVar($args, 'members', 'mixed');
    $gid = SHOP_getVar($args, 'group_id', 'int');
    $exp = SHOP_getVar($args, 'expires', 'string');
    $msg = SHOP_getVar($args, 'message', 'string');
    $notify = SHOP_getVar($args, 'notify', 'boolean', false);
    if (is_string($uids)) {
        $uids = explode('|', $uids);
    }
    if (is_numeric($exp)) {
        $exp = (int)$exp;
        $dt = new \Date('+' . $exp. ' days', $_CONF['timezone']);
        $exp = $dt->format('Y-m-d', true);
    }
    if ($gid > 0) {
        $sql = "SELECT ug_uid FROM {$_TABLES['group_assignments']}
                WHERE ug_main_grp_id = $gid AND ug_uid > 1";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $uids[] = $A['ug_uid'];
        }
    }
    if (empty($uids)) {
        $svc_msg[] = $LANG_SHOP['err_gc_nousers'];
    }
    if ($amt < .01) {
        $svc_msg[] = $LANG_SHOP['err_gc_amt'];
    }
    if (empty($svc_msg)) {
        $uids = array_filter(array_unique($uids));
        foreach ($uids as $uid) {
            $code = Shop\Products\Coupon::Purchase($amt, $uid, $exp);
            $email = DB_getItem($_TABLES['users'], 'email', "uid = $uid");
            $name = COM_getDisplayName($uid);
            $output[$uid] = array(
                'code' => $code,
                'email' => $email,
                'link' => Shop\Products\Coupon::redemptionUrl($code),
            );
            if ($notify && !empty($email)) {
                Shop\Products\Coupon::Notify($code, $email, $amt, '', $msg, $exp, $name);
            }
        }
        return PLG_RET_OK;
    } else {
        return PLG_RET_ERROR;
    }
}


/**
 * Return a formatted amount according to the configured currency.
 * Accepts an array of "amount" => value, or single value as first argument.
 *
 * @param   float   $amount     Amount to format
 * @return  string      Formatted amount according to the currency in use
 */
function plugin_formatAmount_shop($amount)
{
    return Shop\Currency::getInstance()->Format((float)$amount);
}


if (
    $_SHOP_CONF['enable_svc_funcs'] &&
    !function_exists('service_genButton_paypal')
) {
    /**
     * Compatibility function for plugins using "paypal".
     *
     * @param   array   $args       Array of arguments
     * @param   mixed   $output     Holder for function output
     * @param   mixed   $svc_msg    Unused
     * @return  integer     Status code from service function
     */
    function service_genButton_paypal($args, &$output, &$svc_msg)
    {
        return service_genButton_shop($args, $output, $svc_msg);
    }

    /**
     * Compatibility function for plugins using "paypal".
     *
     * @param   array   $args       Array of arguments
     * @param   mixed   $output     Holder for function output
     * @param   mixed   $svc_msg    Unused
     * @return  integer     Status code from service function
     */
    function service_getCurrency_paypal($args, &$output, &$svc_msg)
    {
        return service_getCurrency_shop($args, $output, $svc_msg);
    }

    /**
     * Compatibility function for plugins using "paypal".
     *
     * @param   array   $args       Array of arguments
     * @param   mixed   $output     Holder for function output
     * @param   mixed   $svc_msg    Unused
     * @return  integer     Status code from service function
     */
    function service_getUrl_paypal($args, &$output, &$svc_msg)
    {
        return service_getUrl_shop($args, $output, $svc_msg);
    }

    /**
     * Compatibility function for plugins using "paypal".
     *
     * @param   array   $args       Array of arguments
     * @param   mixed   $output     Holder for function output
     * @param   mixed   $svc_msg    Unused
     * @return  integer     Status code from service function
     */
    function service_addCartItem_paypal($args, &$output, &$svc_msg)
    {
        return service_AddCartItem_shop($args, $output, $svc_msg);
    }

    /**
     * Compatibility function for plugins using "paypal".
     *
     * @param   array   $args       Array of arguments
     * @param   mixed   $output     Holder for function output
     * @param   mixed   $svc_msg    Unused
     * @return  integer     Status code from service function
     */
    function service_btnCheckout_paypal($args, &$output, &$svc_msg)
    {
        return service_btnCheckout_shop($args, $output, $svc_msg);
    }

    /**
     * Compatibility function for plugins using "paypal".
     *
     * @param   array   $args       Array of arguments
     * @param   mixed   $output     Holder for function output
     * @param   mixed   $svc_msg    Unused
     * @return  integer     Status code from service function
     */
    function service_formatAmount_paypal($args, &$output, &$svc_msg)
    {
        return service_formatAmount_shop($args, $output, $svc_msg);
    }

    /**
     * Normal plugin function to get a formatted amount.
     *
     * @see     plugin_formatAmount_shop()
     * @param   array   $amount     Amount to format
     * @return  string      Formatted amount
     */
    function plugin_formatAmount_paypal($amount)
    {
        return plugin_formatAmount_shop($amount);
    }

    /**
     * Compatibility function to get the configured currency.
     *
     * @see     plugin_getCurrency_shop()
     * @return  string      Currency code
     */
    function plugin_getCurrency_paypal()
    {
        return plugin_getCurrency_shop();
    }
}

?>
