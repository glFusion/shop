<?php
/**
 * Testing gateway to pass orders through the internal IPN handler.
 *
 * *NOTE* All orders passed through this gateway are automatically
 * treated as paid in full. This gateway should *NOT* be enabled on
 * a live site!
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     0.6.0
 * @since       0.6.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways;

use Shop\Cart;
use Shop\Coupon;
use Shop\Currency;

/**
 *  Coupon gateway class, just to provide checkout buttons for coupons
 */
class test extends \Shop\Gateway
{
    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     */
    public function __construct()
    {
        global $LANG_SHOP;

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'test';
        $this->gw_desc = 'Internal Testing Gateway';
        $this->gw_url = SHOP_URL . '/ipn/internal.php';
        parent::__construct();
    }


    /*
     * Get the checkout selection for applying a gift card balance.
     * If the GC balance exceeds the order value, create a radio button
     * just like any other gateway to use the balance as payment in full.
     * If the GC balance is less than the order amount, use a checkbox
     * to give the buyer the option of applying it as partial payment.
     *
     * @param   boolean $selected   Indicate if this should be the selected option
     * @return  string      HTML for the radio button or checkbox
     */
    public function checkoutRadio($selected = false)
    {
        global $LANG_SHOP;

        // Get the order total from the cart, and the user's balance
        // to decide what kind of button to show.
        $cart = Cart::getInstance();
        $total = $cart->getTotal();

        $sel = $selected ? 'checked="checked" ' : '';
        $radio = '<input required type="radio" name="gateway" value="' .
                $this->gw_name . '" ' . $sel . '/>&nbsp;' . $this->gw_desc;
        return $radio;
    }


    /**
     *  Get the form variables for this checkout button.
     *  Used if the entire order is being paid by the gift card balance.
     *
     *  @param  object  $cart   Shopping cart
     *  @return string          HTML for input vars
     */
    public function gatewayVars($cart)
    {
        global $_USER;

        // Add custom info for the internal ipn processor
        $cust = $cart->custom_info;
        $cust['uid'] = $_USER['uid'];
        $cust['transtype'] = 'coupon';
        $cust['cart_id'] = $cart->CartID();

        $pmt_gross = $cart->getTotal();
        if (isset($cust['by_gc'])) {
            $pmt_gross -= (float)$cust['by_gc'];
        }
        $gatewayVars = array(
            '<input type="hidden" name="processorder" value="by_gc" />',
            '<input type="hidden" name="cart_id" value="' . $cart->CartID() . '" />',
            '<input type="hidden" name="custom" value=\'' . @serialize($cust) . '\' />',
            '<input type="hidden" name="payment_status" value="Completed" />',
            '<input type="hidden" name="pmt_gross" value="' . $pmt_gross . '" />',
            '<input type="hidden" name="txn_id" value="' . uniqid() . '" />',
        );
        if (COM_isAnonUser()) {
            //$T->set_var('need_email', true);
        } else {
            $gateway_vars[] = '<input type="hidden" name="payer_email" value="' . $_USER['email'] . '" />';
        }
        return implode("\n", $gatewayVars);
    }


    /**
     * No config fields for the test gateway.
     *
     * @return  array   Empty array
     */
    public function getConfigFields()
    {
        return array();
    }


    /**
     * Get a buy-now button for a catalog product.
     * Checks the button table to see if a button exists, and if not
     * a new button will be created.
     *
     * @uses    PrepareCustom()
     * @uses    Gateway::_ReadButton()
     * @uses    Gateway::_SaveButton()
     * @param   object  $P      Product Item object
     * @return  string          HTML code for the button.
     */
    public function ProductButton($P)
    {
        global $_SHOP_CONF, $LANG_SHOP;

        // Make sure we want to create a buy_now-type button
        $btn_type = $P->btn_type;
        if (empty($btn_type)) return '';

        $this->AddCustom('transtype', $btn_type);
        $gateway_vars = '';

        // See if the button is in our cache table
        $btn_key = $P->btn_type . '_' . $P->getPrice();
        if ($this->config['encrypt']) {
            $gateway_vars = $this->_ReadButton($P, $btn_key);
        }
        if (empty($gateway_vars)) {
            $vars = array();
            $vars['cmd'] = $btn_type;
            $vars['business'] = $_CONF['site_email'];
            $vars['item_number'] = htmlspecialchars($P->id);
            $vars['item_name'] = htmlspecialchars($P->short_description);
            $vars['currency_code'] = $this->currency_code;
            $vars['custom'] = $this->PrepareCustom();
            $vars['return'] = SHOP_URL . '/index.php?thanks=shop';
            $vars['cancel_return'] = SHOP_URL;
            $vars['amount'] = $P->getPrice();

            // Get the allowed buy-now quantity. If not defined, set
            // undefined_quantity.
            $qty = $P->getFixedQuantity();
            if ($qty < 1) {
                $vars['undefined_quantity'] = '1';
            } else {
                $vars['quantity'] = $qty;
            }

            $vars['notify_url'] = $this->ipn_url;

            if ($P->weight > 0) {
                $vars['weight'] = $P->weight;
            } else {
                $vars['no_shipping'] = '1';
            }

            switch ($P->shipping_type) {
            case 0:
                $vars['no_shipping'] = '1';
                break;
            case 2:
                $vars['shipping'] = $P->shipping_amt;
                $vars['no_shipping'] = '1';
                break;
            case 1:
                $vars['no_shipping'] = '2';
                break;
            }

            if ($P->taxable) {
                $vars['tax_rate'] = sprintf("%0.4f", SHOP_getTaxRate() * 100);
            }

            // Buy-now product button, set default billing/shipping addresses
            $U = self::UserInfo();
            $shipto = $U->getDefaultAddress('shipto');
            if (!empty($shipto)) {
                if (strpos($shipto['name'], ' ')) {
                    list($fname, $lname) = explode(' ', $shipto['name']);
                    $vars['first_name'] = $fname;
                    if ($lname) $vars['last_name'] = $lname;
                } else {
                    $vars['first_name'] = $shipto['name'];
                }
                $vars['address1'] = $shipto['address1'];
                if (!empty($shipto['address2']))
                    $vars['address2'] = $shipto['address2'];
                $vars['city'] = $shipto['city'];
                $vars['state'] = $shipto['state'];
                $vars['zip'] = $shipto['zip'];
                $vars['country'] = $shipto['country'];
            }

            $gateway_vars = '';
            $enc_btn = '';
            if ($this->config['encrypt']) {
                $enc_btn = $this->_encButton($vars);
                if (!empty($enc_btn)) {
                    $gateway_vars .=
                    '<input type="hidden" name="cmd" value="_s-xclick" />'.LB .
                    '<input type="hidden" name="encrypted" value=\'' .
                        $enc_btn . '\' />' . "\n";
                }
            }
            if (empty($enc_btn)) {
                // Create unencrypted buttons if not configured to encrypt,
                // or if encryption fails.
                foreach ($vars as $name=>$value) {
                    $gateway_vars .= '<input type="hidden" name="' . $name .
                        '" value="' . $value . '" />' . "\n";
                }
            } else {
                $this->_SaveButton($P, $btn_key, $gateway_vars);
            }
        }

        // Set the text for the button, falling back to our Buy Now
        // phrase if not available
        $btn_text = $P->btn_text;    // maybe provided by a plugin
        if ($btn_text == '') {
            $btn_text = isset($LANG_SHOP['buttons'][$btn_type]) ?
                $LANG_SHOP['buttons'][$btn_type] : $LANG_SHOP['buy_now'];
        }
        $T = SHOP_getTemplate('btn_' . $btn_type, 'btn', 'buttons/generic');
        $T->set_var(array(
            'action_url'    => $this->getActionUrl(),
            'btn_text'      => $btn_text,
            'gateway_vars'  => $gateway_vars,
            'method'        => $this->getMethod(),
        ) );
        $retval = $T->parse('', 'btn');
        return $retval;
    }

}

?>
