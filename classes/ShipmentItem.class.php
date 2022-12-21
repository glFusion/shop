<?php
/**
 * Class to manage items that are part of a shipment.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use Shop\Log;
use Shop\Models\DataArray;


/**
 * Class for order line items.
 * @package shop
 */
class ShipmentItem
{
    /** Shipment Item record ID.
     * @var integer */
    private $si_id = 0;

    /** Shipment record ID.
     * @var integer */
    private $shipment_id = 0;

    /** OrderItem record ID.
     * @var integer */
    private $orderitem_id = 0;

    /** Quantity of item shipped.
     * @var integer */
    private $quantity = 0;

    /** Fields for an OrderItem record.
     * @var array */
    private static $fields = array(
        'si_id', 'shipment_id', 'orderitem_id', 'quantity',
    );

    /** OrderItem related to this shipment item
     * @var object */
    private $OrderItem = NULL;


    /**
     * Constructor.
     * Initializes the order item
     *
     * @param   integer $si_id  OrderItem record ID
     * @uses    self::Load()
     */
    public function __construct($si_id = 0)
    {
        if (is_numeric($si_id) && $si_id > 0) {
            // Got a shipment_item ID, read from the DB
            $status = $this->Read($si_id);
            if (!$status) {
                $this->si_id = 0;
            }
        } elseif (is_array($si_id) && isset($si_id['quantity'])) {
            // Got a shipment record, just set the variables
            $this->setVars(new DataArray($si_id));
        }
    }


    /**
    * Load the item information.
    *
    * @param    integer $rec_id     DB record ID of item
    * @return   boolean     True on success, False on failure
    */
    public function Read(int $rec_id) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.shipment_items']} WHERE si_id = ?",
                array($rec_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            $this->setVars(new DataArray($data));
            return true;
        } else {
            $this->si_id = 0;
            return false;
        }
    }


    /**
     * Create a new ShipmentItem object.
     *
     * @param   integer $shipment_id     ID of parent shipment
     * @param   integer $oi_id      ID of order item being shipped
     * @param   integer $qty        Quantity of items in this shipment
     * @return  object      ShipmentItem object
     */
    public static function Create($shipment_id, $oi_id, $qty)
    {
        $Obj = new self(array(
            'shipment_id'   => $shipment_id,
            'orderitem_id'  => $oi_id,
            'quantity'      => $qty,
        ) );
        return $Obj;
    }


    /**
     * Set the shipment record ID.
     *
     * @param   integer $val    Shipment record ID
     * @return  object  $this
     */
    private function setShipmentID($val)
    {
        $this->shipment_id = (int)$val;
        return $this;
    }


    /**
     * Set the orderitem record ID.
     *
     * @param   integer $val    Orderitem record ID
     * @return  object  $this
     */
    private function setOrderitemID($val)
    {
        $this->orderitem_id = (int)$val;
        return $this;
    }


    /**
     * Get the OrderItem record id for this shipment item.
     *
     * @return  integer     Related Orderitem record ID
     */
    public function getOrderitemID()
    {
        return (int)$this->orderitem_id;
    }


    /**
     * Set the quantity shipped.
     *
     * @param   integer $val    Quantity shipped
     * @return  object  $this
     */
    private function setQuantity($val)
    {
        $this->quantity = (int)$val;
        return $this;
    }


    /**
     * Get the OrderItem record id for this shipment item.
     *
     * @return  integer     Related Orderitem record ID
     */
    public function getQuantity()
    {
        return (int)$this->quantity;
    }


    /**
     * Get the shipment items associated with a shipment.
     *
     * @param   integer $shipment_id     Shipment ID
     * @return  array       Array of ShipmentItem objects
     */
    public static function getByShipment(int $shipment_id) : array
    {
        global $_TABLES;

        $retval = array();
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.shipment_items']} WHERE shipment_id = ?",
                array($shipment_id),
                array(Database::INTEGER)
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $retval[] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Get shipped items by OrderItem.
     * Used to find out how many of an ordered item have shipped.
     *
     * @param   integer $oi_id  OrderItem record ID
     * @return  array       Array of ShipmentItem objects
     */
    public static function getByOrderItem(int $oi_id) : array
    {
        global $_TABLES;

        $retval = array();
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.shipment_items']} WHERE orderitem_id = ?",
                array($oi_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $retval[] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Get the OrderItem associated with this shipment item.
     *
     * @return  object  OrderItem object
     */
    public function getOrderItem()
    {
        if ($this->OrderItem === NULL && $this->si_id > 0) {
            $this->OrderItem = new OrderItem($this->orderitem_id);
        }
        return $this->OrderItem;
    }


    /**
     * Set the object variables from an array.
     *
     * @param   DataArray   $A  Array of values
     * @return  boolean     True on success, False if $A is not an array
     */
    public function setVars(DataArray $A) : self
    {
        if (is_array($A))  {
            $this->si_id = $A->getInt('si_id');
            $this->shipment_id = $A->getInt('shipment_id');
            $this->orderitem_id = $A->getInt('orderitem_id');
            $this->quantity = $A->getFloat('quantity');
        }
        return $this;
    }


    /**
     * Save an order item to the database.
     *
     * @param   DataArray   $A  Optional array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save(?DataArray $A= NULL)
    {
        global $_TABLES;

        if (!empty($A)) {
            $this->setVars($A);
        }

        $db = Database::getInstance();
        $values = array(
            'shipment_id' => $this->shipment_id,
            'orderitem_id' => $this->orderitem_id,
            'quantity' => (float)$this->quantity,
        );
        $types = array(
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,

        );
        try {
            if ($this->si_id == 0) {
                $db->conn->insert($_TABLES['shop.shipment_items'], $values, $types);
                $this->si_id = $db->conn->lastInsertId();
            } else {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.shipment_items'],
                    $values,
                    array('si_id' => $this->si_id),
                    $types
                );
            }
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Get the total number of items shipped for an order.
     *
     * @param   string  $oi_id  Order ID
     * @return  integer     Number of items shipped
     */
    public static function getItemsShipped($oi_id)
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT SUM(quantity) AS qty FROM {$_TABLES['shop.shipment_items']}
                WHERE orderitem_id = ?",
                array($oi_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            return $data['qty'];
        } else {
            return 0;
        }
    }

}

