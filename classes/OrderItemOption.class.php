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

    /** Indicate a new or empty option.
     * @var boolean */
    private $isEmpty = true;

    /** OrderItemOption DB record ID.
     * @var integer */
    private $oio_id = 0;

    /** Related OrderItem DB record ID.
     * @var integer */
    private $oi_id = 0;

    /** ProductOptionGroup record ID.
     * @var integer */
    private $pog_id = 0;

    /** ProductOptionValue record ID.
     * @var integer */
    private $pov_id = 0;

    /** Option Name for display.
     * @var string */
    private $oio_name = '';

    /** Option Value.
     * @var string */
    private $oio_value = '';

    /** Incremental option price.
     * @var float */
    private $oio_price = 0;

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
                $this->isEmpty = true;
                $this->oi_id = 0;
            } else {
                $this->isEmpty = false;
            }
        } elseif (is_array($item)) {
            // Got an item record, just set the variables
            if (!isset($item['product_id']) && isset($item['item_id'])) {
                // extract the item_id with options into the product ID
                list($this->product_id) = explode('|', $item['item_id']);
            }
            $this->setVars($item);
            $this->isEmpty = false;
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
     * @return  object  $this
     */
    public function setVars($A)
    {
        $this->oio_id = (int)$A['oio_id'];
        $this->oi_id = (int)$A['oi_id'];
        $this->pog_id = (int)$A['pog_id'];
        $this->pov_id = (int)$A['pov_id'];
        $this->oio_name = $A['oio_name'];
        $this->oio_value = $A['oio_value'];
        $this->oio_price = $A['oio_price'];
        return $this;
    }


    /**
     * Set the OrderItem record ID property.
     *
     * @param   integer $id     OrderItem record ID
     * @return  object  $this
     */
    public function setOrderItemID($id)
    {
        $this->oi_id = (int)$id;
        return $this;
    }


    /**
     * Get the options associated with an order item.
     *
     * @param   object  $OI OrderItem object
     * @return  array       Array of OrderItemOption objects
     */
    public static function getOptionsForItem(OrderItem $OI)
    {
        global $_TABLES;

        static $retval = array();
        $item_id = $OI->getID();
        if ($item_id < 1) {
            // Catch bad or empty Item objects
            return array();
        }

        if (isset($retval[$item_id])) {
            return $retval[$item_id];
        }

        $retval[$item_id] = array();
        //$cache_key = "oio_item_{$Item->id}";
        //$retval = Cache::get($cache_key);
        //if ($retval === NULL) {
            //$retval = array();
            $sql = "SELECT * FROM {$_TABLES['shop.oi_opts']}
                WHERE oi_id = {$item_id}
                ORDER BY oio_id ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$item_id][] = new self($A);
            }
        //    Cache::set($cache_key, $retval, array('order_' . $Item->order_id));
        //}
        return $retval[$item_id];
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
            oi_id = '{$this->oi_id}',
            pog_id = '{$this->pog_id}',
            pov_id = '{$this->pov_id}',
            oio_name = '" . DB_escapeString($this->oio_name) . "',
            oio_value = '" . DB_escapeString($this->oio_value) . "',
            oio_price = '{$this->oio_price}'";
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql, 1);  // ignore dup key issues.
        if (!DB_error()) {
            if ($this->oio_id == 0) {
                $this->oio_id = DB_insertID();
            }
            return true;
        } else {
            SHOP_log($sql);
            return false;
        }
    }


    /**
     * Set the Option attributes from the attibute table.
     * Allows for a standad option, or for a custom name/value pair.
     *
     * @param   integer $pov_id     Product Option Value ID, zero to use name/value
     * @param   string  $name       Name of custom field
     * @param   string  $value      Value of custom field
     */
    public function setOpt($pov_id, $name='', $value='')
    {
        if ($pov_id > 0) {
            $POV = new ProductOptionValue($pov_id);
            if ($POV->getID() > 0) {
                // Have a valid object
                $POG = new ProductOptionGroup($POV->getGroupID());
                $this->pov_id = $POV->getID();
                $this->pog_id = $POG->getID();;
                $this->oio_name = $POG->getName();
                $this->oio_value = $POV->getValue();
                $this->oio_price = $POV->getPrice();
            }
        } elseif ($name != '' && $value != '') {
            $this->pov_id = 0;
            $this->pog_id = 0;
            $this->oio_name = $name;
            $this->oio_value = $value;
            $this->oio_price = 0;
        }
    }


    /**
     * Delete all options related to a specified OrderItem.
     *
     * @param   integer $oi_id      OrderItem record ID
     */
    public static function deleteItem($oi_id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.oi_opts'], 'oi_id', (int)$oi_id);
    }


    /**
     * Check if this option object matches the supplied object.
     *
     * @param   object  $Attr2  Second attribute to check
     * @return  boolean     True if the objects match, False if not.
     */
    public function Matches($Attr2)
    {
        $flds_to_check = array(
            'pog_id', 'pov_id',
            'oio_name', 'oio_value',
            'oio_price',
        );
        foreach ($flds_to_check as $fldname) {
            if ($this->$fldname != $Attr2->$fldname) {
                return false;
            }
        }
        return true;
    }


    /**
     * Check whether all the option objects match.
     *
     * @uses    self::Matches()
     * @param   array   $arr1   Array of OrderItemOption objects
     * @param   array   $arr2   Array of OrderItemOption objects
     * @return  boolean     True if all objects match
     */
    public static function MatchAll($arr1, $arr2)
    {
        // Different number of options, can't match
        if (count($arr1) != count($arr2)) {
            return false;
        }
        foreach ($arr1 as $idx=>$Attr1) {
            $Attr2 = $arr2[$idx];
            if (!$Attr1->Matches($Attr2)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Get the record ID for this item option
     *
     * @return  integer     DB record ID
     */
    public function getID()
    {
        return $this->oio_id;
    }


    /**
     * Get the name of the item option, e.g. "color".
     *
     * @return  string      Name of option
     */
    public function getName()
    {
        return $this->oio_name;
    }


    /**
     * Get the price for this item option.
     *
     * @return  float       Option price
     */
    public function getPrice()
    {
        return $this->oio_price;
    }


    /**
     * Get the value (text string) of the option.
     *
     * @return  string      Option value
     */
    public function getValue()
    {
        return $this->oio_value;
    }

    /**
     * Get the product option value ID for this opton.
     *
     * @return  integer     Product Option Value ID
     */
    public function getOptionID()
    {
        return $this->pov_id;
    }

}

?>
