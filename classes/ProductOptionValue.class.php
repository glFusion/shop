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


/**
 * Class for product options - color, size, etc.
 * @package shop
 */
class ProductOptionValue
{
    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    private $isNew;

    /** Array of error messages, to be accessible by the calling routines.
     * @var array */
    private $Errors = array();

    /** Record ID.
     * @var integer */
    private $pov_id;

    /** Option Group record ID.
     * @var integer */
    private $pog_id;

    /** Option value.
     * @var string */
    private $pov_value;

    /** Option price impact.
     * @var float */
    private $pov_price;

    /** Orderby option for selection.
     * @var integer */
    private $orderby;

    /** SKU component for this option.
     * @var string */
    private $sku;


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
        $this->isNew = true;

        if (is_array($id)) {
            // Received a full Option record already read from the DB
            $this->setVars($id);
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
    public function setVars($row)
    {
        if (!is_array($row)) return;
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
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->pov_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query(
            "SELECT * FROM {$_TABLES['shop.prod_opt_vals']}
            WHERE pov_id='$id'"
        );
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->setVars($row);
            $this->isNew = false;
            return true;
        }
    }


    /**
     * Save the current values to the database.
     *
     * @param   array   $A      Array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save($A = array())
    {
        global $_TABLES, $_SHOP_CONF;

        if (is_array($A)) {
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
        if (isset($A['pog_name']) && !empty($A['pog_name'])) {
            $POG = ProductOptionGroup::getByName($A['pog_name']);
            if (!$POG) {
                $POG = new ProductOptionGroup;
                $POG->setName($_POST['pog_name']);
                $POG->Save();
            }
            $this->pog_id = $POG->pog_id;
        }

        // Make sure the necessary fields are filled in
        if (!$this->isValidRecord()) {
            return false;
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['shop.prod_opt_vals']}";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.prod_opt_vals']}";
            $sql3 = " WHERE pov_id={$this->pov_id}";
        }

        $sql2 = " SET 
                pog_id = " . (int)$this->pog_id . ",
                pov_value='" . DB_escapeString($this->pov_value) . "',
                sku = '" . DB_escapeString($this->sku) . "',
                orderby='" . (int)$this->orderby . "',
                pov_price='" . (float)$this->pov_price . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            if ($this->isNew) {
                $this->pov_id = DB_insertID();
            }
            self::reOrder($this->pog_id);
            //Cache::delete('options_' . $this->item_id);
            Cache::clear('products');
            Cache::clear('options');
            return true;
        } else {
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
    public static function Delete($opt_id)
    {
        global $_TABLES;

        $opt_id = (int)$opt_id;
        if ($opt_id <= 0) {
            return false;
        }

        ProductVariant::deleteOptionValue($opt_id);
        DB_delete($_TABLES['shop.prod_opt_vals'], 'pov_id', $opt_id);
        Cache::clear('products');
        Cache::clear('options');
        return true;
    }


    /**
     * Delete all option values related to a deleted option group.
     *
     * @param   integer $og_id      Option Group ID
     */
    public static function deleteOptionGroup($og_id)
    {
        global $_TABLES;

        $sql = "SELECT pov_id FROM {$_TABLES['shop.prod_opt_vals']}
            WHERE pog_id = " . (int)$og_id;
        $res = DB_query($sql);
        while ($A = DB_fetchArray($sql, false)) {
            self::Delete($A['pov_id']);
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
     * Creates the edit form.
     *
     * @param   integer $id Optional ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('optform', 'option_val_form.thtml');
        $id = $this->pov_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit_opt'] . ': ' . $this->pov_value);
            $T->set_var('pov_id', $id);
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_option']);
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
    public static function reOrder($pog_id)
    {
        global $_TABLES;

        $pog_id = (int)$pog_id;
        $sql = "SELECT pov_id, orderby
                FROM {$_TABLES['shop.prod_opt_vals']}
                WHERE pog_id= {$pog_id}
                ORDER BY orderby ASC;";
        //echo $sql;die;
        $result = DB_query($sql);

        $order = 10;        // First orderby value
        $stepNumber = 10;   // Increment amount
        $changed = false;   // Assume no changes
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $changed = true;
                $sql = "UPDATE {$_TABLES['shop.prod_opt_vals']}
                    SET orderby = '$order'
                    WHERE pov_id = '{$A['pov_id']}'";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
        if ($changed) {
            Cache::clear();
        }
    }


    /**
     * Move a calendar up or down the admin list, within its product.
     * Product ID is needed to pass through to reOrder().
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
            $sql = "UPDATE {$_TABLES['shop.prod_opt_vals']}
                    SET orderby = orderby $oper 11
                    WHERE pov_id = '{$this->pov_id}'";
            //echo $sql;die;
            DB_query($sql);
            self::reOrder($this->pog_id);
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
        $display .= COM_createLink($LANG_SHOP['new_opt'],
            SHOP_ADMIN_URL . '/index.php?pov_edit=0',
            array(
                'style' => 'float:left;',
                'class' => 'uk-button uk-button-success',
            )
        );
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
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array(
                    'title' => $LANG_ADMIN['edit'],
                ) ),
                SHOP_ADMIN_URL . "/index.php?pov_edit=x&amp;opt_id={$A['pov_id']}"
            );
            break;

        case 'orderby':
            $retval = COM_createLink(
                Icon::getHTML('arrow-up'),
                SHOP_ADMIN_URL . '/index.php?pov_move=up&id=' . $A['pov_id']
            ) .
            COM_createLink(
                Icon::getHTML('arrow-down'),
                SHOP_ADMIN_URL . '/index.php?pov_move=down&id=' . $A['pov_id']
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
                id=\"togenabled{$A['pov_id']}\"
                onclick='SHOP_toggle(this,\"{$A['pov_id']}\",\"enabled\",".
                "\"option\");' />" . LB;
            break;

        case 'delete':
            $retval .= COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/index.php?pov_del=x&amp;opt_id=' . $A['pov_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_pov'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
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
    public static function getByGroup($pog_id)
    {
        global $_TABLES;

        $pog_id = (int)$pog_id;
        $cache_key = 'options_' . $pog_id;
        $opts = Cache::get($cache_key);
        if ($opts === NULL) {
            $opts = array();
            $sql = "SELECT pov.* FROM {$_TABLES['shop.prod_opt_vals']} pov
                WHERE pov.pog_id = $pog_id
                ORDER BY pov.orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $opts[$A['pov_id']] = new self($A);
            }
            Cache::set($cache_key, $opts, array('products', 'options'));
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
        //$cache_key = 'options_' . $prod_id . '_' . $og_id;
        //$opts = Cache::get($cache_key);
        //if ($opts === NULL) {
            $opts = array();
            $sql = "SELECT pov.* FROM {$_TABLES['shop.prod_opt_vals']} pov
                LEFT JOIN {$_TABLES['shop.variantXopt']} vxo ON vxo.pov_id = pov.pov_id
                LEFT JOIN {$_TABLES['shop.prod_opt_grps']} pog ON pog.pog_id = pov.pog_id
                LEFT JOIN {$_TABLES['shop.product_variants']} pv ON pv.pv_id = vxo.pv_id
                WHERE pv.enabled = 1";
            if ($prod_id > 0) {
                $sql .= " AND pv.item_id = $prod_id";
            }
            if ($og_id > 0) {
                $sql .= " AND pov.pog_id = '$og_id'";
            }
            $sql .= " ORDER BY pog.pog_orderby, pov.orderby ASC";
            $result = DB_query($sql);
            while ($A = DB_fetchArray($result, false)) {
                $opts[$A['pov_id']] = new self($A);
            }
            //Cache::set($cache_key, $opts, array('products', 'options', $prod_id));
        //}
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
