<?php
/**
 * Class to manage full or partial payment by gift card.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\_coupon;
use Shop\Cart;
use Shop\Products\Coupon;
use Shop\Currency;
use Shop\Payment;
use Shop\Models\PayoutHeader;


/**
 * Coupon gateway class, just to provide checkout buttons for coupons.
 * @package shop
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway name.
     * @var string */
    protected $gw_name = '_coupon';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'Pay by Coupon';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'Payment by Coupon/Gift Card';

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
        global $LANG_SHOP;

        // These are used by the parent constructor, set them first.
        $this->gw_url = SHOP_URL . '/hooks/webhook.php?_gw=' . $this->gw_name;

        // Set the services that this gateway can provide
        $this->services = array(
            'buy_now'   => 0,
            'donation'  => 0,
            'pay_now'   => 0,
            'subscribe' => 0,
            'checkout'  => 1,
            'external'  => 0,
        );
        parent::__construct($A);
    }


    /**
     * Get the checkout selection for applying a gift card balance.
     * If the GC balance exceeds the order value, create a radio button
     * just like any other gateway to use the balance as payment in full.
     * If the GC balance is less than the order amount, use a checkbox
     * to give the buyer the option of applying it as partial payment.
     *
     * @param   boolean $selected   Indicate if this should be the selected option
     * @return  string      HTML for the radio button or checkbox
     */
    public function checkoutRadio($Cart, $selected = false)
    {
        global $LANG_SHOP;

        // Get the order total from the cart, and the user's balance
        // to decide what kind of button to show.
        $total = $Cart->getTotal();
        $gc_bal = Coupon::getUserBalance();
        if ($gc_bal == 0) {
            // No gift card balance to apply, no selection to show
            return false;
        }

        // Get the amount that can be paid by gift card,
        // since coupon products cannot.
        $gc_can_apply = Coupon::canPayByGC($Cart);

        // If no gift card amount can be applied, don't show this gateway option
        if ($gc_can_apply == 0) {
            return false;
        }

        $retval = new \Shop\Models\PaymentRadio;
        $retval['gw_name'] = $this->gw_name;
        $retval['varname'] = 'by_gc';
        // Create the radio button or checkbox as appropriate based
        // on the card balance vs. the amount that can be paid by card.
        if ($gc_bal < $gc_can_apply) {
            $retval['type'] = 'checkbox';
            $retval['value'] = $gc_bal;
            $retval['selected'] = 'checked="checked"';
            $retval['dscp'] = sprintf(
                       $LANG_SHOP['use_gc_full'],
                        Currency::getInstance()->Format($gc_bal)
            );
            $retval['logo'] = $retval['dscp'];

            // Set the partial payment amount by coupon.
            // If the coupon balance is sufficient for the order, it will be treated
            // as the single payment gateway.
            $Cart->setByGC($retval['value'])->Save(false);
        } elseif ($gc_bal >= $gc_can_apply && $gc_can_apply == $total) {
            // GC balance is enough to pay for the order. Show a regular
            // radio button to pay the entire order.
            $retval['selected'] = $selected ? 'checked="checked" ' : '';
            $retval['value'] = $gc_can_apply;
            $retval['dscp'] = sprintf(
                       $LANG_SHOP['use_gc_part'],
                        Currency::getInstance()->Format($gc_can_apply),
                        Currency::getInstance()->Format($gc_bal)
                   );
            $retval['logo'] = $retval['dscp'];
        } else {
            // Have a GC balance, but not enough to pay the entire order because
            // some items can't be paid by GC. Same checkbox as above but with
            // a different text message.
            $by_gc = min($gc_bal, $gc_can_apply);
        }
        $retval['highlight'] = true;
        return $retval;
    }


    /**
     * Get the form variables for this checkout button.
     * Used if the entire order is being paid by the gift card balance.
     *
     * @param   object  $cart   Shopping cart
     * @return  string          HTML for input vars
     */
    public function gatewayVars($cart)
    {
        global $_USER;

        $gatewayVars = array(
            '<input type="hidden" name="order_id" value="' . $cart->getOrderId() . '" />',
            '<input type="hidden" name="txn_id" value="' . uniqid() . '" />',
            '<input type="hidden" name="uid" value="' . $_USER['uid'] . '" />',
        );
        if (!COM_isAnonUser()) {
            $gateway_vars[] = '<input type="hidden" name="payer_email" value="' . $_USER['email'] . '" />';
        }
        return implode("\n", $gatewayVars);
    }


    /**
     * Check that the current user is allowed to use this gateway.
     * Coupons can be used by anyone.
     *
     * @param   float   $total  Total order amount (not used here)
     * @return  boolean     True if access is allowed, False if not
     */
    public function hasAccess($total = 0)
    {
        return true;
    }


    /**
     * Confirm an order.
     * For full payment by coupon this processes the order.
     *
     * @param   object  $Order  Order object
     * @return  string      Redirect URL
     */
    public function confirmOrder($Order)
    {
        global $_CONF;

        // Check that the user has sufficient coupon balance.
        $gc_bal = Coupon::getUserBalance($Order->getUid());
        if ($gc_bal < $Order->getTotal()) {
            COM_setMsg($this->getLang('insuf_bal'));
        } else {
            $Order->setByGC($Order->getTotal());
            $Order->Save();
            $coupons = Coupon::Apply($Order->getTotal(), $Order->getUid());
            COM_setMsg($this->getLang('pd_by_coupon', 'Paid by Coupon'));

            // Create the webhook object and add some data.
            // This is mostly to initialize the IPN property which gets
            // used in handlePurchase().
            $WH = new \Shop\Webhook;
            $WH->setID('gcfull_' . $Order->getOrderId());
            $WH->setSource($this->gw_name);
            $WH->setPayment($Order->getTotal());
            $WH->setTxnDate();
            $WH->setOrderId($Order->getOrderId());
            $status = $WH->handlePurchase($Order);
            if (!$status) {
                COM_setMsg($this->getLang('error', 'An Error Occurred'));
            }
        }
        return SHOP_URL . '/index.php';
    }


    /**
     * Get additional javascript to be attached to the checkout button.
     * Paying by coupon routes through confirmOrder() only, no
     * javascript needed.
     *
     * @param   object  $cart   Shopping cart object
     * @return  string  Javascript commands.
     */
    public function getCheckoutJS($cart)
    {
        return '';
    }


    /**
     * Send affiliate payouts.
     *
     * @param   array   $Payouts    Array of Payout objects
     */
    public function sendPayouts(PayoutHeader $Header, array $Payouts)
    {
        global $_TABLES;

        foreach ($Payouts as $Payout) {
            $uid = (int)$Payout['uid'];
            $amt = (float)$Payout['amount'];
            $exp = '9999-12-31 23:59:59';
            $code = Coupon::Purchase($amt, $uid, $exp);
            $email = DB_getItem($_TABLES['users'], 'email', "uid = $uid");
            $name = COM_getDisplayName($uid);
            $msg = 'You have an affiliate payment via coupon';
            if (!empty($email)) {
                Coupon::Notify($code, $email, $amt, '', $msg, $exp, $name);
            }
            $Payout['txn_id'] = $code;
        }
    }

}
