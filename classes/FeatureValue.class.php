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
    private $fv_value;


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
                $this->fv_value = '';
            } else {
                $this->fv_id =  $id;
                if (!$this->Read()) {
                    $this->fv_id = 0;
                }
            }
        }
    }


    /**
     * Get an instance of a FeatureValue object.
     *
     * @param   integer $fv_id  FeatureValue record ID
     * @return  object      FeatureValue object
     */
    public static function getInstance($fv_id)
    {
        static $fv_arr = array();
        if (!array_key_exists($fv_id, $fv_arr)) {
            $fv_arr[$fv_id] = new self($fv_id);
        }
        return $fv_arr[$fv_id];
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
        $this->fv_value = $row['fv_value'];
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
        $fv_value = DB_escapeString($this->fv_value);
        if ($this->fv_id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['shop.features_values']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.features_values']} SET ";
            $sql3 = " WHERE fv_id = $fv_id";
        }
        $sql2 = "ft_id = {$this->getFeatureID()}, fv_value = '$fv_value'";
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
            $this->fv_value == ''
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
        $this->fv_value = $val;
        return $this;
    }


    /**
     * Get the text value for this option.
     *
     * @return  string  OptionValue value string
     */
    public function getValue()
    {
        return $this->fv_value;
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


    /**
     * Get the options for a FeatureValue selection.
     *
     * @param   integer $ft_id  Feature record being used.
     * @param   integer $sel    Currently-selected option
     * @param   array   $exclude    Optional array of feature IDs to exclude
     * @return  string      HTML for option tags
     */
    public static function optionList($ft_id, $sel=0, $exclude=array())
    {
        global $_TABLES;

        $ft_id = (int)$ft_id;
        $where = "ft_id = $ft_id";
        if (!empty($exclude)) {
            $where .= 'AND fv_id NOT IN (' . implode(',', $exclude) . ')';
        }
        return COM_optionList(
            $_TABLES['shop.features_values'],
            'fv_id,fv_value',
            $sel,
            1,
            $where
        );
    }

}

?>
