<?php
/**
 * Class to handle coupon operations.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 *
 */
namespace Shop\Products;
use Shop\Payment;   // to record application of coupon amounts
use Shop\Models\ProductType;
use Shop\Models\Dates;
use Shop\Template;
use Shop\OrderItem;
use Shop\Order;
use Shop\Models\IPN;
use Shop\FieldList;
use Shop\Company;
use Shop\Currency;
use Shop\Cache;


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
    public static function generate($opts = array())
    {
        global $_SHOP_CONF;

        // Set all the standard option values
        $options = array(
            'length'    => SHOP_getVar($_SHOP_CONF, 'gc_length', 'int', 10),
            'prefix'    => $_SHOP_CONF['gc_prefix'],
            'suffix'    => $_SHOP_CONF[ 'gc_suffix'],
            'letters'   => SHOP_getVar($_SHOP_CONF, 'gc_letters', 'int'),
            'numbers'   => SHOP_getVar($_SHOP_CONF, 'gc_numbers', 'int'),
            'symbols'   => SHOP_getVar($_SHOP_CONF, 'gc_symbols', 'int'),
            'mask'      => $_SHOP_CONF['gc_mask'],
        );

        // Now overlay the requested options
        foreach ($opts as $key=>$val) {
            $options[$key] = $val;
        }

        $uppercase  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase  = 'abcdefghijklmnopqrstuvwxyz';
        $numbers    = '1234567890';
        $symbols    = '`~!@#$%^&*()-_=+[]{}|";:/?.>,<';

        $characters = array();
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

        if (function_exists('random_int')) {
            $rand_func = 'random_int';
        } else {
            $rand_func = 'mt_rand';
        }

        // If a mask is specified, use it and substitute 'X' for coupon chars.
        // Otherwise use the specified length.
        if ($options['mask'] != '') {
            $mask = $options['mask'];
            $len = strlen($mask);
            for ($i = 0; $i < $len; $i++) {
                if ($mask[$i] === 'X') {
                    $coupon .= $characters[$rand_func(0, $charcount - 1)];
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
                $coupon .= $characters[$rand_func(0, $charcount - 1)];
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
    public static function generate_coupons($num = 1, $options = array())
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
    public static function Purchase($amount = 0, $uid = 0, $exp = self::MAX_EXP)
    {
        global $_TABLES, $_USER;

        if ($amount == 0) return false;
        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $options = array();     // Use all options from global config
        do {
            // Make sure there are no duplicates
            $code = self::generate($options);
            $code = DB_escapeString($code);
        } while (DB_count($_TABLES['shop.coupons'], 'code', $code));

        $uid = (int)$uid;
        if (empty($exp)) {
            // Just in case an empty string gets passed in.
            $exp = self::MAX_EXP;
        }
        $exp = DB_escapeString($exp);
        $amount = (float)$amount;
        $sql = "INSERT INTO {$_TABLES['shop.coupons']} SET
                code = '$code',
                buyer = $uid,
                amount = $amount,
                balance = $amount,
                purchased = UNIX_TIMESTAMP(),
                expires = '$exp'";
        DB_query($sql);
        return DB_error() ? false : $code;
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
    public static function Redeem($code, $uid = 0)
    {
        global $_TABLES, $_USER, $LANG_SHOP, $_CONF;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        if ($uid < 2) {
            return array(2, sprintf($LANG_SHOP['coupon_apply_msg2'], $_CONF['site_email']));
        }

        $code = DB_escapeString($code);
        $sql = "SELECT * FROM {$_TABLES['shop.coupons']}
                WHERE code = '$code'";
        $res = DB_query($sql);
        if (DB_numRows($res) == 0) {
            SHOP_log("Attempting to redeem coupon $code, not found in database", SHOP_LOG_ERROR);
            return array(
                3,
                sprintf(
                    $LANG_SHOP['coupon_apply_msg3'],
                    Company::getInstance()->getEmail()
                )
            );
        } else {
            $A = DB_fetchArray($res, false);
            if ($A['redeemed'] > 0 && $A['redeemer'] > 0) {
                SHOP_log("Coupon code $code was already redeemed", SHOP_LOG_ERROR);
                return array(1, $LANG_SHOP['coupon_apply_msg1']);
            } elseif ($A['status'] != self::VALID) {
                SHOP_log("Coupon $code status is not valid");
                return array(1, $LANG_SHOP['coupon_apply_msg3']);
            }
        }
        $amount = (float)$A['amount'];
        if ($amount > 0) {
            DB_query("UPDATE {$_TABLES['shop.coupons']} SET
                    redeemer = $uid,
                    redeemed = UNIX_TIMESTAMP()
                    WHERE code = '$code'");
            Cache::clear('coupons');
            self::writeLog($code, $uid, $amount, 'gc_redeemed');
            if (DB_error()) {
                SHOP_error("A DB error occurred marking coupon $code as redeemed", SHOP_LOG_ERROR);
                return array(2, sprintf($LANG_SHOP['coupon_apply_msg2'], $_CONF['site_email']));
            }
        }
        return array(
            0,
            sprintf(
                $LANG_SHOP['coupon_apply_msg0'],
                Currency::getInstance()->Format($A['amount'])
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
    public static function Apply($amount, $uid = 0, $Order = NULL)
    {
        global $_TABLES, $_USER, $LANG_SHOP;

        $retval = array();
        if ($uid == 0) $uid = $_USER['uid'];
        $order_id = '';
        if (is_object($Order) && !$Order->isNew()) {
            $order_id = DB_escapeString($Order->getOrderID());
            $uid = $Order->getUid();
        }
        if ($uid < 2 || $amount == 0) {
            // Nothing to do if amount is zero, and anon users not supported
            // at this time.
            return $retval;
        }
        $user_balance = self::getUserBalance($uid);
        if ($user_balance < $amount) {  // error: insufficient balance
            return false;
        }
        $coupons = self::getUserCoupons($uid);
        $remain = (float)$amount;
        $applied = 0;
        foreach ($coupons as $coupon) {
            $bal = (float)$coupon['balance'];
            $code = DB_escapeString($coupon['code']);
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
            $sql = "UPDATE {$_TABLES['shop.coupons']}
                    SET balance = $bal
                    WHERE code = '$code';";
            //echo $sql . "\n";
            DB_query($sql);
            if ($remain == 0) break;
        }
        if ($applied > 0) {
            self::writeLog('', $uid, $applied, 'gc_applied', $order_id);
        }

        /*if ($applied > 0) {
            // Record one payment record for the coupon
            $Pmt = new Payment;
            $Pmt->setRefID(uniqid())
                ->setAmount($applied)
                ->setGateway('_coupon')
                ->setMethod('Apply Coupon')
                ->setComment($LANG_SHOP['gc_pmt_comment'])
                ->setOrderID($order_id)
                ->Save();
        }*/

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
    public static function Restore($code, $amount)
    {
        global $_TABLES;

        $code = DB_escapeString($code);
        $amount = (float)$amount;
        $sql = "UPDATE {$_TABLES['shop.coupons']}
            SET balance = balance + $amount
            WHERE code = '$code'";
        $res = DB_query($sql);
        return DB_error() ? false : true;
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
        $special = SHOP_getVar($Item->getExtras(), 'special', 'array');
        $recip_email = SHOP_getVar($special, 'recipient_email', 'string');
        if (empty($recip_email)) {
            $recip_email = $Order->getBuyerEmail();
            $Item->addSpecial('recipient_email', $recip_email);
        }
        $sender_name = SHOP_getVar($IPN, 'payer_name', 'string');
        $msg = SHOP_getVar($special, 'message', 'string');
        $uid = $Item->getOrder()->getUid();
        $gc_code = self::Purchase($amount, $uid);
        // Add the code to the options text. Saving the item will happen
        // next during addSpecial
        //$Item->addOptionText($LANG_SHOP['code'], $gc_code);
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

        SHOP_log("Sending Coupon to " . $recip, SHOP_LOG_DEBUG);
        $T = new Template;
        $T->set_file('message', 'coupon_email_message.thtml');
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
        ) );
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
        SHOP_log("Coupon notification sent to $recip.", SHOP_LOG_DEBUG);
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
    public static function getUserCoupons($uid = 0, $all = false)
    {
        global $_TABLES, $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        $uid = (int)$uid;
        if ($uid < 2) return array();   // Can't get anonymous coupons here

        $all = $all ? 1 : 0;
        $cache_key = 'coupons_' . $uid . '_' . $all;
        $updatecache = false;       // indicator that cache must be updated
        $today = date('Y-m-d');
        /*$coupons = Cache::get($cache_key);
        if ($coupons === NULL) {*/
            // cache not found, read all non-expired coupons
            $coupons = array();
            $sql = "SELECT * FROM {$_TABLES['shop.coupons']}
                WHERE status='" . self::VALID . "' AND redeemer = '$uid'";
            if (!$all) {
                $sql .= " AND (expires = '0000-00-00' OR expires >= '$today') AND balance > 0";
            }
            $sql .= " ORDER BY redeemed ASC";
            //echo $sql;die;
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $coupons[] = $A;
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
    public static function getUserBalance($uid = 0)
    {
        global $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        if ($uid == 1) return 0;    // no coupon bal for anonymous

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
    public static function verifyBalance($amount, $uid = 0)
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
    public static function writeLog($code, $uid, $amount, $msg, $order_id = '')
    {
        global $_TABLES, $_USER;

        $msg = DB_escapeString($msg);
        $order_id = DB_escapeString($order_id);
        $code = DB_escapeString($code);
        $amount = (float)$amount;
        $uid = (int)$uid;
        $done_by = (int)$_USER['uid'];

        $sql = "INSERT INTO {$_TABLES['shop.coupon_log']} SET
            code = '{$code}',
            uid = {$uid},
            done_by = {$done_by},
            order_id = '{$order_id}',
            ts = UNIX_TIMESTAMP(),
            amount = {$amount},
            msg = '{$msg}'";
        DB_query($sql);
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
    public static function getLog($uid, $code = '')
    {
        global $_TABLES, $LANG_SHOP;

        $log = array();
        $uid = (int)$uid;
        $sql = "SELECT * FROM {$_TABLES['shop.coupon_log']}
                WHERE uid = $uid";
        if ($code != '') {
            $sql .= " AND code = '" . DB_escapeString($code) . "'";
        }
        $sql .= ' ORDER BY ts DESC';
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
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
    public static function canPayByGC($cart)
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
    public function getFixedQuantity()
    {
        return 1;
    }


    /**
     * Determine if like items can be accumulated in the cart as a single item.
     *
     * @return  boolean     False, Gift cards are never accumulated.
     */
    public function cartCanAccumulate()
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
            SHOP_log("Invalid status sent to Coupon::Void(): $newval");
            return false;
        }

        SHOP_log("Setting $code as $newstatus", SHOP_LOG_DEBUG);
        $code = DB_escapeString($code);
        $sql = "SELECT * FROM {$_TABLES['shop.coupons']}
                WHERE code = '$code'";
        $res = DB_query($sql);
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
        } else {
            return false;
        }

        $balance = (float)$A['balance'];
        $newstatus = DB_escapeString($newstatus);

        $sql = "UPDATE {$_TABLES['shop.coupons']}
            SET status = '$newstatus'
            WHERE code = '$code'";
        if ($newstatus == self::VOID) {
            if ($balance <= 0) {
                // break here, balance must be > 0 to void
                return false;
            }
            $log_code = 'gc_voided';
            $sql .= ' AND balance > 0';
        } else {
            $log_code = 'gc_unvoided';
        }
        DB_query($sql);
        if (!DB_error()) {
            self::writeLog($code, $A['redeemer'], $balance, $log_code);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Expire one or more coupons.
     * If $code is empty, then all coupons with a balance > 0 that have
     * expired are updated.
     *
     * @param   string  $code   Optional code to expire one coupon
     */
    public static function Expire($code='')
    {
        global $_TABLES, $_CONF;

        $sql = "SELECT * FROM {$_TABLES['shop.coupons']} ";
        if ($code == '') {
            $today = $_CONF['_now']->format('Y-m-d', true);
            $sql .= "WHERE balance > 0 AND expires < '$today'";
        } else {
            $code = DB_escapeString($code);
            $sql .= "WHERE balance > 0 AND code =  '$code'";
        }
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $c = DB_escapeString($A['code']);
            $sql1 = "UPDATE {$_TABLES['shop.coupons']}
                SET balance = 0
                WHERE code = '$c';";
            DB_query($sql1);
            self::writeLog($c, $A['redeemer'], $A['balance'], 'gc_expired');
        }
        if (DB_numRows($res) > 0) {
            // If there were any updates, clear the coupon cache
            Cache::clear('coupons');
        }
    }


    /**
     * Get the link to redeem a coupon code.
     * The link is to the redemption form if no code is provided.
     *
     * @param   string  $code   Coupon Code
     * @return  string      URL to redeem the code
     */
    public static function redemptionUrl($code = '')
    {
        $url = SHOP_URL . '/coupon.php?mode=redeem';
        if ($code !== '') {
            $url .= '&id=' . $code;
        }
        return COM_buildUrl($url);
    }


    /**
     * Purge all coupons and transactions from the database.
     * No safety check or confirmation is done; that should be done before
     * calling this function.
     */
    public static function Purge()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['shop.coupons']}");
        DB_query("TRUNCATE {$_TABLES['shop.coupon_log']}");
    }


    /**
     * Get the text string and value for special fields.
     * Used when displaying cart info.
     * Overrides parent function to exclude the custom message field.
     *
     * @param   array   $values     Special field values
     * @return  array       Array of text=>value
     */
    public function getSpecialFields($values = array())
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
    public static function adminList($cat_id=0)
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
    public function canApplyDiscountCode()
    {
        return false;
    }

}

?>
