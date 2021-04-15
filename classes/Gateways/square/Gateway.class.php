<?php
/**
 * Gateway implementation for Square (squareup.com).
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
namespace Shop\Gateways\square;
use Shop\Config;
use Shop\Currency;
use Shop\Order;
use Shop\Cart;
use Shop\Company;
use Shop\Models\OrderState;
use Square\SquareClient;
use Square\Environment;
use Square\Models\Money;
use Square\Models\OrderLineItem;
use Square\Models\CreateOrderRequest;
use Square\Models\Order as sqOrder;
use LGLib\NameParser;


/**
 * Class for Square payment gateway.
 * @package shop
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = 'square';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'SquareUp.com';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'SquareUp Payments';

    /** Flag this gateway as bundled with the Shop plugin.
     * @var integer */
    protected $bundled = 1;

    /** Square location value.
     * @var string */
    private $loc_id;

    /** Square App ID.
     * @var string */
    private $appid;

    /** Square Token.
     * @var string */
    private $token;

    /** Square API URL. Set to production or sandbox in constructor.
     * @var string */
    private $api_url;

    /** Internal API client to facilitate reuse.
     * @var object */
    private $_api_client = NULL;

    /** API errors.
     * @var object */
    private $_errors = NULL;


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
            'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'CZK', 'ILS', 'MXN',
            'PHP', 'TWD', 'THB', 'MYR', 'RUB',
        );

        // Set up the configuration field definitions.
        $this->cfgFields = array(
            'prod' => array(
                'loc_id'   => 'password',
                'appid'    => 'password',
                'token'    => 'password',
                'webhook_sig_key' => 'password',
            ),
            'test' => array(
                'loc_id'     => 'password',
                'appid'      => 'password',
                'token'      => 'password',
                'webhook_sig_key' => 'password',
            ),
            'global' => array(
                'test_mode' => 'checkbox',
                'cust_ref_prefix' => 'string',
            ),
        );
        // Set defaults
        $this->config = array(
            'global' => array(
                'cust_ref_prefix' => 'glshop_',
                'test_mode'         => '1',
            ),
        );

        // Set the only service supported
        $this->services = array('checkout' => 1, 'terms' => 0);

        // Call the parent constructor to initialize the common variables.
        parent::__construct($A);

        // Set the gateway URL depending on whether we're in test mode or not
        if ($this->isSandbox()) {
            // Test settings
            $this->api_url = 'https://connect.squareupsandbox.com';
        } else {
            // Production settings
            $this->api_url = 'https://connect.squareup.com';
        }
        $this->loc_id = $this->getConfig('loc_id');
        $this->appid = $this->getConfig('appid');
        $this->token = $this->getConfig('token');
        $this->gw_url = NULL;   // Normal gateway action url not used

        // If the configured currency is not one of the supported ones,
        // this gateway cannot be used, so disable it.
        if (!in_array($this->currency_code, $supported_currency)) {
            $this->enabled = 0;
        }
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
     * Create a Square order object for the order/cart.
     *
     * @param   object  $Ord    Cart or order object
     * @return  object      Square OrderRequest object
     */
    private function _createOrderRequest($Ord)
    {
        global $LANG_SHOP;

        $this->_getApiClient();

        $accessToken = $this->token;
        $locationId = $this->loc_id;
        $Cur = Currency::getInstance();
        $shipping = 0;
        $tax = 0;
        $lineItems = array();
        $orderTaxes = array();
        $by_gc = $Ord->getGC();
        if ($by_gc > 0) {
            $total_amount = $Ord->getTotal() - $by_gc;
            $PriceMoney = new \Square\Models\Money;
            $PriceMoney->setCurrency($this->currency_code);
            $PriceMoney->setAmount($Cur->toInt($total_amount));
            $itm = new \Square\Models\OrderLineItem('1');
            $itm->setName($LANG_SHOP['all_items']);
            $itm->setBasePriceMoney($PriceMoney);
            //Puts our line item object in an array called lineItems.
            array_push($lineItems, $itm);
        } else {
            $shipping = $Ord->getShipping();
            $tax = $Ord->getTax();
            $idx = -1;
            foreach ($Ord->getItems() as $Item) {
                $idx++;
                $opts = implode(', ', $Item->getOptionsText());
                $dscp = $Item->getDscp();
                if (!empty($opts)) {
                    $dscp .= ' : ' . $opts;
                }
                $PriceMoney = new Money;
                $PriceMoney->setAmount($Cur->toInt($Item->getPrice()));
                $PriceMoney->setCurrency($this->currency_code);
                $LineItem = new \Square\Models\OrderLineItem((string)$Item->getQuantity());
                $LineItem->setName($dscp);
                $LineItem->setUid($idx);
                $LineItem->setBasePriceMoney($PriceMoney);
                $lineItems[] = $LineItem;
                $shipping += $Item->getShipping();
            }

            if ($Ord->getTax() > 0) {
                $TaxMoney = new Money;
                $TaxMoney->setCurrency($this->currency_code);
                $TaxMoney->setAmount($Cur->toInt($Ord->getTax()));
                $OrderTax = new \Square\Models\OrderLineItem('1');
                $OrderTax->setName($LANG_SHOP['sales_tax']);
                $OrderTax->setUid('__tax');
                $OrderTax->setBasePriceMoney($TaxMoney);
                $lineItems[] = $OrderTax;
            }
        }

        $sqOrder = new sqOrder($locationId);

        // Add a line item for the total shipping charge
        if ($shipping > 0) {
            $ShipMoney = new Money;
            $ShipMoney->setCurrency($this->currency_code);
            $ShipMoney->setAmount($Cur->toInt($shipping));
            $itm = new \Square\Models\OrderLineItem('1');
            $itm->setName($LANG_SHOP['shipping']);
            $itm->setUid('__shipping');
            $itm->setBasePriceMoney($ShipMoney);
            array_push($lineItems, $itm);
        }

        $sqOrder->setReferenceId($Ord->getOrderID());
        $sqOrder->setMetadata(array(
            'order_ref' => $Ord->getOrderId(),
        ) );
        $sqOrder->setLineItems($lineItems);
        $req = new CreateOrderRequest;
        $req->setIdempotencyKey(uniqid());
        $req->setOrder($sqOrder);
        return $req;
    }


    /**
     * Get the form variables for the purchase button.
     *
     * @uses    Gateway::Supports()
     * @uses    _encButton()
     * @uses    getActionUrl()
     * @param   object  $cart   Shopping cart object
     * @return  string      HTML for purchase button
     */
    public function gatewayVars($cart)
    {
        if (!$this->Supports('checkout')) {
            return '';
        }

        $cartID = $cart->CartID();
        $shipping = 0;
        $Cur = Currency::getInstance();

        $accessToken = $this->token;
        $locationId = $this->loc_id;

        // Create and configure a new API client object
        $ApiClient = $this->_getApiClient();
        $checkoutApi = $ApiClient->getCheckoutApi();

        $order_req = $this->_createOrderRequest($cart);
        $checkout = new \Square\Models\CreateCheckoutRequest(
            uniqid(),
            $order_req
        );
        $checkout->setRedirectUrl($this->returnUrl($cart->getOrderID(), $cart->getToken()));
        $checkout->setPrePopulateBuyerEmail($cart->getInfo('payer_email'));

        $apiResponse = $checkoutApi->createCheckout($locationId, $checkout);
        if ($apiResponse->isSuccess()) {
            $createCheckoutResponse = $apiResponse->getResult();
            $url = $createCheckoutResponse->getCheckout()->getCheckoutPageUrl();
        } else {
            $this->_errors = $apiResponse->getErrors();
            return false;
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
     * @uses    Gateway::getDscp()
     * @return  array       Array of name=>value pairs
     */
    public function thanksVars()
    {
        $R = array(
            'gateway_url'   => self::getMainUrl(),
            'gateway_name'  => self::getDscp(),
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
            $result = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            var_dump($code);die;
            var_dump($result);die;
        }
    }


    /**
     * Get the Square API client object.
     *
     * @return  object      SquareClient object
     */
    private function _getApiClient()
    {
        if ($this->_api_client === NULL) {
            $this->loadSDK();
            $this->_api_client = new SquareClient(array(
                'accessToken' => $this->token,
                'environment' => $this->isSandbox() ?
                    Environment::SANDBOX :
                    Environment::PRODUCTION,
            ) );
        }
        return $this->_api_client;
    }


    /**
     * Get the transaction data using the ID supplied in the IPN.
     *
     * @param   string  $trans_id   Transaction ID from IPN
     * @return  array   Array of transaction data.
     */
    public function getTransaction($trans_id)
    {
        if (empty($trans_id)) {
            return false;
        }

        $apiClient = $this->_getApiClient()->getOrdersApi();
        $order_ids = array($trans_id);
        $body = new \Square\Models\BatchRetrieveOrdersRequest($order_ids);
        $resp = $apiClient->batchRetrieveOrders($body);
        if ($resp->isSuccess()) {
            $retval = $resp->getResult();
        } else {
            $this->_errors = $resp->getErrors();
            $retval = false;
        }
        return $retval;
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


    /**
     * Set the return URL after payment is made.
     * Square adds transaction information and returns directly to the IPN
     * url for processing.
     *
     * @param   string  $cart_id    Cart order ID
     * @param   string  $token      Order token, to verify accessa
     * @return  string      URL to pass to the gateway as the return URL
     */
    protected function returnUrl($cart_id, $token)
    {
        return $this->ipn_url . '&o=' . $cart_id .
            '&t=' . $token;
    }


    /**
     * Retrieve an existing customer record from Square by reference ID.
     * Calls createCustomer() to create a new customer record if not found.
     *
     * @param   object  $Order      Order object
     * @return  object|false    Customer object, or false on error.
     */
    private function getCustomer($Order)
    {
        $cust_id = $this->getConfig('cust_ref_prefix') . $Order->getUid();
        $body = new \Square\Models\SearchCustomersRequest;
        $body->setLimit(1);
        $body->setQuery(new \Square\Models\CustomerQuery);
        $body->getQuery()->setFilter(new \Square\Models\CustomerFilter);
        $body->getQuery()->getFilter()->setCreationSource(new \Square\Models\CustomerCreationSourceFilter);
        $body->getQuery()->getFilter()->getCreationSource()->setValues([\Square\Models\CustomerCreationSource::THIRD_PARTY]);
        $body->getQuery()->getFilter()->getCreationSource()->setRule(\Square\Models\CustomerInclusionExclusion::INCLUDE_);
        $body->getQuery()->getFilter()->setReferenceId(new \Square\Models\CustomerTextFilter);
        $body->getQuery()->getFilter()->getReferenceId()->setExact($cust_id);
        $body->getQuery()->setSort(new \Square\Models\CustomerSort);
        $body->getQuery()->getSort()->setField(\Square\Models\CustomerSortField::CREATED_AT);
        $body->getQuery()->getSort()->setOrder(\Square\Models\SortOrder::ASC);

        $customersApi = $this->_getApiClient()->getCustomersApi();
        $apiResponse = $customersApi->searchCustomers($body);
        if ($apiResponse->isSuccess()) {
            $searchCustomersResponse = $apiResponse->getResult();
            if (empty($searchCustomersResponse->getCustomers())) {
                return $this->createCustomer($Order);
            } else {
                return $searchCustomersResponse->getCustomers()[0];
            }
        } else {
            $this->_errors = $apiResponse->getErrors();
            return false;
        }
    }


    /**
     * Create a new customer record with Square.
     * Called if getCustomer() returns an empty set.
     *
     * @param   object  $Order      Order object, to get customer info
     * @return  object|false    Customer object, or false if an error occurs
     */
    private function createCustomer($Order)
    {
        $Customer = $Order->getBillto();

        if (empty($Order->getBuyerEmail())) {
            $email = DB_getItem($_TABLES['users'], 'email', "uid = {$Order->getUid()}");
            $Order->setBuyerEmail($email);
        }
        $customersApi = $this->_getApiClient()->getCustomersApi();
        $body = new \Square\Models\CreateCustomerRequest;
        $body->setGivenName(NameParser::F($Customer->getName()));
        $body->setFamilyName(NameParser::L($Customer->getName()));
        $body->setEmailAddress($Order->getBuyerEmail());
        $body->setAddress(new \Square\Models\Address);
        $body->getAddress()->setAddressLine1($Customer->getAddress1());
        $body->getAddress()->setAddressLine2($Customer->getAddress2());
        $body->getAddress()->setLocality($Customer->getCity());
        $body->getAddress()->setAdministrativeDistrictLevel1($Customer->getState());
        $body->getAddress()->setPostalCode($Customer->getPostal());
        $body->getAddress()->setCountry($Customer->getCountry());
        $body->setPhoneNumber($Customer->getPhone());
        $body->setReferenceId($this->getConfig('cust_ref_prefix') . $Order->getUid());

        $apiResponse = $customersApi->createCustomer($body);
        if ($apiResponse->isSuccess()) {
            $createCustomerResponse = $apiResponse->getResult();
            return $createCustomerResponse->getCustomer();
        } else {
            $this->_errors = $apiResponse->getErrors();
            return false;
        }
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
        global $_CONF;

        $apiClient = $this->_getApiClient();
        $ordersApi = $apiClient->getOrdersApi();
        $order_req = $this->_createOrderRequest($Order);
        $apiResponse = $ordersApi->createOrder($order_req);
        if ($apiResponse->isSuccess()) {
            $createOrderResponse = $apiResponse->getResult();
        } else {
            $this->_errors = $apiResponse->getErrors();
            SHOP_log(
                __FUNCTION__ . ':' . __LINE__ . ': ' .
                print_r($this->_errors,true)
            );
            return false;
        }

        $net_days = (int)$terms_gw->getConfig('net_days');
        if ($net_days < 0) {
            $net_days = 0;
        }
        $due_dt = clone $_CONF['_now'];
        $due_dt->add(new \DateInterval("P{$net_days}D"));

        $order_id = $createOrderResponse->getOrder()->getId();
        $customer = $this->getCustomer($Order);
        $inv = new \Square\Models\Invoice;
        $inv->setLocationId($this->loc_id);
        $inv->setOrderId($order_id);
        $inv->setPrimaryRecipient(new \Square\Models\InvoiceRecipient);
        $inv->getPrimaryRecipient()->setCustomerId($customer->getId());
        //$inv->getPrimaryRecipient()->setEmailAddress($Order->getBuyerEmail());
        $inv_paymentRequests = [];

        /*$dt = clone $_CONF['_now'];
        $due = $dtobj->add(new \DateInterval($interval));*/

        $inv_paymentRequests[0] = new \Square\Models\InvoicePaymentRequest;
        $inv_paymentRequests[0]->setRequestMethod(\Square\Models\InvoiceRequestMethod::EMAIL);
        $inv_paymentRequests[0]->setRequestType(\Square\Models\InvoiceRequestType::BALANCE);
        $inv_paymentRequests[0]->setDueDate($due_dt->format('Y-m-d',true));
        $inv_paymentRequests[0]->setTippingEnabled(false);
        $inv_paymentRequests_0_reminders = [];

        $inv_paymentRequests_0_reminders[0] = new \Square\Models\InvoicePaymentReminder;
        $inv_paymentRequests_0_reminders[0]->setRelativeScheduledDays(-1);
        $inv_paymentRequests_0_reminders[0]->setMessage('Your invoice is due tomorrow');
        $inv_paymentRequests[0]->setReminders($inv_paymentRequests_0_reminders);

        $inv->setPaymentRequests($inv_paymentRequests);

        $inv->setInvoiceNumber($Order->getOrderId());
        $inv->setTitle(Company::getInstance()->getCompany());
        $inv->setDescription('We appreciate your business!');
        //$inv->setScheduledAt('2030-01-13T10:00:00Z');
        $body = new \Square\Models\CreateInvoiceRequest($inv);
        $body->setIdempotencyKey(uniqid());

        $invoicesApi = $this->_getApiClient()->getInvoicesApi();
        $apiResponse = $invoicesApi->createInvoice($body);
        if ($apiResponse->isSuccess()) {
            $createInvoiceResponse = $apiResponse->getResult();
            $Invoice = $createInvoiceResponse->getInvoice();
        } else {
            $this->_errors = $apiResponse->getErrors();
            return false;
        }
        // If we got this far, the Invoice was created.
        // Now publish it to send it to the buyer.
        $body = new \Square\Models\PublishInvoiceRequest(
            $Invoice->getVersion()
        );
        $body->setIdempotencyKey(uniqid());
        $apiResponse = $invoicesApi->publishInvoice($Invoice->getId(), $body);
        if ($apiResponse->isSuccess()) {
            $Order->updateStatus($terms_gw->getConfig('after_inv_status'));
            //$publishInvoiceResponse = $apiResponse->getResult();
        } else {
            $this->_errors = $apiResponse->getErrors();
            return false;
        }
        return true;
    }


    /**
     * Retrieve a payment using the payment intent ID.
     * Called from Webhook to get the order information.
     *
     * @param   string  $pmt_id     Payment Intent ID
     * @return  object      Square Payment object
     */
    public function getPayment($pmt_id)
    {
        $apiClient = $this->_getApiClient();
        $pmtApi = $apiClient->getPaymentsApi();
        return $pmtApi->getPayment($pmt_id);
    }


    /**
     * Retrieve the order from Square.
     *
     * @param   string  $order_id   Square order ID
     * @return  object      Square order
     */
    public function getOrder($order_id)
    {
        $apiClient = $this->_getApiClient();
        $api = $apiClient->getOrdersApi();
        return $api->retrieveOrder($order_id);
    }

    /*public function getInvoice($inv_id)
    {
        $apiClient = $this->_getApiClient();
        $api = $apiClient->getInvoicesApi();
        var_dump($api->getInvoice($inv_id));die;
    }

    public function getOrders($ord_ids)
    {
        $apiClient = $this->_getApiClient();
        $api = $apiClient->getOrdersApi();
        if (!is_array($ord_ids)) {
            $ord_ids = array($ord_ids);
        }
        $req = new \Square\Models\BatchRetrieveOrdersRequest($ord_ids);
        return $api->batchRetrieveOrders($this->loc_id, $req);
    }

    public function listPayments()
    {
        $apiClient = $this->_getApiClient();
        $api = $apiClient->getPaymentsApi();
        $res = $api->listPayments();
        var_dump($res);die;
    }*/


    /**
     * Get any errors that were set during processing.
     *
     * @return  array   Array of Square error objects
     */
    public function getErrors()
    {
        return $this->_errors;
    }


    /**
     * Check that a valid config has been set for the environment.
     *
     * @return  boolean     True if valid, False if not
     */
    public function hasValidConfig()
    {
        return !empty($this->getConfig('loc_id')) &&
            !empty($this->getConfig('appid')) &&
            !empty($this->getConfig('token'));
    }

}
