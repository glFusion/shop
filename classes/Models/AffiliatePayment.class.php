<?php
/**
 * Class to handle affiliate payments.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;
use Shop\Currency;
use Shop\Config;
use Shop\Customer;


/**
 * Class for affiliate payouts.
 * @package shop
 */
class AffiliatePayment
{
    /** Record ID.
     * @var integer */
    private $aff_pmt_id = 0;

    /** User ID.
     * @var integer */
    private $aff_pmt_uid = 0;

    /** Date of the affiliate sale.
     * @var object */
    private $PmtDate = NULL;

    /** Total order amount subject to affiliate rewards.
     * @var float */
    private $aff_pmt_amount = 0;

    /** Payment method (_coupon, paypal, etc.).
     * @var string */
    private $aff_pmt_method = '';


    /**
     * Load the object from a DB record if a record ID is supplied.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct($id = 0)
    {
        if ($id > 0) {
            $this->aff_pmt_id = (int)$id;
            $sql = "SELECT * FROM {$_TABLES['shop.affiliate_payments']}
                WHERE aff_pmt_id = {$this->aff_pmt_id}";
            $res = DB_query($sql);
            if (DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
                $this->setVars($A);
            }
        }
    }


    /**
     * Set the property values from a DB record array.
     *
     * @param   array   $A      Array of values from the database
     * @return  object  $this
     */
    public function setVars($A)
    {
        $this->withPmtId($A['aff_pmt_id']);
        $this->withUid($A['aff_pmt_uid']);
        $this->withOrderId($A['aff_order_id']);
        $this->withPmtAmount($A['aff_pmt_amount']);
        $this->withPmtDate($A['aff_pmt_date']);
        $this->withPmtMethod($A['aff_pmt_method']);
        return $this;
    }


    /**
     * Set the affiliate's user ID.
     *
     * @param   integer $uid        Affiliate user ID
     * @return  object  $this
     */
    public function withUid($uid)
    {
        $this->aff_pmt_uid = (int)$uid;
        return $this;
    }


    public function getUid()
    {
        return (int)$this->aff_pmt_uid;
    }


    /**
     * Set the payment date, default = now.
     *
     * @param   string  $dt_str     Date string, e.g. MySQL datetime
     * @return  object  $this
     */
    public function withPmtDate($dt_str=NULL)
    {
        global $_CONF;

        if ($dt_str === NULL) {
            $this->PmtDate = clone $_CONF['_now'];
        } else {
            $this->PmtDate = new \Date($dt_str);
            $this->PmtDate->setTimezone(new \DateTimezone($_CONF['timezone']));
        }
        return $this;
    }


    /**
     * Set the total payment amount.
     *
     * @param   float   $total      Payment amount
     * @return  object  $this
     */
    public function withPmtAmount($total)
    {
        $this->aff_pmt_amount = (float)$total;
        return $this;
    }


    public function getPmtAmount()
    {
        return (float)$this->aff_pmt_amount;
    }


    /**
     * Set the payment method.
     *
     * @param   string  $method     Method (e.g. Gateway) name
     * @return  object  $this
     */
    public function withPmtMethod($method)
    {
        $this->aff_pmt_method = $method;
        return $this;
    }


    /**
     * Set the payment record ID.
     *
     * @param   integer $id     Payment record ID
     * @return  object  $this
     */
    public function withPmtId($id)
    {
        $this->aff_pmt_id = (int)$id;
        return $this;
    }


    /**
     * Get the payment record ID.
     *
     * @return  integer     DB record ID for this payment
     */
    public function getPmtId()
    {
        return (int)$this->aff_pmt_id;
    }


    /**
     * Generate affiliate payments.
     * Collects all outstanding AffiliateSale records grouped by affiliate ID
     * and creates a single total AffiliatePayment record. Then the payment
     * record ID is stored with the AffiliateSale to mark it as processed.
     */
    public static function generate($uids=array())
    {
        global $_TABLES;

        // Collect outstanding affiliate sales grouped by affiliate ID

        $max_date = new \Date('now');
        $max_date->sub(new \DateInterval('P' . Config::get('aff_delay_days') . 'D'));

        $min_pmt = (float)Config::get('aff_min_payment');
        $statuses = OrderState::allAtLeast(Config::get('aff_min_ordstatus'));
        if (!empty($statuses)) {
            $statuses = "'" . implode("','", array_keys($statuses)) . "'";
            $status_where = " AND o.status IN ($statuses)";
        } else {
            $status_where = '';
        }
        if (!empty($uids)) {
            if (is_array($uids)) {
                $uids = implode(',', $uids);
                $uid_where = " AND aff_sale_uid IN ($uids) ";
            } else {
                $uid_where = " AND aff_sale_uid = $uids ";
            }
        } else {
            $uid_where = '';
        }
        $sql = "SELECT s.aff_sale_id, s.aff_sale_uid,
            sum(si.aff_item_pmt) as pmt_total
            FROM {$_TABLES['shop.affiliate_sales']} s
            LEFT JOIN {$_TABLES['shop.affiliate_saleitems']} si
                ON si.aff_sale_id = s.aff_sale_id
            LEFT JOIN {$_TABLES['shop.orders']} o
                ON o.order_id = s.aff_order_id
            WHERE s.aff_pmt_id = 0
            $uid_where
            $status_where
            AND s.aff_sale_date <= '$max_date'
            GROUP BY s.aff_sale_id
            ORDER BY s.aff_sale_uid ASC";
        //echo $sql;die;
        $res = DB_query($sql);
        $cbrk = -1;
        $pmt_total = 0;
        $sale_ids = array();
        while ($A = DB_fetchArray($res, false)) {
            if ($A['aff_sale_uid'] != $cbrk) {
                // Control-break reached. If this is not the first time through
                // the loop, create the payment for the current affiliate
                // and reset the counters.
                if ($cbrk > 0) {
                    if ($pmt_total >= $min_pmt) {
                        $method = Customer::getInstance($cbrk)->getAffPayoutMethod();
                        $Pmt = new self;
                        $Pmt->withUid($cbrk)
                            ->withPmtAmount($pmt_total)
                            ->withPmtDate()
                            ->withPmtMethod($method)
                            ->Save();
                        AffiliateSale::updatePmtId($Pmt->getPmtId(), $sale_ids);
                    }
                }
                $cbrk = $A['aff_sale_uid'];
                $pmt_total = 0;
                $sale_ids = array();
            }
            $sale_ids[] = $A['aff_sale_id'];
            $pmt_total += (float)$A['pmt_total'];
        }
        // Dropped out of the loop, ensure that the final affiliate is paid.
        if ($cbrk > 0) {
            if ($pmt_total >= $min_pmt) {
                $method = Customer::getInstance($cbrk)->getAffPayoutMethod();
                $Pmt = new self;
                $Pmt->withUid($cbrk)
                    ->withPmtAmount($pmt_total)
                    ->withPmtDate()
                    ->withPmtMethod($method)
                    ->Save();
                AffiliateSale::updatePmtId($Pmt->getPmtId(), $sale_ids);
            }

        }
    }


    /**
     * Save the current Payment object to the database.
     
     * @return  boolean     True on success, False on failure
     */
    public function Save()
    {
        global $_TABLES;

        if ($this->aff_pmt_id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['shop.affiliate_payments']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.affiliate_payments']} SET ";
            $sql3 = " WHERE aff_pmt_id = {$this->aff_pmt_id}";
        }
        $sql2 = "aff_pmt_uid = {$this->aff_pmt_uid},
            aff_pmt_amount = {$this->aff_pmt_amount},
            aff_pmt_date = '" . $this->PmtDate->toMySQL() . "',
            aff_pmt_method = '" . DB_escapeString($this->aff_pmt_method) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        $res = DB_query($sql, 1);
        if ($this->aff_pmt_id == 0) {
            $this->withPmtId(DB_insertID());
        }
        return DB_error() ? false : true;
    }


    public static function process()
    {
        global $_TABLES;

        $sql = "SELECT p.*, u.fullname, u.email
            FROM {$_TABLES['shop.affiliate_payments']} p
            LEFT JOIN {$_TABLES['users']} u
                ON u.uid = p.aff_pmt_uid
            WHERE aff_pmt_txn_id = ''
            ORDER BY aff_pmt_method ASC";
        //echo $sql;die;
        $res = DB_query($sql);
        $cbrk = '';
        $Payouts = array();
        while ($A = DB_fetchArray($res, false)) {
            if ($A['aff_pmt_method'] != $cbrk) {
                // Control-break reached. If this is not the first time through
                // the loop, create the payment for the current affiliate
                // and reset the counters.
                if ($cbrk > 0) {
                    $GW = \Shop\Gateway::getInstance($cbrk);
                    if ($GW->getName() != '') {     // gateway is valid
                        $GW->sendPayouts($Payouts);
                    }
                }
                $cbrk = $A['aff_pmt_method'];
                $Payouts = array();
            }
            $Payout = new Payout(array(
                'type' => 'affiliate',
                'uid' => $A['aff_pmt_uid'],
                'email' => $A['email'],
                'fullname' => $A['fullname'],
                'amount' => (float)$A['aff_pmt_amount'],
                'method' => $A['aff_pmt_method'],
                'parent_id' => $A['aff_pmt_id'],
                'message' => 'Affiliate referral payment',
                'currency' => Config::get('currency'),
            ));
            $Payouts[$A['aff_pmt_id']] = $Payout;
        }
        if (!empty($Payouts)) {
            $amount_str = Currency::getInstance(Config::get('currency'))
                ->Format($Payout['amount'], true);
            $PayoutHeader = new PayoutHeader(array(
                'email_subject' => 'Your Affiliate Payment',
                'email_message' => 'Your affiliate payment of ' . $amount_str . ' has been paid out.',
            ) );
            // Dropped out of the loop, ensure that the final payout batch is sent.
            if ($cbrk != '') {
                $GW = \Shop\Gateway::getInstance($cbrk);
                if ($GW->getName() != '') {     // gateway is valid
                    $GW->sendPayouts($PayoutHeader, $Payouts);
                }
            }
            foreach ($Payouts as $id=>$Payout) {
                if ($Payout['txn_id'] != '') {
                    DB_query("UPDATE {$_TABLES['shop.affiliate_payments']}
                        SET aff_pmt_txn_id = '" . DB_escapeString($Payout['txn_id']) . "'
                        WHERE aff_pmt_id = $id");
                }
            }
        }
    }

}
