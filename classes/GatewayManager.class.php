<?php
/**
 * Payment gateway manager class.
 * Handles admin lists, uploading and installing gateways.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Config;
use Shop\Models\ButtonKey;;
use Shop\Models\GatewayInfo;
use Shop\Cache;
use glFusion\FileSystem;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Base class for Shop payment gateway.
 * Provides common variables and methods required by payment gateway classes.
 * @package shop
 */
class GatewayManager
{
   /** Table name, used by DBO class.
     * @var string */
    protected static $TABLE = 'shop.gateways';

    /** Array to hold cached gateways.
     * @var array */
    private static $gateways = array();

    /** List of bundled gateways.
     * @var array */
    public static $_bundledGateways = array(
        'paypal', 'ppcheckout', 'test', '_coupon',
        'check', 'terms', '_internal', 'free',
    );


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
        //Log::write('shop_system', Log::DEBUG, $sql);
        DB_query($sql);
    }


    /**
     * Save the gateway config variables.
     *
     * @uses    ReOrder()
     * @param   array   $A      Array of config items, e.g. $_POST
     * @return  boolean         True if saved successfully, False if not
     */
    public function saveConfig(?array $A = NULL) : bool
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->enabled = isset($A['enabled']) ? 1 : 0;
            $this->orderby = (int)$A['orderby'];
            $this->grp_access = SHOP_getVar($A, 'grp_access', 'integer', 2);
            $services = SHOP_getVar($A, 'service', 'array');
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
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
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
    protected static function do_toggle($oldvalue, $varname, $id)
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

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['shop.buttons'],
                array('gw_name' => $this->gw_name),
                array(Database::STRING)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Install a new gateway into the gateways table.
     * The gateway has to be instantiated, then called as `$newGateway->Install()`.
     * The config values set by the gateways constructor will be saved.
     *
     * @return  boolean     True on success, False on failure
     */
    public function XXInstall() : bool
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
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }
    }


    /**
     * Remove the current gateway.
     * This removes all of the configuration for the gateway, but not files.
     */
    public static function XRemove(string $gw_name) : void
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
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Get the checkboxes for the button types in the configuration form.
     *
     * @return  string      HTML for checkboxes
     */
    protected function getServiceCheckboxes()
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
    public function ProductButton(Product $P, ?float $price=NULL) : string
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
                if (isset($this->config[$env][$name])) {
                    $val = $this->config[$env][$name];
                } else {
                    $val = '';
                }
                $field = FieldList::text(array(
                    'name' => $fld_name,
                    'value' => $val,
                ) );
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
     * Get all gateways into a static array.
     *
     * @param   boolean $enabled    True to get only enabled gateways
     * @return  array       Array of gateways, enabled or all
     */
    public static function getAll($enabled = false)
    {
        global $_TABLES;

        $gateways = array();
        $key = $enabled ? 1 : 0;
        $gateways[$key] = array();
        $cache_key = 'gateways_' . $key;
        $tmp = Cache::get($cache_key);
        if ($tmp === NULL) {
            $tmp = array();
            // Load the gateways
            $db = Database::getInstance();
            $sql = "SELECT * FROM {$_TABLES['shop.gateways']}";
            // If not loading all gateways, get just then enabled ones
            if ($enabled) $sql .= ' WHERE enabled=1';
            $sql .= ' ORDER BY orderby';
            try {
                $data = $db->conn->executeQuery($sql)->fetchAllAssociative();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $data = false;
            }
            if (is_array($data)) {
                foreach ($data as $A) {
                    $tmp[] = $A;
                }
            }
            Cache::set($cache_key, $tmp, self::$TABLE);
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
                    $dir !== '.' && $dir !== '..' &&
                    $dir[0] != '_' &&       // skip internal utility gateways
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
    }


    /**
     * Get all the installed gateways for the admin list.
     *
     * @param   array   $data_arr   Reference to data array
     */
    private static function getInstalled(&$data_arr) : void
    {
        global $_TABLES;

        $sql = "SELECT *, g.grp_name
            FROM {$_TABLES['shop.gateways']} gw
            LEFT JOIN {$_TABLES['groups']} g
                ON g.grp_id = gw.grp_access
            ORDER BY orderby ASC";

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery($sql)->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $msg);
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $gw = Gateway::create($A['id']);
                if ($gw) {
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
        }
    }


    /**
     * Payment Gateway Admin View.
     *
     * @return  string      HTML for the gateway listing
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN,
            $LANG32;

        $data_arr = array();
        self::getInstalled($data_arr);
        self::getUninstalled($data_arr);
        // Future - check versions of pluggable gateways
        self::_checkAvailableVersions($data_arr);

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
            array (
                'text'  => $LANG32[84],
                'field' => 'bundled',
                'align' => 'center',
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
        $T = new Template('admin');
        $T->set_file('form', 'gw_adminlist_form.thtml');
        $T->set_var('lang_select_file', $LANG_SHOP['select_file']);
        $T->set_var('lang_upload', $LANG_SHOP['upload']);
        $T->parse('output', 'form');
        $display .= $T->finish($T->get_var('output'));
        $display .= ADMIN_listArray(
            Config::PI_NAME . '_gwlist',
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
        global $_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            if ($A['enabled'] !== 'na') {
                $retval .= FieldList::edit(array(
                    'url' => SHOP_ADMIN_URL . "/gateways.php?gwedit&amp;gw_id={$A['id']}",
                ) );
            }
            break;

        case 'enabled':
            if ($fieldvalue == 'na') {
                return FieldList::add(array(
                    'url' => SHOP_ADMIN_URL. '/gateways.php?gwinstall&gwname=' . urlencode($A['id']),
                    array(
                        'title' => $LANG_SHOP['ck_to_install'],
                    )
                ) );
            } elseif ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
                $tip = $LANG_SHOP['ck_to_disable'];
            } else {
                $switch = '';
                $enabled = 0;
                $tip = $LANG_SHOP['ck_to_enable'];
            }
            $retval .= FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['id']}",
                'checked' => $fieldvalue == 1,
                'title' => $tip,
                'onclick' => "SHOP_toggle(this,'{$A['id']}','{$fieldname}','gateway');",
            ) );
            break;

        case 'version':
            // Show the upgrade link if needed. Only display this for
            // installed gateways.
            if (isset($A['version'])) {
                $retval = $fieldvalue;
                if (!COM_checkVersion($fieldvalue, $A['code_version'])) {
                    $retval .= ' ' . FieldList::update(array(
                        'url' => Config::get('admin_url') . '/gateways.php?gwupgrade=' . $A['id'],
                    ) );
                    $retval .= $A['code_version'];
                }
                if (!COM_checkVersion($A['code_version'], $A['available'])) {
                    $retval .= ' ' . FieldList::buttonLink(array(
                        'text' => $A['available'],
                        'url' => $A['upgrade_url'],
                        'size' => 'mini',
                        'style' => 'success',
                        'attr' => array(
                            'target' => '_blank',
                        )
                    ) );
                }
            }
            break;

        case 'orderby':
            $fieldvalue = (int)$fieldvalue;
            if ($fieldvalue == 999) {
                return '';
            } elseif ($fieldvalue > 10) {
                $retval = FieldList::up(array(
                    'url' => SHOP_ADMIN_URL . '/gateways.php?gwmove=up&id=' . $A['id'],
                ) );
            } else {
                $retval = FieldList::space();
            }
            if ($fieldvalue < $extra['gw_count'] * 10) {
                $retval .= FieldList::down(array(
                    'url' => SHOP_ADMIN_URL . '/gateways.php?gwmove=down&id=' . $A['id'],
                )) ;
            } else {
                $retval .= FieldList::space();
            }
            break;

        case 'bundled':
            if (in_array($A['id'], self::$_bundledGateways)) {
                $retval .= FieldList::checkmark(array(
                    'active' => $A['enabled'] != 'na',
                ) );
            }
            break;

        case 'delete':
            if ($A['enabled'] != 'na' && $A['id'][0] != '_') {
                $retval = FieldList::delete(array(
                    'delete_url' => SHOP_ADMIN_URL. '/gateways.php?gwdelete&amp;id=' . $A['id'],
                    'attr' => array(
                        'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                        'title' => $LANG_SHOP['del_item'],
                        'class' => 'tooltip',
                    ),
                ) );
            }
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Upload and install the files for a gateway package.
     *
     * @return  boolean     True on success, False on error
     */
    public static function upload() : bool
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
                Log::write('shop_system', Log::ERROR, "Error setting temp path: " . $upload->printErrors(false));
            }

            $filename = $_FILES['gw_file']['name'];
            $upload->setFileNames($filename);
            $upload->uploadFiles();

            if ($upload->areErrors()) {
                Log::write('shop_system', Log::ERROR, "Errors during upload: " . $upload->printErrors());
                return false;
            }
            $Finalfilename = $_CONF['path_data'] . 'temp/' . $filename;
        } else {
            Log::write('shop_system', Log::ERROR, "No file found to upload");
            return false;
        }

        // decompress into temp directory
        if (function_exists('set_time_limit')) {
            @set_time_limit( 60 );
        }
        $tmp = FileSystem::mkTmpDir();
        if ($tmp === false) {
            Log::write('shop_system', Log::ERROR, "Failed to create temp directory");
            return false;
        }
        $tmp_path = $_CONF['path_data'] . $tmp;
        if (!COM_decompress($Finalfilename, $tmp_path)) {
            Log::write('shop_system', Log::ERROR, "Failed to decompress $Finalfilename into $tmp_path");
            FileSystem::deleteDir($tmp_path);
            return false;
        }
        @unlink($Finalfilename);

        if (!$dh = @opendir($tmp_path)) {
            Log::write('shop_system', Log::ERROR, "Failed to open $tmp_path");
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
            Log::write('shop_system', Log::ERROR, "Could not find upload path under $tmp_path");
            return false;
        }

        // Copy the extracted upload into the Gateways class directory.
        $fs = new FileSystem;
        $gw_name = '';
        if (is_file($upl_path . '/gateway.json')) {
            $json = @file_get_contents($upl_path . '/gateway.json');
            if ($json) {
                $json = @json_decode($json, true);
                if ($json) {
                    $gw_name = $json['name'];
                    $gw_path = Config::get('path') . 'classes/Gateways/' . $gw_name;
                    $status = $fs->dirCopy($upl_path, $gw_path);
                    if ($status) {
                        // Got the files copied, delete the uploaded files.
                        FileSystem::deleteDir($tmp_path);
                        if (@is_dir($gw_path . '/public_html')) {
                            // Copy any public_html files, like custom webhook handles
                            $fs->dirCopy($gw_path . '/public_html', $_CONF['path_html'] . Config::PI_NAME);
                            FileSystem::deleteDir($gw_path . '/public_html');
                        }
                    }
                }
            }
        }

        // If there are any error messages, log them and return false.
        // Otherwise return true.
        if (empty($fs->getErrors())) {
            if (!empty($gw_name)) {
                $gw = Gateway::getInstance($gw_name);
                $gw->doUpgrade();
            }
            return true;
        } else {
            foreach ($fs->getErrors() as $msg) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $msg);
            }
            return false;
        }
    }


    /**
     * Upgrade all bundled gateways. Called during plugin update.
     *
     * @param   string  $to     New version
     */
    public static function upgradeAll() : void
    {
        foreach (self::getAll() as $gw) {
            if ($gw->isBundled()) {
                $gw->doUpgrade();
            }
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
    private static function _checkAvailableVersions(&$data_arr) : void
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
    private static function _checkAvailableVersion(string $gwname) : array
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

}
