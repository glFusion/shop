<?php
/**
 * Class to handle shipping costs based on quantity, total weight and class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.2
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use Shop\Log;
use Shop\Models\Dates;
use Shop\Models\ShippingQuote;
use Shop\Models\DataArray;
use Shop\Config;
use Shop\Util\JSON;


/**
 * Class for product and category sales.
 * @package shop
 */
class Shipper
{
    use \Shop\Traits\DBO;        // Import database operations

    const QUOTE_TABLE = 1;
    const QUOTE_API = 2;

    /** Table key for DBO functions
     * @var string */
    public static $TABLE = 'shop.shipping';

    /** Minimum units. Used since zero indicates free.
     * @const float */
    const MIN_UNITS = .0001;

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

    /** Method to use for rate quotes.
     * @var integer */
    protected $quote_method = 1;

    /** Shipping method used for free shipping.
     * @var string */
    protected $free_method = '';

    /** Flag to indicate where sales tax is calculated.
     * 0 = origin (shop address), 1 = destination (customer address).
     * Default to origin for any taxable virtual items.
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
        '_na' => '-- Not Available--',
        '_fixed' => '-- Fixed Rate --',
    );

    /** Selected package type code.
     * @var string */
    protected $pkg_code = '';

    /** Selected delivery service code.
     * @var string */
    protected $svc_code = '';

    /** Default unit-of-measurement strings.
     * configured setting => shipper's required text.
     * @var array */
    protected $uom = array(
        'IN'    => 'IN',
        'CM'    => 'CM',
        'lbs'   => 'LB',
        'kgs'   => 'KG',
    );

    protected $item_shipping = array('units' => 0, 'amount' => 0);


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
            $A = new DataArray($A);
            $this->setVars($A);
            $this->isNew = false;
        } elseif (is_numeric($A) && $A > 0) {
            // single ID passed in, e.g. from admin form
            if ($this->Read($A)) {
                $this->isNew = false;
            }
        } else {
            // New entry, set defaults
            $this->setValidFrom(NULL);
            $this->setValidTo(NULL);
            $this->min_units = self::MIN_UNITS;
            $this->max_units = 1000000;
            $this->tax_loc = Config::get('tax_nexus_virt');
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
    public function Read(int $id) : bool
    {
        global $_TABLES;

        $id = (int)$id;
        try {
            $A = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.shipping']} WHERE id = ?",
                array($id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A = false;
        }
        if (is_array($A)) {
            $A = new DataArray($A);
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
    public function setVars(DataArray $A, bool $fromDB=true) : void
    {
        global $LANG_SHOP;

        $this->setID($A->getInt('id'))
            ->setModuleCode($A->getString('module_code'))
            ->setName($A->getString('name'))
            ->setMinUnits($A->getFloat('min_units'))
            ->setMaxUnits($A->getFloat('max_units'))
            ->setEnabled($A->getInt('enabled'))
            ->setReqShipto($A->getInt('req_shipto'))
            ->setTaxLocation($A->getInt('tax_loc'))
            ->setUseFixed($A->getInt('use_fixed'))
            ->setQuoteMethod($A->getInt('quote_method', 1))
            ->setGrpAccess($A->getInt('grp_access', 2));
        if (!$fromDB) {
            $this->setValidFrom($A->getString('valid_from', Dates::MIN_DATE));
            $this->free_threshold = isset($A['ena_free']) ? (float)$A['free_threshold'] : 0;
            $rates = array();
            foreach ($A->getArray('rateRate') as $id=>$txt) {
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
            // convert valid dates to full date/time strings.
            // The form does not supply times.
            if (empty($A['valid_from'])) {
                $A['valid_from'] = Dates::MIN_DATE;
            } else {
                $A['valid_from'] = trim($A['valid_from']);
            }
            $A['valid_from'] .=  ' ' . Dates::MIN_TIME;
            if (empty($A['valid_to'])) {
                $A['valid_to'] = Dates::MAX_UNIXDATE;
            } else {
                $A['valid_to'] = trim($A['valid_to']);
            }
            $A['valid_to'] .=  ' ' . Dates::MAX_TIME;
        } else {
            $rates = array();
            if (isset($A['rates'])) {
                $rates = json_decode($A['rates']);
                if (!is_array($rates)) $rates = array();
            }
            $this->rates = $rates;
            $this->free_threshold = (float)$A['free_threshold'];
        }
        $this->setValidFrom($A->getString('valid_from', Dates::MIN_DATE . ' ' . Dates::MIN_TIME));
        $this->setValidTo($A->getString('valid_to', Dates::MAX_UNIXDATE . ' ' . Dates::MAX_TIME));
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
    public function setModuleCode($code)
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
    private function setMinUnits(float $units) : self
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
    private function setMaxUnits(float $units) : self
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


    /**
     * Set the sales tax location to either "origin" or "destination".
     *
     * @param   integer $flag   Location flag, 1=origin, 0=destination
     * @return  object  $this
     */
    private function setTaxLocation($flag)
    {
        $this->tax_loc = (int)$flag;
        return $this;
    }


    /**
     * Get the sales tax location flag.
     *
     * @return  integer     Location flag, 1=origin, 0=destination
     */
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
     * Set the method used to get rate quotes.
     *
     * @param   integer $key        Method key
     * @return  object  $this
     */
    private function setQuoteMethod($key)
    {
        $this->quote_method = (int)$key;
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
    private function setValidFrom(?string $value) : self
    {
        global $_CONF;

        if (empty($value)) {
            $value = Dates::MIN_DATE;
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
    private function setValidTo(?string $value) : self
    {
        global $_CONF;

        if (empty($value)) {
            $value = Dates::MAX_UNIXDATE;
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
    public static function getInstance(int $shipper_id) : self
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
                $shippers[$shipper_code]->setModuleCode($shipper_code);
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
        $shippers = Cache::get($cache_key);
        //$shippers = NULL;
        if ($shippers === NULL) {
            $shippers = array();
            $qb = Database::getInstance()->conn->createQueryBuilder();
            try {
                $qb->select('*')
                   ->from($_TABLES['shop.shipping']);
                if ($valid) {
                    $qb->andWhere('enabled = 1')
                       ->andWhere('valid_from < :now')
                       ->andWhere('valid_to > :now')
                       ->setParameter('now', time(), Database::INTEGER);
                }
                if ($units > -1) {
                    $qb->andWhere('min_units <= :units')
                       ->andWhere('max_units >= :units')
                       ->setParameter('units', $units, Database::STRING);
                }
                $stmt = $qb->execute();
            } catch (\Throwable $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($stmt) {
                while ($A = $stmt->fetchAssociative()) {
                    $shippers[$A['id']] = $A;
                }
            }
            Cache::set($cache_key, $shippers, self::$TABLE);
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
     * @deprecated
     * @param   object  $Order  Order being shipped
     * @return  array       Array of shipper objects, with rates and packages
     */
    public static function getShippersForOrder($Order)
    {
        global $LANG_SHOP;

        $cache_key = 'shipping_order_' . $Order->getOrderID() .
            '_' . $Order->getShippingUnits();
        $shippers = Cache::get($cache_key);
        if (is_array($shippers)) {
            return $shippers;
        }

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
        Cache::set($cache_key, $shippers, array('orders', self::$TABLE));
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
                Log::error("Error packing " . print_r($item,true));
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
    public function Save(?DataArray $A =NULL) : bool
    {
        global $_TABLES, $_SHOP_CONF;

        if (!empty($A)) {
            $this->setVars($A, false);
        }

        $values = array(
            'name' => $this->name,
            'module_code' => $this->module_code,
            'enabled' => $this->enabled,
            'req_shipto' => $this->requiresShipto(),
            'tax_loc' => $this->getTaxLocation(),
            'min_units' => $this->min_units,
            'max_units' => $this->max_units,
            'valid_from' => max(0, $this->valid_from->toUnix()),
            'valid_to' => $this->valid_to->toUnix(),
            'use_fixed' => $this->use_fixed,
            'free_threshold' => $this->free_threshold,
            'grp_access' => $this->grp_access,
            'quote_method' => $this->quote_method,
            'rates' => json_encode($this->rates),
        );
        $types = array(
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
        );

        $db = Database::getInstance();
        try {
            // Insert or update the record, as appropriate.
            if ($this->isNew) {
                $db->conn->insert(
                    $_TABLES['shop.shipping'],
                    $values,
                    $types,
                );
            } else {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.shipping'],
                    $values,
                    array('id' => $this->id),
                    $types
                );
            }
            Cache::clear(self::$TABLE);
            $status = true;
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $status = false;
        }

        usort($this->rates, function($a, $b) {
            return $a['units'] <=> $b['units'];
        });
        return $status;
    }


    /**
     * Delete a single shipper record from the database.
     *
     * @param   integer $id     Record ID
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete(int $id) : bool
    {
        global $_TABLES;

        if ($id <= 0) {
            return false;
        }

        if (!self::isUsed($id)) {
            try {
                Database::getInstance()->conn->delete(
                    $_TABLES['shop.shipping'],
                    array('id' => $id),
                    array(Database::INTEGER)
                );
                Cache::clear(self::$TABLE);
                return true;
            } catch (\Throwable $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
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
            'chk_qm_' . $this->quote_method => 'selected="selected"',
            'rate_type'     => $this->quote_method,     // for initial toggle
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
            Cache::clear(self::$TABLE);
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
                FieldList::info(array(
                    'title' => $LANG_SHOP_HELP['hlp_delete'],
                ) ),
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
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_ship_method'],
            'url' => SHOP_ADMIN_URL . '/index.php?editshipper=0',
            'style' => 'success',
        ) );
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
                    $config = FieldList::edit(array(
                        'url' => SHOP_ADMIN_URL . '/index.php?carrier_config=' . $code,
                    ) );
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
            $retval .= FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . "/index.php?editshipper={$A['id']}",
            ) );
            break;

        case 'enabled':
            $retval .= FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['id']}','enabled','shipping');",
            ) );
            break;

        case 'delete':
            if (!self::isUsed($A['id'])) {
                $retval .= FieldList::delete(array(
                    'delete_url' => SHOP_ADMIN_URL. '/index.php?delshipping=' . $A['id'],
                    'attr' => array(
                        'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                        'title' => $LANG_SHOP['del_item'],
                        'class' => 'tooltip',
                    ),
                ) );
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
                AND valid_from < UNIX_TIMESTAMP()
                AND valid_to > UNIX_TIMESTAMP()";
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
        $retval = '';
        $text = $tracking_num;
        $tracking_num = urlencode($tracking_num);
        if ($internal && $this->hasTrackingAPI()) {
            // Return the internal tracking page
            $retval = COM_createLink(
                $text,
                Config::get('url') . "/track.php?shipper={$this->key}&tracking={$tracking_num}",
                array(
                    'data-uk-lightbox' => '',
                    'data-lightbox-type' => 'iframe',
                )
            );
        } else {
            if (
                Config::get('trk_aftership') &&
                $this->id > 0 &&
                $this->key != ''
            ) {
                // Get the aftership.com tracking url
                $url = "https://aftership.com/track/{$this->key}/{$tracking_num}";
            } else {
                // Get the shipper's own tracking url
                $url = $this->_getTrackingUrl($tracking_num);
            }
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
        $db = Database::getInstance();
        if (
            $db->getCount(
                $_TABLES['shop.orders'],
                'shipper_id',
                $shipper_id,
                Database::INTEGER
            ) > 0 ||
            $db->getCount(
                $_TABLES['shop.shipment_packages'],
                'shipper_id',
                $shipper_id,
                Database::INTEGER
            ) > 0
        ) {
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

        $data = Database::getInstance()->getItem(
            $_TABLES['shop.carrier_config'],
            'data',
            array('code' => $this->key)
        );
        if ($data) {        // check that a data item was retrieved
            $config = @json_decode($data, true);
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
     * @param   DataArray   $form   Form data, e.g. $_POST
     * @return  boolean     True on success, False on failure
     */
    public function saveConfig(?DataArray $form=NULL) : bool
    {
        global $_TABLES;

        // Seed data with common data for all shippers
        $cfg_data = array(
            'ena_quotes'    => $form->getInt('ena_quotes'),
            'ena_tracking'  => $form->getInt('ena_tracking'),
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
                    $value = COM_encrypt($form->getString($name));
                }
                break;
            default:
                if (!isset($form[$name])) {
                    echo "$name<br />\n";
                    var_dump($form);die;
                    return false;       // required field missing
                } else {
                    $value = $form[$name];
                }
                break;
            }
            $cfg_data[$name] = $value;
        }
        if (isset($form['services'])) {
            $cfg_data['services'] = $form->getArray('services');
        }

        $data = JSON::encode($cfg_data);
        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['shop.carrier_config'],
                array('code' => $this->module_code, 'data' => $data),
                array(Database::STRING, Database::STRING)
            );
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            $db->conn->update(
                $_TABLES['shop.carrier_config'],
                array('data' => $data),
                array('code' => $this->module_code),
                array(Database::STRING, Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Present the configuration form for a shipper
     *
     * @return  string      HTML for the configuration form.
     */
    public function Configure()
    {
        global $LANG_SHOP_HELP;

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
            $F = new Template('fields');
            switch ($type) {
            case 'checkbox':
                $fld = FieldList::checkbox(array(
                    'name' => $name,
                    'checked' => $this->getConfig($name),
                ) );
                /*$F->set_file('field', 'checkbox.thtml');
                $F->set_var(array(
                    'fld_name' => $name,
                    'checked' => $this->getConfig($name),
                ) );
                $F->parse('output', 'field');
                $fld = $F->finish($F->get_var('output'));*/
                break;
            case 'text':
            case 'string':
            case 'password':
                $fld = FieldList::text(array(
                    'name' => $name,
                    'value' => $this->getConfig($name),
                ) );
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
                if ($key == '_na') {
                    // N/A service is a special case in the default svc_codes
                    continue;
                }
                //var_dump($this->supportsService($key));die;
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
    public function getPackageQuote($Packages, $Order)
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
        if ($this->use_fixed) {
            $cost += (float)$this->item_shipping['amount'];
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
    public function getUnitQuote($Order)
    {
        $quote = (new ShippingQuote)
            ->setID($this->id)
            ->setShipperID($this->id)
            ->setCarrierCode($this->key)
            ->setCarrierTitle($this->name)
            ->setServiceTitle($this->name)
            ->setServiceCode('units.' . $this->id)
            ->setServiceID($this->key . '.' . $this->id);
        $found = false;
        if (
            $this->item_shipping['units'] <= $this->max_units &&
            $this->item_shipping['units'] >= $this->min_units
        ) {
            foreach ($this->rates as $rate) {
                if ($rate->units > $this->item_shipping['units']) {
                    $found = true;
                    $quote['cost'] = $rate->rate;
                    break;
                }
            }
        }
        if ($found) {
            if ($this->use_fixed) {
                $quote['cost'] += (float)$this->item_shipping['amount'];
            }
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
    public function getQuote(\Shop\Order $Order) : array
    {
        $retval = array();
        $this->item_shipping = $Order->getItemShipping();
        if (
            $this->free_threshold > 0 &&
            $Order->getNetItems() > $this->free_threshold
        ) {
            $retval = array(
                (new ShippingQuote)
                    ->setID($this->id)
                    ->setShipperID($this->id)
                    ->setCarrierCode($this->key)
                    ->setCarrierTitle($this->getCarrierName())
                    ->setServiceCode('free')
                    ->setServiceID('free')
                    ->setServiceTitle($this->getName() . ' Free Shipping')
                    ->setCost(0)
                    ->setPackageCount(1),
            );
        } elseif (
            $this->item_shipping['units'] == 0 &&
            $this->item_shipping['amount'] > 0
        ) {
            // No shipping to be calculated, just use the fixed shipping amount
            $retval = array(
                (new ShippingQuote)
                    ->setID($this->id)
                    ->setShipperID($this->id)
                    ->setCarrierCode($this->key)
                    ->setCarrierTitle($this->getCarrierName())
                    ->setServiceCode('free')
                    ->setServiceID('free')
                    ->setServiceTitle($this->getName() . ' Fixed Shipping')
                    ->setCost($this->item_shipping['amount'])
                    ->setPackageCount(1),
            );
        } else {
            // Calculate shipping based on the shipping units and fixed shipping.
            // cache based on order, units, fixed amt and shipping addr
            $cache_key = $this->getID() . '.' . $this->item_shipping['units'] .
                '.' . $this->item_shipping['amount'] . '.' . $Order->getShipto()->toHash();
            $retval = Cache::get($cache_key);
            //$retval = NULL;           // debugging
            if ($retval === NULL) {
                switch ($this->quote_method) {
                case self::QUOTE_API:
                    $retval = $this->_getQuote($Order);
                    break;
                case self::QUOTE_TABLE:
                default:
                    $retval = $this->getUnitQuote($Order);
                    if (is_object($retval)) {
                        $retval = array($retval);
                    }
                    break;
                }
                Cache::set($cache_key, $retval, self::$TABLE);
            }
            // Will prevent the shipper from appearing in the workflow, this
            // is just to ensure a valid return.
            if (!is_array($retval)) {
                $retval = array();
            }
        }
        return $retval;
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
        if ($Order->totalShippingUnits() == 0) {
            // Return the fixed shipping cost, if any.
            $quote = (new ShippingQuote)
                ->setID($this->id)
                ->setShipperID($this->id)
                ->setCarrierCode($this->key)
                ->setCarrierTitle($this->name)
                ->setServiceTitle($this->name)
                ->setServiceCode('units.' . $this->id)
                ->setServiceID($this->key . '.' . $this->id)
                ->setCost($this->item_shipping['amount']);
            return $quote;
        }

        // If a shipper module is used, use the configured packages.
        // Otherwise, get a quote based on units.
        if ($this->key != '') {
            $Packages = Package::packOrder($Order, $this);
            $retval = array($this->getPackageQuote($Packages, $Order));
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


    /**
     * Get the weight unit of measurement to send to the shipper's API.
     *
     * @return  string      Correct unit of measure for the shipper
     */
    protected function getWeightUOM()
    {
        global $_SHOP_CONF;

        return $this->uom[$_SHOP_CONF['weight_unit']];
    }


    /**
     * Get the size unit of measurement to send to the shipper's API.
     *
     * @return  string      Correct unit of measure for the shipper
     */
    protected function getSizeUOM()
    {
        global $_SHOP_CONF;

        return $this->uom[$_SHOP_CONF['uom_size']];
    }

}
