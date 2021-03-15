<?php
/**
 * Paypal Checkout bundled gateway plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\ppcheckout;
use Shop\Company;
use Shop\Address;
use Shop\Currency;
use Shop\Order;
use Shop\Shipper;
use Shop\Models\OrderState;
use Shop\Models\CustomInfo;
use Shop\Template;
use Shop\Cache;
use Shop\Models\Token;
use Shop\Models\Payout;
use Shop\Models\PayoutHeader;


/**
 * Class for Paypal Checkout gateway plugin.
 * @package shop
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = 'ppcheckout';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'PayPal Checkout';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'Checkout with PayPal';

    /** Flag this gateway as bundled with the Shop plugin.
     * Gateway version will be set to the Shop plugin's version.
     * @var integer */
    protected $bundled = 1;

    /** Paypal API URL, sandbox or production.
     * @var string */
    private $api_url;

    /** Order Intent.
     * @var string */
    private $intent = 'capture';
    //private $intent = 'authorize';


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        $supported_currency = array(
            'USD', 'AUD', 'CAD', 'EUR', 'GBP', 'JPY', 'NZD', 'CHF', 'HKD',
            'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'HUF', 'CZK', 'ILS', 'MXN',
            'PHP', 'TWD', 'THB',
        );

        // Create the configuration item definitions
        $this->cfgFields= array(
            'prod' => array(
                'webhook_id'    => 'string',
                'api_username'  => 'password',
                'api_password'  => 'password',
            ),
            'test' => array(
                'webhook_id'    => 'string',
                'api_username'  => 'password',
                'api_password'  => 'password',
            ),
            'global' => array(
                'test_mode'         => 'checkbox',
                'fee_percent'       => 'string',
                'fee_fixed'         => 'string',
                'supports_payouts'  => 'checkbox',
            ),
        );

        // Set default config values.
        $this->config = array(
            'global' => array(
                'test_mode'         => '1',
                'fee_percent'       => 2.9,
                'fee_fixed'         => .3,
                'supports_payouts'  => '0',
            ),
            'prod' => array(
            ),
            'test' => array(
            ),
        );

        // This gateway can service all button type by default
        $this->services = array(
            'pay_now'   => 1,
            'subscribe' => 1,
            'checkout'  => 1,
            'terms'     => 1,
        );

        // Call the parent constructor to initialize the common variables.
        parent::__construct($A);

        // Set the gateway URL depending on whether we're in test mode or not
        if ($this->isSandbox()) {
            $this->gw_url = 'https://www.sandbox.paypal.com';
            $this->api_url = 'https://api.sandbox.paypal.com';
        } else {
            $this->gw_url = 'https://www.paypal.com';
            $this->api_url = 'https://api.paypal.com';
        }

        // If the configured currency is not one of the supported ones,
        // this gateway cannot be used, so disable it.
        if (!in_array($this->currency_code, $supported_currency)) {
            $this->enabled = 0;
        }
    }


    /**
     * Get the main gateway url.
     * This is used to tell the buyer where they can log in to check their
     * purchase.  For PayPal this is the same as the production action URL.
     *
     * @return  string      Gateway's home page
     */
    public function getMainUrl()
    {
        return $this->gw_url;
    }


    /**
     * Create the checkout button for a cart.
     *
     * @param   object  $Cart   Cart object
     * @param   string  $text   Optional button text override
     * @return  string      HTML for checkout button
     */
    public function checkoutButton($Cart, $text='')
    {
        static $have_js = false;

        if (!$this->Supports('checkout')) {
            return '';
        }

        if (!$have_js) {
            $outputHandle = \outputHandler::getInstance();
            $outputHandle->addRaw(
                '<script src="https://www.paypal.com/sdk/js?client-id=' . $this->getConfig('api_username') .
                '&currency=' . $Cart->getCurrency()->getCode() . 
                '&intent=' . $this->intent .
                '"' .
                ' data-order-id="' . $Cart->getOrderID() . '"' .
                ' data-page-type="checkout"' .
                '></script>'
            );
            $have_js = true;
        }

        $T = new \Template(__DIR__ . '/templates');
        $T->set_file('js', 'checkout.thtml');
        $T->set_var(array(
            'hook_url' => SHOP_URL . '/hooks/webhook.php?_gw=' . $this->gw_name,
            'shop_url' => SHOP_URL,
            'gw_name' => $this->gw_name,
            'cur_code' => $Cart->getCurrency()->getCode(),
            'order_total' => $Cart->getTotal(),
            'order_id' => $Cart->getOrderId(),
            'uid' => $Cart->getUid(),
            'success_url' => SHOP_URL . '/index.php',
            'cancel_url' => $Cart->cancelUrl(),
            'intent' => strtoupper($this->intent),
        ) );
        $T->parse('output', 'js');
        $btn = $T->finish($T->get_var('output'));
        return $btn;
    }


    /**
     * Get the form variables for the cart checkout button.
     * This gateway uses javascript, no form vars to define.
     *
     * @param   object      $cart   Shopping Cart Object
     * @return  string      Gateay variable input fields
     */
    public function gatewayVars($cart)
    {
        return '';
    }


    /**
     * Get the variables to display with the IPN log.
     * This gets the variables from the gateway's IPN data into standard
     * array values to be displayed in the IPN log view.
     *
     * @param   array   $data       Array of original IPN data
     * @return  array               Name=>Value array of data for display
     */
    public function ipnlogVars($data)
    {
        $retval = array();
        /*if (!is_array($data)) {
            return array();
        }
        $verified = 'true';
        $pmt_status = 'paid';
        $buyer_email = '';
        if (isset($data['event_type'])) {   // webhook
            if (isset($data['resource']['invoice']['payments']['transactions'])) {
                $info = array_pop($data['resource']['invoice']['payments']['transactions']);
            }
            if (isset($data['resource']['invoice']
                ['primary_recipients']
                [0]['billing_info']['email_address'])) {
                $buyer_email = $data['resource']['invoice']
                    ['primary_recipients']
                    [0]['billing_info']['email_address'];
            }
        }
        $retval = array(
            'verified'      => $verified,
            'pmt_status'    => $pmt_status,
            'buyer_email'   => $buyer_email,
        );*/
        return $retval;
    }


    /**
     * Get the Paypal API token to be used for web requests.
     *
     * @return  string  API token value
     */
    public function getBearerToken()
    {
        $cache_key = 'paypal-oath2-token';
        $auth = Cache::get($cache_key);
        if ($auth === NULL) {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $this->api_url . '/v1/oauth2/token',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
                CURLOPT_USERPWD => $this->getConfig('api_username') . ':' . $this->getConfig('api_password'),
                CURLOPT_HTTPHEADER  => array (
                    'Accept: application/json',
                ),
                CURLOPT_RETURNTRANSFER  => true,
            ) );
            $result = curl_exec($ch);
            curl_close($ch);
            $auth = json_decode($result, true);
            // Cache the auth token for its duration minus 1 hour, or 8 hours.
            // Only deduct the hour if the token expires in more than 2 hours.
            if ($auth['expires_in'] > 7200) {
                $auth['expires_in'] -= 3600;
            }
            $expire_seconds = min($auth['expires_in'] , 28800);
            Cache::set(
                $cache_key,
                $auth,
                'auth',
                (int)($expire_seconds / 60)
            );
        }
        $access_token = isset($auth['access_token']) ? $auth['access_token'] : NULL;
        return $access_token;
    }


    /**
     * Get the invoice terms string to pass to Paypal.
     * Returns the closest Paypal terms string to match the days due.
     *
     * @param   integer $due_days   Due days (terms)
     * @return  string      Proper terms string for Paypal
     */
    private function getInvoiceTerms($due_days=0)
    {
        $due_days = (int)$due_days;
        if ($due_days == 0) {
            $retval = 'DUE_ON_RECEIPT';
        } else {
            $day_arr = array(10, 15, 30, 45, 60, 90);
            $retval = 90;
            foreach ($day_arr as $days) {
                if ($due_days <= $days) {
                    $retval = $days;
                    break;
                }
            }
            $retval = 'NET_' . $retval;
        }
        return $retval;
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
        global $_CONF, $LANG_SHOP;

        $access_token = $this->getBearerToken();
        if (!$access_token) {
            SHOP_log("Could not get Paypal access token", SHOP_LOG_ERROR);
            return false;
        }

        $Shop = new Company();
        $Currency = $Order->getCurrency();
        $Billto = $Order->getBillto();
        $Shipto = $Order->getShipto();
        $Order->updateStatus(OrderState::INVOICED);
        $order_num = $Order->getOrderId();

        $A = array(
            'detail' => array(
                'invoice_number' => $Order->getInvoiceNumber(),
                'reference' => $order_num,
                'currency_code' => $Currency->getCode(),
                'payment_term' => array(
                    'term_type' => $this->getInvoiceTerms($terms_gw->getConfig('net_days')),
                ),
            ),
            'invoicer' => array(
                'name' => array(
                    'business_name' => $Shop->getCompany(),
                ),
                'address' => array(
                    'address_line_1' => $Shop->getAddress1(),
                    'address_line_2' => $Shop->getAddress2(),
                    'admin_area_2' => $Shop->getCity(),
                    'admin_area_1' => $Shop->getState(),
                    'postal_code' => $Shop->getPostal(),
                    'country_code' => $Shop->getCountry(),
                ),
                'website' => $_CONF['site_url'],
            ),
            'primary_recipients' => array(
                array(
                    'billing_info' => array(
                        'name' => array(
                            'given_name' => $Billto->parseName('fname'),
                            'surname' => $Billto->parseName('lname'),
                        ),
                        'address' => array(
                            'address_line_1'    => $Billto->getAddress1(),
                            'address_line_2'    => $Billto->getAddress2(),
                            'admin_area_2'      => $Billto->getCity(),
                            'admin_area_1'      => $Billto->getState(),
                            'postal_code'       => $Billto->getPostal(),
                            'country_code'      => $Billto->getCountry(),
                        ),
                        'email_address' => $Order->getBuyerEmail(),
                    ),
                    'shipping_info' => array(
                        'name' => array(
                            'given_name' => $Shipto->parseName('fname'),
                            'surname' => $Shipto->parseName('lname'),
                        ),
                        'address' => array(
                            'address_line_1'    => $Shipto->getAddress1(),
                            'address_line_2'    => $Shipto->getAddress2(),
                            'admin_area_2'      => $Shipto->getCity(),
                            'admin_area_1'      => $Shipto->getState(),
                            'postal_code'       => $Shipto->getPostal(),
                            'country_code'      => $Shipto->getCountry(),
                        ),
                    ),
                ),
            ),
            'items' => array(
            ),
            'configuration' => array(
                'partial_payment' => array(
                    'allow_partial_payment' => false,
                ),
                'tax_calculated_after_discount' => true,
                'tax_inclusive' => false,
            ),
            'amount' => array(
                'breakdown' => array(
//                    'tax_total' => array(
//                        'currency_code' => $Currency->getCode(),
//                        'value' => $Currency->FormatValue($Order->getTax()),
//                    ),
                    'shipping' => array(
                        'amount' => array(
                            'currency_code' => $Currency->getCode(),
                            'value' => sprintf('%.02f', $Order->getShipping()),
                        ),
                    ),
                ),
            ),
        );
        if ($Order->getShipping() > 0 && $Order->getTaxShipping()) {
            $A['amount']['breakdown']['shipping']['tax'] = array(
                'currency_code' => $Currency->getCode(),
                'percent' => $Order->getTaxRate() * 100,
            );
        }
        /*if ($Order->getHandling() > 0) {
            $handling = $Order->getHandling();
            $A['amount']['breakdown']['shipping']['tax'] = array(
                'currency_code' => $Currency->getCode(),
                'percent' => $Order->getTaxRate() * 100,
            );
        }*/

        foreach ($Order->getItems() as $OI) {
            $item = array(
                'name' => $OI->getProduct()->getName(),
                'description' => $OI->getDscp(),
                'quantity' => $OI->getQuantity(),
                'unit_amount' => array(
                    'currency_code' => $Currency->getCode(),
                    'value' => $Currency->FormatValue($OI->getNetPrice()),
                ),
                'unit_of_measure' => 'QUANTITY',
            );
            $opts = $OI->getOptionsText();
            if (!empty($opts)) {
                $item['description'] .= ' ' . implode(', ', $opts);
            }
            if ($OI->getProduct()->isTaxable()) {
                $item['tax'] = array(
                    'name' => $LANG_SHOP['sales_tax'],
                    'percent' => $Order->getTaxRate() * 100,
                );
            }
            $A['items'][] = $item;
        }
        //var_dump($item);die;
        //var_export($A);die;

        // Create the draft invoice
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->api_url . '/v2/invoicing/invoices',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token,
            ),
            CURLOPT_POSTFIELDS => json_encode($A),
        ) );
        $inv = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // If the invoice was created successfully, send to the buyer
        if ($http_code == 201) {
            $Order->updateStatus(OrderState::INVOICED);
            $json = json_decode($inv, true);
            if (isset($json['href'])) {
                $ch = curl_init();
                curl_setopt_array($ch, array(
                    CURLOPT_URL => $json['href'] . '/send',
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $access_token,
                    ),
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => '{"send_to_recipient": true}',
                ) );
                $send_response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                if ($http_code > 299) {
                    SHOP_log("Error sending invoice for $order_num. Code $http_code, Text: $send_response", SHOP_LOG_ERROR);
                    return false;
                }
            }
        } else {
            SHOP_log("Error creating invoice for $order_num", SHOP_LOG_ERROR);
            SHOP_Log("Data: " . var_export($inv, true));
            return false;
        }
        return true;
    }


    /**
     * Expose the API url for webhook verification.
     *
     * @return  string      API URL
     */
    public function getApiUrl()
    {
        return $this->api_url;
    }


    /**
     * Get the webhook ID depending on whether in test or production mode.
     *
     * @return  string      Webhook ID from Paypal
     */
    public function getWebhookID()
    {
        return $this->getConfig('webhook_id');
    }


    /**
     * Get an instance of a Paypal API client.
     *
     * @return  object      Paypal API client
     */
    public function getApiClient()
    {
        static $apiClient = NULL;
        if ($apiClient === NULL) {
            $this->loadSDK();
            if ($this->isSandbox()) {
                $env = new \PayPalCheckoutSdk\Core\SandboxEnvironment(
                    $this->getConfig('api_username'),
                    $this->getConfig('api_password')
                );
            } else {
                $env = new \PayPalCheckoutSdk\Core\ProductionEnvironment(
                    $this->getConfig('api_username'),
                    $this->getConfig('api_password')
                );
            }
            $apiClient = new \PayPalCheckoutSdk\Core\PayPalHttpClient($env);
        }
        return $apiClient;
    }


    /**
     * Capture a previous authorization.
     *
     * @param   string  $authorizationId    Paypal-provided authorization ID
     * @return  object      Response to capture request
     */
    public function captureAuth($authorizationId)
    {
        $client = $this->getApiClient();
        $request = new \PayPalCheckoutSdk\Payments\AuthorizationsCaptureRequest($authorizationId);
        $request->body = '{}';      // Null body
        try {
            $response = $client->execute($request);
        } catch (\PaypalCheckoutSdk\PayPalHttp\HttpException $e) {
            SHOP_log("Error capturing $authorizationId, response " . var_export($response,true));
            $response = NULL;
        }
        SHOP_log('Capture response: ' . var_export($response,true), SHOP_LOG_DEBUG);
        return $response;
    }


    /**
     * Get the details of a captured payment.
     *
     * @param   string  $captureId  Paypal payment ID
     * @return  string      JSON response string
     */
    public function getCaptureDetails($captureId)
    {
        $client = $this->getApiClient();
        $request = new \PayPalCheckoutSdk\Payments\CapturesGetRequest($captureId);
        $request->body = '{}';      // Null body
        try {
            $response = $client->execute($request);
        } catch (\PaypalCheckoutSdk\PayPalHttp\HttpException $e) {
            SHOP_log("Error capturing $captureId, response " . var_export($response,true));
            $response = NULL;
        }
        SHOP_log('Capture details: ' . var_export($response,true), SHOP_LOG_DEBUG);
        return $response;
    }


    /**
     * Retrieve the details of an order from Paypal.
     *
     * @param   string  $orderId    Paypal order ID (not the plugin order_id)
     * @return  string      JSON response string
     */
    public function getOrderDetails($orderId)
    {
        $client = $this->getApiClient();
        $request = new \PayPalCheckoutSdk\Orders\OrdersGetRequest($orderId);
        $request->body = '{}';      // Null body
        try {
            $response = $client->execute($request);
        } catch (\PaypalCheckoutSdk\PayPalHttp\HttpException $e) {
            SHOP_log("Error retrieving order $orderId, response " . var_export($response,true));
            $response = NULL;
        }
        SHOP_log('Capture details: ' . var_export($response,true), SHOP_LOG_DEBUG);
        return $response;
    }


    /**
     * Retrieve the details of a webhook from Paypal.
     *
     * @param   string  $whId       Webhook ID (`WH-XXXX...`)
     * @return  string      JSON response string
     */
    public function getWebhookDetails($whId)
    {
        $access_token = $this->getBearerToken();
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->api_url . '/v1/notifications/webhooks-events/' . $whId,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token,
            ),
        ) );
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }


    /**
     * Check that a valid config has been set for the environment.
     *
     * @return  boolean     True if valid, False if not
     */
    public function hasValidConfig()
    {
        return !empty($this->getConfig('webhook_id')) &&
            !empty($this->getConfig('api_username')) &&
            !empty($this->getConfig('api_password'));
    }


    public function calcPayoutFee($amount)
    {
        $fee = ((float)$amount * ($this->getConfig('fee_percent') / 100)) +
            $this->getConfig('fee_fixed');
        return $fee;
    }


    public function calcPayout($amount)
    {

        $payout = (float)$amount - $this->calcPayoutFee($amount);
        return $payout;
    }


    public function sendPayouts(PayoutHeader $Header, array $Payouts)
    {
        $A = array(
            'sender_batch_header' => array(
                'sender_batch_id' => uniqid(),
                'email_subject' => $Header['email_subject'],
                'email_message' => $Header['email_message'],
            ),
            'items' => array(),
        );

        foreach ($Payouts as $id=>$Payout) {
            $amount = (float)$Payout['amount'];
            $amount -= $this->calcPayoutFee($amount);
            if ($Payout['amount'] >= $this->payout_threshold) {
                $A['items'][] = array(
                    'recipient_type' => 'EMAIL',
                    'amount' => array(
                        'value' =>  (float)$Payout['amount'],
                        'currency' => $Payout['currency'],
                    ),
                    'note' => $Payout['message'],
                    'sender_item_id' => $Payout['type'] . '_' . $Payout['uid'] . '_' . $id,
                    'receiver' => 'lee-buyer@leegarner.com', //$Payout['email'],
                );
            }
        }
        if (!empty($A['items'])) {
            $access_token = $this->getBearerToken();
            $ppRequestId = Token::uuid();
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $this->api_url . '/v1/payments/payouts',
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token,
                    'PayPal-Request-Id ' . $ppRequestId,
                ),
                CURLOPT_POSTFIELDS => json_encode($A),
            ) );
            $resp = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($http_code == 201) {
                $resp = json_decode($resp, true);
                foreach ($Payouts as $Payout) {
                    $Payout['txn_id'] = $resp['batch_header']['payout_batch_id'];
                }
            }
        }
    }

}
