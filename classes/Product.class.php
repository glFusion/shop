<?php
/**
 * Class to manage products.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Shop\Config;
use Shop\Models\ProductType;
use Shop\Models\Dates;
use Shop\Models\Views;
use Shop\Models\Stock;
use Shop\Models\IPN;
use Shop\Models\ProductCheckbox;
use Shop\Models\ProductVariantInfo;


/**
 * Class for products.
 * @package shop
 */
class Product
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Table key. Blank value will cause no action to be taken.
     * @var string */
    protected static $TABLE = 'shop.products';

    /** Out-of-stock items can be sold and backordered.
     * @const integer */
    const OVERSELL_ALLOW = 0;

    /** Out-of-stock items appear in the catalog but can't be sold.
     * @const integer */
    const OVERSELL_DENY = 1;

    /** Out-of-stock items are hidden from the catalog and can't be sold.
     * @const integer */
    const OVERSELL_HIDE = 2;

    /** Fixed maximum value for order quantity.
     * @const integer */
    const MAX_ORDER_QTY = 99999;

    /** Product DB record ID.
     * @var string|integer */
    protected $id = 0;

    /** Product Type. @see functions.inc.
     * @var integer */
    protected $prod_type = 1;     // Physical by default

    /** Number of views for this product's detail page.
     * @var integer */
    protected $views = 0;

    /** Number of ratings given to this product.
     * @var integer */
    protected $votes = 0;

    /** Overall rating score for this item.
     * @var float */
    protected $rating = 0;

    /** Type of shipping available for this product.
     * @var integer */
    protected $shipping_type = 0;

    /** Flag indicating how to display out-of-stock items.
     * @var integer */
    protected $oversell = 0;

    /** Number of days after purchase when files cannot be downloaded.
     * @var integer */
    protected $expiration = 0;

    /** Item per-unit base price.
     * @var float */
    protected $price = 0;

    /** Per-unit shipping weight.
     * @var float */
    protected $weight = 0;

    /** Per-unit fixed shipping charge.
     * @var float */
    protected $shipping_amt = 0;

    /** Number of shipping "units" per unit.
     * @var float */
    protected $shipping_units = 0;

    /** Actual price for this product after considering sales.
     * @var float */
    protected $_act_price = 0;

    /** Original item price before any sales.
     * @var float */
    protected $_orig_price = 0;

    /** Date product becomes available.
     * @var string */
    protected $avail_beg = '1900-01-01';

    /** Date product is no longer available.
     * @var string */
    protected $avail_end = '2037-12-31';

    /** Date added to the catalog.
     * @var string */
    protected $dt_add = '';

    /** Item description.
     * @var string */
    protected $description = '';

    /** Item short description.
     * @var string */
    protected $short_description = '';

    /** Filename for downloadable files.
     * @var string */
    protected $filename = '';

    /** Search keywords not included in descriptions.
     * @var string */
    protected $keywords = '';

    /** Button type for buy_now buttons.
     * @var string */
    protected $btn_type = 'buy_now';

    /** Product item ID, including options.
     * @var string */
    protected $item_id = '';

    /** Text to show on the button.
     * @var string */
    protected $btn_text = '';

    /** Return url when buyer cancels before payment.
     * May be overridden by plugin products.
     * @var string */
    protected $cancel_url = '';

    /** Product name/sku.
     * @var string */
    protected $name = '';

    /** Old sku, used when editing product.
     * @var string */
    protected $old_sku = '';

    /** Is the product enabled (shown in catalog)?
     * @var boolean */
    protected $enabled = 1;

    /** Is the product featured?
     * @var boolean */
    protected $featured = 0;

    /** Is the product taxable?
     * @var boolean */
    protected $taxable = 0;

    /** Show in the Random Product block?
     * @var boolean */
    protected $show_random = 0;

    /** Show in the Popular Products block?
     * @var boolean */
    protected $show_popular = 0;

    /** Are ratings enabled?
     * @var boolean */
    protected $rating_enabled = 1;

    /** Are comments enabled?
     * @var integer */
    protected $comments_enabled = 0;

    /** Track inventory onhand?
     * @var boolean */
    protected $track_onhand = 0;

    /** Product attributes.
     * @var array */
    public $Options;

    /** Attribute Groups used for this product.
     * @var array */
    public $OptionGroups = array();

    /** Indicate whether the current user is an administrator.
     * @var boolean */
    public $isAdmin;

    /** Indicate that this is a new record.
     * @var boolean */
    public $isNew;

    /** Array of error messages/
     * @var array */
    protected $Errors = array();

    /** Array of buttons.
     * @var array */
    public $buttons;

    /** Special fields, created by adding text strings.
     * Set statically by the product, see the $custom property for
     * admin-created input fields such as embroidery or engraving info.
     * @var array */
    protected $special_fields = array();

    /** Fixed quantity that can be purchased, zero means use-selectable.
     * @var integer */
    protected $_fixed_q = 0;

    /** Indicate that the price can be overridden during purchase.
     * Typically used by plugin items.
     * @var boolean */
    public $override_price = false;

    /** User ID, used for pricing.
     * @var integer */
    protected $_uid = 0;

    /** Type of button to create depends on the current view- list or detail.
     * @var string */
    protected $_view = Views::DETAIL;

    /** Sale object associated with this product.
     * @var object */
    protected $Sale = NULL;

    /** Product image objects.
     * @var array */
    protected $Images = NULL;

    /** Selected attributes.
     * Using a property to pass between functions.
     * @var array */
    protected $sel_opts = array();

    /** Query string, if any.
     * @var string */
    protected $query = '';

    /** OrderItem ID to get previously-ordered options.
     * @var integer */
    protected $oi_id = 0;

    /** Minimum allowed quantity per order.
     * @var integer */
    protected $min_ord_qty = 1;

    /** Maximum allowed quantity per order.
     * @var integer */
    protected $max_ord_qty = 0;

    /** Custom text input fields solicited on the order form.
     * @var string */
    protected $custom = '';

    /** Supplier ID.
     * @var integer */
    protected $supplier_id = 0;

    /** Brand ID.
     * @var integer */
    protected $brand_id = 0;

    /** Supplier Reference, e.g. model number, sku.
     * @var string */
    protected $supplier_ref = '';

    /** Description of product lead time.
     * @var string */
    protected $lead_time = '';

    /** Default Variant ID.
     * @var integer */
    protected $def_pv_id = 0;

    /** Zone rule ID.
     * @var integer */
    protected $zone_rule = 0;

    /** Quantity discount array (qty->percent).
     * @var array */
    protected $qty_discounts = array();

    /** Related category objects.
     * @var array */
    protected $Categories = NULL;

    /** Product variants for this product. Null to load only once.
     * @var array */
    protected $Variants = NULL;

    /** A single product variant attached to this product.
     * @var object */
    protected $Variant = NULL;

    /** Product features to be shown in the detail page.
     * NULL indicates that the features haven't been loaded yet.
     * @var array */
    protected $Features = NULL;

    /** Plugin name, if this is a plugin-provided product.
     * @var string */
    protected $pi_name = '';

    /** Flag to indicate the product purchase affects referral bonuses.
     * Catalog products typically will do this, plugins will not.
     * @var boolean */
    protected $aff_apply_bonus = true;

    /** Affiliate bonus percentage.
     * @var float */
    protected $aff_percent = 0;

    /** Product class ID.
     * @var integer */
    protected $prod_rule = 0;

    /** Effective product rule object.
     * Only instantiated if needed.
     * var object */
    protected $productRule = NULL;

    /** Flag to indicate that the product cannot be purchased.
     * @var boolean */
    protected $canPurchase = true;

    /** Stock level object for products without variants.
     * Variants have their own related Stock objects.
     * @var object */
    protected $Stock = NULL;

    /** Effective zone rule object.
     * @var object */
    private $_ZoneRule = NULL;


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id Optional type ID
     */
    public function __construct($id=0)
    {
        global $_SHOP_CONF;

        $this->isNew = true;
        $this->pi_name = $_SHOP_CONF['pi_name'];
        $this->btn_text = '';
        $this->cancel_url = SHOP_URL . '/index.php';

        // Disable affiliate bonuses if the program is disabled.
        if (Config::get('aff_enabled')) {
            $this->aff_percent = Config::get('aff_pct');
        } else {
            $this->aff_apply_bonus = false;
        }

        if (is_array($id)) {
            // Record has already been read from the DB
            $this->setVars($id, true);
            $this->isNew = false;
        } elseif ($id == 0) {
            // No catalog item requested, return an empty object
            $this->prod_type = $_SHOP_CONF['def_prod_type'];
            $this->expiration = $_SHOP_CONF['def_expiration'];
            $this->enabled = $_SHOP_CONF['def_enabled'];
            $this->featured = $_SHOP_CONF['def_featured'];
            $this->taxable = $_SHOP_CONF['def_taxable'];
            $this->dt_add = SHOP_now()->toMySQL();
            $this->comments_enabled = $_SHOP_CONF['ena_comments'] == 1 ?
                    SHOP_COMMENTS_ENABLED : SHOP_COMMENTS_DISABLED;
            $this->rating_enabled = $_SHOP_CONF['ena_ratings'] == 1 ? 1 : 0;
            $this->track_onhand = $_SHOP_CONF['def_track_onhand'];
            $this->oversell = $_SHOP_CONF['def_oversell'];
            $this->qty_discounts = array();
        } else {
            // A single item requested, read from the DB.
            $this->id = $id;
            if (!$this->Read()) {
                $this->id = 0;
            }
        }
        if ($this->id > 0) {
            $this->getImages();
        }
        $this->isAdmin = plugin_ismoderator_shop() ? 1 : 0;
    }


    /**
     * Gets an instance of a product object.
     * Figures out the type of product (plugin, catalog, etc.)
     * and instantiates an object if necessary.
     * $A can be a single item id or an array (DB record) of values.
     *
     * @param   mixed   $A      Single item ID or array of values
     * @param   array   $mods   Optional array of product modifiers
     * @return  object          Product Object
     */
    public static function getInstance($A, $mods=array())
    {
        global $_SHOP_CONF, $_TABLES;
        static $P = array();    // cache for internal products

        if (is_array($A) && isset($A['id'])) {
            // A complete product record
            return self::_getInstance($A);
        } elseif (is_string($A)) {
            // A single product ID
            $id = $A;
        }
        if (!$id) {
            // Missing product ID
            return NULL;
        }

        $item = explode('|', $id);
        if (self::isPluginItem($item[0])) {
            // Product provided by another plugin
            return new \Shop\Products\Plugin($item[0], $mods);
        } else {
            if (!array_key_exists($id, $P)) {
                // Product internal to this plugin
                if (Config::get('use_sku')) {
                    $P[$id] = self::getBySKU($item[0]);
                } else {
                    $P[$id] = self::getByID((int)$item[0]);
                }
                if (isset($item[1])) {
                    $P[$id]->setSelectedOptions($item[1]);
                }
            }
            return $P[$id];
        }
    }


    /**
     * Instantiate an object from a DB record array.
     * Internal products only, called from `getByID()` and `getBySKU()`.
     *
     * @param   array   $A  DB record
     * @return  object      Product object
     */
    private static function _getInstance(array $A) : self
    {
        if (isset($A['prod_type']) && $A['prod_type'] == ProductType::COUPON) {
            $P = new \Shop\Products\Coupon($A);
        } else {
            $P = new self($A);
        }
        if ($P->hasAccess()) {
            $P->loadOptions();
        }
        return $P;
    }


    /**
     * Get an item by its SKU.
     *
     * @param   string  $id SKU to locate
     * @return  object      Product object
     */
    public static function getBySKU(string $id) : self
    {
        global $_TABLES;

        $parts = explode('-', $id);
        $main_sku = $parts[0];
        $cache_key = self::_makeCacheKey($main_sku);
        $A = Cache::get($cache_key);
        if (!is_array($A)) {
            try {
                $A = Database::getInstance()->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.products']} WHERE name = ? LIMIT 1",
                    array($main_sku),
                    array(Database::STRING)
                )->fetchAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }
            if (is_array($A) && isset($A['id'])) {
                Cache::set($cache_key, $A, array(self::$TABLE));
            } else {
                $A = array();
            }
        }
        return self::_getInstance($A);
    }


    /**
     * Get a product by the database ID
     *
     * @param   string  $id     Item ID, may include options
     * @return  object      Product object
     */
    public static function getByID(string $id) : self
    {
        global $_TABLES;

        $parts = explode('|', $id);
        // Have to handle possible plugin items here as well
        if (self::isPluginItem($parts[0])) {
            // Product provided by another plugin
            return self::getInstance($parts[0]);
        } else {
            $id = (int)$parts[0];
            $cache_key = self::_makeCacheKey($id);
            $A = Cache::get($cache_key);
            if (!is_array($A)) {
                try {
                    $A = Database::getInstance()->conn->executeQuery(
                        "SELECT * FROM {$_TABLES['shop.products']} WHERE id = ? LIMIT 1",
                        array($id),
                        array(Database::INTEGER)
                    )->fetchAssociative();
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                    $A = false;
                }
                if (is_array($A) && isset($A['id'])) {
                    Cache::set($cache_key, $A, array(self::$TABLE));
                } else {
                    $A = array();
                }
            }
            return self::_getInstance($A);
        }
    }


    /**
     * Get a Product object given a database record array.
     *
     * @param   array   $A      Database record
     * @return  object  Product object
     */
    public static function fromArray(array $A) : self
    {
        return self::_getInstance($A);
    }


    /**
     * Get all products under a category.
     *
     * @param   integer $cat_id Category ID
     * @return  array       Array of Product objects, keyed by ID
     */
    public static function getIDsByCategory(int $cat_id) : array
    {
        global $_TABLES;

        $cat_id = (int)$cat_id;
        $cache_key = 'prod_idbycat_' . $cat_id;
        $retval = Cache::get($cache_key);
        //$retval = NULL;
        if (!is_array($retval)) {
            $retval = array();
            try {
                $stmt = Database::getInstance()->conn->executeQuery(
                    "SELECT id FROM {$_TABLES['shop.products']} WHERE cat_id = ?",
                    array($cat_id),
                    array(Database::INTEGER)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($stmt) {
                while ($A = $stmt->fetchAssociative()) {
                    $retval[] = $A['id'];
                }
            }
            Cache::set($cache_key, $retval, array(self::$TABLE, 'shop.categories'));
        }
        return $retval;
    }


    /**
     * Set the product ID value.
     * If a catalog item, make sure the ID is an integer.
     *
     * @param   string|integer  $value  Product ID
     * @return  object  $this
     */
    public function setID(string $value) : self
    {
        if (!self::isPluginItem($value)) {
            $value = (int)$value;
        }
        $this->id = $value;
        return $this;
    }


    /**
     * Get the product ID.
     * May be an integer for catalog items or a string for plugins.
     *
     * @return  string      Product ID
     */
    public function getID()
    {
        return $this->id;
    }


    /**
     * Set the product name/sku.
     * Sanitize to valid SKU characters.
     *
     * @param   string  $value  Product name
     * @return  object  $this
     */
    public function setSKU($value)
    {
        $this->name = self::sanitizeSKU($value);
        return $this;
    }


    /**
     * Sanitize the SKU to acceptable alphanumeric characters.
     *
     * @param   string  $sku    Actual SKU
     * @return  string          Sanitized version
     */
    private static function _sanitizeSKU($sku)
    {
        return trim(preg_replace("/[^A-Za-z0-9 ]/", '', $sku));
    }


    /**
     * Set the date when availability begins.
     *
     * @param   string  $value  Date string, YYYY-MM-DD format
     * @return  object  $this
     */
    public function setAvailBegin($value)
    {
        // available to end of time by default
        if ($value < '1970-01-02') {
            $value = Dates::MIN_DATE;
        }
        $this->avail_beg = trim($value);
        return $this;
    }


    /**
     * Set the date when availability ends.
     *
     * @param   string  $value  Date string, YYYY-MM-DD format
     * @return  object  $this
     */
    public function setAvailEnd($value)
    {
        // available to end of time by default
        if ($value < '1970-01-02') {
            $value = Dates::MAX_DATE;
        }
        $this->avail_end = trim($value);
        return $this;
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row        Array of values, from DB or $_POST
     * @param   boolean $fromDB     True if read from DB, false if from $_POST
     * @return  object  $this
     */
    public function setVars(array $row, ?bool $fromDB=NULL) : self
    {
        if (!is_array($row) || empty($row)) return $this;

        $this->id = $row['id'];
        $this->item_id = $row['id'];
        $this->description = $row['description'];
        $this->enabled = isset($row['enabled']) ? $row['enabled'] : 0;
        $this->featured = isset($row['featured']) ? $row['featured'] : 0;
        $this->name = $row['name'];
        //$this->old_sku = SHOP_getVar($row, 'old_sku');
        $this->short_description = $row['short_description'];
        $this->price = (float)$row['price'];
        $this->filename = $row['file'];
        $this->expiration = $row['expiration'];
        $this->keywords = $row['keywords'];
        $this->prod_type = isset($row['prod_type']) ? $row['prod_type'] : 0;
        $this->weight = (float)$row['weight'];
        $this->taxable = isset($row['taxable']) ? $row['taxable'] : 0;
        $this->shipping_type = SHOP_getVar($row, 'shipping_type', 'integer');
        $this->shipping_amt = SHOP_getVar($row, 'shipping_amt', 'float');
        $this->shipping_units = SHOP_getVar($row, 'shipping_units', 'float');
        $this->show_random = isset($row['show_random']) ? $row['show_random'] : 0;
        $this->show_popular = isset($row['show_popular']) ? $row['show_popular'] : 0;
        $this->track_onhand = isset($row['track_onhand']) ? $row['track_onhand'] : 0;
        $this->oversell = isset($row['oversell']) ? $row['oversell'] : 0;
        $this->custom = $row['custom'];
        $this->setAvailBegin($row['avail_beg']);
        $this->setAvailEnd($row['avail_end']);
        $this->prod_rule = (int)$row['prod_rule'];
        if ($this->avail_end < $this->avail_beg) {
            $this->avail_end = Dates::MAX_DATE;
        }
        $this->min_ord_qty = SHOP_getVar($row, 'min_ord_qty', 'integer', 1);
        $this->max_ord_qty = SHOP_getVar($row, 'max_ord_qty', 'integer', 0);
        if (!$this->hasVariants()) {
            // Get the stock count from the base Stock record
            $this->Stock = Stock::getByItem($this->id);
        }

        // Get the quantity discount table. If coming from a form,
        // there will be two array variables for qty and discount percent.
        // From the DB there's a single serialized string
        if ($fromDB) {
            $this->setQtyDiscounts($row['qty_discounts']);
            $this->dt_add = $row['dt_add'];
        } else {
            $this->dt_add = SHOP_now()->toMySQL();
            $qty_discounts = array();
            if (isset($row['disc_qty']) && is_array($row['disc_qty'])) {
                for ($i = 0; $i < count($row['disc_qty']); $i++) {
                    $disc_qty = (int)$row['disc_qty'][$i];
                    if ($disc_qty < 1) continue;
                    if (isset($row['disc_amt'][$i])) {
                        $qty_discounts[$disc_qty] = abs($row['disc_amt'][$i]);
                    }
                }
            }
            $this->setQtyDiscounts($qty_discounts);
        }

        $this->votes = SHOP_getVar($row, 'votes', 'integer');
        $this->rating = SHOP_getVar($row, 'rating', 'float');
        $this->comments_enabled = (int)$row['comments_enabled'];
        $this->rating_enabled = isset($row['rating_enabled']) ? $row['rating_enabled'] : 0;
        $this->btn_type = $row['buttons'];
        $this->setSupplierID($row['supplier_id'])
            ->setBrandID($row['brand_id'])
            ->setSupplierRef($row['supplier_ref'])
            ->setLeadTime($row['lead_time'])
            ->setDefVariantID(SHOP_getVar($row, 'def_pv_id', 'integer'))
            ->setZoneRuleID($row['zone_rule']);

        if ($fromDB) {
            $this->views = $row['views'];
        }
        return $this;
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Optional ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read(int $id = 0) : bool
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return false;
        }

        $cache_key = self::_makeCacheKey($id);
        //$row = Cache::get($cache_key);
        //if ($row === NULL) {
        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.products']} WHERE id = ?",
                array($id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $this->isNew = false;
            $this->setVars($row, true)
                ->loadOptions()
                ->loadCategories();
            return true;
        } else {
            return false;
        }
    }


    /**
     * Check if this product has variants.
     *
     * @return  integer     Number of variants
     */
    public function hasVariants()
    {
        if ($this->Variants === NULL) {
            $this->Variants = $this->getVariants();
        }
        return count($this->Variants);
    }


    /**
     * Get all the variants related to this product.
     * Sets the local Variants property for later use.
     *
     * @return  array   Array of ProductVariant objects
     */
    public function getVariants()
    {
        if ($this->Variants === NULL) {
            $this->Variants = ProductVariant::getByProduct($this->id);
        }
        return $this->Variants;
    }


    /**
     * Get the currently-active product variant.
     *
     * @return  object      ProductVariant object
     */
    public function getVariant()
    {
        return $this->Variant;
    }


    /**
     * Get all the static features related to this product.
     * Sets the local Features property for later use.
     *
     * @return  array   Array of Feature objects
     */
    public function getFeatures()
    {
        if ($this->Features === NULL) {
            $this->Features = Feature::getByProduct($this->id);
        }
        return $this->Features;
    }


    /**
     * Set the Supplier Reference field.
     *
     * @param   string  $ref    Supplier reference number
     * @return  object  $this
     */
    public function setSupplierRef($ref)
    {
        $this->supplier_ref = $ref;
        return $this;
    }


    /**
     * Get the Supplier Reference value.
     *
     * @return  string      Supplier reference number
     */
    public function getSupplierRef()
    {
        return $this->supplier_ref;
    }


    /**
     * Set the zone rule for this item.
     *
     * @param   integer $id     Rule ID
     * @return  object  $this
     */
    private function setZoneRuleID($id)
    {
        $this->zone_rule = (int)$id;
        return $this;

    }


    /**
     * Get the zone rule ID for this item.
     *
     * @return  integer     Zone rule record ID
     */
    public function getZoneRuleID()
    {
        return (int)$this->zone_rule;
    }


    /**
     * Get the effective zone rule.
     * Looks first for a rule affecting this specific product. If none found,
     * recurse backwards through the categories to determine if any has a
     * rule set. First enabled rule is returned.
     *
     * @param   boolean $incl_prod  True to include the product rule
     * @return  integer     Rule record ID
     */
    public function getEffectiveZoneRule($incl_prod=true) : Rules\Zone
    {
        if ($this->_ZoneRule === NULL) {
            $cache_key = 'prod_eff_rule_' . $this->id;
            $Rules = Rules\Zone::getAll(true);
            if (
                $incl_prod &&
                $this->zone_rule > 0 &&
                isset($Rules[$this->zone_rule])
            ) {
                // Get the zone rule assigned directly to the product
                $this->_ZoneRule = $Rules[$this->zone_rule];
            } else {
                // Find the first zone rule in the category tree
                $this->_ZoneRule = Cache::get($cache_key);
                if ($this->_ZoneRule === NULL) {
                    $Cats = $this->getCategories();
                    $Cats = array_reverse($Cats, true);
                    foreach ($Cats as $Cat) {
                        $rule_id = $Cat->getEffectiveZoneRuleId();
                        if (
                            $rule_id > 0 &&
                            isset($Rules[$rule_id])
                        ) {
                            // Found a rule, no need to look further
                            $this->_ZoneRule = $Rules[$rule_id];
                            break;
                        }
                    }
                }
            }
            if ($this->_ZoneRule === NULL) {
                // Final check to make sure there's a valid rule object returned.
                $this->_ZoneRule = new Rules\Zone;
            }
            Cache::set($cache_key, $this->_ZoneRule, array('shop.categories', self::$TABLE, 'shop.zone_rules'));
        }
        return $this->_ZoneRule;
    }



    /**
     * Get the filename for a downloadable product.
     *
     * @return  string      Filename
     */
    public function getFilename()
    {
        return $this->filename;
    }


    /**
     * Load the product attributs into the options array.
     *
     * @return  object  $this
     */
    protected function loadOptions()
    {
        if (empty($this->OptionGroups)) {   // Load only once
            $this->OptionGroups = ProductOptionGroup::getByProduct($this->id);
            foreach ($this->OptionGroups as $og_id=>$OG) {
                // TODO: deprecate this array and use group/option objects
                foreach ($OG->getOptions() as $opt_id=>$Opt) {
                    $this->Options[$opt_id] = array(
                        'og_id'     => $Opt->getID(),
                        'attr_name' => $OG->getName(),
                        'attr_value' => $Opt->getValue(),
                        'attr_price' => $Opt->getPrice(),
                        'sku'       => $Opt->getSKU(),
                    );
                }
            }
        }
        return $this;
    }


    /**
     * Save the current values to the database.
     * Does not save values from $this->Images.
     * Appends error messages to the $Errors property.
     *
     * @param   array   $A      Optional array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save(?array $A = NULL) : bool
    {
        global $_TABLES, $_SHOP_CONF, $LANG_SHOP;

        $db = Database::getInstance();
        $old_rating_ena = $this->rating_enabled;    // save original setting

        // See if the name changed. If so, then the Variant skus need to be
        // updated.
        $this->old_sku = $this->name;

        if (is_array($A)) {
            $this->setVars($A);
        }

        $nonce = SHOP_getVar($A, 'nonce');
        $this->Errors = $this->_Validate();
        if (!empty($errs)) {
            SHOP_setMsg($this->Errors, 'error');
            return false;
        }

        // Handle file uploads.
        // This is done first so we know whether there is a valid filename
        // for a download product.
        if ($this->isDownload()) {
            if (!empty($_FILES['uploadfile']['tmp_name'])) {
                $F = new File('uploadfile');
                $F->setMaxDimensions(0, 0);
                $filename = $F->uploadFiles();
                if ($F->areErrors() > 0) {
                    $this->Errors[] = $F->printErrors(true);
                } elseif ($filename != '') {
                    $this->filename = $filename;
                }
                Log::write('shop_system', Log::DEBUG, __METHOD__ . ': Uploaded file: ' . $this->filename);
            }
            if ($this->filename == '') {
                // Not having a file is an error for downloadable products.
                $this->Errors[] = $LANG_SHOP['err_missing_file'];
            }
        } else {
            // Make sure file is empy for non-downloads.
            // May have previously contained a file if the type was changed.
            $this->filename = '';
        }

        // Check for errors during validation and file upload,
        // and abort before any real DB action is taken.
        if (!empty($this->Errors)) {
            SHOP_setMsg($this->Errors, 'error');
            return false;
        }

        if (isset($A['imgdelete']) && !empty($A['imgdelete'])) {
            $del_ids = explode(',', $A['imgdelete']);
            foreach ($del_ids as $img_id) {
                Images\Product::deleteById($img_id);
            }
        }

        // For downloads and virtual items. physical options don't apply.
        if (!$this->isPhysical()) {
            $this->weight = 0;
            $this->shipping_type = 0;
            $this->shipping_amt = 0;
            $this->shipping_units = 0;
        }

        // If ratings were enabled but are now disabled, reset the rating
        // for this product.
        if ($old_rating_ena && !$this->rating_enabled) {
            RATING_resetRating($_SHOP_CONF['pi_name'], $this->id);
        }

        // Delete any existing product options, just simpler to re-add them
        ProductCheckbox::deleteByItem($this->id);

        $status = $this->saveToDB();
        if ($status && $this->id > 0) {
            if ($this->isNew) {
                if (!empty($nonce)) {
                    Images\Product::setProductID($nonce, $this->id);
                }
                $this->getImages();     // Load images
                // Clear categories for new products so the new cats get updated
                // correcly.
                $this->Categories = array();
            }
            if (isset($A['imgorder']) && !empty($A['imgorder'])) {
                $img_ids = explode(',', $A['imgorder']);
                $orderby = 10;
                foreach ($img_ids as $img_id) {
                    $img_id = (int)$img_id;
                    if ($this->Images[$img_id]['orderby'] != $orderby) {
                        $this->Images[$img_id]['orderby'] = $orderby;
                        try {
                            $db->conn->update(
                                $_TABLES['shop.images'],
                                array('orderby' => $orderby),
                                array('img_id' => $img_id),
                                array(Database::INTEGER, Database::INTEGER)
                            );
                        } catch (\Throwable $e) {
                            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                        }
                    }
                    $orderby += 10;
                }
            }

            if (isset($A['selected_cats']) && !empty($A['selected_cats'])) {
                $this->updateCategories($A['selected_cats']);
            }

            // Save any variants that were created.
            // First, set item ID into $_POST var for ProductVariant to use.
            $A['item_id'] = $this->id;
            if (is_array($A) && isset($A['pv_groups'])) {
                $A['pv_item_id'] = $this->id;
                ProductVariant::saveNew($A);
            }

            // Save the stock levels.
            // The product form will have one "quantities" array with index "0"
            // to represent the base product, while the variants form will have
            // multiple quantities.
            if (isset($A['quantities']) && is_array($A['quantities'])) {
                foreach($A['quantities'] as $pv_id=>$data) {
                    Stock::getByItem($this->id, $pv_id)
                        ->withOnhand($data['qty_onhand'])
                        ->withReserved($data['qty_reserved'])
                        ->withReorder($data['qty_reorder'])
                        ->Save();
                }
            }

            // Add any new features
            if (array_key_exists('new_ft', $A)) {
                foreach ($A['new_ft'] as $idx=>$ft_id) {
                    Feature::getInstance($ft_id)->addProduct(
                        $this->id,
                        $A['new_fv_sel'][$idx],
                        $A['new_fv_custom'][$idx]
                    );
                }
            }
            // Delete any features checked for deletion
            if (array_key_exists('del_ft', $A)) {
                foreach ($A['del_ft'] as $ft_id=>$val) {
                    Feature::deleteProduct($this->id, $ft_id);
                }
            }

            // Add any new product checkboxes.
            if (isset($A['cbenabled']) && !empty($A['cbenabled'])) {
                ProductCheckbox::add($this->id, $A['cbenabled'], $A['cbprice']);
            }
        }

        // Clear all product caches since this save may affect availablity
        // and product lists.
        Cache::clear(self::$TABLE);
        Cache::clear('sitemap');

        if ($status) {
            // Handle image uploads.  This is done last because we need
            // the product id to name the images filenames.
            if (!empty($_FILES['images'])) {
                $U = new Images\Product($this->id, 'images');
                $U->uploadFiles();
                if ($U->areErrors() > 0) {
                    $this->Errors = array_merge($this->Errors, $U->getErrors());
                }
            }

            // Clear the button cache
            self::deleteButtons($this->id);
        }

        // Final check to catch error messages from the SQL update
        if (empty($this->Errors)) {
            SHOP_setMsg($LANG_SHOP['msg_updated']);
            Log::write('shop_system', Log::DEBUG, __METHOD__ . ": Update of product {$this->id} succeeded.");
            PLG_itemSaved($this->id, $_SHOP_CONF['pi_name']);
            return true;
        } else {
            SHOP_setMsg($this->Errors, 'error');
            Log::write('shop_system', Log::ERROR, __METHOD__ . ": Update of product {$this->id} failed.");
            echo COM_refresh(SHOP_ADMIN_URL . '/index.php?return=products&editproduct=x&id=' . $this->id);
            return false;
        }
    }


    /**
     * Saves the product record to the database without updating anything else.
     *
     * @return  boolean     True on success, False on error
     */
    public function saveToDB() : bool
    {
        global $_TABLES, $_CONF;

        $db = Database::getInstance();
        //$options = @serialize($this->options);
        $values = array(
            'name' => $this->name,
            'short_description'=>$this->short_description,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'price' => number_format($this->price, 2, '.', ''),
            'prod_type' => $this->prod_type,
            'weight' => number_format($this->weight, 2, '.', ''),
            'file' => $this->filename,
            'expiration' => $this->expiration,
            'enabled' => $this->enabled,
            'featured' => $this->featured,
            'views' => $this->views,
            'taxable' => $this->taxable,
            'shipping_type' => $this->getShippingType(),
            'shipping_amt' => $this->shipping_amt,
            'shipping_units' => $this->shipping_units,
            'comments_enabled' => $this->comments_enabled,
            'rating_enabled' => $this->rating_enabled,
            'show_random' => $this->show_random,
            'show_popular' => $this->show_popular,
            'track_onhand' => $this->track_onhand,
            'oversell' => $this->oversell,
            'qty_discounts' => @serialize($this->qty_discounts),
            'custom' => $this->custom,
            'avail_beg' => $this->avail_beg,
            'avail_end' => $this->avail_end,
            'brand_id' => $this->getBrandID(),
            'supplier_id' => $this->getSupplierID(),
            'supplier_ref' => $this->getSupplierRef(),
            'lead_time' => $this->getLeadTime(),
            'def_pv_id' => $this->getDefVariantID(),
            'zone_rule' => $this->getZoneRuleID(),
            'buttons'=> $this->btn_type,
            'min_ord_qty' => $this->min_ord_qty,
            'max_ord_qty' => $this->max_ord_qty,
            'prod_rule' => $this->prod_rule,
        );
        $types = array(
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
        );

        // Insert or update the record, as appropriatea
        try {
            if ($this->id > 0) {
                // Updating an existing record.
                Log::write('shop_system', Log::DEBUG, __METHOD__ . ": Preparing to update product id {$this->id}");
                $types[] = Database::INTEGER;   // for product id
                $db->conn->update(
                    $_TABLES['shop.products'],
                    $values,
                    array('id' => $this->id),
                    $types
                );
                // While we're here, change the existing Variant SKUs if the
                // product SKU has changed.
                if (empty($this->Errors) && !empty($this->old_sku) && $this->old_sku != $this->name) {
                    foreach ($this->getVariants() as $Variant) {
                        $Variant->updateSKU($this->old_sku, $this->name);
                    }
                }
            } else {
                // Inserting a new record.
                Log::write('shop_system', Log::DEBUG, __METHOD__ . ': Preparing to save a new product.');
                $values['dt_add'] = $_CONF['_now']->toMySQL(false);
                $types[] = Database::STRING;
                $db->conn->insert(
                    $_TABLES['shop.products'],
                    $values,
                    $types
                );
                $this->id = $db->conn->lastInsertId();
            }
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Delete the current product record from the database.
     * Deletes the item, item attributes, images and buttons. Does not
     * update the purchases or IPN log at all. Does not delete an item
     * that has orders associated with it.
     *
     * @uses    self::deleteImage()
     * @uses    self::deleteButtons()
     * @return  boolean     True when deleted, False if invalid ID
     */
    public function Delete() : bool
    {
        global $_TABLES, $_SHOP_CONF;

        if ($this->id <= 0 ||
            self::isUsed($this->id) ||
            self::isPluginItem($this->id)) {
            return false;
        }

        $db = Database::getInstance();
        foreach ($this->Images as $prow) {
            $this->deleteImage($prow['img_id']);
        }
        // Make sure all related image records are deleted, in case some
        // image files are missing and not reflected in the Images array.
        try {
            $db->conn->delete(
                $_TABLES['shop.images'],
                array('product_id' => $this->id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }

        ProductVariant::deleteByProduct($this->id);
        Stock::deleteByProduct($this->id);
        Category::deleteProduct($this->id);
        Feature::deleteProduct($this->id);
        self::deleteButtons($this->id);
        try {
            $db->conn->delete(
                $_TABLES['shop.products'],
                array('id' => $this->id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        Cache::clear(self::$TABLE);
        Cache::clear('sitemap');
        PLG_itemDeleted($this->id, $_SHOP_CONF['pi_name']);
        $this->id = 0;
        $this->isNew = true;
        return true;
    }


    /**
     * Delete all buttons for a product.
     * Called when a product is updated so the buttons will be recreated
     * when needed.
     *
     * @param   integer $item_id    Product ID to delete
     */
    private static function deleteButtons(int $item_id) : void
    {
        global $_TABLES;

        Database::getInstance()->conn->delete(
            $_TABLES['shop.buttons'],
            array('item_id' => $item_id),
            array(Database::INTEGER)
        );
    }


    /**
     * Deletes a single image from disk for the current product.
     *
     * @param   integer $img_id     DB ID of image to delete
     */
    public function deleteImage($img_id)
    {
        global $_TABLES, $_SHOP_CONF;

        $img_id = (int)$img_id;
        if ($img_id < 1 || !isset($this->Images[$img_id])) {
            return;
        }

        Images\Product::deleteById($img_id);
        Cache::delete(self::_makeCacheKey($this->id));
        Cache::delete(self::_makeCacheKey($this->id, 'img'));
    }


    /**
     * Creates the product edit form.
     *
     * Creates the form for editing a product.  If a product ID is supplied,
     * then that product is read and becomes the current product.  If not,
     * then the current product is edited.  If an empty product was created,
     * then a new product is created here.
     *
     * @uses    SHOP_getDocUrl()
     * @uses    SHOP_errorMessage()
     * @param   integer $id     Optional ID, current record used if zero
     * @param   string  $tab    Name of tab to set as active
     * @return  string          HTML for edit form
     */
    public function showForm($id = 0, $tab='')
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_SHOP_HELP;

        $id = (int)$id;
        if ($id > 0) {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return SHOP_errorMessage($LANG_SHOP['invalid_product_id'], 'info');
            }
        }
        $id = $this->id;

        SEC_setCookie(
            $_CONF['cookie_name'].'adveditor',
            SEC_createTokenGeneral('advancededitor'),
            time() + 1200,
            $_CONF['cookie_path'],
            $_CONF['cookiedomain'],
            $_CONF['cookiesecure'],
            false
        );

        // Delete old image records that aren't associated with a product
        Images\Product::cleanUnassigned();

        $T = new Template;
        $T->set_file('product', 'product_form.thtml');
        // Set up the wysiwyg editor, if available
        $tpl_var = $_SHOP_CONF['pi_name'] . '_entry';
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor($_SHOP_CONF['pi_name'], $tpl_var, 'ckeditor_shop.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor($_SHOP_CONF['pi_name'], $tpl_var, 'tinymce_shop.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        // Add the current product ID to the form if it's an existing product.
        if ($id > 0) {
            $retval = COM_startBlock(
                $LANG_SHOP['edit_item'] . ': ' . $this->short_description
            );

        } else {
            $T->set_var('id', '');
            $retval = COM_startBlock($LANG_SHOP['new_item']);

        }

        $ph_lead_time = Supplier::getInstance($this->getSupplierID())->getLeadTime();
        if (empty($ph_lead_time)) {
            $ph_lead_time = $LANG_SHOP['none'];
        }
        list($allcats_sel, $selcats_sel) = $this->getCatSelections($this->id);
        $T->set_var(array(
            //'post_options'  => $post_options,
            'product_id'    => $this->id,
            'old_sku'       => $this->name,
            'name'          => htmlspecialchars($this->name, ENT_QUOTES, COM_getEncodingt()),
            'short_description' => htmlspecialchars($this->short_description, ENT_QUOTES, COM_getEncodingt()),
            'description'   => htmlspecialchars($this->description, ENT_QUOTES, COM_getEncodingt()),
            'price'         => Currency::getInstance()->FormatValue($this->price),
            'file'          => htmlspecialchars($this->filename, ENT_QUOTES, COM_getEncodingt()),
            'expiration'    => $this->expiration,
            'action_url'    => SHOP_ADMIN_URL . '/index.php',
            'file_selection' => $this->FileSelector(),
            'keywords'      => htmlspecialchars($this->keywords, ENT_QUOTES, COM_getEncodingt()),
            'currency'      => $_SHOP_CONF['currency'],
            //'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('product_form', $_CONF['language']),
            'prod_type'     => $this->prod_type,
            'weight'        => $this->weight,
            'feat_chk'      => $this->featured == 1 ? 'checked="checked"' : '',
            'ena_chk'       => $this->enabled == 1 ? 'checked="checked"' : '',
            'tax_chk'       => $this->taxable == 1 ? 'checked="checked"' : '',
            'show_random_chk'  => $this->show_random == 1 ? 'checked="checked"' : '',
            'show_popular_chk' => $this->show_popular == 1 ?
                                    'checked="checked"' : '',
            'ship_sel_' . $this->shipping_type => 'selected="selected"',
            'shipping_type' => $this->shipping_type,
            'track_onhand'  => $this->track_onhand,
            'shipping_amt'  => Currency::getInstance()->FormatValue($this->shipping_amt),
            'shipping_units'  => $this->shipping_units,
            'sel_comment_' . $this->comments_enabled =>
                                    'selected="selected"',
            'rating_chk'    => $this->rating_enabled == 1 ?
                                    'checked="checked"' : '',
            'trk_onhand_chk' => $this->track_onhand== 1 ?
                                    'checked="checked"' : '',
            "oversell_sel{$this->oversell}" => 'selected="selected"',
            'custom' => $this->custom,
            'avail_beg'     => self::_InputDtFormat($this->avail_beg),
            'avail_end'     => self::_InputDtFormat($this->avail_end),
            'ret_url'       => SHOP_getUrl(SHOP_ADMIN_URL . '/index.php?products'),
            'variant_list'  => $this->id > 0 ? ProductVariant::adminList($this->id) : ProductVariant::Create(),
            'cboptions_list' => ProductCheckbox::adminList($this->id),
            'nonce'         => Images\Product::makeNonce(),
            'brand'         => $this->brand_id,
            'min_ord_qty'   => $this->min_ord_qty,
            'max_ord_qty'   => $this->max_ord_qty,
            'available_cats' => $allcats_sel,
            'selected_cats' => $selcats_sel,
            'tabactive_' . $tab => 'class="uk-active"',
            'brand_select' => Supplier::getBrandSelection($this->getBrandID()),
            'supplier_select' => Supplier::getSupplierSelection($this->getSupplierID()),
            //'limit_availability_chk' => $this->limit_availability ? 'checked="checked"' : '',
            'features_list'  => $this->id > 0 ? Feature::productForm($this->id) : '',
            'supplier_ref'  => $this->getSupplierRef(),
            'lead_time'     => $this->getLeadTime(),
            'ph_lead_time'  => $ph_lead_time,
            'zone_rule_options' => Rules\Zone::optionList($this->zone_rule),
        ) );
        if (!$this->hasVariants()) {
            $T->set_var(array(
                'have_variants' => false,
                'qty_onhand' => $this->getOnhand(),
                'qty_reorder' => $this->getReorder(),
                'qty_reserved' => $this->getReserved(),
            ) );
        } else {
            $T->set_var('has_variants', true);
        }

        // Create the button type selections. New products get the default
        // button selected, existing products get the saved button selected
        // or "none" if there is no button.
        $T->set_block('product', 'BtnRow', 'BRow');
        $have_chk = false;
        foreach ($_SHOP_CONF['buttons'] as $key=>$checked) {
            if ($key == $this->btn_type || ($this->isNew && $checked)) {
                $btn_chk = 'checked="checked"';
                $have_chk = true;
            } else {
                $btn_chk = '';
            }
            $T->set_var(array(
                'btn_type'  => $key,
                'btn_chk'   => $key == $this->btn_type ||
                        ($this->isNew && $checked) ? 'checked="checked"' : '',
                'btn_name'  => $LANG_SHOP['buttons'][$key],
            ));
            $T->parse('BRow', 'BtnRow', true);
        }
        // Set the "none" selection if nothing was already selected
        $T->set_var('none_chk', $have_chk ? '' : 'checked="checked"');

        $T->set_block('product', 'ProdTypeRadio', 'ProdType');
        foreach ($LANG_SHOP['prod_types'] as $value=>$text) {
            if ($value == ProductType::COUPON && $_SHOP_CONF['gc_enabled'] == 0) {
                continue;
            }
            $T->set_var(array(
                'type_val'  => $value,
                'type_txt'  => $text,
                'type_sel'  => $this->prod_type == $value ? 'selected="selected"' : '',
            ));
            $T->parse('ProdType', 'ProdTypeRadio', true);
        }

        if (!$this->isNew() && !self::isUsed($this->id)) {
            $T->set_var('candelete', 'true');
        }

        // If there are any images, retrieve and display the thumbnails.
        $T->set_block('product', 'PhotoRow', 'PRow');
        $i = 0;     // initialize image counter
        $imgorder = array();     // string to contain image order
        if ($this->Images) {
            foreach ($this->Images as $id=>$prow) {
                $imgorder[] = $prow['img_id'];
                $T->set_var(array(
                    'img_url'   => $this->getImage($prow['filename'])['url'],
                    'thumb_url' => $this->getThumb($prow['filename'])['url'],
                    'img_id'    => $prow['img_id'],
                    'img_cnt'   => $i++,
                ) );
                $T->parse('PRow', 'PhotoRow', true);
            }
        }
        $imgorder = implode(',', $imgorder);
        $T->set_var('imgorder', $imgorder);

        $T->set_block('dtable', 'discTable', 'DT');
        foreach ($this->qty_discounts as $qty=>$amt) {
            $T->set_var(array(
                'disc_qty' => $qty,
                'disc_amt' => number_format($amt, 2),
            ) );
            $T->parse('DT', 'discTable', true);
        }

        $Disc = Sales::getByProduct($this->id);
        if (!empty($Disc)) {
            $DT = new Template;
            $DT->set_file('stable', 'sales_table.thtml');
            $DT->set_var('edit_sale_url',
                SHOP_ADMIN_URL . '/index.php?sales');
            $DT->set_block('stable', 'SaleList', 'SL');
            foreach ($Disc as $D) {
                if ($D->getValueType() == 'amount') {
                    $amount = Currency::getInstance()->Format($D->getValue());
                } else {
                    $amount = $D->getValue();
                }
                $DT->set_var(array(
                    'sale_name' => htmlspecialchars($D->getName()),
                    'sale_start' => $D->getStart()->toMySQL(true),
                    'sale_end'  => $D->getEnd()->toMySQL(true),
                    'sale_type' => $D->getValueType(),
                    'sale_amt'  => $amount,
                ) );
                $DT->parse('SL', 'SaleList', true);
            }
            $DT->parse('output', 'stable');
            $T->set_var('sale_prices', $DT->finish($DT->get_var('output')));
        }

        $T->set_var('product_rule_options', Rules\Product::optionList($this->prod_rule));
        $retval .= $T->parse('output', 'product');
        $retval .= COM_endBlock();
        return $retval;
    }   // function showForm()


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @uses    DBO::_toggle()
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $varname    Name of DB field to set
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    private static function toggleField($oldvalue, $varname, $id)
    {
        $newval = self::_toggle($oldvalue, $varname, $id);
        if ($newval != $oldvalue) {
            Cache::clear(self::$TABLE);
            Cache::clear('sitemap');
        }
        return $newval;
    }


    /**
     * Toggles the "enabled field.
     *
     * @uses    self::_toggle()
     * @param   integer $oldvalue   Original value
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        return self::toggleField($oldvalue, 'enabled', $id);
    }


    /**
     * Toggles the "featured" field.
     *
     * @uses    self::_toggle()
     * @param   integer $oldvalue   Original value
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function toggleFeatured($oldvalue, $id)
    {
        return self::toggleField($oldvalue, 'featured', $id);
    }


    /**
     * Determine if this product is mentioned in any purchase records.
     * Typically used to prevent deletion of product records that have
     * dependencies.
     * Can be called as Product::isUsed($item_id)
     *
     * @param   integer $item_id    ID of item to check
     * @return  boolean     True if used, False if not
     */
    public static function isUsed(int $item_id) : bool
    {
        global $_TABLES;

        if (Database::getInstance()->getCount(
            $_TABLES['shop.orderitems'],
            array('product_id'),
            array($item_id),
            array(Database::INTEGER)
        ) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Display the detail page for the product.
     *
     * @param   integer $oi_id  OrderItem ID when linked from an order view
     * @return  string      HTML for the product page.
     */
    public function Detail()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER;

        USES_lib_comments();

        $retval = '';
        $prod_id = $this->id;
        if (!$this->canDisplay()) {
            return $retval;
        }

        // Create a good form ID
        $frm_id = 'add_cart_' . COM_sanitizeID($prod_id . uniqid(), false);

        // Get the currency object which is used repeatedly
        $Cur = Currency::getInstance();

        // Get the related OrderItem object, if any.
        // Used when displaying the product detail from an orde or cart view.
        // If none requested or the current user can't view the order, then
        // create an empty object for later use.
        // $this->sel_opts may also be set in getInstance()if an option
        // string is provided in the item number.
        $this->sel_opts = array();
        if ($this->oi_id > 0) {
            $OI = new OrderItem($this->oi_id);
            foreach ($OI->getOptions() as $OIO) {
                if ($OIO->getOptionID() > 0) {    // not a custom text field
                    $this->sel_opts[] = $OIO->getOptionID();
                    }
            }
        } else {
            $OI = NULL;
        }

        if ($this->hasVariants()) {              // also sets $this->Variants
            if ($OI) {
                // Linked from a cart or order so there is an order ID,
                // make the default variant match.
                $def_id = $OI->getVariantId();
            } else {
                // Get the default variant ID, if any.
                $def_id = $this->getDefVariantID();
            }
            if ($def_id > 0) {
                foreach ($this->Variants as $Variant) {
                    if ($Variant->getID() == $def_id) {
                        $this->setVariant($def_id);
                    }
                }
            }
            // Set the default if a default isn't specified or valid
            if (!$this->hasVariant()) {
                $this->Variant = reset($this->Variants);
            }
        } else {
            // Make sure there's always a variant set to avoid calling
            // functions on null objects
            $this->Variant = new ProductVariant;
        }

        $cbOpts = ProductCheckbox::getByProduct($this->item_id);

        // Set the template dir based on the configured template version
        $T = new Template(array(
            'detail/' . $_SHOP_CONF['product_tpl_ver'],
            'detail',
        ) );
        $T->set_file(array(
            'product'   => 'product_detail_attrib.thtml',
            'prod_info' => 'details_blk.thtml',
        ) );
        // Set up the template containing common javascript
        $JT = new Template('detail');
        $JT->set_file('js', 'detail_js.thtml');

        $name = $this->name;
        $l_dscp = PLG_replaceTags($this->description);
        $s_dscp = PLG_replaceTags($this->short_description);

        // Highlight the query terms if coming from a search
        if (!empty($this->query)) {
            $name   = COM_highlightQuery($name, $this->query);
            $l_dscp = COM_highlightQuery($l_dscp, $this->query);
            $s_dscp = COM_highlightQuery($s_dscp, $this->query);
        }

        $this->_act_price = $this->getSalePrice();

        $qty_disc_txt = '';
        $T->set_block('product', 'qtyDiscTxt', 'disc');
        foreach ($this->qty_discounts as $qty=>$pct) {
            $T->set_var('qty_disc', sprintf($LANG_SHOP['buy_x_save'], $qty, $pct));
            $T->parse('disc', 'qtyDiscTxt', true);
        }

        // Get custom text input fields.
        // Pre-filled values come from the OrderItem object, if any.
        // Otherwise they're blank. Known issue: Anon users have no access
        // to the OI object so the fields won't be pre-filled for them.
        if ('' != $this->custom) {
            $T->set_block('product', 'CustAttrib', 'cAttr');
            $text_field_names = explode('|', $this->custom);
            foreach ($text_field_names as $id=>$text_field_name) {
                $val = '';
                if ($OI) {
                    $opt = $OI->getOptionByOG(0, $text_field_name);
                    if ($opt) {
                        $val = $opt->getValue();
                    }
                }
                $T->set_var(array(
                    'fld_id'    => "cust_text_fld_$id",
                    'fld_name'  => htmlspecialchars($text_field_name),
                    'fld_val'   => htmlspecialchars($val),
                ) );
                $T->parse('cAttr', 'CustAttrib', true);
            }
        }

        // Get the product options, if any, and set them into the form
        $pv_opts = array();     // Collect options to find the product variant
        $this->_orig_price = $this->price;
        $T->set_block('product', 'OptionGroup', 'AG');
        $Sale = $this->getSale();   // Get the effective sale pricing.
        if ($this->hasVariant()) {
            $VarOptions = $this->Variant->getOptions();
        }
        foreach ($this->OptionGroups as $OG) {
            if (count($OG->getOptions()) < 1) {
                // Could happen if options are removed leaving an empty option group.
                continue;
            }
            // Most options use the option group name as the prompt
            $display_name = $OG->getName();
            $T->set_block('product', 'Option' . $OG->getType(), 'optSel');
            switch ($OG->getType()) {
            case 'select':
            case 'radio':
                // First find the selected option.
                // Check that the option is included in the variant, this could
                // get out of sync if variants are created with more options
                // later and the default variant doesn' thave them.
                if ($this->hasVariant() && isset($VarOptions[$OG->getName()])) {
                    $sel_opt = $VarOptions[$OG->getName()]->getID();
                } else {
                    $sel_opt = 0;
                }
                $ogOpts = $OG->getOptions();
                foreach ($ogOpts as $Opt) {
                    if (in_array($Opt->getID(), $this->sel_opts)) {
                        $sel_opt = $Opt->getID();
                    }
                }
                if (!$sel_opt) {    // no selected attribute found
                    $sel_opt = reset($ogOpts)->getID();
                }
                foreach ($ogOpts as $Opt) {
                    $T->set_var(array(
                        'frm_id' => $frm_id,
                        'opt_id' => $Opt->getID(),
                        'opt_str' => htmlspecialchars($Opt->getValue()),
                        'radio_selected' => $Opt->getID() == $sel_opt ? 'checked="checked"' : '',
                        'select_selected' => $Opt->getID() == $sel_opt ? 'selected="selected"' : '',
                    ) );
                    $T->parse('optSel', 'Option' . $OG->getType(), true);
                }
                $pv_opts[] = $sel_opt;
                break;
            case 'checkbox':
                foreach ($OG->getOptions() as $Opt) {
                    $checked = in_array($Opt->getID(), $this->sel_opts) ? 'checked="checked"' : '';
                    $T->set_var(array(
                        'frm_id' => $frm_id,
                        'opt_id' => $Opt->getID(),
                        'opt_str' => htmlspecialchars($Opt->getValue()),
                        'checked' => $checked,
                    ) );
                    $T->parse('optSel', 'Option' . $OG->getType(), true);
                }
                break;

            case 'text':
                $display_name = current($OG->getOptions())->getValue();
                $T->set_var('var_name', 'text_option_' . $OG->getID());
                break;
            }
            $T->set_var(array(
                'og_id'     => $OG->getID(),
                'og_name'   => $display_name,
                'og_type'   => $OG->getType(),
            ) );
            $T->parse('AG', 'OptionGroup', true);
            $T->clear_var('optSel');
        }

        $cbrk = NULL;
        if (!empty($cbOpts)) {
            $T->set_block('product', 'checkboxGroups', 'cbGroup');
            foreach ($cbOpts as $Opt) {
                if ($cbrk != $Opt->getGroupID()) {
                    $cbrk = $Opt->getGroupID();
                    $T->set_var('cb_group_name', $Opt->getGroupName());
                    $T->set_block('product', 'checkboxOptions', 'cbOptions');
                }
                $T->set_var(array(
                    'frm_id' => $frm_id,
                    'option_dscp' => $Opt->getOptionValue(),
                    'option_id' => $Opt->getOptionID(),
                ) );
                $T->parse('cbOptions', 'checkboxOptions', true);
                if ($cbrk != $Opt->getGroupID()) {
                    $T->parse('cbGroup', 'checkboxGroups', true);
                    //$T->clear_var('cbOptions');
                }
            }
            $T->parse('cbGroup', 'checkboxGroups', true);
        }

        // Retrieve the photos and put into the templatea.
        // Get the images for the current Variant, if any. If none then show
        // all attached imates.
        $i = 0;
        $all_images = array();      // for json list of all image information
        $all_image_ids = array();   // for json list of image IDs
        $showImages = $this->getImages();
        if ($this->hasVariant()) {
            $ids = $this->Variant->getImageIDs();
            if (!empty($ids)) {
                $showImages = array();
                foreach ($ids as $id) {
                    if (isset($this->Images[$id])) {
                        $showImages[$id] = $this->Images[$id];
                    }
                }
            }
        }
        foreach ($this->Images as $id=>$prow) {
            if (self::imageExists($prow['filename'])) {
                if (isset($showImages[$id])) {
                    if ($i == 0) {
                        $T->set_var(array(
                            'main_img' => $this->getImage($prow['filename'])['url'],
                            'main_imgfile' => $prow['filename'],
                        ) );
                    }
                    $T->set_block('product', 'Thumbnail', 'PBlock');
                    $T->set_var(array(
                        'img_id'        => $id,
                        'img_file'      => $prow['filename'],
                        'img_url'       => $this->getImage($prow['filename'])['url'],
                        'thumb_url'     => $this->getThumb($prow['filename'])['url'],
                        'session_id'    => session_id(),
                    ) );
                    $T->parse('PBlock', 'Thumbnail', true);
                    $i++;
                }
            }
            // Add to "all images" json
            $all_images[$id] = array(
                'img_file'      => $prow['filename'],
                'img_url'       => $this->getImage($prow['filename'])['url'],
                'thumb_url'     => $this->getThumb($prow['filename'])['url'],
            );
            $all_image_ids[] = $id;
        }

        if ($this->getShipping()) {
            $shipping_txt = sprintf(
                $LANG_SHOP['plus_shipping'],
                $Cur->FormatValue($this->shipping_amt)
            );
        } else {
            $shipping_txt = '';
        }

        $T->set_var(array(
            'have_attributes'   => $this->hasOptions(),
            'cur_code'          => $Cur->getCode(),   // USD, etc.
            'frm_id'            => $frm_id,
            'id'                => $prod_id,
            'name'              => $name,
            'short_description' => $s_dscp,
            'description'       => $l_dscp,
            'cur_decimals'      => $Cur->Decimals(),
            'init_price'        => $Cur->FormatValue($this->_act_price),
            'price'             => $Cur->FormatValue($this->getPrice()),
            'orig_price'        => $Cur->FormatValue($this->_orig_price),
            'on_sale'           => $this->isOnSale(),
            'sale_name'         => $this->isOnSale() ? $this->getSale()->getName() : '',
            'img_cell_width'    => ($_SHOP_CONF['max_thumb_size'] + 20),
            'price_prefix'      => $Cur->Pre(),
            'price_postfix'     => $Cur->Post(),
            'qty_disc'          => count($this->qty_discounts),
            'session_id'        => session_id(),
            'shipping_txt'      => $shipping_txt,
            'rating_bar'        => $this->ratingBar(),
            'brand_id'          => $this->getBrandID(),
            'brand_name'        => $this->getBrandName(),
            'brand_logo_url'    => Supplier::getInstance($this->getBrandID())->getImage()['url'],
            'brand_dscp'        => Supplier::getInstance($this->getBrandID())->getDscp(),
            'is_physical'       => $this->isPhysical(),
            'track_onhand'      => $this->getTrackOnhand(),
            'onhand'            => $this->getOnhand(),
            'weight'            => $this->weight + $this->Variant->getWeight(),
            'weight_unit'       => $_SHOP_CONF['weight_unit'],
            'sku'               => $this->getName(),
            //'lead_time'         => $this->getOnhand() == 0 ? $this->LeadTime() : '',
            'lead_time'    => $this->getOnhand() == 0 ? '(' . sprintf($LANG_SHOP['disp_lead_time'], $this->LeadTime()) . ')' : '',
            'aff_link'          => $this->getAffiliateLink(),
            'lang_copy_clipboard' => $LANG_SHOP['copy_cb'],
            'lang_copy_success' => $LANG_SHOP['copy_cb_success'],
        ) );

        if (!empty($this->Categories)) {
            $T->set_var('categories', true);
            $T->set_block('prod_info', 'CatList', 'CL');
            $catnames = array();
            foreach ($this->Categories as $Cat) {
                $catnames[] = COM_createLink(
                    $Cat->getName(),
                    SHOP_URL . '/index.php?category=' . $Cat->getID()
                );
            }
            $catnames = implode(', ', $catnames);
            $T->set_Var('cat_names', $catnames);
        }

        $Features = $this->getFeatures();
        $zonerule_dscp = $this->getEffectiveZoneRule()->getDscp();
        $T->set_block('prod_info', 'FeatList', 'FL');
        foreach ($Features as $FT) {
            $T->set_var(array(
                'ft_name' => $FT->getName(),
                'fv_text' => $FT->getValue(),
            ) );
            $T->parse('FL', 'FeatList', true);
        }
        $restrictions = array();
        if (!empty($zonerule_dscp)) {
            $restrictions[] = $zonerule_dscp;
        }
        $PCs = Rules\Product::getByProduct($this);
        foreach ($PCs as $PC) {
            $pc_restriction = $PC->getDscp();
            if (!empty($pc_restriction)) {
                $restrictions[] = $pc_restriction;
            }
        }
        if (!empty($restrictions)) {
            if (count($restrictions) > 1) {
                $prule_text = '<ul><li>' . implode('</li><li>', $restrictions) . '</li></ul>';
            } else {
                $prule_text = $restrictions[0];
            }

            $T->set_var(array(
                'ft_name' => $LANG_SHOP['other'],
                'fv_text' => $prule_text,
            ) );
            $T->parse('FL', 'FeatList', true);
        }
        $T->set_var(array(
            'prod_det_blk'  => $T->parse('product', 'prod_info'),
        ) );

        $T->set_block('product', 'SpecialFields', 'SF');
        //var_dump($this->special_fields);die;
        foreach ($this->special_fields as $fld) {
            $T->set_var(array(
                'sf_name'   => $fld['name'],
                'sf_text'   => $fld['text'],
                'sf_class'  => isset($fld['class']) ? $fld['class'] : '',
                'sf_help'   => $fld['help'],
                'sf_type'   => isset($fld['type']) ? $fld['type'] : 'textarea',
            ) );
            $T->parse('SF', 'SpecialFields', true);
        }

        $buttons = $this->PurchaseLinks(Views::DETAIL, $frm_id);
        $T->set_block('product', 'BtnBlock', 'Btn');
        foreach ($buttons as $name=>$html) {
            if ($name == 'add_cart') {
                // Set the add to cart button in the main form
                $T->set_var('add_cart_button', $html);
            } else {
                $T->set_var('buy_now_button', $html);
                $T->parse('Btn', 'BtnBlock', true);
            }
        }

        // Show the user comments if enabled globally and for this product
        if (
            plugin_commentsupport_shop() &&
            $this->comments_enabled != SHOP_COMMENTS_DISABLED
        ) {
                // if enabled or closed
            if ($_CONF['commentsloginrequired'] == 1 && COM_isAnonUser()) {
                // Set mode to "disabled"
                $mode = -1;
            } else {
                $mode = $this->comments_enabled;
            }
            /*$UC = \glFusion\Comments\UserComments::getEngine();
            $Cmt = $UC->withSid($prod_id)
                      ->withTitle($this->short_description)
                      ->withType(Config::PI_NAME)
                      ->withDeleteOption(plugin_ismoderator_shop())
                      ->withCommentCode($mode);
            $T->set_var('usercomments', $Cmt->render());
             */
            $T->set_var(
                'usercomments',
                CMT_userComments(
                    $prod_id, $this->short_description, Config::PI_NAME,
                    '', '', 0, 1, false, plugin_ismoderator_shop(), $mode
                )
            );
        }

        if ($this->isAdmin) {
            // Add the quick-edit link for administrators
            $T->set_var(array(
                'pi_admin_url'  => SHOP_ADMIN_URL,
                'can_edit'      => 'true',
                'from_url'      => COM_getCurrentUrl(),
            ) );
        }
        $JT->set_var(array(
            'have_attributes'   => $T->get_var('have_attributes'),
            'price'             => $T->get_var('price'),
            'frm_id'            => $T->get_var('frm_id'),
            'id'                => $T->get_var('id'),
            'cur_decimals'      => $T->get_var('cur_decimals'),
            'session_id'        => session_id(),
            'orig_price_val'    => $this->_orig_price,
            'img_json'          => json_encode($all_images),
            'all_image_ids'     => json_encode($all_image_ids),
        ) );
        $JT->parse('output', 'js');
        $T->set_var('javascript', $JT->finish($JT->get_var('output')));

        // Update the hit counter
        try {
            Database::getInstance()->conn->executeStatement(
                "UPDATE {$_TABLES['shop.products']} SET views = views + 1 WHERE id = ?",
                array($prod_id),
                array(Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        $retval .= $T->parse('output', 'product');
        $retval = PLG_outputFilter($retval, 'shop');
        return $retval;
    }


    /**
     * Provide the file selector options for files already uploaded.
     *
     * @return  string      HTML for file selection dialog options
     */
    public function FileSelector()
    {
        global $_SHOP_CONF;

        $retval = '';

        $dh = opendir($_SHOP_CONF['download_path']);
        if ($dh) {
            while ($file = readdir($dh)) {
                if ($file == '.' || $file == '..') {    // skip directories
                    continue;
                }
                $sel = $file == $this->filename ? 'selected="selected" ' : '';
                $retval .= "<option value=\"$file\" $sel>$file</option>\n";
            }
            closedir($dh);
        }
        return $retval;
    }


    /**
     * Get the array of error messages as an unumbered list.
     *
     * @return  string      Formatted error messages.
     */
    public function PrintErrors()
    {
        $retval = '';
        if (!empty($this->Errors)) {
            $retval .= '<ul>';
            foreach ($this->Errors as $msg) {
                $retval .= '<li>' . $msg . '</li>';
            }
            $retval .= '</ul>';
        }
        return $retval;
    }


    /**
     * Gets the purchase links appropriate for the product.
     * May be Shop buttons, login-required link, or download button.
     *
     * @param   string  $type   View type where the button will be shown
     * @param   string  $frm_id Form ID, NULL to create a random form
     * @return  array   Array of buttons as name=>html.
     */
    public function PurchaseLinks($type=Views::DETAIL, $frm_id=NULL)
    {
        global $_CONF, $_USER, $_SHOP_CONF, $_TABLES;

        $buttons = array();

        if (!$this->canPurchase()) {
            return $buttons;
        }

        // Don't show any buttons for downloads that aren't available
        // in the buyer's location.
        // Physical item rule checks are done during checkout after the
        // shipping address is known.
        if ($this->isDownload() && !$this->getEffectiveZoneRule()->isOK()) {
            return $buttons;
        }

        $this->_view = $type;

        // Indicate that an "add to cart" button should be returned along with
        // the "buy now" button.  If the product has already been purchased
        // and is available for immediate download, this will be turned off.
        $add_cart = $_SHOP_CONF['ena_cart'] == 1 ? true : false;

        if ($this->prod_type == ProductType::DOWNLOAD && $this->price == 0) {
            // Free, or unexpired downloads for non-anymous
            $T = new Template('buttons');
            $T->set_file('download', 'btn_download.thtml');
            $T->set_var('action_url', SHOP_URL . '/download.php');
            $T->set_var('id', $this->id);
            $buttons['download'] = $T->parse('', 'download');
            $add_cart = false;
        } elseif (
            $_USER['uid'] == 1 &&
            !$_SHOP_CONF['anon_buy']
        ) {
            // Requires login before purchasing
            $T = new Template('buttons');
            $T->set_file('login_req', 'btn_login_req.thtml');
            $buttons['login'] = $T->parse('', 'login_req');
            $add_cart = false;
        } else {
            // Normal buttons for everyone else
            if (
                $this->canBuyNow() &&
                $this->btn_type != ''
            ) {
                // Gateway buy-now buttons only used if no options
                foreach (GatewayManager::getAll() as $gw) {
                    if ($gw->Supports($this->btn_type)) {
                        $buttons[$gw->getName()] = $gw->ProductButton($this);
                    }
                }
            }
        }

        // All users and products get an add-to-cart button, if price > 0
        // and cart is enabled, and product is not a donation. Donations
        // can't be mixed with products, so don't allow adding to the cart.
        if (
            $add_cart &&
            $this->btn_type != 'donation' &&
            $this->canOrder()
            //($this->price > 0 || !$this->canBuyNow())
        ) {
            $T = new Template;
            $T->set_file(array(
                'cart'  => 'buttons/btn_add_cart_attrib.thtml',
            ) );
            $btn_class = 'success';
            $price_vis = '';
            if ($this->track_onhand) {
                $this->getVariants();
                if (count($this->Variants) > 0) {
                    if ($this->Variants[0]->getOnhand() == 0 && $this->oversell > self::OVERSELL_ALLOW) {
                        $btn_class = 'disabled';
                        $price_vis = 'hidden';
                    }
                }
            }

            if ($frm_id === NULL) {
                $frm_id = uniqid();
            } else {
                $frm_id = COM_sanitizeID($frm_id, false);
            }
            $add_form_url = in_array($type, array(Views::BLOCK, Views::LIST));
            $T->set_var(array(
                'item_name'     => htmlspecialchars($this->name),
                'item_number'   => $this->id,
                'frm_id'        => $frm_id,     // to separate multiple blocks
                'short_description' => htmlspecialchars($this->short_description),
                'amount'        => $this->getSalePrice(),
                'base_price'    => $this->getPrice(),
                'action_url'    => SHOP_URL . '/index.php',
                //'form_url'  => $this->hasOptions() ? '' : 'true',
                //'form_url'  => false,
                'form_url'  => $add_form_url,
                'tpl_ver'   => $_SHOP_CONF['product_tpl_ver'],
                'quantity'  => $this->getFixedQuantity(),
                'nonce'     => Cart::getInstance()->makeNonce($this->id . $this->name),
                'max_ord_qty'   => $this->getMaxOrderQty(),
                'min_ord_qty'   => $this->min_ord_qty,
                'btn_cls'   => $btn_class,
                'price_vis' => $price_vis,
            ) );
            $buttons['add_cart'] = $T->parse('', 'cart');
        }
        return $buttons;
    }


    /**
     * Determine if this product has any attributes.
     *
     * @return  boolean     True if attributes exist, False if not.
     */
    public function hasOptions()
    {
        return count($this->OptionGroups);
    }


    /**
     * Set the quantity discounts array.
     * Expects an array or serialized string. If neither are passed in
     * then the discounts are set to an empty array.
     *
     * @param   array|string    $val    Array or serialized string
     * @return  object  $this
     */
    private function setQtyDiscounts($val)
    {
        // Force $val to be an array.
        if (!is_array($val)) {
            $val = @unserialize($val);
            if ($val === false) {
                $val = array();
            }
        }
        ksort($val);
        $this->qty_discounts = $val;
        return $this;
    }


    /**
     * Determine if this product has any quantity-based discounts.
     * Used to display "discounts available" message in the product liet.
     *
     * @return  boolean     True if attributes exist, False if not.
     */
    public function hasDiscounts()
    {
        return empty($this->qty_discounts) ? false : true;
    }


    /**
     * Check if this product uses custom per-product text-input fields
     *
     * @return  boolean     True if custom fields are configured
     */
    public function hasCustomFields()
    {
        $cust = $this->custom;
        return empty($cust) ? false : true;
    }


    /**
     * Check if this product type uses special text-input fields.
     *
     * @return  boolean     True if special fields are configured
     */
    public function hasSpecialFields()
    {
        return empty($this->special_fields) ? false : true;
    }


    /**
     * Add a special field to a product. This is a field supplied by the
     * product, not a custom text string defined by the admin in the
     * product definition. See $this->custom for those fields.
     * The field will not be added if $fld_name already exists for the
     * product.
     * The prompt string may be supplied or, if blank, then $fld_name is used
     * to find a string in $LANG_SHOP. Final fallback is to use the field name
     * as the prompt.
     * Plugins should be sure to set $fld_lang.
     *
     * @param   string  $fld_name   Field Name
     * @param   string  $fld_lang   Field prompt, language string
     * @param   array   $opts       Array of option name=>value
     * @return  object  $this
     */
    public function addSpecialField(string $fld_name, ?string $fld_lang=NULL, ?array $opts=NULL) : self
    {
        global $LANG_SHOP, $LANG_SHOP_HELP;

        if (array_key_exists($fld_name, $this->special_fields)) {
            // Only add if the field doesn't already exist
            return $this;
        }

        if (empty($fld_lang)) {
            // No text supplied, try to get one from the language file.
            $fld_lang = SHOP_getVar($LANG_SHOP, $fld_name);
        }

        // Default to help string from the language file.
        // May be overridden from the $opts array if one is supplied there.
        $fld_help = SHOP_getVar($LANG_SHOP_HELP, $fld_name);

        $this->special_fields[$fld_name] = array(
            'name' => $fld_name,
            'text' => $fld_lang,
            'help' => $fld_help,
        );
        if (is_array($opts)) {
            foreach ($opts as $opt_name=>$opt_data) {
                $this->special_fields[$fld_name][$opt_name] = $opt_data;
            }
        }

        // If not provided in $opts, set the field type
        if (!array_key_exists('type', $this->special_fields[$fld_name])) {
            $this->special_fields[$fld_name]['type'] = 'text';
        }
        return $this;
    }


    /**
     * Determine if a "Buy Now" button is allowed for this item.
     * Items with attributes or a quantity discount schedule must be
     * purchased through the shopping cart to allow for proper price
     * calculation.
     *
     * @return  boolean     True to allow Buy Now, False to disable
     */
    public function canBuyNow()
    {
        if (
            $this->isTaxable()          // need address in cart for tax
            || $this->zone_rule > 0     // has an active zone rule
            || !$this->canOrder()       // Can't be ordered at all, unavailable
            || $this->hasOptions()      // has attributes to select
            || $this->hasDiscounts()    // has quantity-based discounts
            || $this->hasCustomFields() // has text fields to fill in
            || $this->hasSpecialFields()    // has special fields to fill in
        ) {
            // If any of the above apply, then the buy-now button can't be used.
            return false;
        }
        return true;
    }


    /**
     * Get the discount to apply based on the quantity of this item sold.
     *
     * @param   integer $quantity   Quantity of item sold
     * @return  float       Percentage discount to apply
     */
    public function getDiscount($quantity)
    {
        $retval = 0;

        foreach ($this->qty_discounts as $qty=>$discount) {
            $qty = (int)$qty;
            if ($quantity < $qty) {     // haven't reached this discount level
                break;
            } else {
                $retval = (float)$discount;
            }
        }
        return $retval;
    }


    /**
     * Get the base price for this item.
     *
     * @return  float       Base item price
     */
    public function getBasePrice(float $incr_price=0)
    {
        $price = (float)$this->price;
        if (!is_null($this->Variant)) {
            $price += (float)$this->Variant->getPrice();
        }
        $price += $incr_price;
        return $price;
    }


    /**
     * Get the discounted price for the product, including options.
     *
     * @param   integer $qty            Quantity purchased
     * @param   float   $opts_price     Total price of selected options
     * @return  float       Net price considering sales and quantity discounts
     */
    public function getDiscountedPrice($qty=1, $opts_price=0)
    {
        $price = $this->getSale()->calcPrice($this->price + $opts_price);
        $disc_price = Currency::getInstance()->RoundVal(
            $price * (1 - ($this->getDiscount($qty) / 100))
        );
        return $disc_price;
    }


    /**
     * Get the unit price of this product, considering the specified options.
     * Quantity discounts are considered, the return value is the effictive
     * price per unit.
     *
     * @param   array   $opts       Array of integer option values
     * @param   integer $quantity   Quantity, used to calculate discounts
     * @param   array   $override   Override elements (price, uid)
     * @return  float       Product price, including option
     */
    public function getPrice($opts = array(), $quantity = 1, $override = array())
    {
        if ($this->override_price && isset($override['price'])) {
            // If an override price is specified, just return it.
            $this->price = (float)$override['price'];
            return round($this->price, Currency::getInstance()->Decimals());
        }

        // Otherwise start with the effective sale price
        $price = $this->getBasePrice();

        if (!empty($opts)) {
            if (!is_array($opts)) {
                $opts = explode(',', $opts);
            } elseif ($opts[0] instanceof OrderItemOption) {
                $ids = array();
                foreach ($opts as $OIO) {
                    $ids[] = $OIO->getOptionId();
                }
                $opts = $ids;
            }
            $PV = ProductVariant::getByAttributes($this->id, $opts);
            $price += $PV->getPrice();
        }

        // Calculate the discount factor if a quantity discount is in play
        $discount_factor = (100 - $this->getDiscount($quantity)) / 100;

        // Discount the price, including attributes
        $price *= $discount_factor;
        $price = Currency::getInstance()->RoundVal($price);
        return $price;
    }


    /**
     * See if this product allows a custom price to be entered by the user.
     * Standard products do not allow user-entered pricing. This is typically
     * used for Donations.
     *
     * @return  boolean     True if allowed, False if not
     */
    public function allowCustomPrice()
    {
        return false;
    }


    /**
     * Get the formatted price for display.
     * Used mainly to allow child classes to override the displayed price.
     *
     * @param   mixed   $price  Fixed price to use, NULL to use getPrice()
     * @return  string          Formatted price for display
     */
    public function getDisplayPrice($price = NULL)
    {
        if ($price === NULL) $price = $this->getSalePrice();
        return Currency::getInstance()->Format($price);
    }


    /**
     * Create and return a SKU for this product and the selected options.
     *
     * @param   object  $item   OrderItem object
     * @return  string      SKU string containing selected options.
     */
    public function getSKU($item)
    {
        $sku = $this->name;

        // Get attributes selected from the available options
        // Skip any that don't have a sku value set
        foreach ($item->options as $opt_id=>$OIO) {
            if (
                isset($this->Options[$OIO->getID()]) &&
                $this->Options[$OIO->getID()]['sku'] != ''
            ) {
                $sku .= '-' . $this->Options[$OIO->getID()]['sku'];
            }
        }
        return $sku;
    }


    /**
     * Get the descriptive values for a specified set of options.
     *
     * @param   array   $options    Array of integer option values
     * @return  string      Comma-separate list of text values, or empty
     */
    public function getOptionDesc($options = array())
    {
        $opts = array();
        if (!is_array($options)) {
            $options = explode(',', $options);
        }
        foreach ($options as $option) {
            $key = NULL;
            if ($option instanceof OrderItemOption) {
                $key = $option->getOptionID();
            } elseif (is_string($key) && strpos($key, '|') !== false) {
                // complete option strings
                list($key, $junk) = explode('|', $key);
            }
            if (isset($this->options[$key])) {
                $opts[] = $this->options[$key]['attr_value'];
            }
        }
        if (!empty($opts)) {
            $retval = implode(', ', $opts);
        } else {
            $retval = '';
        }
        return $retval;
    }


    /**
     * Handle the purchase of this item.
     *  - Update qty on hand if track_onhand is set (min. value 0)
     *
     * @param   object  $Item       Item record, to get options, etc.
     * @param   array   $ipn_data   IPN data (not used in this class)
     * @return  integer     Zero or error value
     */
    public function handlePurchase(OrderItem &$Item, IPN $IPN) : int
    {
        global $_TABLES;

        $status = 0;

        // update the qty on hand, if tracking and not already zero
        if ($this->track_onhand) {
            Stock::recordPurchase($this->id, $Item->getVariantId(), $Item->getQuantity());
            Cache::clear(self::$TABLE);
            Cache::clear('sitemap');
        }
        return $status;
    }


    /**
     * Handle a product refund.
     *
     * @param   object  $OI     OrderItem being refunded
     * @param   object  $IPN    Shop IPN data
     * @return  boolean     True on success, False on error
     */
    public function handleRefund(OrderItem $OI, IPN $IPN) :bool
    {
        return true;
    }


    /**
     * Handle a "cancel purchase" message.
     *
     * @param   object  $Order      Order object.
     * @param   array   $ipn_data   IPN data received
     */
    public function cancelPurchase($Order, $ipn_data = array())
    {
    }


    /**
     * Get an option object from the OptionGroups property.
     * Returns false if the option key is not found.
     *
     * @param   string  $key    Option name to retrieve
     * @return  mixed       Option value, False if not set
     */
    public function getOption($key)
    {
        foreach ($this->OptionGroups as $OG) {
            if (isset($OG->getOptions()[$key])) {
                return $OG->getOptions()[$key];
            }
        }
        return false;
    }


    /**
     * Get all the options for this product.
     *
     * @todo determine if this still works
     * @deprecated
     * @return  array       Array of options
     */
    public function getOptions()
    {
        return $this->Options;
    }


    /**
     * Get all the option groups related to this product.
     *
     * @return  array       Array of OptionGroup objects
     */
    public function getOptionGroups()
    {
        return $this->OptionGroups;
    }


    /**
     * Get the prompt for a custom field.
     * Returns "Undefined" if for some reason the field isn't defined.
     *
     * @param   integer $key    Array key into the $custom fields
     * @return  string      Custom field name, or "undefined"
     */
    public function getCustom($key=NULL)
    {
        static $custom = NULL;
        if ($custom === NULL) {
            $custom = explode('|', $this->custom);
        }
        if ($key === NULL) {
            return $custom;
        } elseif (isset($custom[$key])) {
            return $custom[$key];
        } else {
            return 'Undefined';
        }
    }

    /**
     * Get the shipping weight for one unit of this product.
     *
     * @return  float   Item weight
     */
    public function getWeight()
    {
        return (float)$this->weight;
    }


    /**
     * Get the product class ID assigned to this product.
     *
     * @return  integer     Product Class ID
     */
    public function getProductRuleId() : int
    {
        return $this->prod_rule;
    }


    /**
     * Get the effective product class object for this product.
     *
     * @return  object      Product Rule object
     */
    public function getEffectiveProductRule() : \Shop\Rules\Product
    {
        if ($this->productRule == NULL) {
            $this->productRule = Rules\Product::getEffective($this);
        }
        return $this->productRule;
    }


    /**
     * Duplicate this product.
     *  - Save the original product ID
     *  - Creates a new product record and get the new ID
     *  - Copies all images from oldid_x to newid_x
     *  - Creates records in the images table
     *
     * @return boolean     True on success, False on failure
     */
    public function Duplicate() : bool
    {
        global $_TABLES, $_SHOP_CONF;

        if ($this->id == 0 || self::isPluginItem($this->id)) {
            // Don't handle new items or plugin products
            return false;
        }

        // Save the original ID, needed to copy image files
        $old_id = $this->id;

        // Set product variables to indicate a new product and save it.
        $this->isNew = true;
        $this->id = 0;
        $this->name = $this->name . '_' . uniqid();
        $this->saveToDB();
        if ($this->id < 1) {
            Log::write('shop_system', Log::ERROR, __METHOD__ . ": Error duplicating product id $old_id");
            return false;
        }
        $new_id = $this->id;

        // Now clone the other records
        $s1 = ProductVariant::cloneProduct($old_id, $new_id);
        $s2 = Category::cloneProduct($old_id, $new_id);
        $s3 = Images\Product::cloneProduct($old_id, $new_id);
        $s4 = Feature::cloneProduct($old_id, $new_id);
        return $s1 && $s2 && $s3 && $s4;
    }


    /**
     * Determine if this product is on sale.
     *
     * @return  boolean True if on sale, false if not
     */
    public function isOnSale()
    {
        $price = $this->getBasePRice();
        $sp = $this->getSalePrice();
        return $price > 0 && $sp < $price ? true : false;
    }


    /**
     * Get the sale price for this item, if any.
     * First checks for an item-specific sale price and sale period,
     * then traverses up the category tree to find the first parent
     * category with an effective sale price.
     * Prices are cached for repeated calls.
     *
     * @see     self::isOnSale()
     * @param   float   $price  Optional price override
     * @return  float   Sale price, normal price if not on sale
     */
    public function getSalePrice(?float $price = NULL) : float
    {
        if ($price === NULL) {
            $price = $this->getBasePrice();
        }
        return Currency::getInstance()->RoundVal(
            $this->getSale()->calcPrice($price)
        );
    }


    /**
     * Sets and returns the private Sale object as the current effective sale.
     *
     * @return  object      Sale object
     */
    public function getSale()
    {
        if ($this->Sale === NULL) {
            $this->Sale = Sales::getEffective($this);
        }
        return $this->Sale;
    }


    /**
     * Check if an item can be ordered.
     * Uses canDisplay() to check access, availability dates, etc., then
     * rechecks stock status against the product's oversell setting.
     *
     * @uses    self::canDisplay()
     * @return  boolean     True if the product can be ordered.
     */
    public function canOrder()
    {
        if (
            $this->canDisplay() &&
            ($this->isInStock() || $this->oversell == self::OVERSELL_ALLOW)
        ) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Check if this product can be displayed or purchased due to stock status.
     *
     * @param   integer $requested  Requested oversell value to sell item
     * @return  boolean     True if condition is met, False if not.
     */
    public function isInStock()
    {
        $status = false;

        // Not tracking stock, or have stock on hand, return true
        if ($this->track_onhand == 1) {
            $this->getVariants();
            if (!empty($this->Variants)) {
                foreach ($this->Variants as $V) {
                    if ($V->getOnhand() > 0) {
                        $status = true;
                    }
                }
            } elseif ($this->getOnhand() > 0) {
                $status = true;
            }
        } else {
            $status = true;
        }
        return $status;
    }


    /**
     * Determine if a product can be displayed in the catalog.
     * Default availability dates are from 1900-01-01 to 9999-12-31.
     *
     * @param   integer $uid    User ID, current user if null
     * @return  boolean True if on sale, false if not
     */
    public function canDisplay(?int $uid = NULL) : bool
    {
        global $_USER;

        if ($uid === NULL) {
            $uid = (int)$_USER['uid'];
        }

        // If the product is disabled, return false now
        if ($this->id < 1 || !$this->enabled) {
            return false;
        }

        // Check the user's permission, if not admin
        if (!$this->hasAccess($uid)) {
            return false;
        }

        // If not in stock and oversell set to Hide, return false.
        if (!$this->isInStock() && $this->oversell == self::OVERSELL_HIDE) {
            return false;
        }

        // Check that today is within the product's availability window
        $today = SHOP_now()->format('Y-m-d', true);
        if ($today < $this->avail_beg || $today > $this->avail_end) {
            return false;
        }

        // Finally, no conditions failed, return true
        return true;
    }


    /**
     * Get the quantity that would be backordered given an order qty.
     *
     * @param   float   $qty    Order quantity
     * @return  float       Backordered portion
     */
    public function getQuantityBO($qty)
    {
        if ($this->track_onhand) {
            $avail = $this->getOnhand();
            return max($qty - $avail, 0);
        } else {
            return 0;
        }
    }


    /**
     * Get the max allowed order quantity for this item.
     * If backordering is not allowed, then this is the quantity on hand.
     * Otherwise the quantit is unlimited.
     *
     * @return  integer     Max allowed order quantity
     */
    public function getMaxOrderQty()
    {
        $max = $this->max_ord_qty == 0 ? self::MAX_ORDER_QTY : $this->max_ord_qty;
        $onhand = $this->getOnhand();
        if (!$this->track_onhand || $this->oversell == self::OVERSELL_ALLOW) {
            return $max;
        } else {
            return min($onhand, $max);
        }
    }


    /**
     * Get the max order quantity for this item.
     * This may be added as a configuration item.
     *
     * @return  integer     Max quantity that can be ordered at once
     */
    public function getMinOrderQty()
    {
        return (int)min($this->min_ord_qty, 1);
    }


    /**
     * Verify whether a desired quantity can be ordered.
     * Returns the adjusted quantity if the requested value is not allowed.
     *
     * @param   integer $qty    Requested quantity
     * @return  float       Max allowed quantity
     */
    public function validateOrderQty($qty) : float
    {
        $max = $this->getMaxOrderQty();
        $min = $this->getMinOrderQty();

        // If in some strage case the max is less than the minimum
        // due to the onhand value, return zero
        if ($max < $min) {
            $qty = 0;
        } else {
            // Make sure the qty is at least the minimum amount
            $qty = max($qty, $min);
            // Then make sure it doesn't exceed the maximum
            $qty = min($qty, $max);
        }
        return $qty;
    }


    /**
     * Check if tax should be charged on this item.
     * Checks both the product taxable flag and the configured tax rate.
     *
     * @return  boolean     True if taxable and there is a tax rate
     */
    public function isTaxable()
    {
        return $this->taxable ? 1 : 0;
    }


    /**
     * Display the date, if present, or a blank field if effectively null.
     *
     * @param   string  $str    Date string, "0000-00-00" indicates empty
     * @return  string      Supplied date string, or "" if zeroes
     */
    private static function _InputDtFormat($str)
    {
        if ($str < '1970-01-02' || $str == Dates::MAX_DATE) {
            return '';
        } else {
            return $str;
        }
    }


    /**
     * Determine if a given item number belongs to a plugin.
     * Looks for a colon in the item number, which will indicate a plugin
     * item number formated as "pi_name:item_number:other_opts"
     *
     * @param   string  $item_number    Item Number to check
     * @return  boolean     True if it's a plugin item, false if it's ours
     */
    public static function isPluginItem(string $item_number) : bool
    {
        if (strpos($item_number, ':') > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get the name of the plugin supplying the product.
     * Returns `shop` for catalog items.
     *
     * @return  string      Plugin name
     */
    public function getPluginName() : string
    {
        return $this->pi_name;
    }


    /**
     * Get the text string and value for special fields.
     * Used when displaying cart info
     *
     * @param   array   $values     Special field values
     * @return  array       Array of text=>value
     */
    public function getSpecialFields(array $values = array()) : array
    {
        global $LANG_SHOP;

        $retval = array();
        if (!is_array($values)) {
            return $retval;
        }
        foreach ($this->special_fields as $fld_name=>$fld) {
            if (array_key_exists($fld_name, $values) && !empty($values[$fld_name])) {
                $retval[$fld['text']] = $values[$fld_name];
            }
        }
        return $retval;
    }


    /**
     * Helper function to create the cache key.
     *
     * @param   string  $id     Item ID
     * @param   string  $type   Optional item type
     * @return  string      Cache key
     */
    private static function _makeCacheKey($id, $type='')
    {
        if ($type != '') $type .= '_';
        return 'product_' . $type . $id;
    }


    /**
     * Determine if the current user has access to view this product.
     * If the user has access to at least one parent category, return true.
     *
     * @param   int     $uid    User ID, null = current user
     * @return  boolean     True if access and purchase is allowed.
     */
    public function hasAccess(?int $uid = NULL) : bool
    {
        global $_GROUPS;

        if ($uid === NULL) {
            $groups = $_GROUPS;
        } else {
            $groups = SEC_getUserGroups($uid);
        }

        if (self::isPluginItem($this->item_id)) {
            return true;
        }
        $Cats = $this->getCategories();
        foreach ($this->getCategories() as $Cat) {
            if ($Cat->hasAccess($groups)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Get the product name. Allows for an override.
     *
     * @param   string  $override  Optional name override
     * @return  string              Product Name
     */
    public function getName($override = '')
    {
        return $override == '' ? $this->name : $override;
    }


    /**
     * Get the product short description. Allows for an override.
     *
     * @param   string  $override  Optional description override
     * @return  string              Product sort description
     */
    public function getText($override = '')
    {
        return $override == '' ? $this->description : $override;
    }


    /**
     * Get the product short description. Allows for an override.
     *
     * @param   string  $override  Optional description override
     * @return  string              Product sort description
     */
    public function getDscp($override = '')
    {
        return $override == '' ? $this->short_description : $override;
    }


    /**
     * Get the reorder quantity set for this product.
     *
     * @return  float   Reorder quantity
     */
    public function getReorder()
    {
        return $this->getStock()->getReorder();
    }


    /**
     * Get the quantity of stock items currently reserved for cards.
     *
     * @return  float       Quantity of items reserved
     */
    public function getReserved()
    {
        return $this->getStock()->getReserved();
    }


    /**
     * Get the button type to use for the purchase link.
     *
     * @return  string      Button type, e.g. `buy_now`
     */
    public function getBtnType()
    {
        return $this->btn_type;
    }


    /**
     * Get the affiliate referral link for the product.
     * Does not use COM_buildUrl().
     *
     * @return  string      Affiliate referral URL
     */
    public function getAffiliateLink()
    {
        return Customer::getInstance()->getAffiliateLink($this->id);
    }


    /**
     * Get the URL to the item detail page.
     *
     * @param   integer $oi_id  Order Item ID
     * @param   string  $q      Query string. Should be url-encoded already
     * @return  string      Item detail URL
     */
    public function getLink()
    {
        $id = Config::get('use_sku') ? $this->name : $this->id;
        $url = SHOP_URL . '/detail.php?id=' . $id;
        if ($this->oi_id > 0 || $this->query != '') {
            $url .= '&oi_id=' . (int)$this->oi_id;
            if ($this->query != '') {
                $url .= '&query=' . $this->query;
            }
        }
        return COM_buildUrl($url);
    }


    /**
     * Get additional text to add to the buyer's receipt for a product.
     *
     * @param   object  $OI     OrderItem object to check, not used by default
     */
    public function EmailExtra(OrderItem $OI) : string
    {
        return '';
    }


    /**
     * Get the total shipping amount for this item based on quantity purchased/
     *
     * @param   integer $qty    Quantity purchased
     * @return  float           Total item fixed shipping charge
     */
    public function getShipping($qty = 1)
    {
        return $this->shipping_amt * (float)$qty;
    }


    /**
     * Get the shipping type for this product.
     *
     * @return  integer     Shipping type code
     */
    public function getShippingType()
    {
        return (int)$this->shipping_type;
    }


    /**
     * Get the total handling fee for this item based on quantity purchased
     *
     * @param   integer $qty    Quantity purchased
     * @return  float           Total handling charge
     */
    public function getHandling($qty = 1)
    {
        return 0;   // not implemented yet
        //return (float)$this->handling * $qty;
    }


    /**
     * Get the fixed quantity that can be ordered per item view.
     * If this is zero, then an input box will be shown for the buyer to enter
     * a quantity. If nonzero, then the input box is a hidden variable with
     * the value set to the fixed quantity
     *
     * return   @integer    Fixed quantity number, zero for varible qty
     */
    public function getFixedQuantity()
    {
        return $this->_fixed_q;
    }


    /**
     * Determine if like items can be accumulated in the cart under a single
     * line item.
     * Normal products can be accumulated but some plugin products may not.
     *
     * @return  boolean     True if items can be accumulated, False if not
     */
    public function cartCanAccumulate()
    {
        return true;
    }


    /**
     * Get the out-of-stock message to display on product pages.
     *
     * @return  string      Text for OOS message
     */
    public function getLeadTimeMessage()
    {
        global $LANG_SHOP;

        $lt_msg = '';
        // trim the leadtime since it isn't trimmed in the DB
        // to allow "nothing" instead of inheriting the supplier
        // lead time.
        if (trim($this->LeadTime()) != '') {
            $lt_msg = sprintf(
                $LANG_SHOP['disp_lead_time'],
                $this->LeadTime()
            );
        }
        return $lt_msg;
    }


    /**
     * Check if this item is out of stock.
     *
     * @return  integer     Zero to behave normally, or 1 or 2 if out of stock.
     */
    private function _OutOfStock()
    {
        if ($this->getOnhand() > 0) {
            return self::OVERSELL_ALLOW;
        } else {
            return $this->oversell;
        }
        return 0;
    }


    /**
     * Helper function to check if this item has a downloadable component.
     * Set $only to true to check if the item is only downloadable, e.g. no
     * virtual or physical component.
     *
     * @param   boolean $only   True to check if only download
     * @return  boolean     True if this is a physical item, False if not.
     */
    public function isDownload($only = false)
    {
        if ($only) {
            $retval = ($this->prod_type == ProductType::DOWNLOAD);
        } else {
            $retval = ($this->prod_type & ProductType::DOWNLOAD) == ProductType::DOWNLOAD;
        }
        return $retval;
    }


    /**
     * Get the product type.
     *
     * @return  intger  Product type.
     */
    public function getProductType()
    {
        return (int)$this->prod_type;
    }


    /**
     * Helper function to check if this item has a physical component.
     *
     * @return  boolean     True if this is a physical item, False if not.
     */
    public function isPhysical()
    {
        return ($this->prod_type & ProductType::PHYSICAL);
    }


    /**
     * Get the images for this product.
     * Checks the cache first.
     * Also sets $this->Images
     *
     * @return  array   Array of images
     */
    public function getImages()
    {
        global $_TABLES;

        $cache_key = self::_makeCacheKey($this->id, 'img');
        //$this->Images = Cache::get($cache_key);
        if ($this->Images === NULL) {
            $this->Images = array();
            try {
                $rows = Database::getInstance()->conn->executeQuery(
                    "SELECT img_id, filename, orderby FROM {$_TABLES['shop.images']}
                    WHERE product_id = ?
                    ORDER BY orderby ASC",
                    array($this->id),
                    array(Database::STRING)
                )->fetchAllAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $rows = false;
            }
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    if (self::imageExists($row['filename'])) {
                        $this->Images[$row['img_id']] = $row;
                    } else {
                        // Might as well remove DB records for images that don't exist.
                        $this->deleteImage($row['img_id']);
                    }
                }
            }
            Cache::set($cache_key, $this->Images, self::$TABLE);
        }
        return $this->Images;
    }


    /**
     * Get a single image to display in a block or product list.
     *
     * @return  string      Image filename, or empty string if there is none.
     */
    public function getOneImage()
    {
        $retval = '';
        $Images = $this->getVariantImages();
        if (!is_array($Images) || empty($Images)) {
            $retval = '';
        } else {
            $img = reset($Images);
            $retval = $img['filename'];
        }
        return $retval;
    }


    /**
     * Update a product rating and perform related housekeeping tasks.
     *
     * @see     plugin_itemrated_shop()
     * @param   integer $id     Product ID
     * @param   integer $rating New rating value
     * @param   integer $votes  New total number of votes
     * @return  boolean     True on success, False on DB error
     */
    public static function updateRating(string $id, float $rating, int $votes) : bool
    {
        global $_TABLES;

        $id = (int)$id;
        $rating = number_format($rating, 2, '.', '');
        $votes = (int)$votes;
        try {
            Database::getInstance()->conn->update(
                $_TABLES['shop.products'],
                array('rating' => $rating, 'votes' => $votes),
                array('id' => $id),
                array(Database::STRING, Database::INTEGER, Database::STRING)
            );
            Cache::clear(self::$TABLE);
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Check if there are any products in the database.
     * Used to determine if data can be migrated from Paypal.
     *
     * @return  boolean     True if orders table is empty
     */
    public static function haveProducts() : bool
    {
        global $_TABLES;

        return (
            Database::getInstance()->getCount($_TABLES['shop.products']) > 0
        );
    }


    /**
     * Get an array of all products.
     * Filtering is left to the caller.
     *
     * @return  array   Array of product objects
     */
    public static function getAll() : array
    {
        global $_TABLES;

        $cache_key = 'getall_products';
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            try {
                $stmt = Database::getInstance()->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.products']} ORDER BY name ASC"
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if (!empty($stmt)) {
                while ($A = $stmt->fetchAssociative()) {
                    $retval[$A['id']] = self::getInstance($A);
                }
            }
            Cache::set($cache_key, $retval, self::$TABLE);
        }
        return $retval;
    }


    /**
     * Check if only one of this product may be added to the cart.
     * Buyers can normally buy any number of a product repeatedly.
     *
     * @return  boolean     True if product can be purchased only once
     */
    public function isUnique()
    {
        return false;
    }


    /**
     * Check if an image file exists on the filesystem.
     *
     * @param   string  $filename   Image filename, no path
     * @return  boolean     True if image file exists, False if not
     */
    public static function imageExists($filename)
    {
        global $_SHOP_CONF;

        return is_file($_SHOP_CONF['image_dir'] . DIRECTORY_SEPARATOR . $filename);
    }


    /**
     * Get the URL to a product image.
     *
     * @param   string  $filename   Image filename, no path
     * @param   integer $width      Optional width, assume thumbnail
     * @param   integer $height     Optional height, assume thumbnail
     * @return  array       Array of (url, width, height)
     */
    public function getImage($filename = '', $width = 0, $height = 0)
    {
        // If no filename specified, get the first image name.
        if ($filename == '') {
            $filename = $this->getOneImage();
        }
        return Images\Product::getUrl($filename, $width, $height);
    }


    /**
     * Get the image information for a thumbnail image.
     *
     * @uses    Images\Product::getUrl()
     * @param   string  $filename   Image filename
     * @return  array       Array of (url, width, height)
     */
    public function getThumb($filename='')
    {
        // If no filename specified, get the first image name.
        if ($filename == '') {
            $filename = $this->getOneImage();
        }
        return Images\Product::getUrl($filename, 200);
    }


    /**
     * Check if this product supports product ratings.
     * Returns false if ratings are globaly disabled.
     *
     * @return  boolean     True if ratings are supported, False if not
     */
    public function supportsRatings()
    {
        global $_SHOP_CONF;

        return ($_SHOP_CONF['ena_ratings'] == 1) && $this->rating_enabled;
    }


    /**
     * Check if comments are enabled for this product.
     *
     * @return  integer     1 if enabled, 0 if not
     */
    public function commentsEnabled()
    {
        return $this->comments_enabled ? 1 : 0;
    }


    /**
     * Check if the product is enabled for purchase.
     *
     * @return  integer     1 if enabled, 0 if not
     */
    public function isEnabled()
    {
        return $this->enabled ? 1 : 0;
    }


    /**
     * Get the rating bar, if supported.
     *
     * @param   boolean $force_static   True to force static display.
     * @return  string      HTML for rating bar
     */
    public function ratingBar($force_static = false)
    {
        global $_USER;

        $ratedIds = RATING_getRatedIds($this->pi_name);

        if ($this->supportsRatings()) {
            if (in_array($this->id, $ratedIds)) {
                $static = 1;
                $voted = 1;
            } elseif (!$force_static && plugin_canuserrate_shop($this->id, $_USER['uid'])) {
                $static = 0;
                $voted = 0;
            } else {
                $static = 1;
                $voted = 0;
            }
            $retval = RATING_ratingBar(
                $this->pi_name,
                $this->id,
                $this->votes,
                $this->rating,
                $voted, 5, $static, 'sm'
            );
        } else {
            $retval = '';
        }
        return $retval;
    }


    /**
     * Get the cancel URL for buy-now buttons when the payment is cancelled.
     * Cart always uses cart.php.
     *
     * @return  string      URL to pass to the gateway for cancelling payment
     */
    public function getCancelUrl()
    {
        return ($this->cancel_url) ? $this->cancel_url : SHOP_URL . '/index.php';
    }


    /**
     * Render the form for updating product attributes in bulk.
     *
     * @param   array   $ids    Array of product IDs
     * @return  string      HTML form for update.
     */
    public static function BulkUpdateForm($ids=array())
    {
        global $LANG_SHOP, $_SHOP_CONF;

        if (empty($ids)) {
            SHOP_setMsg("No products selected");
            echo COM_refresh(SHOP_ADMIN_URL . '/index.php?products');
        }
        $ids = implode(',', $ids);
        $T = new Template;
        $T->set_file('form', 'prod_bulk_form.thtml');
        $T->set_var(array(
            'prod_ids'  => $ids,
            'currency'  => Currency::getInstance(),
            'brand_select' => Supplier::getBrandSelection(),
            'supplier_select' => Supplier::getSupplierSelection(),
            'available_cats' => self::getCatSelections(0)[0],
            'zone_rule_options' => Rules\Zone::optionList(),
        ) );
        $T->set_block('form', 'ProdTypeRadio', 'ProdType');
        foreach ($LANG_SHOP['prod_types'] as $value=>$text) {
            if ($value == ProductType::COUPON && $_SHOP_CONF['gc_enabled'] == 0) {
                continue;
            }
            $T->set_var(array(
                'type_val'  => $value,
                'type_txt'  => $text,
            ));
            $T->parse('ProdType', 'ProdTypeRadio', true);
        }
        return $T->parse('output', 'form');
    }


    /**
     * Perform the bulk update of multiple products at once.
     *
     * @param   array   $A      Values from $_POST
     * @return  boolean     True on success, False on failure
     */
    public static function BulkUpdateDo($A)
    {
        global $_TABLES;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        $ids = explode(',', $A['prod_ids']);
        $count = 0;

        if (isset($A['supplier_id']) && $A['supplier_id'] > -1) {
            $qb->set('supplier_id', ':sup_id')
               ->setParameter('sup_id', $A['supplier_id'], Database::INTEGER);
            $count++;
        }
        if (isset($A['brand_id']) && $A['brand_id'] > -1) {
            $qb->set('brand_id', ':brand_id')
               ->setParameter('brand_id', $A['brand_id'], Database::INTEGER);
            $count++;
        }
        if (isset($A['price']) && $A['price'] !== '') {
            $qb->set('price', ':price')
               ->setParameter('price', $A['price'], Database::STRING);
            $count++;
        }
        if (isset($A['taxable']) && $A['taxable'] > -1) {
            $qb->set('taxable', ':taxable')
               ->setParameter('taxable', $A['taxable'], Database::INTEGER);
            $count++;
        }
        if (isset($A['prod_type']) && $A['prod_type'] > -1) {
            $qb->set('prod_type', ':prod_type')
               ->setParameter('prod_type', $A['prod_type'], Database::INTEGER);
            $count++;
        }
        if (isset($A['rule_id']) && $A['rule_id'] > -1) {
            $qb->set('rule_id', ':rule_id')
               ->setParameter('rule_id', $A['rule_id'], Database::INTEGER);
            $count++;
        }
        if ($count > 0) {
            try {
                $qb->update($_TABLES['shop.products'])
                   ->where('id IN (:ids)')
                   ->setParameter('ids', $ids, Database::PARAM_INT_ARRAY)
                   ->execute();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }

        // If any categories were supplied, use them to replace any existing
        // ones for all submitted products.
        if (isset($A['selected_cats']) && !empty($A['selected_cats'])) {
            try {
                $db->conn->executeStatement(
                    "DELETE FROM {$_TABLES['shop.prodXcat']} WHERE product_id IN (?)",
                    array($ids),
                    array(Database::PARAM_INT_ARRAY)
                );
                $status = true;
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $status = false;
            }
            try {
                $stmt = $qb->execute();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($status) {
                $cat_ids = explode('|', $A['selected_cats']);
                $vals = array();
                foreach ($prod_ids as $prod_id) {
                    $prod_id = (int)$prod_id;
                    foreach ($cat_ids as $cat_id) {
                        $cat = (int)$cat_id;
                        $vals[] = "($prod_id, $cat_id)";
                    }
                }
                $sql = "INSERT IGNORE INTO {$_TABLES['shop.prodXcat']}
                    (product_id, cat_id) VALUES " . implode(',', $vals);
                try {
                    $db->conn->executeStatement($sql);
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                    return false;
                }
            }
        }

        Cache::clear(self::$TABLE);
        return true;
    }


    /**
     * Product Admin List View.
     *
     * @param   integer $cat_id     Optional category ID to limit listing
     * @return  string      HTML for the product list.
     */
    public static function adminList(?int $cat_id=NULL) : string
    {
        global $_SHOP_CONF, $_TABLES, $LANG_SHOP,
            $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT
                p.id, p.name, p.short_description, p.description, p.price,
                p.prod_type, p.enabled, p.featured,
                p.avail_beg, p.avail_end, p.track_onhand, p.oversell
            FROM {$_TABLES['shop.products']} p";
        if ($cat_id > 0) {
            $sql .= " LEFT JOIN {$_TABLES['shop.prodXcat']} pxc
                ON p.id = pxc.product_id";
        }

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['copy'],
                'field' => 'copy',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['featured'],
                'field' => 'featured',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['product'],
                'field' => 'name',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['description'],
                'field' => 'short_description',
                'sort' => true,
            ),
            array(
                'text'  => $LANG_SHOP['price'],
                'field' => 'price',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['prod_type'],
                'field' => 'prod_type',
                'sort' => true,
            ),
            /*array(
                'text'  => $LANG_SHOP['status'],
                'field' => 'availability',
                'sort'  => false,
                'align' => 'center',
            ),*/
            array(
                'text'  => $LANG_ADMIN['delete'] . '&nbsp;' .
                FieldList::info(array(
                    'title' => $LANG_SHOP_HELP['hlp_prod_delete'],
                ) ),
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'name',
            'direction' => 'asc',
        );

        $display .= COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/index.php?editproduct=x',
            'style' => 'success',
        ) );

        // Filter on category, brand and supplier
        $cat_id = SHOP_getVar($_GET, 'cat_id', 'integer', 0);
        $brand_id = SHOP_getVar($_GET, 'brand_id', 'integer', 0);
        $supplier_id = SHOP_getVar($_GET, 'supplier_id', 'integer', 0);
        $def_filter = 'WHERE 1=1';
        if ($cat_id > 0) {
            $def_filter .= " AND pxc.cat_id= $cat_id";
        }
        if ($brand_id > 0) {
            $def_filter .= " AND p.brand_id = $brand_id";
        }
        if ($supplier_id > 0) {
            $def_filter .= " AND p.supplier_id = $supplier_id";
        }

        $query_arr = array(
            'table' => 'shop.products',
            'sql'   => $sql,
            'query_fields' => array(
                'p.name',
                'p.short_description',
                'p.description',
            ),
            'default_filter' => $def_filter,
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . "/index.php?products&cat_id=$cat_id&brand+id=$brand_id&supplier_id=$supplier_id",
        );

        $bulk_update = FieldList::button(array(
            'name' => 'prod_bulk_frm',
            'text' => $LANG_SHOP['update'],
            'value' => 'x',
            'size' => 'mini',
            'class' => 'tooltip',
            'attr' => array(
                'title' => $LANG_SHOP['bulk_update'],
            ),
        ) );
        $bulk_update .= FieldList::button(array(
            'name' => 'prod_bulk_del',
            'text' => $LANG_SHOP['delete'],
            'value' => 'x',
            'size' => 'mini',
            'class' => 'tooltip',
            'style' => 'danger',
            'attr' => array(
                'title' => $LANG_SHOP['bulk_delete'],
                'onclick' => "return confirm('" . $LANG_SHOP['q_del_items'] . "');",
            ),
        ) );
        $bulk_update .= FieldList::button(array(
            'name' => 'prod_bulk_reset',
            'text' => $LANG_SHOP['reset_ratings'],
            'value' => 'x',
            'size' => 'mini',
            'class' => 'tooltip',
            'style' => 'primary',
            'attr' => array(
                'title' => $LANG_SHOP['bulk_reset'],
                'onclick' => "return confirm('{$LANG_SHOP['q_reset_ratings']}');",
            ),
        ) );
        $options = array(
            'chkdelete' => true,
            'chkall' => true,
            'chkfield' => 'id',
            'chkname' => 'prod_bulk',
            'chkactions' => $bulk_update,
        );

        // Create the filter by category option
        $sel_options = '<option value="0">' . $LANG_SHOP['all'] . '</option>' . LB;
        $sel_options .= COM_optionList(
                $_TABLES['shop.categories'],
                'cat_id,cat_name',
                $cat_id,
                1
        );
        $filter = $LANG_SHOP['category'] . ': ';
        $filter .= FieldList::select(array(
        //$filter .= FieldList::select(array(
            'name' => 'cat_id',
            'onchange' => "javascript: document.location.href='" .
                SHOP_ADMIN_URL . "/index.php?products" .
                "&amp;brand_id=$brand_id" .
                "&amp;supplier_id=$supplier_id" .
                "&amp;cat_id='+this.options[this.selectedIndex].value",
            'option_list' => $sel_options,
        ) );

        // Create the filter by brand option
        $sel_options = '<option value="0">' . $LANG_SHOP['all'] . '</option>' . LB;
        $sel_options .= COM_optionList(
            $_TABLES['shop.suppliers'],
            'sup_id,company',
            $brand_id,
            1,
            "is_brand=1"
        );
        $filter .= '&nbsp;&nbsp;' . $LANG_SHOP['brand'] . ':';
        $filter .= FieldList::select(array(
            'name' => 'brand_id',
            'onchange' => "javascript: document.location.href='" .
                SHOP_ADMIN_URL . '/index.php?products' .
                "&amp;cat_id=$cat_id" .
                "&amp;supplier_id=$supplier_id" .
                "&amp;brand_id='+this.options[this.selectedIndex].value",
            'option_list' => $sel_options,
        ) );

        // Create the filter by supplier option
        $sel_options = '<option value="0">' . $LANG_SHOP['all'] . '</option>' . LB;
        $sel_options .= COM_optionList(
            $_TABLES['shop.suppliers'],
            'sup_id,company',
            $supplier_id,
            1,
            "is_supplier=1"
        );
        $filter .= '&nbsp;&nbsp;' . $LANG_SHOP['supplier'] . ': ';
        $filter .= FieldList::select(array(
            'name' => 'supplier_id',
            'onchange' => "javascript: document.location.href='" .
                SHOP_ADMIN_URL . '/index.php?products' .
                "&amp;brand_id=$brand_id" .
                "&amp;cat_id=$cat_id" .
                "&amp;supplier_id='+this.options[this.selectedIndex].value",
            'option_list' => $sel_options,
        ) );
        $filter .= '<br />' . LB;

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_productlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the history screen.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra=array())
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;
        static $today = NULL;

        if ($today === NULL) {
            $today = SHOP_now()->format('Y-m-d');
        }
        $retval = '';

        switch($fieldname) {
        case 'copy':
            $retval .= FieldList::copy(array(
                'url' => SHOP_ADMIN_URL . "/index.php?prod_clone=x&amp;id={$A['id']}",
            ) );
            break;

        case 'edit':
            $retval .= FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . "/index.php?return=products&editproduct=x&amp;id={$A['id']}",
            ) );
            break;

        case 'delete':
            if (!\Shop\Product::isUsed($A['id'])) {
                $retval .= FieldList::delete(array(
                    'delete_url' => SHOP_ADMIN_URL. '/index.php?deleteproduct=x&amp;id=' . $A['id'],
                    'attr' => array(
                        'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                        'title' => $LANG_SHOP['del_item'],
                        'class' => 'tooltip',
                    ),
                ) );
            } else {
                $retval = '';
            }
            break;

        case 'enabled':
            $retval .= FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['id']}','enabled','product');",
                'value' => 1,
            ) );
            break;

        /*case 'availability':
            if ($A['avail_beg'] > $today || $A['avail_end'] < $today) {
                $icon = 'avail-expired';
                $caption = $LANG_SHOP['available'] . ' ' . $A['avail_beg'] . ' - ' . $A['avail_end'];
            } elseif ($A['track_onhand'] == 1 && $A['onhand'] < 1) {
                $icon = $A['oversell'] > 0 ? 'avail-unavail' : 'avail-nostock';
                $caption = $LANG_SHOP['out_of_stock'];
            } else {
                $icon = 'avail-ok';
                $caption = $LANG_SHOP['available'] . '.';
                if ($A['track_onhand'] == 1) {
                    $caption .= "<br />{$LANG_SHOP['onhand']} = {$A['onhand']}.";
                }
            }
            $retval = Icon::getHTML($icon, 'tooltip', array('title' => $caption));
            break;*/

        case 'featured':
            $retval = FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togfeatured{$A['id']}",
                'checked' => $fieldvalue == 1,
                'value' => 1,
                'onclick' => "SHOP_toggle(this,'{$A['id']}','featured','product');",
            ) );
            break;

        case 'name':
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/report.php?run=itempurchase&item_id=' . $A['id'],
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_SHOP['item_history'],
                )
             );
            break;

        case 'prod_type':
            if (isset($LANG_SHOP['prod_types'][$A['prod_type']])) {
                $retval = $LANG_SHOP['prod_types'][$A['prod_type']];
            } else {
                $retval = '';
            }
            break;

        case 'cat_name':
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/index.php?cat_id=' . $A['cat_id']
            );
            break;

        case 'short_description':
            $id = $_SHOP_CONF['use_sku'] ? $A['name'] : $A['id'];
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_URL . '/detail.php?id=' . $id,
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_SHOP['see_details'],
                )
            );
            break;

        case 'price':
            $retval = Currency::getInstance()->FormatValue($fieldvalue);
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Verify that the product ID matches the specified value.
     * Used to ensure that the correct product was retrieved.
     *
     * @param   integer|string  $id     Expected product ID or name
     * @return  boolean     True if product matches.
     */
    public function verifyID($id)
    {
        global $_SHOP_CONF;
        $parts = explode('|', $id);
        if ($_SHOP_CONF['use_sku']) {
            return $this->name == $parts[0];
        } else {
            return $this->id == $parts[0];
        }
    }


    /**
     * Validate that the product can be ordered.
     * Checks quantity and qty tracking only. If there are attributes
     * then ProductVariant::Validate() should be used instead.
     *
     * @param   array   $opts   Array of options for compatibility
     * @return  array       Array of validation result information
     */
    public function Validate(ProductVariantInfo &$PVI, $opts = array())
    {
        global $LANG_SHOP;

        if (!isset($opts['quantity'])) {
            $opts['quantity'] = 1;
        }

        if (isset($opts['checkbox'])) {
            $incr_price = ProductCheckbox::getPriceImpact($this->id, $opts['checkbox']);
        } else {
            $incr_price = 0;
        }

        $base_price = $this->getBasePrice($incr_price);
        $sale_price = $this->getSalePrice($base_price);
        $PVI['status'] = 0;
        $PVI['msg'] = $this->track_onhand ? $this->getOnhand() . ' ' . $LANG_SHOP['available'] : '';
        $PVI['allowed'] = true;
        $PVI['is_oos'] = false;
        $PVI['orig_price'] = Currency::getInstance()->RoundVal($base_price);
        $PVI['sale_price'] = Currency::getInstance()->RoundVal($sale_price);
        $PVI['onhand'] = $this->getOnhand();
        $PVI['weight'] = $this->getWeight();
        $PVI['sku'] = $this->getName();
        $PVI['leadtime'] = $this->getOnhand() == 0 ? $this->getLeadTimeMessage() : '';
        $PVI['images'] = array_keys($this->Images);
        if ($this->track_onhand) {
            if ($this->getOnhand() < $opts['quantity']) {
                $PVI['is_oos'] = true;
                if ($this->getOversell() == self::OVERSELL_HIDE) {
                    $PVI['status'] = 2;
                    $PVI['msg'] = 'Not Available';
                    $PVI['allowed'] = false;
                } else {
                    $PVI['status'] = 1;
                    $PVI['msg'] = 'Backordered';
                }
            }
        }
    }


    /**
     * Validate the form fields and return an array of errors.
     *
     * return   array   Array of error messages, empty if all is valid
     */
    private function _Validate()
    {
        global $_TABLES, $LANG_SHOP;

        $errors = array();
        $db = Database::getInstance();
        try {
            $row = $db->conn->executeQuery(
                "SELECT count(*) AS cnt FROM {$_TABLES['shop.products']}
                WHERE name = ? AND id <> ?",
                array($this->name, $this->id),
                array(Database::STRING, Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row) && $row['cnt'] > 0) {
            $errors[] = $LANG_SHOP['err_dup_sku'];
        }
        return $errors;
    }


    /**
     * Get the ID of the first product in the database.
     * Used to set the first item in selection lists.
     * Relies on the primary key on the `id` field.
     *
     * @return  integer     Product ID
     */
    public static function getFirst() : ?int
    {
        global $_TABLES;

        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT id FROM {$_TABLES['shop.products']} ORDER BY id ASC LIMIT 1"
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            return $row['id'];
        } else {
            return NULL;
        }
    }


    /**
     * Set the selected options for a cart item.
     * Used to display the detail page with options selected from the
     * product link in the cart or order.
     *
     * @param   array   $opts   Array of attribute IDs (id1, id2, id3, ...)
     */
    public function setSelectedOptions($opts)
    {
        if (!is_array($opts)) {
            $opts = explode(',', $opts);
        }
        $this->sel_opts = $opts;
    }


    /**
     * Set the variant for this product.
     * Uses the default variant if available and a variant ID is not given.
     *
     * @param   integer|object  $variant    Variant ID or object
     * @return  object  $this
     */
    public function setVariant($variant = 0)
    {
        if (
            $variant === 0 &&
            $this->def_pv_id > 0
        ) {
            $this->Variant = ProductVariant::getInstance($this->def_pv_id);
        } elseif (is_integer($variant) && $variant > 0) {
            $this->Variant = ProductVariant::getInstance($variant);
        } elseif (is_object($variant)) {
            $this->Variant = $variant;
        }
        return $this;
    }


    /**
     * Set the query string to be highlighted in the Detail view.
     *
     * @param   string  $query  Query string
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }


    /**
     * Set the OrderItem record ID to get options from a specific order.
     *
     * @param   integer $oi_id  OrderItem record ID
     */
    public function setOrderItem($oi_id)
    {
        $this->oi_id = (int)$oi_id;
    }


    /**
     * Get the number of shipping units for this product.
     *
     * @return  float   Shipping units
     */
    public function getShippingUnits()
    {
        return $this->shipping_units;
    }


    /**
     * Get the total shipping units related to this product, including variant.
     *
     * @param   integer $pv_id  ProductVariant ID
     * @return  float       Total shipping units for the product variant
     */
    public function getTotalShippingUnits(int $pv_id=0) : float
    {
        $units = $this->getShippingUnits();
        if ($pv_id > 0) {
            $units += ProductVariant::getInstance($pv_id)->getShippingUnits();
        }
        return $units;
    }


    /**
     * Get the item ID for the product.
     *
     * @return  mixed       Item ID
     */
    public function getItemID()
    {
        return $this->item_id;
    }


    /**
     * Get the short description/product name.
     *
     * @return  string      Short description
     */
    public function getShortDscp()
    {
        return $this->short_description;
    }


    /**
     * Check if the quantity onhand is being tracked.
     *
     * @return  boolean     1 if tracking, 0 if not
     */
    public function trackOnhand()
    {
        return $this->track_onhand ? 1 : 0;
    }


    /**
     * Get the text to show in the purchase button.
     *
     * @return  string      Button text
     */
    public function getBtnText()
    {
        return $this->btn_text;
    }


    /**
     * Update the category cross-reference table.
     * TODO: update in-memory categories property.
     *
     * @param   string|array    $cat_ids    Category IDs
     * @return  object  $this
     */
    private function updateCategories($cat_ids) : self
    {
        global $_TABLES;

        if (is_string($cat_ids)) {
            $cat_ids = explode('|', $cat_ids);
        } elseif (!is_array($cat_ids)) {
            return $this;
        }

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['shop.prodXcat'],
                array('product_id' => $this->id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }

        foreach ($cat_ids as $cat_id) {
            $cat_id = (int)$cat_id;
            if ($cat_id > 0) {
                $vals[] = "({$this->id}, $cat_id)";
            }
        }
        $sql = "INSERT IGNORE INTO {$_TABLES['shop.prodXcat']}
            (product_id, cat_id) VALUES " . implode(',', $vals);
        try {
            $db->conn->executeStatement($sql);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        return $this;
    }


    /**
     * Get the array of related category objects.
     *
     * 2return  array       Array of category objects
     */
    public function getCategories()
    {
        if ($this->Categories === NULL) {
            $this->Categories = Category::getByProductId($this->id);
        }
        return $this->Categories;
    }


    /**
     * Load the related categories from the database into the object variable.
     *
     * @return  object  $this
     */
    private function loadCategories()
    {
        $this->Categories = Category::getByProductId($this->id);
        return $this;
    }


    /**
     * Get the first category for the product.
     * Used where only one category's information can be used.
     * Currently indeterminate.
     *
     * @return  object      First category object
     */
    public function getFirstCategory()
    {
        // Need to call getCategories() to be certain the categories
        // have been loaded.
        $Cats = $this->getCategories();
        return reset($Cats);
    }


    /**
     * Create the breadcrumb links at the top of the detail page.
     * TODO: know which category list page we came from and use that category.
     *
     * @return  string      HTML for breadcrumbs
     */
    public function Breadcrumbs()
    {
        return $this->getFirstCategory()->Breadcrumbs();
    }


    /**
     * Get the track_onhand property value.
     *
     * @return  boolean     TrackOnhand setting
     */
    public function getTrackOnhand()
    {
        return $this->track_onhand ? 1 : 0;
    }


    /**
     * Get the value of the oversell property.
     *
     * @return  integer     Oversell setting
     */
    public function getOversell()
    {
        return $this->oversell;
    }


    /**
     * Get the Stock object for this product.
     * If there are variants then the current variant Stock object is used,
     * otherwise the object attached to the Product is used.
     *
     * @return  object      Stock object
     */
    public function getStock()
    {
        if ($this->trackOnhand()) {
            if ($this->hasVariant()) {
                return $this->Variant->getStock();
            } elseif (is_object($this->Stock)) {
                return $this->Stock;
            }
        }
        // fallback if neither condition is satisfied
        return new Stock;
    }


    /**
     * Get the quantity on hand.
     * If there are variants, get the variant onhand value, otherwise use the
     * product's value.
     *
     * @return  float   Quantity on hand
     */
    public function getOnhand()
    {
        return $this->getStock()->getOnhand();
    }


    /**
     * Set the supplier ID for this product.
     *
     * @param   integer $id     Supplier record ID
     * @return  object  $this
     */
    private function setBrandID($id)
    {
        $this->brand_id = (int)$id;
        return $this;
    }


    /**
     * Get the supplier ID for this product.
     *
     * @return  integer     Supplier record ID
     */
    private function getBrandID()
    {
        return (int)$this->brand_id;
    }


    /**
     * Set the supplier ID for this product.
     *
     * @param   integer $id     Supplier record ID
     * @return  object  $this
     */
    private function setSupplierID($id)
    {
        $this->supplier_id = (int)$id;
        return $this;
    }


    /**
     * Get the supplier ID for this product.
     *
     * @return  integer     Supplier record ID
     */
    private function getSupplierID()
    {
        return (int)$this->supplier_id;
    }


    /**
     * Get the brand name for the product,
     *
     * @return  string  Brand name
     */
    public function getBrandName()
    {
        return Supplier::getInstance($this->getBrandID())->getDisplayName();
    }


    /**
     * Set the lead time string to override the supplier's lead time.
     *
     * @param   string  $str    Lead time description
     * @return  object  $this
     */
    public function setLeadTime($str)
    {
        $this->lead_time = $str;
        return $this;
    }


    /**
     * Get the lead time text for this product.
     *
     * @return  string  Lead time description
     */
    public function getLeadTime()
    {
        return $this->lead_time;
    }


    /**
     * Get the lead time string for the product, or from the supplier if not set.
     *
     * @return  string  Lead time description.
     */
    public function LeadTime()
    {
        if (empty($this->lead_time)) {
            return Supplier::getInstance($this->getSupplierID())->getLeadTime();
        } else {
            return $this->lead_time;
        }
    }


    /**
     * Set the ID of the default variant for this product.
     *
     * @param   integer $id     ProductVariant record ID
     * @return  object  $this
     */
    private function setDefVariantID($id)
    {
        $this->def_pv_id = (int)$id;
        return $this;
    }


    /**
     * Get the default ProductVariant ID for this product.
     *
     * @return  integer     ProductVariant record ID
     */
    public function getDefVariantID()
    {
        return (int)$this->def_pv_id;
    }


    /**
     * Set the query string to be passed to detail pages, etc.
     *
     * @param   string  $query_str  Query string
     * @return  object  $this
     */
    public function withQuery($query_str='')
    {
        $this->query = $query_str;
        return $this;
    }


    /**
     * Set the order item ID, to show the correct variant.
     *
     * @param   integer $oi_id      OrderItem record ID
     * @return  object  $this
     */
    public function withOrderItem($oi_id=0)
    {
        $this->oi_id = (int)$oi_id;
        return $this;
    }


    /**
     * Enable or disable purchasing this product.
     * Normal used to display the product, but not allow purchasing, since
     * the default for canPurchase is true.
     *
     * @param   boolean $flag   True to allow purchases, False to disallow
     * @return  object  $this
     */
    public function enablePurchase($flag=true)
    {
        $this->canPurchase = $flag ? true : false;
        return $this;
    }


    /**
     * Check if the product can be purchased.
     * Used to show add-cart and other buttons to the catalog and detail views.
     *
     * @return  boolean     True if purchase allowed, False if not
     */
    public function canPurchase()
    {
        return $this->canPurchase;
    }


    /**
     * Get the category selection lists.
     * Returns 2 arrays, available category options and selected ones.
     * If `$prod_id` is empty then no selected categories are returned. This
     * is used in the bulk update form.
     *
     * @param   integer $prod_id    Product ID, zero if no selected cats needed.
     * @return  array   Array of (available options, selected options)
     */
    private static function getCatSelections($prod_id)
    {
        $allcats = Category::getAll();
        if ($prod_id > 0) {
            $selcats = Category::getByProductID($prod_id);
        } else {
            $selcats = array();
        }
        $allcats_sel = '';
        $selcats_sel = '';
        foreach ($allcats as $cat_id=>$Cat) {
            if (!array_key_exists($cat_id, $selcats)) {
                $allcats_sel .= '<option value="' . $cat_id . '">' . $Cat->getName() . '</option>' . LB;
            }
        }
        foreach ($selcats as $cat_id=>$Cat) {
            $selcats_sel .= '<option value="' . $cat_id . '">' . $Cat->getName() . '</option>' . LB;
        }
        return array($allcats_sel, $selcats_sel);
    }


    /**
     * Get all the Variant images for display.
     * If there is no variant then just return all product images.
     *
     * @return  array   Array of image info
     */
    public function getVariantImages()
    {
        $retval = $this->getImages();
        if ($this->hasVariant()) {
            $ids = $this->Variant->getImageIDs();
            if (!empty($ids)) {
                $retval = array();
                foreach ($ids as $id) {
                    $retval[$id] = $this->Images[$id];
                }
            }
        }
        return $retval;
    }


    /**
     * Get the expiration days for this product.
     *
     * @return  integer     Number of days to expire downloads.
     */
    public function getExpiration()
    {
        return (int)$this->expiration;
    }


    /**
     * Check if this product instance has a valid product variant set.
     * ProductVariant::getInstance() may return an empty object so checking
     * if $this->Variant != NULL isn't sufficient.
     *
     * @reeturn boolean     True if a Variant is set, False if not
     */
    public function hasVariant()
    {
        return ($this->Variant && $this->Variant->getID() > 0);
    }


    /**
     * Check if this is a new product record, possibly from a failed search.
     *
     * @return  integer     1 if new, 0 if existing (from database)
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Check if a discount code can be applied to this product.
     * Normal catalog items can have a discount code applied.
     *
     * @return  boolean     True if a code can apply, False if not
     */
    public function canApplyDiscountCode()
    {
        return true;
    }


    /**
     * Check if an affiliate bonus is available on this product.
     *
     * @return  boolean     True if bonus-elibible, False if not
     */
    public function affApplyBonus()
    {
        return $this->aff_apply_bonus ? 1 : 0;
    }


    /**
     * Get the affiliate bonus percentage for this product.
     *
     * @return  fload       Percentage in whole numbers, 4.5 = .045
     */
    public function getAffiliatePercent()
    {
        if ($this->aff_apply_bonus) {
            return (float)$this->aff_percent;
        } else {
            return 0;
        }
    }


    /**
     * Reserve a quantity of this item when it's added to a cart.
     *
     * @param   float   $qty    Quantity to reserve
     */
    public function reserveStock($qty)
    {
        $this->getVariants();
        $PV = $this->getVariant();
        if ($PV === NULL) {
            $pv_id = 0;
        } else {
            $pv_id = $PV->getID();
        }
        Stock::reserve($this->id, $pv_id, $qty);
    }

}
