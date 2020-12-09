<?php
/**
 * Class to manage payment by gift card.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways;

/**
 * Internal gateway class, just to support zero-balance orders.
 */
class _internal extends \Shop\Gateway
{
    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        // These are used by the parent constructor, set them first.
        $this->gw_name  = '_internal';
        $this->gw_desc  = 'Internal Payment Gateway';
        $this->gw_url   = SHOP_URL . '/ipn/internal.php';

        // This gateway can service all button type by default
        $this->services = array(
            'buy_now'   => 1,
            'donation'  => 1,
            'pay_now'   => 1,
            'subscribe' => 1,
            'checkout'  => 1,
            'external'  => 1,
        );
        parent::__construct($A);
    }


    /**
     * Get a buy-now button for a catalog product.
     * Checks the button table to see if a button exists, and if not
     * a new button will be created.
     *
     * @uses    gwButtonType()
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

        $btn_info = self::gwButtonType($btn_type);
        $this->AddCustom('transtype', $btn_type);
        $gateway_vars = '';

        // See if the button is in our cache table
        $btn_key = $P->btn_type . '_' . $P->getPrice();
        if ($this->config['encrypt']) {
            $gateway_vars = $this->_ReadButton($P, $btn_key);
        }
        if (empty($gateway_vars)) {
            $vars = array();
            $vars['cmd'] = $btn_info['cmd'];
            $this->setReceiver($P->getPrice());
            $vars['business'] = $this->receiver_email;
            $vars['item_number'] = htmlspecialchars($P->id);
            $vars['item_name'] = htmlspecialchars($P->short_description);
            $vars['currency_code'] = $this->currency_code;
            $vars['custom'] = $this->PrepareCustom();
            $vars['return'] = SHOP_URL . '/index.php?thanks=shop';
            $vars['cancel_return'] = SHOP_URL;
            $vars['amount'] = $P->getPrice();
            $vars['notify_url'] = $this->ipn_url;
            $vars['ipn_type'] = 'buy_now';      // Force the IPN type

            // Get the allowed buy-now quantity. If not defined, set
            // undefined_quantity.
            $qty = $P->getFixedQuantity();
            if ($qty < 1) {
                $vars['undefined_quantity'] = '1';
            } else {
                $vars['quantity'] = $qty;
            }

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

            /*if ($P->taxable) {
                $vars['tax_rate'] = sprintf("%0.4f", SHOP_getTaxRate() * 100);
            }*/

            // Buy-now product button, set default billing/shipping addresses
            $U = self::Customer();
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
        $T = new Template('buttons/' . $this->gw_name);
        $T->set_file('btn', 'btn_' . $btn_info['tpl'] . '.thtml');
        $T->set_var(array(
            'action_url'    => $this->getActionUrl(),
            'btn_text'      => $btn_text,
            'gateway_vars'  => $gateway_vars,
            'method'        => $this->getMethod(),
        ) );
        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
     * Get the invoice terms string for simple invoicing.
     *
     * @param   integer $due_days   Due days (terms)
     * @return  string      Proper terms string for Paypal
     */
    private function getInvoiceTerms($due_days=0)
    {
        $due_days = (int)$due_days;
        if ($due_days == 0) {
            $retval = 'Due Upon Receipt';
        } else {
            $day_arr = array(10, 15, 30, 45, 60, 90);
            $retval = 90;
            foreach ($day_arr as $days) {
                if ($due_days <= $days) {
                    $retval = $days;
                    break;
                }
            }
            $retval = 'Net ' . $retval . ' Days';
        }
        return $retval;
    }


    /**
     * Check if the gateway supports invoicing. Default is false.
     *
     * @return  boolean True if invoicing is supported, False if not.
     */
    public function supportsInvoicing()
    {
        return true;
    }


    /**
     * Check that the current user is allowed to use this gateway.
     * This limits access to special gateways like 'check' or 'terms'.
     * The internal gateway can be used by all users if the order value
     * is zero.
     *
     * @param   float   $total  Order total
     * @return  boolean     True if access is allowed, False if not
     */
    public function hasAccess($total=0)
    {
        return $this->getEnabled() && SEC_inGroup($this->grp_access);
    }

}

?>
