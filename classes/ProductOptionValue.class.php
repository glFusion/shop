<?php
/**
 * Class to manage product options.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use Shop\Models\DataArray;


/**
 * Class for product options - color, size, etc.
 * @package shop
 */
class ProductOptionValue
{
    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    private $isNew = true;

    /** Array of error messages, to be accessible by the calling routines.
     * @var array */
    private $Errors = array();

    /** Record ID.
     * @var integer */
    private $pov_id = 0;

    /** Option Group record ID.
     * @var integer */
    private $pog_id = 0;

    /** Option value.
     * @var string */
    private $pov_value = '';

    /** Option price impact.
     * @var float */
    private $pov_price = 0;

    /** Orderby option for selection.
     * @var integer */
    private $orderby = 9999;

    /** SKU component for this option.
     * @var string */
    private $sku = '';


    /**
     * Reads in the specified option, if $id is an integer.
     * If $id is zero, then a new option is being created.
     * If $id is an array, then it is a complete DB record and the properties
     * just need to be set.
     *
     * @param   integer|array   $id Option record or record ID
     */
    public function __construct($id=0)
    {
        if (is_array($id)) {
            // Received a full Option record already read from the DB
            $this->setVars(new DataArray($id));
            $this->isNew = false;
        } else {
            $id = (int)$id;
            if ($id < 1) {
                // New entry, set defaults
                $this->pov_id = 0;
                $this->pog_id = 0;
                $this->pov_value = '';
                $this->pov_price = 0;
                $this->item_id = 0;
                $this->orderby = 9999;
                $this->sku = '';
            } else {
                $this->pov_id =  $id;
                if (!$this->Read()) {
                    $this->pov_id = 0;
                }
            }
        }
    }


    /**
     * Sets all variables to the matching values from $row.
     *
     * @param   array $row Array of values, from DB or $_POST
     */
    public function setVars(DataArray $row) : void
    {
        $this->pov_id = (int)$row['pov_id'];
        $this->pog_id = (int)$row['pog_id'];
        $this->pov_value = $row['pov_value'];
        $this->pov_price = $row['pov_price'];
        $this->orderby = (int)$row['orderby'];
        $this->sku = $row['sku'];
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Option ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read(int $id=0) : bool
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->pov_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return false;
        }

        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.prod_opt_vals']} WHERE pov_id = ?",
                array($id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $this->setVars(new DataArray($row));
            return true;
        } else {
            return false;
        }
    }


    /**
     * Save the current values to the database.
     *
     * @param   array   $A      Array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save(?DataArray $A=NULL) : bool
    {
        global $_TABLES, $_SHOP_CONF;

        $db = Database::getInstance();
        if ($A) {
            if (!isset($A['orderby'])) {
                // Put this field at the end of the line by default.
                $A['orderby'] = 65535;
            } else {
                // Bump the number from the "position after" value.
                $A['orderby'] += 5;
            }
            $this->setVars($A);
        }

        // Get the option group in from the text field, or selection
        $pog_name = $A->getString('pog_name');
        if (!empty($pog_name)) {
            $POG = ProductOptionGroup::getByName($pog_name);
            if (!$POG) {
                // Creating a new option group on the fly.
                $POG = new ProductOptionGroup;
                $POG->setName($pog_name);
                $POG->Save();
            }
            $this->pog_id = $POG->getID();
        }

        // Make sure the necessary fields are filled in
        if (!$this->isValidRecord()) {
            return false;
        }

        $values = array(
            'pog_id' => $this->pog_id,
            'pov_value' => $this->pov_value,
            'sku' => $this->sku,
            'orderby' => $this->orderby,
            'pov_price' => $this->pov_price,
        );
        $types = array(
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::STRING,
        );

        // Insert or update the record, as appropriate.
        try {
            if ($this->pov_id == 0) {
                $db->conn->insert($_TABLES['shop.prod_opt_vals'], $values, $types);
                $this->pov_id = $db->conn->lastInsertId();
            } else {
                $types[] = Database::INTEGER;   // for pov_id
                $db->conn->update(
                    $_TABLES['shop.prod_opt_vals'],
                    $values,
                    array('pov_id' => $this->pov_id),
                    $types
                );
            }
            self::reOrder($this->pog_id);
            Cache::clear('shop.products');
            Cache::clear('shop.prod_opt_vals');
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $this->AddError($err);
            return false;
        }
    }


    /**
     * Delete the current option value record from the database.
     * The value will also be removed from any related product variants.
     *
     * @param   integer $opt_id    Option ID, empty for current object
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete(int $opt_id) : bool
    {
        global $_TABLES;

        $opt_id = (int)$opt_id;
        if ($opt_id <= 0) {
            return false;
        }

        // Delete from the option->value reference table
        ProductVariant::deleteOptionValue($opt_id);
        Database::getInstance()->conn->delete(
            $_TABLES['shop.prod_opt_vals'],
            array('pov_id' => $opt_id),
            array(Database::INTEGER)
        );
        Cache::clear('shop.products');
        Cache::clear('options');
        return true;
    }


    /**
     * Delete all option values related to a deleted option group.
     *
     * @param   integer $og_id      Option Group ID
     */
    public static function deleteOptionGroup(int $og_id) : void
    {
        global $_TABLES;

        try {
            $rows = Database::getInstance()->conn->executeQuery(
                "SELECT pov_id FROM {$_TABLES['shop.prod_opt_vals']} WHERE pog_id = ?",
                array($og_id),
                array(Database::INTEGER)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            $rows = false;
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                self::Delete($row['pov_id']);
            }
        }
    }


    /**
     * Determines if the current record is valid.
     *
     * @return  boolean     True if ok, False when first test fails.
     */
    public function isValidRecord()
    {
        // Check that basic required fields are filled in
        if (
            $this->pog_id == 0 ||
            $this->pov_value == ''
        ) {
            return false;
        }
        return true;
    }


    /**
     * Override the option price.
     *
     * @param   float   $price      Option Price
     * @return  object  $this
     */
    public function withPrice(float $price) : self
    {
        $this->pov_price = $price;
        return $this;
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id Optional ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = new Template('admin');
        $T->set_file('optform', 'option_val_form.thtml');
        $id = $this->pov_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit_item'] . ': ' . $this->pov_value);
            $T->set_var('pov_id', $id);
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_item']);
            $T->set_var('pov_id', '');
            if ($this->pog_id == 0) {
                $this->pog_id = ProductOptionGroup::getFirst()->getID();
            }
        }

        $T->set_var(array(
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('option_form', $_CONF['language']),
            'pog_id'        => $this->pog_id,
            'pog_name'      => ProductOptionGroup::getInstance($this->pog_id)->getName(),
            'pov_value'     => $this->pov_value,
            'pov_price'     => $this->pov_price,
            'option_group_select' => COM_optionList(
                        $_TABLES['shop.prod_opt_grps'],
                        'pog_id,pog_name',
                        $this->pog_id,
                        1
                    ),
            'orderby_opts'  => self::getOrderbyOpts($this->pog_id, $this->orderby),
            'sku'           => $this->sku,
            'orderby'       => $this->orderby,
        ) );
        $retval .= $T->parse('output', 'optform');
        $retval .= COM_endBlock();
        return $retval;
    }   // function Edit()



    /**
     * Add an error message to the Errors array.
     * Also could be used to log certain errors or perform other actions.
     *
     * @param  string  $msg    Error message to append
     */
    public function AddError($msg)
    {
        $this->Errors[] = $msg;
    }


    /**
     * Reorder all attribute items with the same product ID and attribute name.
     *
     * @param   integer $pog_id     Option Group ID (to allow static usage)
     */
    public static function reOrder(int $pog_id) : void
    {
        global $_TABLES;

        $changed = false;   // Assume no changes
        $db = Database::getInstance();
        try {
            $rows = $db->conn->executeQuery(
                "SELECT pov_id, orderby FROM {$_TABLES['shop.prod_opt_vals']}
                WHERE pog_id = ? ORDER BY orderby ASC",
                array($og_id),
                array(Database::INTEGER)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $rows = false;
        }
        if (is_array($rows)) {
            $order = 10;        // First orderby value
            $stepNumber = 10;   // Increment amount
            foreach ($rows as $row) {
                if ($row['orderby'] != $order) {  // only update incorrect ones
                    $changed = true;
                    try {
                        $db->conn->update(
                            $_TABLES['shop.prod_opt_vals'],
                            array('orderby' => $order),
                            array('pov_id' => $A['pov_id']),
                            array(Database::INTEGER, Database::INTEGER)
                        );
                    } catch (\Throwable $e) {
                        Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                        // Ignore error and continue.
                    }
                }
                $order += $stepNumber;
            }
        }
        if ($changed) {
            Cache::clear('shop.products');
            Cache::clear('options');
        }
    }


    /**
     * Move a calendar up or down the admin list, within its product.
     * Product ID is needed to pass through to reOrder().
     *
     * @param   string  $where  Direction to move (up or down)
     */
    public function moveRow(string $where) : void
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
            try {
                Database::getInstance()->conn->executeQuery(
                    "UPDATE {$_TABLES['shop.prod_opt_vals']}
                    SET orderby = orderby $oper 11 WHERE pov_id = ?",
                    array($this->pov_id),
                    array(Database::INTEGER)
                );
                self::reOrder($this->pog_id);
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
        }
    }


    /**
     * Product Option Value list view.
     *
     * @return  string      HTML for the attribute list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $sql = "SELECT pog.pog_name, pov.*
            FROM {$_TABLES['shop.prod_opt_vals']} pov
            LEFT JOIN {$_TABLES['shop.prod_opt_grps']} pog
            ON pov.pog_id = pog.pog_id";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'pov_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['opt_name'],
                'field' => 'pog_name',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['opt_value'],
                'field' => 'pov_value',
                'sort' => true,
            ),
            array(
                'text'  => 'SKU',
                'field' => 'sku',
                'align' => 'center',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['orderby'],
                'field' => 'orderby',
                'align' => 'center',
                'sort'  => true,
            ),
            array(
                'text' => $LANG_SHOP['opt_price'],
                'field' => 'pov_price',
                'align' => 'right',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => 'false',
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'pog_orderby,orderby',
            'direction' => 'ASC',
        );

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/index.php?pov_edit=0',
            'style' => 'success',
        ) );
        $def_filter = '';
        $query_arr = array(
            'table' => 'shop.prod_opt_values',
            'sql' => $sql,
            'query_fields' => array('p.name', 'pog_name', 'pov_value'),
            'default_filter' => $def_filter,
        );

        $text_arr = array(
            'form_url' => SHOP_ADMIN_URL . '/index.php?options=x',
        );
        $filter = '';
        $options = array(
            'chkdelete' => true,
            'chkfield' => 'pov_id',
        );
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_attrlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );

        // Create the "copy "options" form at the bottom
        /*if ($sel_prod_id == 0) {
            $T = new \Template(SHOP_PI_PATH . '/templates');
            $T->set_file('copy_opt_form', 'copy_options_form.thtml');
            $T->set_var(array(
                'src_product'       => $product_selection,
                'product_select'    => COM_optionList($_TABLES['shop.products'], 'id, name'),
                'cat_select'        => COM_optionList($_TABLES['shop.categories'], 'cat_id,cat_name'),
            ) );
            $display .= $T->parse('output', 'copy_opt_form');
        }*/

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
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . "/index.php?pov_edit=x&amp;opt_id={$A['pov_id']}",
            ) );
            break;

        case 'orderby':
            $retval = FieldList::up(array(
                'url' => SHOP_ADMIN_URL . '/index.php?pov_move=up&id=' . $A['pov_id'],
            ) ) .
            FieldList::down(array(
                'url' => SHOP_ADMIN_URL . '/index.php?pov_move=down&id=' . $A['pov_id'],
            ) );
            break;

        case 'enabled':
            $retval .= FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['pov_id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['pov_id']}','enabled','option');",
            ) );
            break;

        case 'delete':
            $retval .= FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL. '/index.php?pov_del=x&amp;opt_id=' . $A['pov_id'],
                'attr' => array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_pov'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                ),
            ) );
            break;

        case 'opt_price':
            $retval = \Shop\Currency::getInstance()->FormatValue($fieldvalue);
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }


    /**
     * Create the selection list for the `orderby` value.
     * Used here and from admin/ajax.php.
     *
     * @param   integer $og_id      Current Option Group ID
     * @param   integer $sel        Currently-selection option
     * @return  string      Option elements for a selection list
     */
    public static function getOrderbyOpts($og_id=0, $sel=0)
    {
        global $_TABLES, $LANG_SHOP;

        $og_id = (int)$og_id;
        $sel = (int)$sel;
        $retval = '<option value="0">--' . $LANG_SHOP['first'] . '--</option>' . LB;
        $retval .= COM_optionList(
            $_TABLES['shop.prod_opt_vals'],
            'orderby,pov_value',
            $sel - 10,
            0,
            "pog_id = '$og_id' AND orderby <> '$sel'"
        );
        return $retval;
    }


    /**
     * Get the option selection for one option group.
     *
     * @param   integer     $pog_id     Option Group ID
     * @return  string      Option list for options under the group
     */
    public static function getSelectionByGroup($pog_id)
    {
        global $_TABLES;

        $pog_id = (int)$pog_id;
        $retval .= COM_optionList(
            $_TABLES['shop.prod_opt_vals'],
            'pov_value,pov_name',
            '',
            1,
            "pog_id = '$pog_id'"
        );
        return $retval;
    }


    /**
     * Get all the available option values for a specific option group.
     *
     * @param   integer $pog_id     ProductOptionGroup ID
     * @return  array       Array of ProductOptionValue objects
     */
    public static function getByGroup(int $pog_id) : array
    {
        global $_TABLES;

        $pog_id = (int)$pog_id;
        $cache_key = 'options_' . $pog_id;
        $opts = Cache::get($cache_key);
        if ($opts === NULL) {
            $opts = array();
            try {
                $rows = Database::getInstance()->conn->executeQuery(
                    "SELECT pov.* FROM {$_TABLES['shop.prod_opt_vals']} pov
                    WHERE pov.pog_id = ? ORDER BY pov.orderby ASC",
                    array($pog_id),
                    array(Database::INTEGER)
                )->fetchAllAssociative();
            } catch (\Throwable $e) {
                $rows = false;
            }
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $opts[$row['pov_id']] = new self($row);
                }
            }
            Cache::set($cache_key, $opts, array('shop.products', 'shop.prod_opt_vals'));
        }
        return $opts;
    }


    /**
     * Get all options for a product, optionally limited by group.
     * Attempts to retrieve first from cache, then reads from the DB.
     *
     * @param   integer $prod_id    Product ID
     * @param   integer $og_id      Optional ProductOptionGroup ID
     * @return  array       Array of Option objects
     */
    public static function getByProduct($prod_id, $og_id=0)
    {
        global $_TABLES;

        $prod_id = (int)$prod_id;
        $og_id = (int)$og_id;
        $cache_key = 'options_' . $prod_id . '_' . $og_id;
        $opts = Cache::get($cache_key);
        if ($opts === NULL) {
            $opts = array();
            $qb = Database::getInstance()->conn->createQueryBuilder();
            $qb->select('pov.*')
               ->from($_TABLES['shop.prod_opt_vals'], 'pov')
               ->leftJoin('pov', $_TABLES['shop.variantXopt'], 'vxo', 'vxo.pov_id = pov.pov_id')
               ->leftJoin('pov', $_TABLES['shop.prod_opt_grps'], 'pog', 'pog.pog_id = pov.pog_id')
               ->leftJoin('vxo', $_TABLES['shop.product_variants'], 'pv', 'pv.pv_id = vxo.pv_id')
               ->where('pv.enabled = 1')
               ->orderBy('pog.pog_orderby, pov.orderby', 'ASC');
            if ($prod_id > 0) {
                $qb->andWhere('pv.item_id = :prod_id')
                   ->setParameter('prod_id', $prod_id, Database::INTEGER);
            }
            if ($og_id > 0) {
                $qb->andWhere('pov.pog_id = :og_id')
                   ->setParameter('og_id', $og_id, Database::INTEGER);
            }
            try {
                $stmt = $qb->execute();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($stmt) {
                while ($A = $stmt->fetchAssociative()) {
                    $opts[$A['pov_id']] = new self($A);
                }
            }
            Cache::set($cache_key, $opts, array('shop.products', 'shop.prod_opt_vals', $prod_id));
        }
        return $opts;
    }


    /**
     * Get the incremental price for this optionvalue.
     *
     * @return  float   Option price
     */
    public function getPrice()
    {
        return $this->pov_price;
    }


    /**
     * Get the SKU for this opion.
     *
     * @return  string  SKU string
     */
    public function getSKU()
    {
        return $this->sku;
    }


    /**
     * Get the text value for this option.
     *
     * @return  string  OptionValue value string
     */
    public function getValue()
    {
        return $this->pov_value;
    }


    /**
     * Get the record ID for this item.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return $this->pov_id;
    }


    /**
     * Get the OptionGroup ID for this value.
     *
     * @return  integer ProductOptionGroup ID
     */
    public function getGroupID()
    {
        return $this->pog_id;
    }


    /**
     * Set the OptionGroup ID for this value.
     *
     * @param   integer $grp_id     ProductOptionGroup ID
     * @return  object  $this
     */
    public function setGroupID($grp_id)
    {
        $this->pog_id = $grp_id;
        return $this;
    }


    /**
     * Set the product ID for this value.
     *
     * @param   integer $item_id    Product ID
     * @return  object  $this
     */
    public function setItemID($item_id)
    {
        $this->item_id = $item_id;
        return $this;
    }

}

?>
