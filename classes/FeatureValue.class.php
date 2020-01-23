<?php
/**
 * Class to manage product feature values.
 * These are stock strings that can be assigned to products, or can be
 * overriden by product-specific custom text.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for product feature values.
 * @package shop
 */
class FeatureValue
{
    /** Record ID.
     * @var integer */
    private $fv_id;

    /** Option Group record ID.
     * @var integer */
    private $ft_id;

    /** Option value.
     * @var string */
    private $fv_text;


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
                $this->fv_id = 0;
                $this->ft_id = 0;
                $this->fv_text = '';
            } else {
                $this->fv_id =  $id;
                if (!$this->Read()) {
                    $this->fv_id = 0;
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
        $this->fv_id = (int)$row['fv_id'];
        $this->ft_id = (int)$row['ft_id'];
        $this->fv_text = $row['fv_text'];
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
        if ($id == 0) $id = $this->fv_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query(
            "SELECT * FROM {$_TABLES['shop.features_values']}
            WHERE fv_id='$id'"
        );
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->setVars($row);
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

        /*if (is_array($A)) {
            if (!isset($A['orderby'])) {
                // Put this field at the end of the line by default.
                $A['orderby'] = 65535;
            } else {
                // Bump the number from the "position after" value.
                $A['orderby'] += 5;
            }
            $this->setVars($A);
        }*/

        // Make sure the necessary fields are filled in
        if (!$this->isValidRecord()) {
            return false;
        }
        $fv_id = (int)$this->fv_id;
        $fv_text = DB_escapeString($this->fv_text);
        if ($this->fv_id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['shop.features_values']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.features_values']} SET ";
            $sql3 = " WHERE fv_id = $fv_id";
        }
        $sql2 = "ft_id = {$this->getFeatureID()}, fv_text = '$fv_text'";
        $sql = $sql1 . $sql2 . $sql3;
        COM_errorLog($sql);
        DB_query($sql, 1);
        $err = DB_error();
        if ($err == '') {
            if ($this->fv_id == 0) {
                $this->fv_id = DB_insertID();
            }
            return true;
        } else {
            return false;
        }
    }


    /**
     * Delete the current feature value record from the database.
     * The value will also be removed from any related product variants.
     *
     * @param   integer $fv_id    Option ID, empty for current object
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($fv_id)
    {
        global $_TABLES;

        $fv_id = (int)$fv_id;
        if ($fv_id <= 0) {
            return false;
        }

        DB_delete($_TABLES['shop.features_values'], 'fv_id', $fv_id);
        return true;
    }


    /**
     * Delete all feature values related to a deleted feature.
     *
     * @param   integer $ft_id      Feature ID
     */
    public static function deleteOptionGroup($ft_id)
    {
        global $_TABLES;

        $sql = "DELETE FROM {$_TABLES['shop.features_values']}
            WHERE ft_id = " . (int)$ft_id;
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
            $this->ft_id == 0 ||
            $this->fv_text == ''
        ) {
            return false;
        }
        return true;
    }


    /**
     * Reorder all attribute items with the same product ID and attribute name.
     *
     * @param   integer $ft_id     Option Group ID (to allow static usage)
     */
    public static function reOrder($ft_id)
    {
        global $_TABLES;

        $ft_id = (int)$ft_id;
        $sql = "SELECT fv_id, orderby
                FROM {$_TABLES['shop.features_values']}
                WHERE ft_id= {$ft_id}
                ORDER BY orderby ASC;";
        //echo $sql;die;
        $result = DB_query($sql);

        $order = 10;        // First orderby value
        $stepNumber = 10;   // Increment amount
        $changed = false;   // Assume no changes
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $changed = true;
                $sql = "UPDATE {$_TABLES['shop.features_values']}
                    SET orderby = '$order'
                    WHERE fv_id = '{$A['fv_id']}'";
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
            $sql = "UPDATE {$_TABLES['shop.features_values']}
                    SET orderby = orderby $oper 11
                    WHERE fv_id = '{$this->fv_id}'";
            //echo $sql;die;
            DB_query($sql);
            self::reOrder($this->ft_id);
        }
    }


    /**
     * Product Option Value list view.
     *
     * @param   integer $ft_id    Feature record ID
     * @return  string      HTML for the attribute list.
     */
    public static function adminList($ft_id)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $ft_id = (int)$ft_id;
        $sql = "SELECT fv_id, fv_text
            FROM {$_TABLES['shop.features_values']}
            WHERE ft_id = $ft_id";
        $res = DB_query($sql);
        $data_arr = array();
        while ($A = DB_fetchArray($res, false)) {
            $data_arr[] = array(
                'fv_id' => $A['fv_id'],
                'fv_text' => $A['fv_text'],
            );
        }
        $data_arr[] = array(
            'fv_id' => 'Add:',
            'fv_text' => '<in',
        );

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'fv_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['feat_value'],
                'field' => 'fv_text',
                'sort' => true,
            ),
        );

        $defsort_arr = array(
            'field' => 'fv_id',
            'direction' => 'ASC',
        );

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $def_filter = '';
        $text_arr = array();
        $filter = '';
        $options = array();
        $display .= ADMIN_listArray(
            $_SHOP_CONF['pi_name'] . '_fvlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $data_arr, $defsort_arr,
            '', '', '', ''
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
                SHOP_ADMIN_URL . "/index.php?pov_edit=x&amp;fv_id={$A['fv_id']}"
            );
            break;

        case 'orderby':
            $retval = COM_createLink(
                Icon::getHTML('arrow-up'),
                SHOP_ADMIN_URL . '/index.php?pov_move=up&id=' . $A['fv_id']
            ) .
            COM_createLink(
                Icon::getHTML('arrow-down'),
                SHOP_ADMIN_URL . '/index.php?pov_move=down&id=' . $A['fv_id']
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
                id=\"togenabled{$A['fv_id']}\"
                onclick='SHOP_toggle(this,\"{$A['fv_id']}\",\"enabled\",".
                "\"option\");' />" . LB;
            break;

        case 'delete':
            $retval .= COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/index.php?pov_del=x&amp;fv_id=' . $A['fv_id'],
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
     * @param   integer $ft_id    Current Feature ID
     * @param   integer $sel        Currently-selection option
     * @return  string      Option elements for a selection list
     */
    public static function getValueOpts($ft_id=0, $sel=0)
    {
        global $_TABLES, $LANG_SHOP;

        $og_id = (int)$og_id;
        $sel = (int)$sel;
        $retval = '<option value="0">--' . $LANG_SHOP['first'] . '--</option>' . LB;
        $retval .= COM_optionList(
            $_TABLES['shop.features_values'],
            'orderby,fv_text',
            $sel - 10,
            0,
            "ft_id = '$og_id' AND orderby <> '$sel'"
        );
        return $retval;
    }


    /**
     * Get all the available feature values for a specific feature.
     *
     * @param   integer $ft_id     ProductOptionGroup ID
     * @return  array       Array of ProductOptionValue objects
     */
    public static function getByFeature($ft_id)
    {
        global $_TABLES;

        $ft_id = (int)$ft_id;
        //$cache_key = 'options_' . $ft_id;
        //$opts = Cache::get($cache_key);
        //if ($opts === NULL) {
            $opts = array();
            $sql = "SELECT * FROM {$_TABLES['shop.features_values']}
                WHERE ft_id = $ft_id";
//                ORDER BY orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $opts[$A['fv_id']] = new self($A);
            }
            //Cache::set($cache_key, $opts, array('products', 'options'));
        //}
        return $opts;
    }


    /**
     * Set the value (text) for the feature.
     *
     * @param   string  $val    Feature value
     * @return  object  $this
     */
    public function setValue($val)
    {
        $this->fv_text = $val;
        return $this;
    }


    /**
     * Get the text value for this option.
     *
     * @return  string  OptionValue value string
     */
    public function getValue()
    {
        return $this->fv_text;
    }


    /**
     * Get the record ID for this item.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return $this->fv_id;
    }


    /**
     * Set the feature ID related to this Value.
     *
     * @param   integer $ft_id  Feature record ID
     * @return  object  $this
     */
    public function setFeatureID($ft_id)
    {
        $this->ft_id = (int)$ft_id;
        return $this;
    }


    /**
     * Get the feature ID related to this Value.
     *
     * @param   integer     Feature record ID
     */
    public function getFeatureID()
    {
        return (int)$this->ft_id;
    }

}

?>
