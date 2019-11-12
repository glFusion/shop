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

        $this->config = array(
            'pub_key_prod'  => '',
            'sec_key_prod'  => '',
            'hook_sec_prod' => '',
            'pub_key_test'  => '',
            'sec_key_test'  => '',
            'hook_sec_test' => '',
            'test_mode'     => '',
        );

        // Set the only service supported
        $this->services = array('checkout' => 1);

        parent::__construct();

        if ($this->config['test_mode'] == 1) {
            $this->pub_key = $this->config['pub_key_test'];
            $this->sec_key = $this->config['sec_key_test'];
            $this->hook_sec = $this->config['hook_sec_test'];
        } else {
            $this->pub_key = $this->config['pub_key_prod'];
            $this->sec_key = $this->config['sec_key_prod'];
            $this->hook_sec = $this->config['hook_sec_prod'];
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
     * No config fields for the test gateway.
     *
     * @return  array   Empty array
     */
    protected function getConfigFields()
    {
        $fields = array();
        foreach($this->config as $name=>$value) {
            $other_label = '';
            switch ($name) {
            case 'test_mode':
                $field = '<input type="checkbox" name="' . $name .
                    '" value="1" ';
                if ($value == 1) $field .= 'checked="checked" ';
                $field .= '/>';
                break;
            default:
                $field = '<input type="text" name="' . $name . '" value="' .
                    $value . '" size="60" />';
                break;
            }
            $fields[$name] = array(
                'param_field'   => $field,
                'other_label'   => $other_label,
                'doc_url'       => '',
            );
        }
        return $fields;
    }


    /**
     * Prepare to save the configuraiton.
     * This copies the new config values into our local variables, then
     * calls the parent function to save to the database.
     *
     * @param   array   $A      Array of name=>value pairs (e.g. $_POST)
     */
    public function SaveConfig($A = NULL)
    {
        if (is_array($A)) {
            foreach ($this->config as $name=>$value) {
                switch ($name) {
                case 'test_mode':
                    $this->config[$name] = isset($A[$name]) ? 1 : 0;
                    break;
                default:
                    $this->config[$name] = $A[$name];
                    break;
                }
            }
        }
        return parent::SaveConfig($A);
    }


    /**
     * Get additional javascript to be attached to the checkout button.
     *
     * @return  string  Javascript commands.
     */
    public function getCheckoutJS()
    {
        $js = array(
            'var stripe = Stripe("' . $this->pub_key . '");',
            "stripe.redirectToCheckout({sessionId: \"{$this->session->id}\"});",
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
