<?php
/**
 * Gateway implementation for PayPal.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\paypal;
use Shop\Config;
use Shop\Company;
use Shop\Address;
use Shop\Currency;
use Shop\Order;
use Shop\Shipper;
use Shop\Models\CustomInfo;
use Shop\Models\OrderState;
use Shop\Models\Token;
use Shop\Template;
use Shop\Tax;
use Shop\Customer;
use Shop\Log;


/**
 * Class for Paypal payment gateway
 * @package shop
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = 'paypal';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'PayPal Web Payments';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'PayPal Web Payments Standard';

    /** Flag this gateway as bundled with the Shop plugin.
     * Gateway version will be set to the Shop plugin's version.
     * @var integer */
    protected $bundled = 1;

    /** Business e-mail to be used for creating buttons.
     * @var string */
    private $receiver_email;

    /** PayPal-assigned certificate ID to be used for encrypted buttons.
    * @var string */
    private $cert_id;


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

        // Set up the configuration field definitions.
        $this->cfgFields= array(
            'prod' => array(
                'receiver_email'    => 'string',
                'micro_receiver_email'  => 'string',
                'encrypt'           => 'checkbox',
                'pp_cert_id'        => 'string',
                'micro_pp_cert'     => 'string',
                'ena_donations'     => 'checkbox',
            ),
            'test' => array(
                'receiver_email'    => 'string',
                'micro_receiver_email'  => 'string',
                'encrypt'           => 'checkbox',
                'pp_cert_id'        => 'string',
                'micro_pp_cert'     => 'string',
                'ena_donations'     => 'checkbox',
            ),
            'global' => array(
                'test_mode'         => 'checkbox',
                'micro_threshold'   => 'string',
                'pp_cert'           => 'string',
                'prv_key'           => 'string',
                'pub_key'           => 'string',
            ),
        );

        // Set configuration defaults
        $this->config = array(
            'global' => array(
                'micro_threshold'   => '10',
                'test_mode'         => '1',
                'ena_donations'     => 0,
            ),
            'prod' => array(
            ),
            'test' => array(
            ),
        );

        // This gateway can service all button type by default
        $this->services = array(
            'buy_now'   => 1,
            'donation'  => 1,
            'pay_now'   => 1,
            'subscribe' => 1,
            'checkout'  => 1,
            'external'  => 1,
        );

        // Call the parent constructor to initialize the common variables.
        parent::__construct($A);

        // Set the gateway URL depending on whether we're in test mode or not
        if ($this->getConfig('test_mode') == 1) {
            $this->gw_url = 'https://www.sandbox.paypal.com';
        } else {
            $this->gw_url = 'https://www.paypal.com';
        }
        $this->postback_url = $this->gw_url;

        // If the configured currency is not one of the supported ones,
        // this gateway cannot be used, so disable it.
        if (!in_array($this->currency_code, $supported_currency)) {
            $this->enabled = 0;
        }

        // Set defaults, just to make sure something is set
        $this->cert_id = $this->getConfig('pp_cert_id');
        $this->receiver_email = $this->getConfig('receiver_email');
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
        return $this->gw_url . '/cgi-bin/webscr';
    }


    /**
     * Get the form variables for the cart checkout button.
     *
     * @uses    Gateway::Supports()
     * @uses    self::_encButton()
     * @uses    self::getActionUrl()
     * @param   object      $cart   Shopping Cart Object
     * @return  string      Gateay variable input fields
     */
    public function gatewayVars($cart)
    {
        global $_USER, $_TABLES, $LANG_SHOP;

        if (!$this->Supports('checkout')) {
            return '';
        }

        $cartID = $cart->getOrderId();
        /*$this->AddCustom('uid' => $_USER['uid']);
        $this->AddCustom('transtype' => 'cart_upload');
        $this->AddCustom('cart_id' => $cartID);*/
        $custom_arr = new CustomInfo(array(
            'uid' => $_USER['uid'],
            'transtype' => 'cart_upload',
            'cart_id' => $cartID,
            'session_id' => \Shop\Tracker::getInstance()->makeCid(),
        ) );
        if (isset($cart->custom_info)) {
            $custom_arr->merge($cart->custom_info);
        }

        $fields = array(
            'cmd'       => '_cart',
            'upload'    => '1',
            'cancel_return' => $cart->cancelUrl(),
            'return'    => $this->returnUrl($cart->getOrderID(), $cart->getToken()),
            'rm'        => '1',     // simple GET return url
            'paymentaction' => 'sale',
            'notify_url' => $this->ipn_url,
            'currency_code'  => $this->currency_code,
            'custom'    => (string)$custom_arr->encode(),
            'invoice'   => $cartID,
        );
        $address = $cart->getShipto();
        if (!empty($address)) {
            $np = explode(' ', $address->getName());
            $fields['first_name'] = isset($np[0]) ? htmlspecialchars($np[0]) : '';
            $fields['last_name'] = isset($np[1]) ? htmlspecialchars($np[1]) : '';
            $fields['address1'] = htmlspecialchars($address->getAddress1());
            $fields['address2'] = htmlspecialchars($address->getAddress2());
            $fields['city'] = htmlspecialchars($address->getCity());
            $fields['state'] = htmlspecialchars($address->getState());
            $fields['country'] = htmlspecialchars($address->getCountry());
            $fields['zip'] = htmlspecialchars($address->getPostal());
        }

        $i = 1;     // Item counter for paypal variables
        $total_amount = 0;
        $shipping = 0;
        $handling = 0;
        $fields['tax_cart'] = 0;

        // If using a gift card, the gc amount could exceed the item total
        // which won't work with Paypal. Just create one cart item to
        // represent the entire cart including tax, shipping, etc.
        //if (SHOP_getVar($custom_arr, 'by_gc', 'float') > 0) {
        $by_gc = $cart->getGC();
        if ($by_gc > 0) {
            $total_amount = $cart->getTotal() - $by_gc;
            $fields['item_number_1'] = $LANG_SHOP['cart'];
            $fields['item_name_1'] = $LANG_SHOP['all_items'];
            $fields['amount_1'] = $total_amount;
        } else {
            $cartItems = $cart->getItems();
            foreach ($cartItems as $cart_item_id=>$item) {
                if ($item->getQuantity() == 0) {
                    continue;
                }
                //$item_parts = explode('|', $item['item_id']);
                //$db_item_id = $item_parts[0];
                //$options = isset($item_parts[1]) ? $item_parts[1] : '';
                $P = \Shop\Product::getByID($item->getProductID(), $custom_arr);
                $db_item_id = DB_escapeString($item->getProductID());
                $oc = 0;
                $oio_arr = array();
                foreach ($item->options as $OIO) {
                    $oio_arr[] = $OIO;
                    $fields['on'.$oc.'_'.$i] = $OIO->getName();
                    $fields['os'.$oc.'_'.$i] = $OIO->getValue();
                    $oc++;
                }
                $overrides = array(
                    'price' => $item->getPrice(),
                    'uid'   => $_USER['uid'],
                );
                //$item_amount = $P->getPrice($oio_arr, $item->getQuantity(), $overrides);
                $item_amount = $item->getNetPrice();
                $fields['amount_' . $i] = $item_amount;
                $fields['item_number_' . $i] = (int)$cart_item_id;
                $fields['item_name_' . $i] = htmlspecialchars($item->getDscp());
                $total_amount += $item->getPrice();
                if (is_array($item->getExtra('custom'))) {
                    foreach ($item->getExtra('custom') as $id=>$val) {
                        $fields['on'.$oc.'_'.$i] = $P->getCustom($id);
                        $fields['os'.$oc.'_'.$i] = $val;
                        $oc++;
                    }
                }
                $fields['quantity_' . $i] = $item->getQuantity();
                $i++;
            }

            if ($cart->getShipping() > 0) {
                $fields['shipping_1'] = $cart->getShipping();
                $shipping += $cart->getShipping();
            }

            $fields['tax_cart'] = (float)$cart->getTax();
            $total_amount += $cart->getTax();
            if ($shipping > 0) $total_amount += $shipping;
        }

        // Set the business e-mail address based on the total puchase amount
        // There must be an address configured; if not then this gateway can't
        // be used for this purchase
        $this->setReceiver($total_amount);
        $fields['business'] = $this->receiver_email;
        if (empty($fields['business'])) {
            return '';
        }
        $gatewayVars = array();
        $enc_btn = '';
        if ($this->getConfig('encrypt')) {
            $enc_btn = self::_encButton($fields);
            if (!empty($enc_btn)) {
                $gatewayVars[] =
                '<input type="hidden" name="cmd" value="_s-xclick" />';
                $gatewayVars[] = '<input type="hidden" name="encrypted" '.
                'value="' . $enc_btn . '" />';
            }
        }
        if (empty($enc_btn)) {
            // If we didn't get an encrypted button, set the plaintext vars
            foreach($fields as $name=>$value) {
                $gatewayVars[] = '<input type="hidden" name="' .
                    $name . '" value="' . $value . '" />';
            }
        }

        $gateway_vars = implode("\n", $gatewayVars);
        return $gateway_vars;
    }


    /**
     * Create encrypted buttons.
     *
     * Requires that the plugin is configured to do so, and that the key files
     * are set up correctly.  If an error is encountered, an empty string
     * is returned so the caller can proceed with an un-encrypted button.
     *
     * @param   array   $fields     Array of data to encrypt into buttons
     * @return  string              Encrypted_value, or empty string on error
     */
    private function _encButton($fields)
    {
        global $_CONF;

        // Make sure button encryption is enabled and needed values are set
        if ($this->getConfig('encrypt') != 1 ||
            empty($this->getConfig('prv_key')) ||
            empty($this->getConfig('pub_key')) ||
            empty($this->getConfig('pp_cert')) ||
            $this->cert_id == ''
        ) {
            return '';
        }

        $keys = array();
        // Now check that the files exist and can be read
        foreach (array('prv_key', 'pub_key', 'pp_cert') as $idx=>$name) {
            $keys[$name] = Config::get('tmpdir') . 'keys/' . $this->getConfig($name);
            if (
                !is_file($keys[$name]) ||
                !is_readable($keys[$name])
            ) {
                return '';
            }
        }

        // Create a temporary file to begin storing our data.  If this fails,
        // then return.
        $dataFile = tempnam(Config::get('tmpdir') . 'cache/', 'data');
        if (!is_writable($dataFile)) {
            return '';
        }

        $plainText = '';
        $signedText = array();
        $encText = '';

        $pub_key = @openssl_x509_read(file_get_contents($keys['pub_key']));
        if (!$pub_key) {
            Log::write('shop_system', Log::ERROR, "Failed reading public key from {$keys['pub_key']}");
            return '';
        }
        $prv_key = @openssl_get_privatekey(file_get_contents($keys['prv_key']));
        if (!$prv_key) {
            Log::write('shop_system', Log::ERROR, "Failed reading private key from {$keys['prv_key']}");
            return '';
        }
        $pp_cert = @openssl_x509_read(file_get_contents($keys['pp_cert']));
        if (!$pp_cert) {
            Log::write('shop_system', Log::ERROR, "Failed reading PayPal certificate from {$keys['pp_cert']}");
            return '';
        }

        //  Make sure this key and certificate belong together
        if (!openssl_x509_check_private_key($pub_key, $prv_key)) {
            Log::write('shop_system', Log::ERROR, "Mismatched private & public keys");
            return '';
        }

        //  Start off the form data with the PayPal certificate ID
        $plainText .= "cert_id=" . $this->cert_id;

        //  Create the form data by separating each value set by a new line
        //  Make sure that required fields are available.  We assume that the
        //  item_number, item_name and amount are in.
        if (!isset($fields['business'])) {
            $fields['business'] = $this->receiver_email;
        }
        if (!isset($fields['currency_code'])) {
            $fields['currency_code'] = $this->currency_code;
        }
        foreach($fields as $key => $value) {
            $plainText .= "\n{$key}={$value}";
        }

        //  First create a file for storing the plain text values
        $fh = fopen($dataFile . '_plain.txt', 'wb');
        if ($fh) {
            fwrite($fh, $plainText);
            @fclose($fh);
        } else {
            return '';
        }

        // Now sign the plaintext values into the signed file
        if (!openssl_pkcs7_sign(
            $dataFile . '_plain.txt',
            $dataFile . '_signed.txt',
            $pub_key,
            $prv_key,
            array(),
            PKCS7_BINARY
        )) {
            return '';
        }

        //  Parse the signed file between the header and content
        $signedText = explode("\n\n",
                file_get_contents($dataFile . '_signed.txt'));

        //  Save only the content but base64 decode it first
        $fh = fopen($dataFile . '_signed.txt', 'wb');
        if ($fh) fwrite($fh, base64_decode($signedText[1]));
        else return '';
        @fclose($fh);

        // Now encrypt the signed file we just wrote
        if (!openssl_pkcs7_encrypt(
            $dataFile . '_signed.txt',
            $dataFile . '_enc.txt',
            $pp_cert,
            array(),
            PKCS7_BINARY
        )) {
            return '';
        }

        // Parse the encrypted file between header and content
        $encryptedData = explode(
            "\n\n",
            file_get_contents($dataFile . "_enc.txt")
        );
        $encText = $encryptedData[1];

        // Delete all of our temporary files
        @unlink($dataFile);
        @unlink($dataFile . "_plain.txt");
        @unlink($dataFile . "_signed.txt");
        @unlink($dataFile . "_enc.txt");

        //  Return the now-encrypted form content
        return "-----BEGIN PKCS7-----\n" . $encText . "\n-----END PKCS7-----";
    }


    /**
     * Get a buy-now button for a catalog product.
     * Checks the button table to see if a button exists, and if not
     * a new button will be created.
     *
     * @uses    gwButtonType()
     * @param   object  $P      Product Item object
     * @return  string          HTML code for the button.
     */
    public function ProductButton($P)
    {
        global $LANG_SHOP;

        $btn_type = $P->getBtnType();
        if (empty($btn_type)) return '';

        // Make sure we want to create a buy_now-type button.
        // Not for items that require shipping or free products.
        if ($P->isPhysical() || (!$P->allowCustomPrice() && $P->getPrice() < .01)) {
            return '';
        }

        $U = Customer::getInstance();
        $btn_info = self::gwButtonType($btn_type);
        $this->AddCustom('transtype', $btn_type);
        $this->AddCustom('ref_token', $U->getReferralToken());
        $this->setReceiver($P->getPrice());
        $sess_info = \Shop\Tracker::getSessionInfo();
        if (isset($sess_info['uniq_id'])) {
            $this->addCustom('trk_id', $sess_info['uniq_id']);
        }

        $vars = array(
            'cmd' => $btn_info['cmd'],
            'business' => $this->receiver_email,
            'item_number' => htmlspecialchars($P->getID()),
            'item_name' => htmlspecialchars($P->getShortDscp()),
            'currency_code' => $this->currency_code,
            'custom' => $this->custom->encode(),
            'return' => $this->returnUrl('', ''),
            'cancel_return' => $P->getCancelUrl(),
            'amount' => $P->getPrice(),
            'notify_url' => $this->ipn_url,
        );

        // Get the allowed buy-now quantity. If not defined, set
        // undefined_quantity.
        $qty = $P->getFixedQuantity();
        if ($qty < 1) {
            $vars['undefined_quantity'] = '1';
        } else {
            $vars['quantity'] = $qty;
        }

        if ($P->getWeight() > 0) {
            $vars['weight'] = $P->getWeight();
        } else {
            $vars['no_shipping'] = '1';
        }

        // TODO: Deprecate this check. Phyisical items are no longer supported
        // for buy-now buttons since a shipping address is required.
        switch ($P->getShippingType()) {
        case 0:
            $vars['no_shipping'] = '1';
            break;
        case 2:
            $shipping = Shipper::getBestRate($P->getShippingUnits())->best_rate;
            $shipping += $P->getShipping();
            $vars['shipping'] = $shipping;
            $vars['no_shipping'] = '1';
            break;
        case 1:
            $vars['no_shipping'] = '2';
            break;
        }

        // Set the tax rate based on the company location if taxable.
        if ($P->isTaxable()) {
            $vars['tax_rate'] = sprintf(
                '%0.4f',
                Tax::getProvider()
                    ->withAddress(new Company)
                    ->getRate() * 100
            );
        }

        // Buy-now product button, set default billing/shipping addresses
        $shipto = $U->getDefaultAddress('shipto');
        if (!empty($shipto)) {
            $fullname = $shipto->getName();
            if (strpos($fullname, ' ')) {
                list($fname, $lname) = explode(' ', $fullname);
                $vars['first_name'] = $fname;
                if ($lname) $vars['last_name'] = $lname;
            } else {
                $vars['first_name'] = $fullname;
            }
            $vars['address1'] = $shipto->getAddress1();
            if (!empty($shipto->getAddress2())) {
                $vars['address2'] = $shipto->getAddress2();
            }
            $vars['city'] = $shipto->getCity();
            $vars['state'] = $shipto->getState();
            $vars['zip'] = $shipto->getPostal();
            $vars['country'] = $shipto->getCountry();
        }

        $gateway_vars = '';
        $enc_btn = '';
        if ($this->getConfig('encrypt')) {
            $enc_btn = $this->_ReadButton($P, $btn_type);
            if (empty($enc_btn)) {
                $enc_btn = $this->_encButton($vars);
                if (!empty($enc_btn)) {
                    $gateway_vars .=
                    '<input type="hidden" name="cmd" value="_s-xclick" />'.LB .
                    '<input type="hidden" name="encrypted" value=\'' .
                    $enc_btn . '\' />' . "\n";
                    $this->_SaveButton($P, $btn_type, $gateway_vars);
                }
            } else {
                $gateway_vars = $enc_btn;
            }
        }
        if (empty($enc_btn)) {
            // Create unencrypted buttons if not configured to encrypt,
            // or if encryption fails.
            foreach ($vars as $name=>$value) {
                if ($name == 'amount' && $P->allowCustomPrice()) {
                    $gateway_vars .= '<br />' . $P->getPricePrompt() .
                        ': <input class="shopCustomPriceField" type="text" name="' . $name .
                        '" value = "' . $value . '" /><br />' . "\n";
                } else {
                    $gateway_vars .= '<input type="hidden" name="' . $name .
                        '" value="' . $value . '" />' . "\n";
                }
            }
        }

        // Set the text for the button, falling back to our Buy Now
        // phrase if not available
        $btn_text = $P->getBtnText();    // maybe provided by a plugin
        if ($btn_text == '') {
            $btn_text = isset($LANG_SHOP['buttons'][$btn_type]) ?
                $LANG_SHOP['buttons'][$btn_type] : $LANG_SHOP['buy_now'];
        }
        $T = new Template('buttons/' . $this->gw_name);
        $T->set_file('btn', 'btn_' . $btn_info['tpl'] . '.thtml');
        $T->set_var(array(
            'action_url'    => $this->getActionUrl(),
            'btn_text'      => $btn_text,
            'gateway_vars'  => $gateway_vars,
            'method'        => $this->getMethod(),
        ) );
        $retval = $T->parse('', 'btn');
        return $retval;
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
            // Use the donation command only if enabled for the gateway
            if ($this->getConfig('ena_donations')) {
                $cmd = '_donations';
            } else {
                $cmd = '_xclick';
            }
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
     * Get the values to show in the "Thank You" message when a customer returns to our site.
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
        case $this->getConfig('receiver_email'):
        case $this->getConfig('micro_receiver_email'):
            $retval = true;
            break;
        default:
            $retval = false;
            break;
        }
        return $retval;
    }


    /**
     * Prepare to save the configuraiton.
     * This copies the new config values into our local variables, then
     * calls the parent function to save to the database.
     *
     * @param   array   $A      Array of name=>value pairs (e.g. $_POST)
     * @return  boolean     True on success, False on error
     */
    public function saveConfig(?array $A = NULL) : bool
    {
        if (is_array($A)) {
            foreach ($this->getConfig() as $name=>$value) {
                switch ($name) {
                case 'encrypt':
                    // Check if the "encrypt" value has changed.  If so, clear the
                    // button cache
                    $encrypt = isset($A['encrypt']) ? 1 : 0;
                    if ($encrypt != $this->getConfig('encrypt')) {
                        $this->ClearButtonCache();
                    }
                    break;
                }
            }
        }
        return parent::SaveConfig($A);
    }


    /**
     * Sets the receiver_email and cert_id properties.
     *
     * @param   float   $amount     Total puchase amount.
     * @return  object  $this
     */
    private function setReceiver($amount)
    {
        // If the order amount exceeds the micro account threshold,
        // or no micro receiver email is specified, return prod.
        if (
            $amount >= $this->getConfig('micro_threshold') ||
            empty($this->getConfig('micro_receiver_email'))
        ) {
            $this->receiver_email = $this->getConfig('receiver_email');
            $this->cert_id = $this->getConfig('pp_cert_id');
        } else {
            $this->receiver_email = $this->getConfig('micro_receiver_email');
            $this->cert_id = $this->getConfig('micro_cert_id');
        }
        return $this;
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
        } else {        // regular IPN
            $verified = $data['payer_status'];
            $pmt_status = $data['payment_status'];
            $buyer_email = $data['payer_email'];
        }
        $retval = array(
            'verified'      => $verified,
            'pmt_status'    => $pmt_status,
            'buyer_email'   => $buyer_email,
        );
        return $retval;
    }


    /**
     * Get a logo image to show on the order as the payment method.
     *
     * @return  string      HTML for logo image
     */
    public function getLogo()
    {
        return COM_createImage(
            'https://www.paypalobjects.com/webstatic/en_US/i/buttons/buy-logo-large.png"',
            'Buy now with PayPal',
            array(
                'width' => self::LOGO_WIDTH,
                'height' => self::LOGO_HEIGHT,
            )
        );
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


    /**
     * Perform upgrade functions for this gateway.
     *
     * @param   string  $to     Target version
     * @return  boolean     True on success, False on error
     */
    protected function _doUpgrade()
    {
        global $_TABLES;

        $installed = $this->getInstalledVersion();
        if (!COM_checkVersion($installed, '1.3.0')) {
            // Get the config straight from the DB.
            $cfg = DB_getItem($_TABLES['shop.gateways'], 'config', "id='paypal'");
            $this->config = @unserialize($cfg);
            foreach (array('encrypt', 'ena_donations', 'pp_cert_id', 'micro_pp_cert') as $key) {
                    if (isset($this->config['global'][$key])) {
                        $this->setConfig($key, $this->config['global'][$key], 'test');
                        $this->setConfig($key, $this->config['global'][$key], 'prod');
                        unset($this->config['global'][$key]);
                    }
            }
            // Remove these config items. No longer used, use only by the ppcheckout gateway
            foreach (array('api_username', 'api_password', 'webhook_id', 'endpoint') as $key) {
                    unset($this->config['prod'][$key]);
                    unset($this->config['test'][$key]);
            }
            $this->version = '1.3.0';
            $this->SaveConfig();
        }
        return true;
    }


    /**
     * Check that a valid config has been set for the environment.
     *
     * @return  boolean     True if valid, False if not
     */
    public function hasValidConfig()
    {
        return !empty($this->getConfig('receiver_email'));
    }

}
