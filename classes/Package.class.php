<?php
/**
 * Class for physical packages.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Shop\Models\DataArray;
use Shop\Util\JSON;


/**
 * Class for physical packages.
 * @package shop
 */
class Package
{
    /** Package record ID.
     * @var integer */
    private $pkg_id = 0;

    /** Max shipping units allowed in this package.
     * @var float */
    private $units = 0;

    /** Max weight allowed in this package.
     * @var float */
    private $max_weight = 0;

    /** Package width.
     * @var float */
    private $width = 0;

    /** Package height.
     * @var float */
    private $height = 0;

    /** Package length.
     * @var float */
    private $length = 0;

    /** Package description.
     * @var string */
    private $dscp = '';

    /** Shipper-specific container info.
     * @var array */
    private $containers = array();

    // Ephemeral properties not stored in the DB
    /** Shipping rate for this package.
     * @var float */
    private $rate = 0;

    /** Actual shipping weight.
     * @var float */
    private $weight = 0;

    /** Declared value of the package.
     * @var float */
    private $value = 0;

    /** Shipping units packed in this packge.
     * @var float */
    private $packed_units = 0;


    /**
     * Constructor. Sets variables from the provided array.
     *
     * @param  array   DB record
     */
    public function __construct($A=array())
    {
        global $_TABLES;

        if (is_array($A) && !empty($A)) {
            $this->setVars(new DataArray($A));
        } elseif (is_integer($A)) {
            $sql = "SELECT * FROM {$_TABLES['shop.packages']}
                WHERE pkg_id = " . (int)$A;
            $res = DB_query($sql);
            if ($res) {
                $A = DB_fetchArray($res, false);
                $this->setVars(new DataArray($A));
            }
        }
    }


    /**
     * Set the variables from an array (POST or DB) into object properties.
     *
     * @param   DataArray   $A  Array of key->value pairs
     * @return  object      $this
     */
    public function setVars(DataArray $A) : self
    {
        $this->pkg_id = $A->getInt('pkg_id');
        $this->units = $A->getFloat('units');
        $this->width = $A->getFloat('width');
        $this->length = $A->getFloat('length');
        $this->height = $A->getFloat('height');
        $this->max_weight = $A->getFloat('max_weight');
        $this->dscp = $A->getString('dscp');
        if (is_array($A['containers'])) {
            $this->containers = $A->getArray('containers');
        } else {
            $this->containers = json_decode($A->getString('containers'),true);
        }
        return $this;
    }


    /**
     * Get a package definition by record ID.
     *
     * @param   integer $pkg_id Package record ID
     * @return  object      Package object
     */
    public static function getInstance(int $pkg_id) : self
    {
        global $_TABLES;

        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.packages']} WHERE pkg_id = ?",
                array($pkg_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        $retval = new self;
        if (is_array($row)) {
            $retval->setVars(new DataArray($row));
        }
        return $retval;
    }


    /**
     * Edit the package definition.
     *
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_SHOP_CONF;

        $T = new \Shop\Template('admin');
        $T->set_file('form', 'pkg_form.thtml');
        $T->set_var(array(
            'pkg_id' => $this->pkg_id,
            'dscp' => $this->dscp,
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'max_weight' => $this->max_weight,
            'units' => $this->units,
            'uom_weight' => strtoupper($_SHOP_CONF['weight_unit']),
            'uom_size' => $_SHOP_CONF['uom_size'],
            'doc_url'       => SHOP_getDocURL('pkg_form'),
        ) );
        $T->set_block('form', 'CarrierInfo', 'CI');
        foreach (Shipper::getCarrierNames() as $carrier_id=>$carrier_name) {
            $Carrier = Shipper::getByCode($carrier_id);
            $pkg_codes = $Carrier->getPackageCodes();
            $services = $Carrier->getServiceCodes();
            $container = $this->getContainer($carrier_id)['container'];
            $service = $this->getContainer($carrier_id)['service'];
            $rate = isset($this->getContainer($carrier_id)['rate']) ?
                $this->getContainer($carrier_id)['rate'] : '';
            if (!empty($pkg_codes)) {
                $T->set_var('has_pkgcode_select', true);
                if ($container == '') {
                    $T->set_var('sel_none', 'selected="selected"');
                }
                $T->set_block('form', 'PkgCodes', 'PC');
                foreach ($pkg_codes as $code=>$dscp) {
                    $sel = $code == $container ? 'selected="selected"' : '';
                    $T->set_var(array(
                        'pkg_code' => $code,
                        'pkg_dscp' => $dscp,
                        'sel' => $sel,
                    ) );
                    $T->parse('PC', 'PkgCodes', true);
                }
            } else {
                // just a text entry
                $T->set_var(array(
                    'has_pkgcode_select' => false,
                    'container' => $container,
                ) );
            }
            if ($service == '_na') {
                $T->set_var('sel_none', 'selected="selected"');
            }
            $T->set_block('form', 'SvcCodes', 'SC');
            foreach ($services as $code=>$dscp) {
                $sel = $code == $service ? 'selected="selected"' : '';
                $T->set_var(array(
                    'svc_code' => $code,
                    'svc_dscp' => $dscp,
                    'sel' => $sel,
                ) );
                $T->parse('SC', 'SvcCodes', true);
            }
            $T->set_var(array(
                'carrier_id' => $carrier_id,
                'carrier_name' => $carrier_name,
                'service' => $this->getContainer($carrier_id)['service'],
                'rate' => $rate,
            ) );
            $T->parse('CI', 'CarrierInfo', true);
            $T->clear_var('PC');
            $T->clear_var('SC');
        }
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save the package definition.
     *
     * @param   array   $A  Posted form array
     * @return  boolean     True on success, False on error
     */
    public function Save(?DataArray $A=NULL) : bool
    {
        global $_TABLES;

        if (!empty($A)) {
            $this->setVars($A);
        }

        $values = array(
            'units' => $this->units,
            'max_weight' => $this->max_weight,
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'dscp' => $this->dscp,
            'containers' => JSON::encode($this->containers),
        );
        $types = array(
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
        );

        $db = Database::getInstance();
        try {
            if ($this->pkg_id == 0) {
                $db->conn->insert($_TABLES['shop.packages'], $values, $types);
                $this->pkg_id = $db->conn->lastInsertId();
            } else {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.packages'],
                    $values,
                    array('pkg_id' => $this->pkg_id),
                    $types,
                );
            }
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Delete a single package record from the database.
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

        try {
            Database::getInstance()->conn->delete(
                $_TABLES['shop.packages'],
                array('pkg_id' => $id),
                array(Database::INTEGER)
            );
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Return the package record ID.
     *
     * @return  integer     Package DB record ID
     */
    public function getID() : int
    {
        return (int)$this->pkg_id;
    }


    /**
     * Get the package description.
     *
     * @return  string      Package description
     */
    public function getDscp() : string
    {
        return $this->dscp;
    }


    /**
     * Add a declared value amount to the package.
     *
     * @param   float   $value  Declared value
     * @return  object  $this
     */
    public function addValue(float $value) : self
    {
        $this->value += (float)ceil($value);
        return $this;
    }


    /**
     * Add shipping weight to the package.
     *
     * @param   float   $weight Shipping weight to add
     * @return  object  $this
     */
    public function addWeight(float $weight) : self
    {
        $this->weight += (float)$weight;
        return $this;
    }


    /**
     * Get the current shipping weight of the package.
     *
     * @return  float   Shipping weight
     */
    public function getWeight() : float
    {
        return (float)$this->weight;
    }


    /**
     * Get the maximum weight allowed for this package.
     *
     * @return  float       Max allowed weight
     */
    public function getMaxWeight() : float
    {
        return (float)$this->max_weight;
    }


    /**
     * Get the package width.
     *
     * @return  float   Package width
     */
    public function getWidth() : float
    {
        return (float)$this->width;
    }


    /**
     * Get the package height.
     *
     * @return  float   Package height
     */
    public function getHeight() : float
    {
        return (float)$this->height;
    }


    /**
     * Get the package length.
     *
     * @return  float   Package length.
     */
    public function getLength() : float
    {
        return (float)$this->length;
    }


    /**
     * Get the package length, converted to inches.
     *
     * @return  float   Package length in inches
     */
    public function convertLength() : float
    {
        global $_SHOP_CONF;

        if ($_SHOP_CONF['uom_size'] == 'IN') {
            return (float)$this->length;
        } else {
            return (float)($this->length / 2.54);
        }
    }


    /**
     * Get the package height, converted to inches.
     *
     * @return  float   Package height in inches
     */
    public function convertHeight() : float
    {
        global $_SHOP_CONF;

        if ($_SHOP_CONF['uom_size'] == 'IN') {
            return (float)$this->height;
        } else {
            return (float)($this->height / 2.54);
        }
    }


    /**
     * Get the package width, converted to inches.
     *
     * @return  float   Package weight in inches
     */
    public function convertWidth() : flaot
    {
        global $_SHOP_CONF;

        if ($_SHOP_CONF['uom_size'] == 'IN') {
            return (float)$this->width;
        } else {
            return (float)($this->width / 2.54);
        }
    }


    /**
     * Get the package weight, converted to pounds.
     *
     * @return  float   Package weight in pounds
     */
    public function convertWeight() : float
    {
        global $_SHOP_CONF;

        if ($_SHOP_CONF['weight_unit'] == 'lbs') {
            return (float)$this->weight;
        } else {
            return (float)($this->weight * 2.204623);
        }
    }


    /**
     * Add shipping units into the package.
     *
     * @param   float   $units  Shipping units to add
     * @return  object  $this
     */
    public function addUnits(float $units) : self
    {
        $this->packed_units += (float)$units;
        return $this;
    }


    /**
     * Get the number of shipping units packed into the package.
     *
     * @return  float       Shipping units in package
     */
    public function getPackedUnits() : float
    {
        return (float)$this->packed_units;
    }


    /**
     * Get the maximum allowed shipping units for this package.
     *
     * @return  float       Max shipping units
     */
    public function getMaxUnits() : float
    {
        return (float)$this->units;
    }


    /**
     * Get the container information for a specific shipper.
     *
     * @param   string  $key    Shipper class name
     * @return  array   Container information
     */
    public function getContainer(string $key) : array
    {
        if (isset($this->containers[$key])) {
            return $this->containers[$key];
        } else {
            return array(
                'service' => '',
                'container' => '',
                'rate' => 0,
            );
        }
    }


    /**
     * Displays the admin list of packages.
     *
     * @return  string  HTML string containing the contents of the ipnlog
     */
    public static function adminList() : string
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $LANG_ADMIN, $LANG_SHOP_HELP;

        $sql = "SELECT * FROM {$_TABLES['shop.packages']}";

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'pkg_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['description'],
                'field' => 'dscp',
            ),
            array(
                'text' => $LANG_SHOP['max_ship_units'],
                'field' => 'units',
                'align' => 'right',
            ),
            array(
                'text' => 'Width',
                'field' => 'width',
                'align' => 'right',
            ),
            array(
                'text' => 'Length',
                'field' => 'length',
                'align' => 'right',
            ),
            array(
                'text' => 'Height',
                'field' => 'height',
                'align' => 'right',
            ),
            array(
                'text' => 'Max. Weight',
                'field' => 'max_weight',
                'align' => 'right',
            ),
            /*array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),*/
            /*array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'name',
            ),
            array(
                'text'  => $LANG_SHOP['grp_access'],
                'field' => 'grp_name',
            ),*/
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
            'field' => 'pkg_id',
            'direction' => 'ASC',
        );

        $query_arr = array(
            'table' => 'shop.packages',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            //'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/packages.php',
        );

        $options = array('chkdelete' => true, 'chkfield' => 'pkg_id');
        $filter = '';
        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_package'],
            'url' => SHOP_ADMIN_URL . '/packages.php?pkgedit=0',
            'style' => 'success',
        ) );
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_pkglist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the package admin list.
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
                'url' => SHOP_ADMIN_URL . "/packages.php?pkgedit={$A['pkg_id']}",
            ) );
            break;

        case 'enabled':
            $retval .= FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['pkg_id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['pkg_id']}','enabled','packages');",
            ) );
            break;

        case 'delete':
            $retval .= FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL. '/packages.php?pkgdelete=' . $A['pkg_id'],
                'attr' => array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                ),
            ) );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Get all the configured packages into a static array.
     *
     * @return  array       Array of Package objects
     */
    public static function getAll() : array
    {
        global $_TABLES;

        static $Packages = NULL;
        if ($Packages === NULL) {
            $Packages = array();
            $sql = "SELECT * FROM {$_TABLES['shop.packages']}
                ORDER BY units ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $Packages[] = new self($A);
            }
        }
        return $Packages;
    }


    /**
     * Get all the packages that are allowed to be used by a shipper.
     *
     * @param   object  $Shipper    Shipper object
     * @return  array       Array of Package objects
     */
    public static function getByShipper(object $Shipper) : array
    {
        $retval = array();
        $Packages = self::getAll();
        foreach ($Packages as $Package) {
            if ($Package->getContainerService($Shipper->getCode()) != '_na') {
                $retval[] = clone $Package;
            }
        }
        return $retval;
    }


    /**
     * Get the service associated with a package for a specifie shipper.
     *
     * @return  string      Container service
     */
    public function getContainerService(string $module_code) : string
    {
        if (
            isset($this->containers[$module_code]) &&
            isset($this->containers[$module_code]['service'])
        ) {
            return $this->containers[$module_code]['service'];
        } else {
            return '';
        }
    }


    /**
     * Pack the order for a shipper using the available packages.
     *
     * @param   object  $Order      Order object, to get the items
     * @param   object  $Shipper    Shipper object, to get the packages
     * @return  array       Array of Package objects
     */
    public static function packOrder(object $Order, object $Shipper) : array
    {
        if (is_integer($Shipper)) {
            $Shipper = Shipper::getInstance($Shipper);  // @deprecate
        }
        $pkgClasses = self::getByShipper($Shipper);
        $retval = array();
        $total_units = 0;
        $total_weight = 0;
        $fixed_shipping = 0;
        $items = array();
        foreach ($Order->getItems() as $id=>$Item) {
            $P = $Item->getProduct();
            $qty = $Item->getQuantity();
            $single_units = $Item->getShippingUnits();
            $item_units = $single_units * $qty;
            $fixed_shipping += $Item->getShipping() * $qty;
            $total_units += $item_units;
            $single_weight = $Item->getShippingWeight();
            $item_weight = $qty * $single_weight;
            $total_weight += $item_weight;
            for ($i = 0; $i < $qty; $i++) {
                $items[] = array(
                    'orderitem_id' => $id,
                    'item_name'     => $Item->getDscp(),
                    'single_units' => $single_units,
                    'single_weight' => $single_weight,
                    'price' => $Item->getPrice(),
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

        /*foreach ($pkgClasses as $Pkg) {
            if ($Pkg->getMaxUnits() >= $total_units) {
                // If a single package will handle the entire order, then
                // there's no need to iterate through all the items.
                $weight = 0;
                $value = 0;
                foreach ($items as $Item) {
                    $weight += $Item['weight'];
                    $value += $Item['price'];
                }
                $thisPkg = clone $Pkg;
                $thisPkg->addWeight($weight);
                $thisPkg->addValue($value);
                $thisPkg->addUnits($total_units);
                $retval[] = $thisPkg;
                return $retval;
            }
        }*/

        $units_left = $total_units;
        $weight_left = $total_weight;
        foreach ($items as &$item) {
            // First check to see if this item can be added to an existing package.
            foreach ($retval as $Pkg) {
                $units_left = $Pkg->getMaxUnits() - $Pkg->getPackedUnits();
                $weight_left = $Pkg->getMaxWeight() - $Pkg->getWeight();
                if (
                    $units_left >= $item['single_units'] &&
                    $weight_left >= $item['single_weight']
                ) {
                    // can add more of this item to the package.
                    $Pkg->addUnits($item['single_units']);
                    //$pkg['items'][] = $item['item_name'];
                    //$pkg['units'] += $item['single_units'];
                    //$pkg['units_left'] -= $item['single_units'];
                    $units_left -= $item['single_units'];
                    $weight_left -= $item['single_weight'];
                    $item['packed'] = true;
                    break;
                }
            }
            unset($Pkg);    // clear last value set in the loop

            // Couldn't fit in an existing package, create a new package.
            // Start with the largest package size, but then check to see if
            // the next-largest size is sufficient to handle the rest of the
            // shipment.
            if (!$item['packed']) {
                for ($i = count($pkgClasses)-1; $i >= 0; $i--) {
                    $pkgClass = $pkgClasses[$i];
                    if (
                        $i > 0 &&
                        $pkgClasses[$i-1]->getMaxUnits() >= $units_left &&
                        $pkgClasses[$i-1]->getMaxWeight() >= $weight_left
                    ) {
                        // get a smaller package if it can handle the rest of the shipment.
                        continue;
                    }
                    // Check that the item will fit. If not, there's a problem.
                    if (
                        $item['single_units'] <= $pkgClass->getMaxUnits() &&
                        $item['single_weight'] <= $pkgClass->getMaxWeight()
                    ) {
                        $thisPkg = clone $pkgClass;
                        $thisPkg->addWeight($item['single_weight']);
                        $thisPkg->addUnits($item['single_units']);
                        $thisPkg->addValue($item['price']);
                        $retval[] = $thisPkg;
                        /*$packages[] = array(
                            'type' => $type['dscp'],
                            'items' => array($item['item_name']),
                            'units' => $item['single_units'],
                            'units_left' => $type['max_units'] - $item['single_units'],
                            'rate' => $type['rate'],
                        );*/
                        $item['packed'] = true;
                        //$total_rate += $type['rate'];
                        //echo "Created new package for " . $item['orderitem_id'] . "\n";
                        break;
                    }
                }
            }

            if ($item['packed'] !== true) {
                // This shipper cannot handle this item
                Log::write('shop_system', Log::ERROR, "Error packing " . print_r($item,true));
                // Flag the total rate as NULL to indicate that this shipper
                // cannot be used.
                break;
            } else {
                $units_left -= $item['single_units'];
            }
        }
        return $retval;
    }

}
