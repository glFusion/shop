<?php
/**
 * Class to manage packages within a shipment.
 * Handles tracking numbers.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.2
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for order line items.
 * @package shop
 */
class ShipmentPackage
{
    /** Package record ID.
     * @var integer */
    private $pkg_id = 0;

    /** Record ID of shipment containing this package.
     * @var integer */
    private $shipment_id = 0;

    /** Record ID of the shipper for this package.
     * @var integer */
    private $shipper_id = 0;

    /** Information about the shipper.
     * @var string */
    private $shipper_info = '';

    /** Package tracking number.
     * @var string */
    private $tracking_num = '';

    /** General comment entered about the package.
     * @var string */
    private $comment = '';


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
        try {
            $data = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.shipment_packages']}
                WHERE shipment_id = ?
                ORDER BY pkg_id ASC",
                array($shipment_id),
                array(Database::INTEGER)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $retval[] = new self($A);
            }
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

        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.shipment_packages']} WHERE pkg_id = ?",
                array($rec_id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $this->setVars($row);
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

        $this->pkg_id = SHOP_getVar($A, 'pkg_id', 'integer', 0);
        $this->shipment_id = SHOP_getVar($A, 'shipment_id', 'integer', 0);
        $this->shipper_id = SHOP_getVar($A, 'shipper_id', 'integer', 0);
        $this->shipper_info = SHOP_getVar($A, 'shipper_info', 'string', '');
        $this->tracking_num = SHOP_getVar($A, 'tracking_num', 'string', '');
        $this->comment = SHOP_getVar($A, 'comment', 'string', '');
        return true;
    }


    /**
     * Get the package record ID.
     *
     * @return  integer     Record ID for the package
     */
    public function getID()
    {
        return (int)$this->pkg_id;
    }


    /**
     * Set the shipment ID for this package.
     *
     * @param   integer $id     Shipment record ID
     * @return  object  $this
     */
    public function setShipmentid($id)
    {
        $this->shipment_id = (int)$id;
        return $this;
    }


    /**
     * Get the shipment ID related to this package.
     *
     * @return  integer     Shipment record ID
     */
    public function getShipmentID()
    {
        return (int)$this->shipment_id;
    }


    /**
     * Set the shipper ID for this package.
     *
     * @param   integer $id     Shipper record ID
     * @return  object  $this
     */
    public function setShipperID($id)
    {
        $this->shipper_id = (int)$id;
        return $this;
    }


    /**
     * Get the shippper ID related to this package.
     *
     * @return  integer     Shipper record ID
     */
    public function getShipperID()
    {
        return (int)$this->shipper_id;
    }


    /**
     * Set the shipper information related to this package.
     *
     * @param   string  $shipper_info   Shipper information
     * @return  object  $this
     */
    public function setShipperInfo($shipper_info)
    {
        $this->shipper_info = $shipper_info;
        return $this;
    }


    /**
     * Get the shipper information for this package shipper.
     *
     * @return  string      Shipper information
     */
    public function getShipperInfo()
    {
        return $this->shipper_info;
    }


    /**
     * Set the tracking number for the package.
     *
     * @param   string  $tracking_num   Tracking number
     * @return  object  $this
     */
    public function setTrackingNum($tracking_num)
    {
        $this->tracking_num = $tracking_num;
        return $this;
    }


    /**
     * Get the tracking number for this package.
     *
     * @return  string      Tracking number
     */
    public function getTrackingNumber()
    {
        return $this->tracking_num;
    }


    /**
     * Save a shipment package to the database.
     *
     * @param   array   $form   Array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save(?array $form = NULL) : bool
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

        $values = array(
            'shipment_id' => $this->shipment_id,
            'shipper_id' => $this->shipper_id,
            'shipper_info' => $this->shipper_info,
            'tracking_num' => $this->tracking_num,
        );
        $types = array(
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
        );

        $db = Database::getInstance();
        try {
            if ($this->pkg_id > 0) {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.shipment_packages'],
                    $values,
                    array('pkg_id' => $this->pkg_id),
                    $types
                );
            } else {
                $db->conn->insert(
                    $_TABLES['shop.shipment_packages'],
                    $values,
                    $types
                );
                $this->pkg_id = $db->conn->lastInsertId();
            }
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        return false;
    }


    /**
     * Delete a tracking record.
     *
     * @param   integer $pkg_id     Record ID for tracking item
     * @return  boolean     True
     */
    public static function Delete(int $pkg_id) : bool
    {
        global $_TABLES;

        try {
            Database::getInstance()->conn->delete(
                $_TABLES['shop.shipment_packages'],
                array('pkg_id' => $pkg_id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
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

