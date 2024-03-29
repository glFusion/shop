<?php
/**
 * Class to handle coupon operations.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 *
 */
namespace Shop\Products;
use glFusion\Database\Database;
use Shop\Payment;   // to record application of coupon amounts
use Shop\Models\ProductType;
use Shop\Models\Dates;
use Shop\Models\DataArray;
use Shop\Template;
use Shop\OrderItem;
use Shop\Order;
use Shop\Models\IPN;
use Shop\FieldList;
use Shop\Company;
use Shop\Currency;
use Shop\Cache;
use Shop\Log;
use Shop\Config;


/**
 * Class for coupons.
 * @package shop
 */
class Coupon extends \Shop\Product
{
    /** Maximum possible expiration date.
     * Used as a default for purchased coupons.
     * @const string */
    const MAX_EXP = '9999-12-31';

    /** Valid status.
     * @const string */
    const VALID =  'valid';

    /** Pending status.  Used to accumulate a coupon balance.
     * @const string */
    const PENDING = 'pending';

    /** Voided status.
     * @const string */
    const VOID = 'void';


    /**
     * Constructor. Set up local variables.
     *
     * @param   integer $prod_id    Product ID
     */
    public function __construct($prod_id = 0)
    {
        global $LANG_SHOP;

        parent::__construct($prod_id);
        $this->prod_type == ProductType::COUPON;
        $this->taxable = 0; // coupons are not taxable

        // Add special fields for Coupon products
        // Relies on $LANG_SHOP for the text prompts
        $this->addSpecialField('recipient_email');
        $this->addSpecialField('sender_name');
        $this->addSpecialField('gc_message', $LANG_SHOP['message'], array('type'=>'textarea'));
    }


    /**
     * Generate a single coupon code based on options given.
     * Mask, if used, is "XXXX-XXXX" where "X" indicates a character and any
     * other character is passed through.
     * Based on https://github.com/joashp/simple-php-coupon-code-generator.
     *
     * @author      Joash Pereira
     * @author      Alex Rabinovich
     * @see         https://github.com/joashp/simple-php-coupon-code-generator
     * @param   array   $opts   Override options
     * @return  string      Coupon code
     */
    public static function generate(array $opts = array()) : string
    {
        global $_SHOP_CONF;

        $opts = new DataArray($opts);

        // Set all the standard option values
        $options = array(
            'length'    => $opts->getInt('gc_length', 10),
            'prefix'    => $_SHOP_CONF['gc_prefix'],
            'suffix'    => $_SHOP_CONF[ 'gc_suffix'],
            'letters'   => $opts->getInt('gc_letters'),
            'numbers'   => $opts->getInt('gc_numbers'),
            'symbols'   => $opts->getInt('gc_symbols'),
            'mask'      => $_SHOP_CONF['gc_mask'],
        );

        // Now overlay the requested options
        if (is_array($opts)) {
            foreach ($opts as $key=>$val) {
                $options[$key] = $val;
            }
        }

        $uppercase  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase  = 'abcdefghijklmnopqrstuvwxyz';
        $numbers    = '1234567890';
        $symbols    = '`~!@#$%^&*()-_=+[]{}|";:/?.>,<';

        $characters = '';
        $coupon = '';

        switch ($options['letters']) {
        case 1:     // uppercase only
            $characters = $uppercase;
            break;
        case 2:     // lowercase only
            $characters = $lowercase;
            break;
        case 3:     // both upper and lower
            $characters = $uppercase . $lowercase;
            break;
        case 0:     // no letters
        default:
            break;
        }
        if ($options['numbers']) {
            $characters .= $numbers;
        }
        if ($options['symbols']) {
            $characters .= $symbols;
        }
        $charcount = strlen($characters);

        // If a mask is specified, use it and substitute 'X' for coupon chars.
        // Otherwise use the specified length.
        if ($options['mask'] != '') {
            $mask = $options['mask'];
            $len = strlen($mask);
            $characters = $uppercase;
            $charcount = strlen($characters);
            for ($i = 0; $i < $len; $i++) {
                if ($mask[$i] === 'X') {
                    $coupon .= $characters[random_int(0, $charcount - 1)];
                } else {
                    $coupon .= $mask[$i];
                }
            }
        } else {
            // if neither mask nor length given use a default length
            if ($options['length'] == 0) {
                $options['length'] = 16;
            }
            for ($i = 0; $i < $options['length']; $i++) {
                $coupon .= $characters[random_int(0, $charcount - 1)];
            }
        }
        return $options['prefix'] . $coupon . $options['suffix'];
    }


    /**
     * Generate a number of coupon codes.
     *
     * @param   integer $num        Number of coupon codes
     * @param   array   $options    Options for code creation
     * @return  array       Array of coupon codes
     */
    public static function generate_coupons(int $num = 1, array $options = array()) : array
    {
        $coupons = array();
        for ($i = 0; $i < $num; $i++) {
            $coupons[] = self::generate($options);
        }
        return $coupons;
    }


    /**
     * Record a coupon purchase.
     *
     * @param   float   $amount     Coupon value
     * @param   integer $uid        User ID, default = current user
     * @param   string  $exp        Expiration date
     * @return  mixed       Coupon code, or false on error
     */
    public static function Purchase(float $amount = 0, int $uid = 0, string $exp = self::MAX_EXP) : ?string
    {
        global $_TABLES, $_USER;

        if ($amount == 0) return false;
        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $options = array();     // Use all options from global config
        $db = Database::getInstance();
        do {
            // Make sure there are no duplicates
            $code = self::generate($options);
        } while ($db->getCount($_TABLES['shop.coupons'], 'code', $code, Database::STRING));

        $uid = (int)$uid;
        if (empty($exp)) {
            // Just in case an empty string gets passed in.
            $exp = self::MAX_EXP;
        }
        $amount = (float)$amount;
        try {
            $db->conn->insert(
                $_TABLES['shop.coupons'],
                array(
                    'code' => $code,
                    'buyer' => $uid,
                    'amount' => $amount,
                    'balance' => $amount,
                    'purchased' => time(),
                    'expires' => $exp,
                ),
                array(
                    Database::STRING,
                    Database::INTEGER,
                    Database::STRING,
                    Database::STRING,
                    Database::INTEGER,
                    Database::STRING,
                )
            );
            return $code;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return NULL;
        }
    }


    /**
     * Apply a coupon to the user's account.
     * Adds the value to the gc_bal field in user info, and marks the coupon
     * as "redeemed" so it can't be used again.
     * Status code returned will be 0=success, 1=already done, 2=error
     *
     * @param   string  $code   Coupon code
     * @param   integer $uid    Optional user ID, current user by default
     * @return  array       Array of (Status code, Message)
     */
    public static function Redeem(string $code, ?int $uid = NULL) : array
    {
        global $_TABLES, $_USER, $LANG_SHOP, $_CONF;

        if (empty($uid)) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        if ($uid < 2) {
            return array(2, sprintf($LANG_SHOP['coupon_apply_msg2'], $_CONF['site_email']));
        }

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.coupons']}
                WHERE code = ?",
                array($code),
                array(Database::STRING)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }

        if (!is_array($data) || count($data) == 0) {
            Log::write('shop_system', Log::ERROR, "Attempting to redeem coupon $code, not found in database");
            return array(
                3,
                sprintf(
                    $LANG_SHOP['coupon_apply_msg3'],
                    Company::getInstance()->getEmail()
                )
            );
        } else {
            if ($data['redeemed'] > 0 && $data['redeemer'] > 0) {
                Log::write('shop_system', Log::ERROR, "Coupon code $code was already redeemed");
                return array(1, $LANG_SHOP['coupon_apply_msg1']);
            } elseif ($data['status'] != self::VALID) {
                Log::write('shop_system', Log::ERROR, "Coupon $code status is not valid");
                return array(1, $LANG_SHOP['coupon_apply_msg3']);
            }
        }
        $amount = (float)$data['amount'];
        if ($amount > 0) {
            try {
                $db->conn->update(
                    $_TABLES['shop.coupons'],
                    array(
                        'redeemer' => $uid,
                        'redeemed' => time(),
                    ),
                    array('code' => $code),
                    array(Database::INTEGER, Database::INTEGER, Database::STRING)
                );
                Cache::clear('coupons');
                self::writeLog($code, $uid, $amount, 'gc_redeemed');
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return array(2, sprintf($LANG_SHOP['coupon_apply_msg2'], $_CONF['site_email']));
            }
        }
        return array(
            0,
            sprintf(
                $LANG_SHOP['coupon_apply_msg0'],
                Currency::getInstance()->Format($data['amount'])
            )
        );
    }


    /**
     * Apply a coupon value against an order.
     * Deducts the maximum of the coupon balance or the order value from
     * the coupons table.
     *
     * @param   float   $amount     Amount to redeem (order value)
     * @param   integer $uid        User ID redeeming the coupon
     * @param   object  $Order      Order object
     * @return  array|false     Array of codes and amounts, False on error
     */
    public static function Apply(float $amount, ?int $uid = NULL, ?Order $Order = NULL) : ?array
    {
        global $_TABLES, $_USER, $LANG_SHOP;

        $retval = array();
        if ($uid == 0) $uid = $_USER['uid'];
        $order_id = '';
        if (is_object($Order) && !$Order->isNew()) {
            $order_id = $Order->getOrderID();
            $uid = $Order->getUid();
        }

        if ($uid < 2 || $amount == 0) {
            // Nothing to do if amount is zero, and anon users not supported
            // at this time.
            return $retval;
        }

        $user_balance = self::getUserBalance($uid);
        if ($user_balance < $amount) {  // error: insufficient balance
            return NULL;
        }
        $coupons = self::getUserCoupons($uid);
        $remain = (float)$amount;
        $applied = 0;
        $db = Database::getInstance();
        foreach ($coupons as $coupon) {
            $bal = (float)$coupon['balance'];
            $code = $coupon['code'];
            if ($bal > $remain) {
                // Coupon balance is enough to cover the remaining amount
                $bal -= $remain;
                $applied += $remain;
                $retval[$code] = $remain;
                $remain = 0;
            } else {
                // Apply the total balance on this coupon and loop to the next one
                $remain -= $bal;
                $applied += $bal;
                $retval[$code] = $bal;
                $bal = 0;
            }
            $bal = round($bal, 4);
            try {
                $db->conn->update(
                    $_TABLES['shop.coupons'],
                    array('balance' => $bal),
                    array('code' => $code),
                    array(Database::STRING, Database::STRING)
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
            if ($remain == 0) break;
        }
        if ($applied > 0) {
            self::writeLog('', $uid, $applied, 'gc_applied', $order_id);
        }

        Cache::clear('coupons_' . $uid);
        return $retval;     // return array of applied coupons and amounts
    }


    /**
     * Restore used card balances to the card records.
     * This is used after a finalized order is reverted to `cart` status,
     * such as when the payment process is cancelled.
     *
     * @param   string  $code   Coupon code
     * @param   float   $amount Amount to restore
     * @return  boolean     True on success, False on error
     */
    public static function Restore(string $code, float $amount) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeStatement(
                "UPDATE {$_TABLES['shop.coupons']} SET
                balance = balance + ?
                WHERE code = ?",
                array($amount, $code),
                array(Database::STRING, Database::STRING)
            );
            return true;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Handle the purchase of this item.
     *
     * @param  object  $Item    OrderItem object, to get options, etc.
     * @param  object  $IPN     IPN model object
     * @return integer     Zero or error value
     */
    public function handlePurchase(OrderItem &$Item, IPN $IPN) : int
    {
        global $LANG_SHOP;

        $Order = $Item->getOrder();
        $status = 0;
        $amount = (float)$Item->getPrice();
        $Extras = new DataArray($Item->getExtras());
        $special = new DataArray($Extras->getArray('special'));
        $recip_email = $special->getString('recipient_email');
        if (empty($recip_email)) {
            $recip_email = $Order->getBuyerEmail();
            $Item->addSpecial('recipient_email', $recip_email);
        }
        $sender_name = $IPN['payer_name'];
        $msg = $special->getString('message');
        $uid = $Item->getOrder()->getUid();
        $gc_code = self::Purchase($amount, $uid);
        $Item->addSpecial('gc_code', $gc_code);
        $Item->Save();
        parent::handlePurchase($Item, $IPN);
        self::Notify($gc_code, $recip_email, $amount, $sender_name, $msg);
        return $status;
    }


    /**
     * Send a notification email to the recipient of the gift card.
     *
     * @param   string  $gc_code    Gift Cart Code
     * @param   string  $recip      Recipient Email, from the custom text field
     * @param   float   $amount     Gift Card Amount
     * @param   string  $sender     Optional sender, from the custom text field
     * @param   string  $msg        Optional extra message
     * @param   string  $exp        Expiration Date
     * @param   string  $recip_name Name of recipient
     */
    public static function Notify(
        $gc_code, $recip, $amount, $sender='', $msg='', $exp=self::MAX_EXP, $recip_name=''
    )
    {
        global $_CONF, $LANG_SHOP_EMAIL;

        if ($recip == '') {
            return;
        }

        $Shop = new Company;
        Log::write('shop_system', Log::DEBUG, "Sending Coupon to " . $recip);
        $T = new Template('notify');
        $T->set_file(array(
            'header_tpl' => 'header.thtml',
            'message' => 'coupon_email_message.thtml',
        ) );
        if ($exp < self::MAX_EXP) {
            $dt = new \Date($exp);
            $exp = $dt->format(Dates::FMT_FULLDATE);
            $T->set_var('expires', $exp);
        }
        $T->set_var(array(
            'gc_code'   => $gc_code,
            'sender_name' => $sender,
            'submit_url' => self::redemptionUrl($gc_code),
            'message'   => strip_tags($msg),
            // Elements for notification header or footer
            'shop_name'         => $Shop->getCompany(),
            'shop_addr1'        => $Shop->getAddress1(),
            'shop_addr2'        => $Shop->getAddress2(),
            'shop_city'         => $Shop->getCity(),
            'shop_state'        => $Shop->getState(),
            'shop_postal'       => $Shop->getPostal(),
            'shop_phone'        => $Shop->getPhone(),
            'shop_email'        => $Shop->getEmail(),
            'shop_addr'         => $Shop->toHTML('address'),
            'logo_url'          => Config::get('logo_url'),
            'logo_height'       => Config::get('logo_height'),
            'logo_width'        => Config::get('logo_width'),
        ) );
        $T->set_var('header', $T->parse('', 'header_tpl'));
        $T->parse('output', 'message');
        $msg_text = $T->finish($T->get_var('output'));
        if (empty($recip_name)) {
            $recip_name = $recip;
        }
        $email_vars = array(
            'to' => array(
                $recip,
                $recip_name,
            ),
            'from' => array(
                'email' => Company::getInstance()->getEmail(),
                'name'  => Company::getInstance()->getCompany(),
            ),
            'htmlmessage' => $msg_text,
            'subject' => $LANG_SHOP_EMAIL['coupon_subject'],
        );
        COM_emailNotification($email_vars);
        Log::write('shop_system', Log::DEBUG, "Coupon notification sent to $recip.");
    }


    /**
     * Get additional text to add to the buyer's recipt for a product.
     * For coupons, add links to redeem against an account.
     *
     * @param   object  $item   Order Item object, to get the code
     * @return  string          Additional message to include in email
     */
    public function EmailExtra(OrderItem $OI) : string
    {
        global $LANG_SHOP;

        $retval = '';
        $extra = $OI->getExtras();
        if (isset($extra['special']) && isset($extra['special']['gc_code'])) {
            $code = $extra['special']['gc_code'];
            if (!empty($code)) {
                $url = self::redemptionUrl($code);
                $retval = sprintf(
                    $LANG_SHOP['apply_gc_email'],
                    $url,
                    $url,
                    $url
                );
            }
        }
        return $retval;
    }


    /**
     * Get the display price for the catalog.
     * Returns "See Details" if the price is zero, or the price if
     * one is set.
     *
     * @param   mixed   $price  Fixed price override (not used)
     * @return  string          Formatted price, or "See Details"
     */
    public function getDisplayPrice($price = NULL)
    {
        global $LANG_SHOP;

        $price = $this->getPrice();
        if ($price == 0) {
            return $LANG_SHOP['see_details'];
        } else {
            return Currency::getInstance()->Format($price);
        }
    }


    /**
     * Get all the current Gift Card records for a user.
     * If $all is true then all records are returned, if false then only
     * those that are not redeemed and not expired are returned.
     *
     * @param   integer $uid    User ID, default = curent user
     * @param   boolean $all    True to get all, False to get currently usable
     * @return  array           Array of gift card records
     */
    public static function getUserCoupons(?int $uid = NULL, ?bool $all = NULL) : array
    {
        global $_TABLES, $_USER;

        if (empty($uid)) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        if ($uid < 2) return array();   // Can't get anonymous coupons here

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        $all = $all ? 1 : 0;
        $cache_key = 'coupons_' . $uid . '_' . $all;
        $updatecache = false;       // indicator that cache must be updated
        $today = date('Y-m-d');
        /*$coupons = Cache::get($cache_key);
        if ($coupons === NULL) {*/
            // cache not found, read all non-expired coupons
            $coupons = array();
            try {
                $qb->select('*')
                   ->from($_TABLES['shop.coupons'])
                   ->where('status = :status')
                   ->andWhere('redeemer = :uid')
                   ->setParameter('status', self::VALID, Database::STRING)
                   ->setParameter('uid', $uid, Database::INTEGER);
                if (!$all) {
                    $qb->andWhere('expires = :nulldate OR expires >= :today')
                       ->andWhere('balance > 0')
                       ->setParameter('today', $today, Database::STRING)
                       ->setParameter('nulldate', '0000-00-00', Database::STRING);
                }
                $qb->orderBy('redeemed', 'ASC');
                $data = $qb->execute()->fetchAllAssociative();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $data = false;
            }
            if (is_array($data)) {
                foreach ($data as $A) {
                    $coupons[] = $A;
                }
            }
            $updatecache = true;
        /*} else {
            // Check the cached expiration dates in case any expired.
            foreach ($coupons as $idx=>$coupon) {
                if ($coupon['expires'] < $today) {
                    unset($coupons[$idx]);
                    $updatecache = true;
                }
            }
        }

        // If coupons were read from the DB, or any cached ones expired,
        // update the cache
        if ($updatecache) {
            Cache::set(
                $cache_key,
                $coupons,
                array('coupons', 'coupons_' . $uid),
                3600
            );
        }*/
        return $coupons;
    }


    /**
     * Get the current unused Gift Card balance for a user.
     *
     * @param   integer $uid    User ID, default = current user
     * @return  float           User's gift card balance
     */
    public static function getUserBalance(?int $uid = NULL) : float
    {
        global $_USER;

        if (empty($uid)) {
            $uid = $_USER['uid'];
        }
        if ($uid == 1) {
            return 0;    // no coupon bal for anonymous
        }

        // Total up the available balances from the coupons table
        $bal = (float)0;
        $coupons = self::getUserCoupons($uid);
        foreach ($coupons as $coupon) {
            $bal += $coupon['balance'];
        }
        return (float)$bal;
    }


    /**
     * Verifies that the given user has a sufficient balance to cover an amount.
     *
     * @uses    self::getUserBalance()
     * @param   float   $amount     Amount to check
     * @param   integer $uid        User ID, default = current user
     * @return  boolean             True if the GC balance is sufficient.
     */
    public static function verifyBalance(float $amount, ?int $uid = NULL) : bool
    {
        $amount = (float)$amount;
        $balance = self::getUserBalance($uid);
        return $amount <= $balance ? true : false;
    }


    /**
     * Write a log entry.
     *
     * @param   string  $code       Gift card code
     * @param   integer $uid        User ID
     * @param   float   $amount     Gift card amount or amount applied
     * @param   string  $msg        Message to log
     * @param   string  $order_id   Order ID (when applying)
     */
    public static function writeLog(string $code, int $uid, float $amount, string $msg, string $order_id = '') : void
    {
        global $_TABLES, $_USER;

        $db = Database::getInstance();
        $amount = (float)$amount;
        $uid = (int)$uid;
        $done_by = (int)$_USER['uid'];

        try {
            $db->conn->insert(
                $_TABLES['shop.coupon_log'],
                array(
                    'code' => $code,
                    'uid' => $uid,
                    'done_by' => $done_by,
                    'order_id' => $order_id,
                    'ts' => time(),
                    'amount' => $amount,
                    'msg' => $msg,
                ),
                array(
                    Database::STRING,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                    Database::INTEGER,
                    Database::STRING,
                    Database::STRING,
                )
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Get the log entries for a user ID to show in their account.
     * Optionally specify a gift card code to get only entries
     * pertaining to that gift card.
     *
     * @param   integer $uid    User ID
     * @param   string  $code   Optional gift card code
     * @return  array           Array of log messages
     */
    public static function getLog(int $uid, ?string $code = NULL) : array
    {
        global $_TABLES, $LANG_SHOP;

        $log = array();
        $uid = (int)$uid;
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$_TABLES['shop.coupon_log']}
                WHERE uid = ?";
        $values = array($uid);
        $types = array(Database::INTEGER);
        if (!empty($code)) {
            $sql .= " AND code = ?";
            $values[] = $code;
            $types[] = Database::STRING;
        }
        $sql .= ' ORDER BY ts DESC';
        try {
            $data = $db->conn->executeQuery($sql, $values, $types)
                             ->getchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $log[] = $A;
            }
        }
        return $log;
    }


    /**
     * From a cart, get the total items that can be paid by gift card.
     * Start with the order total and deduct any coupon items.
     *
     * @param   object  $cart   Shopping Cart
     * @return  float           Total payable by gift card
     */
    public static function canPayByGC(Cart $cart) : float
    {
        $gc_can_apply = $cart->getTotal();
        $items = $cart->getItems();
        foreach ($items as $item) {
            $P = $item->getProduct();
            if ($P->isNew() || $P->getProductType() == ProductType::COUPON) {
                $gc_can_apply -= $P->getPrice($item->getOptions(), $item->getQuantity()) * $item->getQuantity();
            }
        }
        if ($gc_can_apply < 0) {
            $gc_can_apply = 0;
        }
        return $gc_can_apply;
    }


    /**
     * Determine if the current user has access to view this product.
     * Checks that gift cards are enabled in the configuration, then
     * checks the general product hasAccess() function.
     *
     * @param   integer $uid    User ID, current user if null
     * @return  boolean     True if access and purchase is allowed.
     */
    public function hasAccess(?int $uid = NULL) : bool
    {
        global $_SHOP_CONF;

        if (!$_SHOP_CONF['gc_enabled']) {
            return false;
        } else {
            return parent::hasAccess($uid);
        }
    }


    /**
     * Get the fixed quantity that can be ordered per item view.
     * If this is zero, then an input box will be shown for the buyer to enter
     * a quantity. If nonzero, then the input box is a hidden variable with
     * the value set to the fixed quantity
     *
     * @return  integer    Fixed quantity number, zero for varible qty
     */
    public function getFixedQuantity() : int
    {
        return 1;
    }


    /**
     * Determine if like items can be accumulated in the cart as a single item.
     *
     * @return  boolean     False, Gift cards are never accumulated.
     */
    public function cartCanAccumulate() : bool
    {
        return false;
    }


    /**
     * Administratively void a single coupon.
     * Only coupons with a remaining balance can be voided, no reason to
     * void a fully-used coupon.
     *
     * @param   string  $code       Coupon code to be updated
     * @param   string  $newstatus  New status to set
     * @return  boolean     True on success, False on failure
     */
    public static function Void(string $code, string $newstatus=self::VOID) : bool
    {
        global $_TABLES, $_USER;;

        // Check that the requested status is valid
        if ($newstatus != self::VOID && $newstatus != self::VALID) {
            Log::write('shop_system', Log::ERROR, __METHOD__ . ": Invalid status: $newstatus");
            return false;
        }

        Log::write('shop_system', Log::DEBUG, "Setting $code as $newstatus");
        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.coupons']}
                WHERE code = ?",
                array($code),
                array(Database::STRING)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (!is_array($data) || empty($data)) {
            return false;
        }

        $balance = (float)$data['balance'];

        $qb->update($_TABLES['shop.coupons'])
           ->set('status', ':newstatus')
           ->where('code = :code')
           ->setParameter('newstatus', $newstatus, Database::STRING)
           ->setParameter('code', $code, Database::STRING);
        if ($newstatus == self::VOID) {
            if ($balance <= 0) {
                // break here, balance must be > 0 to void
                return false;
            }
            $log_code = 'gc_voided';
            $qb->andWhere("balance > 0");
        } else {
            $log_code = 'gc_unvoided';
        }
        $values[] = $code;
        $types[] = Database::STRING;
        try {
            $qb->execute();
            self::writeLog($code, $data['redeemer'], $balance, $log_code);
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Expire one or more coupons.
     * If $code is empty, then all coupons with a balance > 0 that have
     * expired are updated.
     *
     * @param   string  $code   Optional code to expire one coupon
     */
    public static function Expire(? string $code=NULL) : void
    {
        global $_TABLES, $_CONF;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        $qb->select('*')
            ->from($_TABLES['shop.coupons']);
        if (empty($code)) {
            $today = $_CONF['_now']->format('Y-m-d', true);
            $qb->where('balance > 0 AND expires < :today')
               ->setParameter('today', $today, Database::STRING);
        } else {
            $qb->where('balance > 0 AND code = :code')
               ->setParameter('code', $code, Database::STRING);
        }
        try {
            $data = $qb->execute()->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                try {
                    $db->conn->update(
                        $_TABLES['shop.coupons'],
                        array('balance' => 0),
                        array('code' => $A['code']),
                        array(Database::INTEGER, Database::STRING)
                    );
                } catch (\Exception $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                }
                self::writeLog($c, $A['redeemer'], $A['balance'], 'gc_expired');
            }
            if (count($data) > 0) {
                // If there were any updates, clear the coupon cache
                Cache::clear('coupons');
            }
        }
    }


    /**
     * Get the link to redeem a coupon code.
     * The link is to the redemption form if no code is provided.
     *
     * @param   string  $code   Coupon Code
     * @return  string      URL to redeem the code
     */
    public static function redemptionUrl(?string $code = '') : string
    {
        $url = SHOP_URL . '/coupon.php?mode=redeem';
        if (!empty($code)) {
            $url .= '&id=' . $code;
        }
        return COM_buildUrl($url);
    }


    /**
     * Purge all coupons and transactions from the database.
     * No safety check or confirmation is done; that should be done before
     * calling this function.
     *
     * @return  boolean     True on success, False on error
     */
    public static function Purge() : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeStatement(
                "TRUNCATE {$_TABLES['shop.coupons']}"
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        try {
            $db->conn->executeStatement(
                "TRUNCATE {$_TABLES['shop.coupon_log']}"
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Get the text string and value for special fields.
     * Used when displaying cart info.
     * Overrides parent function to exclude the custom message field.
     *
     * @param   array   $values     Special field values
     * @return  array       Array of text=>value
     */
    public function getSpecialFields(array $values = array()) : array
    {
        global $LANG_SHOP;

        $retval = array();
        if (empty($values)) {
            return $retval;
        }
        foreach ($this->special_fields as $fld_name=>$fld) {
            if ($fld_name == 'gc_message') {
                continue;
            }
            if (array_key_exists($fld_name, $values) && !empty($values[$fld_name])) {
                $retval[$fld['text']] = $values[$fld_name];
            }
        }
        return $retval;
    }


    /**
     * Display the purchase history for coupons.
     *
     * @param   integer $cat_id     Category ID to limit listing
     * @return  string      Display HTML
     */
    public static function adminList(?int $cat_id=NULL) : string
    {
        global $_TABLES, $LANG_SHOP, $_SHOP_CONF;

        USES_lib_admin();
        $filt_sql = '';
        if (isset($_GET['filter']) && isset($_GET['value'])) {
            switch ($_GET['filter']) {
            case 'buyer':
            case 'redeemer':
                $filt_sql = "WHERE `{$_GET['filter']}` = '" . DB_escapeString($_GET['value']) . "'";
                break;
            }
        }
        $sql = "SELECT * FROM {$_TABLES['shop.coupons']} $filt_sql";

        $header_arr = array(
            array(
                'text' => $LANG_SHOP['code'],
                'field' => 'code',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['purch_date'],
                'field' => 'purchased',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['amount'],
                'field' => 'amount',
                'sort' => false,
                'align' => 'right',
            ),
            array(
                'text' => $LANG_SHOP['balance'],
                'field' => 'balance',
                'sort' => false,
                'align' => 'right',
            ),
            array(
               'text' => $LANG_SHOP['buyer'],
                'field' => 'buyer',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['redeemer'],
                'field' => 'redeemer',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['status'],
                'field' => 'status',
                'sort' => false,
            ),
        );

        $defsort_arr = array(
            'field' => 'purchased',
            'direction' => 'DESC',
        );

        $query_arr = array(
            'table' => 'shop.coupons',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?coupons=x',
        );

        $bulk_update = FieldList::button(array(
            'name' => 'coup_bulk_void',
            'text' => $LANG_SHOP['void'],
            'value' => self::VOID,
            'size' => 'mini',
            'style' => 'danger',
            'attr' => array(
                'onclick' => "return confirm('" . $LANG_SHOP['q_confirm_void'] . "');",
            ),
        ) );
        $bulk_update .= FieldList::button(array(
            'name' => 'coup_bulk_unvoid',
            'text' => $LANG_SHOP['valid'],
            'value' => self::VALID,
            'size' => 'mini',
            'style' => 'success',
            'attr' => array(
                'onclick' => "return confirm('" . $LANG_SHOP['q_confirm_unvoid'] . "');",
            ),
        ) );

        $options = array(
            'chkdelete' => true,
            'chkall' => true,
            'chkfield' => 'code',
            'chkname' => 'coupon_code',
            'chkactions' => $bulk_update,
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $display .= '<h2>' . $LANG_SHOP['couponlist'] . '</h2>';
        $display .= '<div>' . FieldList::buttonLink(array(
            'text' => $LANG_SHOP['send_giftcards'],
            'url' => SHOP_ADMIN_URL . '/index.php?sendcards_form=x',
            'style' => 'primary',
        ) ) .
        '</div>';
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_couponlist',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the coupon listing.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra=array())
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        $retval = '';
        static $username = array();
        static $Cur = NULL;
        static $Dt = NULL;
        if ($Dt === NULL) $Dt = new \Date('now', $_CONF['timezone']);
        if ($Cur === NULL) $Cur = Currency::getInstance();

        switch($fieldname) {
        case 'buyer':
        case 'redeemer':
            if ($fieldvalue > 0) {
                if (!isset($username[$fieldvalue])) {
                    $username[$fieldvalue] = COM_getDisplayName($fieldvalue);
                }
                $retval = COM_createLink($username[$fieldvalue],
                    SHOP_ADMIN_URL . "/index.php?coupons=x&filter=$fieldname&value=$fieldvalue",
                    array(
                        'title' => 'Click to filter by ' . $fieldname,
                        'class' => 'tooltip',
                    )
                );
            }
            break;

        case 'amount':
        case 'balance':
            $retval = $Cur->FormatValue($fieldvalue);
            break;

        case 'purchased':
        case 'redeemed':
            $Dt->setTimestamp((int)$fieldvalue);
            $retval = SHOP_dateTooltip($Dt);
            break;

        case 'status':
            $btn_txt = SHOP_getVar($LANG_SHOP, $A['status'], 'string', 'Valid');
            $newval = NULL;
            if ($A['balance'] > 0) {
                if ($A['status'] == self::VOID && $A['balance'] > 0) {
                    $conf_txt = $LANG_SHOP['q_confirm_unvoid'];
                    $newval = self::VALID;
                    $title = $LANG_SHOP['unvoid_item'];
                    $btn_class = 'danger';
                } elseif ($A['status'] == self::VALID && $A['balance'] > 0) {
                    $conf_txt = $LANG_SHOP['q_confirm_void'];
                    $newval = self::VOID;
                    $title = $LANG_SHOP['void_item'];
                    $btn_class = 'success';
                }
            }
            if ($newval) {
                $retval .= FieldList::button(array(
                    'title' => $title,
                    'text' => $btn_txt,
                    'style' => $btn_class,
                    'size' => 'mini',
                    'attr' => array(
                        'onclick' => "if (confirm('{$conf_txt}')) {
                        SHOP_voidItem('coupon','{$A['code']}','$newval',this);
                        }return false;",
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
     * Check if a discount code can be applied to this product.
     *
     * @return  boolean     True if a code can apply, False if not
     */
    public function canApplyDiscountCode() : bool
    {
        return false;
    }


    /**
     * Handle a product refund.
     *
     * @param   object  $OI     OrderItem being refunded
     * @param   object  $IPN    Shop IPN data
     * @return  boolean     True on success, False on error
     */
    public function handleRefund(OrderItem $OI, IPN $IPN) : bool
    {
        $retval = false;
        $extras = $OI->getExtras();
        if (isset($extras['special']) && is_array($extras['special'])) {
            if (isset($extras['special']['gc_code'])) {
                self::Void($extras['special']['gc_code']);
                $retval = true;
            }
        }
        return $retval;
    }


    /**
     * Gift coupons can't be placed on sale.
     *
     * @param   float   $price      Base price to use for calculation
     * @return  float       On-Sale price (same as base for coupons)
     */
    public function getSalePrice(?float $price = NULL) : float
    {
        return $this->getBasePrice();
    }

}

