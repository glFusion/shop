<?php
/**
 * Class to handle affiliate sale items.
 * Products may have different reward levels.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2023 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Shop\Customer;
use Shop\OrderItem;


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
     * @param   integer $id     Optional ID to read
     */
    public function __construct(?int $id = NULL)
    {
        if ($id) {
            $this->aff_item_id = $id;
            try {
                $row = Database::getInstance()->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.affiliate_saleitems']}
                    WHERE aff_item_id = ?",
                    array($this->aff_item_id),
                    array(Database::INTEGER)
                )->fetchAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $row = false;
            }
            if (is_array($row)) {
                $this->setVars($row);
            }
        }
    }


    public function getByAffiliateSale(int $sale_id) : array
    {
        global $_TABLES;

        $retval = array();
        try {
            $rows = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.affiliate_saleitems']}
                WHERE aff_sale_id = ?",
                array($sale_id),
                array(Database::INTEGER)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $rows = false;
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $retval[$row['aff_item_id']] = self::fromArray($row);
            }
        }
        return $retval;
    }


    /**
     * Create a sale from an array of values, e.g. from the DB.
     *
     * @param   array   $A      Array of records
     * @return  object  $this
     */
    public static function fromArray(array $A) : self
    {
        $retval = new self;
        $retval->setVars($A);
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
    public function withId(int $id) : self
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
    public function withOrderItemId(int $id) : self
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
    public function withItemTotal(float $total) : self
    {
        $this->aff_item_total = (float)$total;
        return $this;
    }


    /**
     * Get the extended amount for this item.
     *
     * @return  float       Extension amount (price * quantity)
     */
    public function getItemTotal() : float
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
    public function withItemPayment(float $total) : self
    {
        $this->aff_item_pmt = (float)$total;
        return $this;
    }


    /**
     * Get the total item payment amount.
     *
     * @return  float       Payment amount
     */
    public function getItemPayment() : float
    {
        return (float)$this->aff_item_pmt;
    }


    /**
     * Set the related parent sale record ID.
     *
     * @param   integer $id     AffiliateSale record ID
     * @return  object  $this
     */
    public function withSaleId(int $id) : self
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
    public function withPercent(float $pct) : self
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
    public static function create(OrderItem $OrderItem) : AffiliateSaleItem
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
     * @return  integer     Record ID
     */
    public function Save() : int
    {
        global $_TABLES;

        $db = Database::getInstance();
        $values = array(
            'aff_oi_id' => $this->aff_oi_id,
            'aff_item_total' => $this->aff_item_total,
            'aff_item_pmt' => $this->aff_item_pmt,
            'aff_percent' => $this->aff_percent,
            'aff_sale_id' => $this->aff_sale_id,
        );
        $types = array(
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
        );
        try {
            if ($this->aff_item_id == 0) {
                $db->conn->insert(
                    $_TABLES['shop.affiliate_saleitems'],
                    $values,
                    $types
                );
                $this->aff_item_id = $db->conn->lastInsertId();
            } else {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.affiliate_saleitems'],
                    $values,
                    array('aff_item_id' => $this->aff_item_id),
                    $types
                );
            }
            return $this->aff_item_id;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return 0;
        }
    }

}
