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
use glFusion\Database\Database;
use glFusion\Log\Log;
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
        global $_TABLES;

        if ($id > 0) {
            $this->aff_pmt_id = (int)$id;
            $db = Database::getInstance();
            try {
                $A = $db->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.affiliate_payments']}
                    WHERE aff_pmt_id = ?",
                    array($this->aff_pmt_id),
                    array(Database::INTEGER)
                )->fetchAssociative();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }
            if (is_array($A)) {
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
     *
     * @param   array   $uids   Array of user IDs to process
     */
    public static function generate($uids=array()) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();

        $max_date = new \Date('now');
        $max_date->sub(new \DateInterval('P' . Config::get('aff_delay_days') . 'D'));

        $min_pmt = (float)Config::get('aff_min_payment');
        $statuses = OrderStatus::getAffiliateEligible();
        if (empty($statuses)) {
            Log::write('shop_system', Log::INFO, 'No order statuses are eligible for affiliate payments');
            return false;
        }

        if (!empty($uids)) {
            $qb->andWhere('s.aff_sale_uid IN (:uids)')
               ->setParameter('uids', $uids, Database::PARAM_INT_ARRAY);
        }
        try {
            $stmt = $qb->select('s.aff_sale_id', 's.aff_sale_uid', 'sum(si.aff_item_pmt) as pmt_total')
                       ->from($_TABLES['shop.affiliate_sales'], 's')
                       ->leftJoin('s', $_TABLES['shop.affiliate_saleitems'], 'si', 'si.aff_sale_id = s.aff_sale_id')
                       ->leftJoin('s', $_TABLES['shop.orders'], 'o', 'o.order_id = s.aff_order_id')
                       ->where('s.aff_pmt_id = 0')
                       ->andWhere('s.aff_sale_date <= :max_date')
                       ->andWhere('o.status IN (:statuses)')
                       ->groupBy('s.aff_sale_id')
                       ->orderBy('s.aff_sale_uid', 'ASC')
                       ->setParameter('max_date', $max_date->toMySQL(), Database::STRING)
                       ->setParameter('statuses', array_keys($statuses), Database::PARAM_STR_ARRAY)
                       ->execute();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if (!$stmt) {
            return false;
        }

        $cbrk = -1;
        $pmt_total = 0;
        $sale_ids = array();
        while ($A = $stmt->fetchAssociative()) {
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
        return true;
    }


    /**
     * Save the current Payment object to the database.
     
     * @return  boolean     True on success, False on failure
     */
    public function Save()
    {
        global $_TABLES;

        $db = Database::getInstance();

        if ($this->aff_pmt_id == 0) {
            try {
                $db->conn->insert(
                    $_TABLES['shop.affiliate_payments'],
                    array(
                        'aff_pmt_uid' => $this->aff_pmt_uid,
                        'aff_pmt_amount' => $this->aff_pmt_amount,
                        'aff_pmt_date' => $this->PmtDate->toMySQL(),
                        'aff_pmt_method' => $this->aff_pmt_method,
                    ),
                    array(
                        Database::INTEGER,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                    )
                );
                $this->withPmtId($db->conn->lastInsertId());
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        } else {
            try {
                $db->conn->update(
                    $_TABLES['shop.affiliate_payments'],
                    array(
                        'aff_pmt_uid' => $this->aff_pmt_uid,
                        'aff_pmt_amount' => $this->aff_pmt_amount,
                        'aff_pmt_date' => $this->PmtDate->toMySQL(),
                        'aff_pmt_method' => $this->aff_pmt_method,
                    ),
                    array('aff_pmt_id' => $this->aff_pmt_id),
                    array(
                        Database::INTEGER,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                        Database::INTEGER,
                    )
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }


    /**
     * Actually process all outstanding payments.
     */
    public static function process()
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $stmt = $db->conn->executeQuery(
                "SELECT p.*, u.fullname, u.email
                FROM {$_TABLES['shop.affiliate_payments']} p
                LEFT JOIN {$_TABLES['users']} u
                    ON u.uid = p.aff_pmt_uid
                WHERE aff_pmt_txn_id = ''
                ORDER BY aff_pmt_method ASC"
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            return;
        }

        $res = DB_query($sql);
        $cbrk = 0;
        $Payouts = array();
        while ($A = $stmt->fetchAssociative()) {
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
        if (!empty($Payouts) && $cbrk > 0) {
            // Dropped out of the loop, ensure that the final payout batch is sent.
            $GW = \Shop\Gateway::getInstance($cbrk);
            if ($GW->getName() != '') {     // gateway is valid
                $GW->sendPayouts($Payouts);
            }
        }

        // Now update the affiliate payment table to note the transaction ID.
        foreach ($Payouts as $id=>$Payout) {
            if ($Payout['txn_id'] != '') {
                try {
                    $db->conn->update(
                        $_TABLES['shop.affiliate_payments'],
                        array('aff_pmt_txn_id' => $Payout['txn_id']),
                        array('aff_pmt_id' => $id),
                        array(Database::STRING, Database::INTEGER)
                    );
                } catch (\Exception $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                }
            }
        }
    }

}
