<?php
/**
 * Class to handle stock levels
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.1
 * @since       v1.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;


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
     * Create a Stock object from item/variant IDs or an array.
     *
     * @param   integer|array   $item_id    Item ID or array of values
     * @param   integer         $pv_id      Variant ID
     */
    public function __construct($item_id=0, $pv_id=0)
    {
        global $_TABLES;

        if ($item_id == 0) {
            // creating an empty object.
            return;
        }

        if (is_array($item_id)) {
            $this->setVars($item_id);
        } else {
            $this->item_id = DB_escapeString($item_id);
            $this->pv_id = (int)$pv_id;
            $sql = "SELECT * FROM {$_TABLES['shop.stock']} WHERE
                stk_item_id = '{$this->item_id}' AND stk_pv_id = {$this->pv_id}";
            $res = DB_query($sql,1);
            if (!DB_error()) {
                if ($A = DB_fetchArray($res, false)) {
                    $this->setVars($A);
                }
            }
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
    public function setVars($A)
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
    public function withStockId($id)
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
    public function withItemId($id)
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
    public function withVariantId($id)
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
    public function withOnhand($qty)
    {
        $this->qty_onhand = (float)$qty;
        return $this;
    }


    /**
     * Get the quantity onhand, including reservations.
     *
     * @return  float   Quantity on hand
     */
    public function getOnhand()
    {
        return (float)$this->qty_onhand;
    }


    /**
     * Set the quantity reserved in carts.
     *
     * @param   float   $qty    Quantity reserved
     * @return  object  $this
     */
    public function withReserved($qty)
    {
        $this->qty_reserved = (float)$qty;
        return $this;
    }


    /**
     * Get the quantity reserved in carts.
     *
     * @return  float       Quantity reserved
     */
    public function getReserved()
    {
        return (float)$this->qty_reserved;
    }


    /**
     * Set the reorder quantity for the product or variant.
     *
     * @param   float   $qty    Reorder quantity
     * @return  object  $this
     */
    public function withReorder($qty)
    {
        $this->qty_reorder = (float)$qty;
        return $this;
    }


    /**
     * Get the reorder quantity for this item/variant.
     *
     * @return  float   Reorder quantity
     */
    public function getReorder()
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
    public static function getByItem($item_id, $pv_id=0)
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
    public static function Reserve($item_id, $pv_id, $qty)
    {
        global $_TABLES;

        $qty = (int)$qty;
        $pv_id = (int)$pv_id;
        $sql = "INSERT INTO {$_TABLES['shop.stock']} SET
            stk_item_id = '" . DB_escapeString($item_id) . "',
            stk_pv_id = {$pv_id},
            qty_reserved = $qty
            ON DUPLICATE KEY UPDATE
            qty_reserved = GREATEST(0, qty_reserved + $qty)";
        $res = DB_query($sql);
        if (!DB_error()) {
            return true;
        } else {
            return false;
        }
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
    public static function recordPurchase($item_id, $pv_id, $qty, $reserved=true)
    {
        global $_TABLES;

        $qty = (int)$qty;
        $pv_id = (int)$pv_id;
        $sql = "INSERT INTO {$_TABLES['shop.stock']} SET
            stk_item_id = '" . DB_escapeString($item_id) . "',
            stk_pv_id = {$pv_id},
            qty_onhand = 0,
            qty_reserved = 0
            ON DUPLICATE KEY UPDATE
            qty_onhand = GREATEST(0, qty_onhand - $qty)";
        if ($reserved) {
            // reduce the reserved amount
            $sql .= ", qty_reserved = GREATEST(0, qty_reserved - $qty)";
        }
        $res = DB_query($sql);
        if (!DB_error()) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Save this stock record when updated from the product edit form.
     *
     * @return  boolean     True on success, False on DB error
     */
    public function Save()
    {
        global $_TABLES;

        $sql = "INSERT INTO {$_TABLES['shop.stock']} SET
            stk_item_id = '" . DB_escapeString($this->item_id) . "',
            stk_pv_id = {$this->pv_id},
            qty_onhand = {$this->qty_onhand},
            qty_reserved = {$this->qty_reserved},
            qty_reorder = {$this->qty_reorder}
            ON DUPLICATE KEY UPDATE
            qty_onhand = {$this->qty_onhand},
            qty_reserved = {$this->qty_reserved},
            qty_reorder = {$this->qty_reorder}";
        $res = DB_query($sql);
    }


    /**
     * Delete a stock record when the parent product variant is deleted.
     *
     * @param   integer $pv_id      Variant record ID
     */
    public static function deleteByVariant($pv_id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.stock'], 'stk_pv_id', (int)$pv_id);
    }


    /**
     * Delete all stock records when the parent product is deleted.
     *
     * @param   integer $item_id    Product record ID
     */
    public static function deleteByProduct($item_id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.stock'], 'stk_item_id', (int)$item_id);
    }

}
