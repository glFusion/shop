<?php
/**
 * Class to manage product variants.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for order line items.
 * @package shop
 */
class ProductVariant
{
    /** Variant record ID.
     * @var integer */
    private $pv_id;

    /** Product record ID.
     * @var integer */
    private $item_id;

    /** Variant description.
     * @var string */
    private $dscp;

    /** Price impact amount.
     * @var float */
    private $price;

    /** Weight impact.
     * @var float */
    private $weight;

    /** Shipping Units impact.
     * @var float */
    private $shipping_units;

    /** Variant SKU.
     * @var string */
    private $sku;

    /** Quantity on hand.
     * @var float */
    private $onhand;


    private $Options = NULL;

    /**
     * Constructor.
     * Initializes the variant variables
     *
     * @param   integer $pv_id  Variant record ID
     * @uses    self::Load()
     */
    public function __construct($pv_id = 0)
    {
        if (is_numeric($pv_id) && $pv_id > 0) {
            // Got an item ID, read from the DB
            $status = $this->Read($pv_id);
            if (!$status) {
                $this->pv_id = 0;
            }
        } elseif (is_array($pv_id) && isset($pv_id['pv_id'])) {
            // Got an item record, just set the variables
            $this->setVars($pv_id);
        }
    }


    /**
     * Get an instance of a specific order item.
     *
     * @param   integer $oi OrderItem record array or ID
     * @return  object      OrderItem object
     */
    public static function getInstance($pv)
    {
        static $items = array();
        if (is_array($pv)) {
            $pv_id = $pv['pv_id'];
        } else {
            $pv_id = $pv;
        }

        if (!array_key_exists($pv_id, $items)) {
            $items[$pv_id] = new self($pv);
        }
        return $items[$pv_id];
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
        $sql = "SELECT * FROM {$_TABLES['shop.product_variants']}
                WHERE pv_id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            $this->setVars(DB_fetchArray($res, false));
            $this->loadOptions();
            $this->makeDscp();
            return true;
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
        if (is_array($A)) {
            $this->setId($A['pv_id'])
                ->setItemId($A['item_id'])
                ->setDscp($A['dscp'])
                ->setPrice($A['price'])
                ->setWeight($A['weight'])
                ->setShippingUnits($A['shipping_units'])
                ->setSku($A['sku'])
                ->setOnhand($A['onhand']);
        }
        return $this;
    }

// todo item ID needed?
    public static function getByAttributes($item_id, $attribs)
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        $count = count($attribs);
        $attr_sql = implode(',', $attribs);
        $sql = "SELECT va.pv_id FROM {$_TABLES['shop.variantXopt']} va
            INNER JOIN {$_TABLES['shop.product_variants']} pv
                ON va.pv_id = pv.pv_id
            WHERE va.pov_id IN ($attr_sql)
            GROUP BY pv.pv_id
            HAVING COUNT(pv.item_id) = $count
            LIMIT 1";
        //echo $sql;
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            return self::getInstance($A['pv_id']);
        } else {
            return new Self;
        }
    }

    /**
     * Load the product attributs into the options array.
     *
     * @return  object  $this
     */
    private function loadOptions()
    {
        global $_TABLES;

        if ($this->Options === NULL) {
            $this->Options = array();
            $sql = "SELECT pov.*, pog.pog_name FROM {$_TABLES['shop.prod_opt_vals']} pov
                INNER JOIN {$_TABLES['shop.variantXopt']} vx
                    ON vx.pov_id = pov.pov_id
                INNER JOIN {$_TABLES['shop.prod_opt_grps']} pog
                    ON pog.pog_id = pov.pog_id
                WHERE vx.pv_id = {$this->pv_id}
                ORDER BY pog.pog_orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $this->Options[$A['pog_name']] = new ProductOptionValue($A);
            }
        }
        return $this;
    }


    /**
     * Set the record ID property.
     *
     * @param   integer $rec_id     Record ID
     * @return  object  $this
     */
    public function setId($rec_id)
    {
        $this->pv_id = (int)$rec_id;
        return $this;
    }


    /**
     * Set the product ID.
     *
     * @param   integer $rec_id     Record ID
     * @return  object  $this
     */
    public function setItemId($rec_id)
    {
        $this->item_id = (int)$rec_id;
        return $this;
    }


    public function setDscp($dscp)
    {
        $this->dscp = $dscp;
        return $this;
    }


    public function setPrice($price)
    {
        $this->price = (float)$price;
        return $this;
    }


    public function setWeight($weight)
    {
        $this->weight = (float)$weight;
        return $this;
    }


    public function setShippingUnits($units)
    {
        $this->units= (float)$units;
        return $this;
    }


    public function setOnhand($onhand)
    {
        $this->onhand = (float)$onhand;
        return $this;
    }


    public function setSku($sku)
    {
        $this->sku = $sku;
        return $this;
    }


    public static function getByProduct($product_id)
    {
        global $_TABLES;

        $retval = array();
        $product_id = (int)$product_id;
        $sql = "SELECT * FROM {$_TABLES['shop.product_variants']}
            WHERE item_id = '$product_id'";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = self::getInstance($A);
        }
        return $retval;
    }


    /**
     * Public function to get the Product object for this item.
     *
     * @return  object      Product object
     */
    public function getProduct()
    {
        if ($this->product === NULL) {
            $this->product = Product::getByID($this->product_id);
        }
        return $this->product;
    }


    /**
     * Get the item long description.
     *
     * @return  string      Item description
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    public function getDscpHTML()
    {
        $retval = '';
        foreach ($this->dscp as $dscp) {
            $retval .= " -- {$dscp['name']}: {$dscp['value']}<br />\n";
        }
        return $retval;
    }

    public function getDscpString()
    {
        $retval = array();
        foreach ($this->dscp as $dscp) {
            $retval[] = "{$dscp['name']}:{$dscp['value']}";
        }
        $retval = implode(', ', $retval);
        return $retval;
    }


    private function makeDscp()
    {
        $this->dscp = array();
        foreach ($this->Options as $name=>$POV) {
            $this->dscp[] = array(
                'name' => $name,
                'value' => $POV->getValue(),
            );
        }
        return $this;
    }


    /**
     * Save a variant to the database.
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
        $this->options_text = $this->getOptionsText();

        if ($this->id > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.orderitems']} ";
            $sql3 = " WHERE id = '{$this->id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.orderitems']} ";
            $sql3 = '';
        }
        $dc_pct = $this->getOrder()->getDiscountPct() / 100;
        if ($dc_pct > 0) {
            $this->net_price = $this->price * (1 - $dc_pct);
        } else {
            $this->net_price = $this->price;
        }
        $sql2 = "SET order_id = '" . DB_escapeString($this->order_id) . "',
                product_id = '" . DB_escapeString($this->product_id) . "',
                description = '" . DB_escapeString($this->description) . "',
                quantity = '" . (float)$this->quantity. "',
                txn_id = '" . DB_escapeString($this->txn_id) . "',
                txn_type = '" . DB_escapeString($this->txn_type) . "',
                qty_discount = '" . (float)$this->qty_discount. "',
                base_price = '" . (float)$this->base_price. "',
                price = '" . (float)$this->price . "',
                net_price = '" . (float)$this->net_price . "',
                taxable = '" . (int)$this->taxable. "',
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
            //Cache::deleteOrder($this->order_id);
            if ($this->id == 0) {
                $this->id = DB_insertID();
                return $this->saveOptions();
            }
            return true;
        } else {
            return false;
        }
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
        return OrderItemOption::MatchAll($this->options, $Item2->options);
    }


    /**
     * Set the provided array of options into the private var.
     *
     * @param   array   $opts   Array of ProductOptionValues
     * @return  array       Contents of $this->options
     */
    public function setOptions($opts)
    {
        if (empty($opts)) {
            return $this->options;
        }
        if (is_string($opts)) {
            // todo: deprecate
            $opt_ids = explode(',', $opts);
            $opts = array();
            foreach($opt_ids as $opt_id) {
                $opts[] = new ProductOptionValue($opt_id);
            }
        }

        if (is_array($opts)) {
            foreach ($opts as $POV) {
                //if ($opt_id > 0) {      // Don't set non-standard options here
                $OIO = new OrderItemOption;
                    $OIO->setOpt($POV->getID());
                    $OIO->oi_id = $this->id;
                    $this->options[] = $OIO;
                //}
            }
        }
        return $this->options;
    }


    /**
     * Save all the options to the database.
     *
     * @retun   boolean     True on success, False on failure
     */
    public function saveOptions()
    {
        foreach ($this->options as $Opt) {
            $Opt->oi_id = $this->id;
            $Opt->Save();
        }
        return true;
    }


    /**
     * Get the options from the database for this order item.
     *
     * @return  aray    Array of option objects
     */
    public function getOptions()
    {
        return OrderItemOption::getOptionsForItem($this);
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
                    'opt_name'  => $Opt->oio_name,
                    'opt_value' => strip_tags($Opt->oio_value),
                ) );
                $T->parse('ORow', 'ItemOptions', true);
            }
            $retval .= $T->parse('output', 'options');
        }
        return $retval;
    }


    /**
     * Delete an order item and related options from the database.
     *
     * @see     Cart::Remove()
     * @param   integer $id     Order item record ID
     */
    public static function Delete($id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.orderitems'], 'id', (int)$id);
        OrderItemOption::deleteItem($id);
    }


    /**
     * Check if the current user is allowed to view this order and its items.
     * Also returns false if this is an empty object.
     *
     * @return  boolean     True if view access is granted, False if not
     */
    public function canView()
    {
        if ($this->id < 1) {
            return false;
        } else {
            return $this->getOrder()->canView();
        }
    }


    /**
     * Get the total list price of options selected for this item.
     *
     * @return  float       Total options price
     */
    public function getOptionsPrice()
    {
        $retval = 0;
        foreach ($this->options as $OIO) {
            $retval += $OIO->oio_price;
        }
        return $retval;
    }


    /**
     * Get the total unit price per item from the Product object.
     * Used when creating order items and updating prices in the cart
     * based on qty discounts.
     *
     * @return  float       Current item price, including discounts and options
     */
    public function getItemPrice()
    {
        return $this->product->getDiscountedPrice($this->quantity, $this->getOptionsPrice());
    }


    /**
     * Just return the item price property.
     *
     * @return  float       Item price, including all options and qty discounts.
     */
    public function getPrice()
    {
        return (float)$this->price;
    }


    /**
     * Get the net price after any discount codes.
     *
     * @return  float       Item net price.
     */
    public function getNetPrice()
    {
        return (float)$this->net_price;
    }


    /**
     * Get the item quantity.
     *
     * @return  integer     Item quantity
     */
    public function getQuantity()
    {
        return (float)$this->quantity;
    }


    /**
     * Get the product ID for this order item.
     *
     * @return  string      Product ID
     */
    public function getProductId()
    {
        return $this->product_id;
    }


    /**
     * Get the database record ID of this item.
     *
     * @return  integer     DB record ID
     */
    public function getID()
    {
        return $this->id;
    }


    /**
     * Set the net price for the item.
     *
     * @param   float   $price  New net price
     * @return  object  $this
     */
    public function setNetPrice($price)
    {
        $this->net_price = $price;
        return $this;
    }


    /**
     * Get the random token for this item.
     *
     * @return  string      Token string
     */
    public function getToken()
    {
        return $this->token;
    }


    /**
     * Return taxable status of this item.
     *
     * @return  integer     1 if item is taxable, 0 if not
     */
    public function isTaxable()
    {
        return $this->taxable;
    }


    /**
     * Apply a discount percentage to this item.
     *
     * @param   float   $pct    Discount percent, as a whole number
     * @return  object  $this
     */
    public function applyDiscountPct($pct)
    {
        $price = $this->getPrice() * (100 - $pct) / 100;
        $this->setNetPrice(Currency::getInstance()->RoundVal($price));
        return $this;
    }
}

?>
