<?php
/**
 * Class to manage options/attributes associated with order line items.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use Shop\Log;
use Shop\Models\DataArray;


/**
 * Class for order line items.
 * @package shop
 */
class OrderItemOption
{
    /** TODO remove?
     */
    private $product = NULL;

    /** Indicate a new or empty option.
     * @var boolean */
    private $isEmpty = true;

    /** OrderItemOption DB record ID.
     * @var integer */
    private $oio_id = 0;

    /** Related OrderItem DB record ID.
     * @var integer */
    private $oi_id = 0;

    /** ProductOptionGroup record ID.
     * @var integer */
    private $pog_id = 0;

    /** ProductOptionValue record ID.
     * @var integer */
    private $pov_id = 0;

    /** Option Name for display.
     * @var string */
    private $oio_name = '';

    /** Option Value.
     * @var string */
    private $oio_value = '';

    /** Incremental option price.
     * @var float */
    private $oio_price = 0;

    /** Flag to indicate the object has been changed and needs saving.
     * @var boolean */
    private $_tainted = true;


    /**
     * Constructor.
     * Initializes the order item
     *
     * @param   integer $item   OrderItemObject record ID or array
     * @uses    self::Load()
     */
    public function __construct(int $item = 0)
    {
        $status = $this->Read($item);
        if (!$status) {
            $this->isEmpty = true;
            $this->oi_id = 0;
        } else {
            $this->isEmpty = false;
        }
    }


    /**
     * Create an instance from an array, e.g. from a database record.
     *
     * @param   array   $A      Array of values
     * @return  object      new OrderItem object
     */
    public static function fromArray(array $A) : self
    {
        $retval = new self;
        $retval->setVars(new DataArray($A));
        return $retval;
    }


    /**
    * Load the item information.
    *
    * @param    integer $rec_id     DB record ID of item
    * @return   boolean     True on success, False on failure
    */
    public function Read(int $rec_id) : bool
    {
        global $_SHOP_CONF, $_TABLES;

        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.oi_opts']} WHERE oio_id = ?",
                array($rec_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $this->setVars(new DataArray($row));
            $this->unTaint();
            return true;
        } else {
            return false;
        }
    }


    /**
     * Set the object variables from an array.
     *
     * @param   array   $A      Array of values
     * @return  object  $this
     */
    public function setVars(DataArray $A) : self
    {
        $this->oio_id = $A->getInt('oio_id');
        $this->oi_id = $A->getInt('oi_id');
        $this->pog_id = $A->getInt('pog_id');
        $this->pov_id = $A->getInt('pov_id');
        $this->oio_name = $A->getString('oio_name');
        $this->oio_value = $A->getString('oio_value');
        $this->oio_price = $A->getFloat('oio_price');
        return $this;
    }


    /**
     * Create an OrderItemOption record from a ProductOptionValue.
     *
     * @param   object  $POV        ProductOptionValue object
     * @return  object  $this
     */
    public function fromProductOptionValue(object $POV) : object
    {
        $this->pog_id = $POV->getGroupID();
        $this->pov_id = $POV->getID();
        $this->oio_name = ProductOptionGroup::getInstance($POV->getGroupID())->getName();
        $this->oio_value = $POV->getValue();
        $this->oio_price = $POV->getPrice();
        $this->Taint();
        return $this;
    }


    /**
     * Set the OrderItem record ID property.
     *
     * @param   integer $id     OrderItem record ID
     * @return  object  $this
     */
    public function setOrderItemID($id)
    {
        if ($this->oi_id != $id) {
            $this->Taint();
        }
        $this->oi_id = (int)$id;
        return $this;
    }


    /**
     * Get the options associated with an order item.
     *
     * @param   object  $OI OrderItem object
     * @return  array       Array of OrderItemOption objects
     */
    public static function getOptionsForItem(OrderItem $OI)
    {
        global $_TABLES;

        static $retval = array();
        $item_id = $OI->getID();
        if ($item_id < 1) {
            // Catch bad or empty Item objects
            return array();
        }

        if (isset($retval[$item_id])) {
            return $retval[$item_id];
        }

        $retval[$item_id] = array();
        //$cache_key = "oio_item_{$Item->id}";
        //$retval = Cache::get($cache_key);
        //if ($retval === NULL) {
        //$retval = array();
        try {
            $rows = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.oi_opts']} WHERE oi_id = ? ORDER BY oio_id ASC",
                array($item_id),
                array(Database::INTEGER)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $rows = false;
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $retval[$item_id][] = self::fromArray($row);
            }
        }
        //    Cache::set($cache_key, $retval, array('order_' . $Item->order_id));
        //}
        return $retval[$item_id];
    }


    /**
     * Save an order item to the database. Only new records can be added.
     *
     * @return  boolean     True on success, False on DB error
     */
    public function Save() : bool
    {
        global $_TABLES;

        try {
            Database::getInstance()->insert(
                $_TABLES['shop.oi_opts'],
                array(
                    'oi_id' => $this->oi_id,
                    'pog_id' => $this->pog_id,
                    'pov_id' => $this->pov_id,
                    'oio_name' => $this->oio_name,
                    'oio_value' => $this->oio_value,
                    'oio_price' => $this->oio_price,
                ),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                )
            );
            $this->oio_id = $db->conn->lastInserId();
            return true;
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Set the Option attributes from the attibute table.
     * Allows for a standad option, or for a custom name/value pair.
     *
     * @param   integer $pov_id     Product Option Value ID, zero to use name/value
     * @param   string  $name       Name of custom field
     * @param   string  $value      Value of custom field
     * @return  object  $this
     */
    public function setOpt(int $pov_id, string $name='', string $value='') : self
    {
        if ($pov_id > 0) {
            $POV = new ProductOptionValue($pov_id);
            if ($POV->getID() > 0) {
                // Have a valid object
                $POG = new ProductOptionGroup($POV->getGroupID());
                $this->pov_id = $POV->getID();
                $this->pog_id = $POG->getID();;
                $this->oio_name = $POG->getName();
                $this->oio_value = $POV->getValue();
                $this->oio_price = $POV->getPrice();
            }
        } elseif ($name != '' && $value != '') {
            $this->pov_id = 0;
            $this->pog_id = 0;
            $this->oio_name = $name;
            $this->oio_value = $value;
            $this->oio_price = 0;
        }
        $this->Taint();
        return $this;
    }


    /**
     * Delete all options related to a specified OrderItem.
     *
     * @param   integer $oi_id  OrderItem record ID
     * @param   boolean     True on success, False on error
     */
    public static function deleteItem(int $oi_id) : bool
    {
        global $_TABLES;

        try {
            Database::getInstance()->delete(
                $_TABLES['shop.oi_opts'],
                array('oi_id' => $oi_id),
                array(Database::INTEGER)
            );
            return true;
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Check if this option object matches the supplied object.
     *
     * @param   object  $Attr2  Second attribute to check
     * @return  boolean     True if the objects match, False if not.
     */
    public function Matches(self $Attr2) : bool
    {
        $flds_to_check = array(
            'pog_id', 'pov_id',
            'oio_name', 'oio_value',
            'oio_price',
        );
        foreach ($flds_to_check as $fldname) {
            if ($this->$fldname != $Attr2->$fldname) {
                return false;
            }
        }
        return true;
    }


    /**
     * Check whether all the option objects match.
     *
     * @uses    self::Matches()
     * @param   array   $arr1   Array of OrderItemOption objects
     * @param   array   $arr2   Array of OrderItemOption objects
     * @return  boolean     True if all objects match
     */
    public static function MatchAll($arr1, $arr2)
    {
        // Different number of options, can't match
        if (count($arr1) != count($arr2)) {
            return false;
        }
        foreach ($arr1 as $idx=>$Attr1) {
            $Attr2 = $arr2[$idx];
            if (!$Attr1->Matches($Attr2)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Get the record ID for this item option
     *
     * @return  integer     DB record ID
     */
    public function getID()
    {
        return $this->oio_id;
    }


    /**
     * Get the name of the item option, e.g. "color".
     *
     * @return  string      Name of option
     */
    public function getName()
    {
        return $this->oio_name;
    }


    /**
     * Get the price for this item option.
     *
     * @return  float       Option price
     */
    public function getPrice()
    {
        return $this->oio_price;
    }


    /**
     * Get the value (text string) of the option.
     *
     * @return  string      Option value
     */
    public function getValue()
    {
        return $this->oio_value;
    }

    /**
     * Get the product option value ID for this opton.
     *
     * @return  integer     Product Option Value ID
     */
    public function getOptionID()
    {
        return $this->pov_id;
    }


    /**
     * Save this item if it has been changed.
     *
     * @return  object  $this
     */
    public function saveIfTainted() : object
    {
        if ($this->isTainted()) {
            $this->Save();
        }
        return $this;
    }


    /**
     * Check if the record is "tainted" by values being changed.
     *
     * @return  boolean     True if tainted and needs to be saved
     */
    public function isTainted() : bool
    {
        return $this->_tainted;
    }


    /**
     * Taint this object, indicating that something has changed.
     *
     * @return  object  $this
     */
    public function Taint() : object
    {
        $this->_tainted = true;
        return $this;
    }


    /**
     * Remove the taint flag.
     *
     * @return  object  $this
     */
    public function unTaint() : object
    {
        $this->_tainted = false;
        return $this;
    }


    /**
     * Set the OrderItem record ID.
     *
     * @param   integer $id     OrderItem ID
     * @return  object  $this
     */
    public function withOrderItemId(int $id) : self
    {
        $this->oi_id = $id;
        return $this;
    }


    /**
     * Set the OptionGroup record ID.
     *
     * @param   integer $id     OptionGroup ID
     * @return  object  $this
     */
    public function withOptionGroupId(int $id) : self
    {
        $this->pog_id = $id;
        return $this;
    }


    /**
     * Set the OptionValue record ID.
     *
     * @param   integer $id     OptionValue ID
     * @return  object  $this
     */
    public function withOptionValueId(int $id) : self
    {
        $this->pov_id = $id;
        return $this;
    }


    /**
     * Set the Option Name text.
     *
     * @param   string  $name   Option Name
     * @return  object  $this
     */
    public function withOptionName(string $name) : self
    {
        $this->oio_name = $name;
        return $this;
    }


    /**
     * Set the Option Value text.
     *
     * @param   string  $value  Option Value
     * @return  object  $this
     */
    public function withOptionValue(string $value) : self
    {
        $this->oio_value = $value;
        return $this;
    }


    /**
     * Set the Option Price amount.
     *
     * @param   float   $value  Option price impact
     * @return  object  $this
     */
    public function withOptionPrice(float $value) : self
    {
        $this->oio_price = $value;
        return $this;
    }

}
