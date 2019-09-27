<?php
/**
 * Class to manage order shipments
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     TBD
 * @since       TBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

namespace Shop;

/**
 * Class for order line items.
 * @package shop
 */
class Shipment
{
    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    private $properties = array();


    private $Order = NULL;
    public $Items = array();

    /** Fields for an OrderItem record.
     * @var array */
    private static $fields = array(
        'shp_id', 'order_id', 'shipper_id', 'ts', 'shipper_info',
        'tracking_num', 'comment',
    );

    /**
     * Constructor.
     * Initializes the order item
     *
     * @param   integer $shp_id  OrderItem record ID
     * @uses    self::Load()
     */
    public function __construct($shp_id = 0)
    {
        if (is_numeric($shp_id) && $shp_id > 0) {
            // Got an item ID, read from the DB
            $status = $this->Read($shp_id);
            if (!$status) {
                $this->shp_id = 0;
            }
        } elseif (is_array($shp_id) && isset($shp_id['quantity'])) {
            // Got a shipment record, just set the variables
            $this->setVars($shp_id);
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
        $sql = "SELECT * FROM {$_TABLES['shop.shipments']}
                WHERE shp_id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            $this->setVars(DB_fetchArray($res, false));
            $this->getItems();
            return true;
        } else {
            $this->shp_id = 0;
            return false;
        }
    }


    /**
     * Set the object variables from an array.
     *
     * @param   array   $A      Array of values
     * @return  boolean     True on success, False if $A is not an array
     */
    public function setVars($A)
    {
        if (!is_array($A)) return false;

        foreach (self::$fields as $field) {
            if (isset($A[$field])) {
                $this->$field = $A[$field];
            }
        }
        return true;
    }


    /**
     * Setter function.
     *
     * @param   string  $key    Name of property to set
     * @param   mixed   $value  Value to set for property
     */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'shp_id':
        case 'shipper_id':
        case 'ts':
            $this->properties[$key] = (int)$value;
            break;
        default:
            $this->properties[$key] = trim($value);
            break;
        }
    }


    /**
     * Getter function.
     *
     * @param   string  $key    Property to retrieve
     * @return  mixed           Value of property, NULL if undefined
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    public function getOrder()
    {
        if ($this->Order === NULL) {
            $this->Order = Order::getInstance($this->order_id);
        }
        return $this->Order;
    }


    /**
     * Get the Items shipped in this shipment.
     */
    public function getItems()
    {
        $this->Items = ShipmentItem::getByShipment($this->shp_id);
    }


    /**
     * Save a shipment to the database.
     * `$form` is expected to contain shipment info, including an array
     * named `orderitem` with orderitem IDs and quantities for each.
     *
     * @param   array   $A  Optional array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save($form = NULL)
    {
        global $_TABLES;

        if (is_array($form)) {
            // This sets the base info, ShipmentItems are created after saving
            // the shipment.
            $this->setVars($form);
        }

        if ($this->shp_id > 0) {
            // New shipment
            $sql1 = "UPDATE {$_TABLES['shop.shipments']} ";
            $sql3 = " WHERE id = '{$this->shp_id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.shipments']} ";
            $sql3 = '';
        }
        $shipping_addr = $this->getOrder()->getAddress('shipping');
        $sql2 = "SET 
            order_id = '" . DB_escapeString($this->order_id) . "',
            ts = UNIX_TIMESTAMP(),
            shipper_id = '{$this->shipper_id}',
            shipper_info = '" . DB_escapeString($this->shipper_info) . "',
            tracking_num = '" . DB_escapeString($this->tracking_num) . "',
            comment = '" . DB_escapeString($this->comment) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->shp_id == 0) {
                $this->shp_id = DB_insertID();
                if (isset($form['orderitems']) && is_array($form['orderitems'])) {
                    foreach ($form['orderitems'] as $oi_id=>$qty) {
                        $SI = ShipmentItem::create($this->shp_id, $oi_id, $qty);
                        $SI->Save();
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }



    public function Edit()
    {
        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('shipment_form', 'shipment_form.thtml');

    }


}

?>
