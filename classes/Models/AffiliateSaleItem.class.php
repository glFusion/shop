<?php
/**
 * Class to handle affiliate sale items.
 * Products may have different reward levels.
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
 * Class for affiliate item sales records.
 * @package shop
 */
class AffiliateSaleItem
{
    /** Record ID.
     * @var integer */
    private $aff_item_id = 0;

    /** Record ID.
     * @var integer */
    private $aff_sale_id = 0;

    /** OrderItem record ID.
     * @var integer */
    private $aff_oi_id = 0;

    /** Total order amount subject to affiliate rewards.
     * @var float */
    private $aff_item_total = 0;

    /** Total payment to the affiliate.
     * @var float */
    private $aff_item_pmt = 0;

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
        if (is_array($id)) {
            $this->setVars($id);
        } elseif ($id > 0) {
            $this->aff_item_id = (int)$id;
            $sql = "SELECT * FROM {$_TABLES['shop.affiliate_saleitems']}
                WHERE aff_item_id = {$this->aff_item_id}";
            $res = DB_query($sql);
            if (DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
                $this->setVars($A);
            }
        }
    }


    public function getByAffiliateSale($sale_id)
    {
        global $_TABLES;

        $retval = new self;
            $sql = "SELECT * FROM {$_TABLES['shop.affiliate_saleitems']}
                WHERE aff_sale_id = '" . DB_escapeString($_id) . "'";
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
        $this->withId($A['aff_item_id']);
        $this->withSaleId($A['aff_sale_id']);
        $this->withItemTotal($A['aff_item_total']);
        $this->withItemPayment($A['aff_item_pmt']);
        $this->withOrderItemId($A['aff_oi_id']);
        $this->withPercent($A['aff_percent']);
        return $this;
    }


    /**
     * Set the affiliate user ID.
     *
     * @param   integer $id     Record ID
     * @return  object  $this
     */
    public function withId($id)
    {
        $this->aff_item_id = (int)$id;
        return $this;
    }


    /**
     * Set the related orderitem ID.
     *
     * @param   string  $id     OrderItem Record ID
     * @return  object  $this
     */
    public function withOrderItemId($id)
    {
        $this->aff_oi_id = (int)$id;
        return $this;
    }


    /**
     * Set the total qualifying sale amount from the order.
     *
     * @param   float   $total      Qualifying total amount
     * @return  object  $this
     */
    public function withItemTotal($total)
    {
        $this->aff_item_total = (float)$total;
        return $this;
    }


    /**
     * Get the extended amount for this item.
     *
     * @return  float       Extension amount (price * quantity)
     */
    public function getItemTotal()
    {
        return (float)$this->aff_item_total;
    }


    /**
     * Set the total payment amount.
     * For now this is equal to the order total but may be adjusted in the
     * future for holdbacks.
     *
     * @param   float   $total      Total payment amount
     * @return  object  $this
     */
    public function withItemPayment($total)
    {
        $this->aff_item_pmt = (float)$total;
        return $this;
    }


    public function getItemPayment()
    {
        return (float)$this->aff_item_pmt;
    }


    /**
     * Set the related parent sale record ID.
     *
     * @param   integer $id     AffiliateSale record ID
     * @return  object  $this
     */
    public function withSaleId($id)
    {
        $this->aff_sale_id = (int)$id;
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
     * @param   object  $OrderItem  OrderItem object
     * @return  object  AffiliateSale object
     */
    public static function create($OrderItem)
    {
        global $_SHOP_CONF;

        $AffSaleItem = NULL;
        $aff_pct = $OrderItem->getProduct()->getAffiliatePercent();
        if ($aff_pct > 0) {
            $item_total = $OrderItem->getNetExtension();
            $aff_pmt_total = round(($aff_pct / 100) * $item_total, 2);
            if ($aff_pmt_total > .0001) {
                $AffSaleItem = new self;
                $AffSaleItem = $AffSaleItem
                    ->withOrderItemId($OrderItem->getID())
                    ->withItemPayment($aff_pmt_total)
                    ->withItemTotal($item_total)
                    ->withPercent($aff_pct);
            }
        }
        return $AffSaleItem;
    }


    /**
     * Save the current object to the database.
     *
     * @return  boolean     True on success, False on failure
     */
    public function Save()
    {
        global $_TABLES;

        if ($this->aff_item_id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['shop.affiliate_saleitems']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.affiliate_saleitems']} SET ";
            $sql3 = " WHERE aff_item_id = {$this->aff_item_id}";
        }
        $sql2 = "aff_item_id = {$this->aff_item_id},
            aff_oi_id = {$this->aff_oi_id},
            aff_item_total = {$this->aff_item_total},
            aff_item_pmt = {$this->aff_item_pmt},
            aff_percent = {$this->aff_percent},
            aff_sale_id = {$this->aff_sale_id}";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        $res = DB_query($sql, 1);
        if (!DB_error()) {
            if ($this->aff_item_id == 0) {
                $this->aff_item_id = DB_insertId();
            }
        }
        return $this->aff_item_id;
    }

}
