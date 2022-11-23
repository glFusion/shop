<?php
/**
 * Class to manage product features.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.2
 * @since       v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Shop\Models\DataArray;


/**
 * Class for product features.
 * @package shop
 */
class Feature
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Table key, used by DBO class.
     * @var string */
    public static $TABLE = 'shop.features';

    /** ID Field name, used by DBO class.
     * @var string */
    protected static $F_ID = 'ft_id';

    /** Tag array used with caching, for consistency.
     * @var array */
    private static $TAGS = array('shop.products', 'shop.features');

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
            $this->setVars(new DataArray($id));
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
            try {
                $stmt = Database::getInstance()->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.features']}"
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($stmt) {
                while ($A = $stmt->fetchAssociative()) {
                    $retval[$A['ft_id']] = new self($A);
                }
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
    public function setVars(DataArray $A) : void
    {
        $this->ft_id = $A->getInt('ft_id');
        $this->fv_id = $A->getInt('fv_id');
        $this->ft_name = $A->getString('ft_name');
        $this->fv_text = $A->getString('fv_text');
        $this->orderby = $A->getInt('orderby', 9999);
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Optional ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read(int $id = 0) : bool
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->ft_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return false;
        }

        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.features']} WHERE ft_id = ? LIMIT 1",
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
     * @param   DataArray   $A  Array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save(?DataArray $A=NULL) : bool
    {
        global $_TABLES, $_SHOP_CONF;

        $reorder = false;
        if (!empty($A)) {
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
        $db = Database::getInstance();
        try {
            if ($ft_id > 0) {       // updating existing feature
                $db->conn->update(
                    $_TABLES['shop.features'],
                    array(
                        'ft_name' => $this->getName(),
                        'orderby' => $this->getOrderby(),
                    ),
                    array('ft_id' => $ft_id),
                    array(
                        Database::STRING,
                        Database::INTEGER,
                        Database::INTEGER,
                    )
                );
            } else {
                $db->conn->insert(
                    $_TABLES['shop.features'],
                    array(
                        'ft_name' => $this->getName(),
                        'orderby' => $this->getOrderby(),
                    ),
                    array(
                        Database::STRING,
                        Database::INTEGER,
                    )
                );
                $this->ft_id = $db->conn->lastInsertId();
            }
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $this->AddError($err);
            return false;
        }
        if ($reorder) {
            self::reOrder();
        }
        return true;
    }


    /**
     * Remove the current attrribute group record from the database.
     * Deletes the feature values, any product records using it, and
     * the feature record.
     *
     * @param   integer $ft_id      Record ID of the feature
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete(int $ft_id) : bool
    {
        global $_TABLES;

        $ft_id = (int)$ft_id;
        if ($ft_id <= 0) {
            return false;
        }
        $db = Database::getInstance();
        $values = array('ft_id' => $ft_id);
        $types = array(Databae::INTEGER);
        try {
            $db->conn->delete($_TABLES['shop.features_values'], $values, $types);
            $db->conn->delete($_TABLES['shop.prodXfeat'], $values, $types);
            $db->conn->delete($_TABLES['shop.features'], $values, $types);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
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

        $T = new Template('admin');
        $T->set_file('form', 'feature_form.thtml');
        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($this->ft_id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit_item'] . ': ' . $this->ft_name);
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_item'] . ': ' . $LANG_SHOP['features']);
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
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/index.php?ft_edit=0',
            'style' => 'success',
        ) );
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
            $retval .= FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . "/index.php?ft_edit={$A['ft_id']}",
            ) );
            break;

        case 'orderby':
            if ($fieldvalue > 10) {
                $retval = FieldList::up(array(
                    'url' => SHOP_ADMIN_URL . '/index.php?ft_move=up&id=' . $A['ft_id'],
                ) );
            } else {
                $retval = FieldList::space();
            }
            if ($fieldvalue < $extra['count'] * 10) {
                $retval .= FieldList::down(array(
                    'url' => SHOP_ADMIN_URL . '/index.php?ft_move=down&id=' . $A['ft_id'],
                ) );
            } else {
                $retval .= FieldList::space();
            }
            break;

        case 'delete':
            $retval .= FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL. '/index.php?ft_del=x&amp;ft_id=' . $A['ft_id'],
                'attr' => array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                ),
            ) );
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
    public static function getByProduct(int $prod_id) : array
    {
        global $_TABLES;

        //$cache_key = 'ft_prod_' . $prod_id;
        //$grps = Cache::get($cache_key);
        //if ($grps === NULL) {
        $grps = array();
        $qb = Database::getInstance()->conn->createQueryBuilder();
        $qb->select(
            'pf.prod_id', 'pf.ft_id', 'pf.fv_id',
            'f.ft_name', 'f.orderby',
            'IFNULL(pf.fv_text, fv.fv_value) AS fv_text'
        )
           ->from($_TABLES['shop.products'], 'p')
           ->leftJoin('p', $_TABLES['shop.prodXfeat'], 'pf', 'pf.prod_id = p.id')
           ->leftJoin('pf', $_TABLES['shop.features'], 'f', 'f.ft_id = pf.ft_id')
           ->leftJoin('pf', $_TABLES['shop.features_values'], 'fv', 'fv.fv_id = pf.fv_id')
           ->where('p.id = :prod_id')
           ->andWhere('pf.prod_id IS NOT NULL')
           ->orderBy('f.orderby', 'ASC')
           ->setParameter('prod_id', $prod_id, Database::INTEGER);

        try {
            $stmt = $qb->execute();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                $grps[$A['ft_id']] = new self($A);
            }
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
    public function addProduct(int $prod_id, int $fv_id, ?string $custom_text=NULL) : bool
    {
        global $_TABLES;

        if (!empty($custom_text)) {
            // Override the text and set the FV ID to zero.
            $text = $custom_text;
            $fv_id = 0;
        } else {
            // No custom text and use the FV ID provided.
            $text = NULL;
        }
        try {
            Database::getInstance()->conn->insert(
                $_TABLES['shop.prodXfeat'],
                array(
                    'prod_id' => $prod_id,
                    'ft_id' => $this->getID(),
                    'fv_id' => $fv_id,
                    'fv_text' => $text,
                ),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                )
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
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
    public function updateProduct(int $prod_id, int $fv_id, ?string $custom_text=NULL) : bool
    {
        global $_TABLES;

        if (!empty($custom_text)) {
            // Override the text and set the FV ID to zero.
            $text = $custom_text;
            $fv_id = 0;
        } else {
            // No custom text and use the FV ID provided.
            $text = NULL;
        }
        try {
            Database::getInstance()->conn->update(
                $_TABLES['shop.prodXfeat'],
                array(
                    'fv_id' => $fv_id,
                    'fv_text' => $text,
                ),
                array(
                    'prod_id' => $prod_id,
                    'ft_id' => $this->getID(),
                ),
                array(
                    Database::INTEGER,
                    Database::STRING,
                    Database::INTEGER,
                    Database::INTEGER,
                )
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Delete a product->feature mapping.
     * Called via AJAX.
     *
     * @param   integer $prod_id    Product record ID
     * @param   integer $ft_id      Feature record ID, -1 for all
     * @return  boolean     True on success, False on error
     */
    public static function deleteProduct(int $prod_id, ?int $ft_id = NULL) : bool
    {
        global $_TABLES;

        $values = array('prod_id' => $prod_id);
        $types = array(Database::INTEGER);
        if ($ft_id !== NULL) {
            $values['ft_id'] = $ft_id;
            $types[] = Database::INTEGER;
        }
        try {
            Database::getInstance()->conn->delete($_TABLES['shop.prodXfeat'], $values, $types);
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
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
    public static function cloneProduct(int $src, int $dst) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();

        // Clear target categories, the Home category is probably there.
        try {
            $db->conn->delete(
                $_TABLES['shop.prodXfeat'],
                array('prod_id' => $dst),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        try {
            $db->conn->executeStatement(
                "INSERT INTO {$_TABLES['shop.prodXfeat']}
                (prod_id, ft_id, fv_id, fv_text)
                SELECT ?, ft_id, fv_id, fv_text FROM {$_TABLES['shop.prodXfeat']}
                WHERE prod_id = ?",
                array($dst, $src),
                array(Database::INTEGER, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }

}

