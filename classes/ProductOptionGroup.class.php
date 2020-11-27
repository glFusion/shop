<?php
/**
 * Class to manage product option groups.
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
 * Class for product attribute groups.
 * @package shop
 */
class ProductOptionGroup
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Table key, used by DBO class.
     * @var string */
    private static $TABLE = 'shop.prod_opt_grps';

    /** ID Field name, used by DBO class.
     * @var string */
    private static $F_ID = 'pog_id';

    /** Order field name, useb by DBO class.
     * @var string */
    private static $F_ORDERBY = 'pog_orderby';

    /** Tag array used with caching, for consistency.
     * @var array */
    private static $TAGS = array('products', 'options');

    /** Option Group record ID.
     * @var integer */
    private $pog_id = 0;

    /** Option Group display order.
     * @var integer */
    private $pog_orderby = 9999;

    /** Field type, e.g. `select`, `radio`, etc.
     * @var string */
    private $pog_type = 'select';

    /** Name of option group.
     * @var string */
    private $pog_name = '';

    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    private $isNew;

    /** Array of error messages, to be accessible by the calling routines.
     * @var array */
    private  $Errors = array();

    /** Array of Option objects under this Option Group for a specific product.
     * @var array */
    private $Options = array();

    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id Option Group ID
     */
    public function __construct($id=0)
    {
        $this->isNew = true;

        if (is_array($id)) {
            $this->setVars($id);
        } else {
            $id = (int)$id;
            if ($id >= 1) {
                $this->pog_id = (int)$id;
                if (!$this->Read()) {
                    $this->pog_id = 0;
                }
            }
        }
    }


    /**
     * Get all attribute groups.
     *
     * @return  array       Array of ProductOptionGroup objects
     */
    public static function getAll()
    {
        global $_TABLES;

        //$cache_key = 'shop_opt_grp_all';
        //$retval = Cache::get($cache_key);
        //if ($retval === NULL) {
            $retval = array();
            $sql = "SELECT * FROM {$_TABLES['shop.prod_opt_grps']}
                ORDER BY pog_orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['pog_id']] = new self($A);
            }
            //Cache::set($cache_key, $retval, self::$TAGS);
        //}
        return $retval;
    }


    /**
     * Get an instance of a specific option group.
     *
     * @param   integer $og_id  ProductOptionGroup record ID
     * @return  object      ProductOptionGroup object
     */
    public static function getInstance($og_id)
    {
        static $grps = NULL;
        if ($grps === NULL) {
            $grps = self::getAll();
        }
        if (array_key_exists($og_id, $grps)) {
            return $grps[$og_id];
        } else {
            return new self($og_id);;
        }
    }


    /**
     * Sets all variables to the matching values from $row.
     *
     * @param   array $A    Array of values, from DB or $_POST
     */
    public function setVars($A)
    {
        if (!is_array($A)) {
            return;
        }
        $this->pog_id = (int)$A['pog_id'];
        $this->pog_type = $A['pog_type'];
        $this->pog_name = $A['pog_name'];
        $this->pog_orderby = (int)$A['pog_orderby'];
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Optional ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->pog_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query(
            "SELECT * FROM {$_TABLES['shop.prod_opt_grps']}
            WHERE pog_id='$id'"
        );
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $A = DB_fetchArray($result, false);
            $this->setVars($A);
            $this->isNew = false;
            return true;
        }
    }


    /**
     * Get an attribute group by its name.
     * Used to allow an attribute group to be created by adding a new name in
     * the attribute form.
     *
     * @param   string  $name   Option Group Name
     * @return  object|null Option Group, or NULL if not found
     */
    public static function getByName($name)
    {
        global $_TABLES;

        $retval = NULL;
        $sql = "SELECT * FROM {$_TABLES['shop.prod_opt_grps']}
            WHERE pog_name = '" . DB_escapeString($name) . "'";
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            if (!empty($A)) {
                $retval = new self($A);
            }
        }
        return $retval;
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

        if (is_array($A) && !empty($A)) {
            // Put this field at the end of the line by default
            $this->setVars($A);
        }

        // Make sure the necessary fields are filled in
        if (!$this->isValidRecord()) {
            return false;
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['shop.prod_opt_grps']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.prod_opt_grps']} SET ";
            $sql3 = " WHERE pog_id={$this->pog_id}";
        }

        $sql2 = "pog_type = '" . DB_escapeString($this->pog_type) . "',
            pog_name = '" . DB_escapeString($this->pog_name) . "',
            pog_orderby='{$this->pog_orderby}'";
        $sql = $sql1 . $sql2 . $sql3;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql, 1);
        $err = DB_error();
        if ($err == '') {
            if ($this->isNew) {
                $this->pog_id = DB_insertID();
            }
            self::reOrder();
            //Cache::delete('prod_attr_' . $this->item_id);
            self::clearCache();
            return true;
        } else {
            $this->AddError($err);
            return false;
        }
    }


    /**
     * Delete the current attrribute group record from the database.
     *
     * @todo    Determine the effect on variants and orders
     * @param   integer $og_id    Option Group ID, empty for current object
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($og_id)
    {
        global $_TABLES;

        return true;        // stub until function works properly or is removed

        if ($og_id <= 0) {
            return false;
        }

        ProductOptionValue::deleteOptionGroup($og_id);
        DB_delete($_TABLES['shop.prod_opt_grps'], 'pog_id', $og_id);
        self::clearCache();
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
        if ($this->pog_name == '') {
            return false;
        }
        return true;
    }


    /**
     * Creates the edit form.
     *
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = new Template;
        $T->set_file('form', 'option_grp_form.thtml');
        $id = $this->pog_id;
        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit_og'] . ': ' . $this->pog_name);
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_og']);
        }

        $orderby_sel = $this->pog_orderby - 10;
        $T->set_var(array(
            'pog_id'        => $id,
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('option_grp_form', $_CONF['language']),
            'pog_name'      => $this->pog_name,
            'orderby_opts'  => COM_optionList($_TABLES['shop.prod_opt_grps'], 'pog_orderby,pog_name', $orderby_sel, 0),
            'orderby_last'  => $this->isNew ? 'selected="selected"' : '',
            'sel_' . $this->pog_type => 'selected="selected"',
        ) );

        $retval .= $T->parse('output', 'form');
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
     * Admin List View.
     *
     * @param   integer $cat_id     Optional attribute ID to limit listing
     * @return  string      HTML for the attribute list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $sql = "SELECT * FROM {$_TABLES['shop.prod_opt_grps']}";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'pog_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'pog_name',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['type'],
                'field' => 'pog_type',
                'sort' => false,
            ),
            array(
                'text'  => $LANG_SHOP['orderby'],
                'field' => 'pog_orderby',
                'align' => 'center',
                'sort'  => true,
            ),
            /*array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => 'false',
                'align' => 'center',
            ),*/
        );

        $defsort_arr = array(
            'field' => 'pog_orderby',
            'direction' => 'ASC',
        );
        $extra = array(
            'pog_count' => DB_count($_TABLES['shop.prod_opt_grps']),
        );

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_og'],
            SHOP_ADMIN_URL . '/index.php?pog_edit=0',
            array(
                'style' => 'float:left;',
                'class' => 'uk-button uk-button-success',
            )
        );
        $text_arr = array(
            'form_url' => SHOP_ADMIN_URL . '/index.php?opt_grp=x',
        );
        $query_arr = array(
            'table' => 'shop.prod_opt_grps',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );
        $filter = '';
        $options = array(
            'chkdelete' => true,
            'chkfield' => 'pog_id',
        );
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_og_list',
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
     * @param   array   $extra      Extra information passed in verbatim
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array('title' => $LANG_ADMIN['edit'])),
                SHOP_ADMIN_URL . "/index.php?pog_edit=x&amp;og_id={$A['pog_id']}"
            );
            break;

        case 'pog_orderby':
            if ($fieldvalue > 10) {
                $retval = COM_createLink(
                    Icon::getHTML('arrow-up'),
                    SHOP_ADMIN_URL . '/index.php?pog_move=up&id=' . $A['pog_id']
                );
            } else {
                $retval = '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            if ($fieldvalue < $extra['pog_count'] * 10) {
                $retval .= COM_createLink(
                    Icon::getHTML('arrow-down'),
                    SHOP_ADMIN_URL . '/index.php?pog_move=down&id=' . $A['pog_id']
                );
            } else {
                $retval .= '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            break;

        case 'delete':
            $retval .= COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/index.php?pog_del=x&amp;og_id=' . $A['og_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }


    /**
     * Clear cache entries related to attributes.
     */
    public static function clearCache()
    {
        Cache::clear('products');
        Cache::clear('attributes');
    }


    /**
     * Get the first OptionGroup object in the DB.
     * Used to determine the first element in selection lists.
     *
     * @uses    self::getAll()
     * @return  object      AttibuteGroup object.
     */
    public static function getFirst()
    {
        global $_TABLES;

        $grps = self::getAll();
        reset($grps);
        $retval = array_shift($grps);
        if ($retval === NULL) {
            $retval = new self;
        }
        return $retval;
    }


    /**
     * Add an Option object to the Options array.
     *
     * @param   object  $Opt    Option to add to this group
     */
    public function addOption($Opt)
    {
        $this->Options[$Opt->attr_id] = $Opt;
    }


    /**
     * Get all the option groups associated with a product.
     *
     * @param   integer $prod_id    Product ID
     * @return  array       Array of OptionGroup objects
     */
    public static function getByProduct($prod_id)
    {
        global $_TABLES;

        $prod_id = (int)$prod_id;
        static $retval = array();
        if (isset($retval[$prod_id])) {
            return $retval[$prod_id];
        }
        $retval[$prod_id] = array();
        //$cache_key = 'og_prod_' . $prod_id;
        //$grps = Cache::get($cache_key);
        //if ($grps === NULL) {
            //$grps = array();
            $sql = "SELECT DISTINCT pog.pog_id FROM {$_TABLES['shop.prod_opt_vals']} pov
                LEFT JOIN {$_TABLES['shop.prod_opt_grps']} pog ON pog.pog_id = pov.pog_id
                LEFT JOIN {$_TABLES['shop.variantXopt']} vxo ON vxo.pov_id = pov.pov_id
                LEFT JOIN {$_TABLES['shop.product_variants']} pv ON pv.pv_id = vxo.pv_id
                WHERE pv.item_id = $prod_id AND pv.enabled = 1
                ORDER BY pog.pog_orderby, pov.orderby asc";
            //echo $sql;die;
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$prod_id][$A['pog_id']] = new self($A['pog_id']);
                $retval[$prod_id][$A['pog_id']]->setOptionValues(
                    ProductOptionValue::getByProduct($prod_id, $A['pog_id'])
                );
            }
            //Cache::set($cache_key, $grps, self::$TAGS);
        //} else {
        //    $x = new ProductOptionValue;    // just to get the class loaded.
        //}
        return $retval[$prod_id];
    }


    /**
     * Get all the options related to this OptionGroup for a specific product.
     * Returns the results as well as sets the public Options property.
     *
     * @return  array       Array of Option objects
     */
    public function getOptions()
    {
        return $this->Options;
    }


    /**
     * Set the option values related to this option group.
     *
     * @param   array   $OptValues  Array of ProductOptionValue objects
     * @return  object  $this
     */
    public function setOptionValues($OptValues)
    {
        $this->Options = $OptValues;
        return $this;
    }


    /**
     * Get the record ID for this object.
     *
     * @return  integer Record ID
     */
    public function getID()
    {
        return $this->pog_id;
    }


    /**
     * Get the option group name.
     *
     * @return  string  OptionGroup name
     */
    public function getName()
    {
        return $this->pog_name;
    }


    /**
     * Set the name string for this optiongroup.
     *
     * @param   string  $name   Name to set
     */
    public function setName($name)
    {
        $this->pog_name = $name;
    }


    /**
     * Get the type of optiongroup (select, checkbox, etc.)
     *
     * @return  string  optiongroup type
     */
    public function getType()
    {
        return $this->pog_type;
    }

}

?>
