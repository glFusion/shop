<?php
/**
 * Class to manage multi-use discount codes.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Models\Dates;


/**
 * Class for multi-use discount codes.
 * Discount codes are strings that can be entered at checkout to get a discount.
 * These may be for seasonal discounts, online specials, etc.
 * @package shop
 */
class DiscountCode
{
    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    public $isNew = true;

    /** DB Record ID.
     * @var integer */
    private $code_id = 0;

    /** Actual code string.
     * @var string */
    private $code = '';

    /** Percentage discount when the code is used.
     * @var float */
    private $percent = 0;

    /** Starting date/time. Date object.
     * @var object */
    private $start = NULL;

    /** Expiration date/time. Date object.
     * @var object */
    private $end = NULL;

    /** Minimum net order value to allow the code to be used.
     * @var float */
    private $min_order = 0;

    /** Message text regarding application of a code.
     * @var string */
    private $messages = array();


    /**
     * Constructor. Sets variables from the provided array.
     *
     * @param   array   DB record
     */
    public function __construct($A=array())
    {
        // New entry, set defaults
        $this->code_id = 0;
        $this->percent = 0;
        $this->code = '';
        if (is_array($A) && !empty($A)) {
            $this->setVars($A);
        } elseif (is_numeric($A) && $A > 0) {
            // single ID passed in, e.g. from admin form
            $this->Read($A);
        } else {
            // New entry, set defaults
            $this->setCodeID(0)
                ->setPercent(0)
                ->setCode('')
                ->setStart(self::minDateTime())
                ->setEnd(self::maxDateTime())
                ->setMinOrder(0);
        }
    }


    /**
     * Get a discount code record.
     *
     * @param   string  $code   Discount code string
     * @return  object  $this
     */
    public static function getInstance($code)
    {
        global $_TABLES;

        static $retval = array();

        if (!isset($retval[$code])) {
            $sql = "SELECT * FROM {$_TABLES['shop.discountcodes']}
                WHERE code = '" . DB_escapeString(strtoupper($code)) . "'";
            $res = DB_query($sql);
            if ($res) {
                $A = DB_fetchArray($res, false);
                $retval[$code] = new self($A);
            } else {
                $retval[$code] = new self;
            }
        }
        return $retval[$code];
    }


    /**
     * Read a single record based on the record ID.
     *
     * @param   integer $id     DB record ID
     * @return  boolean     True on success, False on failure
     */
    public function Read($id)
    {
        global $_TABLES;

        $id = (int)$id;
        $sql = "SELECT * FROM {$_TABLES['shop.discountcodes']}
                WHERE code_id = $id";
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            $this->setVars($A);
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
    public function setVars($A, $fromDB=true)
    {
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
        }
        $this->setCodeID(SHOP_getVar($A, 'code_id', 'integer'))
            ->setCode(SHOP_getVar($A, 'code'))
            ->setPercent(SHOP_getVar($A, 'percent', 'float'))
            ->setStart(SHOP_getVar($A, 'start'))
            ->setEnd(SHOP_getVar($A, 'end'))
            ->setMinOrder(SHOP_getVar($A, 'min_order', 'float'));
        return $this;;
    }


    /**
     * Set the discount code record ID.
     *
     * @param   string  $code_id    DB Record ID
     * @return  object  $this
     */
    public function setCodeID($code_id)
    {
        $this->code_id = (int)$code_id;
        return $this;
    }


    /**
     * Get the record ID of the code.
     *
     * @return  integer     Record ID
     */
    public function getCodeID()
    {
        return (int)$this->code_id;
    }


    /**
     * Set the code value.
     *
     * @param   string  $code       Discount code string
     * @return  object  $this
     */
    public function setCode($code)
    {
        $this->code = strtoupper($code);
        return $this;
    }


    /**
     * Get the code string.
     *
     * @return  string      Discount code
     */
    public function getCode()
    {
        return $this->code;
    }


    /**
     * Set the discount percentage.
     *
     * @param   float   $percent    Percentage amount
     * @return  object  $this
     */
    public function setPercent($percent)
    {
        $this->percent = (float)$percent;
        return $this;
    }


    /**
     * Get the expiration date/time object.
     *
     * @return  object      Date object
     */
    public function getPercent()
    {
        return $this->percent;
    }


    /**
     * Set the starting date object.
     *
     * @param   string  $dt_time    Datetime string.
     * @return  object  $this
     */
    public function setStart($dt_time)
    {
        global $_CONF;

        $this->start = new \Date($dt_time, $_CONF['timezone']);
        return $this;
    }


    /**
     * Get the starting date/time object.
     *
     * @return  object      Date object
     */
    public function getStart()
    {
        return $this->start;
    }


    /**
     * Set the expiration date object.
     *
     * @param   string  $dt_time    Datetime string.
     * @return  object  $this
     */
    public function setEnd($dt_time)
    {
        global $_CONF;

        $this->end = new \Date($dt_time, $_CONF['timezone']);
        return $this;
    }


    /**
     * Get the expiration date/time object.
     *
     * @return  object      Date object
     */
    public function getEnd()
    {
        return $this->end;
    }


    /**
     * Set the minimum order amount.
     *
     * @param   float   $amt    Minimum order amount
     * @return   object $this
     */
    public function setMinOrder($amt)
    {
        $this->min_order = (float)$amt;
        return $this;
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

        if (is_array($A)) {
            // Saving from a form
            $this->setVars($A, false);
        }

        // Insert or update the record, as appropriate.
        $sql = "INSERT INTO {$_TABLES['shop.discountcodes']} SET
            code_id = '" . (int)$this->code_id . "',
            code = '" . DB_escapeString($this->code) . "',
            percent = '" . (float)$this->percent . "',
            min_order  = '" . (float)$this->min_order . "',
            start = '" . DB_escapeString($this->start->toMySQL(true)) . "',
            end = '" . DB_escapeString($this->end->toMySQL(true)) . "'
            ON DUPLICATE KEY UPDATE
            code = '" . DB_escapeString($this->code) . "',
            percent = '" . (float)$this->percent . "',
            start = '" . DB_escapeString($this->start->toMySQL(true)) . "',
            end = '" . DB_escapeString($this->end->toMySQL(true)) . "',
            min_order = '" . (float)$this->min_order . "'";
        //echo $sql;die;
        DB_query($sql, 1);
        $err = DB_error('An invalid or duplicate code was entered');
        if ($err == '') {
            return true;
        } else {
            SHOP_setMsg($err);
            return false;
        }
    }


    /**
     * Delete a single discount record from the database.
     *
     * @param   integer $id     Record ID
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($id)
    {
        global $_TABLES;

        if ($id <= 0) {
            return false;
        }

        DB_delete($_TABLES['shop.discountcodes'], 'code_id', $id);
        return true;
    }


    /**
     * Clean out old discount code records.
     * Called from runScheduledTask function.
     */
    public static function Clean()
    {
        global $_CONF, $_TABLES;

        $now = $_CONF['_now']->toMySQL(true);
        $sql = "DELETE FROM {$_TABLES['shop.discountcodes']}
                WHERE end < '$now'";
        DB_query($sql);
    }


    /**
     * Validate a customer-entered discount code.
     *
     * @param   object  $Cart   Cart object
     * @return  float       Percentage discount, NULL if invalid
     */
    public function Validate($Cart)
    {
        global $_CONF, $LANG_SHOP;

        $now = $_CONF['_now']->toMySQL(true);
        if ($this->code_id < 1) {  // discount code not created yet
            $this->messages[] = sprintf($LANG_SHOP['coupon_apply_msg3'], $_CONF['site_mail']);
            return NULL;
        } elseif (
            $now > $this->getEnd()->toMySQL(true) ||
            $now < $this->getStart()->toMySQL(true)
        ) {
            $this->messages[] = $LANG_SHOP['dc_expired'];
            return NULL;
        }

        // Get the item total from the order, excluding products that
        // do not allow discount codes
        $gross_items = $Cart->getItemTotal();
        foreach($Cart->getItems() as $Item) {
            if (!$Item->getProduct()->canApplyDiscountCode()) {
                $gross_items -= $Item->getGrossExtension();
            }
        }
        if ($gross_items < $Cart->getItemTotal()) {
            $this->messages[] = $LANG_SHOP['dc_items_excluded'];
        }
        if ($this->min_order > (float)$gross_items) {  // order doesn't meet minimum
            $this->messages[] = sprintf(
                $LANG_SHOP['min_order_not_met'],
                Currency::getInstance()->Format($this->min_order)
            );
            return NULL;
        }

        $this->messages[] = $LANG_SHOP['dc_applied'];
        return $this->getPercent();
    }


    /**
     * Get the message indicating why the code was rejected.
     *
     * @return  string      Message text
     */
    public function getMessage()
    {
        $cnt = count($this->messages);
        if ($cnt == 0) {
            return '';
        } elseif ($cnt == 1) {
            return $this->messages[0];
        } elseif ($cnt > 1) {
            return '<ul><li>' . implode('</li><li>', $this->messages) . '</li></ul>';
        }
    }


    /**
     * Calculate the discounted price.
     * Always returns at least zero.
     *
     * @param  float   $price      Item base price
     * @return float               Discounted price
     */
    public function calcPrice($price)
    {
        $price = $price * (100 - $this->getPercent()) / 100;
        return max($price, 0);
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id Attributeal ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP;

        if ($this->end->toMySQL(true) == self::maxDateTime()) {
            $end_date = '';
            $end_time = '';
        } else {
            $end_date = $this->end->format('Y-m-d', true);
            $end_time = $this->end->format('H:i', true);
        }
        if ($this->start->toMySQL(true) == self::minDateTime()) {
            $start_date = '';
            $start_time = '';
        } else {
            $start_date = $this->start->format('Y-m-d', true);
            $start_time = $this->start->format('H:i', true);
        }
        $T = new Template('admin');
        $T->set_file('form', 'discount_code.thtml');
        $retval = '';
        $T->set_var(array(
            'code_id'       => $this->code_id,
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('discount_code',
                                            $_CONF['language']),
            'percent'       => $this->percent,
            'code'          => $this->code,
            'start_date'    => $start_date,
            'start_time'    => $start_time,
            'end_date'      => $end_date,
            'end_time'      => $end_time,
            'min_date'      => Dates::MIN_DATE,
            'min_time'      => '00:00',
            'max_date'      => Dates::MAX_DATE,
            'max_time'      => '23:59',
            'min_order'     => $this->min_order,
            'lang_new_or_edit' => $this->code_id == 0 ? $LANG_SHOP['new_item'] : $LANG_SHOP['edit_item'],
        ) );
        if ($this->end->format('H:i:s',true) == Dates::MAX_TIME) {
            $T->set_var(array(
                'exp_allday_chk' => 'checked="checked"',
                'end_time_disabled' => 'disabled="disabled"',
            ) );
        }
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
     * Discount Code Admin List View.
     *
     * @return  string      HTML for the product list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

        $sql = "SELECT * FROM {$_TABLES['shop.discountcodes']}";

        $header_arr = array(
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'align' => 'center',
            ),
            array(
                'text' => 'ID',
                'field' => 'code_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['code'],
                'field' => 'code',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['start'],
                'field' => 'start',
                'align' => 'center',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['end'],
                'field' => 'end',
                'align' => 'center',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['percent'],
                'field' => 'percent',
                'align' => 'right',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['min_order'],
                'field' => 'min_order',
                'align' => 'right',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'end',
            'direction' => 'ASC',
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $query_arr = array(
            'table' => 'shop.discountcodes',
            'sql' => $sql,
            'query_fields' => array('code'),
            'default_filter' => '',
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/index.php?discountcodes',
        );

        $display .= '<div>' . COM_createLink($LANG_SHOP['new_item'],
            SHOP_ADMIN_URL . '/index.php?editcode=x',
            array('class' => 'uk-button uk-button-success')
        ) . '</div>';
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_codelist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the Discount Code admin list.
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
        if ($Cur === NULL) $Cur = Currency::getInstance();

        $retval = '';
        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink(
                Icon::getHTML('edit'),
                SHOP_ADMIN_URL . '/index.php?editcode&code_id=' . $A['code_id']
            );
            break;

        case 'delete':
            $retval = COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL . '/index.php?delcode&code_id=' . $A['code_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

        case 'end':
        case 'start':
            $Dt = new \Date($fieldvalue, $_CONF['timezone']);
            $retval = $Dt->toMySQL(true);
            break;

        case 'percent':
            $retval = $fieldvalue . ' %';
            break;

        case 'min_order':
            $retval = $Cur->formatValue($fieldvalue);
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
     * See if there are any current discount codes available.
     * Used in the checkout flow to display the entry field if appropriate.
     *
     * @return  integer     Count of discount codes available.
     */
    public static function countCurrent()
    {
        global $_CONF, $_TABLES;

        static $count = -1;
        if ($count === -1) {
            $now = $_CONF['_now']->toMySQL(true);
            $sql = "SELECT code FROM {$_TABLES['shop.discountcodes']}
                WHERE '$now' > `start` AND '$now' < `end`";
            $res = DB_query($sql);
            $count = (int)DB_numRows($res);
        }
        return $count;
    }

}
