<?php
/**
 * Class to manage packages within a shipment.
 * Handles tracking numbers.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

namespace Shop;

/**
 * Class for order line items.
 * @package shop
 */
class ShipmentPackage
{
    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    private $properties = array();

    /** Fields for an ShipmentPackage record.
     * @var array */
    private static $fields = array(
        'pkg_id', 'shipment_id', 'shipper_id', 'shipper_info',
        'tracking_num', 'comment',
    );


    /**
     * Initializes the package item based on optional id or array.
     *
     * @param   integer $pkg_id  Package ID or record array
     * @uses    self::Read()
     */
    public function __construct($pkg_id = 0)
    {
        if (is_numeric($pkg_id) && $pkg_id > 0) {
            // Got an item ID, read from the DB
            $status = $this->Read($pkg_id);
            if (!$status) {
                $this->pkg_id = 0;
            }
        } elseif (is_array($pkg_id)) {
            // Got a shipment record, just set the variables
            $this->setVars($pkg_id);
        } else {
            $this->pkg_id = 0;
        }
    }


    /**
     * Get the packages associated with a shipment.
     *
     * @param   integer $shipment_id     Shipment ID
     * @return  array       Array of ShipmentPackage objects
     */
    public static function getByShipment($shipment_id)
    {
        global $_TABLES;

        $retval = array();
        $shipment_id = (int)$shipment_id;
        $sql = "SELECT * FROM {$_TABLES['shop.shipment_packages']}
            WHERE shipment_id = $shipment_id
            ORDER BY pkg_id ASC";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
        }
        return $retval;
    }


    /**
    * Load the record information.
    *
    * @param    integer $rec_id     DB record ID of item
    * @return   boolean     True on success, False on failure
    */
    public function Read($rec_id)
    {
        global $_TABLES;

        $rec_id = (int)$rec_id;
        $sql = "SELECT * FROM {$_TABLES['shop.shipment_packages']}
                WHERE pkg_id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            $this->setVars(DB_fetchArray($res, false));
            return true;
        } else {
            $this->shipment_id = 0;
            return false;
        }
    }


    /**
     * Set the object variables from an array.
     *
     * @param   array   $A      Array of values
     * @return  boolean     True on success, False if $A is not an array
     */
    public function setVars($A)
    {
        if (!is_array($A)) return false;

        foreach (self::$fields as $field) {
            if (isset($A[$field])) {
                $this->$field = $A[$field];
            }
        }
        return true;
    }


    /**
     * Setter function.
     *
     * @param   string  $key    Name of property to set
     * @param   mixed   $value  Value to set for property
     */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'pkg_id':
        case 'shipment_id':
        case 'shipper_id':
            $this->properties[$key] = (int)$value;
            break;
        default:
            $this->properties[$key] = trim($value);
            break;
        }
    }


    /**
     * Getter function.
     *
     * @param   string  $key    Property to retrieve
     * @return  mixed           Value of property, NULL if undefined
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    /**
     * Save a shipment package to the database.
     *
     * @param   array   $form   Array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save($form = NULL)
    {
        global $_TABLES;

        if (is_array($form)) {
            // This sets the base info, ShipmentItems are created after saving
            // the shipment.
            $this->setVars($form);
        }

        if (!$this->_isValidRecord()) {
            return false;
        }

        if ($this->pkg_id > 0) {
            // New shipment
            $sql1 = "UPDATE {$_TABLES['shop.shipment_packages']} ";
            $sql3 = " WHERE pkg_id = '{$this->pkg_id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.shipment_packages']} ";
            $sql3 = '';
        }
        $sql2 = "SET 
            shipment_id = '{$this->shipment_id}',
            shipper_id = '{$this->shipper_id}',
            shipper_info = '" . DB_escapeString($this->shipper_info) . "',
            tracking_num = '" . DB_escapeString($this->tracking_num) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //COM_errorLog($sql);
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->pkg_id <= 0) {
                $this->pkg_id = DB_insertID();
            }
            return true;
        } else {
            return false;
        }
    }


    /**
     * Delete a tracking record.
     *
     * @param   integer $pkg_id     Record ID for tracking item
     * @return  boolean     True
     */
    public static function Delete($pkg_id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.shipment_packages'], 'pkg_id', $pkg_id);
        return true;
    }


    /**
     * Get the shipper object associated with this package.
     *
     * @return  object      Shipper object
     */
    public function getShipper()
    {
        return Shipper::getInstance($this->shipper_id);
    }


    /**
     * Shortcut function to get the tracking URL for this package.
     *
     * @param   bolean  $internal   True to show tracking in a popup
     * @return  string  Tracking URL, empty string if not available.
     */
    public function getTrackingUrl($internal=true)
    {
        return $this->getShipper()->getTrackingURL($this->tracking_num, $internal);
    }


    /**
     * Verify that this package record is valid.
     *
     * @return  boolean     True if valid, False if not
     */
    private function _isValidRecord()
    {
        if ($this->shipper_info == '' && $this->tracking_num == '') {
            return false;
        }
        return true;
    }

}

?>
