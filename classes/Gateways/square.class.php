<?php
/**
 * Gateway implementation for Square (squareup.com).
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways;


/**
 * Class for Square payment gateway.
 * @package shop
 */
class square extends \Shop\Gateway
{

    /** Square location value.
     * @var string */
    private $loc_id;

    /** Square App ID.
     * @var string */
    private $appid;

    /** Square Token.
     * @var string */
    private $token;

    private $api_url;


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     */
    public function __construct()
    {
        global $_SHOP_CONF, $_USER;

        // Import the Square API
        require_once SHOP_PI_PATH . '/vendor/autoload.php';

        $supported_currency = array(
            'USD', 'AUD', 'CAD', 'EUR', 'GBP', 'JPY', 'NZD', 'CHF', 'HKD',
            'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'CZK', 'ILS', 'MXN',
            'PHP', 'TWD', 'THB', 'MYR', 'RUB',
        );

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'square';
        $this->gw_desc = 'SquareConnect';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->cfgFields = array(
            'sb_loc_id'     => 'password',
            'sb_appid'      => 'password',
            'sb_token'      => 'password',
            'prod_loc_id'   => 'password',
            'prod_appid'    => 'password',
            'prod_token'    => 'password',
            'test_mode'     => 'checkbox',
        );

        // Set the only service supported
        $this->services = array('checkout' => 1);

        // Call the parent constructor to initialize the common variables.
        parent::__construct();

        // Set the gateway URL depending on whether we're in test mode or not
        if ($this->isSandbox()) {
            // Test settings
            $this->loc_id = $this->getConfig('sb_loc_id');
            $this->appid = $this->getConfig('sb_appid');
            $this->token = $this->getConfig('sb_token');
            $this->api_url = 'https://connect.squareupsandbox.com';
        } else {
            // Production settings
            $this->loc_id = $this->getConfig('prod_loc_id');
            $this->appid = $this->getConfig('prod_appid');
            $this->token = $this->getConfig('prod_token');
            $this->api_url = 'https://connect.squareup.com';
        }
        $this->gw_url = NULL;   // Normal gateway action url not used

        // If the configured currency is not one of the supported ones,
        // this gateway cannot be used, so disable it.
        if (!in_array($this->currency_code, $supported_currency)) {
            $this->enabled = 0;
        }
    }


    /**
     * Get the main gateway url.
     * This is used to tell the buyer where they can log in to check their
     * purchase. For PayPal this is the same as the production action URL.
     *
     * @return  string      Gateway's home page
     */
    public function getMainUrl()
    {
        return '';
    }


    /**
     * Get the form variables for the purchase button.
     *
     * @uses    Gateway::_Supports()
     * @uses    _encButton()
     * @uses    getActionUrl()
     * @param   object  $cart   Shopping cart object
     * @return  string      HTML for purchase button
     */
    public function gatewayVars($cart)
    {
        global $_SHOP_CONF, $_USER, $_TABLES, $LANG_SHOP;

        if (!$this->_Supports('checkout')) {
            return '';
        }

        $cartID = $cart->CartID();
        $shipping = 0;
        $Cur = \Shop\Currency::getInstance();

        $accessToken = $this->token;
        $locationId = $this->loc_id;

        // Create and configure a new API client object
        $defaultApiConfig = new \SquareConnect\Configuration();
        $defaultApiConfig->setAccessToken($accessToken);
        $defaultApiConfig->setHost($this->api_url);
        $defaultApiClient = new \SquareConnect\ApiClient($defaultApiConfig);
        $checkoutClient = new \SquareConnect\Api\CheckoutApi($defaultApiClient);

        $lineItems = array();
        $by_gc = $cart->getInfo('apply_gc');
        if ($by_gc > 0) {
            $total_amount = $cart->getTotal() - $by_gc;
            $PriceMoney = new \SquareConnect\Model\Money;
            $PriceMoney->setCurrency($this->currency_code);
            $PriceMoney->setAmount($Cur->toInt($total_amount));
            $itm = new \SquareConnect\Model\CreateOrderRequestLineItem;
            $itm
                ->setName($LANG_SHOP['all_items'])
                ->setQuantity('1')
                ->setBasePriceMoney($PriceMoney);
            //Puts our line item object in an array called lineItems.
            array_push($lineItems, $itm);
        } else {
            $shipping = $cart->shipping;
            $tax = $cart->tax;
            $idx = -1;
            foreach ($cart->getItems() as $Item) {
                $idx++;
                $P = $Item->getProduct();

                $PriceMoney = new \SquareConnect\Model\Money;
                $PriceMoney->setCurrency($this->currency_code);
                $Item->Price = $P->getPrice($Item->options);
                $PriceMoney->setAmount($Cur->toInt($Item->price));
                //$itm = new \SquareConnect\Model\CreateOrderRequestLineItem;
                $itm = new \SquareConnect\Model\OrderLineItem;

                $opts = $P->getOptionDesc($Item->options);
                $dscp = $Item->description;
                if (!empty($opts)) {
                    $dscp .= ' : ' . $opts;
                }
                $itm->setUid($idx);
                $itm->setName($dscp);
                $itm->setQuantity((string)$Item->quantity);
                $itm->setBasePriceMoney($PriceMoney);

                // Add tax, if applicable
                /*if ($Item->taxable) {
                    $TaxMoney = new \SquareConnect\Model\Money;
                    $TaxMoney->setCurrency($this->currency_code);
                    $taxObj = new \SquareConnect\Model\OrderLineItemTax(
                        array(
                            'percentage' => (string)($_SHOP_CONF['tax_rate'] * 100),
                            'name' => 'Sales Tax',
                        )
                    );
                    $tax = $Item->price * $Item->quantity * $_SHOP_CONF['tax_rate'];
                    $tax = $Cur->toInt($tax);
                    $TaxMoney->setAmount($tax);
                    $taxObj->setAppliedMoney($TaxMoney);
                    $itm->setTaxes(array($taxObj));
                    $itm->setTotalTaxMoney($tax);
                }*/
                $shipping += $Item->shipping;

                //Puts our line item object in an array called lineItems.
                array_push($lineItems, $itm);
            }
        }

        // Add a line item for the total tax charge
        if ($cart->tax > 0) {
            $TaxMoney = new \SquareConnect\Model\Money;
            $TaxMoney->setCurrency($this->currency_code)
                ->setAmount($Cur->toInt($cart->tax));
            $itm = new \SquareConnect\Model\OrderLineItem;
            $itm->setName($LANG_SHOP['tax'])
                ->setUid('__tax')
                ->setQuantity('1')
                ->setBasePriceMoney($TaxMoney);
            array_push($lineItems, $itm);
        }

        // Add a line item for the total shipping charge
        if ($shipping > 0) {
            $ShipMoney = new \SquareConnect\Model\Money;
            $ShipMoney->setCurrency($this->currency_code)
                ->setAmount($Cur->toInt($shipping));
            $itm = new \SquareConnect\Model\OrderLineItem;
            $itm->setName($LANG_SHOP['shipping'])
                ->setUid('__shipping')
                ->setQuantity('1')
                ->setBasePriceMoney($ShipMoney);
            array_push($lineItems, $itm);
        }

        // Create an Order object using line items from above
        $order = new \SquareConnect\Model\CreateOrderRequest();
        $order
            ->setIdempotencyKey(uniqid())
            ->setReferenceId($cart->cartID())
            //sets the lineItems array in the order object
            ->setLineItems($lineItems);

        $checkout = new \SquareConnect\Model\CreateCheckoutRequest();
        $checkout
            ->setPrePopulateBuyerEmail($cart->getInfo('payer_email'))
            ->setIdempotencyKey(uniqid())        //uniqid() generates a random string.
            ->setOrder($order)          //this is the order we created in the previous step
            ->setRedirectUrl($this->ipn_url . '?thanks=square');

        $url = '';
        $gatewayVars = array();
        try {
            $result = $checkoutClient->createCheckout(
                $locationId,
                $checkout
            );
            //Save the checkout ID for verifying transactions
            $checkoutId = $result->getCheckout()->getId();
            //Get the checkout URL that opens the checkout page.
            $url = $result->getCheckout()->getCheckoutPageUrl();
        } catch (Exception $e) {
            COM_setMsg('Exception when calling CheckoutApi->createCheckout: ', $e->getMessage(), PHP_EOL);
        }
        $url_parts = parse_url($url);
        parse_str($url_parts['query'], $q_parts);
        foreach ($q_parts as $key=>$val) {
            $gatewayVars[] = '<input type="hidden" name="' . $key . '" value="' . $val . '"/>';
        }
        $gateway_vars = implode("\n", $gatewayVars);
        return $gateway_vars;
    }


    /**
     * Get the command value and template name for the requested button type.
     *
     * @param   string  $btn_type   Type of button being created
     * @return  array       Array ('cmd'=>command, 'tpl'=>template name
     */
    private function gwButtonType($btn_type='')
    {
        switch ($btn_type) {
        case 'donation':
            $cmd = '_donations';
            $tpl = 'donation';
            break;
        case 'buy_now':
        default:
            $cmd = '_xclick';
            $tpl = 'buy_now';
            break;
        }
        return array('cmd' => $cmd, 'tpl' => $tpl);
    }


    /**
     * Get the values to show in the "Thank You" message when a customer
     * returns to our site.
     *
     * @uses    getMainUrl()
     * @uses    Gateway::Description()
     * @return  array       Array of name=>value pairs
     */
    public function thanksVars()
    {
        $R = array(
            'gateway_url'   => self::getMainUrl(),
            'gateway_name'  => self::Description(),
        );
        return $R;
    }


    /**
     * Verify that a given email address is one of our business addresses.
     * Called during IPN validation.
     *
     * @param   string  $email  Email address to check (receiver_email)
     * @return  boolean         True if valid, False if not.
     */
    public function isBusinessEmail($email)
    {
        switch ($email) {
        case $this->getConfig('bus_prod_email'):
        case $this->getConfig('micro_prod_email'):
        case $this->getConfig('bus_test_email'):
        case $this->getConfig('micro_test_email'):
            $retval = true;
            break;
        default:
            $retval = false;
            break;
        }
        return $retval;
    }


    /**
     * Get the custom string properly formatted for the gateway.
     *
     * @return  string      Formatted custom string
     */
    protected function PrepareCustom()
    {
        return str_replace('"', '\'', serialize($this->custom));
    }


    /**
     * Get the variables to display with the IPN log.
     * This gateway does not have any particular log values of interest.
     *
     * @param  array   $data       Array of original IPN data
     * @return array               Name=>Value array of data for display
     */
    public function ipnlogVars($data)
    {
        return array();
    }


    /**
     * Get a logo image to show on the order as the payment method.
     *
     * @return  string      HTML for logo image
     */
    public function getLogo()
    {
        global $_CONF, $_SHOP_CONF;
        return COM_createImage($_CONF['site_url'] . '/' .
            $_SHOP_CONF['pi_name'] . '/images/gateways/square-logo-100-27.png');
    }


    /**
     * Get the form method to use with the final checkout button.
     * Return POST by default
     *
     * @return  string  Form method
     */
    public function getMethod()
    {
        return 'get';
    }


    /**
     *   Get the form action URL.
     *   This gets the checkout URL from Square that is created by submitting
     *   an order.
     *
     *   @return string      URL to payment processor
     */
    public function getActionUrl()
    {
        //return 'https://connect.squareup.com/v2/checkout';
        return $this->api_url . '/v2/checkout';
    }


    /**
     * Additional actions to take after saving the configuration.
     *  - Subscribe to webhooks
     */
    protected function _postSaveConfig()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POSTFIELDS, '[PAYMENT_UPDATED]');
        curl_setopt($ch, CURLOPT_PUT, true);
        foreach (array('sb', 'prod') as $env) {
            if (empty($this->getConfig($env . '_token'))) continue;
            $url = 'https://connect.squareup.com/v2/' . $this->getConfig($env . '_loc_id') . '/webhooks';
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $this->getConfig($env . '_token'),
                'Content-Type: application/json',
            ) );
        /*curl_setopt($ch, CURLOPT_ENCODING,       'gzip');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR,    1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT,        10);*/
            $result = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            var_dump($code);die;
            var_dump($result);die;
        }
    }


    /**
     * Get the transaction data using the ID supplied in the IPN.
     *
     * @param   string  $trans_id   Transaction ID from IPN
     * @return  array   Array of transaction data.
     */
    public function getTransaction($trans_id)
    {
        //$trans_id = SHOP_getVar($_GET, 'transactionId');
        if (empty($trans_id)) {
            return false;
        }
        // Create and configure a new API client object
        $defaultApiConfig = new \SquareConnect\Configuration();
        $defaultApiConfig->setAccessToken($this->token);
        $defaultApiConfig->setHost($this->api_url);
        $defaultApiClient = new \SquareConnect\ApiClient($defaultApiConfig);

        $api = new \SquareConnect\Api\TransactionsApi();
        $api->setApiClient($defaultApiClient);
        $resp = $api->retrieveTransaction($this->loc_id, $trans_id);
        $resp = json_decode($resp,true);
        return $resp;
    }


    /**
     * Get additional javascript to be attached to the checkout button.
     * Square doesn't support a "cancel URL", so don't finalize the cart.
     *
     * @param   object  $cart   Shopping cart object
     * @return  string  Javascript commands.
     */
    public function getCheckoutJS($cart)
    {
        return '';
    }

}   // class square

?>
