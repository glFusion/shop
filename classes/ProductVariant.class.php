<?php
/**
 * Class to manage product variants.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class for product variants.
 * Variants are combinations of options represented by a single sku, such as
 * color, size and style.
 * @package shop
 */
class ProductVariant
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Key field name.
     * @var string */
    private static $TABLE = 'shop.product_variants';

    /** Key field name.
     * @var string */
    private  static $F_ID = 'pv_id';

    /** Variant record ID.
     * @var integer */
    private $pv_id = 0;

    /** Product record ID.
     * @var integer */
    private $item_id = 0;

    /** Variant description.
     * @var array */
    private $dscp = array();

    /** Price impact amount.
     * @var float */
    private $price = 0;

    /** Weight impact.
     * @var float */
    private $weight = 0;

    /** Shipping Units impact.
     * @var float */
    private $shipping_units = 0;

    /** Variant SKU.
     * @var string */
    private $sku = '';

    /** Supplier reference number (sku, part number, etc.).
     * @var string */
    private $supplier_ref = '';

    /** Flag to indicate whether quantity onhand is tracked by variant.
     * @var boolean */
    private $track_onhand = 0;

    /** Quantity on hand.
     * @var float */
    private $onhand = 0;

    /** Reorder quantity. Overrides the product reorder setting.
     * @var integer */
    private $reorder = 0;

    /** Flag to incidate that orders can be accepted for this variant.
     * @var integer */
    private $enabled = 1;

    /** OptionValue items associated with this variant.
     * Use NULL to indicate that options have not yet been read.
     * @var array */
    private $Options = NULL;

    /** Image IDs associated with this Variant.
     * @var array */
    private $images = array();

    /** Cache tag used for ProductOptions.
     * @var string */
    private const TAG = 'variants';


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
     * Get an instance of a specific variant record.
     *
     * @param   integer $pv Record ID to retrieve
     * @return  object      ProductVariant object
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
     * @param   boolean $fromDB True if reading from DB, false if from form
     * @return  boolean     True on success, False if $A is not an array
     */
    public function setVars($A, $fromDB=true)
    {
        if (is_array($A)) {
            $pfx = $fromDB ? '' : 'pv_';
            $this
                ->setId(SHOP_getVar($A, 'pv_id', 'integer'))
                ->setItemId(SHOP_getVar($A, $pfx.'item_id'))
                ->setPrice(SHOP_getVar($A, $pfx.'price', 'float'))
                ->setWeight(SHOP_getVar($A, $pfx.'weight', 'float'))
                ->setShippingUnits(SHOP_getVar($A, $pfx.'shipping_units', 'float'))
                ->setSku(SHOP_getVar($A, $pfx.'sku'))
                ->setSupplierRef(SHOP_getVar($A, $pfx.'supplier_ref'))
                ->setTrackOnhand(SHOP_getVar($A, $pfx.'track_onhand', 'integer'))
                ->setOnhand(SHOP_getVar($A, $pfx.'onhand', 'float'))
                ->setReorder(SHOP_getVar($A, $pfx.'reorder', 'float'))
                ->setImageIDs(SHOP_getVar($A, $pfx.'img_ids', 'mixed'))
                ->setEnabled(SHOP_getVar($A, $pfx.'enabled', 'integer', 1));
            if (isset($A['dscp'])) {        // won't be set from the edit form
                $this->setDscp($A['dscp']);
            }
        }
        return $this;
    }


    /**
     * Get the product variant record that has all the requested attributes.
     *
     * @param   integer $item_id    Product ID
     * @param   array   $attribs    Array of OptionValue IDs
     * @return  object  Matching ProductVariant object
     */
    public static function getByAttributes($item_id, $attribs)
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        if (!is_array($attribs) || empty($attribs)) {
            return new self;
        }
        $count = count($attribs);
        $attr_sql = implode(',', $attribs);
        $sql = "SELECT pv.* FROM {$_TABLES['shop.variantXopt']} vxo
            INNER JOIN {$_TABLES['shop.product_variants']} pv
                ON vxo.pv_id = pv.pv_id
            WHERE vxo.pov_id IN ($attr_sql) AND pv.item_id = $item_id
            GROUP BY vxo.pv_id
            HAVING COUNT(pv.item_id) = $count
            LIMIT 1";
        //echo $sql;
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            return self::getInstance($A);
        } else {
            return new Self;
        }
    }


    /**
     * Get the internal Options property.
     *
     * @return  array   Array of ProductOptionValue objects
     */
    public function getOptions()
    {
        if ($this->Options === NULL) {
            $this->loadOptions();
        }
        return $this->Options;
    }


    /**
     * Get the option selections for a product's variants.
     *
     * @deprecated
     * @param   integer $item_id    Product record ID
     */
    public static function XXgetSelections($item_id)
    {
        $sql = "SELECT pov.* FROM {$_TABLES['shop.product_var_opts']} pov
            INNER JOIN {$_TABLES['shop.variantXopt']} vxo
                ON pov.pov_id = vxo.pov_id
            INNER JOIN {$_TABLES['shop.product_variants']} pv
                ON pv.pv_id = vxo.pv_id
            INNER JOIN {$_TABLES['shop.product_option_groups']} pog
                ON pog.pog_id = pov.pog_id
            WHERE pv.item_id = $item_id
            ORDER BY pog.pog_orderby asc";
    }


    /**
     * Load the product attributs into the options array.
     *
     * @access  public  To be called during upgrade
     * @return  object  $this
     */
    public function loadOptions()
    {
        global $_TABLES;

        if ($this->Options === NULL) {
            $cache_key = 'pog_options_' . $this->getID();
            $this->Options = Cache::get($cache_key);
            if ($this->Options === NULL) {
                $this->Options = array();
                $sql = "SELECT pov.*, pog.pog_name FROM {$_TABLES['shop.prod_opt_vals']} pov
                INNER JOIN {$_TABLES['shop.variantXopt']} vx
                    ON vx.pov_id = pov.pov_id
                INNER JOIN {$_TABLES['shop.prod_opt_grps']} pog
                    ON pog.pog_id = pov.pog_id
                WHERE vx.pv_id = {$this->getID()}
                ORDER BY pog.pog_orderby ASC";
                //echo $sql;die;
                $res = DB_query($sql);
                while ($A = DB_fetchArray($res, false)) {
                    $this->Options[$A['pog_name']] = new ProductOptionValue($A);
                }
                Cache::set($cache_key, $this->Options, array(self::TAG, $this->getID()));
            }
        }
        return $this;
    }


    /**
     * Get the option IDs for all options in use by this variant.
     *
     * @return  array       Array of Option Value record IDs.
     */
    private function _optsInUse()
    {
        $retval = array();
        if ($this->Options === NULL) {
            $this->loadOptions();
        }
        foreach ($this->Options as $Opt) {
            $retval [] = $Opt->getID();
        }
        return $retval;
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


    /**
     * Set the description field.
     *
     * @param   array|string    $dscp   Array or JSON string
     * @return  object      $this
     */
    public function setDscp($dscp)
    {
        if (is_array($dscp)) {
            $this->dscp = $dscp;
        } else {
            $this->dscp = json_decode($dscp,true);
        }
        return $this;
    }


    /**
     * Set the variant price impact.
     *
     * @param   float   $price      Price impact
     * @return  object  $this
     */
    public function setPrice($price)
    {
        $this->price = (float)$price;
        return $this;
    }


    /**
     * Set the variant weight impact.
     *
     * @param   float   $weight     Weight impact in KG or LB
     * @return  object  $this
     */
    public function setWeight($weight)
    {
        $this->weight = (float)$weight;
        return $this;
    }


    /**
     * Set the variant shipping unit impact
     *
     * @param   float   $units      Additional shipping units for this variant
     * @return  object  $this
     */
    public function setShippingUnits($units)
    {
        $this->units= (float)$units;
        return $this;
    }


    /**
     * Set the flag to track qty onhand by variant or not.
     *
     * @param   boolean $flag   True to track qty onhand, False to not
     * @return  object  $this
     */
    public function setTrackOnhand($flag)
    {
        $this->track_onhand = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if quantity onhand is tracked by variant.
     *
     * @return  boolean     1 to track by variant, 0 to track by product.
     */
    public function getTrackOnhand()
    {
        return $this->track_onhand ? 1 : 0;
    }


    /**
     * Set the quantity on hand.
     *
     * @param   float   $onhand     Number of units on hand
     * @return  object  $this
     */
    public function setOnhand($onhand)
    {
        $this->onhand = (float)$onhand;
        return $this;
    }


    /**
     * Get the quantity on hand for this variant.
     *
     * @return  float       Quantity onhand
     */
    public function getOnhand()
    {
        return (float)$this->onhand;
    }


    /**
     * Set the reorder quantity.
     *
     * @param   float   $reorder    Reorder quantity
     * @return  object  $this
     */
    public function setReorder($reorder)
    {
        $this->reorder = (float)$reorder;
        return $this;
    }


    /**
     * Get the quantity on hand for this variant.
     *
     * @return  float       Quantity onhand
     */
    public function getReorder()
    {
        return (float)$this->reorder;
    }


    /**
     * Set the `enabled` flag.
     *
     * @param   integer $enabled    0 for disabled, nonzero for enabled
     * @return  object  $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled == 0 ? 0 : 1;
        return $this;
    }


    /**
     * Set the SKU field.
     *
     * @param   string  $sku        SKU for this variant
     * @return  object  $this
     */
    public function setSku($sku)
    {
        $this->sku = $sku;
        return $this;
    }


    /**
     * Get the SKU value.
     *
     * @return  string      SKU for this variant
     */
    public function getSku()
    {
        return $this->sku;
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
     * Get the lead time for this variant.
     * TODO: Add per-variant lead times.
     *
     * @return  string  Lead time if out of stock
     */
    public function getLeadTime()
    {
        // if ($this->lead_time == '') {        // todo
        // return $this->lead_time;
        // } else {
        return Product::getInstance($this->item_id)->LeadTime();
        // }
    }


    /**
     * Get all variants related to a given productd.
     *
     * @param   integer $product_id     Product record ID
     * @return  array       Array of ProductVariant objects
     */
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
     * Get the description array elements.
     *
     * @return  array       Array of description fields
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    /**
     * Get a multi-line version of the description for HTML display.
     *
     * @return  string      HTML version of the description
     */
    public function getDscpHTML()
    {
        $retval = '';
        foreach ($this->dscp as $dscp) {
            $retval .= " -- {$dscp['name']}: {$dscp['value']}<br />\n";
        }
        return $retval;
    }


    /**
     * Get the description as a comma separated string.
     * name:value, name.value, name.value ...
     *
     * @return  string      One-line description
     */
    public function getDscpString()
    {
        $retval = array();
        foreach ($this->dscp as $dscp) {
            $retval[] = "{$dscp['name']}:{$dscp['value']}";
        }
        $retval = implode(', ', $retval);
        return $retval;
    }


    /**
     * Get the decriptive elements from the option names and values.
     * Sets the local dscp property
     *
     * @access  public  To be called during upgrade
     * @return  object  $this
     */
    public function makeDscp()
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
     * Creates the edit form for a new variant for a new product.
     *
     * @return  string      HTML for edit form
     */
    public static function Create()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = new Template;
        $T->set_file('form', 'variant_edit.thtml');

        // Default to the item's reorder quantity
        $T->set_var(array(
            'pv_id'         => 0,
            'price'         => '',
            'weight'        => '',
            'onhand'        => '',
            'shipping_units' => '',
            'sku'           => '',
            'reorder'       => '',
            'is_form'       => false,
        ) );
        $Groups = ProductOptionGroup::getAll();
        $T->set_block('form', 'OptionGroups', 'Grps');
        foreach ($Groups as $gid=>$Grp) {
            $T->set_var(array(
                'pog_id'    => $gid,
                'pog_name'  => $Grp->getName(),
            ) );
            $T->set_block('Grps', 'OptionValues', 'Vals');
            $Opts = ProductOptionValue::getByGroup($Grp->getID());
            foreach ($Opts as $pov_id=>$Opt) {
                $T->set_var(array(
                    'opt_id'    => $Opt->getID(),
                    'opt_val'   => $Opt->getValue(),
                    'opt_sel'   => '',
                ) );
                $T->parse('Vals', 'OptionValues', true);
            }
            $T->parse('Grps', 'OptionGroups', true);
            $T->clear_var('Vals');
        }
        return $T->parse('output', 'form');
    }


    /**
     * Creates the edit form for an existing variant.
     * Editing allows changes to price, weight, shipping impacts and sku.
     * Changes to the component options is not allowed.
     *
     * @param   integer $id Optional ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = new Template;
        $T->set_file(array(
            'form' => 'variant_edit.thtml',
            'tips' => 'tooltipster.thtml',
        ) );

        if ($this->pv_id == 0) {
            $this->setReorder(Product::getInstance($this->getItemId())->getReorder());
            $this->setOnhand(Product::getInstance($this->getItemId())->getOnhand());
            $this->setTrackOnhand(Product::getInstance($this->getItemId())->getTrackOnhand());
        }
        $Product = Product::getByID($this->item_id);
        $T->set_var(array(
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('variant_form', $_CONF['language']),
            'title'         => $this->pv_id == 0 ? $LANG_SHOP['new_variant'] : $LANG_SHOP['edit_variant'],
            'ena_chk'       => $this->enabled == 0 ? '' : 'checked="checked"',
            'trk_onhand_chk' => $this->track_onhand ? 'checked="checked"' : '',
            'onhand_vis'    => $this->track_onhand ? '' : 'none',
            'item_id'       => $this->getItemId(),
            'item_name'     => $Product->getName(),
            'pv_id'         => $this->getId(),
            'price'         => $this->getID() ? $this->getPrice() : '',
            'weight'        => $this->getWeight(),
            'onhand'        => $this->getOnhand(),
            'shipping_units' => $this->getShippingUnits(),
            'sku'           => $this->getSku(),
            'dscp'          => $this->getDscpString(),
            'reorder'       => $this->getReorder(),
            'is_form'       => true,
            'supplier_ref'  => $this->getSupplierRef(),
        ) );
        $Groups = ProductOptionGroup::getAll();
        $optsInUse = $this->_optsInUse();
        $T->set_block('form', 'OptionGroups', 'Grps');
        foreach ($Groups as $gid=>$Grp) {
            $T->set_var(array(
                'pog_id'    => $gid,
                'pog_name'  => $Grp->getName(),
            ) );
            $T->set_block('Grps', 'OptionValues', 'Vals');
            $Opts = ProductOptionValue::getByGroup($Grp->getID());
            foreach ($Opts as $pov_id=>$Opt) {
                $T->set_var(array(
                    'opt_id'    => $Opt->getID(),
                    'opt_val'   => $Opt->getValue(),
                    'opt_sel'   => in_array($Opt->getID(), $optsInUse) ? 'selected="selected"' : '',
                ) );
                $T->parse('Vals', 'OptionValues', true);
            }
            $T->parse('Grps', 'OptionGroups', true);
            $T->clear_var('Vals');
        }

        $T->set_block('form', 'ImageBlock', 'IB');
        foreach ($Product->getImages() as $img) {
            $T->set_var(array(
                'img_id'    => $img['img_id'],
                'img_url'   => Images\Product::getUrl($img['filename'])['url'],
                'img_chk'   => in_array($img['img_id'], $this->images) ? 'checked="checked"' : '',
            ) );
            $T->parse('IB', 'ImageBlock', true);
        }

        $T->parse('tooltipster_js', 'tips');
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Create the form to edit certain variant attributes in bulk.
     *
     * @param   array   $pv_ids     Array of variant IDs
     * @return  string      HTML for edit form
     */
    public static function bulkEdit($pv_ids)
    {
        if (empty($pv_ids)) {
            return '';
        }
        $T = new Template;
        $T->set_file('form', 'var_bulk_form.thtml');
        $T->set_var(array(
            'pv_ids'    => implode(',', $pv_ids),
        ) );
        $T->set_block('form', 'skuList', 'sk');
        $Var = self::getInstance($pv_ids[0]);
        $Product = Product::getByID($Var->getItemId());
        foreach ($pv_ids as $pv_id) {
            $T->set_var('sku', self::getInstance($pv_id)->getSku());
            $T->parse('sk', 'skuList', true);
        }
        $T->set_block('form', 'ImageBlock', 'IB');
        foreach ($Product->getImages() as $img) {
            $T->set_var(array(
                'img_id'    => $img['img_id'],
                'img_url'   => Images\Product::getUrl($img['filename'])['url'],
                'img_chk'   => in_array($img['img_id'], $Var->getImageIDs()) ? 'checked="checked"' : '',
            ) );
            $T->parse('IB', 'ImageBlock', true);
        }

        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Perform the bulk update of multiple variants at once.
     *
     * @param   array   $A      Values from $_POST
     * @return  boolean     True on success, False on failure
     */
    public static function BulkUpdateDo($A)
    {
        global $_TABLES;

        $sql_vals  = array();

        if (isset($A['price']) && $A['price'] !== '') {
            $sql_vals[] = "price = " . (float)$A['price'];
        }
        if (isset($A['weight']) && $A['weight'] !== '') {
            $sql_vals[] = "weight = " . (float)$A['weight'];
        }
        if (isset($A['shipping_units']) && $A['shipping_units'] !== '') {
            $sql_vals[] = "shipping_units = " . (float)$A['shipping_units'];
        }
        if (isset($A['onhand']) && $A['onhand'] !== '') {
            $sql_vals[] = 'onhand = ' . (float)$A['onhand'];
        }
        if (isset($A['reorder']) && $A['reorder'] !== '') {
            $sql_vals[] = 'reorder = ' . (float)$A['reorder'];
        }
        if (isset($A['enabled']) && $A['enabled'] > -1) {
            $sql_vals[] = "enabled = " . ($A['enabled'] == 1 ? 1 : 0);
        }
        if (!isset($A['img_noupdate'])) {
            // no-update checkbox is unchecked for images
            if (isset($A['pv_img_ids'])) {
                $sql_vals[] = "img_ids = '" . implode(',', $A['pv_img_ids']) . "'";
            } else {
                // No images selected, implies all images
                $sql_vals[] = "img_ids = ''";
            }
        }
        if (!empty($sql_vals)) {
            $sql_vals = implode(', ', $sql_vals);
            $ids = DB_escapeString($A['pv_ids']);
            $sql = "UPDATE {$_TABLES['shop.product_variants']} SET
                $sql_vals
                WHERE pv_id IN ($ids)";
            //echo $sql;die;
            DB_query($sql);
            if (DB_error()) {
                return false;
            }
        }
        Cache::clear('products');
        Cache::clear(self::TAG);
        return true;
    }


    /**
     * Save a new product variant.
     * The `item_id` field needs to be set in the array parameter.
     *
     * @param   array   $A      Form values
     * @return  boolean     True on success, False on failure
     */
    public static function saveNew($A)
    {
        global $_TABLES;

        // Clean out any zero (not selected) options for groups
        foreach ($A['groups'] as $id=>&$grp) {
            foreach ($grp as $gid=>$val) {
                if ($val == 0) {
                    unset($grp[$gid]);
                }
            }
            if (empty($grp)) {
                unset($A['groups'][$id]);
            }
        }

        $item_id = (int)$A['pv_item_id'];
        if ($item_id < 1 || empty($A['groups'])) {
            return false;
        }
        $P = Product::getById($item_id);
        if ($P->getID() == 0) {
            return false;   // item ID provided but invalid
        }

        $price = 0;
        $weight = SHOP_getVar($A, 'weight', 'float', 0);
        $shipping_units = SHOP_getVar($A, 'shipping_units', 'float', 0);
        $matrix = self::_cartesian($A['groups']);
        foreach ($matrix as $groups) {
            if ($A['pv_price'] !== '') {
                $price = (float)$A['pv_price'];
            } else  {
                $price = 0;
            }
            $opt_ids = array();
            $sku_parts = array();
            $dscp = array();
            foreach($groups as $pog=>$pov_id) {
                if ($pov_id == 0) {
                    continue;
                }
                $opt_ids[] = $pov_id;   // save for the variant->opt table
                $Opt = new ProductOptionValue($pov_id);
                if (!isset($A['pv_price']) || $A['pv_price'] === '') {   // Zero is valid
                    $price += $Opt->getPrice();
                }
                if (empty($A['pv_sku'])) {
                    if ($Opt->getSku() != '') {
                        $sku_parts[] = $Opt->getSku();
                    }
                }
                $dscp[] = array(
                    'name'  => ProductOptionGroup::getInstance($pog)->getName(),
                    'value' => $Opt->getValue(),
                );
            }
            if (empty($A['pv_sku'])) {
                if (!empty($sku_parts) && !empty($P->getName())) {
                    $sku = $P->getName() . '-' . implode('-', $sku_parts);
                }
            } else {
                $sku = $A['pv_sku'];
            }
            if ($A['pv_onhand'] === '') {
                $onhand = $P->getOnhand();
            } else {
                $onhand = (float)$A['pv_onhand'];
            }
            if ($A['pv_reorder'] === '') {
                $reorder = $P->getReorder();
            } else {
                $reorder = (float)$A['pv_reorder'];
            }
            if ($A['pv_supplier_ref'] === '') {
                $sup_ref = $P->getSupplierRef();
            } else {
                $sup_ref = $A['pv_supplier_ref'];
            }

            $sql = "INSERT INTO {$_TABLES['shop.product_variants']} SET
                item_id = $item_id,
                sku = '" . DB_escapeString($sku) . "',
                supplier_ref = '" . DB_escapeString($sup_ref) . "',
                price = " . (float)$price . ",
                weight = $weight,
                shipping_units = $shipping_units,
                reorder = $reorder,
                dscp = '" . DB_escapeString(json_encode($dscp)) . "',
                onhand = $onhand";
            //echo $sql;die;
            SHOP_log($sql, SHOP_LOG_DEBUG);
            DB_query($sql);
            if (!DB_error()) {
                $pv_id = DB_insertID();
                foreach ($opt_ids as $opt_id) {
                    $vals[] = '(' . $pv_id . ',' . $opt_id . ')';
                }
            }
        }
        if (!empty($vals)) {
            $sql_vals = implode(',', $vals);
            $sql = "INSERT IGNORE INTO {$_TABLES['shop.variantXopt']}
                (pv_id, pov_id) VALUES $sql_vals";
            DB_query($sql);
        }
        Cache::clear(self::TAG);
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
            $this->setVars($A, false);
        }
        if ($this->pv_id == 0) {
           if (isset($A['groups'])) {
               return self::saveNew($A);
           } else {
               $sql1 = "INSERT INTO {$_TABLES['shop.product_variants']} SET ";
                $sql3 = '';
           }
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.product_variants']} SET ";
            $sql3 = " WHERE pv_id = '{$this->pv_id}'";
        }
        $sql2 = "item_id = '" . (int)$this->item_id . "',
            sku = '" . DB_escapeString($this->sku) . "',
            supplier_ref = '" . DB_escapeString($this->supplier_ref) . "',
            price = '" . (float)$this->price . "',
            weight = '" . (float)$this->weight . "',
            shipping_units = '" . (float)$this->shipping_units . "',
            track_onhand = '{$this->getTrackOnhand()}',
            onhand = " . (float)$this->onhand . ",
            img_ids = '" . DB_escapeString(implode(',', $this->images)) . "',
            dscp = '" . DB_escapeString(json_encode($this->dscp)) . "',
            reorder = " . (float)$this->reorder;
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->pv_id == 0) {
                $this->pv_id = DB_insertID();
            }
            $retval = true;
        } else {
            $retval = false;;
        }

        // Create two standardized arrays to detect new and removed option vals.
        // Only if submitted from a form, where the groups variable is present.
        if (isset($A['groups'])) {
            $old_opts = array();
            $new_opts = array();
            foreach ($this->Options as $Opt) {
                $old_opts[] = $Opt->getID();
            }
            foreach ($A['groups'] as $opt) {
                if ($opt > 0) {
                    $new_opts[] = (int)$opt;
                }
            }
            $removed = array_diff($old_opts, $new_opts);
            $added = array_diff($new_opts, $old_opts);
            if (!empty($added)) {
                foreach ($added as $opt_id) {
                    $vals[] = '(' . $this->getID() . ',' . $opt_id . ')';
                }
                $sql_vals = implode(',', $vals);
                $sql = "INSERT IGNORE INTO {$_TABLES['shop.variantXopt']}
                    (pv_id, pov_id) VALUES $sql_vals";
                DB_query($sql);
            }
            if (!empty($removed)) {
                $removed = implode(',', $removed);
                $sql = "DELETE FROM {$_TABLES['shop.variantXopt']}
                    WHERE pv_id = " . $this->getID() . " AND pov_id IN ($removed)";
                DB_query($sql);
            }
        }
        Cache::clear(self::TAG);
        return $retval;
    }


    /**
     * Delete a product variant.
     *
     * @param   integer $id     Variant record ID
     */
    public static function Delete($id)
    {
        global $_TABLES;

        $id = (int)$id;
        DB_delete($_TABLES['shop.product_variants'], 'pv_id', $id);
        DB_delete($_TABLES['shop.variantXopt'], 'pv_id', $id);
        Cache::clear(self::TAG);
    }


    /**
     * Delete all references to an option value when that value is deleted.
     *
     * @param   integer $opt_id     Option value record ID
     */
    public static function deleteOptionValue($opt_id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.variantXopt'], 'pov_id', (int)$opt_id);
    }


    /**
     * Just return the price property.
     *
     * @return  float       Item Price impact
     */
    public function getPrice()
    {
        return (float)$this->price;
    }


    /**
     * Just return the weight property.
     *
     * @return  float       Weight impact
     */
    public function getWeight()
    {
        return (float)$this->weight;
    }


    /**
     * Just return the shipping units property.
     *
     * @return  float       Shipping Units impact
     */
    public function getShippingUnits()
    {
        return (float)$this->shipping_units;
    }


    /**
     * Get the product ID for this variant.
     *
     * @return  string      Product ID
     */
    public function getItemId()
    {
        return $this->item_id;
    }


    /**
     * Get the database record ID of this item.
     *
     * @return  integer     DB record ID
     */
    public function getID()
    {
        return (int)$this->pv_id;
    }


    /**
     * Set the image IDs specific to this variant.
     *
     * @param   array   $ids    Array of image record IDs
     * @return  object  $this
     */
    public function setImageIDs($ids)
    {
        if ($ids === NULL) {
            return $this;
        }
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $this->images = array_filter($ids, 'intval');
        if (!$this->images) {
            $this->images = array();
        }
        return $this;
    }


    /**
     * Get the image IDs for this particular product variant.
     *
     * TODO: implement DB field
     * @return  array       Array of integer image IDs.
     */
    public function getImageIDs()
    {
        return $this->images;
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
     * Reorder all attribute items with the same product ID and attribute name.
     */
    private function reOrder()
    {
        global $_TABLES;

        $sql = "SELECT pv_id, orderby
                FROM {$_TABLES['shop.product_variants']}
                WHERE item_id= '{$this->item_id}'
                ORDER BY orderby ASC;";
        $result = DB_query($sql);

        $order = 10;        // First orderby value
        $stepNumber = 10;   // Increment amount
        $changed = false;   // Assume no changes
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $changed = true;
                $sql = "UPDATE {$_TABLES['shop.product_variants']}
                    SET orderby = '$order'
                    WHERE pv_id = '{$A['pv_id']}'";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
        if ($changed) {
            Cache::clear(self::TAG);
        }
    }


    /**
     * Move a variant up or down the selection list, within its product.
     *
     * @param   string  $where  Direction to move (up or down)
     */
    public function moveRow($where)
    {
        global $_TABLES;

        switch ($where) {
        case 'up':
            $oper = '-';
            break;
        case 'down':
            $oper = '+';
            break;
        default:
            $oper = '';
            break;
        }

        if (!empty($oper)) {
            $sql = "UPDATE {$_TABLES['shop.product_variants']}
                    SET orderby = orderby $oper 11
                    WHERE pv_id = '{$this->pv_id}'";
            //echo $sql;die;
            DB_query($sql);
            $this->reOrder();
        }
    }


    /**
     * Product Variant List View.
     *
     * @param   integer $prod_id    Optional product ID to limit listing
     * @return  string      HTML for the attribute list.
     */
    public static function adminList($prod_id)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM, $LANG01;

        $prod_id = (int)$prod_id;

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'pv_id',
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['order'],
                'field' => 'orderby',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => 'SKU',
                'field' => 'sku',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['supplier_ref'],
                'field' => 'supplier_ref',
                'sort'  => true,
            ),
            array(
                'text' => $LANG_SHOP['description'],
                'field' => 'dscp',
            ),
            array(
                'text' => $LANG_SHOP['opt_price'],
                'field' => 'price',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_SHOP['shipping'],
                'field' => 'shipping_units',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_SHOP['onhand'],
                'field' => 'onhand',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_SHOP['reorder'],
                'field' => 'reorder',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => 'false',
                'align' => 'center',
            ),
        );

        $extra = array();
        if ($prod_id) {
            $header_arr[] = array(
                'text' => $LANG_SHOP['default'],
                'field' => 'def_pv_id',
                'sort' => 'false',
                'align' => 'center',
            );
            $extra['def_pv_id'] = (int)DB_getItem(
                $_TABLES['shop.products'],
                'def_pv_id',
                "id = $prod_id"
            );
            $extra['max_orderby'] = (int)DB_getItem(
                $_TABLES['shop.product_variants'],
                'MAX(orderby)',
                "item_id = $prod_id"
            );
        }

        $defsort_arr = array(
            'field' => 'orderby',
            'direction' => 'ASC',
        );
        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $view = SESS_getVar('shop.pv_view');
        switch ($view) {
        case 'pv_bulk':
            $display .= COM_createLink(
                Icon::getHTML('arrow-left') . '&nbsp;Back to Product',
                SHOP_ADMIN_URL . '/index.php?editproduct&tab=variants&id=' . $prod_id,
                array(
                    'style' => 'float:left;margin-right:10px;',
                    'class' => 'uk-button',
                )
            );
        case 'variants':
            $defsort_arr['field'] = 'sku';
            unset($header_arr[3]);
            $header_arr = array_values($header_arr);
            $options = array(
                'chkselect' => true,
                'chkdelete' => true,
                'chkall' => true,
                'chkfield' => 'pv_id',
                'chkname' => 'pv_bulk_id',
                'chkactions' => '<button name="pv_del_bulk" ' .
                    'style="vertical-align:text-bottom;" ' .
                    'class="uk-button uk-button-mini uk-button-danger" ' .
                    'onclick="return confirm(\'' . $LANG01[125] . '\');">' . $LANG_ADMIN['delete'] . '</button>'.
                    '&nbsp;&nbsp;<button name="pv_edit_bulk" ' .
                    'style="vertical-align:text-bottom;" ' .
                    'class="uk-button uk-button-mini uk-button-primary">' .
                    $LANG_SHOP['update'] . '</button>',
            );
            $text_arr = array(
                'has_limit' => true,
                'has_search' => true,
                'form_url' => SHOP_ADMIN_URL . '/index.php?variants=x',
            );
            break;
        default:
            $options = array();
            $text_arr = array(
            );
            break;
        }
        if ($prod_id > 0) {
            $display .= COM_createLink($LANG_SHOP['new_variant'],
                SHOP_ADMIN_URL . '/index.php?pv_edit=0&item_id=' . $prod_id,
                array(
                    'style' => 'float:left;',
                    'class' => 'uk-button uk-button-success',
                )
            );
            if ($view !== 'pv_bulk') {
                $display .= COM_createLink('Bulk Admin',
                    SHOP_ADMIN_URL . '/index.php?pv_bulk=0&item_id=' . $prod_id,
                    array(
                        'style' => 'float:left;margin-left:10px;',
                        'class' => 'uk-button uk-button-primary',
                    )
                );
            }
        }
        $query_arr = array(
            'table' => 'shop.product_variants',
            'query_fields' => array('sku'),
            'sql' => "SELECT * FROM {$_TABLES['shop.product_variants']}",
        );
        if ($prod_id > 0) {
            $query_arr['default_filter'] = "WHERE item_id = '$prod_id'";
        } else {
            $query_arr['default_filter'] = 'WHERE 1=1';
        }
        $filter = NULL;
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_pvlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, $extra, $options, ''
        );
        // Create the "copy "options" form at the bottom
        if ($prod_id == 0) {
            $T = new Template;
            $T->set_file('copy_opt_form', 'copy_options_form.thtml');
            $T->set_var(array(
                //'src_product'       => COM_optionList($_TABLES['shop.products'], 'id, name'),
                'product_select'    => COM_optionList($_TABLES['shop.products'], 'id, name'),
                'cat_select'        => COM_optionList($_TABLES['shop.categories'], 'cat_id,cat_name'),
            ) );
            $display .= $T->parse('output', 'copy_opt_form');
        }
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the attribute list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra information passed from the list function
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra=array())
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        static $POGS = NULL;
        $retval = '';

        if ($POGS === NULL) {
            $POGS = ProductOptionGroup::getAll();
        }
        $Var = self::getInstance($A['pv_id']);

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array(
                    'title' => $LANG_ADMIN['edit'],
                ) ),
                SHOP_ADMIN_URL . "/index.php?pv_edit=x&amp;pv_id={$A['pv_id']}"
            );
            break;

        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = 'checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                    id=\"togenabled{$A['pv_id']}\"
                    onclick='SHOP_toggle(this,\"{$A['pv_id']}\",\"enabled\",".
                    "\"variant\");' />" . LB;
            break;

        case 'orderby':
            if ($fieldvalue > 10) {
                $retval = COM_createLink(
                    Icon::getHTML('arrow-up'),
                    SHOP_ADMIN_URL . '/index.php?pv_move=up&id=' . $A['pv_id']
                );
            } else {
                $retval = '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            if ($fieldvalue < $extra['max_orderby']) {
                $retval .= COM_createLink(
                    Icon::getHTML('arrow-down'),
                    SHOP_ADMIN_URL . '/index.php?pv_move=down&id=' . $A['pv_id']
                );
            } else {
                $retval .= '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            break;

        case 'dscp':
            $Opts = $Var->getOptions();
            $tmp = array();
            foreach ($Opts as $Opt) {
                $tmp[] = $POGS[$Opt->getGroupID()]->getName() . ':' . $Opt->getValue();
            }
            $retval = implode('; ', $tmp);
            break;

        case 'delete':
            $retval .= COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/index.php?pv_del=x&amp;pv_id=' . $A['pv_id'] . '&item_id=' . $A['item_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

        case 'price':
            $retval = \Shop\Currency::getInstance()->FormatValue($fieldvalue);
            break;

        case 'reorder':
        case 'onhand':
            $retval = (float)$fieldvalue;
            if ((float)$A['onhand'] <= (float)$A['reorder']) {
                $retval = '<span class="uk-text-danger">' . $retval . '</span>';
            }
            break;

        case 'def_pv_id':
            $sel = $A['pv_id'] == $extra['def_pv_id'] ? 'checked="checked"' : '';
            $retval = '<input type="radio" name="def_pv_id" value="' . $A['pv_id'] .
                '" ' . $sel . ' />';
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }


    /**
     * Verify that the product is available with the selected options.
     *
     * @param   array   $opts   Array of option value record IDs
     * @return  array       Array of status and message elements.
     */
    public function Validate($opts)
    {
        global $LANG_SHOP;

        if (!is_array($opts)) {
            $opts = array();
        }
        if (!isset($opts['quantity'])) {
            $opts['quantity'] = 1;
        }

        $P = Product::getByID($this->item_id);
        if ($P->getID() < 1 || $this->getID() < 1) {
            $retval = array(
                'status'    => 0,
                'msg'       => $LANG_SHOP['opts_not_avail'],
                'allowed'   =>  false,
                'orig_price' => 0,
                'sale_price' => 0,
                'onhand'    => 0,
                'weight'    => '--',
                'sku'       => '',
                'leadtime'  => '',
                'images'    => array(),
            );
        } else {
            $price = ($P->getBasePrice() + $this->getPrice());
            $price = $price * (100 - $P->getDiscount($opts['quantity'])) / 100;
            if ($this->onhand == 0) {
                $lt_msg = $P->getLeadTimeMessage();
            } else {
                $lt_msg = '';
            }
            $retval = array(
                'status'    => 0,
                'msg'       => $this->onhand . ' ' . $LANG_SHOP['available'],
                'allowed'   => true,
                'is_oos'    => false,
                'orig_price' => Currency::getInstance()->RoundVal($price),
                'sale_price' => Currency::getInstance()->RoundVal($P->getSalePrice($price)),
                'onhand'    => $this->onhand,
                'weight'    => $P->getWeight() + $this->weight,
                'sku'       => empty($this->getSku()) ? $P->getName() : $this->getSku(),
                'leadtime'  => $lt_msg,
                'images'    => $this->images,
            );
        }
        if ($P->getTrackOnhand()) {
            if ($this->onhand < $opts['quantity']) {
                $retval['is_oos'] = true;
                if ($P->getOversell() > Product::OVERSELL_ALLOW) {
                    // Can't be sold
                    $retval['status'] = 2;
                    $retval['msg'] = 'Not Available';
                    $retval['allowed'] = false;
                } else {
                    $retval['status'] = 1;
                    $retval['msg'] = 'Backordered';
                }
            }
        }
        return $retval;
    }


    /**
     * Create a cartesian product of arrays to map option combinations.
     * Used during creation to create the variants from the select-multiple
     * options.
     * Thanks to Sergiy Sokolenko
     * @link https://stackoverflow.com/a/15973172
     *
     * @param   array   $input  Array of arrays
     * @return  array   Array of cartesian products
     */
    private static function _cartesian($input)
    {
        $result = array(array());

        foreach ($input as $key => $values) {
            $append = array();
            foreach($result as $product) {
                foreach($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }
            $result = $append;
        }
        return $result;
    }


    /**
     * Toggles the Enabled field.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        $newval = self::_toggle($oldvalue, 'enabled', $id);
        if ($newval != $oldvalue) {
            Cache::clear('products');
            Cache::clear(self::TAGS);
        }
        return $newval;
    }


    /**
     * Delete the variants related to a specific product.
     * Called when deleting the product.
     *
     * @param   integer $item_id    Product ID
     */
    public static function deleteByProduct($item_id)
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        $sql = "SELECT pv_id FROM {$_TABLES['shop.product_variants']}
            WHERE item_id = $item_id";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            DB_delete($_TABLES['shop.variantXopt'], 'pv_id', (int)$A['pv_id']);
        }
        DB_delete($_TABLES['shop.product_variants'], 'item_id', $item_id);
    }


    /**
     * Clone a product's variants to another product.
     * All fields are duplicated except the SKU which is created from the item
     * and option values.
     *
     * @param   integer $src    Source product ID
     * @param   integer $dst    Destination product ID
     * @param   boolean $del_existing   True to remove existing values in dst
     */
    public static function cloneProduct($src, $dst, $del_existing=false)
    {
        global $_TABLES;

        $src = (int)$src;
        $dst = (int)$dst;
        $P = Product::getById($dst);
        if ($P->getID() == 0) {
            // Invalid target product specified
            return;
        }
        if ($del_existing) {
            self::deleteByProduct($dst);
        }
        $PVs = self::getByProduct($src);
        foreach ($PVs as $PV) { 
            $PV->loadOptions();
            $sku_parts = array();
            foreach($PV->getOptions() as $pog=>$Opt) {
                if ($Opt->getSku() != '') {
                    $sku_parts[] = $Opt->getSku();
                }
            }
            if (!empty($sku_parts) && !empty($P->getName())) {
                $sku = DB_escapeString($P->getName() . '-' . implode('-', $sku_parts));
            } else {
                $sku = '';
            }
            $sql = "INSERT INTO {$_TABLES['shop.product_variants']} (
                    item_id, sku, price, weight, shipping_units, onhand,
                    reorder, enabled, supplier_ref, img_ids
                )
                SELECT $dst, '$sku', price, weight, shipping_units, onhand, reorder, enabled, supplier_ref, img_ids
                FROM {$_TABLES['shop.product_variants']}
                WHERE pv_id = {$PV->getID()}";
            DB_query($sql);
            $pv_id = DB_insertID();
            $sql = "INSERT INTO {$_TABLES['shop.variantXopt']} (pv_id, pov_id)
                SELECT $pv_id, pov_id FROM {$_TABLES['shop.variantXopt']} WHERE pv_id = {$PV->getID()}";
            DB_query($sql);
        }
        Cache::clear('products');
        Cache::clear('options');
    }

    /**
     * Update the SKU value when the product SKU has changed.
     * Only changes the SKU if the first part is the product sku. No action
     * is taken if the variant SKU has been modified by the admin.
     *
     * @param   string  $old_sku    Original product SKU
     * @param   string  $new_sku    New product SKU
     */
    public function updateSKU($old_sku, $new_sku)
    {
        $len = strlen($old_sku);
        if (substr($this->getSku(), 0, $len) == $old_sku) {
            $var_sku = substr($this->getSku(), $len);
            $this->setSku($new_sku . $var_sku)
                ->Save();
        }
    }

}

?>
