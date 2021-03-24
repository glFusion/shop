<?php
/**
 * Testing gateway to pass orders through the internal IPN handler.
 *
 * *NOTE* All orders passed through this gateway are automatically
 * treated as paid in full. This gateway should *NOT* be enabled on
 * a live site!
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\test;
use Shop\Cart;
use Shop\Coupon;
use Shop\Currency;
use Shop\Template;


/**
 *  Coupon gateway class, just to provide checkout buttons for coupons
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = 'test';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'Testing Gateway';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'Internal Testing Gateway';

    /** Flag this gateway as bundled with the Shop plugin.
     * Gateway version will be set to the Shop plugin's version.
     * @var integer */
    protected $bundled = 1;


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        // These are used by the parent constructor, set them first.
        $this->do_redirect = false; // handled internally

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
        $this->gw_url = SHOP_URL . '/hooks/webhook.php?_gw=_internal';
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

        $pmt_gross = $cart->getTotal();
        if ($cart->getGC() > 0) {
            $pmt_gross -= (float)$cust['by_gc'];
        }
        $gatewayVars = array(
            '<input type="hidden" name="processorder" value="by_gc" />',
            '<input type="hidden" name="order_id" value="' . $cart->CartID() . '" />',
            '<input type="hidden" name="payment_status" value="Completed" />',
            '<input type="hidden" name="pmt_gross" value="' . $pmt_gross . '" />',
            '<input type="hidden" name="txn_id" value="' . uniqid() . '" />',
            '<input type="hidden" name="status" value="paid" />',
            '<input type="hidden" name="transtype" value="' . $this->gw_name . '" />',
            '<input type="hidden" name="uid" value="' . $_USER['uid'] . '" />',
        );
        if (!COM_isAnonUser()) {
            $gateway_vars[] = '<input type="hidden" name="payer_email" value="' . $_USER['email'] . '" />';
        }
        return implode("\n", $gatewayVars);
    }


    /**
     * No config fields for the test gateway.
     *
     * @param   string  $env    Environment (not used here)
     * @return  array   Empty array
     */
    protected function getConfigFields($env='global')
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
        global $LANG_SHOP, $_CONF;

        // Make sure we want to create a buy_now-type button
        if ($P->isPhysical()) return '';
        $btn_type = $P->getBtnType();
        if (empty($btn_type)) return '';

        $this->AddCustom('transtype', $btn_type);
        $gateway_vars = '';

        if (empty($gateway_vars)) {
            $vars = array();
            $vars['cmd'] = $btn_type;
            $vars['business'] = $_CONF['site_mail'];
            $vars['item_number'] = htmlspecialchars($P->getID());
            $vars['item_name'] = htmlspecialchars($P->getShortDscp());
            $vars['currency_code'] = $this->currency_code;
            $vars['custom'] = $this->PrepareCustom();
            $vars['return'] = SHOP_URL . '/index.php?thanks=shop';
            $vars['cancel_return'] = SHOP_URL;
            $vars['amount'] = $P->getPrice();
            $vars['pmt_gross'] = $P->getPrice();
            $vars['ipn_type'] = 'buy_now';  // force type for IPN processor.

            // Get the allowed buy-now quantity. If not defined, set
            // undefined_quantity.
            $qty = $P->getFixedQuantity();
            if ($qty < 1) {
                $vars['undefined_quantity'] = '1';
            } else {
                $vars['quantity'] = $qty;
            }

            $vars['notify_url'] = $this->ipn_url;

            if ($P->getWeight() > 0) {
                $vars['weight'] = $P->getWeight();
            } else {
                $vars['no_shipping'] = '1';
            }

            switch ($P->getShippingType()) {
            case 0:
                $vars['no_shipping'] = '1';
                break;
            case 2:
                $vars['shipping'] = $P->getShipping($vars['quantity']);
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
                $fullname = $shipto->getName();
                if (strpos($fullname, ' ')) {
                    list($fname, $lname) = explode(' ', $fullname);
                    $vars['first_name'] = $fname;
                    if ($lname) $vars['last_name'] = $lname;
                } else {
                    $vars['first_name'] = $fullname;
                }
                $vars['address1'] = $shipto->getAddress1();
                if (!empty($shipto->getAddress2())) {
                    $vars['address2'] = $shipto->getAddress2();
                }
                $vars['city'] = $shipto->getCity();
                $vars['state'] = $shipto->getState();
                $vars['zip'] = $shipto->getPostal();
                $vars['country'] = $shipto->getCountry();
            }

            $gateway_vars = '';
            // Create unencrypted buttons, the test gateway does not handle
            // encryption.
            foreach ($vars as $name=>$value) {
                $gateway_vars .= '<input type="hidden" name="' . $name .
                    '" value="' . $value . '" />' . "\n";
            }
        }

        // Set the text for the button, falling back to our Buy Now
        // phrase if not available
        $btn_text = $P->getBtnText();    // maybe provided by a plugin
        if ($btn_text == '') {
            $btn_text = isset($LANG_SHOP['buttons'][$btn_type]) ?
                $LANG_SHOP['buttons'][$btn_type] : $LANG_SHOP['buy_now'];
        }
        $btn_text .= ' (Test)';
        $T = new Template('buttons/generic');
        $T->set_file('btn', 'btn_' . $btn_type . '.thtml');
        $T->set_var(array(
            'action_url'    => $this->getActionUrl(),
            'btn_text'      => $btn_text,
            'gateway_vars'  => $gateway_vars,
            'gw_name'       => $this->gw_name,
            'method'        => $this->getMethod(),
        ), false, true);
        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
     * Check if this gateway allows an order to be processed without an IPN msg.
     * The Check gateway does allow this as it just presents a remittance form.
     *
     * @return  boolean     True
     */
    public function allowNoIPN()
    {
        return true;
    }


    /**
     * Check that the current user is allowed to use this gateway.
     * This limits access to special gateways like 'check' or 'terms'.
     * The Test gateway can be used by the authorized group regardless of
     * order value.
     *
     * @return  boolean     True if access is allowed, False if not
     */
    public function hasAccess($total=0)
    {
        return $this->isEnabled() && SEC_inGroup($this->grp_access);
    }

}

?>
