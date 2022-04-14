<?php
/**
 * Class to manage order line items.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Models\Stock;


/**
 * Class for order line items.
 * @package shop
 */
class OrderItem
{
    /** OrderItem DB record ID.
     * @var integer */
    private $id = 0;

    /** Related order ID.
     * @var string */
    private $order_id = '';

    /** Product record ID or string for a plugin item.
     * @var mixed */
    private $product_id = 0;

    /** Product quantity.
     * @var integer */
    private $quantity = 0;

    /** Product SKU, derived from product name and variant SKU.
     * @var string */
    private $sku = '';

    /** Product description.
     * Saved with the orderitem in case the product is updated or deleted.
     * @var string */
    private $dscp = '';

    /** Extra text values.
     * @var array */
    private $extras = array();

    /** Option values text.
     * @var array */
    private $options_text = array();

    /** Base unit price of item, specified in the catalog
     * @var float */
    private $base_price = 0;

    /** Item sale price, if on sale.
     * @var float */
    private $price = 0;

    /** Shipping amount for the line item.
     * @var float */
    private $shipping = 0;

    /** Total number of shipping units for the line item.
     * @var float */
    private $shipping_units = 0;

    /** Handling charge for the line item.
     * @var float */
    private $handling = 0;

    /** Final net unit price of the line item.
     * Includes options, sales, discounts.
     * @var float */
    private $net_price = 0;

    /** Quantity discount percentage applied to the line item.
     * @var float */
    private $qty_discount = 0;

    /** Is the line item taxable?
     * @var boolean */
    private $taxable = 0;

    /** Array of options for this item
     * @var array */
    public $options = array();

    /** Product object.
     * @var object */
    private $Product = NULL;

    /** Product variant ID.
     * @var integer */
    private $variant_id = 0;

    /** Sales tax charged for this item.
     * @var float */
    private $tax = 0;

    /** Sales tax rate for this item.
     * @var float */
    private $tax_rate = 0;

    /** Quantity that has been shipped/fulfilled.
     * @var integer */
    private $qty_shipped = 0;

    /** Expiration timestamp, normally for downloadable items.
     * @var integer */
    private $expiration = 0;

    /** Token, used to verify anonymous download access.
     * @var string */
    private $token = '';

    /** Flag indicating the item failed a zone rule.
     * Not saved to the database.
     * @var boolean;
     */
    private $invalid = false;

    /** ProductVariant object related to this line item.
     * @var object */
    private $Variant = NULL;

    /** Flag to indicate the object has changed and needs to be saved.
     * @var boolean */
    private $_tainted = true;


    /**
     * Constructor.
     * Initializes the order item
     *
     * @param   integer $oi_id  OrderItem record ID
     * @uses    self::Load()
     */
    public function __construct($oi_id = 0)
    {
        if (is_numeric($oi_id) && $oi_id > 0) {
            // Got an item ID, read from the DB
            $status = $this->Read($oi_id);
            if (!$status) {
                $this->id = 0;
            } else {
                $this->options = array();
            }
            $this->Product = $this->getProduct();
        }
    }


    /**
     * Get an instance of a specific order item.
     *
     * @param   integer $oi OrderItem record array or ID
     * @return  object      OrderItem object
     */
    public static function getInstance($oi)
    {
        static $items = array();
        if (is_array($oi)) {
            $oi_id = $oi['oi_id'];
        } else {
            $oi_id = $oi;
        }
        if (!array_key_exists($oi_id, $items)) {
            $items[$oi_id] = new self($oi_id);
        }
        return $items[$oi_id];
    }


    /**
     * Create an OrderItem object from an array of values.
     * May be a DB record, or a partial set of values to create a
     * minimum object.
     *
     * @param   array   $A      Array of key-value pairs
     * @return  object      New OrderItem object
     */
    public static function fromArray(array $A) : object
    {
        $OI = new self;
        $overrides = array();

        if (isset($A['price'])) {
            $overrides['price'] = $A['price'];
        }
        $OI->setVars($A);
        if (!isset($A['base_price'])) {
            $OI->setBasePrice(
                $OI->getProduct()
                   ->setVariant($OI->getVariantId())
                   ->getPrice(array(), 1, $overrides)
            );
        }
        if ($OI->getID() == 0) {
            // New item, add options from the supplied arguments.
            $OI->setPrice($OI->getBasePrice());
            foreach ($OI->getVariant()->getOptions() as $POV) {
                $OIO = new OrderItemOption;
                $OIO->fromProductOptionValue($POV);
                $OI->addOption($OIO);
            }
            // Set the text options description.
            if (isset($A['options_text']) && is_array($A['options_text'])) {
                foreach ($A['options_text'] as $name=>$val) {
                    $OIO = new OrderItemOption;
                    $OIO->setOpt(0, $name, $val);
                    $OI->addOption($OIO);
                }
            }
            if (
                is_array($A['extras']) &&
                isset($A['extras']['custom']) &&
                is_array($A['extras']['custom']) &&
                !empty($A['extras']['custom'])
            ) {
                $cust = $A['extras']['custom'];
                $P = Product::getByID($OI->product_id);
                foreach ($P->getCustom() as $id=>$name) {
                    if (isset($cust[$id]) && !empty($cust[$id])) {
                        $OI->addOptionText($name, $cust[$id]);
                    }
                }
            }
            // Calculate if there's a sale price applicable.
            $OI->setPrice($OI->getProduct()->getSalePrice($OI->getPrice()));
        } else {
            // Existing orderitem record, get the existing options
            $OI->setOptions(OrderItemOption::getOptionsForItem($OI));
            $OI->setVariant(ProductVariant::getInstance($OI->getVariantId()));
        }
        return $OI;
    }


    /**
     * Get all the order items belonging to a specific order.
     *
     * @param   string  $order_id   Order record ID
     * @return  array       Array of OrderItem objects
     */
    public static function getByOrder(string $order_id) : array
    {
        global $_TABLES;

        $items = array();
        $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}
                WHERE order_id = '" . DB_escapeString($order_id) . "'";
        $res = DB_query($sql);
        if ($res && DB_numRows($res) > 0) {
            while ($A = DB_fetchArray($res, false)) {
                $items[$A['id']] = self::fromArray($A);
                $items[$A['id']]->unTaint();
            }
        }
        return $items;
    }


    /**
     * Load the item information.
     *
     * @param   integer $rec_id     DB record ID of item
     * @return  boolean     True on success, False on failure
     */
    public function Read($rec_id)
    {
        global $_SHOP_CONF, $_TABLES;

        $rec_id = (int)$rec_id;
        $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}
                WHERE id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            $this->setVars(DB_fetchArray($res, false));
            $this->options = OrderItemOption::getOptionsForItem($this);
            return true;
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
        if (!is_array($A)) {
            return $this;
        }
        $this->id = SHOP_getVar($A, 'id', 'integer', 0);
        $this->order_id = SHOP_getVar($A, 'order_id');
        $this->product_id = SHOP_getVar($A, 'product_id');
        $this->setSKU(SHOP_getVar($A, 'sku'));
        $this->dscp = SHOP_getVar($A, 'description');
        $this->quantity = SHOP_getVar($A, 'quantity', 'integer', 0);
        $this->expiration = SHOP_getVar($A, 'expiration', 'integer', 0);
        $this->base_price = SHOP_getVar($A, 'base_price', 'float', 0);
        $this->price = SHOP_getVar($A, 'price', 'float', 0);
        $this->setDiscount(SHOP_getVar($A, 'qty_discount', 'float'), 0);
        $this->token = SHOP_getVar($A, 'token');
        $this->net_price = SHOP_getVar($A, 'net_price', 'float', 0);
        if (array_key_exists('extras', $A)) {
            $this->setExtras($A['extras']);
        }
        $this->taxable = SHOP_getVar($A, 'taxable', 'integer') ? 1 : 0;
        $this->shipping = SHOP_getVar($A, 'shipping', 'float', 0);
        $this->shipping_units = SHOP_getVar($A, 'shipping_units', 'float', 0);
        $this->handling = SHOP_getVar($A, 'handling', 'float', 0);
        $this->tax = SHOP_getVar($A, 'tax', 'float', 0);
        $this->tax_rate = SHOP_getVar($A, 'tax_rate', 'float', 0);
        $this->variant_id = SHOP_getVar($A, 'variant_id', 'integer', 0);
        $this->setQtyShipped(SHOP_getVar($A, 'qty_shipped', 'integer', 0));
        return $this;
    }


    /**
     * Get the variant ID for this item.
     *
     * @return  integer     Product Variant ID
     */
    public function getVariantId() : int
    {
        return (int)$this->variant_id;
    }


    /**
     * Get the associated product variant.
     *
     * return   object      ProductVariant object.
     */
    public function getVariant() : object
    {
        static $PVs = array();
        $pov_id = $this->getVariantId();
        if (!isset($PVs[$pov_id])) {
            $PVs[$pov_id] = ProductVariant::getInstance($this->getVariantId());
        }
        return $PVs[$pov_id];
    }


    /**
     * Get the order object related to this item.
     *
     * @return  object  Order Object
     */
    public function getOrder() : Order
    {
        return Order::getInstance($this->order_id);
    }


    /**
     * Get the OrderItemOption object for a specific option by ID.
     *
     * @param   integer $og_id  Option group ID
     * @param   string  $name   Option name, for custom text fields
     * @return  object      OrderItemOption object
     */
    public function getOptionByOG($og_id, $name='')
    {
        if ($this->id < 1) {
            // This could be an empty object if the detail view is not from
            // an order view.
            return NULL;
        }
        if ($og_id > 0) {     // getting a standard option selection
            $key = 'og_id';
            $val = $og_id;
        } elseif ($name != '') {
            $key = 'oio_name';
            $val = $name;
        } else {
            return NULL;
        }
        // Now get the actual option object requested.
        // There's no easy index for this.
        foreach($this->options as $Opt) {
            switch ($key) {
            case 'og_id':
                if ($Opt->getID() == $val) {
                    return $Opt;
                }
                break;
            case 'og_name':
                if ($Opt->getName() == $val) {
                    return $Opt;
                }
                break;
            }
        }
        return NULL;    // Not found
    }


    /**
     * Public function to get the Product object for this item.
     *
     * @return  object      Product object
     */
    public function getProduct()
    {
        if ($this->Product === NULL) {
            $this->Product = Product::getByID($this->product_id);
        }
        return $this->Product;
    }


    /**
     * Set the item description.
     *
     * @param   string  $dscp   Item description
     * @return  object  $this
     */
    public function setDscp($dscp)
    {
        $this->dscp = $dscp;
        return $this;
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


    /**
     * Set the options text based on selected standard options.
     *
     * @return  aray    Array of option desciptions
     */
    public function getOptionsText()
    {
        $retval = array();

        // Add selected options
        $opts = $this->options;
        if (is_string($opts)) {
            $opts = explode(',', $opts);
        }
        foreach ($opts as $opt_id=>$OIO) {
            $retval[] = $OIO->getName() . ': ' . $OIO->getValue();
            /*if (isset($this->getProduct()->Options[$opt_id])) {
                $retval[] = $this->Product->Options[$opt_id]['attr_name'] . ': ' .
                    $this->Product->Options[$opt_id]['attr_value'];
            }*/
        }
        foreach ($this->options_text as $key=>$val) {
            $retval[] = $key . ': ' . $val;
        }

        // Add custom text strings
        /*$cust = explode('|', $this->Product->custom);
        foreach ($cust as $id=>$str) {
            if (
                isset($this->extras['custom'][$id]) &&
                !empty($this->extras['custom'][$id])
            ) {
                $retval[] = $str . ': ' . $this->extras['custom'][$id];
            }
        }*/
        return $retval;
    }


    /**
     * Add an orderitem option object.
     *
     * @param   object  $OIO    OrderItemOption object
     * @return  object  $this
     */
    public function addOption(object $OIO) : object
    {
        $this->options[] = $OIO;
        if ($this->id > 0) {
            $OIO->setOrderItemID($this->id)->saveIfTainted();
        }
        return $this;
    }


    /**
     * Add an option text item to the order item.
     * This allows products to add additional information when purchased,
     * beyond the standard options selected.
     * Updates $this->options only, and saves the option if the current
     * item has already been saved.
     *
     * @param   string  $name   Name of option
     * @param   string  $value  Value of option
     */
    public function addOptionText($name, $value)
    {
        $OIO = new OrderItemOption;
        $OIO->setOrderItemID($this->id);
        //$OIO->order_id = $this->order_id;
        $OIO->setOpt(0, $name, $value);
        $this->options[] = $OIO;
        // Update the Options table now if this is an existing item,
        // othewise it might not get saved.
        if ($this->id > 0) {
            $OIO->setOrderItemID($this->id)->saveIfTainted();
        }
        return $this;
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
    public function addSpecial(string $name, string $value, bool $save=true) : self
    {
        $this->extras['special'][$name] = strip_tags($value);
        if ($save) {
            $this->Save();
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

        $purchase_ts = SHOP_now()->toUnix();
        //$shipping = $this->Product->getShipping($this->quantity);
        $shipping = 0;
        $handling = $this->getProduct()->getHandling($this->quantity);

        if ($this->id > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.orderitems']} ";
            $sql3 = " WHERE id = '{$this->id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.orderitems']} ";
            $sql3 = '';
        }
        $dc_pct = $this->getOrder()->getDiscountPct() / 100;
        if ($dc_pct > 0 && $this->getProduct()->canApplyDiscountCode()) {
            $this->applyDiscountPct($dc_pct);
        }
        $sql2 = "SET order_id = '" . DB_escapeString($this->order_id) . "',
                product_id = '" . DB_escapeString($this->product_id) . "',
                variant_id = '" . (int)$this->variant_id . "',
                sku = '" . DB_escapeString($this->sku) . "',
                description = '" . DB_escapeString($this->dscp) . "',
                quantity = '" . (float)$this->quantity. "',
                qty_discount = '" . (float)$this->qty_discount. "',
                base_price = '" . (float)$this->base_price. "',
                price = '" . (float)$this->price . "',
                net_price = '" . (float)$this->net_price . "',
                taxable = '" . (int)$this->taxable. "',
                token = '" . DB_escapeString($this->token) . "',
                options_text = '" . DB_escapeString(@json_encode($this->options_text)) . "',
                extras = '" . DB_escapeString(json_encode($this->extras)) . "',
                shipping = {$this->shipping},
                shipping_units = {$this->shipping_units},
                tax = {$this->getTax()},
                tax_rate = {$this->getTaxRate()}";
                //options = '" . DB_escapeString($this->options) . "',
                //handling = {$handling},
            // add an expiration date if appropriate
        if ($this->getProduct()->getExpiration() > 0) {
            $sql2 .= ", expiration = " . (string)($purchase_ts + ($this->Product->getExpiration() * 86400));
        }
        $sql = $sql1 . $sql2 . $sql3;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql, 1);
        if (!DB_error()) {
            //Cache::deleteOrder($this->order_id);
            if ($this->id == 0) {
                $this->id = DB_insertID();
                return $this->saveOptions();
            }
            $this->_tainted = false;
            return true;
        } else {
            SHOP_log('SQL Error: ' . $sql, SHOP_LOG_ERROR);
            return false;
        }
    }


    /**
     * Set the order ID.
     * This is used when merging the cart from anonymous to simply update
     * the order ID value.
     *
     * @param   string  $order_id   Order ID
     * @return  object  $this
     */
    public function setOrderID(string $order_id) : object
    {
        if ($this->order_id != $order_id) {
            $this->Taint();
        }
        $this->order_id = $order_id;
        return $this;
    }


    /**
     * Update the quantity for a cart item.
     * Does not save the item since Order::Save() must be called
     * anyway to update shipping, tax, etc.
     *
     * @param   integer $newqty New quantity
     * @param   float|null  $price  Null to calculate price, float to fix price
     * @return  object  $this
     */
    public function setQuantity(float $newqty, float $price=NULL) : object
    {
        if ($newqty >= 0) {
            $this->quantity = (float)$newqty;
            $this->handling = $this->getProduct()->getHandling($newqty);
            if ($price === NULL) {
                $this->setPrice($this->getItemPrice());
                $this->setNetPrice($this->price);
            } else {
                $this->setPrice($price);
                $this->setNetPrice($price);
            }
            $this->setTax($this->net_price * $this->quantity * $this->tax_rate);
        }
        $this->Taint();
        return $this;
    }


    /**
     * Set the item price after any quantity discounts.
     *
     * @param   float   $newprice   New price to set
     * @return  object  $this
     */
    public function setPrice(float $newprice) : object
    {
        if ($this->price != $newprice) {
            $this->Taint();
        }
        $this->price = (float)$newprice;
        return $this;
    }


    /**
     * Set the base price of the order item.
     * This is the total gross price, including options, excluding discounts.
     *
     * @param   float   $newprice   New price to set
     * @return  object  $this
     */
    public function setBasePrice(float $newprice) : object
    {
        if ($this->base_price != $newprice) {
            $this->Taint();
        }
        $this->base_price = (float)$newprice;
        return $this;
    }


    /**
     * Public accessor to set the qty discount as a percentage.
     *
     * @param   float   $disc   Discount to set
     * @return  object  $this
     */
    public function setDiscount($disc)
    {
        if ($this->qty_discount != $disc) {
            $this->Taint();
        }
        $this->qty_discount = (float)$disc;
        return $this;
    }


    /**
     * Get the quantity discount applied to this item.
     *
     * @return  float   Quantity discount
     */
    public function getDiscount()
    {
        return (float)$this->qty_discount;
    }


    /**
     * Set the product related to this order item.
     *
     * @param   object  $PV     ProductVariant object
     * @return  object  $this
     */
    public function setVariant(ProductVariant $PV) : object
    {
        $this->Variant = $PV;
        return $this;
    }


    /**
     * Get the total shipping weight for this item.
     *
     * @return  float   Total weight for this line item
     */
    public function getWeight()
    {
        $weight = $this->getProduct()->getWeight();
        if ($this->variant_id > 0) {
            $weight += ProductVariant::getInstance($this->variant_id)->getWeight();
        }
        $weight *= $this->quantity;
        return $weight;
    }


    /**
     * Check if the buyer can download a file from the order view.
     * If the order is "paid" or "invoiced" then download is available.
     *
     * @return  boolean     True if download is allowed.
     */
    public function canDownload()
    {
        if (
            $this->Product->getFilename() == '' ||
            $this->expiration > 0 && $this->expiration < time()
        ) {
            return false;
        }
        return $this->getOrder()->okToShip();
    }


    /**
     * Get the total number of shipping units for this item
     *
     * @return  float       Shipping units for one of this item
     */
    public function getShippingUnits() : float
    {
        return (float)$this->shipping_units;
    }


    /**
     * Gets the total shipping units for the item.
     *
     * @return  float       Number of shipping units.
     */
    public function getTotalShippingUnits() : float
    {
        return (float)$this->shipping_units * (float)$this->quantity;
    }


    /**
     * Get the product shipping unit amount.
     *
     * @return  float       Shipping units for the product/variant
     */
    public function productShippingUnits() : float
    {
        $units = $this->Product->getShippingUnits();
        if ($this->variant_id > 0) {
            $units += ProductVariant::getInstance($this->variant_id)->getShippingUnits();
        }
        $units *= $this->quantity;
        return $units;
    }


    /**
     * Set the total shipping amount for this item.
     *
     * @param   float   $amt    Total shipping amount
     * @return  object  $this
     */
    public function setShipping($amt)
    {
        $this->shipping = (float)$amt;
        return $this;
    }


    /**
     * Get the total of all per-item shipping costs for this item
     *
     * @return  float       Total fixed shipping cost (per-product * quantity)
     */
    public function getShipping()
    {
        return $this->shipping;
    }


    /**
     * Set the total number of shipping units related to this line item.
     * Quantity x units_per_unit
     *
     * @param   float   $units      Total shipping units
     * @return  object  $this
     */
    public function setShippingUnits(float $units) : object
    {
        $this->shipping_units = (float)$units;
        return $this;
    }


    /**
     * Get the handling charge for this item.
     *
     * @return  float       Total handling charge for this line item
     */
    public function getHandling()
    {
        return (float)$this->handling;
    }


    /**
     * Set the quantity shipped/fulfilled.
     *
     * @param   integer $qty    Item quantity
     * @return  object  $this
     */
    public function setQtyShipped($qty)
    {
        $this->qty_shipped = (int)$qty;
        return $this;
    }


    /**
     * Get the quantity shipped/fulfilled.
     *
     * @return  integer     Item quantity
     */
    public function getQtyShipped()
    {
        return (int)$this->qty_shipped;
    }


    /**
     * Set the item as "embargoed" due to failing a zone rule.
     *
     * @param   boolean $flag   True to prevent ordering this item
     * @return  object  $this
     */
    public function setInvalid($flag)
    {
        $this->invalid = $flag ? 1: 0;
        return $this;
    }


    /**
     * Get the embargoed status flag.
     *
     * @return  boolean     True if embargoed, False if not
     */
    public function getInvalid()
    {
        return $this->invalid ? 1 : 0;
    }


    /**
     * Convert from one currency to another.
     * Also saves the item.
     *
     * @param   string  $old    Original currency
     * @param   string  $new    New currency
     * @return  object  $this
     */
    public function convertCurrency($old, $new)
    {
        if ($new != $old) {
            foreach (array('price') as $fld) {
                $this->$fld = Currency::Convert($this->$fld, $new, $old);
            }
            $this->Save();
        }
        return $this;
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
     * Update one or more orderitem fields from an array of updates.
     *
     * @param   array   $updates    Array of fld_name=>new_value
     * @return  object  $this
     */
    public function updateItem($updates)
    {
        foreach ($updates as $fld=>$val) {
            $this->$fld = $val;
        }
    }


    /**
     * Set the provided array of ProductOptionValues into the private var.
     *
     * @param   array   $opts   Array of ProductOptionValues
     * @return  object  $this
     */
    public function setOptionsFromPOV(array $opts) : object
    {
        if (empty($opts)) {
            return $this;
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
                $OIO = new OrderItemOption;
                $OIO->setOpt($POV->getID());
                $OIO->setOrderItemID($this->id);
                $this->options[] = $OIO;
            }
        }
        return $this;
    }



    /**
     * Set the provided array of OrderItemOptions into the private var.
     *
     * @param   array   $opts   Array of ProductOptionValues
     * @return  object  $this
     */
    public function setOptions(array $opts) : object
    {
        if (empty($opts)) {
            return $this;
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
            foreach ($opts as $OIO) {
                $OIO->setOrderItemID($this->id);
                $this->options[] = $OIO;
            }
        }
        return $this;
    }


    /**
     * Save all the options to the database.
     *
     * @retun   boolean     True on success, False on failure
     */
    public function saveOptions()
    {
        foreach ($this->options as $Opt) {
            $Opt->setOrderItemID($this->id);
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
        return $this->options;
        //return OrderItemOption::getOptionsForItem($this);
    }


    /**
     * Get the option IDs as a string for the product ID.
     *
     * @return  string      Comma-separated list of option IDs
     */
    public function getOptionIdString()
    {
        $ids = array();
        foreach ($this->getOptions() as $Opt) {
            $ids[] = $Opt->getOptionID();
        }
        return implode(',', $ids);
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
        if (!empty($this->options_text)) {
            $T = new Template;
            $T->set_file('options', 'view_options.thtml');
            $T->set_block('options', 'ItemOptions', 'ORow');
            foreach ($this->options_text as $key=>$val) {
                $T->set_var(array(
                    'opt_name'  => strip_tags($key),
                    'opt_value' => strip_tags($val),
                ) );
                $T->parse('ORow', 'ItemOptions', true);
            }
            $retval .= $T->parse('output', 'options');
        }
        if (!empty($this->options)) {
            $T = new Template;
            $T->set_file('options', 'view_options.thtml');
            $T->set_block('options', 'ItemOptions', 'ORow');
            foreach ($this->options as $Opt) {
                $T->set_var(array(
                    'opt_name'  => $Opt->getName(),
                    'opt_value' => strip_tags($Opt->getValue()),
                ) );
                $T->parse('ORow', 'ItemOptions', true);
            }
            $retval .= $T->parse('output', 'options');
        }
        return $retval;
    }


    public function getExtraDisplay()
    {
        global $LANG_SHOP;

        $retval = '';
        if (is_array($this->extras) && isset($this->extras['special'])) {
            $T = new Template;
            $T->set_file('options', 'view_options.thtml');
            $T->set_block('options', 'ItemOptions', 'ORow');
            foreach ($this->extras['special'] as $name=>$val) {
                $T->set_var(array(
                    'opt_name'  => isset($LANG_SHOP[$name]) ? $LANG_SHOP[$name] : $name,
                    'opt_value' => strip_tags($val),
                ) );
                $T->parse('ORow', 'ItemOptions', true);
            }
            $retval .= $T->parse('output', 'options');
        }
        return $retval;
    }


    /**
     * Set the Extra values into the private array.
     *
     * @param   string|array    $value  Extra values array or json string
     * @return  object  $this
     */
    public function setExtras($value)
    {
        if (is_string($value)) {    // convert to array
            $value = @json_decode($value, true);
        }
        if (!$value) $value = array();
        $this->extras = $value;
        return $this;
    }


    /**
     * Get the value of some "extra" item.
     *
     * @param   string  $key    Item name
     * @return  mixed       Value of extras[$key], NULL if not set
     */
    public function getExtra($key)
    {
        if (isset($thie->extras[$key])) {
            return $this->extras[$key];
        } else {
            return NULL;
        }
    }


    /**
     * Get the entire "extras" array.
     *
     * @return  array       Array of all extra info
     */
    public function getExtras()
    {
        return $this->extras;
    }


    /**
     * Set the options text values into the private array.
     *
     * @param   string|array    $value  Text values array or json string
     * @return  object  $this
     */
    public function setOptionsText($value=array())
    {
        if (is_string($value)) {    // convert to array
            $value = @json_decode($value, true);
            if (!$value) $value = array();
        }
        $this->options_text  = $value;
        return $this;
    }


    /**
     * Delete an order item and related options from the database.
     *
     * @see     Cart::Remove()
     */
    public function Delete()
    {
        global $_TABLES;

        $P = $this->getProduct();
        $P->setVariant($this->Variant)
          ->reserveStock($this->getQuantity() * -1);
        DB_delete($_TABLES['shop.orderitems'], 'id', $this->getID());
        OrderItemOption::deleteItem($this->getID());
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
        $PV = $this->getVariant();
        if ($PV->getID() > 0) {
            $retval = $this->getVariant()->getPrice();
        } else {
            $retval = 0;
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
        if (!Product::isPluginItem($this->product_id)) {
            //$retval = $this->Product->getDiscountedPrice($this->quantity, $this->getOptionsPrice());
            $retval = $this->Product->setVariant($this->getVariant())->getPrice(array(), $this->quantity);
            $retval = $this->Product->getSalePrice($retval);
        } else {
            $retval = $this->price;
        }
        return $retval;
    }


    /**
     * Get the base item price including options and extras.
     *
     * @return  float       Item base price
     */
    public function getBasePrice()
    {
        return (float)$this->base_price;
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
     * Shortcut function to get the gross line item extension.
     * Includes option prices, excludes discounts.
     *
     * @return  float   Item price * quantity
     */
    public function getGrossExtension()
    {
        return (float)$this->price * (float)$this->quantity;
    }


    /**
     * Shortcut function to get the net line item extension, after discount.
     *
     * @return  float   Item price * quantity
     */
    public function getNetExtension()
    {
        return (float)$this->net_price * (float)$this->quantity;
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
        return (int)$this->id;
    }


    /**
     * Set the net price for the item.
     *
     * @param   float   $price  New net price
     * @return  object  $this
     */
    public function setNetPrice($price)
    {
        $this->net_price = (float)$price;
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
        // Normally this should be a percentage, but in case a whole number
        // is provided, convert it
        if ($pct > 1) {
            $pct = $pct / 100;
        }
        if ($this->getProduct()->canApplyDiscountCode()) {
            $price = $this->getPrice() * (1 - $pct);
            $this->setNetPrice(Currency::getInstance()->RoundVal($price));
            $this->setTax($this->net_price * $this->quantity * $this->tax_rate);
        }
        return $this;
    }


    /**
     * Set the total sales tax amount for this item.
     *
     * @param   float   $tax    Tax amount
     * @return  object  $this
     */
    public function setTax($tax)
    {
        $newtax = (float)$this->getOrder()->getCurrency()->RoundVal($tax);
        if ($this->tax != $newtax) {
            $this->Taint();
        }
        $this->tax = $newtax;
        return $this;
    }


    /**
     * Get the total tax amount for this line item.
     *
     * @return  float       Total sales tax for the item
     */
    public function getTax()
    {
        return (float)$this->tax;
    }


    /**
     * Set the sales tax rate charged for this item.
     *
     * @param   float   $rate   Sales tax rate
     * @return  object  $this
     */
    public function setTaxRate($rate)
    {
        if ($this->tax_rate != $rate) {
            $this->Taint();
        }
        $this->tax_rate = (float)$rate;
        if ($this->taxable) {
            $this->setTax($this->quantity * $this->net_price * $this->tax_rate);
        } else {
            $this->setTax(0);
        }
        return $this;
    }


    /**
     * Get the sales tax rate applied to this item.
     *
     * @return  float       Sales tax rate
     */
    public function getTaxRate()
    {
        return (float)$this->tax_rate;
    }


    /**
     * Get the taxable status of the item.
     *
     * @return  integer     1 if taxable, 0 if not
     */
    public function getTaxable()
    {
        return $this->taxable ? 1 : 0;
    }


    /**
     * Get the SKU (product name) for the item.
     *
     * @return  string      Item SKU
     */
    public function getSKU()
    {
        return $this->sku;
    }


    /**
     * Check if this order item is from a plugin.
     *
     * @return  boolean     True if it is a plugin item, False if catalog
     */
    public function isPluginItem()
    {
        return Product::isPluginItem($this->product_id);
    }


    /**
     * Set the SKU, creating from the variant or product name if empty.
     *
     * @param   string  $sku    SKU, empty if not known
     * @return  object  $this
     */
    public function setSKU($sku='')
    {
        if (empty($sku) && !$this->isPluginItem()) {
            if ($this->variant_id > 0) {
                // Check for a variant, it already has the full SKU.
                $sku = ProductVariant::getInstance($this->variant_id)->getSKU();
            } else {
                // Get the base product SKU field.
                $sku = Product::getInstance($this->product_id)->getName();
            }
        }
        if ($this->sku != $sku) {
            $this->Taint();
        }
        $this->sku = $sku;
        return $this;
    }


    /**
     * Save this item if it has been changed.
     *
     * @return  object  $this
     */
    public function saveIfTainted() : object
    {
        if ($this->isTainted()) {
            $this->Save();
        }
        return $this;
    }


    /**
     * Check if the record is "tainted" by values being changed.
     *
     * @return  boolean     True if tainted and needs to be saved
     */
    public function isTainted() : bool
    {
        return $this->_tainted;
    }


    /**
     * Taint this object, indicating that something has changed.
     *
     * @return  object  $this
     */
    public function Taint() : object
    {
        $this->_tainted = true;
        return $this;
    }


    /**
     * Remove the taint flag.
     *
     * @return  object  $this
     */
    public function unTaint() : object
    {
        $this->_tainted = false;
        return $this;
    }

}
