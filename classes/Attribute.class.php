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
class Attribute
{
    /** Property fields accessed via `__set()` and `__get()`.
     * @var array */
    var $properties;

    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    var $isNew;

    /** Array of error messages, to be accessible by the calling routines.
     * @var array */
    var $Errors = array();


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id Attributeal type ID
     */
    public function __construct($id=0)
    {
        $this->properties = array();
        $this->isNew = true;

        $id = (int)$id;
        if ($id < 1) {
            // New entry, set defaults
            $this->attr_id = 0;
            $this->ag_id = 0;
            $this->attr_name = 0;
            $this->attr_value = '';
            $this->attr_price = 0;
            $this->item_id = 0;
            $this->enabled = 1;
            $this->orderby = 9999;
        } else {
            $this->attr_id =  $id;
            if (!$this->Read()) {
                $this->attr_id = 0;
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
        case 'attr_id':
        case 'item_id':
        case 'orderby':
        case 'ag_id':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'attr_value':
        case 'attr_name':
        case 'sku':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'enabled':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        case 'attr_price':
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
        $this->attr_id = $row['attr_id'];
        $this->item_id = $row['item_id'];
        $this->ag_id = $row['ag_id'];
        $this->attr_name = $row['attr_name'];
        $this->attr_value = $row['attr_value'];
        $this->attr_price = $row['attr_price'];
        $this->enabled = $row['enabled'];
        $this->orderby = $row['orderby'];
        $this->sku = $row['sku'];
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Attributeal ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->attr_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query("SELECT *
                    FROM {$_TABLES['shop.prod_attr']}
                    WHERE attr_id='$id'");
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
            if (empty($A['orderby'])) {
                // Put this field at the end of the line by default.
                $A['orderby'] = 65535;
            } else {
                // Bump the number from the "position after" value.
                $A['orderby'] += 5;
            }
            $this->setVars($A);
        }

        // Get the option group in from the text field, or selection
        if (isset($_POST['attr_name']) && !empty($_POST['attr_name'])) {
            $this->attr_name = $_POST['attr_name'];
        } else {
            $this->attr_name = $_POST['attr_name_sel'];
        }

        // Make sure the necessary fields are filled in
        if (!$this->isValidRecord()) {
            return false;
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['shop.prod_attr']}";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.prod_attr']}";
            $sql3 = " WHERE attr_id={$this->attr_id}";
        }

        $sql2 = " SET item_id='{$this->item_id}',
                attr_name='" . DB_escapeString($this->attr_name) . "',
                ag_id = {$this->ag_id},
                attr_value='" . DB_escapeString($this->attr_value) . "',
                sku = '" . DB_escapeString($this->sku) . "',
                orderby='{$this->orderby}',
                attr_price='" . number_format($this->attr_price, 2, '.', '') . "',
                enabled='{$this->enabled}'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            if ($this->isNew) {
                $this->attr_id = DB_insertID();
            }
            self::reOrder($this->item_id);
            //Cache::delete('prod_attr_' . $this->item_id);
            Cache::clear('products');
            Cache::clear('attributes');
            return true;
        } else {
            $this->AddError($err);
            return false;
        }
    }


    /**
     * Delete the current category record from the database.
     *
     * @param   integer $attr_id    Attribute ID, empty for current object
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($attr_id)
    {
        global $_TABLES;

        if ($attr_id <= 0)
            return false;

        DB_delete($_TABLES['shop.prod_attr'], 'attr_id', $attr_id);
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
            $this->ag_id == 0 ||
            $this->attr_value == ''
        ) {
            return false;
        }
        return true;
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id Attributeal ID, current record used if zero
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

        $T = SHOP_getTemplate('attribute_form', 'attrform');
        $id = $this->attr_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit'] . ': ' . $this->attr_value);
            $T->set_var('attr_id', $id);
            $init_item_id = $this->item_id;
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_option']);
            $this->ag_id = AttributeGroup::getFirst()->ag_id;
            $T->set_var('attr_id', '');
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
            'ag_id'         => $this->ag_id,
            'ag_name'       => AttributeGroup::getInstance($this->ag_id)->ag_name,
            'attr_value'    => $this->attr_value,
            'attr_price'    => $this->attr_price,
            'product_select' => COM_optionList($_TABLES['shop.products'],
                    'id,name', $this->item_id),
            'option_group_select' => COM_optionList(
                        $_TABLES['shop.attr_grp'],
                        'ag_id,ag_name',
                        $this->ag_id,
                        0
                    ),
            'orderby_opts'  => self::getOrderbyOpts($init_item_id, $this->ag_id, $this->orderby),
            'sku'           => $this->sku,
            'orderby'       => $this->orderby,
            'ena_chk'       => $this->enabled == 1 ? ' checked="checked"' : '',
        ) );
        $retval .= $T->parse('output', 'attrform');
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

        $sql = "UPDATE {$_TABLES['shop.prod_attr']}
                SET $varname=$newvalue
                WHERE attr_id=$id";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
            return $oldvalue;
        } else {
            return $newvalue;
        }
    }


    /**
     * Toggles the "enabled" field value.
     *
     * @uses    Attribute::_toggle()
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

        $sql = "SELECT attr_id, orderby
                FROM {$_TABLES['shop.prod_attr']}
                WHERE item_id = '{$this->item_id}'
                AND ag_id= '{$this->ag_id}'
                ORDER BY orderby ASC;";
        //echo $sql;die;
        $result = DB_query($sql);

        $order = 10;        // First orderby value
        $stepNumber = 10;   // Increment amount
        $changed = false;   // Assume no changes
        while ($A = DB_fetchArray($result, false)) {
            SHOP_log("checking item {$A['attr_id']}", SHOP_LOG_DEBUG);
            SHOP_log("Order by is {$A['orderby']}, should be $order", SHOP_LOG_DEBUG);
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $changed = true;
                $sql = "UPDATE {$_TABLES['shop.prod_attr']}
                    SET orderby = '$order'
                    WHERE attr_id = '{$A['attr_id']}'";
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
            $sql = "UPDATE {$_TABLES['shop.prod_attr']}
                    SET orderby = orderby $oper 11
                    WHERE attr_id = '{$this->attr_id}'";
            //echo $sql;die;
            DB_query($sql);
            $this->reOrder();
        }
    }


    /**
     * Category Admin List View.
     *
     * @param   integer $cat_id     Optional attribute ID to limit listing
     * @return  string      HTML for the attribute list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $sql = "SELECT ag.ag_name, at.*, p.name AS prod_name
            FROM {$_TABLES['shop.prod_attr']} at
            LEFT JOIN {$_TABLES['shop.attr_grp']} ag
            ON at.ag_id = ag.ag_id
            LEFT JOIN {$_TABLES['shop.products']} p
            ON at.item_id = p.id";
//            WHERE 1=1 ";

        if (isset($_POST['product_id'])) {
            $sel_prod_id = (int)$_POST['product_id'];
            SESS_setVar('shop.attr_prod_id', $sel_prod_id);
        } elseif (SESS_isSet('shop.attr_prod_id')) {
            $sel_prod_id = (int)SESS_getVar('shop.attr_prod_id');
        } else {
            $sel_prod_id = 0;
        }

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'attr_id',
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
                'text' => $LANG_SHOP['attr_name'],
                'field' => 'ag_name',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['attr_value'],
                'field' => 'attr_value',
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
                'text' => $LANG_SHOP['attr_price'],
                'field' => 'attr_price',
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
            'field' => 'prod_name,attr_name,orderby',
            'direction' => 'ASC',
        );

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink($LANG_SHOP['new_attr'],
            SHOP_ADMIN_URL . '/index.php?editattr=0',
            array(
                'style' => 'float:left;',
                'class' => 'uk-button uk-button-success',
            )
        );
        $product_selection = COM_optionList($_TABLES['shop.products'], 'id, name', $sel_prod_id);
        $filter = "{$LANG_SHOP['product']}: <select name=\"product_id\"
            onchange=\"this.form.submit();\">
            <option value=\"0\">-- Any --</option>\n" .
            $product_selection .
            "</select>&nbsp;\n";

        if ($sel_prod_id > 0) {
            $def_filter = "WHERE item_id = '$sel_prod_id'";
        } else {
            $def_filter = '';
        }
        $query_arr = array('table' => 'shop.prod_attr',
            'sql' => $sql,
            'query_fields' => array('p.name', 'attr_name', 'attr_value'),
            'default_filter' => $def_filter,
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?attributes=x',
        );

        $options = array('chkdelete' => true, 'chkfield' => 'attr_id');
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_attrlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );

        // Create the "copy attributes" form at the bottom
        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('copy_attr_form', 'copy_attributes_form.thtml');
        $T->set_var(array(
            'src_product'       => $product_selection,
            'product_select'    => COM_optionList($_TABLES['shop.products'], 'id, name'),
            'cat_select'        => COM_optionList($_TABLES['shop.categories'], 'cat_id,cat_name'),
        ) );
        $display .= $T->parse('output', 'copy_attr_form');

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
                '<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
                SHOP_ADMIN_URL . "/index.php?editattr=x&amp;attr_id={$A['attr_id']}"
            );
            break;

        case 'orderby':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-arrow-up"></i>',
                SHOP_ADMIN_URL . '/index.php?attrmove=up&id=' . $A['attr_id']
            ) .
            COM_createLink('<i class="uk-icon uk-icon-arrow-down"></i>',
                SHOP_ADMIN_URL . '/index.php?attrmove=down&id=' . $A['attr_id']
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
                id=\"togenabled{$A['attr_id']}\"
                onclick='SHOP_toggle(this,\"{$A['attr_id']}\",\"enabled\",".
                "\"attribute\");' />" . LB;
            break;

        case 'delete':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-trash uk-text-danger"></i>',
                SHOP_ADMIN_URL. '/index.php?deleteopt=x&amp;attr_id=' . $A['attr_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

        case 'attr_price':
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
     * @param   integer $ag_id      Current Attribute Group ID
     * @param   integer $sel        Currently-selection option
     * @return  string      Option elements for a selection list
     */
    public static function getOrderbyOpts($item_id=0, $ag_id=0, $sel=0)
    {
        global $_TABLES, $LANG_SHOP;

        $item_id = (int)$item_id;
        $ag_id = (int)$ag_id;
        $sel = (int)$sel;
        $retval = '<option value="0">--' . $LANG_SHOP['first'] . '--</option>' . LB;
        $retval .= COM_optionList(
            $_TABLES['shop.prod_attr'],
            'orderby,attr_value',
            $sel - 10,
            0,
            "ag_id = '$ag_id' AND item_id = '$item_id' AND orderby <> '$sel'"
        );
        return $retval;
    }

}

?>
