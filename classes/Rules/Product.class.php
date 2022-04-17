<?php
/**
 * Class to manage product rules, such as hazardous materials.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Rules;
use Shop\Product as ProductClass;
use Shop\Category;
use Shop\Shipper;
use Shop\Cache;
use Shop\FieldList;
use Shop\Template;


/**
 * Class for product rules.
 * @package shop
 */
class Product
{
    /** Class record ID.
     * @var integer */
    private $id = 0;

    /** Class short name.
     * @var string */
    private $name = '';

    /** Class description.
     * @var string */
    private $dscp = '';

    /** Is the product hazardous material?
     * @var boolean */
    private $is_hazmat = 0;

    /** Eligible shippers that can handle the product.
     * Empty array indicates that all shippers can take the product.
     * @var array */
    private $shipper_ids = array();

    /** Cache tag used for ProductOptions.
     * @var string */
    private const TAG = 'productrules';


    /**
     * Constructor.
     * Initializes the variant variables
     *
     * @param   integer $pr_id  Variant record ID
     * @uses    self::Load()
     */
    public function __construct(?int $pr_id = NULL)
    {
        if ($pr_id != NULL) {
            $status = $this->Read($pr_id);
            if (!$status) {
                $this->id = 0;
            }
        }
    }


    /**
     * Get all the rules that relate to a category, include inherited.
     *
     * @param   object  $Cat    Category object
     * @return  array       Array of product rule objects
     */
    public static function getByCategory(Category $baseCat) : array
    {
        $cache_key = 'productrules_cat_' . $baseCat->getId();
        $retval = Cache::get($cache_key);
        if ($retval !== NULL) {
            return $retval;
        }
        $retval = array();
        if ($baseCat->getProductRuleId() > 0) {
            $retval[$baseCat->getProductRuleId()] = new self($baseCat->getProductRuleId());
        }
        $Cats = $baseCat->getParentTree();
        if (count($Cats) > 0) {
            foreach ($Cats as $Cat) {
                if ($Cat->getProductRuleId() > 0) {
                    $retval[$Cat->getProductRuleId()] = new self($Cat->getProductRuleId());
                }
            }
        }
        Cache::set($cache_key, $retval, array(self::TAG, 'categories'));
        return $retval;
    }

    /**
     * Get all the product classes that relate to a product.
     * Include those assigned to the product and any inherited category.
     *
     * @param   object  $Product    Product object
     * @return  array       Array of ProductClass objects
     */
    public static function getByProduct(ProductClass $Product) : array
    {
        $cache_key = 'productrules_prod_' . $Product->getId();
        $retval = Cache::get($cache_key);
        if ($retval !== NULL) {
            return $retval;
        }
        $retval = array();
        if ($Product->getProductRuleId() > 0) {
            $retval[$Product->getProductRuleId()] = new self($Product->getProductRuleId());
        }
        foreach ($Product->getCategories() as $Cat) {
            $Cats = $Cat->getParentTree();
            if (count($Cats) > 0) {
                foreach ($Cats as $Cat) {
                    if ($Cat->getProductRuleId() > 0) {
                        $retval[$Product->getProductRuleId()] = new self($Cat->getProductRuleId());
                    }
                }
            }
        }
        Cache::set($cache_key, $retval, array(self::TAG, 'products'));
        return $retval;
    }

        
    /**
     * Get the final effective ruleset for a product.
     * If the product has a rule specified, then that is the final class.
     * Otherwise, if the categories have rules they will be merged together
     * to create the most restrictive combination.
     *
     * @param   object  $Product    Product to get
     * @return  object      Product Rule object
     */
    public static function getEffectiveProduct(ProductClass $Product) : self
    {
        //$cache_key = 'eff_prodclass_' . $Product->getId();
        //$retval = Cache::get($cache_key);
        //if ($retval === NULL) {
            if ($Product->getProductRuleId() > 0) {
                $retval = new self($Product->getProductRuleId());
            } else {
                $Cats = Category::getByProductId($Product->getId());
                if (count($Cats) > 0) {
                    $retval = new self;
                    foreach ($Cats as $Cat) {
                        if ($Cat->getProductRuleId() > 0) {
                            $PC = new self($Cat->getProductRuleId());
                            $retval->Merge($PC);
                        }
                    }

                }
            }
            $retval->setDscp('Effective product class for ' . $Product->getName());
            //Cache::set($cache_key, $retval, array(self::TAG));
        //}
        return $retval;
    }


    /**
     * Load the rule information.
     *
     * @param   integer $rec_id     DB record ID of item
     * @return  boolean     True on success, False on failure
     */
    public function Read(int $rec_id) : bool
    {
        global $_SHOP_CONF, $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['shop.product_rules']} pr
            WHERE pr_id = $rec_id";
            //LEFT JOIN {$_TABLES['shop.zone_rules']} zr ON zr.rule_id = pr.pr_zone_rule
        $res = DB_query($sql);
        if ($res && DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $this->setVars($A, true);
            //$this->ZoneRule = new Zon
            return true;
        } else {
            // Create a dummy Stock object to avoid errors with NULL.
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
            $this
                ->setId(SHOP_getVar($A, 'pr_id', 'integer'))
                ->setName(SHOP_getVar($A, 'pr_name', 'string'))
                ->setHazmat(SHOP_getVar($A, 'pr_hazmat', 'int', 0))
                //->setShipperIds(SHOP_getVar($A, 'shipper_ids', 'string'))
                ->setDscp($A['pr_dscp']);
        }
        if ($fromDB) {
            $shipper_ids = json_decode($A['pr_shipper_ids'], true);
        } else {
            $shipper_ids = SHOP_getVar($A, 'pr_shipper_ids', 'array');
        }
        $this->setShipperIds($shipper_ids);
        return $this;
    }


    /**
     * Set the record ID property.
     *
     * @param   integer $pr_id     Record ID
     * @return  object  $this
     */
    public function setId(int $pr_id) : self
    {
        $this->id = (int)$pr_id;
        return $this;
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
     * Set the name field.
     *
     * @param   string  $name   Short name
     * @return  object  $this
     */
    public function setName(string $name) : self
    {
        $this->name = $name;
        return  $this;
    }


    /**
     * Get the short name.
     *
     * @return  string      Short name
     */
    public function getName() : string
    {
        return $this->name;
    }


    /**
     * Set the description field.
     *
     * @param   array|string    $dscp   Descriptive text
     * @return  object      $this
     */
    public function setDscp(string $dscp) : self
    {
        $this->dscp = $dscp;
        return $this;
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
     * Set the flag indicating that this product type is hazardous.
     *
     * @param   integer $val    1 if hazardous, 0 if not
     * @return  object  $this
     */
    public function setHazmat(int $val) : self
    {
        $this->is_hazmat= $val ? 1 : 0;
        return $this;
    }


    /**
     * Check if this product type is hazardous.
     *
     * @return  integer     1 if hazardous, 0 if not
     */
    public function isHazmat() : int
    {
        return $this->is_hazmat;
    }


    /**
     * Set the IDs of the allowed shippers for this rule.
     *
     * @param   array   $ids    Array of ID numbers
     * @return  object  $this
     */
    public function setShipperIds(array $ids) : self
    {
        $this->shipper_ids = $ids;
        return $this;
    }


    /**
     * Get the IDs of shippers allowed by this rule.
     *
     * @return  array       Array of shipper record IDs
     */
    public function getShipperIds() : array
    {
        return $this->shipper_ids;
    }


    /**
     * Creates the edit form for a product class.
     *
     * @return  string      HTML for edit form
     */
    public function Edit() : string
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = new Template('admin');
        $T->set_file(array(
            'form' => 'pr_form.thtml',
            'tips' => '../tooltipster.thtml',
        ) );

        $T->set_var(array(
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('pr_form', $_CONF['language']),
            'title'         => $LANG_SHOP['edit_pr'],
            'pr_id'         => $this->getId(),
            'pr_name'       => $this->getName(),
            'pr_dscp'       => $this->getDscp(),
            'hazmat_chk'    => $this->is_hazmat ? 'checked="checked"' : '',
        ) );
        $T->set_block('form', 'shipper_opts', 'shippers');
        $Shippers = Shipper::getAll(false);
        foreach ($Shippers as $Shipper) {
            $T->set_var(array(
                'shipper_id' => $Shipper->getId(),
                'shipper_name' => $Shipper->getName(),
                'shipper_sel' => in_array($Shipper->getId(), $this->shipper_ids) ? 'selected="selected"' : '',
            ) );
            $T->parse('shippers', 'shipper_opts', true);
        }
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save a variant to the database.
     *
     * @param   array   $A  Optional array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save(?array $A= NULL) : bool
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A, false);
        }

        if ($this->id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['shop.product_rules']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.product_rules']} SET ";
            $sql3 = " WHERE pr_id = '{$this->id}'";
        }
        $sql2 = "pr_name = '" . DB_escapeString($this->name) . "',
            pr_dscp = '" . DB_escapeString($this->dscp) . "',
            pr_shipper_ids = '" . DB_escapeString(json_encode($this->shipper_ids)) . "',
            pr_hazmat = {$this->is_hazmat}";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->id == 0) {
                $this->id = DB_insertID();
            }
            $retval = true;
        } else {
            $retval = false;;
        }
        Cache::clear(self::TAG);
        return $retval;
    }


    /**
     * Delete a product class.
     *
     * @param   integer $id     Variant record ID
     */
    public static function Delete(int $id) : void
    {
        global $_TABLES;

        $id = (int)$id;
        DB_delete($_TABLES['shop.product_rules'], 'pr_id', $id);
        DB_query("UPDATE {$_TABLES['products']} SET prod_class = 0 WHERE prod_class = $id");
        DB_query("UPDATE {$_TABLES['categories']} SET prod_class = 0 WHERE prod_class = $id");
        Cache::clear(self::TAG);
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
     * Merge another product class into this one to restrict further.
     *
     * @param   object  $PC     Product Rule object
     * @return  void
     */
    public function Merge(self $PC) : void
    {
        if ($PC->isHazmat()) {
            $this->is_hazmat= 1;
        }
        if (empty($this->shipper_ids)) {
            $this->shipper_ids = $PC->getShipperIds();
        } elseif (!empty($PC->getShipperIds())) {
            $this->shipper_ids = array_intersect($this->shipper_ids, $PC->getShipperIds());
        }
    }


    /**
     * Admin list view.
     *
     * @return  string      HTML for the attribute list.
     */
    public static function adminList() : string
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM, $LANG01;

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'pr_id',
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['description'],
                'field' => 'pr_name',
            ),
            array(
                'text' => $LANG_SHOP['is_hazmat'],
                'field' => 'pr_hazmat',
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'align' => 'center',
            ),
        );

        $extra = array();
        $defsort_arr = array(
            'field' => 'pr_id',
            'direction' => 'ASC',
        );
        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $options = array(
            'chkselect' => true,
            'chkdelete' => true,
            'chkall' => true,
            'chkfield' => 'pr_id',
            'chkname' => 'pr_del',
        );
        $text_arr = array(
            'has_limit' => true,
            'has_search' => true,
            'form_url' => SHOP_ADMIN_URL . '/rules.php',
        );
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/rules.php?pr_edit=0',
            'style' => 'success',
        ) );
        $query_arr = array(
            'table' => 'shop.product_rules',
            'sql'   => "SELECT * FROM {$_TABLES['shop.product_rules']}",
            'query_fields' => array(
                'pr_dscp',
            ),
            'default_filter' => '',
        );
        $filter = NULL;

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_prlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, $extra, $options, ''
        );
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

        switch($fieldname) {
        case 'edit':
            $retval .= FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . "/rules.php?pr_edit={$A['pr_id']}",
            ) );
            break;

        case 'delete':
            $retval .= FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL. '/rules.php?pr_del=' . $A['pr_id'],
                'attr' => array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                ),
            ) );
            break;

        case 'pr_hazmat':
            if ($fieldvalue) {
                $retval = FieldList::checkmark(array('active' => true));
            }
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Get the options to show when editing products and categories.
     *
     * @param   integer $sel    Optional ID of selected class
     * @return  string      Option elements for a selection
     */
    public static function optionList(?int $sel=NULL) : string
    {
        global $_TABLES;

        $sel = (int)$sel;
        return COM_optionList(
            $_TABLES['shop.product_rules'],
            'pr_id,pr_name',
            $sel,
            1
        );
    }


    /**
     * Clear cache entries related to rules.
     */
    public static function clearCache() : void
    {
        Cache::clear(array(self::TAG, 'products'));
        Cache::clear(array(self::TAG, 'categories'));
    }

}
