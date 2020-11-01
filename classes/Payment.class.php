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
use Shop\Models\OrderState;


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

    /** Flag to indicate whether this is a money payment, or other credit type.
     * @var boolean */
    private $is_money = 1;


    /**
     * Set internal variables from a data array.
     *
     * @param   array|null  $A  Optional data array
     */
    public function __construct($A=NULL)
    {
        if (is_array($A)) {
            $pmt_id = isset($A['pmt_id']) ? $A['pmt_id'] : 0;
            $this->setPmtID($pmt_id)
                ->setRefID($A['pmt_ref_id'])
                ->setAmount($A['pmt_amount'])
                ->setTS($A['pmt_ts'])
                ->setIsMoney($A['is_money'])
                ->setGateway($A['pmt_gateway'])
                ->setOrderID($A['pmt_order_id'])
                ->setComment($A['pmt_comment'])
                ->setMethod($A['pmt_method'])
                ->setUid($A['pmt_uid']);
        } else {
            $this->ts = time();
            $this->ref_id = COM_makeSid() . rand(100,999);
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
     * Get a payment record from the database by reference ID.
     *
     * @param   string  $ref_id Paymetn reference ID
     * @return  object      Payment object
     */
    public static function getByReference($ref_id)
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['shop.payments']}
            WHERE pmt_ref_id = '" . DB_escapeString($ref_id) . "'";
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
     * Set the `is_money` flag value.
     *
     * @param   integer $flag   1 if payment is money, 0 for other credit
     * @return  object  $this
     */
    public function setIsMoney($flag)
    {
        $this->is_money = $flag ? 1 : 0;
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
    public function setUid($uid)
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
        return (float)$this->amount;
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
     * Check if this payment is money or other credit.
     *
     * @return  integer     1 for a money payment 0 for other credit
     */
    public function isMoney()
    {
        return $this->is_money ? 1 : 0;
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
     * @param   boolean $notify_buyer   True to notify the buyer
     * @return  object  $this
     */
    public function Save($notify_buyer=true)
    {
        global $_TABLES, $LANG_SHOP;

        $sql = "INSERT INTO {$_TABLES['shop.payments']} SET
            pmt_ts = {$this->getTS()},
            is_money = {$this->isMoney()},
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
            $Order = Order::getInstance($this->getOrderID());
            $Order->updatePmtStatus()
                ->Log(
                    sprintf($LANG_SHOP['amt_paid_gw'],
                        $this->getAmount(),
                        $this->getGateway()
                    )
                );
            $Order->Notify(
                OrderState::PAID,
                $LANG_SHOP['notify_pmt_received'],
                true,
                false
            );
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
                    ->setIsMoney(true)
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
                ->setIsMoney(false)
                ->setGateway('_coupon')
                ->setMethod('Apply Coupon')
                ->setComment($LANG_SHOP['gc_pmt_comment'])
                ->setOrderID($A['order_id'])
                ->Save();
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
     * Create a form to enter payments manually.
     *
     * @return  string      HTML for payment form
     */
    public function pmtForm()
    {
        global $_TABLES;

        //$Orders = Order::getUnpaid();
        $Order = Order::getInstance($this->order_id);
        $bal_due = $Order->getBalanceDue();
        $T = new Template;
        $T->set_file('form', 'pmt_form.thtml');
        $T->set_var(array(
            'user_select' => COM_optionList(
                $_TABLES['users'],
                'uid,fullname',
                0,
                1
            ),
            'pmt_id' => $this->pmt_id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'ref_id' => $this->ref_id,
            'money_chk' => $this->is_money ? 'checked="checked"' : '',
            'bal_due' => Currency::getInstance($Order->getCurrency()->getCode())->formatMoney($bal_due),
        ) );
        $Gateways = Gateway::getAll();
        $T->set_block('form', 'GatewayOpts', 'gwo');
        foreach ($Gateways as $GW) {
            $T->set_var(array(
                'gw_id' => $GW->getName(),
                'gw_dscp' => $GW->getDscp(),
                'selected' => $GW->getName() == $this->gw_id ? 'selected="selected"' : '',
            ) );
            $T->parse('gwo', 'GatewayOpts', true);
        }
        $T->parse('output', 'form');
        $form = $T->finish($T->get_var('output'));
        return $form;
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
     * Leverages the payment report module.
     *
     * @param   string  $order_id   Optional Order ID to limit listing
     * @return  string      HTML for payment list
     */
    public static function adminList($order_id='')
    {
        global $LANG_SHOP;

        $R = \Shop\Report::getInstance('payment');
        if ($R === NULL) {
            return '';
        }
        if (!empty($order_id)) {
            $R->setParam('order_id', $order_id);
        }
        if ($order_id != 'x') {
            $new_btn = COM_createLink(
                $LANG_SHOP['add_payment'],
                SHOP_ADMIN_URL . '/index.php?newpayment=' . $order_id,
                array(
                    'class' => 'uk-button uk-button-success',
                )
            );
        } else {
            $new_btn = '';
        }
        $R->setAdmin(true)
            // Params usually from GET but could be POSTed
            ->setParams($_REQUEST)
            ->setShowHeader(false);
        return $new_btn . $R->Render();
    }

}

?>
