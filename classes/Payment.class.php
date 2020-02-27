<?php
/**
 * Payment class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Handle payment recording.
 * @package shop
 */
class Payment
{
    /** Payment record ID in the database.
     * @var integer */
    private $pmt_id = 0;

    /** Transaction reference ID provided by the payment gateway.
     * @var string */
    private $ref_id = '';

    /** Timestamp for when the payment notification was received.
     * @var integer */
    private $ts = 0;

    /** Gross amount of the payment.
     * @var float */
    private $amount = 0;

    /** Payment Gateway ID.
     * @var string */
    private $gw_id = '';

    /** Order ID.
     * @var string */
    private $order_id = '';

    /** Entering user ID, for manually-entered payments.
     * Zero indicates payment by IPN or webhook
     * @var integer */
    private $uid = 0;

    /** Payment method.
     * @var string */
    private $method = '';

    /** Comment made with the payment.
     * @var string */
    private $comment = '';


    /**
     * Set internal variables from a data array.
     *
     * @param   array|null  $A  Optional data array
     */
    public function __construct($A=NULL)
    {
        $pmt_id = isset($A['pmt_id']) ? $A['pmt_id'] : 0;
        if (is_array($A)) {
            $this->setPmtID($pmt_id)
                ->setRefID($A['pmt_ref_id'])
                ->setAmount($A['pmt_amount'])
                ->setTS($A['pmt_ts'])
                ->setGateway($A['pmt_gateway'])
                ->setOrderID($A['pmt_order_id'])
                ->setComment($A['pmt_comment'])
                ->setMethod($A['pmt_method'])
                ->setUid($A['uid']);
        } else {
            $this->ts = time();
        }
    }


    /**
     * Get a payment record from the database by record ID.
     *
     * @param   integer $id     DB record ID
     * @return  object      Payment object
     */
    public static function getInstance($id)
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['shop.payments']}
            WHERE pmt_id = " . (int)$id;
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, true);
        } else {
            $A = NULL;
        }
        return new self($A);
    }


    /**
     * Set the payment record ID.
     *
     * @param   integer $id     Payment ID
     * @return  object  $this
     */
    public function setPmtID($id)
    {
        $this->pmt_id = (int)$id;
        return $this;
    }


    /**
     * Accessor function to set the Reference ID.
     *
     * @param   string  $ref_id     Reference ID
     * @return  object  $this
     */
    public function setRefID($ref_id = '')
    {
        if ($ref_id == '') {
            // create a dummy reference, used for non-IPN payments,
            // e.g. gift cards and manual entries.
            $ref_id = uniqid();
        }
        $this->ref_id = $ref_id;
        return $this;
    }


    /**
     * Accessor function to set the Timestamp.
     *
     * @param   integer $ts     Timestamp
     * @return  object  $this
     */
    public function setTS($ts)
    {
        $this->ts = (int)$ts;
        return $this;
    }


    /**
     * Accessor function to set the Payment Amount.
     *
     * @param   float   $amount     Payment amount
     * @return  object  $this
     */
    public function setAmount($amount)
    {
        $this->amount = (float)$amount;
        return $this;
    }


    /**
     * Accessor function to set the Gateway ID.
     *
     * @param   string  $gw_id      Gateway ID
     * @return  object  $this
     */
    public function setGateway($gw_id)
    {
        $this->gw_id = $gw_id;
        return $this;
    }


    /**
     * Accessor function to set the Order ID.
     *
     * @param   string  $order_id   Order ID
     * @return  object  $this
     */
    public function setOrderID($order_id)
    {
        $this->order_id = $order_id;
        return $this;
    }


    /**
     * Set the payment method text.
     *
     * @param   string  $method     Payment method or gateway name
     * @return  object  $this
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }


    /**
     * Get the payment method.
     *
     * @return  string      Payment method
     */
    public function getMethod()
    {
        return $this->method;
    }


    /**
     * Get the display payment method.
     * This will be the gateway, if any, or the pmt_method field for manual
     * entries.
     *
     * @return  string      Payment method for display
     */
    public function getDisplayMethod()
    {
        return empty($this->method) ? $this->gw_id : $this->method;
    }


    /**
     * Set the submitting user ID.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    private function setUid($uid)
    {
        $this->uid = (int)$uid;
        return $this;
    }


    /**
     * Set the comment string.
     *
     * @param   string  $comment    Comment text
     * @return  object  $this
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }


    /**
     * Get the comment text for the payment.
     *
     * @return  string      Comment
     */
    public function getComment()
    {
        return $this->comment;
    }


    /**
     * Get the payment record ID.
     *
     * @return  integer     DB record ID for the payment
     */
    public function getPmtID()
    {
        return (int)$this->pmt_id;
    }


    /**
     * Accessor function to get the Reference ID.
     *
     * @return  string      Reference ID
     */
    public function getRefID()
    {
        return $this->ref_id;
    }


    /**
     * Accessor function to get the Gateway ID.
     *
     * @return  string      Gateway ID
     */
    public function getGateway()
    {
        return $this->gw_id;
    }


    /**
     * Accessor function to get the Order ID.
     *
     * @return  string      Order ID
     */
    public function getOrderID()
    {
        return $this->order_id;
    }


    /**
     * Accessor function to get the Payment Amount.
     *
     * @return  float       Payment Amount.
     */
    public function getAmount()
    {
        return $this->amount;
    }


    /**
     * Accessor function to get the Timestamp.
     *
     * @return  integer     Timestamp value
     */
    public function getTS()
    {
        return (int)$this->ts;
    }


    /**
     * Accessor function to get the Payment Date.
     *
     * @return  object      Date object
     */
    public function getDt()
    {
        global $_CONF;
        return new \Date($this->ts, $_CONF['timezone']);
    }


    /**
     * Save the payment object to the database.
     *
     * @return  object  $this
     */
    public function Save()
    {
        global $_TABLES;

        $sql = "INSERT INTO {$_TABLES['shop.payments']} SET
            pmt_ts = {$this->getTS()},
            pmt_gateway = '" . DB_escapeString($this->getGateway()) . "',
            pmt_amount = '" . $this->getAmount() . "',
            pmt_ref_id = '" . DB_escapeString($this->getRefID()) . "',
            pmt_order_id = '" . DB_escapeString($this->getOrderID()) . "',
            pmt_method = '" . DB_escapeString($this->method) . "',
            pmt_comment = '" . DB_escapeString($this->comment) . "',
            pmt_uid = " . (int)$this->uid;
        //echo $sql;die;
        $res = DB_query($sql);
        if (!DB_error()) {
            $this->setPmtId(DB_insertID());
        }
        return $this;
    }


    /**
     * Migrate payment information from the IPN log to the Payments table.
     * This is done during upgrade to v1.3.0.
     */
    public static function loadFromIPN()
    {
        global $_TABLES, $LANG_SHOP;

        $sql = "SELECT * FROM {$_TABLES['shop.ipnlog']}
            ORDER BY ts ASC";
            //WHERE id = 860
        $res = DB_query($sql);
        $Pmt = new self;
        $done = array();        // Avoid duplicates
        while ($A = DB_fetchArray($res, false)) {
            $ipn_data = @unserialize($A['ipn_data']);
            if (empty($A['gateway']) || empty($ipn_data)) {
                continue;
            }
            $cls = 'Shop\\ipn\\' . $A['gateway'];
            if (!class_exists($cls)) {
                continue;
            }
            $ipn = new $cls($ipn_data);
            if (isset($ipn_data['pmt_gross']) && $ipn_data['pmt_gross'] > 0) {
                $pmt_gross = $ipn_data['pmt_gross'];
            } else {
                $pmt_gross = $ipn->getPmtGross();
            }
            if ($pmt_gross < .01) {
                continue;
            }

            if (!empty($A['order_id'])) {
                $order_id = $A['order_id'];
            } elseif ($ipn->getOrderId() != '') {
                $order_id = $ipn->getOrderID();
            } elseif ($ipn->getTxnId() != '') {
                $order_id = DB_getItem(
                    $_TABLES['shop.orders'],
                    'order_id',
                    "pmt_txn_id = '" . DB_escapeString($ipn->getTxnId()) . "'"
                );
            } else {
                $order_id = '';
            }
            if (!array_key_exists($A['txn_id'], $done)) {
                $Pmt->setRefID($A['txn_id'])
                    ->setAmount($pmt_gross)
                    ->setTS($A['ts'])
                    ->setGateway($A['gateway'])
                    ->setMethod($ipn->getGW()->getDisplayName())
                    ->setOrderID($order_id)
                    ->setComment('Imported from IPN log')
                    ->Save();
                $done[$Pmt->getRefId()] = 'done';
            }
        }

        // Get all the "payments" via coupons.
        $sql = "SELECT * FROM {$_TABLES['shop.coupon_log']}
            WHERE msg = 'gc_applied'";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $Pmt = new self;
            $Pmt->setRefID(uniqid())
                ->setAmount($A['amount'])
                ->setTS($A['ts'])
                ->setGateway('coupon')
                ->setMethod('Apply Coupon')
                ->setComment($LANG_SHOP['gc_pmt_comment'])
                ->setOrderID($A['order_id'])
                ->Save();
        }

        // Now get all the orders that are marked "paid" and make sure there's
        // a payment record for each.
        $sql = "SELECT order_id, order_total, by_gc
            FROM {$_TABLES['shop.orders']}
            WHERE status IN ('paid','shipped','complete','processing')";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $total_paid = (float)DB_getItem(
                $_TABLES['shop.payments'],
                'SuM(pmt_amount)',
                "pmt_order_id = '" . DB_escapeString($A['order_id']) . "'"
            );
            $fill_amt = (float)$A['order_total'] - (float)$A['by_gc'] - $total_paid;
            if ($fill_amt > 0) {
                $Pmt = new self;
                $Pmt->setRefID($A['order_id'] . '-' . uniqid())
                    ->setAmount($fill_amt)
                    ->setTS(time())
                    ->setGateway('system')
                    ->setOrderID($A['order_id'])
                    ->setComment('Added by system to match paid order')
                    ->Save();
            }
        }
    }


    /**
     * Get all the payment objects for a specific order.
     *
     * @param   string  $order_id   Order ID
     * @return  array       Array of Payment objects
     */
    public static function getByOrder($order_id)
    {
        global $_TABLES;
        static $P = array();

        if (isset($P[$order_id])) {
            return $P[$order_id];
        }

        $P[$order_id] = array();
        $order_id = DB_escapeString($order_id);
        $sql = "SELECT * FROM {$_TABLES['shop.payments']}
            WHERE pmt_order_id = '$order_id'
            ORDER BY pmt_ts ASC";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $P[$order_id][] = new self($A);
        }
        return $P[$order_id];
    }


    /**
     * Purge all payments from the database.
     * No safety check or confirmation is done; that should be done before
     * calling this function.
     */
    public static function Purge()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['shop.payments']}");
    }


    /**
     * Show an admin list of payments.
     *
     * @param   string  $order_id   Order ID to limit listing
     * @return  string      HTML for payment list
     */
    public static function adminList($order_id='')
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN,
            $LANG32;
            $sql = "SELECT pmt.* FROM {$_TABLES['shop.payments']} pmt";

        $header_arr = array(
            /*array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => '',
                'field' => 'action',
                'sort'  => false,
            ),*/
            array(
                'text'  => 'ID',
                'field' => 'pmt_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['order'],
                'field' => 'pmt_order_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['datetime'],
                'field' => 'pmt_ts',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['gateway'],
                'field' => 'pmt_gateway',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['pmt_method'],
                'field' => 'pmt_method',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['comment'],
                'field' => 'pmt_comment',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['amount'],
                'field' => 'pmt_amount',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort'  => 'false',
                'align' => 'center',
            ),
        );
        $extra = array();
        $defsort_arr = array(
            'field' => 'pmt_ts',
            'direction' => 'DESC',
        );

        if ($order_id != 'x') {
            $filter = "WHERE pmt.pmt_order_id = '" . DB_escapeString($order_id) . "'";
            $title = $LANG_SHOP['order'] . ' ' . $order_id;
        } else {
            $filter = '';
            $title = '';
        }

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $options = array(
            'chkselect' => 'true',
            'chkname'   => 'payments',
            'chkfield'  => 'pmt_id',
            //'chkactions' => $prt_pl,
        );
        $query_arr = array(
            'table' => 'shop.payments',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => $filter,
        );
        $text_arr = array(
            'has_extras' => false,
            'has_limit' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?ord_pmts=x',
        );

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_payments',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', $extra, $options, ''
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
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;
        static $Cur = NULL;

        if ($Cur === NULL) {
            $Cur = Currency::getInstance();
        }
        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array('title'=>$LANG_ADMIN['edit'])),
                SHOP_ADMIN_URL . "/index.php?editshipment={$A['shipment_id']}"
            );
            break;

        case 'pmt_order_id':
            $retval .= COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/index.php?order=' . $fieldvalue
            );
            break;
        case 'pmt_amount':
            $retval = $Cur->FormatValue($fieldvalue);
            break;

        case 'pmt_ts':
            $D = new \Date($fieldvalue, $_CONF['timezone']);
            $retval = $D->toMySQL(true);
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }

}

?>
