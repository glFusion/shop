<?php
/**
 * Testing gateway to handle free orders. Based on the Test gateway.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\free;
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
    protected $gw_name = 'free';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'Free Order';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'Free Order';

    /** Flag this gateway as bundled with the Shop plugin.
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
        global $LANG_SHOP;

        // These are used by the parent constructor, set them first.
        $this->gw_url = SHOP_URL . '/hooks/webhook.php?_gw=free';

        // This gateway can service all button type by default
        $this->services = array(
            'buy_now'   => 0,
            'donation'  => 0,
            'pay_now'   => 0,
            'subscribe' => 0,
            'checkout'  => 1,
            'external'  => 0,
        );
        $this->enabled = 1;         // set default for installation
        parent::__construct($A);
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
        if ($pmt_gross > 0) {
            return '';
        }
        $gatewayVars = array(
            '<input type="hidden" name="processorder" value="free" />',
            '<input type="hidden" name="order_id" value="' . $cart->CartID() . '" />',
            '<input type="hidden" name="payment_status" value="Completed" />',
            '<input type="hidden" name="pmt_gross" value="' . $pmt_gross . '" />',
            '<input type="hidden" name="txn_id" value="' . uniqid() . '" />',
            '<input type="hidden" name="status" value="paid" />',
        );
        if (!COM_isAnonUser()) {
            $gatewayVars[] = '<input type="hidden" name="payer_email" value="' . $_USER['email'] . '" />';
        }
        return implode("\n", $gatewayVars);
    }


    /**
     * No config fields for the free gateway.
     *
     * @param   string  $env    Environment (not used here)
     * @return  array   Empty array
     */
    public function getConfigFields($env='global')
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
        global $_USER, $LANG_SHOP;

        if ($P->getPrice() > 0 || $P->isPhysical()) {
            return '';    // Not for items that require shipping.
        }
        $cust = array(
            'uid' => $_USER['uid'],
            'transtype' => $this->gw_name,
            'btn_type' => 'buy_now',
        );
        $gatewayVars = array(
            '<input type="hidden" name="processorder" value="' . $this->gw_name . '" />',
            '<input type="hidden" name="custom" value=\'' . htmlspecialchars(@serialize($cust)) . '\' />',
            '<input type="hidden" name="payment_status" value="Completed" />',
            '<input type="hidden" name="pmt_gross" value="0" />',
            '<input type="hidden" name="txn_id" value="' . uniqid() . '" />',
            '<input type="hidden" name="status" value="paid" />',
            '<input type="hidden" name="payer_email" value="' . $_USER['email'] . '" />',
            '<input type="hidden" name="item_number" value="' . $P->getID() . '" />',
            '<input type="hidden" name="quantity" value="1" />',
            '<input type="hidden" name="return" value="' . SHOP_URL . '/index.php?thanks=free" />',
            '<input type="hidden" name="cmd" value="buy_now" />',
            '<input type="hidden" name="ipn_type" value="buy_now" />',
        );
        $gatewayVars = implode(LB, $gatewayVars);

        // Set the text for the button, falling back to our Buy Now
        // phrase if not available
        $btn_text = $P->btn_text;    // maybe provided by a plugin
        if ($btn_text == '') {
            $btn_text = SHOP_getVar($LANG_SHOP['buttons'], $this->gw_name, 'string', $LANG_SHOP['buy_now']);
        }
        $T = new Template('buttons');
        $T->set_file('btn', 'btn_free.thtml');
        $T->set_var(array(
            'action_url'    => $this->getActionUrl(),
            'btn_text'      => $btn_text,
            'gateway_vars'  => $gatewayVars,
            'method'        => $this->getMethod(),
            'uniqid'        => uniqid(),
        ) );
        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
     * Check that the current user is allowed to use this gateway.
     * The free gateway can be used by allowed users only if the order value
     * is zero.
     *
     * @param   float   $total  Order total
     * @return  boolean     True if access is allowed, False if not
     */
    public function hasAccess($total=0)
    {
        return $total == 0 && $this->isEnabled() && SEC_inGroup($this->grp_access);
    }

}
