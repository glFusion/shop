<?php
/**
 * Class to manage product features.
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
 * Class for product features.
 * @package shop
 */
class Feature
{
    /** Tag array used with caching, for consistency.
     * @var array */
    static $TAGS = array('products', 'features');

    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    public $isNew;

    private $ft_id;
    private $ft_name;
    private $fv_text;

    private $Values = NULL;

    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id Option Group ID
     */
    public function __construct($id=0)
    {
        $this->properties = array();
        $this->isNew = true;

        if (is_array($id)) {
            $this->setVars($id);
        } else {
            $id = (int)$id;
            if ($id < 1) {
                // New entry, set defaults
                $this->ft_id = 0;
                $this->ft_name = '';
                $this->feat_value = NULL;
            } else {
                $this->ft_id = $id;
                if (!$this->Read()) {
                    $this->ft_id = 0;
                    $this->ft_name = '';
                }
            }
        }
    }


    /**
     * Get all feature names.
     *
     * @return  array       Array of Feature objects
     */
    public static function getAll()
    {
        global $_TABLES;

        //$cache_key = 'shop_opt_grp_all';
        //$retval = Cache::get($cache_key);
        //if ($retval === NULL) {
            $retval = array();
            $sql = "SELECT * FROM {$_TABLES['shop.features']}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['ft_id']] = new self($A);
            }
        //    Cache::set($cache_key, $retval, self::$TAGS);
        //}
        return $retval;
    }


    /**
     * Get an instance of a specific feature.
     *
     * @param   integer $ft_id  Feature record ID
     * @return  object      ProductOptionGroup object
     */
    public static function getInstance($ft_id)
    {
        static $grps = NULL;
        if ($grps === NULL) {
            $grps = self::getAll();
        }
        if (array_key_exists($ft_id, $grps)) {
            return $grps[$ft_id];
        } else {
            return new self($ft_id);;
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
        $this->ft_id = (int)$A['ft_id'];
        $this->ft_name = $A['ft_name'];
        $this->fv_text = SHOP_getVar($A, 'fv_text', 'string', '');
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
        if ($id == 0) $id = $this->ft_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query(
            "SELECT * FROM {$_TABLES['shop.features']}
            WHERE ft_id='$id'
            LIMIT 1"
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

        $ft_id = $this->getId();
        $ft_name = DB_escapeString($this->getName());
        $sql = "INSERT INTO {$_TABLES['shop.features']} SET 
            ft_id = {$ft_id}
            ft_name = '$ft_name'
            ON DUPLICATE KEY UPDATE
            ft_name = '$ft_name'";
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            if ($this->ft_id == 0) {
                $this->ft_id = DB_insertID();
            }
            return true;
        } else {
            $this->AddError($err);
            return false;
        }
    }


    /**
     * Delete the current attrribute group record from the database.
     *
     * @param   integer $ft_id      Record ID of the feature
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($ft_id)
    {
        global $_TABLES;

        if ($ft_id <= 0) {
            return false;
        }

        DB_delete($_TABLRES['shop.features_values'], 'ft_id', $ft_id);
        DB_delete($_TABLES['shop.features'], 'ft_id', $ft_id);
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
        if ($this->ft_name == '') {
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

        $T = SHOP_getTemplate('feature_form', 'form');
        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($this->ft_id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit_ft'] . ': ' . $this->ft_name);
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_ft']);
        }

        $T->set_var(array(
            'ft_id'       => $this->ft_id,
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('feature_form'),
            'ft_name'      => $this->ft_name,
        ) );
        $T->set_block('form', 'FVList', 'FV');
        foreach ($this->getValues() as $FV) {
            $T->set_var(array(
                'fv_id' => $FV->getID(),
                'fv_text' => $FV->getValue(),
            ) );
            $T->parse('FV', 'FVList', true);
        }

        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
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

        $sql = "SELECT * FROM {$_TABLES['shop.features']}";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'ft_id',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['order'],
                'field' => 'orderby',
                'sort' => 'false',
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'ft_name',
                'sort' => false,
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => 'false',
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'ft_id',
            'direction' => 'ASC',
        );
        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_ft'],
            SHOP_ADMIN_URL . '/index.php?ft_edit=0',
            array(
                'style' => 'float:left;',
                'class' => 'uk-button uk-button-success',
            )
        );
        $query_arr = array(
            'table' => 'shop.features',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );
        $extra = array(
            'count' => DB_count($_TABLES['shop.features']),
        );
        $options = array('chkdelete' => true, 'chkfield' => 'ft_id');
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_feat_list',
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
                SHOP_ADMIN_URL . "/index.php?ft_edit={$A['ft_id']}"
            );
            break;

        case 'orderby':
            if ($fieldvalue > 10) {
                $retval = COM_createLink(
                    Icon::getHTML('arrow-up'),
                    SHOP_ADMIN_URL . '/index.php?ft_move=up&id=' . $A['ft_id']
                );
            } else {
                $retval = '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            if ($fieldvalue < $extra['count'] * 10) {
                $retval .= COM_createLink(
                    Icon::getHTML('arrow-down'),
                    SHOP_ADMIN_URL . '/index.php?ft_move=down&id=' . $A['ft_id']
                );
            } else {
                $retval .= '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            break;

        case 'delete':
            $retval .= COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/index.php?ft_del=x&amp;ft_id=' . $A['ft_id'],
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
     * Get all the option groups associated with a product.
     *
     * @param   integer $prod_id    Product ID
     * @return  array       Array of OptionGroup objects
     */
    public static function getByProduct($prod_id)
    {
        global $_TABLES;

        $prod_id = (int)$prod_id;
        //$cache_key = 'ft_prod_' . $prod_id;
        //$grps = Cache::get($cache_key);
        //if ($grps === NULL) {
            $sql = "SELECT  pf.*, f.ft_name
                FROM {$_TABLES['shop.products']} p
                LEFT JOIN {$_TABLES['shop.prodXfeat']} pf
                    ON pf.prod_id = p.id
                LEFT JOIN {$_TABLES['shop.features']} f
                    ON f.ft_id = pf.ft_id
                WHERE p.id = $prod_id
                ORDER BY f.orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $grps[$A['ft_id']] = new self($A);
            }
            //Cache::set($cache_key, $grps, self::$TAGS);
        //}
        return $grps;
    }


    /**
     * Get all the options related to this OptionGroup for a specific product.
     * Returns the results as well as sets the public Options property.
     *
     * @return  array       Array of FeatureValue objects
     */
    public function getValues()
    {
        $this->Values = FeatureValue::getByFeature($this->ft_id);
        return $this->Values;
    }


    /**
     * Get the record ID for this object.
     *
     * @return  integer Record ID
     */
    public function getID()
    {
        return $this->ft_id;
    }


    /**
     * Get the option group name.
     *
     * @return  string  OptionGroup name
     */
    public function getName()
    {
        return $this->ft_name;
    }


    /**
     * Set the name string for this optiongroup.
     *
     * @param   string  $name   Name to set
     */
    public function setName($name)
    {
        $this->ft_name = $name;
    }


    public function getValue()
    {
        return $this->fv_text;
    }


    /**
     * Update the feature display order.
     */
    public static function reOrder()
    {
        global $_TABLES;

        $sql = "SELECT ft_id, orderby
                FROM {$_TABLES['shop.features']}
                ORDER BY orderby ASC;";
        //echo $sql;die;
        $result = DB_query($sql);

        $order = 10;        // First orderby value
        $stepNumber = 10;   // Increment amount
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $changed = true;
                $sql = "UPDATE {$_TABLES['shop.features']}
                    SET orderby = '$order'
                    WHERE ft_id = '{$A['ft_id']}'";
                DB_query($sql);
            }
            $order += $stepNumber;
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
            $sql = "UPDATE {$_TABLES['shop.featuress']}
                    SET orderby = orderby $oper 11
                    WHERE ft_id = '{$this->ft_id}'";
            //echo $sql;die
            DB_query($sql);
            self::reOrder();
        }
    }

}

?>
