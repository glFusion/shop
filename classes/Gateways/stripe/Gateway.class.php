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
namespace Shop\Gateways\stripe;
use Shop\Config;
use Shop\Cart;
use Shop\Coupon;
use Shop\Currency;
use Shop\Customer;
use Shop\Models\OrderState;


/**
 *  Coupon gateway class, just to provide checkout buttons for coupons
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = 'stripe';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'stripe.com';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'Stripe Payment Gateway';

    /** Flag this gateway as bundled with the Shop plugin.
     * @var integer */
    protected $bundled = 1;

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
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        // Set up the config field definitions.
        $this->cfgFields = array(
            'prod' => array(
                'pub_key'  => 'password',
                'sec_key'  => 'password',
                'hook_sec' => 'password',
            ),
            'test' => array(
                'pub_key'  => 'password',
                'sec_key'  => 'password',
                'hook_sec' => 'password',
            ),
            'global' => array(
                'test_mode' => 'checkbox',
            ),
        );

        // Set the only service supported
        $this->services = array(
            'checkout' => 1,
            'terms' => 1,
        );

        $this->ipn_url = $this->getWebhookUrl();
        parent::__construct($A);

        $this->pub_key = $this->getConfig('pub_key');
        $this->sec_key = $this->getConfig('sec_key');
        $this->hook_sec = $this->getConfig('hook_sec');
    }


    /**
     * Make the API classes available. May be needed for reports.
     *
     * @return  object  $this
     */
    public function loadSDK()
    {
        require_once __DIR__ . '/vendor/autoload.php';
        return $this;
    }


    /**
     * Get the Square API client object.
     * Initializes the API for use with static functions as well as
     * returning a client object.
     *
     * @return  object      SquareClient object
     */
    public function getApiClient()
    {
        static $_client = NULL;
        if ($_client === NULL) {
            $this->loadSDK();
            \Stripe\Stripe::setApiKey($this->sec_key);
            $_client = new \Stripe\StripeClient($this->sec_key);
        }
        return $_client;
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
        global $LANG_SHOP;

        static $have_js = false;

        if (!$this->Supports('checkout')) {
            return '';
        }

        $apiClient = $this->getApiClient();
        $this->_cart = $cart;   // Save to it is available to getCheckoutButton()
        $cartID = $cart->getOrderID();
        $shipping = 0;
        $Cur = \Shop\Currency::getInstance();
        $line_items = array();
        $taxRates = array();    // save tax rate objects for reuse

        // If the cart has a gift card applied, set one line item for the
        // entire cart. Stripe does not support discounts or gift cards.
        $by_gc = $cart->getGC();
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
                $Item->Price = $P->getPrice($Item->getOptions());
                $opts = $P->getOptionDesc($Item->getOptions());
                $dscp = $Item->getDscp();
                if (!empty($opts)) {
                    $dscp .= ' : ' . $opts;
                }
                $line_items[] = array(
                    'quantity'  => $Item->getQuantity(),
                    'price_data' => array(
                        'unit_amount' => $Cur->toInt($Item->getPrice()),
                        'currency'  => strtolower($Cur),
                        'product_data' => array(
                            'name'      => $Item->getDscp(),
                            'description' => $dscp,
                        ),
                    ),
                );
            }

            // Add line items to represent tax and shipping.
            // These are included in "all items" above when using a coupon.
            if ($cart->getTax() > 0) {
                $line_items[] = array(
                    'quantity'  => 1,
                    'price_data' => array(
                        'unit_amount'    => $Cur->toInt($cart->getTax()),
                        'currency'  => strtolower($Cur),
                        'product_data' => array(
                            'name'      => '__tax',
                            'description' => $LANG_SHOP['tax'],
                        ),
                    ),
                );
            }
            if ($cart->getShipping() > 0) {
                $line_items[] = array(
                    'quantity'  => 1,
                    'price_data' => array(
                        'unit_amount' => $Cur->toInt($cart->getShipping()),
                        'currency'  => strtolower($Cur),
                        'product_data' => array(
                            'name'      => '__shipping',
                            'description' => $LANG_SHOP['shipping'],
                        ),
                    ),
                );
            }
        }

        // Create the checkout session
        $session_params = array(
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'success_url' => Config::get('url') . '/index.php',
            'cancel_url' => $cart->cancelUrl(),
            'client_reference_id' => $cartID,
            'metadata' => array(
                'order_id' => $cart->getOrderID(),
            ),
        );

        // Retrieve or create a Square customer record as needed
        $gwCustomer = $this->getCustomer($cart->getUid());
        if ($gwCustomer) {
            $session_params['customer'] = $gwCustomer->id;
        }

        // Create the checkout session and load the Stripe javascript
        $this->session = $apiClient->checkout->sessions->create($session_params);
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
            'finalizeCart("' . $cart->getOrderID() . '","' . $cart->getUID() . '", ' . $this->do_redirect . ');',
            'var stripe = Stripe("' . $this->pub_key . '");',
            "stripe.redirectToCheckout({sessionId: \"{$this->session->id}\"});",
            "return false;",
        );
        return implode(" ", $js);
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
     * Get the secret API key.
     * Required by the IPN handler.
     *
     * @return  string      Secret API key
     */
    public function getSecretKey()
    {
        return $this->sec_key;
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
        $this->getApiClient();
        return \Stripe\PaymentIntent::retrieve($pmt_id);
    }


    /**
     * Get instructions for this gateway to display on the configuration page.
     *
     * @return  string      Instruction text
     */
    protected function getInstructions()
    {
        global $LANG_SHOP_HELP;
        return '<ul><li>' . $this->adminWarnBB() . '</li><li>' .
            $LANG_SHOP_HELP['gw_wh_instr'] . '</li></ul>';
    }


    /**
     * Get the gateway's customer record by user ID.
     * Creates a customer if not already present.
     *
     * @param   integer $uid    Customer user ID
     * @return  object      Stripe customer record
     */
    public function getCustomer($uid)
    {
        $cust_info = NULL;
        $Customer = Customer::getInstance($uid);
        $gw_id = $Customer->getGatewayId($this->gw_name);
        if ($gw_id) {
            $client = $this->getApiClient();
            $cust_info = $client->customers->retrieve($gw_id);
        }
        if (
            !is_object($cust_info) ||
            !isset($cust_info->id) ||
            !isset($cust_info->created)
        ) {
            $cust_info = $this->createCustomer($Customer);
        }
        return $cust_info;
    }


    /**
     * Create a new customer record with Stripe.
     * Called if getCustomer() returns an empty set.
     *
     * @param   object  $Order      Order object, to get customer info
     * @return  object|false    Customer object, or false if an error occurs
     */
    private function createCustomer($Customer)
    {
        // Get the default billing address to user in the Stripe record.
        // If there is no name entered for the default address, use the
        // glFusion full name.
        $Address = $Customer->getDefaultAddress('billto');
        $name = $Address->getName();
        if (empty($name)) {
            $name = $Customer->getFullname();
        }

        $params = [
            'name' => $name,
            'email' => $Customer->getEmail(),
            'address' => [
                'line1' => $Address->getAddress1(),
                'line2' => $Address->getAddress2(),
                'city' => $Address->getCity(),
                'state' => $Address->getState(),
                'postal_code' => $Address->getPostal(),
                'country' => $Address->getCountry(),
            ],
        ];

        $client = $this->getApiClient();
        $apiResponse = $client->customers->create($params);
        if (isset($apiResponse->id)) {
            $Customer->setGatewayId($this->gw_name, $apiResponse->id);
        }
        return $apiResponse;
    }


    /**
     * Create and send an invoice for an order.
     *
     * @param   object  $Order  Order object
     * @param   object  $terms_gw   Invoice terms gateway, for config values
     * @return  boolean     True on success, False on error
     */
    public function createInvoice($Order, $terms_gw)
    {
        global $LANG_SHOP;

        $gwCustomer = $this->getCustomer($Order->getUid());
        if ($gwCustomer) {
            $cust_id = $gwCustomer->id;
        } else {
            SHOP_log("Error creating Stripe customer for order {$Order->getOrderId()}");
            return false;
        }

        $Currency = $Order->getCurrency();
        $apiClient = $this->getApiClient();
        $taxRates = array();

        foreach ($Order->getItems() as $Item) {
            $opts = implode(', ', $Item->getOptionsText());
            $dscp = $Item->getDscp();
            if (!empty($opts)) {
                $dscp .= ' : ' . $opts;
            }
            $params = array(
                'customer' => $cust_id,
                'unit_amount' => $Currency->toInt($Item->getNetPrice()),
                'quantity' => $Item->getQuantity(),
                'description' => $dscp,
                'currency' => $Order->getCurrency(),
            );
            if ($Item->getTaxRate() > 0) {
                if (!isset($taxRates[$Item->getTaxRate()])) {
                    $taxRates[$Item->getTaxRate()]  = $apiClient->taxRates->create([
                        'display_name' => $LANG_SHOP['sales_tax'],
                        'percentage' => $Item->getTaxRate() * 100,
                        'inclusive' => false,
                    ]);
                }
                $params['tax_rates'] = array(
                    $taxRates[$Item->getTaxRate()],
                );
            }
            $apiClient->invoiceItems->create($params);
        }

        if ($Order->getShipping() > 0) {
            $apiClient->invoiceItems->create(array(
                'customer' => $cust_id,
                'unit_amount' => $Currency->toInt($Order->getShipping()),
                'quantity' => 1,
                'description' => $LANG_SHOP['shipping'],
                'currency' => $Order->getCurrency(),
            ) );
        }
        $invObj = $apiClient->invoices->create(array(
            'customer' => $cust_id,
            'auto_advance' => true,
            'metadata' => array(
                'order_id' => $Order->getOrderID(),
            ),
            'collection_method' => 'send_invoice',
            'days_until_due' => (int)$terms_gw->getConfig('net_days'),
        ) );
        // Get the invoice number if a valid draft invoice was created.
        if (isset($invObj->status) && $invObj->status == 'draft') {
            $Order->setGatewayRef($invObj->id)
                  ->setInfo('terms_gw', $this->getConfig('gateway'))
                  ->Save();
            $Order->updateStatus(OrderState::INVOICED);
        }
        $invObj->finalizeInvoice();
        return $invObj;
    }


    /**
     * Check that a valid config has been set for the environment.
     *
     * @return  boolean     True if valid, False if not
     */
    public function hasValidConfig()
    {
        return !empty($this->getConfig('pub_key')) &&
            !empty($this->getConfig('sec_key')) &&
            !empty($this->getConfig('hook_sec'));
    }

}
