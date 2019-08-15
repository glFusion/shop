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
        'oio_name', 'oio_value',
        'oio_price',
    );

    /**
     * Constructor.
     * Initializes the order item
     *
     * @param   integer $item   OrderItemObject record ID or array
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
        //$this->product = Product::getByID($this->product_id);
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
        case 'oio_price':
            $this->properties[$key] = (float)$value;
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
     * @param   object  $Item   OrderItem
     * @return  array       Array of OrderItemOption objects
     */
    public static function getOptionsForItem($Item)
    {
        global $_TABLES;

        $cache_key = "oio_item_{$Item->order_id}";
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $sql = "SELECT * FROM {$_TABLES['shop.oi_opts']}
                WHERE oi_id = {$Item->id}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[] = new self($A);
            }
            Cache::set($cache_key, $retval, array('order_' . $Item->order_id));
        }
        return $retval;
    }


    /**
     * Save an order item to the database. Only new records can be added.
     *
     * @return  boolean     True on success, False on DB error
     */
    public function Save()
    {
        global $_TABLES;

        $sql = "INSERT INTO {$_TABLES['shop.oi_opts']} SET
            order_id = '" . DB_escapeString($this->order_id) . "',
            oi_id = '{$this->oi_id}',
            ag_id = '{$this->ag_id}',
            attr_id = '{$this->attr_id}',
            oio_name = '" . DB_escapeString($this->oio_name) . "',
            oio_value = '" . DB_escapeString($this->oio_value) . "',
            oio_price = '{$this->oio_price}'";
        //echo $sql;die;
        //SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql, 1);  // ignore dup key issues.
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


    /**
     * Set the Option attributes from the attibute table.
     * Allows for a standad option, or for a custom name/value pair.
     *
     * @param   integer $attr_id    Option ID, zero to user name/value
     * @param   string  $name       Name of custom field
     * @param   string  $value      Value of custom field
     */
    public function setAttr($attr_id, $name='', $value='')
    {
        if ($attr_id > 0) {
            $Attr = new Attribute($attr_id);
            if ($Attr->attr_id > 0) {
                $AG = new AttributeGroup($Attr->ag_id);
                $this->attr_id = $Attr->attr_id;
                $this->ag_id = $Attr->ag_id;
                $this->oio_name = $AG->ag_name;
                $this->oio_value = $Attr->attr_value;
                $this->oio_price = $Attr->attr_price;
            }
        } elseif ($name != '' && $value != '') {
            $this->attr_id = 0;
            $this->ag_id = 0;
            $this->oio_name = $name;
            $this->oio_value = $value;
            $this->oio_price = 0;
        }
    }



    public static function deleteItem($oi_id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.oi_opts'], 'oi_id', (int)$oi_id);
    }

}

?>
