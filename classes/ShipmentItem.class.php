<?php
/**
 * Class to manage items that are part of a shipment.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

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
            $this->setVars($si_id);
        }
    }


    /**
    * Load the item information.
    *
    * @param    integer $rec_id     DB record ID of item
    * @return   boolean     True on success, False on failure
    */
    public function Read($rec_id)
    {
        global $_TABLES;

        $rec_id = (int)$rec_id;
        $sql = "SELECT * FROM {$_TABLES['shop.shipment_items']}
                WHERE si_id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            $this->setVars(DB_fetchArray($res, false));
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
    public static function getByShipment($shipment_id)
    {
        global $_TABLES;

        $retval = array();
        $shipment_id = (int)$shipment_id;
        $sql = "SELECT * FROM {$_TABLES['shop.shipment_items']}
            WHERE shipment_id = $shipment_id";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
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
    public static function getByOrderItem($oi_id)
    {
        global $_TABLES;

        $retval = array();
        $oi_id = (int)$oi_id;
        $sql = "SELECT * FROM {$_TABLES['shop.shipment_items']}
            WHERE orderitem_id = $oi_id";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
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
     * @param   array   $A      Array of values
     * @return  boolean     True on success, False if $A is not an array
     */
    public function setVars($A)
    {
        if (is_array($A))  {
            $this->si_id = isset($A['si_id']) ? (int)$A['si_id'] : 0;
            $this->shipment_id = (int)$A['shipment_id'];
            $this->orderitem_id = (int)$A['orderitem_id'];
            $this->quantity = (float)$A['quantity'];
        }
        return $this;
    }


    /**
     * Save an order item to the database.
     *
     * @param   array   $A  Optional array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save($A= NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A);
        }

        if ($this->si_id > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.shipment_items']} ";
            $sql3 = " WHERE si_id = '{$this->si_id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.shipment_items']} ";
            $sql3 = '';
        }

        $sql2 = "SET shipment_id = '{$this->shipment_id}',
            orderitem_id = '{$this->orderitem_id}',
            quantity = '{$this->quantity}'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        Log::write('shop_system', Log::DEBUG, $sql);
        DB_query($sql);
        if (!DB_error()) {
            //Cache::deleteOrder($this->order_id);
            if ($this->si_id == 0) {
                $this->si_id = DB_insertID();
            }
            return true;
        } else {
            return false;
        }
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

        return (int)DB_getItem(
            $_TABLES['shop.shipment_items'],
            'SUM(quantity)',
            "orderitem_id = $oi_id"
        );
    }

}

?>
