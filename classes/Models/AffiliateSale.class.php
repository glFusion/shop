<?php
/**
 * Class to handle affiliate sales.
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
use Shop\Customer;


/**
 * Class for affiliate sales records.
 * @package shop
 */
class AffiliateSale
{
    /** Record ID.
     * @var integer */
    private $aff_sale_id = 0;

    /** User ID.
     * @var integer */
    private $aff_uid = 0;

    /** Date of the affiliate sale.
     * @var object */
    private $SaleDate = NULL;

    /** Related order ID.
     * @var string */
    private $aff_order_id = '';

    /** Total order amount subject to affiliate rewards.
     * @var float */
    private $aff_order_total = 0;

    /** Total payment to the affiliate.
     * @var float */
    private $aff_pmt_total = 0;

    /** Affiliate payment record ID.
     * @var integer */
    private $aff_pmt_id = 0;

    /** Affiliate payment rate (percentage).
     * @var float */
    private $aff_percent = 0;


    /**
     * Initialize the properties from a supplied string or array.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct($id = 0)
    {
        if ($id > 0) {
            $this->aff_sale_id = (int)$id;
            $sql = "SELECT * FROM {$_TABLES['shop.affiliate_sales']}
                WHERE aff_sale_id = {$this->aff_sale_id}";
            $res = DB_query($sql);
            if (DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
                $this->setVars($A);
            }
        }

    }


    public static function findByOrderId($order_id)
    {
        global $_TABLES;

        $retval = new self;
        $sql = "SELECT * FROM {$_TABLES['shop.affiliate_sales']}
                WHERE aff_order_id = '" . DB_escapeString($order_id) . "'";
        $res = DB_query($sql);
        if (DB_numRows($res) > 0) {
            $A = DB_fetchArray($res, false);
            $retval->setVars($A);
        }
        return $retval;

    }


    /**
     * Set the property values from the DB record fields.
     *
     * @param   array   $A      Array of record fields
     * @return  object  $this
     */
    public function setVars($A)
    {
        $this->withId($A['aff_sale_id']);
        $this->withUid($A['aff_sale_uid']);
        $this->withOrderId($A['aff_order_id']);
        $this->withOrderTotal($A['aff_order_total']);
        $this->withPmtTotal($A['aff_pmt_total']);
        $this->withPmtId($A['aff_pmt_id']);
        $this->withSaleDate($A['aff_sale_date']);
        $this->withPercent($A['aff_percent']);
        return $this;
    }


    public function withId($id)
    {
        $this->aff_sale_id = (int)$id;
        return $this;
    }


    public function getId()
    {
        return (int)$this->aff_sale_id;
    }


    /**
     * Set the affiliate user ID.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    public function withUid($uid)
    {
        $this->aff_uid = (int)$uid;
        return $this;
    }


    /**
     * Set the sale date, default = now.
     *
     * @param   string  $dt_str     Acceptable date string, e.g. MySQL datetime
     * @return  object  $this
     */
    public function withSaleDate($dt_str=NULL)
    {
        global $_CONF;

        if ($dt_str === NULL) {
            $this->SaleDate = clone $_CONF['_now'];
        } else {
            $this->SaleDate = new \Date($dt_str);
            $this->SaleDate->setTimezone(new \DateTimezone($_CONF['timezone']));
        }
        return $this;
    }


    /**
     * Set the related order ID.
     *
     * @param   string  $id     Order ID
     * @return  object  $this
     */
    public function withOrderId($id)
    {
        $this->aff_order_id = $id;
        return $this;
    }


    /**
     * Set the total qualifying sale amount from the order.
     *
     * @param   float   $total      Qualifying total amount
     * @return  object  $this
     */
    public function withOrderTotal($total)
    {
        $this->aff_order_total = (float)$total;
        return $this;
    }


    /**
     * Set the total payment amount.
     * For now this is equal to the order total but may be adjusted in the
     * future for holdbacks.
     *
     * @param   float   $total      Total payment amount
     * @return  object  $this
     */
    public function withPmtTotal($total)
    {
        $this->aff_pmt_total = (float)$total;
        return $this;
    }


    /**
     * Set the payment record ID when a payment to the affiliate is created.
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
     * Set the percentage being paid to the affiliate.
     *
     * @param   float   $pct    Affiliate payment rate
     * @return  object  $this
     */
    public function withPercent($pct)
    {
        $this->aff_percent = (float)$pct;
        return $this;
    }


    /**
     * Create an affiliate bonus from an order object.
     * Checks each line item to see if it qualifies to be included.
     *
     * @param   object  $Order      Order object
     * @return  object  AffiliateSale object
     */
    public static function create($Order)
    {
        global $_SHOP_CONF;

        if (!$_SHOP_CONF['aff_enabled']) {
            return false;
        }

        $Affiliate = Customer::findByAffiliate($Order->getReferralToken());
        if (!$Affiliate || $Affiliate->getUid() == $Order->getUid()) {
            return false;
        }

        $AffSale = self::findByOrderId($Order->getOrderId());
        if ($AffSale->getId() > 0) {
            return false;
        }

        //$aff_pct = (float)$_SHOP_CONF['aff_pct'] / 100;
        $total = 0;
        $aff_pmt_total = 0;
        $AffSaleItems = array();
        foreach ($Order->getItems() as $Item) {
            if ($Item->getProduct()->affApplyBonus()) {
                $AffSaleItem = AffiliateSaleItem::create($Item);
                if ($AffSaleItem) {
                    $AffSaleItems[] = $AffSaleItem;
                    $total += $AffSaleItem->getItemTotal();
                    $aff_pmt_total += $AffSaleItem->getItemPayment();
                }
            }
        }

            //$aff_pmt_total = round($aff_pct * $total, 2);
            // Find the affiliate. Also verifies that the referral is valid.
            SHOP_log("Processing referral bonus for {$Affiliate->getUid()}", SHOP_LOG_DEBUG);
            $AffSale = new self;
            $AffSale->withOrderId($Order->getOrderId())
                    ->withUid($Affiliate->getUid())
                    ->withSaleDate()
                    ->withPmtTotal($aff_pmt_total)
                    ->withOrderTotal($total)
                    ->withPercent($_SHOP_CONF['aff_pct'])
                    ->Save();
            foreach ($AffSaleItems as $AffSaleItem) {
                if ($AffSaleItem) {
                    $AffSaleItem->withSaleId($AffSale->getId())->Save();
                }
            }
        return $AffSale;
    }


    /**
     * Save the current object to the database.
     *
     * @return  boolean     True on success, False on failure
     */
    public function Save()
    {
        global $_TABLES;

        if ($this->aff_sale_id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['shop.affiliate_sales']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.affiliate_sales']} SET ";
            $sql3 = " WHERE aff_sale_id = {$this->aff_sale_id}";
        }
        $sql2 = "aff_sale_uid = {$this->aff_uid},
            aff_sale_date = '" . $this->SaleDate->toMySQL() . "',
            aff_order_id = '" . DB_escapeString($this->aff_order_id) . "',
            aff_order_total = {$this->aff_order_total},
            aff_pmt_total = {$this->aff_pmt_total},
            aff_pmt_id = {$this->aff_pmt_id}";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        $res = DB_query($sql, 1);
        if (!DB_error()) {
            $this->withId(DB_insertId());
            return true;
        } else {
            return false;
        }
    }


    /**
     * Set the Payment record ID into one or more Sale records.
     * Called after a payment is created.
     *
     * @param   integer $pmt_id     AffiliatePayment record ID
     * @param   array   $sale_ids   AffiliateSale IDs included in the payment
     */
    public static function updatePmtId($pmt_id, $sale_ids)
    {
        global $_TABLES;

        $pmt_id = (int)$pmt_id;
        $sale_ids = implode(',', $sale_ids);
        $sql = "UPDATE {$_TABLES['shop.affiliate_sales']}
            SET aff_pmt_id = $pmt_id
            WHERE aff_sale_id IN ($sale_ids)";
        DB_query($sql);
    }

}
