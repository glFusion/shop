<?php
/**
 * Class to handle user account info for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for user info such as addresses.
 * @package shop
 */
class Customer
{
    /** User ID
    * @var integer */
    private $uid;

    /** Addresses stored for this user.
    * @var array */
    private $addresses = array();

    /** Customer's preferred payment gateway ID.
     * @var string */
    private $pref_gw;

    /**  Flag to indicate that this is a new record.
     * @var boolean */
    private $isNew = true;

    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    private $properties = array();

    /** Form action URL when saving profile information.
     * @var string */
    private $formaction = NULL;

    /** Extra form variables added depending on how the profile is being edited.
     * @var array */
    private $extravars = array();

    /** Array to cache user profiles.
     * @var array */
    private static $users = array();


    /**
     * Constructor.
     * Reads in the specified user, if $id is set.  If $id is zero,
     * then the current user is used.
     *
     * @param   integer     $uid    Optional user ID
     */
    public function __construct($uid=0)
    {
        global $_USER;

        $uid = (int)$uid;
        if ($uid < 1) {
            $uid = (int)$_USER['uid'];
        }
        $this->uid = $uid;  // Save the user ID
        $this->ReadUser();  // Load the user's stored addresses
    }


    /**
     * Set a property's value.
     *
     * @param   string  $key    Name of property to set
     * @param   mixed   $value  Value to set
     */
    public function __set($key, $value)
    {
        global $_CONF;

        switch ($key) {
        case 'cart':
            // Check if the cart is being passed as an array or is already
            // serialized
            if (is_string($value)) {
                $value = @unserialize($value);
                if (!$value) $value = array();
            }
            $this->properties[$key] = $value;
            break;
        case 'language':
            if (empty($value)) {
                // Use default if no user language specified.
                $value = $_CONF['language'];
            }
        case 'username':
        case 'fullname':
        case 'email':
            $this->properties[$key] = $value;
            break;
        }
    }


    /**
     * Get a property's value.
     *
     * @param   string  $key    Name of property to retrieve
     * @return  mixed       Value of property, NULL if not set
     */
    public function __get($key)
    {
        if (isset($this->properties[$key])) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    /**
     * Read one user from the database.
     *
     * @param   integer $uid    Optional User ID.  Current user if zero.
     */
    private function ReadUser($uid = 0)
    {
        global $_TABLES;

        $uid = (int)$uid;
        if ($uid == 0) $uid = $this->uid;
        if ($uid < 2) {     // Anon information is not saved
            return;
        }

        $res = DB_query(
            "SELECT u.username, u.fullname, u.email, u.language, ui.*
                FROM {$_TABLES['users']} u
                LEFT JOIN {$_TABLES['shop.userinfo']} ui
                ON u.uid = ui.uid
                WHERE u.uid = $uid"
        );
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $cart = @unserialize($A['cart']);
            if (!$cart) $cart = array();
            $this->cart = $cart;
            $this->username = $A['username'];
            $this->fullname = $A['fullname'];
            $this->email = $A['email'];
            $this->language = $A['language'];
            $this->isNew = false;
            $this->setPrefGW(SHOP_getVar($A, 'pref_gw'));
            $this->addresses = Address::getByUser($uid);
            /*$res = DB_query(
                "SELECT * FROM {$_TABLES['shop.address']} WHERE uid=$uid"
            );
            while ($A = DB_fetchArray($res, false)) {
                $this->addresses[$A['id']] = $A;
            }*/
        } else {
            $this->cart = array();
            $this->isNew = true;
            $this->addresses = array();
            $this->pref_gw = '';
            $this->saveUser();      // create a user record
        }
    }


    /**
     * Get an address.
     *
     * @param   integer $add_id     DB Id of address
     * @return  array               Array of address values
     */
    public function getAddress($add_id)
    {
        global $_TABLES, $_USER;

        $add_id = (int)$add_id;
        if (array_key_exists($add_id, $this->addresses)) {
            return $this->addresses[$add_id];
        } else {
            return array();
        }

        $cache_key = 'shop.address_' . $add_id;
        $A = Cache::get($cache_key);
        if ($A === NULL) {
            $sql = "SELECT * FROM {$_TABLES['shop.address']}
                WHERE id = $add_id";
            $A = DB_fetchArray(DB_query($sql), false);
            if (empty($A)) {
                $A = array();
            }
            Cache::set($cache_key, $A, 'user_' . $A['uid']);
        }
        return $A;
    }


    /**
     * Get the default billing or shipping address.
     * If no default found, return the first address available.
     * If no addresses are defined, return NULL
     *
     * @param   string  $type   Type of address to get
     * @return  mixed   Address array (name, street, city, etc.), or NULL
     */
    public function getDefaultAddress($type='billto')
    {
        if ($type != 'billto') $type = 'shipto';
        foreach ($this->addresses as $Addr) {
            if ($Addr->isDefault($type)) {
                return $Addr;
            }
        }
        if (count($this->addresses) > 0) {
            return reset($this->addresses);
        } else {
            return new Address;
        }
    }


    /**
     * Save the current values to the database.
     * The $A parameter must contain the addr_id value if updating.
     *
     * @param   array   $A      Array of data ($_POST)
     * @param   string  $type   Type of address (billing or shipping)
     * @return  array       Array of DB record ID, -1 for failure and message
     */
    public function saveAddress($A, $type='')
    {
        global $_TABLES, $_USER;

        // Don't save invalid addresses, or anonymous
        if ($_USER['uid'] < 2 || !is_array($A)) {
            return array(-1, '');
        }
        $type = $type == 'billto' ? 'billto' : 'shipto';
        $Address = new Address($A);
        $Address->setUid($this->uid);     // Probably not included in $_POST
        $msg = $Address->isValid();
        if (!empty($msg)) {
            return array(-1, $msg);
        }

        if (isset($A['is_default'])) {
            $Address->setDefault($type);
        }
        $addr_id = $Address->Save();
        return array($addr_id, $msg);
    }


    /**
     * Save the usr information.
     *
     * @return  boolean     True on success, False on failure
     */
    public function saveUser()
    {
        global $_TABLES;

        if ($this->uid < 2) {
            // Act as if saving was successful but do nothing.
            return true;
        }

        $cart = DB_escapeString(@serialize($this->cart));
        $sql = "INSERT INTO {$_TABLES['shop.userinfo']} SET
            uid = {$this->uid},
            pref_gw = '" . DB_escapeString($this->getPrefGW()) . "',
            cart = '$cart'
            ON DUPLICATE KEY UPDATE
            pref_gw = '" . DB_escapeString($this->getPrefGW()) . "',
            cart = '$cart'";
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        Cache::clear('shop.user_' . $this->uid);
        return DB_error() ? false : true;
    }


    /**
     * Delete all information for a user.
     * Called from plugin_user_deleted_shop() when a user account is
     * removed.
     *
     * @param   integer $uid    User ID
     */
    public static function deleteUser($uid)
    {
        global $_TABLES;

        $uid = (int)$uid;
        DB_delete($_TABLES['shop.userinfo'], 'uid', $uid);
        DB_delete($_TABLES['shop.address'], 'uid', $uid);
        Cache::clear('shop.user_' . $uid);
    }


    /**
     * Delete an address by id.
     * Called when the user deletes one of their billing or shipping addresses.
     *
     * @param   integer $addr_id    Record ID of address to delete
     * @return  boolean     Status of change, True if successful
     */
    public function deleteAddress($addr_id)
    {
        $addr_id = (int)$addr_id;
        if ($addr_id < 1 || !array_key_exists($addr_id, $this->addresses)) {
            $status = false;
        } else {
            $status = $this->addresses[$addr_id]->Delete();
            if ($status) {
                unset($this->addresses[$addr_id]);
            }
        }
        return $status;
    }


    /**
     * Validate the address components.
     *
     * @param   array   $A      Array of parameters, e.g. $_POST
     * @return  string      List of invalid items, or empty string for success
     */
    public static function isValidAddress($A)
    {
        global $LANG_SHOP, $_SHOP_CONF;

        $invalid = array();
        $retval = '';

        if (empty($A['name']) && empty($A['company'])) {
            $invalid[] = 'name_or_company';
        }

        if ($_SHOP_CONF['get_street'] == 2 && empty($A['address1']))
            $invalid[] = 'address1';
        if ($_SHOP_CONF['get_city'] == 2 && empty($A['city']))
            $invalid[] = 'city';
        if ($_SHOP_CONF['get_state'] == 2 && empty($A['state']))
            $invalid[] = 'state';
        if ($_SHOP_CONF['get_postal'] == 2 && empty($A['zip']))
            $invalid[] = 'zip';
        if ($_SHOP_CONF['get_country'] == 2 && empty($A['country']))
            $invalid[] = 'country';

        if (!empty($invalid)) {
            foreach ($invalid as $id) {
                $retval .= '<li> ' . $LANG_SHOP[$id] . '</li>' . LB;
            }
            $retval = '<ul>' . $retval . '</ul>';
        }
        return $retval;
    }


    /**
     * Creates the address edit form.
     * Pre-fills values from another address if supplied
     *
     * @param   string  $type   Address type (billing or shipping)
     * @param   array   $A      Optional values to pre-fill form
     * @param   integer $step   Current step number
     * @return  string          HTML for edit form
     */
    public function AddressForm($type='billto', $A=array(), $step)
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_USER;

        if ($type != 'billto') $type = 'shipto';
        if (empty($this->formaction)) $this->formaction = 'save' . $type;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('address', 'address.thtml');

        // Set the address to select by default. Start by using the one
        // already stored in the cart, if any.
        if (empty(array_filter($A))) {
            // No address specified, get the customer's default address
            $Def = $this->getDefaultAddress($type);
        } else {
            // Cart has an address, retrieve it to use as the default
            $Def = new Address($A);
        }
        $addr_id = $Def->getID();
        $count = 0;
        $def_addr = 0;

        $T->set_block('address', 'SavedAddress', 'sAddr');
        foreach($this->addresses as $ad_id => $address) {
            $count++;
            if ($address->isDefault($type)) {
                $is_default = true;
                $def_addr = $ad_id;
            } else {
                $is_default = false;
            }

            // If this is the default address, or this is the already-stored
            // address, then check it's radio button.
            if (
                (empty($addr_id) && $is_default) ||
                $addr_id == $ad_id
            ) {
                $ad_checked = 'checked="checked"';
                $addr_id = $ad_id;
            } else {
                $ad_checked = '';
            }

            $T->set_var(array(
                'id'        => $address->getID(),
                'ad_name'   => $address->getName(),
                'ad_company' => $address->getCompany(),
                'ad_addr_1' => $address->getAddress1(),
                'ad_addr_2' => $address->getAddress2(),
                'ad_city'   => $address->getCity(),
                'ad_state'  => $address->getState(),
                'ad_country' => $address->getCountry(),
                'ad_zip'    => $address->getPostal(),
                'ad_phone'  => $address->getPhone(),
                'ad_checked' => $ad_checked,
                'del_icon'  => Icon::getHTML(
                    'delete', 'tooltip',
                    array(
                        'title' => $LANG_SHOP['delete'],
                        'onclick' => 'removeAddress(' . $address->getID() . ');',
                    )
                ),
            ) );
            $T->parse('sAddr', 'SavedAddress', true);
        }

        $hiddenvars = '';
        foreach ($this->extravars as $var) {
            $hiddenvars .= $var . LB;
        }

        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            'billship'      => $type,
            'order_id'      => $this->order_id,
            'sel_addr_text' => $LANG_SHOP['sel_' . $type . '_addr'],
            'addr_type'     => $LANG_SHOP[$type . '_info'],
            'allow_default' => $this->uid > 1 ? 'true' : '',
            'have_addresses' => $count > 0 ? 'true' : '',
            'addr_id'   => empty($addr_id) ? '' : $addr_id,
            'name'      => isset($A['name']) ? $A['name'] : '',
            'company'   => isset($A['company']) ? $A['company'] : '',
            'address1'  => isset($A['address1']) ? $A['address1'] : '',
            'address2'  => isset($A['address2']) ? $A['address2'] : '',
            'city'      => isset($A['city']) ? $A['city'] : '',
            'state'     => isset($A['state']) ? $A['state'] : '',
            'zip'       => isset($A['zip']) ? $A['zip'] : '',
            'country'   => isset($A['country']) ? $A['country'] : '',
            'def_checked' => $def_addr > 0 && $def_addr == $addr_id ?
                                'checked="checked"' : '',

            'req_street'    => $_SHOP_CONF['get_street'] == 2 ? 'true' : '',
            'req_city'      => $_SHOP_CONF['get_city'] == 2 ? 'true' : '',
            'req_state'     => $_SHOP_CONF['get_state'] == 2 ? 'true' : '',
            'req_country'   => $_SHOP_CONF['get_country'] == 2 ? 'true' : '',
            'req_postal'    => $_SHOP_CONF['get_postal'] == 2 ? 'true' : '',
            'req_phone'     => $_SHOP_CONF['get_phone'] == 2 ? 'true' : '',
            'get_street'    => $_SHOP_CONF['get_street'] > 0 ? 'true' : '',
            'get_city'      => $_SHOP_CONF['get_city'] > 0 ? 'true' : '',
            'get_state'     => $_SHOP_CONF['get_state'] > 0 ? 'true' : '',
            'get_country'   => $_SHOP_CONF['get_country'] > 0 ? 'true' : '',
            'get_postal'    => $_SHOP_CONF['get_postal'] > 0 ? 'true' : '',
            'get_phone'     => $_SHOP_CONF['get_phone'] > 0 ? 'true' : '',

            'hiddenvars'    => $hiddenvars,
            'action'        => $this->formaction,
            'next_step'     => (int)$step + 1,
        ) );

        $T->parse('output','address');
        return $T->finish($T->get_var('output'));

    }


    /**
     * Provide a public method to set the private formaction variable
     *
     * @param   string  $action     Value to set as form action
     */
    public function setFormAction($action)
    {
        $this->formaction = $action;
    }


    /**
     * Add a hidden form value.
     *
     * @param   string  $name   Name of form variable
     * @param   string  $value  Value of variable
     */
    public function addFormVar($name, $value)
    {
        $this->extravars[] = '<input type="hidden" name="' . $name .
                '" value="' . $value . '" />';
    }


    /**
     * Get the instance of a Customer object for the specified user.
     *
     * @param   integer $uid    User ID
     * @return  object          Customer object for the user
     */
    public static function getInstance($uid = 0)
    {
        global $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        $uid = (int)$uid;
        // If not already set, read the user info from the database
        if (!isset(self::$users[$uid])) {
            $key = 'shop.user_' . $uid;  // Both the key and cache tag
            self::$users[$uid] = Cache::get($key);
            if (!self::$users[$uid]) {
                self::$users[$uid] = new self($uid);
                Cache::set($key, self::$users[$uid], $key);
            }
        }
        return self::$users[$uid];
    }


    /**
     * Get the base language name from the full string contained in the user record.
     * For example, "spanish_columbia_utf-8" returns "spanish" if $fullname is
     * false, or the full string if $fullname is true.
     * Supplies the language name for notification template selection and
     * for loading a $LANG_SHOP array.
     *
     * @param   boolean $fullname   True to return full name of language
     * @return  string  Language name for the buyer.
     */
    public function getLanguage($fullname = false)
    {
        if (!$fullname) {
            $lang = explode('_', $this->language);
            return $lang[0];
        } else {
            return $this->language;
        }
    }


    /**
     * Get an array of field names used by this class.
     * May be used when constructing forms and SQL statements.
     *
     * @return  array   Array of field names
     */
    public static function Fields()
    {
        return array(
            'name',
            'company',
            'address1',
            'address2',
            'city',
            'state',
            'country',
            'zip',
        );
    }


    /**
     * Set the customer's preferred payment gateway.
     *
     * @param   string  $gw     ID of preferred gateway
     * @return  object  $this
     */
    public function setPrefGW($gw)
    {
        $this->pref_gw = $gw;
        return $this;
    }


    /**
     * Get the customer's preferred payment gateway.
     *
     * @return  string      ID of preferred gateway
     */
    public function getPrefGW()
    {
        return $this->pref_gw;
    }


}   // class Customer

?>
