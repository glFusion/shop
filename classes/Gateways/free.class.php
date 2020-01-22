<?php
/**
 * Testing gateway to handle free orders. Based on the Test gateway.
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
namespace Shop\Gateways;

use Shop\Cart;
use Shop\Coupon;
use Shop\Currency;

/**
 *  Coupon gateway class, just to provide checkout buttons for coupons
 */
class free extends \Shop\Gateway
{
    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     */
    public function __construct()
    {
        global $LANG_SHOP;

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'free';
        $this->gw_desc = 'Free Order';
        $this->gw_url = SHOP_URL . '/ipn/internal.php';
        // This gateway can service all button type by default
        $this->services = array(
            'buy_now'   => 0,
            'donation'  => 0,
            'pay_now'   => 0,
            'subscribe' => 0,
            'checkout'  => 1,
            'external'  => 0,
            //'terms'     => 0,
        );
        $this->enabled = 1;         // set default for installation
        $this->is_system = 1;       // this gateway is required
        parent::__construct();
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
        $cust['transtype'] = 'free';
        $cust['cart_id'] = $cart->CartID();

        $pmt_gross = $cart->getTotal();
        if (isset($cust['by_gc'])) {
            $pmt_gross -= (float)$cust['by_gc'];
        }
        $gatewayVars = array(
            '<input type="hidden" name="processorder" value="free" />',
            '<input type="hidden" name="cart_id" value="' . $cart->CartID() . '" />',
            '<input type="hidden" name="custom" value=\'' . htmlspecialchars(@serialize($cust)) . '\' />',
            '<input type="hidden" name="payment_status" value="Completed" />',
            '<input type="hidden" name="pmt_gross" value="' . $pmt_gross . '" />',
            '<input type="hidden" name="txn_id" value="' . uniqid() . '" />',
            '<input type="hidden" name="status" value="paid" />',
            '<input type="hidden" name="payer_email" value="' . $_USER['email'] . '" />',
        );
        return implode("\n", $gatewayVars);
    }


    /**
     * No config fields for the free gateway.
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
        // TODO
        return '';
    }


    /**
     * Check that the current user is allowed to use this gateway.
     * This limits access to special gateways like 'check' or 'terms'.
     * The internal gateway can be used by all users if the order value
     * is zero.
     *
     * @return  boolean     True if access is allowed, False if not
     */
    public function hasAccess($total=0)
    {
        return $total == 0 && SEC_inGroup($this->grp_access);
    }

}

?>
