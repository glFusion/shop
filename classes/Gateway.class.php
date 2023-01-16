<?php
/**
 * Payment gateway class.
 * Provides the base class for actual payment gateway classes to use.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\FileSystem;
use glFusion\Database\Database;
use Shop\Config;
use Shop\Cart;
use Shop\Order;
use Shop\Product;
use Shop\Customer;
use Shop\Log;
use Shop\Models\OrderStatus;
use Shop\Models\ProductType;
use Shop\Models\CustomInfo;
use Shop\Models\ButtonKey;
use Shop\Models\PayoutHeader;
use Shop\Models\PaymentRadio;
use Shop\Models\GatewayInfo;
use Shop\Models\DataArray;
use Shop\Models\CustomerGateway;
use Shop\Cache;


/**
 * Base class for Shop payment gateway.
 * Provides common variables and methods required by payment gateway classes.
 * @package shop
 */
class Gateway
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Gateway logo width, in pixels.
     * @const integer */
    protected const LOGO_WIDTH = 240;

    /** Gateway logo height, in pixels.
     * @const integer */
    protected const LOGO_HEIGHT = 40;

    /** Gateway information from the JSON file.
     * @var GatewayInfo */
    protected $GatetwayInfo = NULL;

    /** Table name, used by DBO class.
     * @var string */
    public static $TABLE = 'shop.gateways';

    /** Items on this order.
     * @var array */
    protected $items = array();

    /** The short name of the gateway, e.g. "shop" or "amazon".
     * @var string */
    protected $gw_name = '';

    /** The long name or description of the gateway, e.g. "Amazon SimplePay".
     * @var string */
    protected $gw_desc = 'Unknown Payment Gateway';

    /** The provider name, e.g. "Amazon" or "Shop".
     * @var string; */
    protected $gw_provider = '';

    /** Services (button types) provided by the gateway.
     * This is an array of button_name=>0/1 to indicate which services are available.
     * @var array */
    protected $services = NULL;

    /** The gateway's configuration items.
     * This is an associative array of name=>value elements.
     * @var array */
    protected $config = array();

    /** Configuration item names, to create the config form.
     *@var array */
    protected $cfgFields = array();

    /** The URL to the gateway's IPN processor.
     * @var string */
    protected $ipn_url = '';

    protected $ipn_filename = 'ipn.php';

    /** Indicator of whether the gateway is enabled at all.
     * @var boolean */
    protected $enabled = 0;

    /** Order in which the gateway is selected.
     * Gateways are selected from lowest to highest order.
     * @var integer */
    protected $orderby = 999;

    /** Gateway installed version.
     * For bundled gateways this will be the plugin version.
     * Other installable gateways will have their own versions.
     * @var string */
    protected $version = '';

    /** Environment configuration for prod or test.
     * Also contains global settings. This is a subset of `$config`.
     * @var array */
    private $envconfig = array();

    /**
     * This is a CustomInfo object containing custom data to be passed to the
     * gateway and returned verbatim.
     * @var object
     */
    protected $custom = NULL;

    /**
     * The URL to the payment gateway. This must be set by the derived class.
     * getActionUrl() can be overriden by the derived class to apply additional
     * logic to the url before it is used to create payment buttons.
     *
     * @var string
     */
    protected $gw_url = '';

    /** The postback URL for verification of IPN messages.
     * If not set the value of gw_url will be used.
     * @var string */
    protected $postback_url = NULL;

    /** Checkout button url.
     * @var string */
    protected $button_url = '';

    /** Array to hold cached gateways.
     * @var array */
    private static $gateways = array();

    /** Currency code.
     * @var string */
    protected $currency_code = 'USD';

    /** Language strings specific to this gateway.
     * @var array */
    protected $lang = array();

    /** ID of user group authorized to use this gateway.
     * This may be used for non-upfront payment terms such as check,
     * net 30 or COD.
     * @var integer */
    protected $grp_access = 1;

    /** Flag to indicate if the gateway requires a billing address.
     * @var boolean */
    protected $req_billto = 0;

    /** Indicate that the checkout process redirects to the provider.
     * Causes the spinner to stay on the page until redirected to avoid
     * confusion for the buyer.
     * @var boolean */
    protected $do_redirect = true;

    /** Are payments taken online?
     * @var boolean */
    protected $can_pay_online = 1;


    /**
     * Constructor. Initializes variables.
     * Derived classes should set the gw_name, gw_desc and config values
     * before calling this constructor, to make sure these properties are
     * correct.  This function merges the config items read from the database
     * into the existing config array.
     *
     * Optionally, the child can also create the services array if desired,
     * to limit the services provided.
     *
     * @uses    self::AddCustom()
     * @param   array   $A  Optional array of fields, used with getInstance()
     */
    function __construct(array $A = array())
    {
        global $_TABLES, $_USER;

        $this->GatewayInfo = $this->readJSON();
        $this->custom = new CustomInfo;
        $this->properties = array();
        $this->getIpnUrl();     // construct the IPN processor URL
        $this->currency_code = Config::get('currency');
        if (empty($this->currency_code)) {
            $this->currency_code = 'USD';
        }

        // Set the provider name if not supplied by the gateway
        if (empty($this->gw_provider)) {
            $this->gw_provider = ucfirst($this->gw_name);
        }

        // The child gateway can override the services array.
        if (!isset($this->services)) {
            $this->services = array(
                'buy_now'   => 0,
                'donation'  => 0,
                'pay_now'   => 0,
                'subscribe' => 0,
                'checkout'  => 0,
                'external'  => 0,
            );
        }

        if (empty($A)) {
            $db = Database::getInstance();
            try {
                $A = $db->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.gateways']}
                    WHERE id = ?",
                    array($this->gw_name),
                    array(Database::STRING)
                )->fetchAssociative();
            } catch (\Exception $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }
        }

        if (!empty($A)) {
            $A = new DataArray($A);
            $this->orderby = $A->getInt('orderby');
            $this->enabled = $A->getInt('enabled');
            $this->grp_access = $A->getInt('grp_access', 2);
            $this->version = $A->getString('version');
            $services = @unserialize($A->getString('services'));
            if ($services) {
                foreach ($services as $name=>$status) {
                    if (isset($this->services[$name])) {
                        $this->services[$name] = $status;
                    }
                }
            }
            $cfg_arr = @unserialize($A->getString('config'));
            if (!empty($cfg_arr)) {
                foreach ($cfg_arr as $env=>$props) {
                    if (!is_array($props)) {
                        continue;   // something bad happened
                    }
                    foreach ($props as $key=>$value) {
                        if (array_key_exists($key, $this->cfgFields[$env])) {
                            if ($this->cfgFields[$env][$key] == 'password') {
                                $decrypted = COM_decrypt($value);
                                if ($decrypted !== false) {
                                    $value = $decrypted;
                                }
                            }
                            $this->config[$env][$key] = $value;
                        }
                    }
                }
            } else {
                $this->cfg = $this->cfgFields;
            }
        }

        // Set the environment (sandbox vs. production)
        $this->setEnv();

        // The user ID is usually required, and doesn't hurt to add it here.
        $this->AddCustom('uid', $_USER['uid']);

        // If the actual gateway class doesn't define a postback url,
        // then assume it's the gateway url.
        if ($this->postback_url === NULL) {
            $this->postback_url = $this->gw_url;
        }
        $this->loadLanguage();
    }


    /**
     * Magic getter function.
     * Returns the requested value if set, otherwise returns NULL.
     * Note that derived classes must define their own __set() function.
     *
     * @param   string  $key    Name of property to return
     * @return  mixed   property value if defined, otherwise returns NULL
     */
    public function __get(string $key)
    {
        switch ($key) {
        case 'buy_now':
        case 'pay_now':
        case 'donation':
        case 'subscribe':
            return $this->services->getInt($key, NULL);
            /*if (isset($this->services[$key])) {
                return $this->services[$key];
            } else {
                return NULL;
            }*/
            break;
        default:
            if (isset($this->properties[$key])) {
                return $this->properties[$key];
            } else {
                return NULL;
            }
            break;
        }
    }


    /**
     * Return the gateway short name.
     *
     * @return  string      Short name of gateway
     */
    public function getName() : string
    {
        return $this->gw_name;
    }


    /**
     * Set the user-friendly display name for the provier.
     *
     * @param   string  $name   Provider name
     * @return  object  $this
     */
    public function setDisplayName(string $name) : self
    {
        $this->gw_provider = $name;
        return $this;
    }


    /**
     * Return the gateway short name, capitlaized for display.
     *
     * @return  string      Short name of gateway
     */
    public function getDisplayName() : string
    {
        return $this->gw_provider;
    }


    /**
    *   Return the gateway description
    *
    *   @return string      Full name of the gateway
    */
    public function getDscp() : string
    {
        return $this->gw_desc;
    }


    /**
     * Make the API class functions available for gateways that need them.
     *
     * @return  object  $this
     */
    public function loadSDK() : self
    {
        $dir = __DIR__ . '/Gateways/' . $this->gw_name . '/vendor';
        if (is_dir($dir)) {
            require_once $dir . '/autoload.php';
        }
        return $this;
    }


    /**
     * Get a single buy_now-type button from the database.
     *
     * @param   object  $P      Product object
     * @param   string  $btn_key    Button Key, btn_type + price
     * @return  string      Button code, or empty if not available
     */
    protected function _ReadButton(Product $P, string $btn_key) : string
    {
        global $_TABLES;

        $BtnKey = new ButtonKey(array(
            'btn_type' => $btn_key,
            'price' => $P->getPrice(),
        ) );
        $btn_key = DB_escapeString((string)$BtnKey);
        $db = Database::getInstance();
        $btn = $db->getItem(
            $_TABLES['shop.buttons'],
            'button',
            array(
                'pi_name' => $pi_name,
                'item_id' => $item_id,
                'gw_name' => $this->gw_name,
                'btn_key' => $btn_key,
            )
        );
        return $btn;
    }


    /**
     * Save a single button to the button cache table.
     *
     * @param   object  $P          Product object
     * @param   string  $btn_key    Button key (name)
     * @param   string  $btn_value  Value to save fo rbutton
     * @param   string      HTML code for this button
     */
    protected function _SaveButton(Product $P, string $btn_key, string $btn_value) : string
    {
        global $_TABLES;

        $pi_name = DB_escapeString($P->getPluginName());
        $item_id = DB_escapeString($P->getID());
        $BtnKey = new ButtonKey(array(
            'btn_type' => $btn_key,
            'price' => $P->getPrice(),
        ) );
        $btn_key = DB_escapeString((string)$BtnKey);
        $btn_value = DB_escapeString($btn_value);

        $sql = "INSERT INTO {$_TABLES['shop.buttons']}
                (pi_name, item_id, gw_name, btn_key, button)
            VALUES
                ('{$pi_name}', '{$item_id}', '{$this->gw_name}', '{$btn_key}', '{$btn_value}')
            ON DUPLICATE KEY UPDATE
                button = '{$btn_value}'";
        //echo $sql;die;
        //Log::debug($sql);
        DB_query($sql);
    }


    /**
     * Save the gateway config variables.
     *
     * @uses    ReOrder()
     * @param   DataArray   $A  Array of config items, e.g. $_POST
     * @return  boolean         True if saved successfully, False if not
     */
    public function saveConfig(?DataArray $A = NULL) : bool
    {
        global $_TABLES;

        if (!empty($A)) {
            $this->enabled = $A->getInt('enabled');
            $this->orderby = $A->getInt('orderby');
            $this->grp_access = $A->getInt('grp_access', 2);
            $services = $A->getArray('service');
            foreach ($this->services as $name=>$enabled) {
                if (array_key_exists($name, $services)) {
                    $this->services[$name] = (int)$services[$name];
                } else {
                    $this->services[$name] = 0;
                }
            }

            // Only update config if provided from form
            foreach ($this->cfgFields as $env=>$flds) {
                foreach ($flds as $name=>$type) {
                    switch ($type) {
                    case 'checkbox':
                        $value = isset($A[$name][$env]) ? 1 : 0;
                        break;
                    case 'password':
                        $value = COM_encrypt($A[$name][$env]);
                        break;
                    default:
                        $value = $A[$name][$env];
                        break;
                    }
                    $this->setConfig($name, $value, $env);
                }
            }
        }

        $config = @serialize($this->config);
        $services = @serialize($this->services);
        if (!$config || !$services) {
            return false;
        }

        $this->clearButtonCache();   // delete all buttons for this gateway
        $db = Database::getInstance();
        try {
            $db->conn->update(
                $_TABLES['shop.gateways'],
                array(
                    'config' => $config,
                    'services' => $services,
                    'orderby' => $this->orderby,
                    'enabled' => $this->enabled,
                    'grp_access' => $this->grp_access,
                ),
                array('id' => $this->gw_name),
                array(
                    Database::STRING,
                    Database::STRING,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                )
            );
            $this->_postConfigSave();   // Run function for further setup
            Cache::clear(self::$TABLE);
            self::ReOrder();
            return true;
        } catch (\Exception $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Toggles a boolean value from zero or one to the opposite value.
     *
     * @param   integer $oldvalue   Original value, 1 or 0
     * @param   string  $varname    Field name to set
     * @param   string  $id         Gateway ID
     * @return  integer             New value, or old value upon failure
     */
    protected static function do_toggle(int $oldvalue, string $varname, string $id) : int
    {
        $newval = self::_toggle($oldvalue, $varname, $id);
        if ($newval != $oldvalue) {
            Cache::clear(self::$TABLE);
        }
        return $newval;
    }


    /**
     * Sets the "enabled" field to the specified value.
     *
     * @uses    self::_toggle()
     * @param   integer $oldvalue   Original value
     * @param   string  $id         Gateway ID
     * @return  integer             New value, or old value upon failure
     */
    public static function toggleEnabled(int $oldvalue, string $id) : int
    {
        return self::do_toggle($oldvalue, 'enabled', $id);
    }


    /**
     * Toggles the "buy_now" field value.
     *
     * @uses    self::_toggle()
     * @param   integer $oldvalue    Original value
     * @param   string  $id         Gateway ID
     * @return  integer              New value, or old value upon failure
     */
    public static function toggleBuyNow(int $oldvalue, string $id) : int
    {
        return self::do_toggle($oldvalue, 'buy_now', $id);
    }


    /**
     * Toggles the "donation" field value.
     *
     * @uses    self::_toggle()
     * @param   integer $oldvalue    Original value
     * @param   string  $id         Gateway ID
     * @return  integer              New value, or old value upon failure
     */
    public static function toggleDonation(int $oldvalue, string $id) : int
    {
        return self::do_toggle($oldvalue, 'donation', $id);
    }


    /**
     * Clear the cached buttons for this payment gateway.
     */
    public function clearButtonCache() : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['shop.buttons'],
                array('gw_name' => $this->gw_name),
                array(Database::STRING)
            );
        } catch (\Exception $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Install a new gateway into the gateways table.
     * The gateway has to be instantiated, then called as `$newGateway->Install()`.
     * The config values set by the gateways constructor will be saved.
     *
     * @return  boolean     True on success, False on failure
     */
    public function Install() : bool
    {
        global $_TABLES;

        // Only install the gateway if it isn't already installed
        $installed = GatewayManager::getAll(false);
        if (!array_key_exists($this->gw_name, $installed)) {
            if (is_array($this->config)) {
                $config = @serialize($this->config);
            } else {
                $config = '';
            }
            if (is_array($this->services)) {
                $services = @serialize($this->services);
            } else {
                $services = '';
            }
            $db = Database::getInstance();
            try {
                $db->conn->insert(
                    $_TABLES['shop.gateways'],
                    array(
                        'id' => $this->gw_name,
                        'orderby' => 990,
                        'enabled' => $this->isEnabled(),
                        'description' => $this->gw_desc,
                        'config' => $config,
                        'services' => $services,
                        'version' => $this->getCodeVersion(),
                        'grp_access' => $this->grp_access,
                    ),
                    array(
                        Database::STRING,
                        Database::INTEGER,
                        Database::INTEGER,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                        Database::INTEGER,
                    )
                );
                self::ReOrder();
                Cache::clear(self::$TABLE);
                return true;
            } catch (\Exception $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }
    }


    /**
     * Remove the current gateway.
     * This removes all of the configuration for the gateway, but not files.
     */
    public static function Remove(string $gw_name) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES[self::$TABLE],
                array('id' => $gw_name),
                array(Database::STRING)
            );
            Cache::clear(self::$TABLE);
        } catch (\Exception $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Get the checkboxes for the button types in the configuration form.
     *
     * @return  string      HTML for checkboxes
     */
    protected function getServiceCheckboxes() : string
    {
        global $LANG_SHOP;

        $T = new Template('fields');
        $T->set_file('field', 'multicheck.thtml');
        $T->set_block('field', 'optionRow', 'opt');
        foreach ($this->services as $name => $value) {
            $text = isset($LANG_SHOP['buttons'][$name]) ?
                $LANG_SHOP['buttons'][$name] : $name;
            $T->set_var(array(
                'text'      => $text,
                'varname'   => 'service',
                'valname'   => $name,
                'checked'   => $value == 1,
            ) );
            $T->parse('opt', 'optionRow', true);
        }
        $T->parse('output', 'field');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Check if a gateway supports a button type.
     * The $gw_info parameter should be the array of info for a single gateway
     * if only that gateway is to be checked.
     *
     * @param   string  $btn_type   Button type to check
     * @return  boolean             True if the button is supported
     */
    public function Supports(string $btn_type) : bool
    {
        $supports = isset($this->services[$btn_type]) && $this->services[$btn_type];
        return $supports && $this->hasValidConfig();
    }


    /**
     * Load a gateway's language file.
     * The language variable should be $LANG_SHOP_gateway and should be
     * declared "global" in the language file.
     *
     * @return  object  $this
     */
    protected function loadLanguage() : self
    {
        global $_CONF;

        $this->lang = array();
        $langfile = $_CONF['language'] . '.php';
        $langpath = __DIR__ . '/Gateways/' . $this->gw_name . '/language/';
        if (is_dir($langpath)) {    // some gateways may not have language files.
            if (!is_file($langpath . $langfile)) {
                $langfile = 'english_utf-8.php';
            }
            if (is_file($langpath . $langfile)) {
                include $langpath . $langfile;
                if (isset($LANG_SHOP_gateway) && is_array($LANG_SHOP_gateway)) {
                    $this->lang = $LANG_SHOP_gateway;
                }
            }
        }
        return $this;
    }


    /**
     * Get a language string.
     *
     * @param   string  $key    Language string key
     * @return  string      Language string, or key value if not defined
     */
    protected function getLang(string $key, ?string $default=NULL) : string
    {
        if (is_array($this->lang) && array_key_exists($key, $this->lang)) {
            return $this->lang[$key];
        } elseif ($default !== NULL) {
            return $default;
        } else {
            return $key;
        }
    }


    /**
     * Return the order status to be set when an IPN message is received.
     * The default is to mark the order "closed" for downloadable items,
     * since no further processing is needed, and "processing" for other items.
     *
     * @param   object  $Order  Order object
     * @return  string          Status of the order
     */
    public function getPaidStatus(Order $Order) : string
    {
        if ($Order->hasPhysical()) {
            $retval = OrderStatus::PROCESSING;
        } else {
            $retval = OrderStatus::CLOSED;
        }
        return $retval;
    }


    /**
     * Create an order record.
     * This is virtually identical to the function in BaseIPN.class.php
     * and is used here to create an order record when the purchase is
     * being handled by the payment gateway, without an IPN.
     *
     * @param   array   $A      Array of order info, at least a user ID
     * @param   array   $cart   The shopping cart, to get addresses, etc.
     * @return  string          Order ID just created
     */
    protected function createOrder(array $A, array $cart) : string
    {
        global $_TABLES, $_USER;

        $ord = new Order();
        $uid = isset($A['uid']) ? (int)$A['uid'] : $_USER['uid'];
        $ord->setUid($uid);
        $ord->setStatus('pending');   // so there's something in the status field

        if ($uid > 1) {
            $U = self::Customer($uid);
        }

        $BillTo = $cart->getAddress('billto');
        if (empty($BillTo) && $uid > 1) {
            $BillTo = $U->getDefaultAddress('billto');
        }

        if (is_array($BillTo)) {
            $ord->setBillto($BillTo);
        }

        $ShipTo = $cart->getAddress('shipto');
        if (empty($ShipTo) && $uid > 1) {
            $ShipTo = $U->getDefaultAddress('shipto');
        }
        if (is_array($ShipTo)) {
            $ord->setShipto($ShipTo);
        }

        $ord->setPmtMethod($this->gw_name);
        //$ord->setPmtTxnId('');
        /*$ord->tax = $this->pp_data['pmt_tax'];
        $ord->shipping = $this->pp_data['pmt_shipping'];
        $ord->handling = $this->pp_data['pmt_handling'];*/
        $ord->setBuyerEmail(DB_getItem($_TABLES['users'], 'email', "uid=$uid"));
        $ord->setLogUser(COM_getDisplayName($uid) . " ($uid)");

        //$order_id = $ord->Save();
        //return $order_id;
        return $ord;
    }


    //
    //  The next group of functions will PROBABLY need to be re-declared
    //  for each child class.  For the most part, they don't do anything useful
    //  and you won't get payment buttons or proper IPN processing without them.
    //

    /**
     * Create a "buy now" button for a catalog item.
     * Each gateway must implement its own function for payment buttons.
     *
     * @param   object  $P      Instance of a Product object for the product
     * @param   DataArray   $Props  Option override values
     * @return  string          Complete HTML for the "Buy Now"-type button
     */
    public function ProductButton(Product $P, ?DataArray $Props=NULL) : string
    {
        return '';
    }


    /**
     * Get the checkout button.
     *
     * @param   object  $cart   Shoppping cart
     * @return  string      HTML for checkout button
     */
    public function checkoutButton(Order $cart, string $text='') : string
    {
        global $_USER, $LANG_SHOP;

        if (!$this->Supports('checkout')) return '';

        $gateway_vars = $this->gatewayVars($cart);
        $T = new Template('buttons');
        $T->set_file('btn', 'btn_checkout.thtml');
        $T->set_var(array(
            'action'    => $this->getActionUrl(),
            'method'    => $this->getMethod(),
            'gateway_vars' => $gateway_vars,
            'button_url' => $this->getCheckoutButton(),
            'cart_id'   => $cart->getOrderId(),
            'uid'       => $_USER['uid'],
            'gw_js'     => $this->getCheckoutJS($cart),
            'btn_js'    => $this->getButtonJS($cart),
            'disabled'  => $cart->hasInvalid(),
            'uniqid'    => uniqid(),
            'btn_text'  => $text != '' ? $text : $LANG_SHOP['confirm_order'],
        ) );
        return $T->parse('', 'btn');
    }


    /**
     * Get the logo files for light and dark themes.
     * Gateway classes may override this if necessary.
     *
     * @return  string      Path to logo image file
     */
    protected function getLogoFile() : string
    {
        global $_SYSTEM;

        $path = __DIR__ . '/Gateways/' . $this->gw_name . '/images/';
        $default = $path . $this->gw_name . '.png';
        if (!is_file($default)) {
            $default = '';
        }
        if (
            isset($_SYSTEM['theme_hue']) &&
            $_SYSTEM['theme_hue'] == 'dark'
        ) {
            $retval = $path . $this->gw_name . '_dark.png';
        } else {
            $retval = $path . $this->gw_name . '_light.png';
        }

        if (!is_file($retval)) {
            $retval = $default;
        }
        return $retval;
    }


    /**
     * Get the URL to a logo image for this gateway.
     * Returns the description by default.
     *
     * @return  string  Gateway logo URL
     */
    public function getLogo() : string
    {
        global $_CONF;

        $srcImage = $this->getLogoFile();
        $tag = $this->gw_desc;  // default if no image or resizing fails
        if (!empty($srcImage ) && is_file($srcImage)) {
            $destPath = $_CONF['path_html'] . '/shop/images/gateways/';
            $L = new Logo;
            $L->withImage($srcImage)
              ->withDestPath($destPath)
              ->reSize(self::LOGO_WIDTH, self::LOGO_HEIGHT);
            if ($L->isValid()) {
                $tag = COM_createImage(
                    $_CONF['site_url'] . '/shop/images/gateways/' . $L->getFilename(),
                    $this->gw_desc,
                    array(
                        'width' => $L->getDestWidth(),
                        'height' => $L->getDestHeight(),
                    )
                );
            }
        }
        return $tag;
    }


    /**
     * Abstract function to add an item to the custom string.
     * This default just addes the value to an array; the child class
     * can override this if necessary
     *
     * @param   string  $key        Item name
     * @param   string  $value      Item value
     * @return  object  $this
     */
    public function AddCustom(string $key, string $value) : self
    {
        // The CustomInfo object implements ArrayAccess
        $this->custom[$key] = $value;
        return $this;
    }


    /**
     * Check that the seller email address in an IPN message is valid.
     * Default is true, override this function in the gateway to implement.
     *
     * @param   string  $email  Email address to check
     * @return  boolean     True if valid, False if not.
     */
    public function isBusinessEmail(string $email) : bool
    {
        return true;
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
    public function getActionUrl() : string
    {
        return $this->gw_url;
    }


    /**
     * Get the postback URL for transaction verification.
     *
     * @return  string      URL for postbacks
     */
    public function getPostBackUrl() : string
    {
        return $this->postback_url;
    }


    /**
     * Get the values to show in the "Thank You" message when a customer returns to our site.
     * The returned array should be formatted as shown.
     * There are no parameters, any data will be via $_GET.
     *
     * This stub function returns only an empty array, which will cause
     * a simple "Thanks for your order" message to appear, without any
     * payment details.
     *
     * @return array   Array of name=>value pairs, empty for default msg
     */
    public function thanksVars() : array
    {
        $R = array(
            //'gateway_url'   => Gateway URL for use to check purchase
            //'gateway_name'  => self::getDscp(),
        );
        return $R;
    }


    /**
     * Get the user information.
     * Just a wrapper for the Customer class to save re-reading the
     * database each time a Customer object is needed. Assumes only one
     * user's information is needed per page load.
     *
     * @return  object  Customer object
     */
    protected static function Customer() : Customer
    {
        static $Customer = NULL;

        if ($Customer === NULL) {
            $Customer = Customer::getInstance();
        }
        return $Customer;
    }


    /**
     * Get the variables to display with the IPN log.
     * This gets the variables from the gateway's IPN data into standard
     * array values to be displayed in the IPN log view.
     *
     * @param   array   $data       Array of original IPN data
     * @return  array               Name=>Value array of data for display
     */
    public function ipnlogVars(array $data) : array
    {
        return array();
    }


    /**
     * Present the configuration form for a gateway.
     * This could almost be run within the parent class, but only
     * the gateway knows the types of its configuration variables (checkbox,
     * radio, text, etc).  Therefore, this function MUST be declared in each
     * child class.
     *
     * getServiceCheckboxes() is available to create the list of checkboxes
     * for button types handled by this gateway.  Refer to the instance in
     * the shop gateway for guidance.
     *
     * @return  string      HTML for the configuration form.
     */
    public function Configure() : string
    {
        global $_CONF, $LANG_SHOP, $_TABLES;

        $T = new Template;
        $T->set_file(array(
            'tpl' => 'gateway_edit.thtml',
            'tips' => 'tooltipster.thtml',
        ) );
        $svc_boxes = $this->getServiceCheckboxes();
        $doc_url = SHOP_getDocUrl(
            'gwhelp_' . $this->gw_name,
            $_CONF['language']
        );
        // Load the language for this gateway and get all the config fields
        $this->loadLanguage();
        $T->set_var(array(
            'gw_description' => $this->gw_desc,
            'gw_id'         => $this->gw_name,
            'orderby'       => $this->orderby,
            'enabled_chk'   => $this->enabled == 1 ? ' checked="checked"' : '',
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'doc_url'       => $doc_url,
            'svc_checkboxes' => $svc_boxes,
            'gw_instr'      => $this->getInstructions(),
            'grp_access_sel' => COM_optionList(
                $_TABLES['groups'],
                'grp_id,grp_name',
                $this->grp_access
            ),
            'inst_version'  => $this->version,
            'code_version'  => $this->getCodeVersion(),
            'need_upgrade'  => !COM_checkVersion($this->version, $this->getCodeVersion()),
        ), false, false);

        foreach ($this->cfgFields as $env=>$flds) {
            $fields = $this->getConfigFields($env);
            if (empty($fields)) {
                continue;
            }
            $T->set_var('have_' . $env, true);
            $T->set_block('tpl', $env . 'Row', 'Row' . $env);
            foreach ($fields as $name=>$field) {
                // Format the parameter name nicely for the form
                if (isset($this->lang[$name])) {
                    $prompt = $this->lang[$name];
                } else {
                    $parts = array_map('ucfirst', explode('_', $name));
                    $prompt = implode(' ', $parts);
                }
                $T->set_var(array(
                    'param_name'    => $prompt,
                    'field_name'    => $name,
                    'param_field'   => $field['param_field'],
                    'other_label'   => isset($field['other_label']) ? $field['other_label'] : '',
                    'hlp_text'      => $this->getLang('hlp_' . $name, ''),
                ) );
                $T->parse('Row' . $env, $env . 'Row', true);
            }
        }
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output', 'tpl');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
     * Create the fields for the configuration form.
     * Can be overridden by a specific gateway if necessary.
     *
     * @param   string  $env    Environment (test, prod or global)
     * @return  array   Array of fields (name=>field_info)
     */
    protected function getConfigFields(string $env='global') : array
    {
        $fields = array();
        if (!array_key_exists($env, $this->cfgFields)) {
            return $fields;
        }
        foreach ($this->cfgFields[$env] as $name=>$type) {
            $fld_name = "{$name}[{$env}]";
            switch ($type) {
            case 'checkbox':
                $field = FieldList::checkbox(array(
                    'name' => $fld_name,
                    'checked' => (isset($this->config[$env][$name]) &&
                        $this->config[$env][$name] == 1),
                ) );
                break;
            case 'select':
                $opts = $this->getConfigOptions($name, $env);
                $options = array();
                foreach ($opts as $opt) {
                    $options[$opt['name']] = array(
                        'value' => $opt['value'],
                        'selected' => $opt['selected'],
                    );
                }
                $field = FieldList::select(array(
                    'name' => $fld_name,
                    'options' => $options,
                ) );
                break;
            default:
                if (is_array($type)) {
                    // Create a selection of the options available
                    $options = array();
                    foreach ($type as $value) {
                        $options[$value] = array(
                            'value' => $value,
                            'selected' => $this->config[$env][$name] == $value,
                        );
                    }
                    $field = FieldList::select(array(
                        'name' => $fld_name,
                        'options' => $options,
                    ) );
                } else {
                    if (isset($this->config[$env][$name])) {
                        $val = $this->config[$env][$name];
                    } else {
                        $val = '';
                    }
                    $field = FieldList::text(array(
                        'name' => $fld_name,
                        'value' => $val,
                    ) );
                }
                break;
            }
            $fields[$name] = array(
                'param_field' => $field,
                'doc_url'       => '',
            );
        }
        return $fields;
    }


    /**
     * Create an instance of a gateway.
     * This function ignores the `installed` state and simply creates an object
     * from the class file, if present.
     *
     * @param   string  $gw_name    Gateway name
     * @return  object      Gateway object
     */
    public static function create(string $gw_name) : self
    {
        $cls = __NAMESPACE__ . "\\Gateways\\{$gw_name}\\Gateway";
        if (class_exists($cls)) {
            $gw = new $cls;
        } else {
            $gw = NULL;
        }
        return $gw;
    }


    /**
     * Get an instance of a gateway.
     * Supports reading multiple gateways, but only one is normally needed.
     *
     * @param   string  $gw_name    Gateway name, or '_enabled' for all enabled
     * @param   array   $A          Optional array of fields and values
     * @return  object              Gateway object
     */
    public static function getInstance(string $gw_name, array $A=array()) : self
    {
        global $_TABLES;

        static $gateways = NULL;
        if ($gateways === NULL) {
            // Load the gateways once
            $gateways = GatewayManager::getAll(false);
        }
        if (!array_key_exists($gw_name, $gateways)) {
            $gateways[$gw_name] = new self;
        }
        return $gateways[$gw_name];
    }


    /**
     * Helper function to get only enabled gateways.
     *
     * @return  array       Array of installed and enabled gateways
     */
    public static function getEnabled() : array
    {
        return GatewayManager::getAll(true);
    }


    /**
     * Get the value of a single configuration item.
     * setEnv() must be called first to set up the environment config.
     *
     * @param   string  $cfgItem    Name of field to get
     * @return  mixed       Value of field, empty string if not defined
     */
    public function getConfig(string $cfgItem = '')
    {
        if ($cfgItem == '') {
            // Get all items at once
            return $this->envconfig;
            // Get a single item
        } elseif (array_key_exists($cfgItem, $this->envconfig)) {
            return $this->envconfig[$cfgItem];
        } else {
            // Item specified but not found, return empty string
            return '';
        }
    }


    /**
     * See if this gateway is in Sandbox mode.
     *
     * @return  boolean     True if the test_mode config var is set
     */
    public function isSandbox() : bool
    {
        if (
            isset($this->config['global']['test_mode']) &&
            $this->config['global']['test_mode']
        ) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get the configuration settings for a specific environment.
     *
     * @param   string  $env    Environment (test, prod or global)
     * @return  array       Configuration array
     */
    public function getEnvConfig($env='global') : array
    {
        return $this->config[$env];
    }


    /**
     * Set the current environment, normally 'prod' or 'test'.
     * Gets the environment configuration into the `envconfig` property
     * and merges in the global configuration.
     * If an environment is not specified, either 'test' or 'prod is selected
     * based on the `test_mode` setting.
     *
     * @param   string  $env    Selected Environment
     * @return  object  $this
     */
    public function setEnv(?string $env=NULL) : self
    {
        if ($env === NULL) {
            $env = $this->isSandbox() ? 'test' : 'prod';
        }
        $cfg = array();
        if (isset($this->config[$env])) {
            $cfg = $this->config[$env];
        }
        if (isset($this->config['global'])) {
            $cfg = array_merge($cfg, $this->config['global']);
        }
        $this->envconfig = $cfg;
        return $this;
    }


    /**
     * Get the radio button for this gateway to show on the checkout form.
     *
     * @param   boolean $selected   True if the button should be selected
     * @return  PaymentRadio    Radio button object
     */
    public function checkoutRadio(Order $Cart, bool $selected = false) : ?PaymentRadio
    {
        $retval = new \Shop\Models\PaymentRadio;
        $retval['gw_name'] = $this->gw_name;
        $retval['value'] = $this->gw_name;
        $retval['selected'] = $selected ? 'checked="checked" ' : '';
        $retval['logo'] = $this->getLogo();
        return $retval;
    }


    /**
     * Stub function to get the gateway variables.
     * The child class should provide this.
     *
     * @param   object  $cart   Cart object
     * @return  string      Gateay variable input fields
     */
    public function gatewayVars(Order $cart) : string
    {
        return '';
    }


    /**
     * Stub function to get the HTML for a checkout button.
     * Each child class must supply this if it supports checkout.
     *
     * @return  string  HTML for checkout button
     */
    public function getCheckoutButton() : ?string
    {
        return NULL;
    }


    /**
     * Get additional javascript to be attached to the checkout button.
     * The default is to finalize the cart via AJAX.
     *
     * @param   object  $cart   Shopping cart object
     * @return  string  Javascript commands.
     */
    public function getCheckoutJS(Order $cart) : string
    {
        return 'finalizeCart("' . $cart->getOrderID() . '","' . $cart->getUID() . '", ' . $this->do_redirect . '); return true;';
    }


    public function getButtonJS(Order $cart) : ?string
    {
        return NULL;
    }


    /**
     * Get the form method to use with the final checkout button.
     * Return POST by default.
     *
     * @return  string  Form method
     */
    public function getMethod() : string
    {
        return 'post';
    }


    /**
     * Run additional functions after saving the configuration.
     * Default: Do nothing.
     */
    protected function _postConfigSave() : void
    {
        return;
    }


    /**
     * Set a configuration value.
     *
     * @param   string  $key    Name of configuration item
     * @param   mixed   $value  Value to set
     * @param   string  $env    Environment (test, prod or global)
     * @return  object  $this
     */
    public function setConfig(string $key, $value, string $env) : self
    {
        $this->config[$env][$key] = $value;
        return $this;
    }


    /**
     * Set a gateway ID as the selected one to remember between orders.
     *
     * @param   string  $gw_id  Gateway ID
     */
    public static function setSelected(string $gw_id) : void
    {
        SESS_setVar('shop_gateway_sel', $gw_id);
    }


    /**
     * Get the remembered gateway ID to use as the default on later orders.
     *
     * @return  string|null  Saved gateway, NULL if none previously saved
     */
    public static function getSelected() : ?string
    {
        return SESS_getVar('shop_gateway_sel');
    }


    /**
     * Get gateway-specific instructions, if any.
     * Gateways can override this and call adminWarnBB().
     *
     * @return  string  Instruction text
     */
    protected function getInstructions() : string
    {
        return '';
    }


    /**
     * Check if this gateway allows an order to be processed without an IPN msg.
     * Orders fully paid by gift card or otherwise free get processed without
     * a payment gateway being used.
     *
     * @return  boolean     True if no IPN required, default = false
     */
    public function allowNoIPN() : bool
    {
        return false;
    }


    /**
     * Create a warning message about whitelisting the IPN in Bad Behavior.
     * Common message applies to several gateways.
     *
     * @see     getInstructions()
     * @return  string      Warning message
     */
    protected function adminWarnBB() : string
    {
        global $LANG_SHOP_HELP, $_CONF, $_TABLES;

        $url = parse_url($this->ipn_url);
        $ipn_url = $url['path'];
        if (!empty($url['query'])) {
            $ipn_url .= '?' . $url['query'];
        }
        $db = Database::getInstance();
        $whitelisted = 0;
        if (function_exists('plugin_chkversion_bad_behavior2')) {
            try {
                $whitelisted = $db->getCount(
                    $_TABLES['bad_behavior2_whitelist'],
                    array('type', 'item'),
                    array('url', $url['path']),
                    array(Database::STRING, Database::STRING)
                );
            } catch (\Exception $e) {
                // Do nothing, $whitelisted is already zero
            }
        }
        $retval = sprintf($LANG_SHOP_HELP['gw_ipn_url_is'], $ipn_url). ' ';
        if ($whitelisted) {
            $cls = 'success';
            $retval .= $LANG_SHOP_HELP['gw_bb2_wl_done'];
        } else {
            $cls = 'danger';
            $retval .= '<br />' . sprintf($LANG_SHOP_HELP['gw_bb2_wl_needed'], $url['path']);
        }
        return SHOP_errorMessage($retval, $cls);
    }


    /**
     * Check that the current user is allowed to use this gateway.
     * This limits access to special gateways like 'check' or 'terms'.
     * Actual gateways such as Paypal also won't handle an amount of zero.
     *
     * @param   float   $total  Total order amount
     * @return  boolean     True if access is allowed, False if not
     */
    public function hasAccess(float $total=0) : bool
    {
        return $total > 0 && $this->isEnabled() && SEC_inGroup($this->grp_access);
    }


    /**
     * Set the return URL after payment is made.
     * Creates url parameters `gwname/order/token` for gateways that might
     * have issues with normal URL parameters.
     *
     * @param   string  $cart_id    Cart order ID
     * @param   string  $token      Order token, to verify accessa
     * @return  string      URL to pass to the gateway as the return URL
     */
    protected function returnUrl(string $cart_id, string $token) : string
    {
        $retval = SHOP_URL . '/index.php?thanks=' . $this->gw_name;
        if (!empty($cart_id)) {
            $retval .= '/' . $cart_id;
            if (!empty($token)) {
                $retval .= '/' . $token;
            }
        }
        return $retval;
    }


    /**
     * Get the "enabled" flag value.
     *
     * @return  integer     1 if enabled, 0 if not
     */
    protected function isEnabled() : bool
    {
        return $this->enabled ? 1 : 0;
    }


    /**
     * Return a dummy value during AJAX finalizing cart.
     *
     * @param   object  $Order  Order object
     * @return  boolean     False, indicating no action was taken
     */
    public function processOrder(Order $Order) : bool
    {
        return false;
    }


    /**
     * Check if the gateway is valid by checking that $gw_name is set.
     * Used in case getInstance() returns a generic gateway instance when
     * the requested gateway is not available.
     *
     * @return  boolean     True if valid, False if not
     */
    public function isValid() : bool
    {
        return $this->gw_name != '';
    }


    /**
     * Check if this gateway requires a billing address.
     *
     * @return  boolean     True if a billing address is required
     */
    public function requiresBillto() : bool
    {
        return $this->req_billto ? 1 : 0;
    }


    /**
     * Get the webhook url.
     * Allows a custom base url for SSL if needed.
     *
     * @param   array   $args   Array of optional arguments
     * @return  string      IPN URL
     */
    public function getWebhookUrl(array $args=array()) : string
    {
        static $urls = array();
        if (!array_key_exists($this->gw_name, $urls)) {
            $url = Config::get('ipn_url');
            if (empty($url)) {
                // Use the default IPN url
                $url = Config::get('url') . '/hooks/';
            }
            if (substr($url, -1) != '/') {
                $url .= '/';
            }
            $url .= 'webhook.php?_gw=' . $this->gw_name;
            $urls[$this->gw_name] = $url;
        }
        if (!empty($args)) {
            $params = '&' . http_build_query($args);
        } else {
            $params = '';
        }
        return $urls[$this->gw_name] . $params;
    }


    /**
     * Get the IPN processor URL.
     *
     * @param   array   $args   Array of optional arguments
     * @return  string      IPN URL
     */
    public function getIpnUrl(array $args=array()) : string
    {
        if ($this->ipn_url === '') {
            $url = Config::get('ipn_url');
            if (empty($url)) {
                // Use the default IPN url
                $url = Config::get('url') . '/ipn/';
            }
            if (substr($url, -1) != '/') {
                $url .= '/';
            }
            $this->ipn_url = $url . $this->ipn_filename . '?_gw=' . $this->gw_name;
        }
        if (!empty($args)) {
            $params = '&' . http_build_query($args);
        } else {
            $params = '';
        }
        return $this->ipn_url . $params;
    }


    /**
     * Check if online payments can be made via this gateway.
     * Most use online payments but some, like "check" and "terms"
     * require out-of-band payments.
     *
     * @return  boolean     True of online payments accepted
     */
    public function canPayOnline() : bool
    {
        return $this->can_pay_online ? 1 : 0;
    }


    /**
     * Check if payments can be made "later", e.g. pay a pending order.
     * Most gateways do not support this to avoid confusion, but invoiced
     * orders can be paid later.
     *
     * @return  boolean     True if pay-later is supported, False if not
     */
    public function canPayLater() : bool
    {
        return false;
    }


    /**
     * Create the "pay now" button for orders.
     * For most gateways this is the same as the checkout button.
     *
     * @return  string  HTML for payment button
     */
    public function payOnlineButton(Order $Order) : string
    {
        global $LANG_SHOP;

        return $this->checkoutButton($Order, $LANG_SHOP['buttons']['pay_now']);
    }


    /**
     * Check if the order can be processed.
     * For most payment methods the order can only be processed after
     * it is paid.
     *
     * @return  boolean     True to process the order, False to hold
     */
    public function okToProcess(Order $Order) : bool
    {
        return $Order->isPaid();
    }


    /**
     * Default gateway upgrade function.
     * Just sets the current version for bundled gateways,
     * ignores others.
     */
    public function doUpgrade() : bool
    {
        global $_TABLES;

        if ($this->_doUpgrade()) {
            $this->version = $this->getCodeVersion();
            $db = Database::getInstance();
            try {
                $db->conn->update(
                    $_TABLES['shop.gateways'],
                    array('version' => $this->version),
                    array('id' => $this->gw_name),
                    array(Database::STRING, Database::STRING)
                );
                Cache::clear(self::$TABLE);
                return true;
            } catch (\Exception $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * Actually perform the gateway-specific upgrade functions.
     *
     * @return  boolean     True on success, False on error
     */
    protected function _doUpgrade()
    {
        // nothing to do by default, just update the version.
        $this->version = $this->getCodeVersion();
        return true;
    }


    /**
     * Get the current gateway code version.
     *
     * @return  string      Current version
     */
    public function getCodeVersion() : string
    {
        // Some backwards compatibility
        if ($this->GatewayInfo['version'] != 'unset') {
            return $this->GatewayInfo['version'];
        } elseif (isset($this->VERSION)) {
            // legacy
            return (string)$this->VERSION;
        } else {
            // Bundled plugins that still have a gateway.json file
            // may get here. v1.5.0 upgrade removes those files so this
            // should happen only once.
            Log::system(Log::ERROR, var_export($this->readJSON(),true));
            return 'unknown';
        }
    }


    /**
     * Get the required shop version.
     * Default is the current plugin code version unless the gateway
     * has a different version set.
     *
     * @return  string      Required plugin version for the gateway
     */
    public function getShopVersion() : string
    {
        if ($this->GatewayInfo['shop_version'] == 'unset') {
            return Config::get('pi_version');   // use installed plugin version
        } else {
            return $this->GatewayInfo['shop_version'];
        }
    }


    /**
     * Get the current gateway installed version.
     *
     * @return  string      Current version
     */
    public function getInstalledVersion()
    {
        return $this->version;
    }


    /**
     * Check if this gateway is bundled with the Shop plugin.
     *
     * return   integer     1 if bundled, 0 if not.
     */
    public function isBundled() : bool
    {
        return in_array($this->gw_name, GatewayManager::$_bundledGateways);
    }


    /**
     * Stub function to confirm an order.
     * Some gateways will override this.
     *
     * @param   object  $Order  Order object
     * @return  string      Redirect URL
     */
    public function confirmOrder(Order $Order) : string
    {
        return '';
    }


    /**
     * Check that the gateway is properly configured.
     * Each gateway needs to override this.
     *
     * @return  boolean     True if a valid config is set, False if not
     */
    public function hasValidConfig() : bool
    {
        return true;
    }


    /**
     * Check if this gateway is configured to support affiliate payouts.
     *
     * @return  boolean     True if payouts are supported, otherwise false
     */
    public function supportsPayouts() : bool
    {
        return $this->getConfig('supports_payouts') ? true : false;
    }


    /**
     * Default stub function to handle payouts.
     * Gateways that support this will supply their own function.
     *
     * @param   array   $Payouts    Array of Payout objects
     */
    public function sendPayouts(array &$Payouts) : void
    {
        Log::error("Payouts not implemented for gateway {$this->gw_name}");
        foreach ($Payouts as $Payout) {
            $Payout['txn_id'] = 'n/a';
        }
    }


    /**
     * Check available versions for all pluggable gateways and add to data_arr.
     * Bundled gateways always use '0.0.0' as the version to avoid indicating
     * that an update is available.
     * Versions are added to the $data_arr array to be used in the admin list.
     *
     * @param   array   $data_arr   Reference to data array
     */
    private static function XX_checkAvailableVersions(&$data_arr) : void
    {
        global $_VARS;

        // Only check in sync with other update checks.
        $versions = Cache::get('shop_gw_versions');
        if ($versions === NULL) {
            $versions = array();
            foreach ($data_arr as $idx=>$gw) {
                $key = $gw['id'];
                $versions[$key] = self::_checkAvailableVersion($key);
            }
            Cache::set('shop_gw_versions', $versions, self::$TABLE);
        }

        foreach ($data_arr as $key=>$gw) {
            if (array_key_exists($gw['id'], $versions)) {
                $data_arr[$key]['available'] = $versions[$gw['id']]['available'];
                $data_arr[$key]['upgrade_url'] = $versions[$gw['id']]['upgrade_url'];
            } else {
                // Bundled or no version available
                $data_arr[$key]['available'] = $gw['version'];
                $data_arr[$key]['upgrade_url'] = '';
            }
        }
    }


    /**
     * Get the latest release and download URL for a single gateway.
     * Queries Github or Gitlab based on gateway.json.
     *
     * @param   string  $gwname     Gateway name
     * @return  array       Array of (available, download_link)
     */
    private static function XX_checkAvailableVersion(string $gwname) : array
    {
        $default = array(
            'available' => '0.0.0',
            'upgrade_url' => '',
        );

        $filename = __DIR__ . '/Gateways/' . $gwname . '/gateway.json';
        if (is_file($filename)) {
            $json = @file_get_contents($filename);
            $json = @json_decode($json, true);
            if (!$json || !isset($json['repo']['type'])) {
                return $default;
            }
        } else {
            return $default;
        }

        switch ($json['repo']['type']) {
        case 'gitlab':
            $releases_url = 'https://gitlab.com/api/v4/projects/' . $json['repo']['project_id'] . '/releases/';
            break;
        case 'github':
            $releases_url = 'https://api.github.com/repos/glshop-gateways/' . $json['repo']['project_id'] . '/releases/latest';
            break;
        default:
            $releases_url = '';
            break;
        }
        if (empty($releases_url)) {
            return $default;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $releases_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/vnd.github.v3+json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'glFusion Shop');
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $data = @json_decode($result, true);
        }
        if (!$data) {
            return $default;
        }

        switch ($json['repo']['type']) {
        case 'gitlab':
            $data = $data[0];
            $releases_link = $data['_links']['self'];
            break;
        case 'github':
            $releases_link = $data['html_url'];
            break;
        default:
            $releases_link = '';
            break;
        }
        if (empty($releases_link)) {
            return $default;
        }

        $latest = $data['tag_name'];
        if ($latest[0] == 'v') {
            $latest = substr($latest, 1);
        }
        return array(
            'available' => $latest,
            'upgrade_url' => $releases_link,
        );
    }


    /**
     * Get information about the gateway from the JSON config file.
     *
     * @return  object      GatewayInfo object
     */
    protected function readJSON() : GatewayInfo
    {
        $retval = new GatewayInfo;
        $filespec = __DIR__ . '/Gateways/' . $this->gw_name . '/gateway.json';
        if (is_file($filespec)) {
            $json = @file_get_contents($filespec);
            $arr = @json_decode($json, true);
            if (is_array($arr)) {
                $retval->merge($arr);
            }
        } else {
            $retval['version'] = Config::get('pi_version');
        }
        return $retval;
    }


    /**
     * Save the customer id, email and gateway's customer ID in the table.
     *
     * @param   object  $Customer   Customer object, to get uid and email
     * @return  boolean     True on success, False on error
     */
    protected function saveCustomerInfo(CustomerGateway $Info) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['shop.customerXgateway'],
                array(
                    'email' => $Info['email'],
                    'gw_id' => $this->gw_name,
                    'cust_id' => $Info['cust_id'],
                    'uid' => $Info['uid'],
                ),
                array(
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                    Database::INTEGER,
                )
            );
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            try {
                $db->conn->update(
                    $_TABLES['shop.customerXgateway'],
                    array(
                        'cust_id' => $Info['cust_id'],
                        'uid' => $Info['uid'],
                    ),
                    array(
                        'email' => $Info['email'],
                        'gw_id' => $this->gw_name,
                    ),
                    array(
                        Database::STRING,
                        Database::INTEGER,
                        Database::STRING,
                        Database::STRING,
                    )
                );
            } catch (\Throwable $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Find a customer's gateway ID from the local table.
     *
     * @param   Customer    $Customer   Customer object
     * @return  string      Customer ID, if found.
     */
    protected function getCustomerId(Customer $Customer) : ?string
    {
        global $_TABLES;

        $values = array($this->gw_name);
        $types = array(Database::STRING);
        if ($Customer->getUid() > 1) {
            // Logged-in user, locate by user ID.
            $where = 'uid = ?';
            $values[] = $Customer->getUid();
            $types[] = Database::INTEGER;
        } else {
            // Anonymous user, locate by the user-supplied email address.
            $where = 'email = ?';
            $values[] = $Customer->getEmail();
            $types[] = Database::STRING;
        }
        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.customerXgateway']}
                WHERE gw_id = ? AND $where",
                $values,
                $types
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            return new CustomerGateway($row);
        } else {
            return NULL;
        }
    }


    /**
     * Cancel a checkout session.
     * No-op by default, may be implemented by each gateway.
     *
     * @param   object  $Cart   Cart object
     */
    public function cancelCheckout(Order $Cart) : void
    {
    }

}
