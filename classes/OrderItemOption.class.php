<?php
/**
 * Class to manage options/attributes associated with order line items.
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
class OrderItemOption
{
    /** TODO remove?
     */
    private $product = NULL;

    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    private $properties = array();

    /** Fields for an OrderItemOption record.
     * @var array */
    private static $fields = array(
        'oio_id', 'order_id', 'oi_id',
        'ag_id', 'attr_id',
        'attr_name', 'attr_value',
    );

    /**
     * Constructor.
     * Initializes the order item
     *
     * @param   integer $item   OrderItem record ID
     * @uses    self::Load()
     */
    function __construct($item = 0)
    {
        if (is_numeric($item) && $item > 0) {
            // Got an item ID, read from the DB
            $status = $this->Read($item);
            if (!$status) {
                $this->id = 0;
            }
        } elseif (is_array($item)) {
            // Got an item record, just set the variables
            if (!isset($item['product_id']) && isset($item['item_id'])) {
                // extract the item_id with options into the product ID
                list($this->product_id) = explode('|', $item['item_id']);
            }
            $this->setVars($item);
        }
        $this->product = Product::getByID($this->product_id);
    }


    /**
    * Load the item information.
    *
    * @param    integer $rec_id     DB record ID of item
    * @return   boolean     True on success, False on failure
    */
    public function Read($rec_id)
    {
        global $_SHOP_CONF, $_TABLES;

        $rec_id = (int)$rec_id;
        $sql = "SELECT * FROM {$_TABLES['shop.oi_opts']}
                WHERE oio_id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            return $this->setVars(DB_fetchArray($res, false));
        } else {
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
        case 'oio_id':
        case 'oi_id':
        case 'ag_id':
        case 'attr_id':
            $this->properties[$key] = (int)$value;
            break;
        default:
            //COM_errorLog($key . ' is ' . print_r($value,true));
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


    /**
     * Get the order object related to this item.
     *
     * @return  object  Order Object
     */
    public function getOrder()
    {
        return Order::getInstance($this->order_id);
    }


    /**
     * Get the options associated with an order item.
     *
     * @param   integer $oi_id      OrderItem ID
     * @return  array       Array of OrderItemOption objects
     */
    public static function getOptionsForItem($oi_id)
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['shop.oi_opts']}
            WHERE oi_id = $oi_id";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
        }
        return $retval;
    }


    /**
     * Add an option item to the order item.
     * This allows products to add additional information when purchased,
     * beyond the standard options selected.
     *
     * @param   string  $text   Text to add
     * @param   boolean $save   True to immediately save the item
     */
    public static function AddAttrib($oi_id, $attr_id)
    {
        global $_TABLES;

        $OI = new OrderItem($oi_id);
        if ($OI->id == 0) {
            return false;
        }
        $Attr = new Attribute($attr_id);
        if ($Attr->attr_id == 0) {
            return false;
        }
    }


    /**
     * Add a special text element to an order item.
     * This allows products to add additional information when purchased,
     * beyond the items entered at purchase.
     *
     * @param   string  $name   Name of element
     * @param   string  $value  Value of element
     * @param   boolean $save   True to immediately save the item
     */
    public function addSpecial($name, $value, $save=true)
    {
        // extras is set by __set so it has to be extracted to get at
        // the sub-elements
        $x = $this->extras;
        $x['special'][$name] = strip_tags($value);
        $this->extras = $x;
        if ($save) $this->Save();
    }


    /**
     * Save an order item to the database.
     *
     * @return  boolean     True on success, False on DB error
     */
    public function Save()
    {
        global $_TABLES;

        if ($this->oio_id > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.oi_opts']} ";
            $sql3 = " WHERE oio_id = '{$this->oio_id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.oi_opts']} ";
            $sql3 = '';
        }
        /*'oio_id', 'order_id', 'orderitem_id',
        'ag_id', 'attr_id',
        'attr_name', 'attr_value',*/

        $sql2 = "SET 
                oi_id = '" . DB_escapeString($this->oi_id) . "',
                ag_id = '{$this->ag_id}',
                attr_id = '{$this->attr_id}',
                attr_name = '" . DB_escapeString($this->attr_name) . "',
                attr_value = '" . DB_escapeString($this->attr_value) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        //SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            Cache::deleteOrder($this->order_id);
            if ($this->oio_id == 0) {
                $this->oio_id = DB_insertID();
            }
            return true;
        } else {
            return false;
        }
    }

}

?>
