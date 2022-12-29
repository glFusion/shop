<?php
/**
 * Class to manage order line items.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use Shop\Models\Stock;
use Shop\Models\ProductCheckbox;
use Shop\Models\DataArray;
use Shop\Util\JSON;


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

    /** Number of shipping units for a unit of this item.
     * @var float */
    private $shipping_units = 0;

    /** Shipping weight for a unit of this item.
     * @var float */
    private $shipping_weight = 0;

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
        $A = DataArray::fromArray($A);  // convert to object
        $OI->setVars($A);
        if (!isset($A['base_price'])) {
            $item_price = $OI->getProduct()
                             ->setVariant($OI->getVariantId())
                             ->getPrice(array(), 1, $overrides);
        } else {
            $item_price = $A['base_price'];
        }
        if (isset($A['options_price'])) {
            $item_price += $A['options_price'];
        }
        $OI->setBasePrice($item_price);

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
            if (is_array($A['extras'])) {
                if (
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
                // Now get custom/checkbox options
                if (
                    isset($A['extras']['options']) &&
                    is_array($A['extras']['options']) &&
                    !empty($A['extras']['options'])
                ) {
                    foreach ($A['extras']['options'] as $opt_id) {
                        $OIO = new OrderItemOption;
                        $POV = new ProductCheckbox;
                        if ($POV->withItemId((int)$OI->product_id)
                            ->withOptionId((int)$opt_id)
                            ->Read()
                        ) {
                            $OIO->withOptionGroupId($POV->getGroupId())
                                ->withOptionValueId($POV->getOptionId())
                                ->withOptionName($POV->getGroupName())
                                ->withOptionValue($POV->getOptionValue())
                                ->withOptionPrice($POV->getPrice());
                            $OI->addOption($OIO);
                        }
                    }
                }
            }
            if (!isset($A['shipping_weight'])) {
                // Get the shipping weight from the product if not included.
                $OI->setShippingWeight($OI->getProduct()->getWeight());
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
        try {
            $stmt = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.orderitems']} WHERE order_id = ?",
                array($order_id),
                array(Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
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
    public function Read(int $rec_id) : bool
    {
        global $_SHOP_CONF, $_TABLES;

        $rec_id = (int)$rec_id;
        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.orderitems']} WHERE id = ?",
                array($rec_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $this->setVars(new DataArray($row));
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
    public function setVars(DataArray $A) : self
    {
        /*if (!is_array($A)) {
            return $this;
    }*/
        $this->id = $A->getInt('id');
        $this->order_id = $A->getString('order_id');
        $this->product_id = $A->getString('product_id');
        $this->variant_id = $A->getInt('variant_id');
        $this->dscp = $A->getString('description');
        $this->quantity = $A->getInt('quantity');
        $this->expiration = $A->getInt('expiration');
        $this->base_price = $A->getFloat('base_price');
        $this->price = $A->getFloat('price');
        $this->setDiscount($A->getFloat('qty_discount'));
        $this->token = $A->getString('token');
        $this->net_price = $A->getFloat('net_price');
        $this->setExtras($A['extras']);
        $this->taxable = $A->getInt('taxable');
        $this->shipping = $A->getFloat('shipping');
        $this->shipping_units = $A->getFloat('shipping_units');
        $this->shipping_weight = $A->getFloat('shipping_weight');
        $this->handling = $A->getFloat('handling');
        $this->tax = $A->getFloat('tax');
        $this->tax_rate = $A->getFloat('tax_rate');
        $this->setQtyShipped($A->getInt('qty_shipped'));
        $this->setSKU($A->getString('sku'));
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
        if ($this->Variant === NULL) {
            $this->Variant = ProductVariant::getInstance($this->getVariantId());
        }
        return $this->Variant;
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
    public function Save(?array $A= NULL) : bool
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A);
        }

        $purchase_ts = SHOP_now()->toUnix();
        //$shipping = $this->Product->getShipping($this->quantity);
        $shipping = 0;
        $handling = $this->getProduct()->getHandling($this->quantity);
        $values = array(
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'sku' => $this->sku,
            'description' => $this->dscp,
            'quantity' => $this->quantity,
            'qty_discount' => $this->qty_discount,
            'base_price' => $this->base_price,
            'price' => $this->price,
            'net_price' => $this->net_price,
            'taxable' => $this->taxable,
            'token' => $this->token,
            'options_text' => JSON::encode($this->options_text),
            'extras' => JSON::encode($this->extras),
            'shipping' => $this->shipping,
            'shipping_units' => $this->shipping_units,
            'shipping_weight' => $this->shipping_weight,
            'tax' => $this->getTax(),
            'tax_rate' => $this->getTaxRate(),
            //'options' => $this->options,
            //'handling' => $handling,
        );
        $types = array(
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
        );
        // add an expiration date if appropriate
        if ($this->getProduct()->getExpiration() > 0) {
            $values['expiration'] = (string)($purchase_ts + ($this->Product->getExpiration() * 86400));
            $types[] = Database::STRING;
        }

        $dc_pct = $this->getOrder()->getDiscountPct() / 100;
        if ($dc_pct > 0 && $this->getProduct()->canApplyDiscountCode()) {
            $this->applyDiscountPct($dc_pct);
        }

        $db = Database::getInstance();
        try {
            if ($this->id > 0) {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.orderitems'],
                    $values,
                    array('id' => $this->id),
                    $types
                );
            } else {
                $db->conn->insert(
                    $_TABLES['shop.orderitems'],
                    $values,
                    $types
                );
                $this->id = $db->conn->lastInsertId();
                return $this->saveOptions();
            }
            $this->_tainted = false;
            return true;
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
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
    public function setDiscount(float $disc) : self
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
     * Get the unit shipping weight for this line item.
     *
     * @return  float       Shipping units for one of this item
     */
    public function getShippingWeight() : float
    {
        return (float)$this->shipping_weight;
    }


    /**
     * Gets the total shipping weight for the item.
     *
     * @return  float       Total shipping weight for this line item
     */
    public function getTotalShippingWeight() : float
    {
        return (float)$this->shipping_weight * (float)$this->quantity;
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
    public function setShipping(float $amt) : self
    {
        $this->shipping = (float)$amt;
        return $this;
    }


    /**
     * Get the total of all per-item shipping costs for this item
     *
     * @return  float       Total fixed shipping cost (per-product * quantity)
     */
    public function getShipping() : float
    {
        return $this->shipping;
    }


    /**
     * Set the total number of shipping units related to this line item.
     * Quantity x units_per_unit
     *
     * @param   float   $units      Unit shipping units
     * @return  object  $this
     */
    public function setShippingUnits(float $units) : object
    {
        $this->shipping_units = (float)$units;
        return $this;
    }


    /**
     * Set the total number of shipping units related to this line item.
     * Quantity x units_per_unit
     *
     * @param   float   $weight     Unit shipping weight
     * @return  object  $this
     */
    public function setShippingWeight(float $wt) : object
    {
        $this->shipping_weight = (float)$wt;
        return $this;
    }


    /**
     * Get the handling charge for this item.
     *
     * @return  float       Total handling charge for this line item
     */
    public function getHandling() : float
    {
        return (float)$this->handling;
    }


    /**
     * Set the quantity shipped/fulfilled.
     *
     * @param   integer $qty    Item quantity
     * @return  object  $this
     */
    public function setQtyShipped(int $qty) : self
    {
        $this->qty_shipped = (int)$qty;
        return $this;
    }


    /**
     * Get the quantity shipped/fulfilled.
     *
     * @return  integer     Item quantity
     */
    public function getQtyShipped() : int
    {
        return (int)$this->qty_shipped;
    }


    /**
     * Set the item as "embargoed" due to failing a zone rule.
     *
     * @param   boolean $flag   True to prevent ordering this item
     * @return  object  $this
     */
    public function setInvalid(bool $flag=true) : self
    {
        $this->invalid = $flag ? 1: 0;
        return $this;
    }


    /**
     * Get the embargoed status flag.
     *
     * @return  boolean     True if embargoed, False if not
     */
    public function getInvalid() : bool
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
    public function convertCurrency(string $old, string $new) : self
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
    public function Matches(OrderItem $Item2) : bool
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
    public function updateItem(array $updates) : self
    {
        foreach ($updates as $fld=>$val) {
            $this->$fld = $val;
        }
        return $this;
    }


    /**
     * Set the provided array of ProductOptionValues into the private var.
     *
     * @param   array   $opts   Array of ProductOptionValues
     * @return  object  $this
     */
    public function setOptionsFromPOV(array $opts) : self
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
    public function setOptions(array $opts) : self
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
     * @return   boolean     True on success, False on failure
     */
    public function saveOptions() : bool
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
    public function getOptions() : array
    {
        return $this->options;
    }


    /**
     * Get the option IDs as a string for the product ID.
     *
     * @return  string      Comma-separated list of option IDs
     */
    public function getOptionIdString() : string
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
     * @return string      Option display
     */
    public function getOptionDisplay() : string
    {
        $retval = '';
        $opts = array();    // local var to collect option names and values
        if (!empty($this->options_text)) {
            foreach ($this->options_text as $key=>$val) {
                $opts[] = array('name' => $key, 'value' => $val);
            }
        }
        if (!empty($this->options)) {
            foreach ($this->options as $Opt) {
                $opts[] = array('name' => $Opt->getName(), 'value' => $Opt->getValue());
            }
        }
        if (!empty($opts)) {
            // This is double work, but saves instantiating the template if not needed.
            $T = new Template;
            $T->set_file('options', 'view_options.thtml');
            $T->set_block('options', 'ItemOptions', 'ORow');
            foreach ($opts as $opt_arr) {
                $T->set_var(array(
                    'opt_name'  => strip_tags($opt_arr['name']),
                    'opt_value' => strip_tags($opt_arr['value']),
                ) );
                $T->parse('ORow', 'ItemOptions', true);
            }
            $retval .= $T->parse('output', 'options');
        }
        return $retval;
    }


    /**
     * Get the display for extra items like text input fields.
     * Returns a string like so:
     *      -- option1: option1_value
     *      -- option2: optoin2_value
     *
     * @return string      Option display
     */
    public function getExtraDisplay() : string
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
    public function setExtras($value) : self
    {
        if (is_string($value)) {    // convert to array
            $value = JSON::decode($value);
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
    public function getExtra(string $key) : ?string
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
    public function getExtras() : array
    {
        return $this->extras;
    }


    /**
     * Set the options text values into the private array.
     *
     * @param   string|array    $value  Text values array or json string
     * @return  object  $this
     */
    public function setOptionsText($value=array()) : self
    {
        if (is_string($value)) {    // convert to array
            $value = JSON::decode($value);
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
    public function Delete() : void
    {
        global $_TABLES;

        $P = $this->getProduct();
        $P->setVariant($this->Variant)
          ->reserveStock($this->getQuantity() * -1);
        try {
            Database::getInstance()->conn->delete(
                $_TABLES['shop.orderitems'],
                array('id' => $this->getID()),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        OrderItemOption::deleteItem($this->getID());
    }


    /**
     * Check if the current user is allowed to view this order and its items.
     * Also returns false if this is an empty object.
     *
     * @return  boolean     True if view access is granted, False if not
     */
    public function canView() : bool
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
    public function getOptionsPrice() : float
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
    public function getItemPrice() : float
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
    public function getBasePrice() : float
    {
        return (float)$this->base_price;
    }



    /**
     * Just return the item price property.
     *
     * @return  float       Item price, including all options and qty discounts.
     */
    public function getPrice() : float
    {
        return (float)$this->price;
    }


    /**
     * Get the net price after any discount codes.
     *
     * @return  float       Item net price.
     */
    public function getNetPrice() : float
    {
        return (float)$this->net_price;
    }


    /**
     * Get the item quantity.
     *
     * @return  integer     Item quantity
     */
    public function getQuantity() : int
    {
        return (float)$this->quantity;
    }


    /**
     * Shortcut function to get the gross line item extension.
     * Includes option prices, excludes discounts.
     *
     * @return  float   Item price * quantity
     */
    public function getGrossExtension() : float
    {
        return (float)$this->price * (float)$this->quantity;
    }


    /**
     * Shortcut function to get the net line item extension, after discount.
     *
     * @return  float   Item price * quantity
     */
    public function getNetExtension() : float
    {
        return (float)$this->net_price * (float)$this->quantity;
    }


    /**
     * Get the product ID for this order item.
     *
     * @return  string      Product ID
     */
    public function getProductId() : string
    {
        return $this->product_id;
    }


    /**
     * Get the database record ID of this item.
     *
     * @return  integer     DB record ID
     */
    public function getID() : int
    {
        return (int)$this->id;
    }


    /**
     * Set the net price for the item.
     *
     * @param   float   $price  New net price
     * @return  object  $this
     */
    public function setNetPrice(float $price) : self
    {
        $this->net_price = (float)$price;
        return $this;
    }


    /**
     * Get the random token for this item.
     *
     * @return  string      Token string
     */
    public function getToken() : string
    {
        return $this->token;
    }


    /**
     * Return taxable status of this item.
     *
     * @return  integer     1 if item is taxable, 0 if not
     */
    public function isTaxable() : bool
    {
        return $this->taxable;
    }


    /**
     * Apply a discount percentage to this item.
     *
     * @param   float   $pct    Discount percent, as a whole number
     * @return  object  $this
     */
    public function applyDiscountPct(float $pct) : self
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
    public function setTax(float $tax) : self
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
    public function getTax() : float
    {
        return (float)$this->tax;
    }


    /**
     * Set the sales tax rate charged for this item.
     *
     * @param   float   $rate   Sales tax rate
     * @return  object  $this
     */
    public function setTaxRate(float $rate) : self
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
    public function getTaxRate() : float
    {
        return (float)$this->tax_rate;
    }


    /**
     * Get the taxable status of the item.
     *
     * @return  integer     1 if taxable, 0 if not
     */
    public function getTaxable() : bool
    {
        return $this->taxable ? 1 : 0;
    }


    /**
     * Get the SKU (product name) for the item.
     *
     * @return  string      Item SKU
     */
    public function getSKU() : string
    {
        return $this->sku;
    }


    /**
     * Check if this order item is from a plugin.
     *
     * @return  boolean     True if it is a plugin item, False if catalog
     */
    public function isPluginItem() : bool
    {
        return Product::isPluginItem($this->product_id);
    }


    /**
     * Set the SKU, creating from the variant or product name if empty.
     *
     * @param   string  $sku    SKU, empty if not known
     * @return  object  $this
     */
    public function setSKU(?string $sku=NULL) : self
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
        $this->sku = (string)$sku;
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
