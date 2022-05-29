<?php
/**
 * Payment class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Models\OrderState;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Handle payment recording.
 * @package shop
 */
class Payment
{
    const UNVERIFIED = 0;
    const VERIFIED = 1;

    /** Payment record ID in the database.
     * @var integer */
    private $pmt_id = 0;

    /** Payment reference ID provided by the payment gateway.
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

    /** Flag to indicate the payment status.
     * @var integer */
    private $is_complete = 1;   // assume completed, for backwards compatibility

    /** Payment status from latest webhook.
     * @var status */
    private $status = '';

    /** Order object related to this payment.
     * This is to easily retrieve the order object without having to
     * recreate it, since an order object is updated in Save() anyway.
     * @var object */
    private $Order = NULL;


    /**
     * Set internal variables from a data array.
     *
     * @param   array|null  $A  Optional data array
     */
    public function __construct(?array $A=NULL)
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
                 ->setStatus($A['pmt_status'])
                 ->setComplete($A['is_complete'])
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
    public static function getInstance(int $id) : self
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.payments']} WHERE pmt_id = ?",
                array($id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        return new self($data);
    }


    /**
     * Get a payment record from the database by reference ID.
     *
     * @param   string  $ref_id Paymetn reference ID
     * @return  object      Payment object
     */
    public static function getByReference(string $ref_id) : self
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.payments']} WHERE pmt_ref_id = ?",
                array($ref_id),
                array(Database::STRING)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = NULL;
        }
        return new self($data);
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
    public function Save($notify_buyer=true) : self
    {
        global $_TABLES, $LANG_SHOP;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        if ($this->pmt_id == 0) {
            $qb->insert($_TABLES['shop.payments'])
               ->setValue('pmt_ts', ':pmt_ts')
               ->setValue('is_money', ':is_money')
               ->setValue('pmt_gateway', ':pmt_gateway')
               ->setValue('pmt_amount', ':pmt_amount')
               ->setValue('pmt_ref_id', ':pmt_ref_id')
               ->setValue('pmt_order_id', ':pmt_order_id')
               ->setValue('pmt_method', ':pmt_method')
               ->setValue('pmt_comment', ':pmt_comment')
               ->setValue('pmt_status', ':pmt_status')
               ->setValue('is_complete', ':is_complete')
               ->setValue('pmt_uid', ':pmt_uid');
        } else {
            $qb->update($_TABLES['shop.payments'])
               ->set('pmt_ts', ':pmt_ts')
               ->set('is_money', ':is_money')
               ->set('pmt_gateway', ':pmt_gateway')
               ->set('pmt_amount', ':pmt_amount')
               ->set('pmt_ref_id', ':pmt_ref_id')
               ->set('pmt_order_id', ':pmt_order_id')
               ->set('pmt_method', ':pmt_method')
               ->set('pmt_comment', ':pmt_comment')
               ->set('pmt_status', ':pmt_status')
               ->set('is_complete', ':is_complete')
               ->set('pmt_uid', ':pmt_uid')
               ->where('pmt_id = :pmt_id')
               ->setParameter('pmt_id', $this->pmt_id);
        }
        $qb->setParameter('pmt_ts', $this->getTS(), Database::INTEGER)
           ->setParameter('is_money', $this->isMoney(), Database::INTEGER)
           ->setParameter('pmt_gateway', $this->getGateway(), Database::STRING)
           ->setParameter('pmt_amount', $this->getAmount(), Database::STRING)
           ->setParameter('pmt_ref_id', $this->getRefID(), Database::STRING)
           ->setParameter('pmt_order_id', $this->getOrderID(), Database::STRING)
           ->setParameter('pmt_method', $this->method, Database::STRING)
           ->setParameter('pmt_comment', $this->comment, Database::STRING)
           ->setParameter('pmt_status', $this->getStatus(), Database::STRING)
           ->setParameter('is_complete', $this->isComplete(), Database::INTEGER)
           ->setParameter('pmt_uid', $this->uid, Database::INTEGER);
        try {
            $qb->execute();
            $stat = true;
            if ($this->pmt_id == 0) {
                $this->setPmtId($db->conn->lastInsertId());
            }
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stat = false;
        }

        if ($stat && $this->isComplete()) {
            $lang_str = $this->getAmount() < 0 ? $LANG_SHOP['amt_credit_gw'] : $LANG_SHOP['amt_paid_gw'];
            $this->Order = Order::getInstance($this->getOrderID());
            $this->Order->updatePmtStatus();
            $this->Order->Log(
                sprintf(
                    $lang_str,
                    $this->getAmount(),
                    $this->getGateway()
                )
            );
        }
        return $this;
    }


    /**
     * Migrate payment information from the IPN log to the Payments table.
     * This is done during upgrade to v1.3.0.
     */
    public static function loadFromIPN() : void
    {
        global $_TABLES, $LANG_SHOP;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.ipnlog']} ORDER BY ts ASC"
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            $done = array();        // Avoid duplicates
            foreach ($data as $A) {
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
                    $order_id = $db->getItem(
                        $_TABLES['shop.orders'],
                        'order_id',
                        array('pmt_txn_id', $ipn->getTxnId())
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
        }

        // Get all the "payments" via coupons.
        try {
            $pmts = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.coupon_log']} WHERE msg = 'gc_applied'"
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $pmts = false;
        }
        if (is_array($pmts)) {
            foreach ($pmts as $A) {
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
    }


    /**
     * Get all the payment objects for a specific order.
     *
     * @param   string  $order_id   Order ID
     * @param   integer $is_complete    True to get only completed pmts
     * @return  array       Array of Payment objects
     */
    public static function getByOrder($order_id, $is_complete=1) : array
    {
        global $_TABLES;
        /*static $P = array();

        if (isset($P[$order_id])) {
            return $P[$order_id];
        }*/
        $retval = array();

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        $P[$order_id] = array();

        $qb->select('*')
           ->from($_TABLES['shop.payments'])
           ->where('pmt_order_id = :pmt_order_id')
           ->setParameter('pmt_order_id', $order_id, Database::STRING)
           ->orderBy('pmt_ts', 'ASC');
        if ($is_complete) {
            $qb->andWhere('is_complete = 1');
        }
        try {
            $data = $qb->execute()->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                //$P[$order_id][] = new self($A);
                $retval[] = new self($A);
            }
        }
        //return $P[$order_id];
        return $retval;
    }


    /**
     * Get the order object after saving.
     * This saves the caller from having to read the order data yet again.
     *
     * @return  object      Order object, NULL if not instantiated
     */
    public function getOrder() : ?Order
    {
        return $this->Order;
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
            'amount' => $this->amount > 0 ? $this->amount : '',
            'ref_id' => $this->ref_id,
            'money_chk' => $this->is_money ? 'checked="checked"' : '',
            'bal_due' => Currency::getInstance($Order->getCurrency()->getCode())->formatMoney($bal_due),
        ) );
        $Gateways = Gateway::getEnabled();
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
     * Delete a single payment.
     *
     * @param   integer $pmt_id     Payment record ID
     */
    public static function delete(int $pmt_id) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['shop.payments'],
                array('pmt_id' => $pmt_id),
                array(Database::INTEGER)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Purge all payments from the database.
     * No safety check or confirmation is done; that should be done before
     * calling this function.
     */
    public static function Purge() : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeUpdate("TRUNCATE {$_TABLES['shop.payments']}");
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Show an admin list of payments.
     * Leverages the payment report module.
     *
     * @param   string  $order_id   Optional Order ID to limit listing
     * @return  string      HTML for payment list
     */
    public static function adminList(?string $order_id=NULL) : string
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
            $new_btn = FieldList::buttonLink(array(
                'text' => $LANG_SHOP['add_payment'],
                'url' => SHOP_ADMIN_URL . '/payments.php?newpayment=' . $order_id,
                'style' => 'success',
            ) );
        } else {
            $new_btn = '';
        }
        $R->setAdmin(true)
            // Params usually from GET but could be POSTed
            ->setParams($_REQUEST)
            ->setShowHeader(false);
        return $new_btn . $R->Render();
    }


    /**
     * Get the URL to a single payment detail record.
     *
     * @param   integer $pmt_id     Payment record ID
     * @return  string      URL to detail display
     */
    public static function getDetailUrl($pmt_id)
    {
        return SHOP_ADMIN_URL . '/payments.php?pmtdetail=x&pmt_id=' . $pmt_id;
    }


    /**
     * Set the payment complete flag.
     * True indicates that the payment has been confirmed complete by the
     * gateway.
     *
     * @param   integer $status     1 if complete, 0 if pending
     * @eturn   object  $this
     */
    public function setComplete($status)
    {
        $this->is_complete = (int)$status;
        return $this;
    }


    /**
     * Check if the payment is finalized.
     *
     * @return  integer     1 if complete, 0 if pending
     */
    public function isComplete()
    {
        return (int)$this->is_complete;
    }


    /**
     * Set the payment status string from the gateway.
     *
     * @param   string  $status     Informational status
     * @return  object  $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }


    /**
     * Get the payment status string.
     *
     * @return  string      Informational status info
     */
    public function getStatus()
    {
        return $this->status;
    }

}
