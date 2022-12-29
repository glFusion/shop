<?php
/**
 * Class to handle user account info for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.1
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use Shop\Models\ReferralTag;
use Shop\Models\DataArray;
use Shop\Models\ProductType;


/**
 * Class for user info such as addresses.
 * @package shop
 */
class Customer
{
    /** User ID.
    * @var integer */
    private $uid = 0;

    /** User Name.
     * @var string */
    private $username = '';

    /** Full name.
     * @var string */
    private $fullname = '';

    /** Email address.
     * @var string */
    private $email = '';

    /** Language.
     * @var string */
    private $language = '';

    /** Addresses stored for this user.
    * @var array */
    private $addresses = array();

    /** Customer's preferred payment gateway ID.
     * @var string */
    private $pref_gw;

    /** Referrer ID.
     * @var string */
    private $affiliate_id = '';

    /** Affiliate payment method.
     * @var string */
    private $aff_pmt_method = '_coupon';

    /** Customer IDs created by payment gateways.
     * @var array */
    private $gw_ids = NULL;

    /** Shopping cart information.
     * @var array */
    private $cart = array();

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

        if ($uid < 1) {
            $uid = $_USER['uid'];
        }
        $this->uid = (int)$uid;  // Save the user ID
        $this->gw_ids = new DataArray;
        $this->ReadUser();  // Load the user's stored addresses
    }


    /**
     * Read one user from the database.
     *
     * @param   integer $uid    Optional User ID.  Current user if zero.
     */
    private function ReadUser($uid = 0)
    {
        global $_TABLES, $_CONF;

        $uid = (int)$uid;
        if ($uid == 0) $uid = $this->uid;
        if ($uid < 2) {     // Anon information is not saved
            return;
        }
        try {
            $A = Database::getInstance()->conn->executeQuery(
                "SELECT u.username, u.fullname, u.email, u.language,
                ui.*, UNIX_TIMESTAMP(ui.created) AS created_ts
                FROM {$_TABLES['users']} u
                LEFT JOIN {$_TABLES['shop.userinfo']} ui ON u.uid = ui.uid
                WHERE u.uid = ?",
                array($uid),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A = false;
        }
        if (is_array($A)) {
            $A = new DataArray($A);
            $this->username = $A['username'];
            $this->fullname = $A['fullname'];
            $this->email = $A['email'];
            $this->language = str_replace('_' . COM_getCharset(), '', $A['language']);
            $this->setCart($A['cart']);
            $this->setPrefGW($A->getString('pref_gw'));
            $this->addresses = Address::getByUser($uid);
            $this->gw_ids = new DataArray($this->getCustomerIds($uid));
            $this->setCreationDate($A['created_ts']);
            if ($A['uid'] > 0) {
                // The returned uid value will be null if the user record was
                // found but there is no customer record created yet.
                $this->affiliate_id = $A['affiliate_id'];
                $this->aff_pmt_method = $A['aff_pmt_method'];
            } else {
                // Create a record
                $this->saveUser();
            }
        } else {
            // Not even a valid user ID
            $this->cart = array();
            $this->addresses = array();
            $this->pref_gw = '';
        }
    }


    /**
     * Get the gateway-specific customer IDs for this customer.
     * This is to be able to check if the customer exists with the gateway
     * provider and to search transactions.
     *
     * @return  array   Array of gateway-specific customer IDs
     */
    public function getCustomerIds()
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['shop.customerXgateway']}
            WHERE uid = {$this->uid}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['gw_id']] = $A['cust_id'];
        }
        return $retval;
    }


    /**
     * Set the customer reference ID for a payment gateway.
     * This stores the customer ID returned from the payment gateway
     * when a customer record is created during invoicing.
     *
     * @param   string  $gw_id      ID of gateway
     * @param   string  $cust_id    Gateway's reference ID
     * @return  object  $this
     */
    public function setGatewayId($gw_id, $cust_id)
    {
        global $_TABLES;

        $gw_id = DB_escapeString($gw_id);
        $cust_id = DB_escapeString($cust_id);
        $sql = "INSERT INTO {$_TABLES['shop.customerXgateway']} SET
            uid = {$this->uid},
            gw_id = '$gw_id',
            cust_id = '$cust_id'
            ON DUPLICATE KEY UPDATE
            cust_id = '$cust_id'";
        //echo $sql;die;
        DB_query($sql);
        return $this;
    }


    /**
     * Get the customer reference ID for a payment gateway.
     *
     * @param   string  $gw_id      ID of gateway
     * @return  string      Customer ID, NULL if not set
     */
    public function getGatewayId(string $gw_id) : string
    {
        return $this->gw_ids->getString($gw_id);
    }


    /**
     * Get the customer's full name from the glFusion profile.
     *
     * @return  string      Full name
     */
    public function getFullname()
    {
        return $this->fullname;
    }


    /**
     * Set the cart array.
     *
     * @param   string|array    Array, or serialized string
     * @return  object  $this
     */
    public function setCart($value)
    {
        // Check if the cart is being passed as an array or is already
        // serialized
        if (is_string($value)) {
            $value = @unserialize($value);
            if (!$value) {
                // deserialization failed
                $value = array();
            }
        }
        $this->cart = $value;
        return $this;
    }


    /**
     * Get the cart contents.
     *
     * @return  array       Cart information
     */
    public function getCart()
    {
        return $this->cart;
    }


    /**
     * Get the glFusion user ID for this customer
     *
     * @return  integer     User ID
     */
    public function getUid()
    {
        return (int)$this->uid;
    }


    /**
     * Get an address.
     *
     * @param   integer $add_id     DB Id of address
     * @return  array               Array of address values
     */
    public function getAddress(int $add_id) : Address
    {
        global $_TABLES;

        $add_id = (int)$add_id;
        if (array_key_exists($add_id, $this->addresses)) {
            return $this->addresses[$add_id];
        } elseif (!empty($this->addresses)) {
            return reset($this->addresses);
        } else {
            return new self;
        }
    }


    /**
     * Get all the customer addresses. Used with privacy export.
     *
     * @return  array       Array of addresses
     */
    public function getAddresses() : array
    {
        return $this->addresses;
    }


    /**
     * Get the default billing or shipping address.
     * If no default found, return the first address available.
     * If no addresses are defined, return NULL
     *
     * @param   string  $type   Type of address to get
     * @return  mixed   Address array (name, street, city, etc.), or NULL
     */
    public function getDefaultAddress($type='billto') : Address
    {
        if ($this->uid < 2) {
            // Anonymous has no saved address
            return Address::fromGeoLocation();
        }

        if ($type != 'billto') $type = 'shipto';
        foreach ($this->addresses as $Addr) {
            if ($Addr->isDefault($type)) {
                return $Addr;
            }
        }
        if (count($this->addresses) > 0) {
            return reset($this->addresses);
        } else {
            $retval = new Address;
            $retval->setName($this->fullname);
            return $retval;
        }
    }


    /**
     * Get the customer's email address.
     *
     * @return  string      Email address
     */
    public function getEmail()
    {
        return $this->email;
    }


    /**
     * Get the referral token.
     *
     * @return  string      Referral Token value
     */
    public function getReferralToken()
    {
        return ReferralTag::get();
    }


    /**
     * Get the customer user ID.
     * Used to determine if a customer record was actually found.
     *
     * @return  integer     Record ID from userinfo table
     */
    public function countOrders()
    {
        return Order::countActiveByUser($this->uid);
    }


    /**
     * Get the customer's affiliate ID.
     *
     * @return  string      Affiliate ID, NULL if affiliate sales disabled
     */
    public function getAffiliateId()
    {
        if (Config::get('aff_enabled')) {
            return $this->affiliate_id;
        } else {
            return NULL;
        }
    }


    /**
     * Create an affiliate ID for this customer.
     *
     * @return  object  $this
     */
    public function createAffiliateId()
    {
        if (
            $this->affiliate_id == 'pending' ||
            $this->affiliate_id == ''
        ) {
            $this->affiliate_id = ReferralTag::create();
        }
        return $this;
    }


    public function withAffiliateId($aff_id)
    {
        $this->affiliate_id = $aff_id;
        return $this;
    }


    /**
     * Set the creation date object from a unix timestamp.
     *
     * @param   integer $ts     Timestamp, NULL for now.
     * @return  object  $this
     */
    public function setCreationDate($ts=NULL)
    {
        global $_CONF;

        if ($ts === NULL) {
            $ts = time();
        } else {
            $ts = (int)$ts;
        }
        $this->created = new \Date($ts, $_CONF['timezone']);
        return $this;
    }


    /**
     * Get the creation date for a customer.
     *
     * @return  object      Date object
     */
    public function getCreationDate()
    {
        return $this->created;
    }


    /**
     * Delete the affiliate ID for this customer.
     *
     * @return  object  $this
     */
    public function resetAffiliateId()
    {
        $this->affiliate_id = '';
        return $this;
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
        global $_TABLES;

        // Don't save invalid addresses, or anonymous
        if ($this->uid < 2 || !is_array($A)) {
            return array(-1, '');
        }
        $type = $type == 'billto' ? 'billto' : 'shipto';
        $Address = new Address($A);
        $Address->setUid($this->uid);     // Probably not included in $_POST
        $msg = $Address->isValid(ProductType::PHYSICAL);
        if (!empty($msg)) {
            return array(-1, $msg);
        }

        /*if (isset($A['is_default'])) {
            $Address->setDefault($type);
        }*/
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
        global $_TABLES, $_CONF;

        if ($this->uid < 2) {
            // Act as if saving was successful but do nothing.
            return true;
        }

        // Create a new referrer token if not present.
        // If auto_enroll is set then check the order count unless all
        // uses are auto-enrolled.
        if (
            $this->affiliate_id == '' &&
            Config::get('aff_auto_enroll') && (
                Config::get('aff_eligible') == 'allusers' ||
                $this->countOrders() > 0
            )
        ) {
            $this->affiliate_id = ReferralTag::create();
        }

        $cart = DB_escapeString(@serialize($this->cart));
        $sql = "INSERT INTO {$_TABLES['shop.userinfo']} SET
            uid = {$this->uid},
            pref_gw = '" . DB_escapeString($this->getPrefGW()) . "',
            cart = '$cart',
            affiliate_id = '" . DB_escapeString($this->affiliate_id) . "',
            aff_pmt_method = '" . DB_escapeString($this->aff_pmt_method) . "'
            ON DUPLICATE KEY UPDATE
            pref_gw = '" . DB_escapeString($this->getPrefGW()) . "',
            cart = '$cart',
            affiliate_id = '" . DB_escapeString($this->affiliate_id) . "',
            aff_pmt_method = '" . DB_escapeString($this->aff_pmt_method) . "'";
        Log::debug($sql);
        DB_query($sql);
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
    }


    /**
     * Change a customer's user ID. Called from plugin_user_merge function.
     *
     * @param   integer $old_uid    Original user ID
     * @param   integer $new_uid    New user ID
     */
    public static function changeUid(int $old_uid, int $new_uid) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeQuery(
                "UPDATE {$_TABLES['shop.address']} SET uid = ? WHERE uid = ?",
                array($new_uid, $old_uid),
                array(Database::INTEGER, Database::INTEGER)
            );
        } catch (\Exception $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return;
        }

        try {
            $db->conn->executeQuery(
                "UPDATE {$_TABLES['shop.userinfo']} SET uid = ? WHERE uid = ?",
                array($new_uid, $old_uid),
                array(Database::INTEGER, Database::INTEGER)
            );
        } catch (\Exception $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return;
        }
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
        global $LANG_SHOP;

        $invalid = array();
        $retval = '';

        if (empty($A['name']) && empty($A['company'])) {
            $invalid[] = 'name_or_company';
        }
        foreach (array('address1', 'city', 'state', 'zip', 'country') as $key) {
            if (!isset($A[$key]) || empty($A[$key])) {
                $invalid[] = $key;
            }
        }
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
    public function AddressForm($type='billto', $A=array(), $step=1)
    {
        global $_TABLES, $_CONF, $LANG_SHOP;
        if ($type != 'billto') $type = 'shipto';
        if (empty($this->formaction)) $this->formaction = 'save' . $type;

        $T = new Template;
        $T->set_file('address', 'address.thtml');

        // Set the address to select by default. Start by using the one
        // already stored in the cart, if any.
        $have_address = false;
        if (empty(array_filter($A))) {
            // No address specified, get the customer's default address
            $Def = $this->getDefaultAddress($type);
        } else {
            // Cart has an address, retrieve it to use as the default
            $Def = new Address($A);
            if (isset($A['id']) && $A['id'] > 0) {
                $Def->setID($A['id']);
            }
            $have_address = true;
        }
        $A = new DataArray($A);
        $addr_id = $Def->getID();
        if ($addr_id > 0) {
            $have_address = true;
        }
        if (!$have_address) {
            $loc = GeoLocator::getProvider()->geoLocate();
            if ($loc['ip'] != '') {
                $A['country'] = $loc['country_code'];
                $A['state'] = $loc['state_code'];
                $A['city'] = $loc['city_name'];
            }
        }

        $count = 0;
        $def_addr = 0;
        $selAddress = new Address;     // start with an empty address selected

        $T->set_block('address', 'SavedAddress', 'sAddr');
        foreach($this->addresses as $ad_id => $address) {
            $count++;
            if ($address->isDefault($type)) {
                $is_default = true;
                $def_addr = $ad_id;
                $defAddress = $address;
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
                $selAddress = $address;
            } else {
                $ad_checked = '';
            }
            $T->set_var(array(
                'id'        => $address->getID(),
                'ad_type'   => $type,
                'ad_full'   => $address->toText('all', ', '),
                'ad_name'   => $address->getName(),
                'ad_company' => $address->getCompany(),
                'ad_addr_1' => $address->getAddress1(),
                'ad_addr_2' => $address->getAddress2(),
                'ad_city'   => $address->getCity(),
                'ad_state'  => $address->getState(),
                'ad_country' => $address->getCountry(),
                'ad_zip'    => $address->getPostal(),
                'ad_phone'  => $address->getPhone(),
                'ad_billto_def' => $address->isDefaultBillto(),
                'ad_shipto_def' => $address->isDefaultShipto(),
                'ad_checked' => $ad_checked,
                'del_icon'  => FieldList::delete(array(
                    'title' => $LANG_SHOP['delete'],
                    'onclick' => 'removeAddress(' . $address->getID() . ');',
                ) ),
            ) );
            $T->parse('sAddr', 'SavedAddress', true);
        }

        $hiddenvars = '';
        foreach ($this->extravars as $var) {
            $hiddenvars .= $var . LB;
        }

        $country = $selAddress->getCountry();
        if ($country == '') {
            // To show the state selection if applicable
            $Company = new Company;
            $country = $Company->getCountry();
        }
        if ($country == '') {
            // Still empty (shop address not configured)? Use a default value
            $country = 'US';
        }
        // Get the state options into a variable so the length of the options
        // can be set in a template var, to set the visibility.
        $state_options = State::optionList(
            $A->getString('country', $country),
            $A->getString('state', $selAddress->getState())
        );
        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            'billship'      => $type,
            //'order_id'      => $this->order_id,
            'sel_addr_text' => $LANG_SHOP['sel_' . $type . '_addr'],
            'addr_type'     => $LANG_SHOP[$type . '_info'],
            'ad_type'       => $type,
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
            'hiddenvars'    => $hiddenvars,
            'action'        => $this->formaction,
            'next_step'     => (int)$step + 1,
            'country_options' => Country::optionList(
                $A->getString('country', $country)
            ),
            'state_options' => $state_options,
            'state_sel_vis' => strlen($state_options) > 0 ? '' : 'none',
            'allow_default' => $this->uid > 1,
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
    public static function getInstance(?int $uid = NULL) : self
    {
        global $_USER;

        if (empty($uid)) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        // If not already set, read the user info from the database
        if (!isset(self::$users[$uid])) {
            self::$users[$uid] = new self($uid);
        }
        return self::$users[$uid];
    }


    /**
     * Get a customer record by the referrer token.
     * Used to validate a supplied referral.
     *
     * @param   string  $affiliate_id   Affiliate ID
     * @return  object|boolean  Customer object, false if token not valid
     */
    public static function findByAffiliate($affiliate_id)
    {
        global $_TABLES, $_CONF;

        $retval = false;
        if (!empty($affiliate_id)) {
            $where = "affiliate_id ='" . DB_escapeString($affiliate_id) . "'";
            $uid = (int)DB_getItem($_TABLES['shop.userinfo'], 'uid', $where);
            if ($uid > 1) {
                $retval = new self($uid);
            }
        }
        return $retval;
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
        return $this->language;
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
        if ($gw != 'free') {
            $this->pref_gw = $gw;
        }
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


    /**
     * Get the customer's preferred payout method for affiliate referrals.
     * Only Coupons are currently supported.
     *
     * @return  string      Payout method (Gateway name)
     */
    public function getAffPayoutMethod()
    {
        return '_coupon';
    }


    /**
     * Get this affiliate's link to be shared.
     *
     * @param   mixed   $item_id    Product ID (not SKU) to add to the link
     * @return  string      URL to product with affiliate ID parameter.
     */
    public function getAffiliateLink($item_id='')
    {
        if (Config::get('aff_enabled') && !empty($this->affiliate_id)) {
            if ($item_id != '') {
                // Direct to the product detail page
                $url = Config::get('url') . '/detail.php?' .
                    Config::get('aff_key') . '=' . $this->affiliate_id .
                    '&item_id=' . $item_id;
            } else {
                // Go to the catalog homepage
                $url = Config::get('url') . '/index.php?' .
                    Config::get('aff_key') . '=' . $this->affiliate_id;
            }
            return $url;
        } else {
            return '';
        }
    }


    /**
     * Parse a fullname string into component parts.
     *
     * @param   string  $name       Full name to parse
     * @return  mixed       Array of parts, or specified format
     */
    public static function parseName(string $name, ?string $format=NULL)
    {
        $args = array(1 => $name);
        if ($format !== NULL) {
            $args[2] = $format;
        }
        $retval = PLG_callFunctionForOnePlugin(
            'plugin_parseName_lglib',
            $args,
        );
        if (empty($retval)) {
            $p = explode(' ', $name);
            $parts = array();
            $parts['fname'] = $p[0];
            $parts['lname'] = isset($p[1]) ? $p[1] : '';
            if ($format == 'LCF') {
                if (!empty($parts['lname'])) {
                    $retval = $parts['lname'] . ', ' . $parts['fname'];
                } else {
                    $retval = $parts['fname'];
                }
            } else {
                $retval = $parts;
            }
        }
        return $retval;
    }

}
