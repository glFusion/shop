<?php
/**
 * Class to manage order line items.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

namespace Shop;

/**
 * Class for order line items.
 * @package shop
 */
class OrderItem
{
    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    private $properties = array();

    /** Array of options for this item
     * @var array */
    public $options = array();

    /** Product object.
     * @var object */
    private $product = NULL;

    /** Fields for an OrderItem record.
     * @var array */
    private static $fields = array(
        'id', 'order_id', 'product_id',
        'description', 'quantity', 'txn_id', 'txn_type',
        'expiration', 'price', 'token',
        //'options',
        'options_text', 'extras', 'taxable', 'paid',
        'shipping', 'handling',
    );

    /**
     * Constructor.
     * Initializes the order item
     *
     * @param   integer $oi_id  OrderItem record ID
     * @uses    self::Load()
     */
    function __construct($oi_id = 0)
    {
        if (is_numeric($oi_id) && $oi_id > 0) {
            // Got an item ID, read from the DB
            $status = $this->Read($oi_id);
            if (!$status) {
                $this->id = 0;
            } else {
                $this->options = $this->getOptions();
            }
        } elseif (is_array($oi_id)) {
            // Got an item record, just set the variables
            if (!isset($oi_id['product_id']) && isset($oi_id['item_id'])) {
                // extract the item_id with options into the product ID
                list($this->product_id) = explode('|', $oi_id['item_id']);
            } else {
                $this->product_id = $oi_id['product_id'];
            }
            $extras = json_decode($oi_id['extras'], true);
            if (
                isset($extras['custom']) && 
                !empty($extras['custom'])
            ) {
                $cust = $extras['custom'];
                $P = Product::getByID($this->product_id);
                foreach ($P->getCustom() as $id=>$name) {
                    if (isset($cust[$id]) && !empty($cust[$id])) {
                        $this->addOptionTextNew($name, $cust[$id], $id);
                    }
                }
            }
            $this->setVars($oi_id);
        }
        if ($oi_id['id'] == 0) {
           if (isset($oi_id['options'])) {
//                COM_errorLog("HERE: " . print_r($oi_id,true));die;
               $this->options = $this->setOptions($oi_id['options']);
           }
        } else {
            $this->options = $this->getOptions();
        }

        $this->product = Product::getByID($this->product_id);
        COM_errorLog("item final: " . print_r($this->options,true));
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
        $sql = "SELECT *,
                UNIX_TIMESTAMP(CONVERT_TZ(`expiration`, '+00:00', @@session.time_zone)) AS ux_exp
                FROM {$_TABLES['shop.orderitems']}
                WHERE id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            $this->options = OrderItemOption::getOptionsForItem($rec_id);
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
        $this->ux_exp = SHOP_getVar($A, 'ux_exp');
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
        case 'id':
        case 'quantity':
            $this->properties[$key] = (int)$value;
            break;
        case 'extras':
        case 'options_text':
            if (is_string($value)) {    // convert to array
                $value = @json_decode($value, true);
                if (!$value) $value = array();
            }
            $this->properties[$key] = $value;
            break;
        case 'price':
        case 'paid':
        case 'shipping':
        case 'handling':
            $this->properties[$key] = (float)$value;
            break;
        case 'taxable':
            $this->properties[$key] = $value == 0 ? 0 : 1;
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
     * Public function to get the Product object for this item.
     *
     * @return  object      Product object
     */
    public function getProduct()
    {
        return $this->product;
    }


    /**
     * Get the short description. Return the long descr if not defined.
     *
     * @return  string  Description string
     */
    public function getShortDscp()
    {
        if ($this->short_description == '') {
            return $this->description;
        } else {
            return $this->short_description;
        }
    }


    public function addOptionTextNew($name, $value, $idx)
    {
        $OIO = new OrderItemOption;
        $OIO->oio_id = $this->id;
        $OIO->ag_id = 0;
        $OIO->attr_id = $idx;
        $OIO->attr_name = $name;
        $OIO->attr_value = $value;
        $this->options[] = $OIO;
        COM_errorLog("new option aray: " . print_r($this->options,true));
        
    }


    /**
     * Add an option text item to the order item.
     * This allows products to add additional information when purchased,
     * beyond the standard options selected.
     *
     * @param   string  $text   Text to add
     * @param   boolean $save   True to immediately save the item
     */
    public function addOptionText($text, $save=true)
    {
        $opts = $this->options_text;
        $opts[] = $text;
        $this->options_text = $opts;
        if ($save) $this->Save();
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
     * @param   array   $A  Optional array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save($A= NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A);
        }
        $purchase_ts = SHOP_now()->toUnix();
        //$shipping = $this->product->getShipping($this->quantity);
        $shipping = 0;
        $handling = $this->product->getHandling($this->quantity);

        if ($this->id > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.orderitems']} ";
            $sql3 = " WHERE id = '{$this->id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.orderitems']} ";
            $sql3 = '';
        }
        $sql2 = "SET order_id = '" . DB_escapeString($this->order_id) . "',
                product_id = '" . DB_escapeString($this->product_id) . "',
                description = '" . DB_escapeString($this->description) . "',
                quantity = '{$this->quantity}',
                txn_id = '" . DB_escapeString($this->txn_id) . "',
                txn_type = '" . DB_escapeString($this->txn_type) . "',
                price = '$this->price',
                taxable = '{$this->taxable}',
                token = '" . DB_escapeString($this->token) . "',
                options_text = '" . DB_escapeString(@json_encode($this->options_text)) . "',
                extras = '" . DB_escapeString(json_encode($this->extras)) . "'";
                //options = '" . DB_escapeString($this->options) . "',
                //shipping = {$shipping},
                //handling = {$handling},
            // add an expiration date if appropriate
        if ($this->product->expiration > 0) {
            $sql2 .= ", expiration = " . (string)($purchase_ts + ($this->product->expiration * 86400));
        }
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            COM_errorLog("item saved");
            Cache::deleteOrder($this->order_id);
            if ($this->id == 0) {
                $this->id = DB_insertID();
            }
            return $this->saveOptions();
            return true;
        } else {
            return false;
        }
    }


    /**
     * Update the quantity for a cart item.
     * Does not save the item since Order::Save() must be called
     * anyway to update shipping, tax, etc.
     *
     * @param   integer $newqty New quantity
     * @return  object          Updated item object
     */
    public function setQuantity($newqty)
    {
        if ($newqty > 0) {
            $this->quantity = (float)$newqty;
            $product = $this->getProduct();
            $price = $product->getPrice($this->options, $newqty);
            $this->handling = $product->getHandling($newqty);
            //$this->shipping = $product->getShipping($newqty);
            $this->price = $price;
        }
        return $this;
    }


    /**
     * Check if the buyer can download a file from the order view.
     */
    public function canDownload()
    {
        if (
            // Check that the order is paid
            !$this->getOrder()->isPaid() ||
            // Check if product is not a download
            $this->getProduct()->file == '' ||
            // or is expired
            ( $this->expiration > 0 && $this->expiration < time() )
        ) {
            return false;
        }
        // All conditions passed, return true
        return true;
    }


    /**
     * Get the total number of shipping units for this item
     *
     * @return  float       Total shipping units (per-product * quantity)
     */
    public function getShippingUnits()
    {
        return $this->product->shipping_units * $this->quantity;
    }


    /**
     * Get the total of all per-item shipping costs for this item
     *
     * @return  float       Total fixed shipping cost (per-product * quantity)
     */
    public function getShippingAmt()
    {
        return $this->product->shipping_amt * $this->quantity;
    }


    /**
     * Convert from one currency to another.
     *
     * @param   string  $old    Original currency
     * @param   string  $new    New currency
     * @return  boolean     True on success, False on error
     */
    public function convertCurrency($old, $new)
    {
        // If already set, return OK. Nothing to do.
        if ($new == $old) return true;

        foreach (array('price') as $fld) {
            $this->$fld = Currency::Convert($this->$fld, $new, $old);
        }
        $this->Save();
        return true;
    }


    /**
     * Check if the supplied OrderItem object matches this object.
     * Checks the product ID, options and extras.
     *
     * @param   object  $Item2  Item object to check
     * @return  boolean     True if $Item2 matches this item, False if not
     */
    public function Matches($Item2)
    {
        if ($this->product_id != $Item2->product_id) {
            return false;
        }
        if ($this->options != $Item2->options) {
            return false;
        }
        if ($this->extras != $Item2->extras) {
            return false;
        }
        return true;
    }


    public function setOptions($opts = NULL)
    {
        if (is_string($opts)) {
            $opts = explode(',', $opts);
        }
        if (is_array($opts)) {
            foreach ($opts as $opt_id) {
                //$Attr = new Attribute($opt_id);
                $OIO = new OrderItemOption;
                $OIO->setAttr($opt_id);
                $OIO->oio_id = $this->id;
                /*$OIO->ag_id = $Attr->ag_id;
                $OIO->attr_id = $opt_id;
                $OIO->attr_name = $Attr->attr_name;
                $OIO->attr_value = $Attr->attr_value;*/
                $this->options[] = $OIO;
            }
        }
        return $this->options;
    }


    public function saveOptions()
    {
        COM_errorLog('here: ' . print_r($this->options,true));
        foreach ($this->options as $Opt) {
            $Opt->oi_id = $this->id;
            COM_errorLog('SAVING: ' . print_r($Opt,true));
            $Opt->Save();
        }
    }


    public function getOptions()
    {
        return OrderItemOption::getOptionsForItem($this->id);
    }


    /**
     * Get the options display to be shown in the cart and on the order.
     * Returns a string like so:
     *      -- option1: option1_value
     *      -- option2: optoin2_value
     *
     * @param  object  $item   Specific OrderItem object from the cart
     * @return string      Option display
     */
    public function getOptionDisplay()
    {
        $retval = '';

        if (!empty($this->options)) {
            $T = SHOP_getTemplate('view_options', 'options');
            $T->set_block('options', 'ItemOptions', 'ORow');
            foreach ($this->options as $Opt) {
                $T->set_var(array(
                    'opt_name'  => $Opt->attr_name,
                    'opt_value' => strip_tags($Opt->attr_value),
                ) );
                $T->parse('ORow', 'ItemOptions', true);
            }
            $retval .= $T->parse('output', 'options');
        }
        return $retval;
    }

}

?>
