<?php
/**
 * Payment gateway class.
 * Provides the base class for actual payment gateway classes to use.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Config;
use Shop\Models\OrderState;
use Shop\Models\ProductType;
use Shop\Models\CustomInfo;;
use Shop\Models\ButtonKey;;
use Shop\Models\PayoutHeader;


/**
 * Base class for Shop payment gateway.
 * Provides common variables and methods required by payment gateway classes.
 * @package shop
 */
class Gateway
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Gateway version.
     * @const string */
    protected const VERSION = '1.3.0';

    /** Gateway logo width, in pixels.
     * @const integer */
    protected const LOGO_WIDTH = 240;

    /** Gateway logo height, in pixels.
     * @const integer */
    protected const LOGO_HEIGHT = 40;

    /** Table name, used by DBO class.
     * @var string */
    protected static $TABLE = 'shop.gateways';

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

    /** Indicator of a bundled gateway, vs an installable one.
     * @var integer */
    protected $bundled = 0;

    /** Environment configuration for prod or test.
     * Also contains global settings. This is a subset of `$config`.
     * @var array */
    private $envconfig = array();

    /**
     * This is an array of custom data to be passed to the gateway.
     * How it is passed is up to the gateway, which uses the PrepareCustom()
     * function to get the array data into the desired format. AddCustom()
     * can be used to add items to the array.
     * @var array
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
    protected $grp_access = 0;

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
    function __construct($A = array())
    {
        global $_SHOP_CONF, $_TABLES, $_USER;

        $this->custom = new CustomInfo;
        $this->properties = array();
        $this->getIpnUrl();     // construct the IPN processor URL
        $this->currency_code = empty($_SHOP_CONF['currency']) ? 'USD' :
            $_SHOP_CONF['currency'];

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
            $sql = "SELECT *
                FROM {$_TABLES['shop.gateways']}
                WHERE id = '" . DB_escapeString($this->gw_name) . "'";
            $res = DB_query($sql);
            if ($res) $A = DB_fetchArray($res, false);
        }

        if (!empty($A)) {
            $this->orderby = (int)$A['orderby'];
            $this->enabled = (int)$A['enabled'];
            $this->grp_access = SHOP_getVar($A, 'grp_access', 'integer', 2);
            $this->version = SHOP_getVar($A, 'version');
            $services = @unserialize($A['services']);
            if ($services) {
                foreach ($services as $name=>$status) {
                    if (isset($this->services[$name])) {
                        $this->services[$name] = $status;
                    }
                }
            }
            $cfg_arr = @unserialize($A['config']);
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
     * Returns the requeste value if set, otherwise returns NULL.
     * Note that derived classes must define their own __set() function.
     *
     * @param   string  $key    Name of property to return
     * @return  mixed   property value if defined, otherwise returns NULL
     */
    public function __get($key)
    {
        switch ($key) {
        case 'buy_now':
        case 'pay_now':
        case 'donation':
        case 'subscribe':
            if (isset($this->services[$key])) {
                return $this->services[$key];
            } else {
                return NULL;
            }
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
    public function getName()
    {
        return $this->gw_name;
    }


    /**
     * Set the user-friendly display name for the provier.
     *
     * @param   string  $name   Provider name
     * @return  object  $this
     */
    public function setDisplayName($name)
    {
        $this->gw_provider = $name;
        return $this;
    }


    /**
     * Return the gateway short name, capitlaized for display.
     *
     * @return  string      Short name of gateway
     */
    public function getDisplayName()
    {
        return $this->gw_provider;
    }


    /**
    *   Return the gateway description
    *
    *   @return string      Full name of the gateway
    */
    public function getDscp()
    {
        return $this->gw_desc;
    }


    /**
     * Make the API class functions available for gateways that need them.
     *
     * @return  object  $this
     */
    public function loadSDK()
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
    protected function _ReadButton($P, $btn_key)
    {
        global $_TABLES;

        $pi_name = DB_escapeString($P->getPluginName());
        $item_id = DB_escapeString($P->getItemID());
        $BtnKey = new ButtonKey(array(
            'btn_type' => $btn_key,
            'price' => $P->getPrice(),
        ) );
        $btn_key = DB_escapeString((string)$BtnKey);
        $btn  = DB_getItem($_TABLES['shop.buttons'], 'button',
                "pi_name = '{$pi_name}' AND item_id = '{$item_id}' AND
                gw_name = '{$this->gw_name}' AND btn_key = '{$btn_key}'"
        );
        return $btn;
    }


    /**
     * Save a single button to the button cache table.
     *
     * @param   object  $P          Product object
     * @param   string  $btn_key    Button key (name)
     * @param   string  $btn_value  Value to save fo rbutton
     * @param   string  $btn_value  HTML code for this button
     */
    protected function _SaveButton($P, $btn_key, $btn_value)
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
        //SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
    }


    /**
     * Save the gateway config variables.
     *
     * @uses    ReOrder()
     * @param   array   $A      Array of config items, e.g. $_POST
     * @return  boolean         True if saved successfully, False if not
     */
    public function SaveConfig($A = NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->enabled = isset($A['enabled']) ? 1 : 0;
            $this->orderby = (int)$A['orderby'];
            $this->grp_access = SHOP_getVar($A, 'grp_access', 'integer', 2);
            $this->test_mode = SHOP_getVar($A, 'test_mode', 'integer');
            $services = SHOP_getVar($A, 'service', 'array');
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
        if (!$config) return false;

        $config = DB_escapeString($config);
        $services = DB_escapeString(@serialize($services));
        $id = DB_escapeString($this->gw_name);

        $sql = "UPDATE {$_TABLES['shop.gateways']} SET
                config = '$config',
                services = '$services',
                orderby = '{$this->orderby}',
                enabled = '{$this->enabled}',
                grp_access = '{$this->grp_access}'
                WHERE id='$id'";
        //echo $sql;die;
        //SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        $this->clearButtonCache();   // delete all buttons for this gateway
        if (DB_error()) {
            return false;
        } else {
            $this->_postConfigSave();   // Run function for further setup
            Cache::clear('gateways');
            self::ReOrder();
            return true;
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
    protected static function do_toggle($oldvalue, $varname, $id)
    {
        $newval = self::_toggle($oldvalue, $varname, $id);
        if ($newval != $oldvalue) {
            Cache::clear('gateways');
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
    public static function toggleEnabled($oldvalue, $id)
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
    public static function toggleBuyNow($oldvalue, $id)
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
    public static function toggleDonation($oldvalue, $id)
    {
        return self::do_toggle($oldvalue, 'donation', $id);
    }


    /**
     * Clear the cached buttons for this payment gateway.
     */
    public function clearButtonCache()
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.buttons'], 'gw_name', $this->gw_name);
    }


    /**
     * Install a new gateway into the gateways table.
     * The gateway has to be instantiated, then called as `$newGateway->Install()`.
     * The config values set by the gateways constructor will be saved.
     *
     * @return  boolean     True on success, False on failure
     */
    public function Install()
    {
        global $_TABLES;

        // Only install the gateway if it isn't already installed
        $installed = self::getAll(false);
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
            $sql = "INSERT INTO {$_TABLES['shop.gateways']} SET
                    id = '" . DB_escapeString($this->gw_name) . "',
                    orderby = 990,
                    enabled = {$this->isEnabled()},
                    description = '" . DB_escapeString($this->gw_desc) . "',
                    config = '" . DB_escapeString($config) . "',
                    services = '" . DB_escapeString($services) . "',
                    version = '" . DB_escapeString($this->getCodeVersion()) . "',
                    grp_access = 1";
            DB_query($sql);
            Cache::clear('gateways');
            return DB_error() ? false : true;
        }
        return false;
    }


    /**
     * Remove the current gateway.
     * This removes all of the configuration for the gateway, but not files.
     */
    public static function Remove($gw_name)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.gateways'], 'id', $gw_name);
        Cache::clear('gateways');
    }


    /**
     * Get the checkboxes for the button types in the configuration form.
     *
     * @return  string      HTML for checkboxes
     */
    protected function getServiceCheckboxes()
    {
        global $LANG_SHOP;

        $T = new Template;
        $T->set_file('tpl', 'gw_servicechk.thtml');
        $T->set_block('tpl', 'ServiceCheckbox', 'cBox');
        foreach ($this->services as $name => $value) {
            $T->set_var(array(
                'text'      => $LANG_SHOP['buttons'][$name],
                'name'      => $name,
                'checked'   => $value == 1 ? 'checked="checked"' : '',
            ) );
            $T->parse('cBox', 'ServiceCheckbox', true);
        }
        $T->parse('output', 'tpl');
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
    public function Supports($btn_type)
    {
        $supports = SHOP_getVar($this->services, $btn_type, 'integer', 0);
        return $supports && $this->hasValidConfig();
    }


    /**
     * Load a gateway's language file.
     * The language variable should be $LANG_SHOP_gateway and should be
     * declared "global" in the language file.
     *
     * @return  object  $this
     */
    protected function loadLanguage()
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
    protected function getLang($key, $default=NULL)
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
    public function getPaidStatus($Order)
    {
        if ($Order->hasPhysical()) {
            $retval = OrderState::PROCESSING;
        } else {
            $retval = OrderState::CLOSED;
        }
        return $retval;
    }


    /**
     * Processes the purchase, for purchases made without an IPN message.
     *
     * @param  array   $vals   Submitted values, e.g. $_POST
     */
    public function handlePurchase($vals = array())
    {
        global $_TABLES, $_CONF, $_SHOP_CONF;

        COM_errorLog('Gateway::handlePurchase deprecated');
        return;

        if (!empty($vals['cart_id'])) {
            $cart = Cart::getInstance($vals['cart_id']);
            if (!$cart->hasItems()) return; // shouldn't be empty
            $items = $cart->getItems();
        } else {
            $cart = new Cart();
        }

        // Create an order record to get the order ID
        $Order = $this->createOrder($vals, $cart);
        $db_order_id = DB_escapeString($Order->getOrderID());

        $prod_types = 0;

        // For each item purchased, record purchase in purchase table
        foreach ($items as $id=>$item) {
            list($item_number, $item_opts) = SHOP_explode_opts($id, true);

            // If the item number is numeric, assume it's an
            // inventory item.  Otherwise, it should be a plugin-supplied
            // item with the item number like pi_name:item_number:options
            if (SHOP_is_plugin_item($item_number)) {
                SHOP_log("Plugin item " . $item_number, SHOP_LOG_DEBUG);

                // Initialize item info array to be used later
                $A = array();

                // Split the item number into component parts.  It could
                // be just a single string, depending on the plugin's needs.
                $pi_info = explode(':', $item['item_number']);
                SHOP_log('Paymentgw::handlePurchase() pi_info: ' . print_r($pi_info,true), SHOP_LOG_DEBUG);

                $status = LGLIB_invokeService($pi_info[0], 'productinfo',
                        array($item_number, $item_opts),
                        $product_info, $svc_msg);
                if ($status != PLG_RET_OK) {
                    $product_info = array();
                }

                if (!empty($product_info)) {
                    $items[$id]['name'] = $product_info['name'];
                }
                SHOP_log("Paymentgw::handlePurchase() Got name " . $items[$id]['name'], SHOP_LOG_DEBUG);
                $vars = array(
                        'item' => $item,
                        'ipn_data' => array(),
                );
                $status = LGLIB_invokeService(
                    $pi_info[0],
                    'handlePurchase',
                    $vars,
                    $A,
                    $svc_msg
                );
                if ($status != PLG_RET_OK) {
                    $A = array();
                }

                // Mark what type of product this is
                $prod_types |= ProductType::VIRTUAL;

            } else {
                SHOP_log("Shop item " . $item_number, SHOP_LOG_DEBUG);
                $P = Product::getByID($item_number);
                $A = array(
                    'name' => $P->getName(),
                    'short_description' => $P->getDscp(),
                    'expiration' => $P->getExpiration(),
                    'prod_type' => $P->getProductType(),
                    'file' => $P->getFilename(),
                    'price' => $item['price'],
                );

                if (!empty($item_opts)) {
                    $opts = explode(',', $itemopts);
                    $opt_str = $P->getOptionDesc($opts);
                    if (!empty($opt_str)) {
                        $A['short_description'] .= " ($opt_str)";
                    }
                    $item_number .= '|' . $item_opts;
                }

                // Mark what type of product this is
                $prod_types |= $P->getProductType();
            }

            // An invalid item number, or nothing returned for a plugin
            if (empty($A)) {
                continue;
            }

            // If it's a downloadable item, then get the full path to the file.
            // TODO: pp_data isn't available here, should be from $vals?
            if (!empty($A['file'])) {
                $this->items[$id]['file'] = $_SHOP_CONF['download_path'] . $A['file'];
                $token_base = $this->pp_data['txn_id'] . time() . rand(0,99);
                $token = md5($token_base);
                $this->items[$id]['token'] = $token;
            } else {
                $token = '';
            }
            $items[$id]['prod_type'] = $A['prod_type'];

            // If a custom name was supplied by the gateway's IPN processor,
            // then use that.  Otherwise, plug in the name from inventory or
            // the plugin, for the notification email.
            if (empty($item['name'])) {
                $items[$id]['name'] = $A['short_description'];
            }

            // Add the purchase to the shop purchase table
            $uid = isset($vals['uid']) ? (int)$vals['uid'] : $_USER['uid'];

            $sql = "INSERT INTO {$_TABLES['shop.orderitems']} SET
                        order_id = '{$db_order_id}',
                        product_id = '{$item_number}',
                        description = '{$items[$id]['name']}',
                        quantity = '{$item['quantity']}',
                        txn_type = '{$this->gw_name}',
                        txn_id = '',
                        status = 'complete',
                        token = '$token',
                        price = " . (float)$item['price'] . ",
                        options = '" . DB_escapeString($item_opts) . "'";

            // add an expiration date if appropriate
            if (is_numeric($A['expiration']) && $A['expiration'] > 0) {
                $sql .= ", expiration = DATE_ADD('" . SHOP_now()->toMySQL() .
                        "', INTERVAL {$A['expiration']} DAY)";
            }
            //echo $sql;die;
            SHOP_log($sql, SHOP_LOG_DEBUG);
            DB_query($sql);

        }   // foreach item
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
    protected function createOrder($A, $cart)
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
     * @return  string          Complete HTML for the "Buy Now"-type button
     */
    public function ProductButton($P)
    {
        return '';
    }


    /**
     * Get the checkout button.
     *
     * @param   object  $cart   Shoppping cart
     * @return  string      HTML for checkout button
     */
    public function checkoutButton($cart, $text='')
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP;

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
     * @return  array   Array of image file paths
     */
    protected function getLogoFile()
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
    public function getLogo()
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
    public function AddCustom($key, $value)
    {
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
    public function isBusinessEmail($email)
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
    public function getActionUrl()
    {
        return $this->gw_url;
    }


    /**
     * Get the postback URL for transaction verification.
     *
     * @return  string      URL for postbacks
     */
    public function getPostBackUrl()
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
    public function thanksVars()
    {
        $R = array(
            //'gateway_url'   => Gateway URL for use to check purchase
            //'gateway_name'  => self::getDscp(),
        );
        return $R;
    }


    /**
     * Function to return the "custom" string.
     * Depends on the gateway to define this.  This default simply
     * returns an HTML-safe version of the serialized $custom array.
     *
     * @return  string  Custom string to pass to gateway
     */
    protected function PrepareCustom()
    {
        return (string)$this->custom;
        //return str_replace('"', '\'', serialize($this->custom));
    }


    /**
     * Get the user information.
     * Just a wrapper for the Customer class to save re-reading the
     * database each time a Customer object is needed. Assumes only one
     * user's information is needed per page load.
     *
     * @return  object  Customer object
     */
    protected static function Customer()
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
    public function ipnlogVars($data)
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
    public function Configure()
    {
        global $_CONF, $LANG_SHOP, $_SHOP_CONF, $_TABLES;

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
            'code_version'  => static::VERSION,
            'need_upgrade'  => !COM_checkVersion($this->version, static::VERSION),
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
     * Get all the configuration fields specifiec to this gateway.
     * Can be overridden by a specific gateway if necessary
     *
     * @param   string  $env    Environment (test, prod or global)
     * @return  array   Array of fields (name=>field_info)
     */
    protected function getConfigFields($env='global')
    {
        $fields = array();
        if (!array_key_exists($env, $this->cfgFields)) {
            return $fields;
        }
        foreach ($this->cfgFields[$env] as $name=>$type) {
            $fld_name = "{$name}[{$env}]";
            switch ($type) {
            case 'checkbox':
                $field = '<input type="checkbox" name="' . $fld_name .
                    '" value="1" ';
                if (
                    isset($this->config[$env][$name]) &&
                    $this->config[$env][$name] == 1
                ) {
                    $field .= 'checked="checked" ';
                }
                $field .= '/>';
                break;
            case 'select':
                $field = '<select name="' . $fld_name . '">' . LB;
                foreach ($this->getConfigOptions($name, $env) as $opt) {
                    $sel = $opt['selected'] ? 'selected="selected"' : '';
                    $field .= '<option value="' . $opt['value'] .
                        '" ' . $sel . '">' .
                        $opt['name'] . '</option>' . LB;
                }
                $field .= '</select>' . LB;
                break;
            default:
                if (isset($this->config[$env][$name])) {
                    $val = $this->config[$env][$name];
                } else {
                    $val = '';
                }
                $field = '<input type="text" name="' . $fld_name . '" value="' .
                    $val . '" size="60" />';
                break;
            }
            $fields[$name] = array(
                'param_field'   => $field,
                //'other_label'   => '',
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
    public static function create($gw_name)
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
    public static function getInstance($gw_name, $A=array())
    {
        global $_TABLES, $_SHOP_CONF;

        static $gateways = NULL;
        if ($gateways === NULL) {
            // Load the gateeways once
            $gateways = self::getAll(false);
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
    public static function getEnabled()
    {
        return self::getAll(true);
    }


    /**
     * Get all gateways into a static array.
     *
     * @param   boolean $enabled    True to get only enabled gateways
     * @return  array       Array of gateways, enabled or all
     */
    public static function getAll($enabled = false)
    {
        global $_TABLES, $_SHOP_CONF;

        $gateways = array();
        $key = $enabled ? 1 : 0;
        $cache_key = 'gateways_' . $key;
        $tmp = Cache::get($cache_key);
        if ($tmp === NULL) {
            $tmp = array();
            // Load the gateways
            $sql = "SELECT * FROM {$_TABLES['shop.gateways']}";
            // If not loading all gateways, get just then enabled ones
            if ($enabled) $sql .= ' WHERE enabled=1';
            $sql .= ' ORDER BY orderby';
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $tmp[] = $A;
            }
            Cache::set($cache_key, $tmp, 'gateways');
        }
        // For each available gateway, load its class file and add it
        // to the static array. Check that a valid object is
        // returned from getInstance()
        foreach ($tmp as $A) {
            $cls = __NAMESPACE__ . '\\Gateways\\' . $A['id'] . '\\Gateway';
            if (class_exists($cls)) {
                $gw = new $cls($A);
            } else {
                $gw = NULL;
            }
            if (is_object($gw)) {
                $gateways[$key][$A['id']] = $gw;
            } else {
                continue;       // Gateway enabled but not installed
            }
        }
        return $gateways[$key];
    }


    /**
     * Get the value of a single configuration item.
     * setEnv() must be called first to set up the environment config.
     *
     * @param   string  $cfgItem    Name of field to get
     * @return  mixed       Value of field, empty string if not defined
     */
    public function getConfig($cfgItem = '')
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
    public function isSandbox()
    {
        if (
            isset($this->config['global']['test_mode']) &&
            $this->config['global']['test_mode']
        ) {
            return 1;
        } else {
            return 0;
        }
    }


    /**
     * Get the configuration settings for a specific environment.
     *
     * @param   string  $env    Environment (test, prod or global)
     * @return  array       Configuration array
     */
    public function getEnvConfig($env='global')
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
    public function setEnv($env=NULL)
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
     * @return  string      HTML for radio button
     */
    public function checkoutRadio($Cart, $selected = false)
    {
        $retval = new \Shop\Models\PaymentRadio;
        $retval['gw_name'] = $this->gw_name;
        $retval['value'] = $this->gw_name;
        $retval['selected'] = $selected ? 'checked="checked" ' : '';
        $retval['logo'] = $this->getLogo();
/*
        $radio = '<input required type="radio" name="gateway" value="' .
            $this->gw_name . '" id="' . $this->gw_name . '" ' . $sel . '/>';
        $radio .= '<label for="' . $this->gw_name . '">&nbsp;' . $this->getLogo() .
            '</label>';
        return $radio;*/
        return $retval;
    }


    /**
     * Stub function to get the gateway variables.
     * The child class should provide this.
     *
     * @param   object  $cart   Cart object
     * @return  string      Gateay variable input fields
     */
    public function gatewayVars($cart)
    {
        return '';
    }


    /**
     * Stub function to get the HTML for a checkout button.
     * Each child class must supply this if it supports checkout.
     *
     * @return  string  HTML for checkout button
     */
    public function getCheckoutButton()
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
    public function getCheckoutJS($cart)
    {
        return 'finalizeCart("' . $cart->getOrderID() . '","' . $cart->getUID() . '", ' . $this->do_redirect . '); return true;';
    }


    public function getButtonJS($cart)
    {
        return NULL;
    }


    /**
     * Get an array of uninstalled gateways for the admin list.
     *
     * @param   array   $data_arr   Reference to data array
     */
    private static function getUninstalled(&$data_arr)
    {
        global $LANG32;

        $installed = self::getAll(false);
        $base_path = __DIR__ . '/Gateways';
        $dirs = scandir($base_path);
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                if (
                    $dir !== '.' && $dir != '..' &&
                    is_dir("{$base_path}/{$dir}") &&
                    is_file("{$base_path}/{$dir}/Gateway.class.php") &&
                    !array_key_exists($dir, $installed)
                ) {
                    $clsfile = 'Shop\\Gateways\\' . $dir . '\\Gateway';
                    $gw = new $clsfile;
                    if (is_object($gw)) {
                        $data_arr[] = array(
                            'id'    => $gw->getName(),
                            'description' => $gw->getDscp(),
                            'enabled' => 'na',
                            'orderby' => 999,
                        );
                    }
                }
            }
        }
        /*$files = glob(__DIR__ . '/Gateways/*');
        if (is_array($files)) {
            foreach ($files as $fullpath) {
                $parts = explode('/', $fullpath);
                list($class,$x1,$x2) = explode('.', $parts[count($parts)-1]);
                //if ($class[0] == '_') continue;     // special internal gateway
                if (array_key_exists($class, $installed)) {
                    continue; // already installed
                }
                $clsfile = 'Shop\\Gateways\\' . $class;
                $gw = new $clsfile;
                if (is_object($gw)) {
                    $data_arr[] = array(
                        'id'    => $gw->getName(),
                        'description' => $gw->getDscp(),
                        'enabled' => 'na',
                        'orderby' => 999,
                    );
                }
            }
        }*/
    }


    /**
     * Get the form method to use with the final checkout button.
     * Return POST by default.
     *
     * @return  string  Form method
     */
    public function getMethod()
    {
        return 'post';
    }


    /**
     * Run additional functions after saving the configuration.
     * Default: Do nothing.
     */
    protected function _postConfigSave()
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
    public function setConfig($key, $value, $env)
    {
        $this->config[$env][$key] = $value;
        return $this;
    }


    /**
     * Set a gateway ID as the selected one to remember between orders.
     *
     * @param   string  $gw_id  Gateway ID
     */
    public static function setSelected($gw_id)
    {
        SESS_setVar('shop_gateway_sel', $gw_id);
    }


    /**
     * Get the remembered gateway ID to use as the default on later orders.
     *
     * @return  string|null  Saved gateway, NULL if none previously saved
     */
    public static function getSelected()
    {
        return SESS_getVar('shop_gateway_sel');
    }


    /**
     * Get gateway-specific instructions, if any.
     * Gateways can override this and call adminWarnBB().
     *
     * @return  string  Instruction text
     */
    protected function getInstructions()
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
    public function allowNoIPN()
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
    protected function adminWarnBB()
    {
        global $LANG_SHOP_HELP, $_CONF, $_TABLES;

        $url = parse_url($this->ipn_url);
        $ipn_url = $url['path'];
        if (!empty($url['query'])) {
            $ipn_url .= '?' . $url['query'];
        }
        $whitelisted = 0;
        if (function_exists('plugin_chkversion_bad_behavior2')) {
            try {
                $whitelisted = DB_count(
                    $_TABLES['bad_behavior2_whitelist'],
                    array('type', 'item'),
                    array('url', $url['path'])
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
        return '<span class="uk-text-' . $cls . '">' . $retval . '</span>';
    }


    /**
     * Get all the installed gateways for the admin list.
     *
     * @param   array   $data_arr   Reference to data array
     */
    private static function getInstalled(&$data_arr)
    {
        global $_TABLES;

        $sql = "SELECT *, g.grp_name
            FROM {$_TABLES['shop.gateways']} gw
            LEFT JOIN {$_TABLES['groups']} g
                ON g.grp_id = gw.grp_access";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $gw = self::create($A['id']);
            $data_arr[] = array(
                'id'    => $A['id'],
                'orderby' => $A['orderby'],
                'enabled' => $A['enabled'],
                'description' => $A['description'],
                'grp_name' => $A['grp_name'],
                'version' => $A['version'],
                'code_version' => $gw->getCodeVersion(),
            );
        }
    }


    /**
     * Payment Gateway Admin View.
     *
     * @return  string      HTML for the gateway listing
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN,
            $LANG32;

        $data_arr = array();
        self::getInstalled($data_arr);
        self::getUninstalled($data_arr);
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['orderby'],
                'field' => 'orderby',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => 'ID',
                'field' => 'id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['description'],
                'field' => 'description',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['grp_access'],
                'field' => 'grp_name',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['version'],
                'field' => 'version',
            ),
            array(
                'text'  => $LANG_SHOP['control'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort'  => 'false',
                'align' => 'center',
            ),
        );

        $extra = array(
            'gw_count' => DB_count($_TABLES['shop.gateways']),
        );

        $defsort_arr = array(
            'field' => 'orderby',
            'direction' => 'ASC',
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/gateways.php?gwadmin',
        );
        $display .= '<form action="' . SHOP_ADMIN_URL . '/gateways.php" method="post" enctype="multipart/form-data">
		<input placeholder="Selected File" disabled="disabled" />
		<div class="tm-fileUpload uk-button uk-button-small uk-button-primary">
			<span>Select File</span>
			<input type="file" name="gw_file" id="gw_file" class="tm-upload">
		</div>
		<button class="uk-button uk-button-success uk-button-small" type="submit" name="gwupload" value="Upload">Upload</button>
	</form>';
        $display .= ADMIN_listArray(
            $_SHOP_CONF['pi_name'] . '_gwlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $data_arr, $defsort_arr,
            '', $extra, '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the options admin list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra information passed in verbatim
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            if ($A['enabled'] !== 'na') {
                $retval .= COM_createLink(
                    Icon::getHTML('edit', 'tooltip', array('title'=> $LANG_ADMIN['edit'])),
                    SHOP_ADMIN_URL . "/gateways.php?gwedit&amp;gw_id={$A['id']}"
                );
            }
            break;

        case 'enabled':
            if ($fieldvalue == 'na') {
                return COM_createLink(
                    Icon::getHTML('add'),
                    SHOP_ADMIN_URL. '/gateways.php?gwinstall&gwname=' . urlencode($A['id']),
                    array(
                        'data-uk-tooltip' => '',
                        'title' => $LANG_SHOP['ck_to_install'],
                    )
                );
            } elseif ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
                $tip = $LANG_SHOP['ck_to_disable'];
            } else {
                $switch = '';
                $enabled = 0;
                $tip = $LANG_SHOP['ck_to_enable'];
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                data-uk-tooltip
                id=\"togenabled{$A['id']}\"
                title=\"$tip\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"{$fieldname}\",".
                "\"gateway\");' />" . LB;
            break;

        case 'version':
            // Show the upgrade link if needed. Only display this for
            // installed gateways.
            if (isset($A['version'])) {
                $retval = $fieldvalue;
                if (!COM_checkVersion($fieldvalue, $A['code_version'])) {
                    $retval .= COM_createLink(
                        '&nbsp;<i class="uk-icon uk-icon-arrow-up"></i>&nbsp;',
                        Config::get('admin_url') . '/gateways.php?gwupgrade=' . $A['id'],
                        array(
                            'class' => 'tooltip uk-text-success',
                            'title' => $LANG_SHOP['upgrade'],
                        )
                    );
                    $retval .= $A['code_version'];
                }
            }
            break;

        case 'orderby':
            if ($fieldvalue == 999) {
                return '';
            } elseif ($fieldvalue > 10) {
                $retval = COM_createLink(
                    Icon::getHTML('arrow-up', 'uk-icon-justify'),
                    SHOP_ADMIN_URL . '/gateways.php?gwmove=up&id=' . $A['id']
                );
            } else {
                $retval = '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            if ($fieldvalue < $extra['gw_count'] * 10) {
                $retval .= COM_createLink(
                    Icon::getHTML('arrow-down', 'uk-icon-justify'),
                    SHOP_ADMIN_URL . '/gateways.php?gwmove=down&id=' . $A['id']
                );
            } else {
                $retval .= '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            break;

        case 'delete':
            if ($A['enabled'] != 'na') {
                $retval = COM_createLink(
                    Icon::getHTML('delete'),
                    SHOP_ADMIN_URL. '/gateways.php?gwdelete&amp;id=' . $A['id'],
                    array(
                        'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                        'title' => $LANG_SHOP['del_item'],
                        'class' => 'tooltip',
                    )
                );
            }
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Check that the current user is allowed to use this gateway.
     * This limits access to special gateways like 'check' or 'terms'.
     * Actual gateways such as Paypal also won't handle an amount of zero.
     *
     * @param   float   $total  Total order amount
     * @return  boolean     True if access is allowed, False if not
     */
    public function hasAccess($total=0)
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
    protected function returnUrl($cart_id, $token)
    {
        return SHOP_URL . '/index.php?thanks=' . $this->gw_name .
            '/' . $cart_id .
            '/' . $token;
    }


    /**
     * Get the "enabled" flag value.
     *
     * @return  integer     1 if enabled, 0 if not
     */
    protected function isEnabled()
    {
        return $this->enabled ? 1 : 0;
    }


    /**
     * Return a dummy value during AJAX finalizing cart.
     *
     * @param   string  $order_id   Order ID
     * @return  boolean     False, indicating no action was taken
     */
    public function processOrder($order_id)
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
    public function isValid()
    {
        return $this->gw_name != '';
    }


    /**
     * Check if this gateway requires a billing address.
     *
     * @return  boolean     True if a billing address is required
     */
    public function requiresBillto()
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
    public function getWebhookUrl($args=array())
    {
        static $url = NULL;
        if ($url === NULL) {
            $url = Config::get('ipn_url');
            if (empty($url)) {
                // Use the default IPN url
                $url = Config::get('url') . '/hooks/';
            }
            if (substr($url, -1) != '/') {
                $url .= '/';
            }
            $url .= 'webhook.php?_gw=' . $this->gw_name;
        }
        if (!empty($args)) {
            $params = '&' . http_build_query($args);
        } else {
            $params = '';
        }
        return $url . $params;
     }


    /**
     * Get the IPN processor URL.
     *
     * @param   array   $args   Array of optional arguments
     * @return  string      IPN URL
     */
    public function getIpnUrl($args=array())
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
    public function canPayOnline()
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
    public function canPayLater()
    {
        return false;
    }


    /**
     * Create the "pay now" button for orders.
     * For most gateways this is the same as the checkout button.
     *
     * @return  string  HTML for payment button
     */
    public function payOnlineButton($Order)
    {
        global $LANG_SHOP;

        return $this->checkoutButton($Order, $LANG_SHOP['buttons']['pay_now']);
    }


    /**
     * Upload and install the files for a gateway package.
     */
    public static function upload()
    {
        global $_CONF;

        $retval = '';

        if (
            count($_FILES) > 0 &&
            $_FILES['gw_file']['error'] != UPLOAD_ERR_NO_FILE
        ) {
            $upload = new UploadDownload();
            $upload->setMaxFileUploads(1);
            $upload->setMaxFileSize(25165824);
            $upload->setAllowedMimeTypes(array (
                'application/x-gzip'=> array('.gz', '.gzip,tgz'),
                'application/gzip'=> array('.gz', '.gzip,tgz'),
                'application/zip'   => array('.zip'),
                'application/octet-stream' => array(
                    '.gz' ,'.gzip', '.tgz', '.zip', '.tar', '.tar.gz',
                ),
                'application/x-tar' => array('.tar', '.tar.gz', '.gz'),
                'application/x-gzip-compressed' => array('.tar.gz', '.tgz', '.gz'),
            ) );
            $upload->setFieldName('gw_file');
            if (!$upload->setPath($_CONF['path_data'] . 'temp')) {
                SHOP_log("Error setting temp path: " . $upload->printErrors(false));
            }

            $filename = $_FILES['gw_file']['name'];
            $upload->setFileNames($filename);
            $upload->uploadFiles();

            if ($upload->areErrors()) {
                SHOP_log("Errors during upload: " . $upload->printErrors());
                return false;
            }
            $Finalfilename = $_CONF['path_data'] . 'temp/' . $filename;
        } else {
            SHOP_log("No file found to upload");
            return false;
        }

        // decompress into temp directory
        if (function_exists('set_time_limit')) {
            @set_time_limit( 60 );
        }
        $tmp = FileSystem::mkTmpDir();
        if ($tmp === false) {
            SHOP_log("Failed to create temp directory");
            return false;
        }
        $tmp_path = $_CONF['path_data'] . $tmp;
        if (!COM_decompress($Finalfilename, $tmp_path)) {
            SHOP_log("Failed to decompress $Finalfilename into $tmp_path");
            FileSystem::deleteDir($tmp_path);
            return false;
        }
        @unlink($Finalfilename);

        if (!$dh = @opendir($tmp_path)) {
            SHOP_log("Failed to open $tmp_path");
            return false;
        }
        $upl_path = $tmp_path;
        while (false !== ($file = readdir($dh))) {
            if ($file == '..' || $file == '.') {
                continue;
            }
            if (@is_dir($tmp_path . '/' . $file)) {
                $upl_path = $tmp_path . '/' . $file;
                break;
            }
        }
        closedir($dh);

        if (empty($upl_path)) {
            SHOP_log("Could not find upload path under $tmp_path");
            return false;
        }

        // Copy the extracted upload into the Gateways class directory.
        $fs = new FileSystem;
        if (is_file($upl_path . '/gateway.json')) {
            $json = @file_get_contents($upl_path . '/gateway.json');
            if ($json) {
                $json = @json_decode($json, true);
                if ($json) {
                    $gw_name = $json['name'];
                    $gw_path = SHOP_PI_PATH . __DIR__ . '/Gateways/' . $gw_name;
                    $fs->dirCopy($upl_path, $gw_path);
                    // Got the files copied, delete the path.
                    FileSystem::deleteDir($tmp_path);
                    if (@is_dir($gw_path . '/public_html')) {
                        $fs->dirCopy($gw_path . '/public_html', $_CONF['path_html'] . '/shop');
                        FileSystem::deleteDir($gw_path . '/public_html');
                    }
                }
            }
        }
        return empty($fs->getErrors()) ? true : false;
    }


    /**
     * Check if the order can be processed.
     * For most payment methods the order can only be processed after
     * it is paid.
     *
     * @return  boolean     True to process the order, False to hold
     */
    public function okToProcess($Order)
    {
        return $Order->isPaid();
    }



    /**
     * Perform actions required during plugin upgrades.
     *
     * @param   string  $to     Target version
     */
    public static function UpgradeAll($to)
    {
        foreach (self::getAll() as $gw) {
            $gw->doUpgrade($to);
        }
    }


    /**
     * Default gateway upgrade function.
     * Just sets the current version for bundled gateways,
     * ignores others.
     */
    public function doUpgrade()
    {
        global $_TABLES;

        if ($this->_doUpgrade()) {
            $sql = "UPDATE {$_TABLES['shop.gateways']}
                SET version = '{$this->version}'
                WHERE id = '{$this->gw_name}'";
            DB_query($sql);
            return true;
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
        $this->version = static::VERSION;
        return true;
    }


    /**
     * Get the current gateway code version.
     *
     * @return  string      Current version
     */
    public function getCodeVersion()
    {
        return static::VERSION;
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
    public function isBundled()
    {
        return $this->bundled ? 1 : 0;
    }


    /**
     * Stub function to confirm an order.
     * Some gateways will override this.
     *
     * @param   object  $Order  Order object
     * @return  string      Redirect URL
     */
    public function confirmOrder($Order)
    {
        return '';
    }


    /**
     * Check that the gateway is properly configured.
     * Each gateway needs to override this.
     *
     * @return  boolean     True if a valid config is set, False if not
     */
    public function hasValidConfig()
    {
        return true;
    }


    /**
     * Check if this gateway is configured to support affiliate payouts.
     *
     * @return  boolean     True if payouts are supported, otherwise false
     */
    public function supportsPayouts()
    {
        return $this->getConfig('supports_payouts') ? true : false;
    }


    /**
     * Default stub function to handle payouts.
     * Gateways that support this will supply their own function.
     *
     * @param   array   $Payouts    Array of Payout objects
     */
    public function sendPayouts(PayoutHeader $Header, array $Payouts)
    {
        SHOP_LOG("Payouts not implemented for gateway {$this->gw_name}", SHOP_LOG_ERROR);
        foreach ($Payouts as $Payout) {
            $Payout['txn_id'] = 'n/a';
        }
    }

}
