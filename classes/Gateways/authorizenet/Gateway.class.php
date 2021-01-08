<?php
/**
 * Class to manage Authorize.Net Hosted Accept payments.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\authorizenet;


/**
 * Class for Authorize.Net Hosted Accept payment gateway.
 * @package shop
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = 'authorizenet';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'Authorize.Net';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'Authorize.Net Accept Hosted';

    /** Flag this gateway as bundled with the Shop plugin.
     * @var integer */
    protected $bundled = 1;

    /** Authorize.net transaction key.
     * @var string */
    private $trans_key = '';

    /** Authorize.net api login.
     * @var string */
    private $api_login = '';

    /** Signature key configured on Authorize.Net
     * @var string */
    private $hash_key = '';

    /** URL for requesting an authorization token.
     * @var string */
    private $token_url = '';

    /** Shopping cart object.
     * We need to access this both from `CheckoutButon()`  and `_getButton()`.
     * @var object
     */
    private $cart = NULL;


    /**
     * Constructor.
     * Sets gateway-specific variables and calls the parent constructor.
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        global $_SHOP_CONF;

        // Set up the configuration field definitions.
        $this->cfgFields= array(
            'prod' => array(
                'api_login'    => 'password',
                'trans_key'    => 'password',
                'hash_key'     => 'password',
            ),
            'test' => array(
                'api_login'    => 'password',
                'trans_key'    => 'password',
                'hash_key'     => 'password',
            ),
            'global' => array(
                'test_mode' => 'checkbox',
            ),
        );

        // Set the supported services as this gateway only supports cart checkout
        $this->services = array('checkout' => 1);

        // The parent constructor reads our config items from the database to
        // override defaults
        parent::__construct($A);

        // parent constructor loads the config array, here we select which
        // keys to use based on test_mode
        $this->api_login    = trim($this->getConfig('api_login'));
        $this->trans_key    = trim($this->getConfig('trans_key'));
        $this->hash_key     = trim($this->getconfig('hash_key'));
        if ($this->isSandbox()) {
            $this->token_url = 'https://apitest.authorize.net/xml/v1/request.api';
            $this->gw_url = 'https://test.authorize.net/payment/payment';
        } else {
            $this->token_url = 'https://api.authorize.net/xml/v1/request.api';
            $this->gw_url = 'https://accept.authorize.net/payment/payment';
        }
    }


    /**
     * Get the main website URL for this payment gateway.
     * Used to tell the buyer where to log in to check their account.
     *
     * @return  string      Gateway's website URL
     */
    private function _getMainUrl()
    {
        return 'https://www.authorize.net';
    }


    /**
     * Get the gateway variables to put in the checkout button.
     *
     * @param   object      $cart   Shopping Cart Object
     * @return  string      Gateay variable input fields
     */
    public function gatewayVars($cart)
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP;

        // Make sure we have at least one item
        if (empty($cart->getItems())) return '';
        $total_amount = 0;
        $line_items = array();
        $Cur = \Shop\Currency::getInstance();
        $return_opts = array(
            'url'       => $this->returnUrl($cart->getOrderID(), $cart->getToken()),
            'cancelUrl' => $cart->cancelUrl(),
        );

        $by_gc = $cart->getGC();
        $dc_pct = $cart->getDiscountPct() / 100;
        if ($by_gc > 0 || $dc_pct > 0) {
            $total_amount = $cart->getTotal() - $by_gc;
            $line_items[] = array(
                    'itemId' => $LANG_SHOP['cart'],
                    'name' => $LANG_SHOP['all_items'],
                    'description' => $LANG_SHOP['all_items'],
                    'quantity' => 1,
                    'unitPrice' => $Cur->FormatValue($total_amount),
                    'taxable' => false,
            );
        } else {
            foreach ($cart->getItems() as $Item) {
                $P = $Item->getProduct();
                $line_items[] = array(
                    'itemId'    => substr($P->getItemID(), 0, 31),
                    'name'      => substr(strip_tags($P->getShortDscp()), 0, 31),
                    'description' => substr(strip_tags($P->getDscp()), 0, 255),
                    'quantity' => $Item->getQuantity(),
                    'unitPrice' => $Cur->FormatValue($Item->getPrice()),
                    'taxable' => $Item->isTaxable() ? true : false,
                );
                $total_amount += $Item->getPrice()* $Item->getQuantity();
            }
            $total_amount += $cart->getShipping();
            $total_amount += $cart->getHandling();
            $total_amount += $cart->getTax();
        }

        $json = array(
            'getHostedPaymentPageRequest' => array(
                'merchantAuthentication' => array(
                    'name' => $this->api_login,
                    'transactionKey' => $this->trans_key,
                ),
                'refId' => $cart->getOrderID(),
                'transactionRequest' => array(
                    'transactionType' => 'authCaptureTransaction',
                    'amount' => $Cur->FormatValue($total_amount),
                    'order' => array(
                        'invoiceNumber' => $cart->getOrderID(),
                    ),
                    'lineItems' => array(
                        'lineItem' => $line_items,
                    ),
                    'tax' => array(
                        'amount' => $Cur->FormatValue($cart->getTax()),
                        'name' => 'Sales Tax',
                    ),
                    'shipping' => array(
                        'amount' => $Cur->FormatValue($cart->getShipping()),
                        'name' => 'Shipping',
                    ),
                    'customer' => array(
                        'id' => $cart->getUid(),
                        'email' => $cart->getBuyerEmail(),
                    ),
                ),
                'hostedPaymentSettings' => array(
                    'setting' => array(
                        0 => array(
                            'settingName' => 'hostedPaymentReturnOptions',
                            'settingValue' => json_encode($return_opts, JSON_UNESCAPED_SLASHES),
                        ),
                        1 => array(
                            'settingName' => 'hostedPaymentButtonOptions',
                            'settingValue' => '{"text": "Pay"}',
                        ),
                        2 => array(
                            'settingName' => 'hostedPaymentPaymentOptions',
                            'settingValue' => '{"cardCodeRequired": false, "showCreditCard": true, "showBankAccount": true}',
                        ),
                        3 => array(
                            'settingName' => 'hostedPaymentSecurityOptions',
                            'settingValue' => '{"captcha": false}',
                        ),
                        4 => array(
                            'settingName' => 'hostedPaymentIFrameCommunicatorUrl',
                            'settingValue' => '{"url": "' . $this->ipn_url . '"}',
                        ),
                    ),
                ),
            ),
        );
        $jsonEncoded = json_encode($json, JSON_UNESCAPED_SLASHES);

        $ch = curl_init();
        //var_dump($this->token_url);die;
        curl_setopt($ch, CURLOPT_URL, $this->token_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonEncoded);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        //var_dump($result);die;
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code != 200) {             // Check for a 200 code before anything else
            COM_setMsg("Error checking out");
            SHOP_log('Bad response from token request: ' . print_r($result,true), SHOP_LOG_ERROR);
            return false;
        }
        $bom = pack('H*','EFBBBF');
        $result = preg_replace("/^$bom/", '', $result);
        $result = json_decode($result);
        //var_export($result);die;
        if ($result->messages->resultCode != 'Ok') {  // Check for errors due to invalid data, etc.
            foreach ($result->messages->message as $msg) {
                COM_errorlog($this->gw_provider . ' error: ' . $msg->code . ' - ' . $msg->text);
            }
            COM_setMsg("Error checking out");
            return false;
        }

        $vars = array(
            'token' => $result->token,
        );
        $gateway_vars = '';
        foreach ($vars as $name=>$value) {
            $gateway_vars .= '<input type="hidden" name="' . $name .
                        '" value=\'' . $value . '\' />' . "\n";
        }
        return $gateway_vars;
    }


    /**
     * Get the variables from the return URL to display a "thank-you" message to the buyer.
     *
     * @param   array   $A      Optionally override the $_GET parameters
     * @return  array           Array of standard name=>value pairs
     */
    public function thanksVars($A='')
    {
        $R = array(
            'gateway_name'  => $this->gw_provider,
        );
        return $R;
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
        if (!is_array($data)) {
            return array();
        }
        $retval = array(
            'verified'      => 'verified',
            'pmt_status'    => $data['status'],
            'buyer_email'   => '',
        );
        return $retval;
    }


    /**
     * Get the MD5 hash key.
     * Needed by the IPN processor.
     *
     * @return  string  MD5 Hash Key
     */
    public function getTransKey()
    {
        return $this->trans_key;
    }


    /**
     * Get the API Login.
     * Needed by the IPN processor.
     *
     * @return  string  API Login ID
     */
    public function getApiLogin()
    {
        return $this->api_login;
    }


    /**
     * Get a logo image to show on the order as the payment method.
     *
     * @return  string      HTML for logo image
     */
    public function getLogo()
    {
        global $_CONF;
        return COM_createImage(
            'https://www.authorize.net/content/dam/anet-redesign/reseller/authorizenet-200x50.png',
            $this->gw_provider,
            array(
                'width' => 160,
                'height' => 40,
                'border' => 0,
            )
        );
    }


    /**
     * Hash a text string using the hash_key.
     *
     * @param   string  $text   Text to hash
     * @return  string      Hashed text
     */
    public function _genHash($text)
    {
        $sig = hash_hmac('sha512', $text, hex2bin($this->hash_key));
        return $sig;
    }


    /**
     * Get special warnings and instructions for the configuration screen.
     * This warns that the IPN URL must be whitelisted in the Bad Behavior
     * plugin.
     *
     * @return  string  Message text
     */
    protected function getInstructions()
    {
        return $this->adminWarnBB();
    }

}   // class authorizenet

?>
