<?php
/**
 * Gateway implementation for PayPal.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways;

use Shop\Company;
use Shop\Address;
use Shop\Currency;
use Shop\Order;

/**
 * Class for Paypal payment gateway
 * @package shop
 */
class paypal extends \Shop\Gateway
{
    /** Business e-mail to be used for creating buttons.
     * @var string */
    private $receiver_email;

    /** PayPal-assigned certificate ID to be used for encrypted buttons.
    * @var string */
    private $cert_id;

    /** Paypal API URL, sandbox or production.
     * @var string */
    private $api_url;


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     */
    public function __construct()
    {
        global $_SHOP_CONF, $_USER;

        $supported_currency = array(
            'USD', 'AUD', 'CAD', 'EUR', 'GBP', 'JPY', 'NZD', 'CHF', 'HKD',
            'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'HUF', 'CZK', 'ILS', 'MXN',
            'PHP', 'TWD', 'THB',
        );

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'paypal';
        $this->gw_provider = 'Paypal';
        $this->gw_desc = 'PayPal Web Payments Standard';
        $this->button_url = '<img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-medium.png" alt="Check out with PayPal" />';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->cfgFields= array(
            'bus_prod_email'    => 'string',
            'micro_prod_email'  => 'string',
            'bus_test_email'    => 'string',
            'micro_test_email'  => 'string',
            'test_mode'         => 'checkbox',
            'micro_threshold'   => 'string',
            'encrypt'           => 'checkbox',
            'pp_cert'           => 'string',
            'pp_cert_id'        => 'string',
            'micro_cert_id'     => 'string',
            'sandbox_main_cert' => 'string',
            'sandbox_micro_cert' => 'string',
            'prv_key'           => 'string',
            'pub_key'           => 'string',
            'prod_url'          => 'string',
            'sandbox_url'       => 'string',
            'api_username'      => 'password',
            'api_password'      => 'password',
            'api_sig'           => 'password',
            'sandbox_webhook_id' => 'string',
            'prod_webhook_id'   => 'string',
            'api_sig'           => 'password',
        );

        // Set defaults
        $this->config = array(
            'micro_threshold'   => '10',
            'test_mode'         => '1',
            'prod_url'          => 'https://www.paypal.com',
            'sandbox_url'       => 'https://www.sandbox.paypal.com',
        );

        // This gateway can service all button type by default
        $this->services = array(
            'buy_now'   => 1,
            'donation'  => 1,
            'pay_now'   => 1,
            'subscribe' => 1,
            'checkout'  => 1,
            'external'  => 1,
            //'terms'     => 0,
        );

        // Call the parent constructor to initialize the common variables.
        parent::__construct();

        // Set the gateway URL depending on whether we're in test mode or not
        if ($this->getConfig('test_mode') == 1) {
            $this->gw_url = $this->getConfig('sandbox_url');
            $this->postback_url = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
            $this->api_url = 'https://api.sandbox.paypal.com';
        } else {
            $this->gw_url = $this->getConfig('prod_url');
            $this->postback_url = 'https://ipnpb.paypal.com/cgi-bin/webscr';
            $this->api_url = 'https://api.paypal.com';
        }

        // If the configured currency is not one of the supported ones,
        // this gateway cannot be used, so disable it.
        if (!in_array($this->currency_code, $supported_currency)) {
            $this->enabled = 0;
        }

        // Set defaults, just to make sure something is set
        $this->cert_id = $this->getConfig('pp_cert_id');
        $this->receiver_email = $this->getConfig('bus_prod_email');
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
     * @uses    Gateway::_Supports()
     * @uses    self::_encButton()
     * @uses    self::getActionUrl()
     * @param   object      $cart   Shopping Cart Object
     * @return  string      Gateay variable input fields
     */
    public function gatewayVars($cart)
    {
        global $_SHOP_CONF, $_USER, $_TABLES, $LANG_SHOP;

        if (!$this->_Supports('checkout')) {
            return '';
        }

        $cartID = $cart->CartID();
        $custom_arr = array(
            'uid' => $_USER['uid'],
            'transtype' => 'cart_upload',
            'cart_id' => $cartID,
        );
        $custom_arr = array_merge($custom_arr, $cart->custom_info);

        $fields = array(
            'cmd'       => '_cart',
            'upload'    => '1',
            'cancel_return' => $cart->cancelUrl(),
            'return'    => $this->returnUrl($cart->order_id, $cart->token),
            'rm'        => '1',     // simple GET return url
            'paymentaction' => 'sale',
            'notify_url' => $this->ipn_url,
            'currency_code'  => $this->currency_code,
            'custom'    => str_replace('"', '\'', serialize($custom_arr)),
            'invoice'   => $cartID,
        );

        $address = $cart->getAddress('shipto');
        if (!empty($address)) {
            $np = explode(' ', $address['name']);
            $fields['first_name'] = isset($np[0]) ? htmlspecialchars($np[0]) : '';
            $fields['last_name'] = isset($np[1]) ? htmlspecialchars($np[1]) : '';
            $fields['address1'] = htmlspecialchars($address['address1']);
            $fields['address2'] = htmlspecialchars($address['address2']);
            $fields['city'] = htmlspecialchars($address['city']);
            $fields['state'] = htmlspecialchars($address['state']);
            $fields['country'] = htmlspecialchars($address['country']);
            $fields['zip'] = htmlspecialchars($address['zip']);
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
        $by_gc = $cart->getInfo('apply_gc');
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
                $item_count++;
                //$item_parts = explode('|', $item['item_id']);
                //$db_item_id = $item_parts[0];
                //$options = isset($item_parts[1]) ? $item_parts[1] : '';
                $P = \Shop\Product::getByID($item->product_id, $custom_arr);
                $db_item_id = DB_escapeString($item->product_id);
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
                $fields['item_name_' . $i] = htmlspecialchars($item->description);
                $total_amount += $item->getPrice();
                if (isset($item->extras['custom']) && is_array($item->extras['custom'])) {
                    foreach ($item->extras['custom'] as $id=>$val) {
                        $fields['on'.$oc.'_'.$i] = $P->getCustom($id);
                        $fields['os'.$oc.'_'.$i] = $val;
                        $oc++;
                    }
                }
                $fields['quantity_' . $i] = $item->getQuantity();

                if ($item->shipping > 0) {
                    $fields['shipping_' . $i] = $item->getShipping();
                    $shipping += $item->shipping;
                }
                $i++;
            }

            if ($cart->getShipping() > 0) {
                $fields['shipping_1'] = $cart->getShipping();
                $shipping += $cart->getShipping();
            }

            //$fields['tax_cart'] = (float)$cart->getInfo('tax');
            $fields['tax_cart'] = (float)$cart->getTax();
            $total_amount += $cart->getTax();
            if ($shipping > 0) $total_amount += $shipping;
        }

        // Set the business e-mail address based on the total puchase amount
        // There must be an address configured; if not then this gateway can't
        // be used for this purchase
        $this->setReceiver($total_amount);
        $fields['business'] = $this->receiver_email;
        if (empty($fields['business']))
            return '';

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
        global $_CONF, $_SHOP_CONF;

        // Make sure button encryption is enabled and needed values are set
        if ($this->getConfig('encrypt') != 1 ||
            empty($this->getConfig('prv_key')) ||
            empty($this->getConfig('pub_key')) ||
            empty($this->getConfig('pp_cert')) ||
            $this->cert_id == '') {
            return '';
        }

        $keys = array();
        // Now check that the files exist and can be read
        foreach (array('prv_key', 'pub_key', 'pp_cert') as $idx=>$name) {
            $keys[$name] = $_SHOP_CONF['tmpdir'] . 'keys/' . $this->getConfig($name);
            if (!is_file($keys[$name]) ||
                !is_readable($keys[$name])) {
                return '';
            }
        }

        // Create a temporary file to begin storing our data.  If this fails,
        // then return.
        $dataFile = tempnam($_SHOP_CONF['tmpdir'].'cache/', 'data');
        if (!is_writable($dataFile)) {
            return '';
        }

        $plainText = '';
        $signedText = array();
        $encText = '';

        $pub_key = @openssl_x509_read(file_get_contents($keys['pub_key']));
        if (!$pub_key) {
            SHOP_log("Failed reading public key from {$keys['pub_key']}", SHOP_LOG_ERROR);
            return '';
        }
        $prv_key = @openssl_get_privatekey(file_get_contents($keys['prv_key']));
        if (!$prv_key) {
            SHOP_log("Failed reading private key from {$keys['prv_key']}", SHOP_LOG_ERROR);
            return '';
        }
        $pp_cert = @openssl_x509_read(file_get_contents($keys['pp_cert']));
        if (!$pp_cert) {
            SHOP_log("Failed reading PayPal certificate from {$keys['pp_cert']}", SHOP_LOG_ERROR);
            return '';
        }

        //  Make sure this key and certificate belong together
        if (!openssl_x509_check_private_key($pub_key, $prv_key)) {
            SHOP_log("Mismatched private & public keys", SHOP_LOG_ERROR);
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
        if (!openssl_pkcs7_sign($dataFile . '_plain.txt',
                    $dataFile . '_signed.txt',
                    $pub_key,
                    $prv_key,
                    array(),
                    PKCS7_BINARY)) {
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
        if (!openssl_pkcs7_encrypt($dataFile . '_signed.txt',
                    $dataFile . '_enc.txt',
                    $pp_cert,
                    array(),
                    PKCS7_BINARY)) {
            return '';
        }

        // Parse the encrypted file between header and content
        $encryptedData = explode("\n\n",
                file_get_contents($dataFile . "_enc.txt"));
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
     * @uses    PrepareCustom()
     * @uses    Gateway::_ReadButton()
     * @uses    Gateway::_SaveButton()
     * @param   object  $P      Product Item object
     * @return  string          HTML code for the button.
     */
    public function ProductButton($P)
    {
        global $_SHOP_CONF, $LANG_SHOP;

        // Make sure we want to create a buy_now-type button
        if ($P->getPrice() == 0 || $P->isPhysical()) {
            return '';    // Not for items that require shipping or are free
        }
        $btn_type = $P->btn_type;
        if (empty($btn_type)) return '';

        $btn_info = self::gwButtonType($btn_type);
        $this->AddCustom('transtype', $btn_type);
        $gateway_vars = '';

        if (empty($gateway_vars)) {
            $vars = array();
            $vars['cmd'] = $btn_info['cmd'];
            $this->setReceiver($P->getPrice());
            $vars['business'] = $this->receiver_email;
            $vars['item_number'] = htmlspecialchars($P->id);
            $vars['item_name'] = htmlspecialchars($P->short_description);
            $vars['currency_code'] = $this->currency_code;
            $vars['custom'] = $this->PrepareCustom();
            $vars['return'] = SHOP_URL . '/index.php?thanks=paypal';
            $vars['cancel_return'] = $P->getCancelUrl();
            $vars['amount'] = $P->getPrice();

            // Get the allowed buy-now quantity. If not defined, set
            // undefined_quantity.
            $qty = $P->getFixedQuantity();
            if ($qty < 1) {
                $vars['undefined_quantity'] = '1';
            } else {
                $vars['quantity'] = $qty;
            }

            $vars['notify_url'] = $this->ipn_url;

            if ($P->weight > 0) {
                $vars['weight'] = $P->weight;
            } else {
                $vars['no_shipping'] = '1';
            }

            switch ($P->shipping_type) {
            case 0:
                $vars['no_shipping'] = '1';
                break;
            case 2:
                $shipping = \Shop\Shipper::getBestRate($P->shipping_units)->best_rate;
                $shipping += $P->getShipping();
                $vars['shipping'] = $shipping;
                $vars['no_shipping'] = '1';
                break;
            case 1:
                $vars['no_shipping'] = '2';
                break;
            }

            /*if ($P->taxable) {
                $vars['tax_rate'] = sprintf("%0.4f", SHOP_getTaxRate() * 100);
            }*/

            // Buy-now product button, set default billing/shipping addresses
            $U = self::Customer();
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
                $enc_btn = $this->_encButton($vars);
                if (!empty($enc_btn)) {
                    $gateway_vars .=
                    '<input type="hidden" name="cmd" value="_s-xclick" />'.LB .
                    '<input type="hidden" name="encrypted" value=\'' .
                        $enc_btn . '\' />' . "\n";
                }
            }
            if (empty($enc_btn)) {
                // Create unencrypted buttons if not configured to encrypt,
                // or if encryption fails.
                foreach ($vars as $name=>$value) {
                    $gateway_vars .= '<input type="hidden" name="' . $name .
                        '" value="' . $value . '" />' . "\n";
                }
            //} else {
            //    $this->_SaveButton($P, $btn_key, $gateway_vars);
            }
        }

        // Set the text for the button, falling back to our Buy Now
        // phrase if not available
        $btn_text = $P->btn_text;    // maybe provided by a plugin
        if ($btn_text == '') {
            $btn_text = isset($LANG_SHOP['buttons'][$btn_type]) ?
                $LANG_SHOP['buttons'][$btn_type] : $LANG_SHOP['buy_now'];
        }
        $T = SHOP_getTemplate('btn_' . $btn_info['tpl'], 'btn', 'buttons/' . $this->gw_name);
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
     * Get a button for an external item, not one of our catalog items.
     *
     * @uses    getActionUrl()
     * @uses    AddCustom()
     * @uses    setReceiver()
     * @param   array   $attribs    Array of standard item attributes
     * @param   string  $type       Type of button (buy_now, etc.)
     * @return  string              HTML for button
     */
    public function ExternalButton($attribs = array(), $type = 'buy_now')
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $T = SHOP_getTemplate('btn_' . $type, 'btn', 'buttons/' . $this->gw_name);
        $btn_text = isset($LANG_SHOP['buttons'][$type]) ?
                $LANG_SHOP['buttons'][$type] : $LANG_SHOP['buy_now'];
        $amount = isset($attribs['amount']) ? (float)$attribs['amount'] : 0;
        $this->setReceiver($amount);
        $this->AddCustom('transtype', $type);
        if (isset($attribs['custom']) && is_array($attribs['custom'])) {
            foreach ($attribs['custom'] as $key => $value) {
                $this->AddCustom($key, $value);
            }
        }
        $cmd = '_xclick';       // default Paypal command type
        if (isset($attribs['cmd'])) {
            $valid_cmds = array('_xclick', '_cart', '_oe-gift-certificate',
                '_xclick-subscriptions', '_xclick-auto-billing',
                '_xclick-payment-plan', '_donations');
            if (in_array($attribs['cmd'], $valid_cmds)) {
                $cmd = $attribs['cmd'];
            }
        }
        $vars = array(
            'cmd'           => $cmd,
            'business'      => $this->receiver_email,
            'item_number'   => $attribs['item_number'],
            'item_name'     => $attribs['item_name'],
            'currency_code' => $this->currency_code,
            'custom'        => $this->PrepareCustom(),
            'return'        => isset($attribs['return']) ? $attribs['return'] :
                            SHOP_URL . '/index.php?thanks=paypal',
            'rm'            => 1,
            'notify_url'    => $this->ipn_url,
            'amount'        => $amount,
        );

        // Add options, if present.  Only 2 are supported, and the amount must
        // already be included in the $amount above.
        // Option variables are shown on the checkout page, but the custom value
        // is what's really used to process the purchase since that's available
        // to all gateways.
        if (isset($attribs['options']) && is_array($attribs['options'])) {
            $i = 0;
            foreach ($attribs['options'] as $name => $value) {
                $this->addcustom($name, $value);
                $vars['on' . $i] = $name;
                $vars['os' . $i] = $value;
                $i++;
            }
        }

        if (!isset($attribs['quantity']) || $attribs['quantity'] == 0) {
            $vars['undefined_quantity'] = '1';
        } else {
            $vars['quantity'] = $attribs['quantity'];
        }

        if (isset($attribs['weight']) && $attribs['weight'] > 0) {
            $vars['weight'] = $attribs['weight'];
        } else {
            $vars['no_shipping'] = '1';
        }

        if (!isset($attribs['shipping_type']))
            $attribs['shipping_type'] = 0;
        switch ($attribs['shipping_type']) {
        case 0:
            $vars['no_shipping'] = '1';
            break;
        case 2:
            $vars['shipping'] = $attribs['shipping_amt'];
        case 1:
            $vars['no_shipping'] = '2';
            break;
        }

        // Set the tax flag.  If item is taxable ($attribs['taxable'] == 1), then set
        // the tax amount to the specific $attribs['tax'] amount if given.  If no tax
        // amount is given for a taxable item, do not set the value- let PayPal calculate
        // the tax.  Setting $vars['tax'] to zero means no tax is charged.
        if (isset($attribs['taxable']) && $attribs['taxable'] > 0) {
            if (isset($attribs['tax']) && $attribs['tax'] > 0) {
                $vars['tax'] = (float)$attribs['tax'];
            }
        } else {
            $vars['tax'] = '0';
        }

        if ($this->getConfig('encrypt')) {
            $enc_btn = $this->_encButton($vars);
            if (!empty($enc_btn)) {
                $vars = array(
                    'encrypted' => $enc_btn,
                    'cmd'       => '_s-xclick',
                );
            }
        }
        $gateway_vars = '';
        foreach ($vars as $name=>$value) {
            $gateway_vars .= '<input type="hidden" name="' . $name .
                        '" value="' . $value . '" />' . "\n";
        }
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
     * Prepare to save the configuraiton.
     * This copies the new config values into our local variables, then
     * calls the parent function to save to the database.
     *
     * @param   array   $A      Array of name=>value pairs (e.g. $_POST)
     */
    public function SaveConfig($A = NULL)
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
     * Get the custom string properly formatted for the gateway.
     *
     * @return  string      Formatted custom string
     */
    protected function PrepareCustom()
    {
        return str_replace('"', '\'', serialize($this->custom));
    }


    /**
     * Sets the receiver_email and cert_id properties.
     *
     * @param   float   $amount     Total puchase amount.
     */
    private function setReceiver($amount)
    {
        // Available receiver_email addresses
        $aEmail = array(
            array('bus_prod_email', 'micro_prod_email'),
            array('bus_test_email', 'micro_test_email'),
        );

        // Available cert_id properties
        $aCert = array(
            array('pp_cert_id', 'micro_cert_id'),
            array('sandbox_main_cert', 'sandbox_micro_cert'),
        );

        // Set the array keys based on test mode and amount
        $kTest = $this->isSandbox() ? 1 : 0;
        $kAmount = $amount < $this->getConfig('micro_threshold') ? 1 : 0;
        $this->receiver_email = !empty($this->getConfig($aEmail[$kTest][$kAmount])) ?
                $this->getConfig($aEmail[$kTest][$kAmount]) :
                $this->getConfig($aEmail[$kTest][0]);

        $this->cert_id = !empty($this->getConfig($aCert[$kTest][$kAmount])) ?
                $this->getConfig($aCert[$kTest][$kAmount]) :
                $this->getConfig($aCert[$kTest][0]);
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
            'pmt_gross'     => $data['mc_gross'] . ' ' . $data['mc_currency'],
            'verified'      => $data['payer_status'],
            'pmt_status'    => $data['payment_status'],
            'buyer_email'   => $data['payer_email'],
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
        return $this->button_url;
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
     * Get the Paypal API token to be used for web requests.
     *
     * @return  string  API token value
     */
    public function getBearerToken()
    {
        global $_SHOP_CONF;     // TODO: move tokens to gw config

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->api_url . '/v1/oauth2/token',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $_SHOP_CONF['pp_api_username'] . ':' . $_SHOP_CONF['pp_api_passwd'],
            CURLOPT_HTTPHEADER  => array (
                'Accept: application/json',
            ),
            CURLOPT_RETURNTRANSFER  => true,
        ) );

        $result = curl_exec($ch);
        curl_close($ch);

        $auth = json_decode($result, true);
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
     * @param   string  $order_num  Order Number
     * @return  boolean     True on success, False on error
     */
    public function createInvoice($order_num)
    {
        global $_CONF, $LANG_SHOP;

        $access_token = $this->getBearerToken();
        if (!$access_token) {
            SHOP_log("Could not get Paypal access token", SHOP_LOG_ERROR);
            return false;
        }
        echo "Token: $access_token\n";

        $Shop = new Company();
        $Order = Order::getInstance($order_num);
        $Currency = Currency::getInstance($Order->currency);
        $Billto = $Order->getBillto();
        $Shipto = $Order->getShipto();

        $A = array(
            'detail' => array(
                'invoice_number' => $order_num,
                'reference' => $order_num,
                'currency_code' => $Order->currency,
                'payment_term' => array(
                    'term_type' => $this->getInvoiceTerms(),
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
                        'email_address' => $Order->buyer_email,
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
                    'shipping' => array(
                        'amount' => array(
                            'currency_code' => $Order->getCurrency()->getCode(),
                            'value' => $Currency->FormatValue($Order->getShipping()),
                        ),
                    ),
                ),
            ),
        );
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
            if ($OI->getProduct()->isTaxable()) {
                $item['tax'] = array(
                    'name' => $LANG_SHOP['tax'],
                    'percent' => $OI->getTax(),
                );
            }
            $A['items'][] = $item;
        }
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
            /*        echo "Failed to send invoice\n";
                    var_dump($http_code);
            var_dump($send_response);*/
                    SHOP_log("Error sending invoice for $order_num. Code $http_code, Text: $send_response", SHOP_LOG_ERROR);
                    return false;
                }
            }
        } else {
//            var_dump($inv);
            SHOP_log("Error creating invoice for $order_num", SHOP_LOG_ERROR);
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
        if ($this->getConfig('test_mode')) {
            return $this->getConfig('sandbox_webhook_id');
        } else {
            return $this->getConfig('prod_webhook_id');
        }
    }


    /**
     * Check if the gateway supports invoicing. Default is false.
     *
     * @return  boolean True if invoicing is supported, False if not.
     */
    public function supportsInvoicing()
    {
        return true;
    }

}

?>
