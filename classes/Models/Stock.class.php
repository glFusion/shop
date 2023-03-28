<?php
/**
 * Class to handle stock levels
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2023 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for stock levels.
 * @package shop
 */
class Stock
{
    /** Record ID.
     * @var integer */
    private $stk_id = 0;

    /** Product record ID.
     * @var integer */
    private $item_id = 0;

    /** Product Variant record ID.
     * @var integer */
    private $pv_id = 0;

    /** Quantity onhand, including reservations.
     * @var integer */
    private $qty_onhand = 0;

    /** Quantity reserved in carts.
     * @var integer */
    private $qty_reserved = 0;

    /** Quantity level to trigger reorder.
     * @var integer */
    private $qty_reorder = 0;


    /**
     * Create a Stock object from item/variant IDs.
     *
     * @param   integer     $item_id    Item ID
     * @param   integer     $pv_id      Variant ID
     */
    public function __construct(int $item_id=0, $pv_id=0)
    {
        global $_TABLES;

        if ($item_id == 0) {
            // creating an empty object.
            return;
        }

        $this->item_id = $item_id;
        $this->pv_id = (int)$pv_id;
        try {
            $A = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.stock']} WHERE
                stk_item_id = ? AND stk_pv_id = ?",
                array($this->item_id, $this->pv_id),
                array(Database::INTEGER, Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A = false;
        }
        if (is_array($A)) {
            $this->setVars($A);
        }
    }


    /**
     * Create a Stock object from an array, e.g. database record.
     *
     * @param   array   $A      Array of properties
     * @return  object      New Stock object
     */
    public static function createFromArray(array $A) : self
    {
        $retval = new self;
        $retval->setVars($A);
        return $retval;
    }


    /**
     * Set all the object properties.
     *
     * @param   array   $A      Array of properties
     * @return  object  $this
     */
    public function setVars(array $A) : self
    {
        if (isset($A['stk_id'])) {
            $this->withStockId($A['stk_id']);
        }
        // The rest should be present always
        $this->withItemId($A['stk_item_id'])
             ->withVariantId($A['stk_pv_id'])
             ->withOnhand($A['qty_onhand'])
             ->withReserved($A['qty_reserved'])
             ->withReorder($A['qty_reorder']);
        return $this;
    }


    /**
     * Set the DB record ID for this object.
     *
     * @param   integer $id     Record ID
     * @return  object  $this
     */
    public function withStockId(int $id) : self
    {
        $this->stk_id = (int)$id;
        return $this;
    }


    /**
     * Set the product record ID for this object.
     *
     * @param   integer $id     Record ID
     * @return  object  $this
     */
    public function withItemId(int $id) : self
    {
        $this->item_id = (int)$id;
        return $this;
    }


    /**
     * Set the variant record ID for this object.
     *
     * @param   integer $id     Record ID
     * @return  object  $this
     */
    public function withVariantId(int $id) : self
    {
        $this->pv_id = (int)$id;
        return $this;
    }


    /**
     * Set the quantity onhand, including reservations.
     *
     * @param   float   $qty    Quantity on hand
     * @return  object  $this
     */
    public function withOnhand(float $qty) : self
    {
        $this->qty_onhand = (float)$qty;
        return $this;
    }


    /**
     * Get the quantity onhand, including reservations.
     *
     * @return  float   Quantity on hand
     */
    public function getOnhand() : float
    {
        return (float)$this->qty_onhand;
    }


    /**
     * Set the quantity reserved in carts.
     *
     * @param   float   $qty    Quantity reserved
     * @return  object  $this
     */
    public function withReserved(float $qty) : self
    {
        $this->qty_reserved = (float)$qty;
        return $this;
    }


    /**
     * Get the quantity reserved in carts.
     *
     * @return  float       Quantity reserved
     */
    public function getReserved() : float
    {
        return (float)$this->qty_reserved;
    }


    /**
     * Set the reorder quantity for the product or variant.
     *
     * @param   float   $qty    Reorder quantity
     * @return  object  $this
     */
    public function withReorder(float $qty) : self
    {
        $this->qty_reorder = (float)$qty;
        return $this;
    }


    /**
     * Get the reorder quantity for this item/variant.
     *
     * @return  float   Reorder quantity
     */
    public function getReorder() : float
    {
        return (float)$this->qty_reorder;
    }


    /**
     * Get a Stock record by item/variant IDs.
     *
     * @param   integer $item_id    Product record ID
     * @param   integer $pv_id      Variant record ID
     * @return  object      Stock object
     */
    public static function getByItem(int $item_id, int $pv_id=0) : self
    {
        return new self($item_id, $pv_id);
    }


    /**
     * Reserve a quantity of an item when it is added to a cart.
     * Quantity may be negative as when a cart qty is reduced or removed.
     *
     * @param   integer $item_id    Item record ID
     * @param   integer $pv_id      Variant record ID
     * @param   float   $qty        Quantity, positive or negative
     * @return  boolean     True on success, False on DB error
     */
    public static function Reserve(int $item_id, int $pv_id, float $qty) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['shop.stock'],
                array(
                    'stk_item_id' => $item_id,
                    'stk_pv_id' => $pv_id,
                    'qty_reserved' => $qty,
                ),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                )
            );
            $retval = true;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            try {
                $db->conn->executeStatement(
                    "UPDATE {$_TABLES['shop.stock']}
                    SET qty_reserved = GREATEST(0, qty_reserved + ?)
                    WHERE stk_item_id = ? AND stk_pv_id = ?",
                    array($qty, $item_id, $pv_id),
                    array(
                        Database::INTEGER,
                        Database::INTEGER,
                        Database::INTEGER,
                    )
                );
                $retval = true;
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $retval = false;
            }
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $retval = false;
        }
        return $retval;
    }


    /**
     * Record the sale of a quantity of an item.
     * Removes the quantity from "reserved" and "onhand".
     *
     * @param   integer $item_id    Item record ID
     * @param   integer $pv_id      Variant record ID
     * @param   float   $qty        Quantity sold
     * @return  boolean     True on success, False on DB error
     */
    public static function recordPurchase(int $item_id, int $pv_id, float $qty, bool $reserved=true) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['shop.stock'],
                array(
                    'stk_item_id' => $item_id,
                    'stk_pv_id' => $pv_id,
                    'qty_reserved' => $qty,
                    'qty_onhand' => 0,
                    'qty_reserved' => 0,
                ),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                )
            );
            $retval = true;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $values = 'qty_onhand = GREATEST(0, qty_onhand - ?)';
            $params = array($qty);
            $types = array(Database::INTEGER, Database::INTEGER, Database::INTEGER);
            if ($reserved) {
                $values = ', qty_reserved = GREATEST(0, qty_reserved - ?)';
                $params[] = $qty;
                $types[] = Database::INTEGER;
            }
            $db->conn->executeStatement(
                "UPDATE {$_TABLES['shop.stock']}
                SET $values
                WHERE stk_item_id = ? AND stk_pv_id = ?",
                $params,
                array($item_id, $pv_id),
                $types
            );
            $retval = true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $retval = false;
        }
        return $retval;
    }


    /**
     * Save this stock record when updated from the product edit form.
     *
     * @return  boolean     True on success, False on DB error
     */
    public function Save() : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        $types = array(
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
        );
        try {
            $db->conn->insert(
                $_TABLES['shop.stock'],
                array(
                    'stk_item_id' => $this->item_id,
                    'stk_pv_id' => $this->pv_id,
                    'qty_onhand' => $this->qty_onhand,
                    'qty_reserved' => $this->qty_reserved,
                    'qty_reorder' => $this->qty_reorder,
                ),
                $types
            );
            $retval = true;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $db->conn->update(
                $_TABLES['shop.stock'],
                array(
                    'qty_onhand' => $this->qty_onhand,
                    'qty_reserved' => $this->qty_reserved,
                    'qty_reorder' => $this->qty_reorder,
                ),
                array(
                    'stk_item_id' => $this->item_id,
                    'stk_pv_id' => $this->pv_id,
                ),
                $types
            );
            $retval = true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $retval = false;
        }
        return $retval;
    }


    /**
     * Delete a stock record when the parent product variant is deleted.
     *
     * @param   integer $pv_id      Variant record ID
     */
    public static function deleteByVariant(int $pv_id) : void
    {
        global $_TABLES;

        try {
            $db->conn->delete(
                $_TABLES['shop.stock'],
                array('stk_pv_id'),
                array($pv_id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $retval = false;
        }
    }


    /**
     * Delete all stock records when the parent product is deleted.
     *
     * @param   integer $item_id    Product record ID
     */
    public static function deleteByProduct(int $item_id) : void
    {
        global $_TABLES;

        try {
            $db->conn->delete(
                $_TABLES['shop.stock'],
                array('stk_item_id'),
                array($item_id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $retval = false;
        }
    }

}
