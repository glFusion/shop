<?php
/**
 * Class to manage product checkbox options.
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
namespace Shop\Models;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Shop\FieldList;
use Shop\ProductOptionValue;


/**
 * Class for product checkboxes.
 * Similar to the ProductVariant but multiple selections are allowed.
 * @package shop
 */
class ProductCheckbox
{
    /** Array of error messages, to be accessible by the calling routines.
     * @var array */
    private $Errors = array();

    /** Record ID.
     * @var integer */
    private $x_id = 0;

    /** Product Item ID.
     * @var integer */
    private $item_id = 0;

    /** Product Option Value ID.
     * @var integer */
    private $pov_id = 0;

    /** Product Option Group ID.
     * @var integer */
    private $pog_id = 0;

    /** Product Option Group Name.
     * @var string */
    private $pog_name = '';

    /** Option value.
     * @var string */
    private $pov_value = '';

    /** Option price impact.
     * @var float */
    private $price = 0;


    /** Product Option Value object.
     * @var object */
    private $POV;


    /**
     * Reads in the specified option, if $id is an integer.
     * If $id is zero, then a new option is being created.
     * If $id is an array, then it is a complete DB record and the properties
     * just need to be set.
     *
     * @param   integer|array   $id Option record or record ID
     */
/*    public function __construct()
    {
            $id = (int)$id;
            if ($id < 1) {
                // New entry, set defaults
                $this->pov_id = 0;
                $this->item_id  = 0;
                $this->pov_value = '';
                $this->price = 0;
            } else {
                $this->pov_id =  $id;
                if (!$this->Read()) {
                    $this->pov_id = 0;
                }
            }
        }
    }
 */

    public static function fromArray(array $A) : self
    {
        $retval = new self;
        $retval->setVars($A);
        return $retval;
    }


    public static function getPriceImpact(int $item_id, array $opt_ids) : float
    {
        $retval = 0;
        $cbOpts = ProductCheckbox::getByProduct($item_id);
        foreach ($cbOpts as $opt_id=>$cbOpt) {
            if (in_array($opt_id, $opt_ids)) {
                $retval += $cbOpt->getPrice();
            }
        }
        return $retval;
    }


    /**
     * Sets all variables to the matching values from $row.
     *
     * @param   array $row Array of values, from DB or $_POST
     */
    public function setVars(array $row) : self
    {
        COM_errorLog("row: " . var_export($row,true));
        if (is_array($row)) {
            if (isset($row['x_id'])) {
                $this->x_id = (int)$row['x_id'];
            }
            $this->item_id = (int)$row['item_id'];
            $this->pov_id = (int)$row['pov_id'];
            $this->pog_id = (int)$row['pog_id'];
            $this->pog_name = $row['pog_name'];
            $this->pov_value = $row['pov_value'];
            $this->price = (float)$row['price'];
        }
        return $this;
    }


    public static function getByProduct($item_id) : array
    {
        global $_TABLES;

        $retval = array();
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT pov.*, pog.pog_name, x.price, x.item_id
                FROM {$_TABLES['shop.prodXcbox']} x
                LEFT JOIN {$_TABLES['shop.prod_opt_vals']} pov
                    ON x.pov_id = pov.pov_id
                LEFT JOIN {$_TABLES['shop.prod_opt_grps']} pog
                    ON pov.pog_id = pog.pog_id
                WHERE x.item_id = ?
                ORDER BY pog.pog_orderby ASC",
                array($item_id),
                array(Database::INTEGER)
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $retval[$A['pov_id']] = new self;
                $retval[$A['pov_id']]->setVars($A);
            }
        }
        return $retval;
    }

    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Option ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read() : bool
    {
        global $_TABLES;

        if ($this->item_id < 1 || $this->pov_id < 1) {
            return false;
        }

        $retval = array();
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT pov.*, pog.pog_name, x.price, x.item_id, x.x_id
                FROM {$_TABLES['shop.prodXcbox']} x
                LEFT JOIN {$_TABLES['shop.prod_opt_vals']} pov
                    ON x.pov_id = pov.pov_id
                LEFT JOIN {$_TABLES['shop.prod_opt_grps']} pog
                    ON pov.pog_id = pog.pog_id
                WHERE x.item_id = ? AND x.pov_id = ?
                ORDER BY pog.pog_orderby ASC",
                array($this->item_id, $this->pov_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            $this->setVars($data);
            return true;
        } else {
            return false;
        }
    }


    public function getOptionID() : int
    {
        return $this->pov_id;
    }

    public function getOptionValue() : string
    {
        return $this->pov_value;
    }

    public function getGroupID() : int
    {
        return $this->pog_id;
    }


    public function getGroupName() : string
    {
        return $this->pog_name;
    }


    /**
     * Enable or disable an option for an item.
     *
     * @param   integer $oldval Original value to be toggled
     * @param   integer $item_id    Product ID
     * @param   integer $pov_id ProductOptionValue ID
     * @return  integer     New value
     */
    public static function toggleEnabled(int $oldval, int $item_id, int $pov_id, float $opt_price=0) : int
    {
        global $_TABLES;

        $oldval = $oldval ? 1 : 0;
        $newval = $oldval ? 0 : 1;  // Plan to return the new value
        $db = Database::getInstance();
        $values = array($item_id, $pov_id);
        $types = array(Database::INTEGER, Database::INTEGER);
        if ($oldval) {
            // Was enabled, now disabling.
            $sql = "DELETE FROM {$_TABLES['shop.prodXcbox']} WHERE item_id = ? AND pov_id = ?";
        } else {
            // Was disabled, now enabling.
            $sql = "INSERT INTO {$_TABLES['shop.prodXcbox']} VALUES(0, ?, ?, ?)";
            $values[] = $opt_price;
            $types[] = Database::STRING;
        }
        try {
            $db->conn->executeStatement($sql, $values, $types);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            try {
                $db->conn->update(
                    $_TABLES['shop.prodXcbox'],
                    array('price' => (float)$opt_price),
                    array('item_id' => $item_id, 'pov_id' => $pov_id),
                    array(Database::STRING, Database::INTEGER, Database::INTEGER),
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $newval = $oldval;
        }
        return $newval;
    }


    public static function deleteByItem(int $item_id) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['shop.prodXcbox'],
                array('item_id' => $item_id),
                array(Database::INTEGER)
            );
            return true;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    public static function add(int $item_id, array $ids, array $prices) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        foreach ($ids as $pov_id=>$enabled) {
            $price = isset($prices[$pov_id]) ? (float)$prices[$pov_id] : 0;
            try {
                $db->conn->insert(
                    $_TABLES['shop.prodXcbox'],
                    array(
                        'item_id' => $item_id,
                        'pov_id' => $pov_id,
                        'price' => $price,
                    ),
                    array(
                        Database::INTEGER,
                        Database::INTEGER,
                        Database::STRING,
                    )
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
        }
    }


    /**
     * Product Option Value list view.
     *
     * @return  string      HTML for the attribute list.
     */
    public static function adminList(int $item_id) : string
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $sql = "SELECT pog.pog_name, pov.*, x.x_id, x.price, $item_id AS item_id
            FROM {$_TABLES['shop.prod_opt_vals']} pov
            LEFT JOIN {$_TABLES['shop.prod_opt_grps']} pog
            ON pov.pog_id = pog.pog_id
            LEFT JOIN {$_TABLES['shop.prodXcbox']} x
            ON x.pov_id = pov.pov_id AND x.item_id = $item_id
            WHERE pog.pog_type = 'checkbox'";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'pov_id',
                'sort' => true,
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
                'text' => $LANG_SHOP['opt_price'],
                'field' => 'price',
                'align' => 'right',
                'sort' => true,
            ),
        );

        $defsort_arr = array(
            'field' => 'pog.pog_orderby,pov.orderby',
            'direction' => 'ASC',
        );

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $def_filter = '';
        $query_arr = array(
            'table' => 'shop.prod_opt_values',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => $def_filter,
        );

        $text_arr = array();
        $filter = '';
        $options = array();
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_attrlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
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
                'name' => 'cbenabled[' . $A['pov_id'] . ']',
                'value' => '1',
                'checked' => $A['x_id'] != NULL,
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

        case 'price':
            $retval = FieldList::text(array(
                'name' => 'cbprice[' . $A['pov_id'] . ']',
                'value' => $fieldvalue,
            ) );
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }


    /**
     * Get the option selection for one option group.
     *
     * @param   integer     $pog_id     Option Group ID
     * @return  string      Option list for options under the group
     */
    public static function getSelectionByGroup($grp_id)
    {
        global $_TABLES;

        $pog_id = (int)$grp_id;
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
            $sql = "SELECT cb.* FROM {$_TABLES['shop.cb']} pov
                WHERE pov.pog_id = $pog_id
                ORDER BY pov.orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $opts[$A['pov_id']] = new self($A);
            }
            Cache::set($cache_key, $opts, array('shop.products', 'shop.prod_opt_vals'));
        }
        return $opts;
    }


    /**
     * Get the incremental price for this optionvalue.
     *
     * @return  float   Option price
     */
    public function getPrice() : float
    {
        return (float)$this->price;
    }


    /**
     * Get the text value for this option.
     *
     * @return  string  OptionValue value string
     */
    public function getValue() : string
    {
        return $this->dscp;
    }


    /**
     * Get the record ID for this item.
     *
     * @return  integer     Record ID
     */
    public function getID() : int
    {
        return $this->cb_id;
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
    public function withItemId(int $item_id) : self
    {
        $this->item_id = $item_id;
        return $this;
    }


    public function withOptionId(int $pov_id) : self
    {
        $this->pov_id = $pov_id;
        return $this;
    }
}

