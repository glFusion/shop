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
use Shop\Order;
use Shop\Config;
use glFusion\Database\Database;
use glFusion\Log\Log;


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

    /** Affiliate payment record ID.
     * @var integer */
    private $aff_pmt_id = 0;


    /**
     * Initialize the properties from a supplied string or array.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct(int $id = 0)
    {
        global $_TABLES;

        if ($id > 0) {
            $this->aff_sale_id = (int)$id;
            $db = Database::getInstance();
            try {
                $A = $db->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.affiliate_sales']}
                    WHERE aff_sale_id = ?",
                    array($this->aff_sale_id),
                    array(Database::INTEGER)
                )->fetchAssociative();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }
            if (!empty($A)) {
                $this->setVars($A);
            }
        }
    }


    /**
     * Find the affiliate sale record related to a specific order.
     *
     * @param   string  $order_id   Order ID
     * @return  object  AffiliateSale object
     */
    public static function findByOrderId(string $order_id) : self
    {
        global $_TABLES;

        $retval = new self;
        $db = Database::getInstance();
        try {
            $A = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.affiliate_sales']}
                WHERE aff_order_id = ?",
                array($order_id),
                array(Database::STRING)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A = false;
        }
        if (!empty($A)) {
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
        $this->withPmtId($A['aff_pmt_id']);
        $this->withSaleDate($A['aff_sale_date']);
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
    public function withOrderId(string $id) : self
    {
        $this->aff_order_id = $id;
        return $this;
    }


    /**
     * Set the payment record ID when a payment to the affiliate is created.
     *
     * @param   integer $id     Payment record ID
     * @return  object  $this
     */
    public function withPmtId(int $id) : self
    {
        $this->aff_pmt_id = (int)$id;
        return $this;
    }


    /**
     * Create an affiliate bonus from an order object.
     * Checks each line item to see if it qualifies to be included.
     *
     * @param   object  $Order      Order object
     * @return  object  AffiliateSale object, NULL if not available
     */
    public static function create(Order $Order) : ?self
    {
        // Is the affiliate system enabled?
        if (!Config::get('aff_enabled')) {
            return NULL;
        }

        // Is the referral token valid?
        $Affiliate = Customer::findByAffiliate($Order->getReferralToken());
        if (!$Affiliate || $Affiliate->getUid() == $Order->getUid()) {
            return NULL;
        }

        // Has it already been created?
        $AffSale = self::findByOrderId($Order->getOrderId());
        if ($AffSale->getId() > 0) {
            return NULL;
        }

        $total = 0;
        $AffSaleItems = array();
        foreach ($Order->getItems() as $Item) {
            if ($Item->getProduct()->affApplyBonus()) {
                $AffSaleItem = AffiliateSaleItem::create($Item);
                if ($AffSaleItem) {
                    $AffSaleItems[] = $AffSaleItem;
                    $total += $AffSaleItem->getItemTotal();
                }
            }
        }

        // Find the affiliate. Also verifies that the referral is valid.
        Log::write('shop_system', Log::DEBUG, "Processing referral bonus for {$Affiliate->getUid()}");
        if (!empty($AffSaleItems)) {
            $AffSale = new self;
            $AffSale->withOrderId($Order->getOrderId())
                    ->withUid($Affiliate->getUid())
                    ->withSaleDate()
                    ->Save();
            foreach ($AffSaleItems as $AffSaleItem) {
                if ($AffSaleItem) {
                    $AffSaleItem->withSaleId($AffSale->getId())->Save();
                }
            }
            return $AffSale;
        } else {
            Log::write('shop_system', Log::DEBUG, "No eligible referral bonus items found for {$Affiliate->getUid()}");
            return NULL;
        }
    }


    /**
     * Save the current object to the database.
     *
     * @return  boolean     True on success, False on failure
     */
    public function Save() : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        $params = array(
            'aff_sale_uid' => $this->aff_uid,
            'aff_sale_date' => $this->SaleDate->toMySQL(),
            'aff_order_id' => $this->aff_order_id,
            'aff_pmt_id' => $this->aff_pmt_id,
            'aff_sale_id' => $this->aff_sale_id,
        );
        $types = array(
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
        );

        if ($this->aff_sale_id == 0) {
            try {
                $db->conn->insert(
                    $_TABLES['shop.affiliate_sales'],
                    $params,
                    $types
                );
            }  catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
            $this->aff_sale_id = $db->conn->lastInsertId();
        } else {
            try {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.affiliate_sales'],
                    $params,
                    array('aff_sale_id' => $this->aff_sale_id),
                    $types
                );
            }  catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }


    /**
     * Set the Payment record ID into one or more Sale records.
     * Called after a payment is created.
     *
     * @param   integer $pmt_id     AffiliatePayment record ID
     * @param   array   $sale_ids   AffiliateSale IDs included in the payment
     */
    public static function updatePmtId(int $pmt_id, array $sale_ids) : void
    {
        global $_TABLES;

        $pmt_id = (int)$pmt_id;
        $db = Database::getInstance();
        try {
            $db->conn->executeStatement(
                "UPDATE {$_TABLES['shop.affiliate_sales']}
                SET aff_pmt_id = ?
                WHERE aff_sale_id IN (?)",
                array($pmt_id, $sale_ids),
                array(Database::INTEGER, Database::PARAM_INT_ARRAY)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }

}
