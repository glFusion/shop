<?php
/**
 * Class to handle shipping costs based on quantity, total weight and class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Models\Dates;
use Shop\Models\ShippingQuote;


/**
 * Class for product and category sales.
 * @package shop
 */
class Shipper
{
    use \Shop\Traits\DBO;        // Import database operations

    const TAX_DESTINATION = 1;
    const TAX_ORIGIN = 0;

    /** Table key for DBO functions
     * @var string */
    private static $TABLE = 'shop.shipping';

    /** Minimim possible effective date/time.
     * @const string */
    const MIN_DATETIME = '1970-01-01 00:00:00';

    /** Maximum possible effective date/time.
     * @const string */
    const MAX_DATETIME = '2037-12-31 23:59:59';

    /** Minimum units. Used since zero indicates free.
     * @const float */
    const MIN_UNITS = .0001;

    /** Base tag used for caching.
     * @var string */
    static $base_tag = 'shipping';

    /** Shipper record ID.
     * @var integer */
    protected $id = 0;

    /** glFusion group ID which can use this shipper.
     * @var integer */
    private $grp_access = 2;

    /** Name of the shipper, e.g. "United Parcel Service".
     * @var string */
    protected $name = '';

    /** Short code, e.g. "ups".
     * @var string */
    protected $module_code = '';

    /** Minimum shipping units that can be sent by this shipper.
     * @var float */
    private $min_units = .0001;

    /** Maximum shipping units that can be sent by this shipper.
     * @var float */
    private $max_units = -1;

    /** Flag to indicate whether this shipper is active.
     * @var boolean */
    private $enabled = true;

    /** Flag to indicate whether fixed shipping cost is included or ignored.
     * @var boolean */
    private $use_fixed = 1;

    /** Std class to accumulate order shipping prices for the shipper.
     * This is manipulated as a public variable within this class.
     * @var object */
    public $ordershipping = NULL;

    /** Earliest date/time that this shipper can be used.
     * @var object */
    private $valid_from = NULL;

    /** Latest date/time that this shipper can be used.
     * @var object */
    private $valid_to = NULL;

    /** Configuration items, if used.
     * @var array */
    protected $_config = NULL;

    /** Configuration item names, to create the config form.
     *@var array */
    protected $cfgFields = array();

    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    private $isNew = true;

    /** Individual rate element.
     * @var array */
    private $rates = array();

    /** Flag to indicate whether the shipper class implements a quote API.
     * @var boolean */
    protected $implementsQuoteAPI = false;

    /** Flag to indicate whether the shipper class implements a tracking API.
     * @var boolean */
    protected $implementsTrackingAPI = false;

    /** Base name of shipper class file. Default = `generic`.
     * @var string */
    protected $key = 'generic';

    /** Flag to indicate that a shipping address is required.
     * For "will-call" or certain other methods an address may not be needed.
     * @var boolean */
    protected $req_shipto = true;

    /** Order value threshold for free shipping.
     * @var float */
    protected $free_threshold = 0; // 0 disables free shipping

    /** Shipping method used for free shipping.
     * @var string */
    protected $free_method = '';

    /** Flag to indicate where sales tax is calculated.
     * 1 = origin (shop address), 0 = destination (customer address)
     * @var integer */
    protected $tax_loc = 0;

    /** Supported services.
     * @var array */
    protected $supported_services = array();

    /** Package type codes, dependent on shipper.
     * @var array */
    protected $pkg_codes = array();

    /** Delivery service codes, dependent on shipper.
     * @var array */
    protected $svc_codes = array(
        '_fixed' => 'Fixed Rate',
    );

    /** Selected package type code.
     * @var string */
    protected $pkg_code = '';

    /** Selected delivery service code.
     * @var string */
    protected $svc_code = '';


    /**
     * Constructor. Sets variables from the provided array.
     *
     * @param  array   DB record
     */
    public function __construct($A=array())
    {
        $this->properties = array();
        $this->isNew = true;

        if (is_array($A) && !empty($A)) {
            // DB record passed in, e.g. from _getSales()
            $this->setVars($A);
            $this->isNew = false;
        } elseif (is_numeric($A) && $A > 0) {
            // single ID passed in, e.g. from admin form
            if ($this->Read($A)) $this->isNew = false;
        } else {
            // New entry, set defaults
            $this->setValidFrom(NULL);
            $this->setValidTo(NULL);
            $this->min_units = self::MIN_UNITS;
            $this->max_units = 1000000;
            $this->rates = array(
                (object)array(
                    'dscp'  => 'Rate 1',
                    'units' => 10,
                    'rate'  => 5,
                ),
            );
        }
        // Initialize the object to hold shipping data for an order
        $this->ordershipping = new \stdClass;
        $this->ordershipping->packages = 0;
        $this->ordershipping->total_rate = 1000000;
    }


    /**
     * Read a single record based on the record ID.
     *
     * @param   integer $id     DB record ID
     * @return  boolean     True on success, False on failure
     */
    public function Read($id)
    {
        global $_TABLES;

        $id = (int)$id;
        //$cache_key = self::$base_tag . ' _ ' . $id;
        //$A = Cache::get($cache_key);
        //if ($A === NULL) {
            $sql = "SELECT *
                    FROM {$_TABLES['shop.shipping']}
                    WHERE id = $id";
            //echo $sql;die;
            $res = DB_query($sql);
            if ($res) {
                $A = DB_fetchArray($res, false);
          //      Cache::set($cache_key, $A, self::$base_tag);
            }
        //}
        if (!empty($A)) {
            $this->setVars($A);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Set the variables from a DB record into object properties.
     *
     * @param   array   $A      Array of properties
     * @param   boolean $fromDB True if reading from DB, False if from a form
     */
    public function setVars($A, $fromDB=true)
    {
        global $LANG_SHOP;

        $this->setID(SHOP_getVar($A, 'id', 'integer'))
            ->setModuleCode(SHOP_getVar($A, 'module_code'))
            ->setName(SHOP_getVar($A, 'name'))
            ->setMinUnits(SHOP_getVar($A, 'min_units', 'integer'))
            ->setMaxUnits(SHOP_getVar($A, 'max_units', 'integer'))
            ->setEnabled(SHOP_getVar($A, 'enabled', 'integer'))
            ->setReqShipto(SHOP_getVar($A, 'req_shipto', 'integer'))
            ->setTaxLocation(SHOP_getVar($A, 'tax_loc', 'integer'))
            ->setUseFixed(SHOP_getVar($A, 'use_fixed', 'integer', 0))
            ->setGrpAccess(SHOP_getVar($A, 'grp_access', 'integer', 2));
        if (!$fromDB) {
            $this->free_threshold = isset($A['ena_free']) ? (float)$A['free_threshold'] : 0;
            $rates = array();
            foreach ($A['rateRate'] as $id=>$txt) {
                if (empty($A['rateDscp'][$id])) {
                    $A['rateDscp'][$id] = $LANG_SHOP['shipping_type'];;
                }
                if (empty($A['rateUnits'][$id])) {
                    $A['rateUnits'][$id] = $this->max_units;
                }
                $rates[] = array(
                    'dscp' => $A['rateDscp'][$id],
                    'units' => (float)$A['rateUnits'][$id],
                    'rate' => (float)$A['rateRate'][$id],
                );
            }
            $this->rates = $rates;
            // convert valid dates to full date/time strings
            if (empty($A['valid_from'])) {
                $A['valid_from'] = self::MIN_DATETIME;
            } else {
                $A['valid_from'] = trim($A['valid_from']) . '00:00:00';
            }
            if (empty($A['valid_to'])) {
                $A['valid_to'] = self::MAX_DATETIME;
            } else {
                $A['valid_to'] = trim($A['valid_to']) . ' 23:59:59';
            }
        } else {
            $rates = array();
            if (isset($A['rates'])) {
                $rates = json_decode($A['rates']);
                if ($rates === NULL) $rates = array();
            }
            $this->rates = $rates;
            $this->free_threshold = (float)$A['free_threshold'];
        }
        $this->setValidFrom(SHOP_getVar($A, 'valid_from', 'string', Dates::MIN_DATE));
        $this->setValidTo(SHOP_getVar($A, 'valid_to', 'string', Dates::MAX_DATE));
    }


    /**
     * Check whether this is a new record.
     *
     * @return  boolean     True if new, False if already saved
     */
    public function isNew()
    {
        return (bool)$this->isNew;
    }


    /**
     * Set the record ID.
     *
     * @param   integer $id     Record ID
     * @return  object  $this
     */
    private function setID($id)
    {
        $this->id = (int)$id;
        return $this;
    }


    /**
     * Get the record ID.
     *
     * @return  integer     Shipper DB record ID
     */
    public function getID()
    {
        return (int)$this->id;
    }


    /**
     * Set the module code value.
     *
     * @param   string  $code   Module code
     * @return  object  $this
     */
    private function setModuleCode($code)
    {
        $this->module_code = $code;
        return $this;
    }


    /**
     * Set the shipper name.
     *
     * @param   string  $name   Shipper name
     * @return  object  $this
     */
    private function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * Get the shipper's name.
     *
     * @return  string      Shipper name
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Set the minimum shipping units allowed.
     *
     * @param   float   $units  Shipping units
     * @return  object  $this
     */
    private function setMinUnits($units)
    {
        $this->min_units = (float)$units;
        return $this;
    }

    /**
     * Set the maximum shipping units allowed.
     *
     * @param   float   $units  Shipping units
     * @return  object  $this
     */
    private function setMaxUnits($units)
    {
        $this->max_units = (float)$units;
        return $this;
    }


    /**
     * Set the `enabled` flag value.
     *
     * @param   boolean $enabled    True or false
     * @return  object  $this
     */
    private function setEnabled($enabled)
    {
        $this->enabled = $enabled ? 1 : 0;
        return $this;
    }


    /**
     * Set the `require shipto address` flag value.
     *
     * @param   boolean $enabled    True or false
     * @return  object  $this
     */
    private function setReqShipto($flag)
    {
        $this->req_shipto = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Get the value of the "requires shipto" flag.
     *
     * @return  integer     1 if required, 0 if not
     */
    public function requiresShipto()
    {
        return $this->req_shipto ? 1 : 0;
    }


    private function setTaxLocation($flag)
    {
        $this->tax_loc = $flag ? 1 : 0;
        return $this;
    }


    public function getTaxLocation()
    {
        return (int)$this->tax_loc;
    }


    /**
     * Set the flag to include product fixed shipping charge.
     *
     * @param   boolean $enabled    True or false
     * @return  object  $this
     */
    private function setUseFixed($enabled)
    {
        $this->use_fixed = $enabled ? 1 : 0;
        return $this;
    }


    /**
     * Set the glFusion group which can use this shipper.
     *
     * @param   integer $gid    Group ID
     * @return  object  $this
     */
    private function setGrpAccess($gid)
    {
        $this->grp_access = (int)$gid;
        return $this;
    }


    /**
     * Set the valid_from property.
     *
     * @param   string  $value  DateTime string
     * @return  object  $this
     */
    private function setValidFrom($value)
    {
        global $_CONF;

        if (empty($value)) {
            $value = self::MIN_DATETIME;
        }
        $this->valid_from = new \Date($value, $_CONF['timezone']);
        return $this;
    }


    /**
     * Set the valid_to property.
     *
     * @param   string  $value  DateTime string
     * @return  object  $this
     */
    private function setValidTo($value)
    {
        global $_CONF;

        if (empty($value)) {
            $value = self::MAX_DATETIME;
        }
        $this->valid_to = new \Date($value, $_CONF['timezone']);
        return $this;
    }


    /**
     * Get the order shipping table for this shipper.
     *
     * @return  object      Shipping table object
     */
    public function getOrderShipping()
    {
        return $this->ordershipping;
    }


    /**
     * Get the module code for this shipper.
     *
     * @return  string      Module code
     */
    public function getCode()
    {
        return $this->module_code;
    }


    /**
     * Set the free shipping threshold, zero to disable.
     *
     * @param   float   $value  Value to enable free shipping.
     * @return  object  $this
     */
    public function setFreeThreshold($value)
    {
        $this->free_threshold = (float)$value;
        return $this;
    }


    /**
     * Get a single shipper record.
     * Returns an empty shipper object if the requested ID is not found so
     * that object operations won't fail.
     *
     * @uses    self::getAll()
     * @param   integer $shipper_id     ID of shipper to retrieve
     * @return  object      Shipper object, new object if not found.
     */
    public static function getInstance($shipper_id)
    {
        static $shippers = NULL;
        if ($shippers === NULL) {
            $shippers = self::getAll(false);
        }
        if (array_key_exists($shipper_id, $shippers)) {
            return $shippers[$shipper_id];
        } else {
            return new self;
        }
    }


    /**
     * Get a shipper object by carrier code rather than database ID.
     *
     * @param   string  $shipper_code   Carrier class Code, e.g. 'ups'
     * @return  object|null     Shipper object, NULL if not found
     */
    public static function getByCode($shipper_code)
    {
        static $shippers = array();
        if (!array_key_exists($shipper_code, $shippers)) {
            $cls = '\\Shop\\Shippers\\' . $shipper_code;
            if (class_exists($cls)) {
                $shippers[$shipper_code] = new $cls;
            } else {
                $shippers[$shipper_code] = NULL;
            }
        }
        return $shippers[$shipper_code];
    }


    /**
     * Get all shipping options.
     *
     * @param   boolean $valid  True to get only enabled shippers
     * @return  array   Array of all DB records
     */
    public static function getAll($valid=true, $units = -1)
    {
        global $_TABLES, $_GROUPS;

        $cache_key = 'shippers_all_' . (int)$valid . (float)$units;
        $now = time();
        $shippers = Cache::get($cache_key);
        $shippers = NULL;
        if ($shippers === NULL) {
            $shippers = array();
            $sql = "SELECT * FROM {$_TABLES['shop.shipping']}";
            if ($valid) {
                $sql .= " WHERE enabled = 1
                    AND valid_from < '$now'
                    AND valid_to > '$now'";
            }
            if ($units > -1) {
                $units = (float)$units;
                $sql .= " AND min_units <= $units AND max_units >= $units";
            }
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $shippers[$A['id']] = $A;
            }
            Cache::set($cache_key, $shippers, self::$base_tag);
        }
        $retval = array();
        $modules = self::getCarrierNames();
        foreach ($shippers as $shipper) {
            if (in_array($shipper['grp_access'], $_GROUPS)) {
                if (array_key_exists($shipper['module_code'], $modules)) {
                    $cls = 'Shop\\Shippers\\' . $shipper['module_code'];
                    if (class_exists($cls)) {
                        // Got a known shipping module
                        $retval[$shipper['id']] = new $cls($shipper);
                    } else {
                        // Module code defined but module not available
                        $retval[$shipper['id']] = new self($shipper);
                    }
                } else {
                    // Custom or unknown shipper
                    $retval[$shipper['id']] = new self($shipper);
                }
            }
        }
        return $retval;
    }


    /**
     * Get all the shippers that can handle a number of units.
     *
     * @param   float   $units      Number of units being shipped
     * @param   boolean $ignore_limits  True to ignore unit limit for shippers
     * @return  array               Array of shipper objects, including rates
     */
    public static function getShippers($units=0, $ignore_limits=false)
    {
        $rates = array();
        if ($units == 0) return $rates;     // no shipping, return empty

        $shippers = self::getAll();
        $shipper = new \stdClass();
        foreach ($shippers as $s_id=>&$shipper) {
            if (
                !$ignore_limits &&
                (
                    $units < $shipper->min_units ||
                    ($shipper->max_units > 0 && $units > $shipper->max_units)
                )
            ) {
                // Skip shippers that don't handle this number of units
                continue;
            } else {
                $shipper->ordershipping = new \stdClass;
                $shipper->ordershipping->packages = 0;  // not used here
                $shipper->ordershipping->total_rate = 1000000;
                foreach ($shipper->rates as $r_id=>$rate) {
                    // Calculate the shipping cost for this shipper
                    $ship_cost = $rate->rate * ceil($units / $rate->units);
                    // If the new cost is lower than the current best rate,
                    // then we found a new best rate.
                    if ($shipper->ordershipping->total_rate > $ship_cost) {
                        $shipper->ordershipping->total_rate = $ship_cost;
                    }
                }
                $rates[$s_id] = $shipper;
            }
        }
        return $rates;
    }


    /**
     * Get the single best shipper for a number of units.
     * If `$ignore_limits` is false then shippers that cannot handle the
     * number of units will be ignored. If true, then shippers will be included
     * even if they cannot ship the number of units.
     *
     * @param   integer $units      Number of units being shipped
     * @param   boolean $ignore_limits  True to ignore min and max unit limits
     * @return  object      Shipper object for the shipper with the lowest rate
     */
    public static function getBestRate($units, $ignore_limits=false)
    {
        $shippers = self::getShippers($units, $ignore_limits);
        $best = NULL;
        foreach ($shippers as $shipper) {
            if (
                $best === NULL ||
                $shipper->ordershipping->total_rate < $best->ordershipping->total_rate
            ) {
                $best = $shipper;
                $best->ordershipping->total_rate = $shipper->ordershipping->total_rate;
            }
        }
        if ($best === NULL) {
            // Create an empty object to provide zero shipping cost
            $best = new self();
        }
        return $best;
    }


    /**
     * Get all the shippers that can ship an order sorted by the total charge.
     * The shipper objects have an additional variable `ordershipping` added
     * which contains the total charge and the packages required.
     * If no qualified shippers are found, then only the total charge is
     * included and the package count is set to zero.
     *
     * @param   object  $Order  Order being shipped
     * @return  array       Array of shipper objects, with rates and packages
     */
    public static function getShippersForOrder($Order)
    {
        global $LANG_SHOP;

        /*$cache_key = 'shipping_order_' . $Order->getOrderID();
        $shippers = Cache::get($cache_key);
        if (is_array($shippers)) {
            return $shippers;
        }
         */

        // Get all the order items into a simple array where they can be
        // ordered by unit count and marked when packed.
        // This is then passed to calcBestFit() so it doesn't have to be
        // done multiple times.
        $total_units = 0;
        $fixed_shipping = 0;
        $items = array();
        foreach ($Order->getItems() as $id=>$Item) {
            $P = $Item->getProduct();
            $qty = $Item->getQuantity();
            $single_units = $P->getShippingUnits();
            $item_units = $single_units * $qty;
            $fixed_shipping += $P->getShipping($qty);
            $total_units += $item_units;
            for ($i = 0; $i < $qty; $i++) {
                $items[] = array(
                    'orderitem_id' => $id,
                    'item_name'     => $Item->getDscp(),
                    'single_units' => $single_units,
                    'packed'    => false,
                );
            }
        }
        // Sort items by shipping units, then reverse so larger items are
        // handled first
        usort($items, function($a, $b) {
            return $a['single_units'] <=> $b['single_units'];
        });
        $items = array_reverse($items);

        $shippers = self::getShippers($total_units);
        foreach ($shippers as $id=>&$shipper) {
            $shipper->calcBestFit($items, $total_units);
            if ($shipper->ordershipping->total_rate === NULL) {
                unset($shippers[$id]);
            }
            if ($shipper->use_fixed) {
                // Add the product fixed per-item shipping unless the shipper
                // doesn't use it.
                $shipper->ordershipping->total_rate += $fixed_shipping;
            }
        }

        // Check if at least one qualified shipper was obtained.
        // If not, then get the best shipping rate from all shippers, ignoring
        // the max_units restriction. This is so there will be some shipping
        // charge shown.
        $active_shippers = count($shippers);
        if ($active_shippers > 0) {
            usort($shippers, function($a, $b) {
                return $a->ordershipping->total_rate <=> $b->ordershipping->total_rate;
            });
        } else {
            $shipper = self::getBestRate($Order->totalShippingUnits(), true);
            $shipper->ordershipping->total_rate += $fixed_shipping;
            if (!$shipper->isNew) {
                $shippers = array($shipper);
            } else {
                // Last resort, create a dummy shipper using the total fixed
                // shipping charge.
                $shipper = new self(array(
                    0, 1, $LANG_SHOP['shipping'], array()
                ));
                $shipper->ordershipping = new \stdClass;
                $shipper->ordershipping->total_rate = $fixed_shipping;
                $shipper->ordershipping->packages = 0;
            }
            $shippers = array($shipper);
        }

        // Cache the shippers for a short time.
        // The cache is also cleared whenever a shipper or the order is updated.
        //Cache::set($cache_key, $shippers, array('orders', self::$base_tag), 30);
        return $shippers;
    }


    /**
     * Calculate the best fit for items/packages for this shipper.
     *
     * @param   array   $items  Array of items containing basic shipping info
     * @param   float   $total_units    Total number of shipping units
     * @return  float       Shipping amount
     */
    public function calcBestFit($items, $total_units)
    {
        $this->ordershipping = new \stdClass;
        $this->ordershipping->total_rate = NULL;
        $this->ordershipping->packages = array();

        // Get the package types into an array to track how much space is used
        // as items are packed.
        // This should already be in ascending order by the unit count.
        // Don't bother with larger packages once a package is found that will
        // accomodate the entire shipment.
        $types = array();
        foreach ($this->rates as $type) {
            $types[] = array(
                'dscp' => $type->dscp,
                'max_units' => $type->units,
                'units_left' => $type->units,
                'rate' => $type->rate,
            );
            if ($type->units >= $total_units) {
                // If a single package will handle the entire order, then
                // there's no need to iterate through all the items.
                $pkg_items = array();
                foreach ($items as $Item) {
                    $pkg_items[] = $Item['orderitem_id'];
                }
                $this->ordershipping->packages[] = array(
                    'type' => $type->dscp,
                    'items' => $pkg_items,
                    'units' => $total_units,
                    'units_left' => $type->units - $total_units,
                    'rate' => $type->rate,
                );
                $this->ordershipping->total_rate = $type->rate;
                return;
            }
        }

        // Figure out the packages that will be needed. Start with the largest item,
        $packages = array();
        $total_rate = 0;
        $units_left = $total_units;
        foreach ($items as &$item) {
            // First check to see if this item can be added to an existing package.
            foreach ($packages as &$pkg) {
                //echo "Checking {$pkg['units_left']} against item {$item['single_units']}\n";
                if ($pkg['units_left'] >= $item['single_units']) {
                    // can add more of this item to the package.
                    $pkg['items'][] = $item['item_name'];
                    $pkg['units'] += $item['single_units'];
                    $pkg['units_left'] -= $item['single_units'];
                    $item['packed'] = true;
                    break;
                }
            }
            unset($pkg);    // clear last value set in the loop

            // Couldn't fit in an existing package, create a new package.
            // Start with the largest package size, but then check to see if
            // the next-largest size is sufficient to handle the rest of the
            // shipment.
            if (!$item['packed']) {
                for ($i = count($types)-1; $i >= 0; $i--) {
                    $type = $types[$i];
                    if ($i > 0 && $types[$i-1]['max_units'] >= $units_left) {
                        // get a smaller package if it can handle the rest of the shipment.
                        //echo "skipping from {$type['dscp']} to {$nexttype['dscp']}\n";
                        continue;
                    }
                    // Check that the item will fit. If not, there's a problem.
                    if ($item['single_units'] <= $type['max_units']) {
                        $packages[] = array(
                            'type' => $type['dscp'],
                            'items' => array($item['item_name']),
                            'units' => $item['single_units'],
                            'units_left' => $type['max_units'] - $item['single_units'],
                            'rate' => $type['rate'],
                        );
                        $item['packed'] = true;
                        $total_rate += $type['rate'];
                        //echo "Created new package for " . $item['orderitem_id'] . "\n";
                        break;
                    }
                }
            }
            if ($item['packed'] !== true) {
                // This shipper cannot handle this item
                SHOP_log(__NAMESPACE__ . '\\' . __CLASS__ . "::Error packing " . print_r($item,true), SHOP_LOG_ERROR);
                // Flag the total rate as NULL to indicate that this shipper
                // cannot be used.
                $total_rate = NULL;
                break;
            } else {
                $units_left -= $item['single_units'];
            }
        }

        $this->ordershipping->total_rate = $total_rate;
        $this->ordershipping->packages = $packages;
        return;
    }


    /**
     * Save shipper information to the database.
     *
     * @param   array   $A      Optional array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save($A =NULL)
    {
        global $_TABLES, $_SHOP_CONF;

        if (is_array($A)) {
            $this->setVars($A, false);
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['shop.shipping']}";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.shipping']}";
            $sql3 = " WHERE id={$this->id}";
        }
        usort($this->rates, function($a, $b) {
            return $a['units'] <=> $b['units'];
        });
        $sql2 = " SET name = '" . DB_escapeString($this->name) . "',
            module_code= '" . DB_escapeString($this->module_code) . "',
            enabled = '{$this->enabled}',
            req_shipto = '{$this->requiresShipto()}',
            tax_loc = '{$this->getTaxLocation()}',
            min_units = '{$this->min_units}',
            max_units = '{$this->max_units}',
            valid_from = '{$this->valid_from->toUnix()}',
            valid_to = '{$this->valid_to->toUnix()}',
            use_fixed = '{$this->use_fixed}',
            free_threshold = '{$this->free_threshold}',
            grp_access = '{$this->grp_access}',
            rates = '" . DB_escapeString(json_encode($this->rates)) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            Cache::clear(self::$base_tag);
            Cache::clear('shippers');
            return true;
        } else {
            return false;
        }
    }


    /**
     * Delete a single shipper record from the database.
     *
     * @param   integer $id     Record ID
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($id)
    {
        global $_TABLES;

        if ($id <= 0) {
            return false;
        }

        if (!self::isUsed($id)) {
            DB_delete($_TABLES['shop.shipping'], 'id', $id);
            Cache::clear(self::$base_tag);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id Attributeal ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $_TABLES;

        $T = new Template;
        $T->set_file('form', 'shipping_form.thtml');
        $retval = '';
        $T->set_var(array(
            'id'            => $this->id,
            'name'          => $this->name,
            'action_url'    => SHOP_ADMIN_URL,
            'doc_url'       => SHOP_getDocURL('shipping_form',
                                            $_CONF['language']),
            'min_units'     => $this->min_units == self::MIN_UNITS ? 0 : $this->min_units,
            'max_units'     => $this->max_units,
            'ena_sel'       => $this->enabled ? 'checked="checked"' : '',
            'req_shipto_sel' => $this->req_shipto ? 'checked="checked"' : '',
            'fixed_sel'     => $this->use_fixed ? 'checked="checked"' : '',
            'valid_from'    => $this->valid_from->format('Y-m-d', true),
            'valid_to'      => $this->valid_to->format('Y-m-d', true),
            'grp_sel'       => COM_optionList($_TABLES['groups'], 'grp_id,grp_name', $this->grp_access),
            'tax_loc_' . $this->getTaxLocation() => 'selected="selected"',
            'ena_free_chk' => $this->free_threshold > 0 ? 'checked="checked"' : '',
            'free_threshold' => $this->free_threshold,
            'span_free_vis' => $this->free_threshold > 0 ? '' : 'none',
        ) );

        // Construct the dropdown selection of defined carrier modules
        $T->set_block('form', 'shipperCodes', 'sCodes');
        foreach (self::getCarrierNames() as $module_code=>$name) {
            $T->set_var(array(
                'module_code'  => $module_code,
                'module_name'  => $name,
                'selected' => $module_code == $this->module_code ? 'selected="selected"' : '',
            ) );
            $T->parse('sCodes', 'shipperCodes', true);
        }

        $T->set_block('form', 'rateTable', 'rt');
        foreach ($this->rates as $R) {
            $T->set_var(array(
                'rate_dscp'     => $R->dscp,
                'rate_units'    => $R->units,
                'rate_price'    => Currency::getInstance()->FormatValue($R->rate),
            ) );
            $T->parse('rt', 'rateTable', true);
        }

        if ($this->module_code != '') {
            $Carrier = self::getByCode($this->module_code);
            $T->set_block('form', 'PkgCodes', 'PC');
            foreach ($Carrier->getPackageCodes() as $code=>$dscp) {
                $T->set_var(array(
                    'pkg_code' => $code,
                    'pkg_dscp' => $dscp,
                ) );
                $T->parse('PC', 'PkgCodes', true);
            }
            $T->set_block('form', 'SvcCodes', 'SC');
            foreach ($Carrier->getServiceCodes() as $code=>$dscp) {
                $T->set_var(array(
                    'svc_code' => $code,
                    'svc_dscp' => $dscp,
                ) );
                $T->parse('SC', 'SvcCodes', true);
            }
            //$T->clear_var('SC');
            //$T->clear_var('PC');
        }
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
     * Sets the "enabled" field to the opposite of the given value.
     *
     * @param   integer $oldvalue   Original field value
     * @param   integer $id         ID number of element to modify
     * @return         New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        $newval = self::_toggle($oldvalue, 'enabled', $id);
        if ($newval != $oldvalue) {
            Cache::clear(self::$base_tag);
        }
        return $newval;
    }


    /**
     * Get the carrier name for a shipper.
     * This comes from the class under the Shippers namespace.
     * Safety function in case this function is not defined properly.
     *
     * @return  string  Carrier Name.
     */
    public static function getCarrierName()
    {
        $parts = explode('\\', get_called_class());
        $cls = $parts[count($parts)-1];
        return "Unknown ($cls)";
    }


    /**
     * Displays the admin list of shippers.
     *
     * @return  string  HTML string containing the contents of the ipnlog
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $LANG_ADMIN, $LANG_SHOP_HELP;

        $sql = "SELECT s.*, g.grp_name
            FROM {$_TABLES['shop.shipping']} s
            LEFT JOIN {$_TABLES['groups']} g
                ON g.grp_id = s.grp_access";

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
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
                'text'  => $LANG_SHOP['carrier'],
                'field' => 'module_code',
            ),
            array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'name',
            ),
            array(
                'text'  => $LANG_SHOP['grp_access'],
                'field' => 'grp_name',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'] . '&nbsp;' .
                    Icon::getHTML('question', 'tooltip', array('title'=>$LANG_SHOP_HELP['hlp_delete'])),
                'field' => 'delete',
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'name',
            'direction' => 'ASC',
        );

        $query_arr = array(
            'table' => 'shop.shipping',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            //'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?shipping=x',
        );

        $options = array('chkdelete' => true, 'chkfield' => 'id');
        $filter = '';
        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_ship_method'],
            SHOP_ADMIN_URL . '/index.php?editshipper=0',
            array('class' => 'uk-button uk-button-success')
        );
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_shiplist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Create an admin list of carrier modules available.
     *
     * @return  string      HTML for class list.
     */
    public static function carrierList()
    {
        global $LANG_SHOP, $_SHOP_CONF;

        $carriers = self::getCarrierNames();
        $data_arr = array();
        foreach ($carriers as $code=>$name) {
            $config = 'n/a';
            $Sh = self::getByCode($code);
            if ($Sh !== NULL) {
                if ($Sh->hasConfig()) {
                    $config = COM_createLink(
                        '<i class="uk-icon uk-icon-edit"></i>',
                        SHOP_ADMIN_URL . '/index.php?carrier_config=' . $code
                    );
                }
            }
            $data_arr[] = array(
                'code'  => $code,
                'name'  => $name,
                'config' => $config,
            );
        }
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'config',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => 'ID',
                'field' => 'code',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'name',
                'sort'  => false,
            ),
        );
        $text_arr = '';
        $defsort_arr = array(
            'field' => 'code',
            'direction' => 'ASC',
        );
        $retval = ADMIN_listArray(
            $_SHOP_CONF['pi_name'] . '_carrierlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $data_arr, $defsort_arr
        );
        return $retval;
    }


    /**
     * Get an individual field for the shipping profiles.
     *
     * @param  string  $fieldname  Name of field (from the array, not the db)
     * @param  mixed   $fieldvalue Value of the field
     * @param  array   $A          Array of all fields from the database
     * @param  array   $icon_arr   System icon array (not used)
     * @return string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        static $grp_names = array();
        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array('title'=>$LANG_ADMIN['edit'])),
                SHOP_ADMIN_URL . "/index.php?editshipper={$A['id']}"
            );
            break;

        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"enabled\",".
                "\"shipping\");' />" . LB;
            break;

        case 'delete':
            if (!self::isUsed($A['id'])) {
                $retval .= COM_createLink(
                    Icon::getHTML('delete'),
                    SHOP_ADMIN_URL. '/index.php?delshipping=' . $A['id'],
                    array(
                        'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                        'title' => $LANG_SHOP['del_item'],
                        'class' => 'tooltip',
                    )
                );
            }
            break;

/*        case 'grp_name':
            if (!isset($grp_names[$fieldvalue])) {
                $grp_names[$fieldvalue] = DB_getItem($_TABLES['groups'], 'grp_name', "grp_id='" . $fieldvalue ."'");
            }
            $retval = $grp_names[$fieldvalue];
            break;
 */
        case 'module_code':
            $retval = strtoupper($fieldvalue);
            break;

        case 'config':
            $retval = $fieldvalue;
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Get the names of shippers defined in class files.
     * Currently these are only used to get tracking info, but may have
     * additional features added.
     *
     * @return  array   Array of (ClassName => Shipper Name)
     */
    public static function getCarrierNames()
    {
        $carriers = array();
        $files = glob(__DIR__ . '/Shippers/*.class.php');
        if (is_array($files)) {
            foreach ($files as $fullpath) {
                $parts = pathinfo($fullpath);
                list($class,$x1) = explode('.', $parts['filename']);
                $classfile = __NAMESPACE__ . '\\Shippers\\' . $class;
                $carriers[$class] = $classfile::getCarrierName();
            }
            asort($carriers);
        }
        return $carriers;
    }


    /**
     * Get the HTML option list of shippers.
     *
     * @param   integer $selected   Selected item ID
     * @param   boolean $ena_only   True to include only enabled
     * @return  string      HTML for selection list
     */
    public static function optionList($selected = 0, $ena_only=false)
    {
        global $_TABLES;

        if ($ena_only) {
            $now = time();
            $where = "enabled = 1
                AND valid_from < '$now'
                AND valid_to > '$now'";
        } else {
            $where = '';
        }

        $lst = COM_optionList(
            $_TABLES['shop.shipping'],
            'id,name',
            $selected,
            1,
            $where
        );
        $lst = str_replace("\n", '', $lst);
        return $lst;
    }


    /**
     * Get the default tracking info URL for a shipper
     * Publicly accessed through getTrackingUrl().
     * Used if the shipper does not implement a tracking API.
     * This default returns an empty string.
     *
     * @param   string  $tracking_num   Tracking number
     * @return  string      URL to shipper's tracking site
     */
    protected function _getTrackingUrl($tracking_num)
    {
        return '';
    }


    /**
     * Get the tracking URL for a package.
     * Checks if the tracking API is available and returns that.
     * If not, returns the shipper's default URL.
     *
     * @uses    self::_getTrackingUrl()
     * @param   string  $tracking_num   Tracking Number
     * @param   boolean $internal       True to show in a popup
     * @return  string      URL to tracking information
     */
    public function getTrackingUrl($tracking_num, $internal=true)
    {
        $text = $tracking_num;
        if ($internal && $this->hasTrackingAPI()) {
            // Return the internal tracking page
            $retval = COM_createLink(
                $text,
                SHOP_URL . "/track.php?shipper={$this->key}&tracking={$tracking_num}",
                array(
                    'data-uk-lightbox' => '',
                    'data-lightbox-type' => 'iframe',
                )
            );
        } else {
            $url = $this->_gettrackingUrl($tracking_num);
            if (!empty($url)) {
                $retval = COM_createLink(
                    $text,
                    $url,
                    array(
                        'target' => '_blank',
                    )
                );
            } else {
                // No url found, just return the text for display
                $retval = $text;
            }
        }
        return $retval;
    }


    /**
     * Check if a specific shipper is associated with any orders or packages.
     * Used to determine whether a shipper record can be deleted.
     *
     * @param   integer $shipper_id     Shipper record ID
     * @return  boolean     True if the shipper is in use, False if not
     */
    public static function isUsed($shipper_id)
    {
        global $_TABLES;

        $shipper_id = (int)$shipper_id;
        if (DB_count($_TABLES['shop.orders'], 'shipper_id', $shipper_id) > 0) {
            return true;
        } elseif (DB_count($_TABLES['shop.shipment_packages'], 'shipper_id', $shipper_id) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Read the shipper's configuration from the database.
     *
     * @return  boolean     True if config was successfuly read
     */
    protected function readConfig()
    {
        global $_TABLES;

        $code = DB_escapeString($this->key);
        $data = DB_getItem(
            $_TABLES['shop.carrier_config'],
            'data',
            "code = '$code'"
        );
        if ($data) {        // check that a data item was retrieved
            $config = @unserialize($data);
            if ($config) {
                foreach ($config as $name=>$value) {
                    if (isset($this->cfgFields[$name])) {
                        // check carrier-specific fields for special handling
                        if ($this->cfgFields[$name] == 'password') {
                            $value = COM_decrypt($value);
                        }
                    }
                    $this->_config[$name] = $value;
                }
                return true;
            }
        }
        return false;       // didn't get any config read
    }


    /**
     * Get the value of a single configuration item.
     *
     * @param   string  $cfgItem    Name of field to get
     * @return  mixed       Value of field, empty string if not defined
     */
    protected function getConfig($cfgItem = '')
    {
        if ($this->_config === NULL) {
            $this->readConfig();
        }
        if ($cfgItem == '') {
            // Get all items at once
            return $this->_config;
            // Get a single item
        } elseif (
            is_array($this->_config) &&
            array_key_exists($cfgItem, $this->_config)
        ) {
            return $this->_config[$cfgItem];
        } else {
            // Item specified but not found, return empty string
            return '';
        }
    }


    /**
     * Check if the configuration is valid for this shipper.
     * Used to check if API functions can be used.
     *
     * @return  boolean     True if config is valid, False if not
     */
    protected function hasValidConfig()
    {
        $this->getConfig();
        foreach ($this->cfgFields as $name=>$type) {
            if (!isset($this->_config[$name])) {
                return false;       // config var doesn't exist
            }
            if (
                $type != 'checkbox' && $type != 'array' &&
                $this->_config[$name] == ''
            ) {
                return false;       // empty string value
            }
        }
        return true;
    }


    /**
     * Callback function to sort the rate quote array.
     * Returns the cost difference between two arrays.
     *
     * @param   array   $a      First array to check
     * @param   array   $b      Second array to check
     * @return  float       Difference between a and b
     */
    public static function sortQuotes($a, $b)
    {
        return $a['cost'] - $b['cost'];
    }


    /**
     * Check if this shipper implements a rate quote API.
     * Returns false if a quote API isn't implemented, or if the
     * configuration isn't valid.
     *
     * @return   boolean    True if the api is available, false if not
     */
    protected function hasQuoteAPI()
    {
        return ($this->implementsQuoteAPI && $this->hasValidConfig());
    }


    /**
     * Check if this shipper implements a package tracking API.
     * Returns false if a tracking API isn't implemented, or if the
     * configuration isn't valid.
     *
     * @return   boolean    True if the api is available, false if not
     */
    protected function hasTrackingAPI()
    {
        return ($this->implementsTrackingAPI && $this->hasValidConfig());
    }


    /**
     * Check if this shipper has configuration options.
     * Used to determine if a link to the configuration form should be shown.
     *
     * @return  boolean     True if this shipper can be configured
     */
    public function hasConfig()
    {
        return !empty($this->cfgFields);
    }


    /**
     * Save a shipper's configuration from a submitted form.
     * Encrypts password fields prior to saving.
     *
     * @param   array   $form   Form data, e.g. $_POST
     * @return  boolean     True on success, False on failure
     */
    public function saveConfig($form)
    {
        global $_TABLES;

        $code = DB_escapeString($this->key);
        // Seed data with common data for all shippers
        $cfg_data = array(
            'ena_quotes'    => isset($form['ena_quotes']) ? 1 : 0,
            'ena_tracking'  => isset($form['ena_tracking']) ? 1 : 0,
        );

        foreach ($this->cfgFields as $name=>$type) {
            switch ($type) {
            case 'checkbox':
                $value = isset($form[$name]) ? 1 : 0;
                break;
            case 'password':
                if (!isset($form[$name])) {
                    return false;       // required field missing
                } else {
                    $value = COM_encrypt($form[$name]);
                }
                break;
            default:
                if (!isset($form[$name])) {
                    return false;       // required field missing
                } else {
                    $value = $form[$name];
                }
                break;
            }
            $cfg_data[$name] = $value;
        }
        if (isset($form['services'])) {
            $cfg_data['services'] = $form['services'];
        }

        $data = DB_escapeString(serialize($cfg_data));
        $sql = "INSERT INTO {$_TABLES['shop.carrier_config']} SET
            code = '$code',
            data = '$data'
            ON DUPLICATE KEY UPDATE data = '$data'";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            SHOP_log("Shipper::saveConfig() error: $sql");
            return false;
        } else {
            return true;
        }
    }


    /**
     * Present the configuration form for a shipper
     *
     * @return  string      HTML for the configuration form.
     */
    public function Configure()
    {
        global $LANG_SHOP_HELP;

 //       $retval = '<div class="uk-alert">' .
//            $LANG_SHOP_HELP['hlp_carrier_modules']
        $T = new Template;
        $T->set_file('form', 'carrier_config.thtml');

        // Load the language for this gateway and get all the config fields
        $T->set_var(array(
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'carrier_name'  => $this->getCarrierName(),
            'carrier_code'  => $this->key,
            'implementsQuotes' => $this->implementsQuoteAPI,
            'implementsTracking' => $this->implementsTrackingAPI,
            'ena_quotes_chk' => $this->getConfig('ena_quotes') ? ' checked="checked"' : '',
            'ena_tracking_chk' => $this->getConfig('ena_tracking') ? ' checked="checked"' : '',
        ), false, false);
        $T->set_block('tpl', 'ItemRow', 'IRow');
        foreach ($this->cfgFields as $name=>$type) {
            switch ($type) {
            case 'checkbox':
                $chk = $this->getConfig($name) ? 'checked="checked"' : '';
                $fld = '<input type="checkbox" value="1" name="' . $name . '" ' . $chk . '/>';
                break;
            case 'text':
            case 'password':
                $fld = '<input type="text" size="80" name="' . $name . '" value="' . $this->getConfig($name) . '" />';
                break;
            default:
                continue 2;
            }
            $T->set_var(array(
                'param_name'    => $name,
                'field_name'    => $name,
                'field_value'   => $this->getConfig($name),
                'input_field'   => $fld,
            ) );
            $T->parse('IRow', 'ItemRow', true);
        }
        $services = $this->getAllServices();
        if (!empty($services)) {
            $T->set_var('has_services', true);
            $T->set_block('tpl', 'Services', 'Svcs');
            foreach ($services as $key=>$dscp) {
                $T->set_var(array(
                    'svc_chk' => $this->supportsService($key) ? 'checked="checked"' : '',
                    'svc_key' => $key,
                    'svc_dscp' => $dscp,
                ) );
                $T->parse('Svcs', 'Services', true);
            }
        }

        $T->set_block('tpl', 'SpecialConfig', 'SC');
        foreach ($this->getConfigForm() as $prompt=>$form) {
            $T->set_var(array(
                'prompt' => $prompt,
                'form' => $form,
            ) );
            $T->parse('SC', 'SpecialConfig', true);
        }
        $T->parse('output', 'form');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    public function getAllServices()
    {
        return $this->svc_codes;
    }

    public function getConfigForm()
    {
        return array();
    }


    /**
     * Get the package type codes for this shipper.
     *
     * @return  array   Array of key->description pairs
     */
    public function getPackageCodes()
    {
        return $this->pkg_codes;
    }


    /**
     * Get the package service codes for this shipper.
     *
     * @return  array   Array of key->description pairs
     */
    public function getServiceCodes()
    {
        return $this->svc_codes;
    }


    /**
     */
    public function getFreeMethod()
    {
        if ($this->free_threshold > 0) {
            return $this->free_method;
        } else {
            return NULL;
        }
    }


    /**
     * Get the minimum order value for free shipping.
     *
     * @return  float       Order value for this shipper to be free
     */
    public function getFreeThreshold()
    {
        return (float)$this->free_threshold;
    }


    /**
     * Get the shipping quote according to the fixed package rates.
     *
     * @param   array   $Packages   Array of Package objects
     * @return  object      Single ShippingQuote object
     */
    public function getPackageQuote($Packages)
    {
        if (empty($Packages)) {
            $error = true;
        }
        $cost = 0;
        foreach ($Packages as $Package) {
            $container = $Package->getContainer($this->key);
            if (isset($container['rate'])) {
                $cost += (float)$container['rate'];
            }
            $error = false;
        }
        $retval = (new ShippingQuote)
            ->setID($this->id)
            ->setShipperID($this->id)
            ->setCarrierCode($this->key)
            ->setCarrierTitle($this->name)
            ->setServiceCode('0')
            ->setServiceID($this->key . '.fixed')
            ->setServiceTitle($this->name)
            ->setCost($cost)
            ->setPackageCount(count($Packages));
        return $retval;
    }


    /**
     * Get a shipping quote only based on the number of units.
     *
     * @param   object  $Order  Order to be shipped
     * @return  array       Array of ShippingQuote objects
     */
    public function getUnitQuote($units)
    {
        $quote = (new ShippingQuote)
            ->setID($this->id)
            ->setCarrierCode($this->key)
            ->setCarrierTitle($this->name)
            ->setServiceTitle($this->name)
            ->setServiceCode('units')
            ->setServiceID($this->key . '.' . $this->id);
 
        $found = false;
        if ($units <= $this->max_units && $units >= $this->min_units) {
            foreach ($this->rates as $rate) {
                if ($rate->units > $units) {
                    $found = true;
                    $quote['cost'] = $rate->rate;
                    break;
                }
            }
        }
        if ($found) {
            return $quote;
        } else {
            return NULL;
        }
    }


    /**
     * Default function to return a shipping quote where not implemted.
     *
     * @param   object  $Order  Order to be shipped
     * @return  array       Array of ShippingQuote objects
     */
    public function getQuote(\Shop\Order $Order)
    {
        if (
            $this->free_threshold > 0 &&
            $Order->getItemTotal() > $this->free_threshold
        ) {
            $retval = array(
                (new ShippingQuote)
                    ->setID($this->id)
                    ->setCarrierCode($this->key)
                    ->setCarrierTitle($this->getCarrierName())
                    ->setServiceCode('free')
                    ->setServiceID('free')
                    ->setServiceTitle($this->getName() . ' Free Shipping')
                    ->setCost(0)
                    ->setPackageCount(1),
            );
            return $retval;
        }

        return $this->_getQuote($Order);
    }


    /**
     * Generic quote function to get a quote for a generic shipper.
     * This just uses the shipper's static rate table.
     *
     * @param   object  $Order      Order being shipped
     * @return  array       Single-element array with a ShippingQuote object
     */
    protected function _getQuote($Order)
    {
        // If a shipper module is used, use the configured packages.
        // Otherwise, get a quote based on units.
        if ($this->key != 'generic') {
            $Packages = Package::packOrder($Order, $this);
            $retval = array($this->getPackageQuote($Order->getShipto(), $Packages));
        } else {
            $retval = array($this->getUnitQuote($Order));
        }
        return $retval;
    }


    /**
     * Check if a specific service is supported by this shipper.
     *
     * @param   string  $key    Service key
     * @return  boolean     True if supported, False if not.
     */
    public function supportsService($key)
    {
        return in_array($key, $this->supported_services);
    }

}   // class Shipper
