<?php
/**
 * Stripe payment gateway class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.1
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
class stripe extends \Shop\Gateway
{
    /** Active public API Key.
     * @var string */
    private $pub_key;

    /** Active secret key.
     * @var string */
    private $sec_key;

    /** Active webhook secret.
     * @var string */
    private $hook_sec;

    /** Checkout session.
     * @var object */
    private $session;

    /** Cart object. Set in gatewayVars and used in getCheckoutButton().
     * @var object */
    private $_cart;


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     */
    public function __construct()
    {
        global $LANG_SHOP, $_SHOP_CONF;

        require_once SHOP_PI_PATH . '/vendor/autoload.php';

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'stripe';
        $this->gw_desc = 'Stripe Payment Gateway';
        $this->gw_url = SHOP_URL . '/ipn/stripe.php';

        $this->cfgFields = array(
            'pub_key_prod'  => 'password',
            'sec_key_prod'  => 'password',
            'hook_sec_prod' => 'password',
            'pub_key_test'  => 'password',
            'sec_key_test'  => 'password',
            'hook_sec_test' => 'password',
            'test_mode'     => 'checkbox',
        );

        // Set the only service supported
        $this->services = array('checkout' => 1);

        parent::__construct();

        if ($this->getConfig('test_mode') == 1) {
            $this->pub_key = $this->getConfig('pub_key_test');
            $this->sec_key = $this->getConfig('sec_key_test');
            $this->hook_sec = $this->getConfig('hook_sec_test');
        } else {
            $this->pub_key = $this->getConfig('pub_key_prod');
            $this->sec_key = $this->getConfig('sec_key_prod');
            $this->hook_sec = $this->getConfig('hook_sec_prod');
        }
        \Stripe\Stripe::setApiKey($this->sec_key);
    }


    /**
     *  Get the form variables for this checkout button.
     *  For Stripe there are several things to do, but there are no form vars.
     *
     *  @param  object  $cart   Shopping cart
     *  @return string          HTML for input vars
     */
    public function gatewayVars($cart)
    {
        global $_SHOP_CONF, $_USER, $_TABLES, $LANG_SHOP, $_CONF;

        static $have_js = false;

        if (!$this->_Supports('checkout')) {
            return '';
        }

        $this->_cart = $cart;   // Save to it is available to getCheckoutButton()
        $cartID = $cart->CartID();
        $shipping = 0;
        $Cur = \Shop\Currency::getInstance();
        $line_items = array();

        // If the cart has a gift card applied, set one line item for the
        // entire cart. Stripe does not support discounts or gift cards.
        $by_gc = $cart->getInfo('apply_gc');
        if ($by_gc > 0) {
            $total_amount = $cart->getTotal() - $by_gc;
            $line_items[] = array(
                'name'      => $LANG_SHOP['all_items'],
                'description' => $LANG_SHOP['all_items'],
                'amount'    => $Cur->toInt($total_amount),
                'currency'  => strtolower($Cur),
                'quantity'  => 1,
            );
        } else {
            foreach ($cart->getItems() as $Item) {
                $P = $Item->getProduct();
                $Item->Price = $P->getPrice($Item->options);
                $opts = $P->getOptionDesc($Item->options);
                $dscp = $Item->description;
                if (!empty($opts)) {
                    $dscp .= ' : ' . $opts;
                }
                $line_items[] = array(
                    'name'      => $Item->description,
                    'description' => $dscp,
                    'amount'    => $Cur->toInt($Item->Price),
                    'currency'  => strtolower($Cur),
                    'quantity'  => $Item->quantity,
                );
            }

            // Add line items to represent tax and shipping.
            // These are included in "all items" above when using a coupon.
            if ($cart->tax > 0) {
                $line_items[] = array(
                    'name'      => '__tax',
                    'description' => $LANG_SHOP['tax'],
                    'amount'    => $cart->tax * pow(10, $Cur->decimals),
                    'currency'  => strtolower($Cur),
                    'quantity'  => 1,
                );
            }
            if ($cart->shipping > 0) {
                $line_items[] = array(
                    'name'      => '__shipping',
                    'description' => $LANG_SHOP['shipping'],
                    'amount'    => $cart->shipping * pow(10, $Cur->decimals),
                    'currency'  => strtolower($Cur),
                    'quantity'  => 1,
                );
            }
        }

        // Create the checkout session
        $this->session = \Stripe\Checkout\Session::create(array(
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'success_url' => $_CONF['site_url'],        // todo
            'cancel_url' => $cart->cancelUrl(),
            'client_reference_id' => $cartID,
        ) );

        if (!$have_js) {
            $outputHandle = \outputHandler::getInstance();
            $outputHandle->addLinkScript('https://js.stripe.com/v3/');
            $have_js = true;
        }

        // No actual form vars needed
        return '';
    }


    /**
     * Get additional javascript to be attached to the checkout button.
     * Stripe redirect is done completely in JS, and there is no cancel option.
     *
     * @param   object  $cart   Shopping cart object
     * @return  string  Javascript commands.
     */
    public function getCheckoutJS($cart)
    {
        $js = array(
            'finalizeCart("' . $cart->order_id . '","' . $cart->uid . '");',
            'var stripe = Stripe("' . $this->pub_key . '");',
            "stripe.redirectToCheckout({sessionId: \"{$this->session->id}\"});",
            "return false;",
        );
        return implode(" ", $js);
    }


    /**
     * Get a logo image to show on the order as the payment method.
     *
     * @return  string      HTML for logo image
     */
    public function getLogo()
    {
        global $_CONF, $_SHOP_CONF;
        return COM_createImage(
            $_CONF['site_url'] . '/' . $_SHOP_CONF['pi_name'] . '/images/gateways/stripe_logo_2x.png',
            $this->gw_desc,
            array(
                'width' => '170px',
            )
        );
    }


    /**
     * Get the webhook secret in use.
     * Required by the IPN handler to validate IPN signatures.
     *
     * @return  string      Configured webhook secret value
     */
    public function getWebhookSecret()
    {
        return $this->hook_sec;
    }


    /**
     * Get the public API key.
     * Required by the IPN handler.
     *
     * @return  string      Public API key in use.
     */
    public function getAPIKey()
    {
        return $this->pub_key;
    }


    /**
     * Retrieve a payment intent to get payment details.
     *
     * @param   string  $pmt_id     Payment Intent ID
     * @return  object  Strip Payment Intent object
     */
    public function getPayment($pmt_id)
    {
        if (empty($pmt_id)) {
            return false;
        }
        return \Stripe\PaymentIntent::retrieve($pmt_id);
    }


    /**
     * Get instructions for this gateway to display on the configuration page.
     *
     * @return  string      Instruction text
     */
    protected function getInstructions()
    {
        return $this->adminWarnBB();
    }

}

?>
