<?php
/**
 * Class to manage product variants.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.1
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Shop\Models\Stock;
use Shop\Models\ProductVariantInfo;
use Shop\Models\DataArray;
use Shop\Util\JSON;


/**
 * Class for product variants.
 * Variants are combinations of options represented by a single sku, such as
 * color, size and style.
 * @package shop
 */
class ProductVariant
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Table name, for DBO operations.
     * @var string */
    public static $TABLE = 'shop.product_variants';

    /** Key field name, for DBO operations.
     * @var string */
    protected static $F_ID = 'pv_id';

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

    /** Flag to indicate whether quantity onhand is tracked.
     * This is just a holder for the product's setting.
     * @var boolean */
    private $track_onhand = 0;

    /** Quantity on hand.
     * @var integer */
    //private $onhand = 0;

    /** Quantity on hand (from Stock table).
     * @var integer */
    //private $qty_onhand = 0;

    /** Quantity reserved in carts (from Stock table).
     * @var integer */
    //private $qty_reserved = 0;

    /** Reorder quantity. Overrides the product reorder setting.
     * @var integer */
    //private $reorder = 0;

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

    /** Stock level object.
     * @var object */
    private $Stock = NULL;


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
            if (isset($pv_id['stk_id'])) {
                $this->Stock = Stock::createFromArray($pv_id);
            } else {
                $this->Stock = new Stock($this->item_id, $this->pv_id);
            }
            $this->setVars(new DataArray($pv_id));
        }
        if (!is_object($this->Stock)) {
            // Create a Stock object for later use, if not created above
            // or in Read()
            $this->Stock = new Stock;
        }
    }


    /**
     * Create a ProductVariant object from an array, e.g. database record.
     *
     * @param   array   $A      Array of properties
     * @return  object      New ProductVariant object
     */
    public static function createFromArray(array $A) : self
    {
        $retval = new self;
        $retval->setVars(new DataArray($A));
        return $retval;
    }


    /**
     * Get an instance of a specific variant record.
     *
     * @param   integer $pv_id  Record ID to retrieve
     * @return  object      ProductVariant object
     */
    public static function getInstance(int $pv_id) : self
    {
        static $items = array();
        if (!array_key_exists($pv_id, $items)) {
            $items[$pv_id] = new self($pv_id);
        }
        return $items[$pv_id];
    }


    /**
    * Load the item information.
    *
    * @param    integer $rec_id     DB record ID of item
    * @return   boolean     True on success, False on failure
    */
    public function Read(int $rec_id) : bool
    {
        global $_SHOP_CONF, $_TABLES;

        $qb = Database::getInstance()->conn->createQueryBuilder();
        try {
            $row = $qb->select('pv.*', 'stk.*', 'p.track_onhand')
               ->from($_TABLES['shop.product_variants'], 'pv')
               ->leftJoin('pv', $_TABLES['shop.stock'], 'stk', 'stk.stk_item_id = pv.item_id AND stk.stk_pv_id = pv.pv_id')
               ->leftJoin('pv', $_TABLES['shop.products'], 'p', 'p.id = pv.item_id')
               ->where('pv.pv_id = :pv_id')
               ->setParameter('pv_id', $rec_id, Database::INTEGER)
               ->execute()
               ->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $this->setVars(new DataArray($row));
            $this->loadOptions();
            $this->makeDscp();
            $this->Stock = Stock::createFromArray($row);
            return true;
        } else {
            // Create a dummy Stock object to avoid errors with NULL.
            $this->Stock = new Stock;
            return false;
        }
    }


    /**
     * Set the object variables from an array.
     *
     * @param   DataArray   $A  Array of values
     * @param   boolean $fromDB True if reading from DB, false if from form
     * @return  boolean     True on success, False if $A is not an array
     */
    public function setVars(DataArray $A, $fromDB=true)
    {
        $pfx = $fromDB ? '' : 'pv_';
        $this
            ->setId($A->getInt('pv_id'))
            ->setItemId($A->getString($pfx.'item_id'))
            ->setPrice($A->getFloat($pfx.'price'))
            ->setWeight($A->getFloat($pfx.'weight'))
            ->setShippingUnits($A->getFloat($pfx.'shipping_units'))
            ->setSku($A->getString($pfx.'sku'))
            ->setSupplierRef($A->getString($pfx.'supplier_ref'))
            ->setTrackOnhand($A->getInt($pfx.'track_onhand'))
            ->setImageIDs($A->get($pfx.'img_ids'))
            ->setEnabled($A->getInt($pfx.'enabled', 1));
        if (isset($A['dscp'])) {        // won't be set from the edit form
            $this->setDscp($A->getString('dscp'));
        }
        if (isset($A['stk_id'])) {
            $this->Stock = Stock::createFromArray($A->toArray());
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
    public static function getByAttributes(int $item_id, array $attribs) : self
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        if ($item_id < 1 || !is_array($attribs) || empty($attribs)) {
            return new self;
        }
        $count = count($attribs);
        $qb = Database::getInstance()->conn->createQueryBuilder();
        $qb->select('pv.*', 'stk.*')
           ->from($_TABLES['shop.variantXopt'], 'vxo')
           ->innerJoin('vxo', $_TABLES['shop.product_variants'], 'pv', 'vxo.pv_id = pv.pv_id')
           ->leftJoin('pv', $_TABLES['shop.stock'], 'stk', 'stk.stk_pv_id = pv.pv_id')
           ->where('vxo.pov_id IN (:attribs)')
           ->andWhere('pv.item_id = :item_id')
           ->groupBy('vxo.pv_id')
           ->having('COUNT(pv.item_id) = :count')
           ->setParameter('attribs', $attribs, Database::PARAM_INT_ARRAY)
           ->setParameter('item_id', $item_id, Database::INTEGER)
           ->setParameter('count', count($attribs), Database::INTEGER);
        try {
            $row = $qb->execute()->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $retval = self::createFromArray($row);
        } else {
            // Make sure a valid object is returned.
            $retval = new self;
        }
        return $retval;
    }


    /**
     * Get the internal Options property.
     *
     * @return  array   Array of ProductOptionValue objects
     */
    public function getOptions() : array
    {
        if ($this->Options === NULL) {
            $this->loadOptions();
        }
        return $this->Options;
    }


    /**
     * Load the product attributes into the options array.
     *
     * @access  public  To be called during upgrade
     * @return  object  $this
     */
    public function loadOptions() : self
    {
        global $_TABLES;

        if ($this->Options === NULL) {
            $cache_key = 'pog_options_' . $this->getID();
            $this->Options = Cache::get($cache_key);
            if ($this->Options === NULL) {
                $this->Options = array();
                try {
                    $qb = Database::getInstance()->conn->createQueryBuilder();
                    $stmt = $qb->select('pov.*', 'pog.pog_name')
                               ->from($_TABLES['shop.prod_opt_vals'], 'pov')
                               ->innerJoin('pov', $_TABLES['shop.variantXopt'], 'vxo', 'vxo.pov_id = pov.pov_id')
                               ->innerJoin('pov', $_TABLES['shop.prod_opt_grps'], 'pog', 'pog.pog_id = pov.pog_id')
                               ->where('vxo.pv_id = :pv_id')
                               ->orderBy('pog.pog_orderby', 'ASC')
                               ->setParameter('pv_id', $this->getID(), Database::INTEGER)
                               ->execute();
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                    $stmt = false;
                }
                if ($stmt) {
                    while ($A = $stmt->fetchAssociative()) {
                        $this->Options[$A['pog_name']] = ProductOptionValue::fromArray($A);
                    }
                }
                Cache::set(
                    $cache_key,
                    $this->Options,
                    array(Product::$TABLE, ProductOptionValue::$TABLE, $this->getID())
                );
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
     * @param   float   $qty    Number of units on hand
     * @return  object  $this
     */
    public function XsetOnhand($qty)
    {
        if (is_object($this->Stock)) {
            $this->Stock->withOnhand($qty);
        }
        //$this->qty_onhand = (float)$qty;
        return $this;
    }


    /**
     * Get the quantity on hand for this variant.
     *
     * @return  float       Quantity onhand
     */
    public function getOnhand()
    {
        if (is_object($this->Stock)) {
            return $this->Stock->getOnhand();
        } else {
            return 0;
        }
        //return (float)$this->qty_onhand;
    }


    /**
     * Set the quantity reserved.
     *
     * @param   float   $qty    Quantity reserved.
     * @return  object  $this
     */
    public function XsetReserved($qty)
    {
        if (is_object($this->Stock)) {
            $this->Stock->withReserved($qty);
        }
        //$this->qty_reserved = (float)$qty;
        return $this;
    }


    /**
     * Get the quantity on hand for this variant.
     *
     * @return  float       Quantity onhand
     */
    public function getReserved()
    {
        if (is_object($this->Stock)) {
            return $this->Stock->getReserved();
        } else {
            return 0;
        }
    }


    /**
     * Set the reorder quantity.
     *
     * @param   float   $reorder    Reorder quantity
     * @return  object  $this
     */
    public function XsetReorder($reorder)
    {
        if (is_object($this->Stock)) {
            $this->Stock->withReorder($reorder);
        }
        //$this->reorder = (float)$reorder;
        return $this;
    }


    /**
     * Get the quantity on hand for this variant.
     *
     * @return  float       Quantity onhand
     */
    public function getReorder()
    {
        if (is_object($this->Stock)) {
            return $this->Stock->getReorder();
        } else {
            return 0;
        }
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
    public static function getByProduct(int $product_id) : array
    {
        global $_TABLES;
        $retval = array();
        try {
            $stmt = Database::getInstance()->conn->executeQuery(
                "SELECT pv.*, stk.*
                FROM {$_TABLES['shop.product_variants']} pv
                LEFT JOIN {$_TABLES['shop.stock']} stk
                    ON stk.stk_item_id = pv.item_id AND stk.stk_pv_id = pv.pv_id
                WHERE pv.item_id = ? AND pv.enabled = 1",
                array($product_id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                $retval[] = self::createFromArray($A);
            }
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
            if ($Grp->getType() == 'checkbox') {
                continue;
            }
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

        /*if ($this->pv_id == 0) {
            $this->setReorder(Product::getInstance($this->getItemId())->getReorder());
            $this->setOnhand(Product::getInstance($this->getItemId())->getOnhand());
            $this->setTrackOnhand(Product::getInstance($this->getItemId())->getTrackOnhand());
        }*/
        $Product = Product::getByID($this->item_id);
        $T->set_var(array(
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('variant_form', $_CONF['language']),
            'title'         => $this->pv_id == 0 ? $LANG_SHOP['new_variant'] : $LANG_SHOP['edit_variant'],
            'ena_chk'       => $this->enabled == 0 ? '' : 'checked="checked"',
            //'trk_onhand_chk' => $this->track_onhand ? 'checked="checked"' : '',
            'track_onhand'  => $this->getTrackOnhand(),
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
            if ($Grp->getType() == 'checkbox') {
                continue;
            }
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

        // Get the product ID from the first variant, needed to get the
        // available images.
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
    public static function BulkUpdateDo(DataArray $A) : bool
    {
        global $_TABLES;

        $sql_vals  = array();
        $params = array();
        $values = array();
        $types = array();

        if (isset($A['price']) && $A['price'] !== '') {
            $params[] = 'price = ?';
            $values[] = (float)$A['price'];
            $types[] = Database::STRING;
        }
        if (isset($A['weight']) && $A['weight'] !== '') {
            $params[] = 'weight = ?';
            $values[] = (float)$A['weight'];
            $types[] = Database::STRING;
        }
        if (isset($A['shipping_units']) && $A['shipping_units'] !== '') {
            $params[] = 'shipping_units = ?';
            $values[] = (float)$A['shipping_units'];
            $types[] = Database::STRING;
        }
        if (isset($A['onhand']) && $A['onhand'] !== '') {
            $params[] = 'onhand = ?';
            $values[] = (float)$A['onhand'];
            $types[] = Database::STRING;
        }
        if (isset($A['reorder']) && $A['reorder'] !== '') {
            $params[] = 'reorder = ?';
            $values[] = (float)$A['reorder'];
            $types[] = Database::STRING;
        }
        if (isset($A['enabled']) && $A['enabled'] > -1) {
            $params[] = 'enabled = ?';
            $values[] = $A['enabled'] ? 1 : 0;
            $types[] = Database::INTEGER;
        }
        if (!isset($A['img_noupdate'])) {
            // no-update checkbox is unchecked for images
            if (isset($A['pv_img_ids'])) {
                $params[] = 'img_ids = ?';
                $values[] = implode(',', $A['pv_img_ids']);
                $types[] = Database::STRING;
            } else {
                // No images selected, implies all images
                $params[] = "img_ids = ''";
            }
        }
        if (!empty($params)) {
            $sql_vals = implode(', ', $params);
            $types[] = Database::STRING;    // for pv_ids
            try {
                Database::getInstance()->conn->executeStatement(
                    "UPDATE {$_TABLES['shop.product_variants']} SET $sql_vals WHERE pv_id IN (?)",
                    array($params),
                    array($A['pv_ids']),
                    array($types)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }
        Cache::clear(Product::$TABLE);
        return true;
    }


    /**
     * Save a new product variant.
     * The `item_id` field needs to be set in the array parameter.
     *
     * @param   DataArray   $A  Form values
     * @return  boolean     True on success, False on failure
     */
    public static function saveNew(DataArray $A) : bool
    {
        global $_TABLES;

        // Clean out any zero (not selected) options for groups
        foreach ($A->getArray('pv_groups') as $id=>&$grp) {
            foreach ($grp as $gid=>$val) {
                if ($val == 0) {
                    unset($grp[$gid]);
                }
            }
            if (empty($grp)) {
                unset($A['pv_groups'][$id]);
            }
        }
        $pv_groups = $A->getArray('pv_groups');

        $item_id = $A->getInt('pv_item_id');
        if ($item_id < 1 || empty($pv_groups)) {
            return false;
        }
        $P = Product::getById($item_id);
        if ($P->getID() == 0) {
            return false;   // item ID provided but invalid
        }

        $price = 0;
        $weight = $A->getFloat('weight');
        $shipping_units = $A->getFloat('shipping_units');
        $matrix = self::_cartesian($pv_groups);
        foreach ($matrix as $groups) {
            $pv_price = $A->getFloat('pv_price', NULL);
            $opt_ids = array();
            $sku_parts = array();
            $dscp = array();
            foreach($groups as $pog=>$pov_id) {
                if ($pov_id == 0) {
                    continue;
                }
                $opt_ids[] = $pov_id;   // save for the variant->opt table
                $Opt = new ProductOptionValue($pov_id);
                if (is_null($pv_price)) {   // Zero is valid
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

            $sku = '';
            if (empty($A['pv_sku'])) {
                if (!empty($sku_parts) && !empty($P->getName())) {
                    $sku = $P->getName() . '-' . implode('-', $sku_parts);
                }
            } else {
                $sku = $A['pv_sku'];
            }

            // Use the product stock values as defalts, don't set reserved.
            $onhand = $P->getOnhand();
            $reorder = $P->getReorder();

            if ($A['pv_supplier_ref'] === '') {
                $sup_ref = $P->getSupplierRef();
            } else {
                $sup_ref = $A['pv_supplier_ref'];
            }

            $db = Database::getInstance();
            try {
                $db->conn->insert(
                    $_TABLES['shop.product_variants'],
                    array(
                        'item_id' => $item_id,
                        'sku' => $sku,
                        'supplier_ref' => $sup_ref,
                        'price' => (float)$price,
                        'weight' => (float)$weight,
                        'shipping_units' => (float)$shipping_units,
                        'dscp' => JSON::encode($dscp),
                    ),
                    array(
                        Database::INTEGER,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                    )
                );
                $pv_id = $db->conn->lastInsertId();
                foreach ($opt_ids as $opt_id) {
                    $vals[] = '(' . $pv_id . ',' . $opt_id . ')';
                }
                Stock::getByItem($A['pv_item_id'], $pv_id)
                    ->withOnhand($onhand)
                    ->withReorder($reorder)
                    ->withReserved(0)
                    ->Save();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
        }
        if (!empty($vals)) {
            $sql_vals = implode(',', $vals);
            $sql = "INSERT IGNORE INTO {$_TABLES['shop.variantXopt']}
                (pv_id, pov_id) VALUES $sql_vals";
            DB_query($sql);
        }
        self::reOrder($item_id);
        Cache::clear(self::$TABLE);
        return true;
    }


    /**
     * Save a variant to the database.
     *
     * @param   DataArray   $A  Optional array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save(?DataArray $A= NULL) : bool
    {
        global $_TABLES;

        if (!empty($A)) {
            $this->setVars($A, false);
        }

        // Create the variant SKU from product & options if empty.
        if (empty($this->sku) && !empty($A->getInt('pv_item_id'))) {
            $P = Product::getInstance($A->getInt('pv_item_id'));
            $this->sku = $P->getName();
            foreach ($this->getOptions() as $PVO) {
                $pvo_sku = $PVO->getSku();
                if (!empty($pvo_sku)) {
                    $this->sku .= '-' . $pvo_sku;
                }
            }
        }

        if ($A->getString('price') === '') {
            // Empty string, not "0", to calculate price from the options.
            $this->price = 0;
            foreach ($this->getOptions() as $PVO) {
                $this->price += $PVO->getPrice();
            }
        }

        $values = array(
            'item_id' => $this->item_id,
            'sku' => $this->sku,
            'supplier_ref' => $this->supplier_ref,
            'price' => $this->price,
            'weight' => $this->weight,
            'shipping_units' => $this->shipping_units,
            'img_ids' => implode(',', $this->images),
            'dscp' => JSON::encode($this->dscp),
        );
        $types = array(
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
        );
        $db = Database::getInstance();
        try {
            if ($this->pv_id == 0) {
               if (isset($A['pv_groups'])) {
                    return self::saveNew($A);
               } else {
                    $db->conn->insert(
                        $_TABLES['shop.product_variants'],
                        $values,
                        $types
                    );
                    $this->pv_id = $db->conn->lastInsertId();
                }
            } else {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.product_variants'],
                    $values,
                    array('pv_id' => $this->pv_id),
                    $types
                );
            }
            Stock::getByItem($this->item_id, $this->pv_id)
                ->withOnhand($this->getOnhand())
                ->withReserved($this->getReserved())
                ->Save();
            $retval = true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $retval = false;
        }

        // Create two standardized arrays to detect new and removed option vals.
        // Only if submitted from a form, where the groups variable is present.
        if (isset($A['pv_groups'])) {
            $old_opts = array();
            $new_opts = array();
            foreach ($this->getOptions() as $Opt) {
                $old_opts[] = $Opt->getID();
            }
            foreach ($A['pv_groups'] as $opt) {
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
                try {
                    $db->conn->executeStatement($sql);
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                }
            }
            if (!empty($removed)) {
                try {
                    $db->conn->executeStatement(
                        "DELETE FROM {$_TABLES['shop.variantXopt']}
                        WHERE pv_id = ? AND pov_id IN (?)",
                        array($this->getID(), $removed),
                        array(Database::INTEGER, Database::PARAM_INT_ARRAY)
                    );
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                }
            }
        }
        Cache::clear(self::$TABLE);
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
        Stock::deleteByVariant($id);
        Cache::clear(self::$TABLE);
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
    public function setImageIDs($ids=NULL) : self
    {
        if ($ids === NULL) {
            $ids = array();
        } elseif (is_string($ids)) {
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


    public function getStock()
    {
        return $this->Stock;
    }


    /**
     * Reorder all attribute items with the same product ID and attribute name.
     */
    public static function reOrder(int $item_id) : void
    {
        global $_TABLES;

        $sql = "SELECT pv_id, orderby
                FROM {$_TABLES['shop.product_variants']}
                WHERE item_id= '{$item_id}'
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
            Cache::clear(self::$TABLE);
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
            self::reOrder($this->item_id);
        }
    }


    /**
     * Product Variant List View.
     *
     * @param   integer $prod_id    Optional product ID to limit listing
     * @return  string      HTML for the attribute list.
     */
    public static function adminList(?int $prod_id=NULL) : string
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM, $LANG01;

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
                'field' => 'qty_onhand',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_SHOP['reserved'],
                'field' => 'qty_reserved',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_SHOP['reorder'],
                'field' => 'qty_reorder',
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
            $extra['prod_id'] = $prod_id;
        } else {
            $extra['prod_id'] = 0;
        }

        $defsort_arr = array(
            'field' => 'orderby',
            'direction' => 'ASC',
        );
        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $view = SESS_getVar('shop.pv_view');
        switch ($view) {
        case 'pv_bulk':
            $display .= FieldList::buttonLink(array(
                'text' => FieldList::left(),
                'style' => 'primary',
                'url' => SHOP_ADMIN_URL . '/index.php?editproduct&tab=variants&id=' . $prod_id,
            ) );
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
                'chkactions' => FieldList::button(array(
                    'name' => 'pv_del_bulk',
                    'style' => 'danger',
                    'size' => 'mini',
                    'onclick' => "return confirm('{$LANG01[125]}');",
                    'text' => $LANG_ADMIN['delete'],
                ) ) . FieldList::button(array(
                    'name' => 'pv_edit_bulk',
                    'style' => 'primary',
                    'size' => 'mini',
                    'text' => $LANG_SHOP['update'],
                ) ),
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
            $display .= FieldList::buttonLink(array(
                'text' => $LANG_SHOP['new_variant'],
                'url' => SHOP_ADMIN_URL . '/index.php?pv_edit=0&item_id=' . $prod_id,
                'style' => 'success',
            ) );
            if ($view !== 'pv_bulk') {
                $display .= FieldList::buttonLink(array(
                    'text' => 'Bulk Admin',
                    'url' => SHOP_ADMIN_URL . '/index.php?pv_bulk=0&item_id=' . $prod_id,
                    'style' => 'primary',
                ) );
            }
        }
        $sql = "SELECT pv.*, stk.qty_onhand, stk.qty_reserved, stk.qty_reorder
            FROM {$_TABLES['shop.product_variants']} pv
            LEFT JOIN {$_TABLES['shop.stock']} stk
                ON stk.stk_item_id = pv.item_id AND stk.stk_pv_id = pv.pv_id";
        $query_arr = array(
            'table' => 'shop.product_variants',
            'query_fields' => array('sku'),
            'sql' => $sql,
        );

        if ($prod_id > 0) {
            $query_arr['default_filter'] = "WHERE pv.item_id = '$prod_id'";
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
            $retval .= FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . "/index.php?pv_edit=x&amp;pv_id={$A['pv_id']}",
            ) );
            break;

        case 'enabled':
            $retval = FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['pv_id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['pv_id']}','enabled','variant');",
            ) );
            break;

        case 'orderby':
            if ($fieldvalue > 10) {
                $retval = FieldList::up(array(
                    'url' => SHOP_ADMIN_URL . '/index.php?pv_move=up&id=' . $A['pv_id'],
                ) );
            } else {
                $retval = FieldList::space();
            }
            if ($fieldvalue < $extra['max_orderby']) {
                $retval .= FieldList::down(array(
                    'url' => SHOP_ADMIN_URL . '/index.php?pv_move=down&id=' . $A['pv_id'],
                ) );
            } else {
                $retval .= FieldList::space() . '&nbsp;';
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
            $retval .= FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL. '/index.php?pv_del=x&amp;pv_id=' . $A['pv_id'] . '&item_id=' . $A['item_id'],
                'attr' => array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                ),
            ) );
            break;

        case 'price':
            $retval = \Shop\Currency::getInstance()->FormatValue($fieldvalue);
            break;

        case 'onhand':
            $retval = (float)$fieldvalue;
            if ((float)$A['onhand'] <= (float)$A['reorder']) {
                $retval = '<span class="uk-text-danger">' . $retval . '</span>';
            }
            break;

        case 'qty_reorder':
        case 'qty_onhand':
        case 'qty_reserved':
            if ($extra['prod_id'] > 0) {
                // For a specific product, values may be saved with the product.
                $retval = FieldList::text(array(
                    'name' => 'quantities[' . $A['pv_id'] . '][' . $fieldname . ']',
                    'value' => (float)$fieldvalue,
                    'style' => 'text-align:right;width:50px;',
                ) );
            } else {
                // Showing all product variants, no editing from the list.
                $retval = '<span style="align:right;">' . $fieldvalue . '</span>';
            }
            break;

        case 'def_pv_id':
            $sel = $A['pv_id'] == $extra['def_pv_id'] ? 'checked="checked"' : '';
            $retval = FieldList::radio(array(
                'name' => 'def_pv_id',
                'value' => $A['pv_id'],
                'checked' => $A['pv_id'] == $extra['def_pv_id'],
            ) );
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
    public function Validate(ProductVariantInfo &$PVI, array $opts) : void
    {
        global $LANG_SHOP;

        if (!is_array($opts)) {
            $opts = array();
        }
        if (!isset($opts['quantity'])) {
            $opts['quantity'] = 1;
        }
        if (isset($opts['checkbox'])) {
            $incr_price = ProductCheckbox::getPriceImpact($this->id, $opts['checkbox']);
        } else {
            $incr_price = 0;
        }

        $P = Product::getByID($this->item_id);
        if ($P->getID() < 1 || $this->getID() < 1) {
            /*$retval = array(
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
            );*/
        } else {
            $price = ($P->getBasePrice() + $this->getPrice() + $incr_price);
            $price = $price * (100 - $P->getDiscount($opts['quantity'])) / 100;
            if ($this->Stock->getOnhand() == 0) {
                $lt_msg = $P->getLeadTimeMessage();
            } else {
                $lt_msg = '';
            }
            $PVI['status'] = 0;
            $PVI['msg'] = $this->Stock->getOnhand() . ' ' . $LANG_SHOP['available'];
            $PVI['allowed'] = true;
            $PVI['is_oos'] = false;
            $PVI['orig_price'] = Currency::getInstance()->RoundVal($price);
            $PVI['sale_price'] = Currency::getInstance()->RoundVal($P->getSalePrice($price));
            $PVI['onhand'] = $this->Stock->getOnhand();
            $PVI['weight'] = $P->getWeight() + $this->weight;
            $PVI['sku'] = empty($this->getSku()) ? $P->getName() : $this->getSku();
            $PVI['leadtime'] = $lt_msg;
            $PVI['images'] = $this->images;
        }
        if ($P->getTrackOnhand()) {
            if ($this->Stock->getOnhand() < $opts['quantity']) {
                $PVI['is_oos'] = true;
                if ($P->getOversell() > Product::OVERSELL_ALLOW) {
                    // Can't be sold
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
            Cache::clear(Product::$TABLE);
            Cache::clear(self::$TABLE);
        }
        return $newval;
    }


    /**
     * Delete all the variants related to a specific product.
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
            self::Delete($A['pv_id']);
        }
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
        Cache::clear(Product::$TABLE);
        Cache::clear(self::$TABLE);
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


    /**
     * Get all the options that make up an item variant.
     *
     * @param   integer $item_id    Product ID
     * @return  array       Array of option groups and values
     */
    public static function getOptionsByProduct(int $item_id) : array
    {
        global $_TABLES;

        $retval = array();
        $qb = Database::getInstance()->conn->createQueryBuilder();
        $qb->select('pog.pog_id', 'pog.pog_name', 'pog.pog_type')
           ->distinct()
           ->from($_TABLES['shop.product_variants'], 'pv')
           ->leftJoin('pv', $_TABLES['shop.variantXopt'], 'vxo', 'pv.pv_id = vxo.pv_id')
           ->leftJoin('vxo', $_TABLES['shop.prod_opt_vals'], 'pov', 'vxo.pov_id = pov.pov_id')
           ->leftJoin('pov', $_TABLES['shop.prod_opt_grps'], 'pog', 'pov.pog_id = pog.pog_id')
           ->where('pv.item_id = :item_id')
           ->setParameter('item_id', $item_id, Database::INTEGER);
        try {
            $stmt = $qb->execute();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($row = $stmt->fetchAssociative()) {
                $retval[] = new DataArray($row);
            }
        }
        return $retval;
    }

}
