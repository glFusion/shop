<?php
/**
 * Gateway implementation for Paylike (https://paylike.io)
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
namespace Shop\Gateways;
use Shop\Currency;
use Shop\Cart;
use Shop\Config;


/**
 * Class for Square payment gateway.
 * @package shop
 */
class paylike extends \Shop\Gateway
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
        $supported_currency = array(
            'USD', 'AUD', 'CAD', 'EUR', 'GBP', 'JPY', 'NZD', 'CHF', 'HKD',
            'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'CZK', 'ILS', 'MXN',
            'PHP', 'TWD', 'THB', 'MYR', 'RUB',
        );

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'paylike';
        $this->gw_desc = 'Paylike';
        //$this->postback_url = Config::get('url') . '/ipn/ipn.php';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->cfgFields = array(
            'prod' => array(
                'pub_key'   => 'password',
                'prv_key'   => 'password',
            ),
            'test' => array(
                'pub_key'   => 'password',
                'prv_key'   => 'password',
            ),
            'global' => array(
                'test_mode' => 'checkbox',
            ),
        );
        // Set defaults
        $this->config = array(
            'global' => array(
                'test_mode'         => '1',
            ),
        );

        // Set the only service supported
        $this->services = array('checkout' => 1, 'terms' => 0);

        // Call the parent constructor to initialize the common variables.
        parent::__construct($A);

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
        static $have_js = false;

        $cartID = $Cart->CartID();
        $shipping = 0;
        $Cur = Currency::getInstance();

        if (!$have_js) {
            $outputHandle = \outputHandler::getInstance();
            $outputHandle->addLinkScript('//sdk.paylike.io/3.js');
            $have_js = true;
        }

        $ipn_params = array(
            'order_id' => $Cart->getOrderID(),
        );
        $js_str = '<script>
	    var paylike = Paylike("' . $this->getConfig('pub_key') . '");
            function SHOP_paylike_' . $Cart->getOrderID() . '() {
                paylike.popup({
		    currency: "' . $Cur->getCode() . '",
                    amount: ' . $Cart->getTotal() * 100 . ',
                    custom: {
                        order_id: "' . $Cart->getOrderiD() . '"
                    },
    	        }, function( err, res ){
                    if (err) {
                        return console.log(err);
                    }
                    location.href = "' . $this->getIpnUrl($ipn_params) .
                        '&txn_id=" + res.transaction.id;
                    //console.log(res.transaction.id);
            	    //alert("Thank you!");
	        });
            }
	</script>';
        return $js_str;
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
            Config::get('url') . '/images/gateways/paylike.png',
            $this->gw_desc,
            array(
                //'width' => '170px',
            )
        );
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
     * Get the Square API client object.
     *
     * @return  object      SquareClient object
     */
    private function _getApiClient()
    {
        if ($this->_api_client === NULL) {
            // Import the API SDK
            require_once SHOP_PI_PATH . '/vendor/autoload.php';
            $this->_api_client = new \Paylike\Paylike($this->getConfig('prv_key'));
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
        $transactions = $this->_getApiClient()->transactions();
        $transaction  = $transactions->fetch($trans_id);
        return $transaction;
    }


    /**
     * Capture the transaction amount.
     *
     * @param   string  $trans_id   Transaction ID
     * @param   array   $args       Arguments, amount and currency
     * @return  boolean     True on success, False on error
     */
    public function captureTransaction($trans_id, $args)
    {
        if (empty($trans_id)) {
            return false;
        }
        if (!isset($args['amount']) || $args['amount'] < .01) {
            return false;
        }
        $transactions = $this->_getApiClient()->transactions();
        $txn = $transactions->capture($trans_id, $args);
        $captured = SHOP_getVar($txn, 'capturedAmount', 'integer');
        if ($captured == $args['amount']) {
            return true;
        } else {
            return false;
        }
     }


    /**
     * Get additional javascript to be attached to the checkout button.
     *
     * @param   object  $Cart   Shopping cart object
     * @return  string      Javascript commands.
     */
    public function getCheckoutJS($Cart)
    {
        return 'SHOP_paylike_' . $Cart->getOrderId() . '(); return false;';
    }

}
