<?php
/**
 * Class to manage product sale prices based on item or category.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Shop\Models\Dates;
use Shop\Models\DataArray;


/**
 * Class for product and category sales.
 * @package shop
 */
class Sales
{
    /** Base tag to use in creating cache IDs.
     * @var string */
    private static $base_tag = 'shop.sales';

    /** Sale record ID.
     * @var integer */
    private $sale_id = 0;

    /** Catalog item record ID.
     * @var integer */
    private $item_id = 0;

    /** Start date.
     * @var object */
    private $StartDate = NULL;

    /** Ending date.
     * @var object */
    private $EndDate = NULL;

    /** Type of discount, percent or amount.
     * @var string */
    private $discount_type = 'none';

    /** Sale name, e.g. `Father's Day Sale`.
     * @var string */
    private $name = '';

    /** Discount amount, either dollars or a percentage.
     * @var float */
    private $amount = 0;

    /** Item type for the discount, either `product` or `category`.
     * @var string */
    private $item_type = 'product';


    /**
     * Constructor. Sets variables from the provided array.
     *
     * @param   array   DB record
     */
    public function __construct($A=array())
    {
        if (is_array($A) && !empty($A)) {
            // DB record passed in, e.g. from _getSales()
            $this->setVars(new DataArray($A));
        } elseif (is_numeric($A) && $A > 0) {
            // single ID passed in, e.g. from admin form
            if (!$this->Read($A)) {
                $this->sale_id = 0;
            }
        } else {
            $this->setStartDate();
            $this->setEndDate();
        }
    }


    /**
     * Read a single record based on the record ID.
     *
     * @param   integer $id     DB record ID
     * @return  boolean     True on success, False on failure
     */
    public function Read(int $id) : bool
    {
        global $_TABLES;

        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.sales']} WHERE id = ?",
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
        }
        return false;
    }


    /**
     * Set the variables from a DB record into object properties.
     *
     * @param   array   $A      Array of properties
     * @param   boolean $fromDB True if reading from DB, False if from a form
     */
    public function setVars(DataArray $A, bool $fromDB=true)
    {
        $this->sale_id = $A->getInt('id');
        $this->setItemType($A->getString('item_type'));
        $this->discount_type = $A->getString('discount_type');
        $this->amount = $A->getFloat('amount');
        $this->name = $A->getString('name');
        if (!$fromDB) {
            // If coming from the form, convert individual fields to a datetime.
            // Use the minimum start date if none provided.
            if (empty($A['start'])) {
                $A['start'] = Dates::MIN_DATE;
            }
            // Use the minimum start time if none provided.
            if (isset($A['start_allday']) || empty($A['start_time'])) {
                $A['start'] = trim($A['start']) . ' ' . Dates::MIN_TIME;
            } else {
                $A['start'] = trim($A['start']) . ' ' . trim($A['start_time']);
            }
            // Use the maximum date if none is provided.
            if (empty($A['end'])) {
                $A['end'] = Dates::MAX_DATE;
            }
            // Use tme maximum time if none is provided.
            if (isset($A['end_allday']) || empty($A['end_time'])) {
                $A['end'] = trim($A['end']) . ' ' . Dates::MAX_TIME;
            } else {
                $A['end'] = trim($A['end']) . ' ' . trim($A['end_time']);
            }

            // Get the item type from the correct form field depending on
            // whether it's applied to a category or product.
            if ($this->item_type == 'product') {
                $this->item_id = $A->getInt('item_id');
            } else {
                $this->item_id = $A->getInt('cat_id');
            }
        } else {
            $this->item_id = $A->getInt('item_id');
        }
        $this->setStartDate($A->getString('start'));
        $this->setEndDate($A->getString('end'));
    }


    /**
     * Get all sales records for the specified type and item.
     * To facilitate caching, all sales records are retrieved and the requester
     * is responsible for selecting the currently-active sale.
     *
     * @see     self::getByProduct()
     * @see     self::getCategory()
     * @param   string  $type       Item type (product or category)
     * @param   integer $item_id    Product or Category ID
     * @return  array           Array of Sales objects
     */
    private static function _getSales($type, $item_id)
    {
        global $_TABLES;
        static $sales = array();

        if ($type != 'product') $type = 'category';
        $item_id = (int)$item_id;

        if (!array_key_exists($type, $sales)) {
            $sales[$type] = array();
        }
        if (!array_key_exists($item_id, $sales[$type])) {
            $cache_key = self::_makeCacheKey($type . '_' . $item_id);
            $sales[$type][$item_id] = Cache::get($cache_key);
            if ($sales[$type][$item_id] === NULL) {
                // If not found in cache
                $sales[$type][$item_id] = array();

                try {
                    $stmt = Database::getInstance()->conn->executeQuery(
                        "SELECT * FROM {$_TABLES['shop.sales']}
                        WHERE item_type = ? AND item_id = ?
                        ORDER BY start ASC",
                        array($type, $item_id),
                        array(Database::STRING, Database::INTEGER)
                    );
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                    $stmt = false;
                }
                if ($stmt) {
                    while ($A = $stmt->fetchAssociative()) {
                        $sales[$type][$item_id][] = new self($A);
                    }
                }
                Cache::set($cache_key, $sales[$type][$item_id], self::$base_tag);
            }
        }
        return $sales[$type][$item_id];
    }


    /**
     * Read all the sale prices for a category.
     *
     * @uses    self::_getSales()
     * @see     self::getEffective()
     * @param   integer $cat_id     Category ID
     * @return  array       Array of Sales objects
     */
    public static function getCategory($cat_id)
    {
        return self::_getSales('category', $cat_id);
    }


    /**
     * Read all the sale prices for a product.
     *
     * @uses    self::_getSales()
     * @see     self::getEffective()
     * @param   integer $item_id    Product ID
     * @return  array       Array of Sales objects
     */
    public static function getByProduct($item_id)
    {
        return self::_getSales('product', $item_id);
    }


    /**
     * Get the current active sales object for a product.
     * First check product sales, then categories.
     * Scans for the sale with the most recent start date. For example,
     * a long-term sale could have a short "flash sale" within it.
     *
     * @uses    self::getByProduct()
     * @uses    self::getCategory()
     * @param   object  $P  Product object
     * @return  object      Sales object, empty object if not found
     */
    public static function getEffective($P)
    {
        global $_CONF;

        $now = $_CONF['_now']->toUnix();
        $sales = self::getByProduct($P->getID());
        $SaleObj = NULL;
        foreach ($sales as $obj) {
            if (
                $obj->getStartDate()->toUnix() < $now &&
                $obj->getEndDate()->toUnix() > $now
            ) {
                // Found an active product sales, return it.
                $SaleObj = $obj;
            }
        }
        if ($SaleObj !== NULL) {
            return $SaleObj;
        }

        // If no product sales was found, look for a category.
        // Traverse the category tree from the current category up to
        // the root and return the first sales object found, if any.
        $cats = array();
        foreach ($P->getCategories() as $Cat) {
            $cats = array_merge($cats, $Cat->getPath(false));
        }
        $cats = array_reverse($cats);
        foreach ($cats as $cat) {
            $sales = self::getCategory($cat->getID());
            foreach ($sales as $obj) {
                if (
                    $obj->getStartDate()->toUnix() < $now &&
                    $obj->getEndDate()->toUnix() > $now
                ) {
                    $SaleObj = $obj;
                }
            }
            if ($SaleObj !== NULL) {
                return $SaleObj;
            }
        }
        // Return an empty object so Sales::getEffective->calcPrice()
        // will work.
        return new self;
    }


    /**
     * Set the sale starting date. Use the minimum value if none provided.
     *
     * @param   string  $value  Date/time string
     * @return  object  $this
     */
    public function setStartDate($value=NULL)
    {
        global $_CONF;

        if (empty($value)) {
            $value = self::minDateTime();
        }
        $this->StartDate = new \Date($value, $_CONF['timezone']);
    }


    /**
     * Get the starting date.
     *
     * @return  object      date object
     */
    public function getStartDate()
    {
        return $this->StartDate;
    }


    /**
     * Set the sale ending date. Use the minimum value if none provided.
     *
     * @param   string  $value  Date/time string
     * @return  object  $this
     */
    public function setEndDate($value=NULL)
    {
        global $_CONF;

        if (empty($value)) {
            $value = self::maxDateTime();
        }
        $this->EndDate = new \Date($value, $_CONF['timezone']);
    }


    /**
     * Get the ending date.
     *
     * @return  object      date object
     */
    public function getEndDate()
    {
        return $this->EndDate;
    }


    /**
     * Set the item type to either `product` or `category`.
     *
     * @param   string  $key    Product or Category
     * @return  object  $this
     */
    public function setItemType($key)
    {
        $this->item_type = $key == 'product' ? $key : 'category';
        return $this;
    }


    /**
     * Save the current values to the database.
     *
     * @param   DataArray   $A  Array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save(?DataArray $A=NULL)
    {
        global $_TABLES, $_SHOP_CONF;

        if (!empty($A)) {
            $this->setVars($A, false);
        }

        $values = array(
            'item_type' => $this->item_type,
            'item_id' => $this->item_id,
            'name' => $this->name,
            'start' => $this->StartDate->toMySQL(true),
            'end' => $this->EndDate->toMySQL(true),
            'discount_type' => $this->discount_type,
            'amount' => $this->amount,
        );
        $types = array(
            Database::STRING,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
        );
        $db = Database::getInstance();
        try {
            // Insert or update the record, as appropriate.
            if ($this->isNew()) {
                $db->conn->insert($_TABLES['shop.sales'], $values, $types);
                $this->sale_id = $db->conn->lastInsertId();
            } else {
                $types[] = Database::INTEGER;   // for sale_id
                $db->conn->update(
                    $_TABLES['shop.sales'],
                    $values,
                    array('id' => $this->sale_id),
                    $types
                );
            }
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }

        Cache::clear(self::$base_tag);
        return true;
    }


    /**
     * Delete a single sales record from the database.
     *
     * @param   integer $id     Record ID
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete(int $id) : bool
    {
        global $_TABLES;

        if ($id <= 0) {
            return false;
        }

        try {
            Database::getInstance()->conn->delete(
                $_TABLES['shop.sales'],
                array('id' => $id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        Cache::clear(self::$base_tag);
        return true;
    }


    /**
     * Clean out old sales records.
     * Called from runScheduledTask function.
     */
    public static function Clean()
    {
        global $_TABLES, $_CONF;

        
        $days = (int)Config::get('purge_sale_prices');
        if ($days > -1) {
            try {
                Database::getInstance()->conn->executeStatement(
                    "DELETE FROM {$_TABLES['shop.sales']}
                    WHERE end < DATE_SUB(?, INTERVAL ? DAY)",
                    array(
                        $_CONF['_now']->toMySQL(true),
                        $days
                    ),
                    array(
                        Database::STRING,
                        Database::INTEGER,
                    )
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
        }
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id Attributeal ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        // If there are no products defined, return a formatted error message
        // instead of the form.
        if (Database::getInstance()->getCount($_TABLES['shop.products']) == 0) {
            return SHOP_errorMessage($LANG_SHOP['todo_noproducts']);
        }

        if ($this->EndDate->toMySQL(true) == self::maxDateTime()) {
            $end_dt = '';
            $end_tm = '';
        } else {
            $end_dt = $this->EndDate->format('Y-m-d', true);
            $end_tm = $this->EndDate->format('H:i', true);
        }
        if ($this->StartDate->toMySQL(true) == self::minDateTime()) {
            $st_dt = '';
            $st_tm = '';
        } else {
            $st_dt = $this->StartDate->format('Y-m-d', true);
            $st_tm = $this->StartDate->format('H:i', true);
        }
        $T = new Template('admin');
        $T->set_file('form', 'sales_form.thtml');
        $retval = '';
        $T->set_var(array(
            'sale_id'       => $this->sale_id,
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('sales_form',
                                            $_CONF['language']),
            'amount'        => $this->amount,
            'product_select' => COM_optionList($_TABLES['shop.products'],
                    'id,name', $this->item_id),
            'category_select' => Category::optionList($this->item_id),
            'it_sel_' . $this->item_type => 'checked="checked"',
            'dt_sel_' . $this->discount_type => 'selected="selected"',
            'item_type'     => $this->item_type,
            'name'          => $this->name,
            'start_date'    => $st_dt,
            'end_date'      => $end_dt,
            'start_time'    => $st_tm,
            'end_time'      => $end_tm,
            'min_date'      => Dates::MIN_DATE,
            'min_time'      => Dates::MIN_TIME,
            'max_date'      => Dates::MAX_DATE,
            'max_time'      => Dates::MAX_TIME,
            'lang_new_or_edit' => $this->sale_id == 0 ? $LANG_SHOP['new_item'] : $LANG_SHOP['edit_item'],
        ) );
        if ($this->EndDate->format('H:i:s',true) == Dates::MAX_TIME) {
            $T->set_var(array(
                'end_allday_chk' => 'checked="checked"',
                'end_time_disabled' => 'disabled="disabled"',
            ) );
        }
        if ($this->StartDate->format('H:i:s',true) == Dates::MIN_TIME) {
            $T->set_var(array(
                'st_allday_chk' => 'checked="checked"',
                'st_time_disabled' => 'disabled="disabled"',
            ) );
        }
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
     * Helper function to create the cache key.
     *
     * @param   string  $id     Item ID, e.g. "category_1"
     * @return  string  Cache key
     */
    protected static function _makeCacheKey($id)
    {
        return self::$base_tag . '_' . $id;
    }


    /**
     * Calculate the sales price.
     * Always returns at least zero.
     *
     * @param  float   $price      Item base price
     * @return float               Salesed price
     */
    public function calcPrice($price)
    {
        switch ($this->discount_type) {
        case 'amount':
            $price -= $this->amount;
            break;
        case 'percent':
            $price = $price * (100 - $this->amount) / 100;
            break;
        case 'none':
        default:
            // An empty Sales object may be returned if there are no
            // sales. In that case, there's no sales to apply.
            break;
        }
        return max($price, 0);
    }


    /**
     * Sale Pricing Admin List View.
     *
     * @return  string      HTML for the product list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

        $sql = "SELECT * FROM {$_TABLES['shop.sales']}";
        if (!isset($_POST['show_inactive'])) {
            $sql .= " WHERE end >= '" . $_CONF['_now']->toMySQL(true) . "'";
        }

        $header_arr = array(
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'name',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['item_type'],
                'field' => 'item_type',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['item_name'],
                'field' => 'item_id',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['amount'] . '/' . $LANG_SHOP['percent'],
                'field' => 'amount',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['start'],
                'field' => 'start',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['end'],
                'field' => 'end',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'start',
            'direction' => 'ASC',
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $query_arr = array(
            'table' => 'shop.sales',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?sales=x',
        );

        $filter = 'Show Inactive?&nbsp;' . Field::checkbox(array(
            'name' => 'show_inactive',
            'id' => 'show_inactive',
            'checked' => isset($_POST['show_inactive']),
        ) );
        $options = array();
        $form_arr = array(
            'top' => FieldList::buttonLink(array(
                'url' => SHOP_ADMIN_URL . '/index.php?editsale=x',
                'text' => $LANG_SHOP['new_item'],
                'style' => 'success',
            ) ),
        );

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_discountlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, $form_arr
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the Sales admin list.
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
        static $Cur = NULL;
        static $Dt = NULL;
        if ($Cur === NULL) $Cur = Currency::getInstance();
        if ($Dt === NULL) $Dt = new \Date('now', $_CONF['timezone']);
        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . '/index.php?editsale&id=' . $A['id']
            ) );
            break;

        case 'delete':
            $retval = FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL . '/index.php?delsale&id=' . $A['id'],
                'attr' => array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            ) );
            break;

        case 'end':
        case 'start':
            //$Dt->setTimestamp((int)$fieldvalue);
            $Dt = new \Date($fieldvalue, $_CONF['timezone']);
            $retval = '<span class="tooltip" title="' .
                $Dt->format('Y-m-d H:i:s T', true) .
                '">' . $Dt->format('Y-m-d', true) . '</span>';
            break;

        case 'item_id':
            switch ($A['item_type']) {
            case 'product':
                $P = Product::getByID($fieldvalue);
                if ($P) {
                    $retval = $P->getDscp();
                } else {
                    $retval = 'Unknown';
                }
                break;
            case 'category':
                if ($fieldvalue == 0) {     // root category
                    $retval = $LANG_SHOP['home'];
                } else {
                    $retval = Category::getInstance($fieldvalue)->getName();
                }
                break;
            default;
                $retval = '';
                break;
            }
            break;

        case 'amount':
            switch ($A['discount_type']) {
            case 'amount':
                $retval = $Cur->format($fieldvalue);
                break;
            case 'percent':
                $retval = $fieldvalue . ' %';
                break;
            }
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Get the maximum datetime value allowed.
     *
     * @return  string  Maximum datetime value
     */
    private static function maxDateTime()
    {
        return Dates::MAX_DATE . ' ' . Dates::MAX_TIME;
    }


    /**
     * Get the minimum datetime value allowed.
     *
     * @return  string  Minimum datetime value
     */
    private static function minDateTime()
    {
        return Dates::MIN_DATE . ' ' . Dates::MIN_TIME;
    }


    /**
     * Get the sale price for a product attribute.
     * Only percentage discounts are applied to attributes.
     * If the sale is amount-based then the original price is returned.
     *
     * @param   float   $price  Original price
     * @return  float       Sale price.
     */
    public function getOptionPrice($price)
    {
        if ($this->discount_type == 'percent') {
            $price = $price * (100 - $this->amount) / 100;
            $price = Currency::getInstance()->RoundVal($price);
        }
        return $price;
    }


    /**
     * Get the name of the sale, e.g. "Black Friday".
     *
     * @return  string      Sale name
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Get the type of the discount, amount or percent.
     *
     * @return  string      Type of discount
     */
    public function getValueType()
    {
        return $this->discount_type;
    }


    /**
     * Get the value of the discount, either a percentage or amount.
     * Percent is in whole numbers, e.g. "15" for "15%".
     *
     * @return  float       Numeric value of discount
     */
    public function getValue()
    {
        return $this->amount;
    }


    /**
     * Get the formatted value of the discount amount.
     * Returns either the simple amount string for a percentage, or a
     * formatted dollar amount.
     *
     * @return  string      Formatted value
     */
    public function getFormattedValue()
    {
        if ($this->discount_type == 'amount') {
            return Currency::getInstance()->Format($this->amount);
        } else {
            return $this->amount;
        }
    }


    /**
     * Get the starting date.
     *
     * @return  object      Starting date object
     */
    public function getStart()
    {
        return $this->StartDate;
    }


    /**
     * Get the ending date.
     *
     * @return  object      Starting date object
     */
    public function getEnd()
    {
        return $this->EndDate;
    }


    /**
     * Check if this is a new record.
     *
     * @return  boolean     True if new, False if existing
     */
    public function isNew()
    {
        return $this->sale_id == 0;
    }

}

