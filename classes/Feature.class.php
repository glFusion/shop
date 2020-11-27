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
    use \Shop\Traits\DBO;        // Import database operations

    /** Table key, used by DBO class.
     * @var string */
    private static $TABLE = 'shop.features';

    /** ID Field name, used by DBO class.
     * @var string */
    private static $F_ID = 'ft_id';

    /** Tag array used with caching, for consistency.
     * @var array */
    private static $TAGS = array('products', 'features');

    /** Feature record ID.
     * @var integer */
    private $ft_id = 0;

    /** Current FeatureValue record ID.
     * @var integer */
    private $fv_id = 0;

    /** Feature name.
     * @var string */
    private $ft_name = '';

    /** FeatureValue text.
     * @var string */
    private $fv_text = '';

    /** Number to determine display order.
     * @var integer */
    private $orderby = 9999;

    /** FeatureValue objects associated with this feature.
     * @var array */
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
        $this->fv_id = SHOP_getVar($A, 'fv_id', 'integer', 0);
        $this->ft_name = $A['ft_name'];
        $this->fv_text = SHOP_getVar($A, 'fv_text', 'string', '');
        $this->orderby = SHOP_getVar($A, 'orderby', 'integer', 9999);
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

        $reorder = false;
        if (is_array($A) && !empty($A)) {
            if (!isset($A['orderby'])) {
                // Put this field at the end of the line by default.
                $A['orderby'] = 9999;
                $reorder = true;
            } elseif ($A['orderby'] != $this->getOrderby()) {
                // Bump the number from the "position after" value and
                // indicate that sorting is needed after saving.
                $A['orderby'] += 5;
                $reorder = true;
            }
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
            ft_id = {$ft_id},
            ft_name = '$ft_name',
            orderby = {$this->getOrderby()}
            ON DUPLICATE KEY UPDATE
            ft_name = '$ft_name',
            orderby = {$this->getOrderby()}";
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            if ($this->ft_id == 0) {
                $this->ft_id = DB_insertID();
            }
            if ($reorder) {
                self::reOrder();
            }
            return true;
        } else {
            $this->AddError($err);
            return false;
        }
    }


    /**
     * Remove the current attrribute group record from the database.
     * Deletes the feature values, any product records using it, and
     * the feature record.
     *
     * @param   integer $ft_id      Record ID of the feature
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($ft_id)
    {
        global $_TABLES;

        $ft_id = (int)$ft_id;
        if ($ft_id <= 0) {
            return false;
        }
        DB_delete($_TABLES['shop.features_values'], 'ft_id', $ft_id);
        DB_delete($_TABLES['shop.prodXfeat'], 'ft_id', $ft_id);
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

        $T = new Template;
        $T->set_file('form', 'feature_form.thtml');
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
            'orderby_opts'  => COM_optionList(
                $_TABLES['shop.features'],
                'orderby,ft_name',
                $this->orderby - 10,
                0,
                "ft_id <> {$this->ft_id}"
            ),
            'last_sel'      => $this->ft_id == 0 ? 'selected="selected"' : '',
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
            'field' => 'orderby',
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
        $text_arr = array();
        $filter = '';
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
        $grps = array();
        $sql = "SELECT  pf.prod_id, pf.ft_id, pf.fv_id,
                f.ft_name, f.orderby,
                IFNULL(pf.fv_text, fv.fv_value) AS fv_text
            FROM {$_TABLES['shop.products']} p
            LEFT JOIN {$_TABLES['shop.prodXfeat']} pf
                ON pf.prod_id = p.id
            LEFT JOIN {$_TABLES['shop.features']} f
                ON f.ft_id = pf.ft_id
            LEFT JOIN {$_TABLES['shop.features_values']} fv
                ON fv.fv_id = pf.fv_id
            WHERE p.id = $prod_id AND pf.prod_id IS NOT NULL
            ORDER BY f.orderby ASC";
        //echo $sql;die;
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
        return (int)$this->ft_id;
    }


    /**
     * Get the value record ID.
     *
     * @return  integer     FeatureValue record ID
     */
    public function getValueID()
    {
        return (int)$this->fv_id;
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
     * Get the orderby field to ensure it's sanitized as an integer.
     *
     * @return  integer     Feature orderby value
     */
    public function getOrderby()
    {
        return (int)$this->orderby;
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


    /**
     * Get the value text for this feature.
     *
     * @return  string      Feature text
     */
    public function getValue()
    {
        return $this->fv_text;
    }


    /**
     * Get the feature list to show on the product form.
     * Returns an array of current feature options plus an input row to
     * submit additional features.
     *
     * @param   integer $prod_id        Product record ID
     * @return  string      HTML for Feature  page in product form
     */
    public static function productForm($prod_id)
    {
        global $_TABLES;

        $T = new Template;
        $T->set_file('prod_feat', 'prod_feat_form.thtml');
        $T->set_var('prod_id', $prod_id);
        $Features = self::getByProduct($prod_id);
        $ft_ids = array();
        if ($Features) {
            foreach ($Features as $F) {
                $ft_ids[] = $F->getID();
            }
        }
        $T->set_var('ft_ids', json_encode($ft_ids));
        $T->set_block('prod_feat', 'FeatList', 'FL');
        foreach ($Features as $F) {
            $T->set_var(array(
                'prod_id'   => $prod_id,
                'ft_name'   => $F->getName(),
                'ft_id'     => $F->getID(),
                'fv_text'   => $F->getValueID() == 0 ? $F->getValue() : '',
                'fv_sel'    => FeatureValue::optionList($F->getID(), $F->getValueID()),
            ) );
            $T->parse('FL', 'FeatList', true);
        }
        $T->set_var(array(
            'ft_name_options' => self::optionList(),
        ) );

        $retval = $T->parse('output', 'prod_feat');
        return $T->parse('output', 'prod_feat');
    }


    /**
     * Add a product->feature mapping.
     * Called via AJAX.
     *
     * @param   integer $prod_id        Product record ID
     * @param   integer $fv_id          FeatureValue record ID
     * @param   string  $custom_text    Optional override text
     * @return  boolean     True on success, False on error
     */
    public function addProduct($prod_id, $fv_id, $custom_text='')
    {
        global $_TABLES;

        $prod_id = (int)$prod_id;
        $fv_id = (int)$fv_id;
        if (!empty($custom_text)) {
            // Override the text and set the FV ID to zero.
            $text = "'" . DB_escapeString($custom_text) . "'";
            $fv_id = 0;
        } else {
            // No custom text and use the FV ID provided.
            $text = 'NULL';
        }
        $sql = "INSERT INTO {$_TABLES['shop.prodXfeat']} SET
            prod_id = $prod_id,
            ft_id = {$this->getID()},
            fv_id = {$fv_id},
            fv_text = $text";
        $res = DB_query($sql,1);
        $err = DB_error($res);
        return $err === NULL ? true : false;
    }

    /**
     * Update an existing product->feature mapping.
     * Called via AJAX.
     *
     * @param   integer $prod_id        Product record ID
     * @param   integer $fv_id          FeatureValue record ID
     * @param   string  $custom_text    Optional override text
     * @return  boolean     True on success, False on error
     */
    public function updateProduct($prod_id, $fv_id, $custom_text='')
    {
        global $_TABLES;

        $prod_id = (int)$prod_id;
        $fv_id = (int)$fv_id;
        if (!empty($custom_text)) {
            // Override the text and set the FV ID to zero.
            $text = "'" . DB_escapeString($custom_text) . "'";
            $fv_id = 0;
        } else {
            // No custom text and use the FV ID provided.
            $text = 'NULL';
        }
        $sql = "UPDATE {$_TABLES['shop.prodXfeat']} SET
            fv_id = {$fv_id},
            fv_text = $text
            WHERE prod_id = $prod_id AND ft_id = {$this->getID()}";
        $res = DB_query($sql,1);
        $err = DB_error($res);
        return $err === NULL ? true : false;
    }


    /**
     * Delete a product->feature mapping.
     * Called via AJAX.
     *
     * @param   integer $prod_id    Product record ID
     * @param   integer $ft_id      Feature record ID, -1 for all
     * @return  boolean     True on success, False on error
     */
    public static function deleteProduct($prod_id, $ft_id = -1)
    {
        global $_TABLES;

        $args = array('prod_id');
        $vals = array((int)$prod_id);
        if ($ft_id > -1) {
            $args[] = 'ft_id';
            $vals[] = (int)$ft_id;
        }
        DB_delete($_TABLES['shop.prodXfeat'], $args, $vals);
        return DB_error() ? false : true;
    }


    /**
     * Get the selection options for features.
     * Returns the `<option></option>` tags for the selection list.
     *
     * @param   integer $sel    Currently-selected option
     * @param   array   $exclude    Array of feature IDs to exclude
     * @return  string      Option tags for selection
     */
    public static function optionList($sel=0, $exclude=array())
    {
        global $_TABLES;

        if (!empty($exclude)) {
            $exclude = 'ft_id NOT IN (' . implode(',', $exclude) . ')';
        } else {
            $exclude = '';
        }

        return COM_optionList(
            $_TABLES['shop.features'],
            'ft_id,ft_name',
            $sel,
            1,
            $exclude
        );
    }


    /**
     * Duplicate a feature set from one product to another.
     *
     * @param   integer $src    Source product record ID
     * @param   integer $dst    Destination product record ID
     * @return  boolean     True on success, False on error
     */
    public static function cloneProduct($src, $dst)
    {
        global $_TABLES;

        $src = (int)$src;
        $dst = (int)$dst;
        // Clear target categories, the Home category is probably there.
        DB_delete($_TABLES['shop.prodXfeat'], 'prod_id', $dst);
        $sql = "INSERT INTO {$_TABLES['shop.prodXfeat']}
            (prod_id, ft_id, fv_id, fv_text)
            SELECT $dst, ft_id, fv_id, fv_text FROM {$_TABLES['shop.prodXfeat']}
            WHERE prod_id = $src";
        DB_query($sql, 1);
        return DB_error() ? false : true;
    }

}

?>
