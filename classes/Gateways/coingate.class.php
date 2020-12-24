<?php
/**
 * Gateway implementation for Paylike (https://paylike.io)
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways;
use Shop\Currency;
use Shop\Config;
use LGLib\NameParser;


/**
 * Class for Square payment gateway.
 * @package shop
 */
class coingate extends \Shop\Gateway
{
    /** Internal API client to facilitate reuse.
     * @var object */
    private $_api_client = NULL;


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        // Import the Square API
        require_once SHOP_PI_PATH . '/vendor/autoload.php';

        $supported_currency = array(
            'USD', 'EUR',
        );

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'coingate';
        $this->gw_desc = 'CoinGate Crypto Currency';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->cfgFields = array(
            'prod' => array(
                'auth_token'   => 'password',
            ),
            'test' => array(
                'auth_token'   => 'password',
            ),
            'global' => array(
                'test_mode' => 'checkbox',
                'rcv_currency' => 'select',
            ),
        );
        // Set defaults
        $this->config = array(
            'global' => array(
                'test_mode'     => '1',
                'rcv_currency'  => 'BTC',
            ),
        );

        // Set the only service supported
        $this->services = array('checkout' => 1);

        // Call the parent constructor to initialize the common variables.
        parent::__construct($A);

        // If the configured currency is not one of the supported ones,
        // this gateway cannot be used, so disable it.
        if (!in_array($this->currency_code, $supported_currency)) {
            $this->enabled = 0;
        }
    }


    protected function getConfigOptions($name, $env='global')
    {
        if (isset($this->config[$env][$name])) {
            $selected = $this->config[$env][$name];
        } else {
            $selected = '';
        }
        switch ($name) {
        case 'rcv_currency':
            $opts = array(
                array('name'=>'Bitcoin', 'value'=>'BTC', 'selected'=>($selected=='BTC')),
                array('name'=>'Lightcoin', 'value'=>'LTC', 'selected'=>($selected=='LTC')),
                array('name'=>'US Dollar', 'value'=>'USD', 'selected'=>($selected=='USD')),
                array('name'=>'Euro', 'value'=>'EUR', 'selected'=>($selected=='EUR')),
            );
        }
        return $opts;
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
     * Check if the gateway supports invoicing. Default is false.
     *
     * @return  boolean True if invoicing is supported, False if not.
     */
    public function supportsInvoicing()
    {
        return false;
    }


    /**
     * Create and send an invoice for an order.
     *
     * @param   string  $order_num  Order Number
     * @return  boolean     True on success, False on error
     */
    public function createInvoice($order_num, $terms_gw)
    {
        global $_CONF, $LANG_SHOP;

        $access_token = $this->getBearerToken();
        if (!$access_token) {
            SHOP_log("Could not get Paypal access token", SHOP_LOG_ERROR);
            return false;
        }

        $Shop = new Company();
        $Order = Order::getInstance($order_num);
        $Currency = $Order->getCurrency();
        $Billto = $Order->getBillto();
        $Shipto = $Order->getShipto();
        $Order->updateStatus(OrderState::INVOICED);

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
                        'name' => array(),
                    ),
                ),
            ),
        );
    }

    public function _testCust($Order)
    {
        return $this->createCustomer($Order);
    }

    private function createCustomer($Order)
    {
        $cust_id = $Order->getUid();
        $Customer = $Order->getBillto();
        if (empty($Order->getBuyerEmail())) {
            $email = DB_getItem($_TABLES['users'], 'email', "uid = {$Order->getUid()}");
            $Order->setBuyerEmail($email);
        }
        $this->_getApiClient();
        $params = array(
            'email' => $Order->getBuyerEmail(),
            'subscriber_id' => $Order->getUid(),
            'first_name' => NameParser::F($Customer->getName()),
            'last_name' => NameParser::L($Customer->getName()),
            'address' => $Customer->getAddress1(),
            'secondary_address' => $Customer->getAddress2(),
            'city' => $Customer->getCity(),
            'postal_code' => $Customer->getPostal(),
            'country' => $Customer->getCountry(),
        );
        $this->_getApiClient();
        $result = \CoinGate\CoinGate::request(
            '/billing/subscribers',
            'POST',
            $params
        );
        var_dump($result);die;
    }

    public function _getCust($Order)
    {
        return $this->getCustomer($Order);
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
        $cust_id = $Order->getUid();
        $this->_getApiClient();
        $cust = \CoinGate\CoinGate::request('/billing/subscribers/219', 'GET');
        var_dump($cust);die;


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
     * Get the form action URL.
     * This function may be overridden by the child class.
     * The default is to simply return the configured URL
     *
     * This is public so that if it is not declared by the child class,
     * it can be called during IPN processing.
     *
     * @return  string      URL to payment processor
     */
    public function getActionUrl()
    {
        return Config::get('url') . '/confirm.php';
    }


    /**
     * Get the form variables for the purchase button.
     *
     * @uses    Gateway::Supports()
     * @uses    _encButton()
     * @uses    getActionUrl()
     * @param   object  $Cart   Shopping cart object
     * @return  string      HTML for purchase button
     */
    public function gatewayVars($Cart)
    {
        if (!$this->Supports('checkout')) {
            return '';
        }
    
        $vars = array(
            'order_id' => $Cart->getOrderID(),
        );
        $gw_vars = array();
        foreach ($vars as $name=>$val) {
            $gw_vars[] = '<input type="hidden" name="' . $name .
                '" value="' . $val . '" />';
        }
        return implode("\n", $gw_vars);
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
        return COM_createImage(
            Config::get('url') . '/images/gateways/coingate.svg',
            $this->gw_desc,
            array(
                'width' => '170px',
            )
        );
    }


    /**
     * Get the form method to use with the final checkout button.
     * Use GET to work with confirm.php.
     *
     * @return  string  Form method
     */
    public function getMethod()
    {
        return 'get';
    }


    /**
     * Get the Square API client object.
     *
     * @return  object      SquareClient object
     */
    private function _getApiClient()
    {
        require_once SHOP_PI_PATH . '/vendor/autoload.php';
        \CoinGate\CoinGate::config(array(
            'environment'               => $this->getConfig('test_mode') ? 'sandbox' : 'live',
            'auth_token'                => $this->getConfig('auth_token'),
            'curlopt_ssl_verifypeer'    => false,    // default is false
        ) );
    }


    /**
     * Create an order with the payment provider.
     *
     * @param   object  $Cart   Cart object
     * @return  object      Provider's order object
     */
    public function createGWorder($Cart)
    {
        global $LANG_SHOP;

        $this->_getApiClient();
        $Cur = $Cart->getCurrency();
        $by_gc = $Cart->getGC();
        $total_amount = $Cart->getTotal() - $Cart->getGC();
        $params = array(
            'order_id'          => $Cart->getOrderID(),
            'price_amount'      => $Cart->getTotal() - $Cart->getGC(),
            'price_currency'    => $Cart->getCurrency()->getCode(),
            'receive_currency'  => $this->getConfig('rcv_currency'),
            'callback_url'      => $this->getIpnUrl(),
            'cancel_url'        => $Cart->cancelUrl(),
            'success_url'       => Config::get('url') . '/index.php?thanks=' . $this->gw_name,
            'title'             => $LANG_SHOP['order'] . ' ' . $Cart->getOrderId(),
            'description'       => $LANG_SHOP['order'] . ' ' . $Cart->getOrderId(),
            'token'             => $Cart->getToken(),
        );
        $order = \CoinGate\Merchant\Order::create($params);
        return $order;
    }


    /**
     * Get additional javascript to be attached to the checkout button.
     * Coingate does not need this since it redirects through confirm.php.
     *
     * @param   object  $cart   Shopping cart object
     * @return  string  Javascript commands.
     */
    public function getCheckoutJS($cart)
    {
        return '';
    }


    public function findOrder($id)
    {
        $this->_getApiClient();
        try {
            $order = \CoinGate\Merchant\Order::find($id);
            if (!$order) {
                $order = NULL;
            }
        } catch (Exception $e) {
            SHOP_log(__CLASS__.'::'.__FUNCTION__. $e->getMessage());
            $order = NULL;
        }
        return $order;
    }


    /**
     * Confirm the order and create an invoice on Coingate.
     *
     * @param   object  $Order  Shop Order object
     * @return  string      Redirect URL
     */
    public function confirmOrder($Order)
    {
        $redirect = '';
        if (!$Order->isNew()) {
            $gwOrder = $this->createGWorder($Order);
            SHOP_log("order created: " . print_r($gwOrder,true), SHOP_LOG_DEBUG);
            if (is_object($gwOrder)) {
                $redirect = $gwOrder->payment_url;
            } else {
                COM_setMsg("There was an error processing your order");
            }
        }
        return $redirect;
    }

}
