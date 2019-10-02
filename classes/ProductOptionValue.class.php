<?php
/**
 * Class to manage product options.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for product attributes - color, size, etc.
 * @package shop
 */
class ProductOptionValue
{
    /** Property fields accessed via `__set()` and `__get()`.
     * @var array */
    private $properties;

    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    private $isNew;

    /** Array of error messages, to be accessible by the calling routines.
     * @var array */
    private $Errors = array();


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
        $this->properties = array();
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
                $this->enabled = 1;
                $this->orderby = 9999;
            } else {
                $this->pov_id =  $id;
                if (!$this->Read()) {
                    $this->pov_id = 0;
                }
            }
        }
    }


    /**
     * Set a property's value.
     *
     * @param   string  $var    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    public function __set($var, $value='')
    {
        switch ($var) {
        case 'pov_id':
        case 'item_id':
        case 'orderby':
        case 'pog_id':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'pov_value':
        case 'sku':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'enabled':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        case 'pov_price':
            // Floating-point values
            $this->properties[$var] = (float)$value;
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
     * Get the value of a property.
     *
     * @param   string  $var    Name of property to retrieve.
     * @return  mixed           Value of property, NULL if undefined.
     */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
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
        $this->pov_id = $row['pov_id'];
        $this->item_id = $row['item_id'];
        $this->pog_id = $row['pog_id'];
        $this->pov_value = $row['pov_value'];
        $this->pov_price = $row['pov_price'];
        $this->enabled = $row['enabled'];
        $this->orderby = $row['orderby'];
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

        $result = DB_query("SELECT *
                    FROM {$_TABLES['shop.prod_opt_vals']}
                    WHERE pov_id='$id'");
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

        $sql2 = " SET item_id='{$this->item_id}',
                pog_id = {$this->pog_id},
                pov_value='" . DB_escapeString($this->pov_value) . "',
                sku = '" . DB_escapeString($this->sku) . "',
                orderby='{$this->orderby}',
                pov_price='" . number_format($this->pov_price, 2, '.', '') . "',
                enabled='{$this->enabled}'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            if ($this->isNew) {
                $this->pov_id = DB_insertID();
            }
            self::reOrder($this->item_id);
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
     * Delete the current category record from the database.
     *
     * @param   integer $opt_id    Option ID, empty for current object
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($opt_id)
    {
        global $_TABLES;

        if ($opt_id <= 0)
            return false;

        DB_delete($_TABLES['shop.prod_opt_vals'], 'pov_id', $opt_id);
        Cache::clear('products');
        return true;
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
            $this->item_id == 0 ||
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

        // If there are no products defined, return a formatted error message
        // instead of the form.
        if (DB_count($_TABLES['shop.products']) == 0) {
            return SHOP_errMsg($LANG_SHOP['todo_noproducts']);
        }

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('optform', 'option_val_form.thtml');
        $id = $this->pov_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit_opt'] . ': ' . $this->pov_value);
            $T->set_var('pov_id', $id);
            $init_item_id = $this->item_id;
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_option']);
            $this->og_id = ProductOptionGroup::getFirst()->getID();
            $T->set_var('pov_id', '');
            $init_item_id = Product::getFirst();
        }
        $T->set_var(array(
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL(
                'attribute_form',
                $_CONF['language']
            ),
            'item_id'       => $this->item_id,
            'init_item_id'  => $init_item_id,
            'item_name'     => Product::getByID($this->item_id)->name,
            'pog_id'        => $this->pog_id,
            'pog_name'      => ProductOptionGroup::getInstance($this->pog_id)->getName(),
            'pov_value'     => $this->pov_value,
            'pov_price'     => $this->pov_price,
            'product_select' => COM_optionList($_TABLES['shop.products'],
                    'id,name', $this->item_id),
            'option_group_select' => COM_optionList(
                        $_TABLES['shop.prod_opt_grps'],
                        'pog_id,pog_name',
                        $this->pog_id,
                        0
                    ),
            'orderby_opts'  => self::getOrderbyOpts($init_item_id, $this->pog_id, $this->orderby),
            'sku'           => $this->sku,
            'orderby'       => $this->orderby,
            'ena_chk'       => $this->enabled == 1 ? ' checked="checked"' : '',
        ) );
        $retval .= $T->parse('output', 'optform');
        $retval .= COM_endBlock();
        return $retval;
    }   // function Edit()


    /**
     * Sets a boolean field to the specified value.
     *
     * @param   integer $oldvalue   Original value of field
     * @param   integer $varname    Name of field to change
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    private static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        // Determing the new value (opposite the old)
        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['shop.prod_opt_vals']}
                SET $varname=$newvalue
                WHERE pov_id=$id";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
            return $oldvalue;
        } else {
            Cache::clear('products');
            Cache::clear('options');
            return $newvalue;
        }
    }


    /**
     * Toggles the "enabled" field value.
     *
     * @uses    self::_toggle()
     * @param   integer $oldvalue   Original field value
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
     public static function toggleEnabled($oldvalue, $id=0)
     {
         return self::_toggle($oldvalue, 'enabled', $id);
     }


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
     */
    private function reOrder()
    {
        global $_TABLES;

        $sql = "SELECT pov_id, orderby
                FROM {$_TABLES['shop.prod_opt_vals']}
                WHERE item_id = '{$this->item_id}'
                AND pog_id= '{$this->pog_id}'
                ORDER BY orderby ASC;";
        //echo $sql;die;
        $result = DB_query($sql);

        $order = 10;        // First orderby value
        $stepNumber = 10;   // Increment amount
        $changed = false;   // Assume no changes
        while ($A = DB_fetchArray($result, false)) {
            SHOP_log("checking item {$A['pov_id']}", SHOP_LOG_DEBUG);
            SHOP_log("Order by is {$A['orderby']}, should be $order", SHOP_LOG_DEBUG);
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
            $this->reOrder();
        }
    }


    /**
     * Product Option List View.
     * Values for `$prod_id`:
     * - -1 : Default, display a normal attribute admin list
     * - 0 : Invalid product, return nothing
     * - >0 : Display only for the product ID, no embedded form
     *
     * @param   integer $prod_id    Optional product ID to limit listing
     * @return  string      HTML for the attribute list.
     */
    public static function adminList($prod_id=-1)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $sql = "SELECT pog.pog_name, pov.*, p.name AS prod_name
            FROM {$_TABLES['shop.prod_opt_vals']} pov
            LEFT JOIN {$_TABLES['shop.prod_opt_grps']} pog
            ON pov.pog_id = pog.pog_id
            LEFT JOIN {$_TABLES['shop.products']} p
            ON pov.item_id = p.id";

        if ($prod_id == 0) {
            return '';
        } elseif ($prod_id > 0) {
            $sel_prod_id = (int)$prod_id;
        } elseif (isset($_POST['product_id'])) {
            $sel_prod_id = (int)$_POST['product_id'];
            SESS_setVar('shop.opt_prod_id', $sel_prod_id);
        } elseif (SESS_isSet('shop.opt_prod_id')) {
            $sel_prod_id = (int)SESS_getVar('shop.opt_prod_id');
        } else {
            $sel_prod_id = 0;
        }

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
                'text' => $LANG_SHOP['product'],
                'field' => 'prod_name',
                'sort' => true,
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
        );
        if ($prod_id == -1) {
            // No changing the order when shown as part of the product edit form
            $header_arr[] = array(
                'text'  => $LANG_SHOP['orderby'],
                'field' => 'orderby',
                'align' => 'center',
                'sort'  => true,
            );
        }
        $header_arr[] = array(
                'text' => $LANG_SHOP['opt_price'],
                'field' => 'pov_price',
                'align' => 'right',
                'sort' => true,
        );
        $header_arr[] = array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => 'false',
                'align' => 'center',
        );

        $defsort_arr = array(
            'field' => 'prod_name,pog_orderby,orderby',
            'direction' => 'ASC',
        );

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink($LANG_SHOP['new_opt'],
            SHOP_ADMIN_URL . '/index.php?pov_edit=0&item_id=' . $sel_prod_id,
            array(
                'style' => 'float:left;',
                'class' => 'uk-button uk-button-success',
            )
        );
        if ($sel_prod_id > 0) {
            $def_filter = "WHERE item_id = '$sel_prod_id'";
        } else {
            $def_filter = '';
        }
        $query_arr = array(
            'table' => 'shop.prod_opt_values',
            'sql' => $sql,
            'query_fields' => array('p.name', 'pog_name', 'pov_value'),
            'default_filter' => $def_filter,
        );

        if ($prod_id == -1) {
        $filter = "{$LANG_SHOP['product']}: <select name=\"product_id\"
            onchange=\"this.form.submit();\">
            <option value=\"0\">-- {$LANG_SHOP['any']} --</option>\n" .
            COM_optionList($_TABLES['shop.products'], 'id, name', $sel_prod_id) .
            "</select>&nbsp;\n";
        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?attributes=x',
        );
        $options = array('chkdelete' => true, 'chkfield' => 'pov_id');
        } else {
            $text_arr = array();
            $filter = '';
            $options = array();
            $query_arr['sql'] .= " WHERE item_id = '$prod_id'";
        }
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_attrlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );

        // Create the "copy attributes" form at the bottom
        if ($prod_id == 0) {
            $T = new \Template(SHOP_PI_PATH . '/templates');
            $T->set_file('copy_opt_form', 'copy_attributes_form.thtml');
            $T->set_var(array(
                'src_product'       => $product_selection,
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
                SHOP_ADMIN_URL. '/index.php?deleteopt=x&amp;opt_id=' . $A['pov_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
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
     * @param   integer $item_id    Current product ID
     * @param   integer $og_id      Current Option Group ID
     * @param   integer $sel        Currently-selection option
     * @return  string      Option elements for a selection list
     */
    public static function getOrderbyOpts($item_id=0, $og_id=0, $sel=0)
    {
        global $_TABLES, $LANG_SHOP;

        $item_id = (int)$item_id;
        $og_id = (int)$og_id;
        $sel = (int)$sel;
        $retval = '<option value="0">--' . $LANG_SHOP['first'] . '--</option>' . LB;
        $retval .= COM_optionList(
            $_TABLES['shop.prod_opt_vals'],
            'orderby,pov_value',
            $sel - 10,
            0,
            "pog_id = '$og_id' AND item_id = '$item_id' AND orderby <> '$sel'"
        );
        return $retval;
    }


    /**
     * Get all attributes for a product, optionally limited by group.
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
            $sql = "SELECT pog.pog_name, pov.*
                FROM {$_TABLES['shop.prod_opt_vals']} pov
                LEFT JOIN {$_TABLES['shop.prod_opt_grps']} pog
                    ON pov.pog_id = pog.pog_id
                WHERE pov.item_id = '{$prod_id}' AND pov.enabled = 1";
            if ($og_id > 0) {
                $sql .= " AND pov.pog_id = '$og_id'";
            }
            $sql .= " ORDER BY pog.pog_orderby, pov.orderby ASC";
            $result = DB_query($sql);
            while ($A = DB_fetchArray($result, false)) {
                $opts[$A['pov_id']] = new self($A);
            }
            Cache::set($cache_key, $opts, array('products', 'options', $prod_id));
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
     */
    public function setGroupID($grp_id)
    {
        $this->pog_id = $grp_id;
    }


    /**
     * Set the product ID for this value.
     *
     * @param   integer $item_id    Product ID
     */
    public function setItemID($item_id)
    {
        $this->item_id = $item_id;
    }


    /**
     * Delete the option values related to a specific product.
     * Called when deleting the product.
     *
     * @param   integer $item_id    Product ID
     */
    public static function deleteProduct($item_id)
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        DB_delete($_TABLES['shop.prod_opt_vals'], 'item_id', $item_id);
    }


    /**
     * Clone a product's option values to another product.
     *
     * @param   integer $src    Source product ID
     * @param   integer $dst    Destination product ID
     * @param   boolean $del_existing   True to remove existing values in dst
     */
    public static function cloneProduct($src, $dst, $del_existing=true)
    {
        global $_TABLES;

        $src = (int)$src;
        $dst = (int)$dst;
        if ($del_existing) {
            self::deleteProduct($dst);
        }
        $sql = "INSERT IGNORE INTO {$_TABLES['shop.prod_opt_vals']}
            SELECT NULL, $dst, pog_id, pov_name, pov_value, orderby, pov_price, enabled
            FROM {$_TABLES['shop.prod_opt_vals']}
            WHERE item_id = $src";
        DB_query($sql);
    }

}

?>
