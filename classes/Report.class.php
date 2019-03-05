<?php
/**
 * Class to manage reports.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.5.8
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for reports.
 * @package shop
 */
class Report
{
    /** Property fields accessed via `__set()` and `__get()`.
    * @var array */
    protected $properties;

    /** Report shortname.
     * @var string */
    protected $key;

    /** Flag to indicate whether this report has a parameter form.
     * Most reports do have a form.
     * @var boolean */
    protected $hasForm = true;

    /** Starting date.
     * @var object */
    protected $startDate;

    /** Ending date.
     * @var object */
    protected $endDate;

    /** Output type (html or csv)
     * @var string */
    protected $type = 'html';


    /**
     * Initializes the report.
     */
    protected function __construct()
    {
        $this->key = (new \ReflectionClass($this))->getShortName();
    }


    /**
     * Render the report.
     */
    public function Render()
    {
        return "Running {$this->key} report, this should be overridden";
    }


    /**
     * Get the list of available reports for selection.
     *
     * @todo    Better formatting, use a template
     * @return  string  HTML for report listing
     */
    public static function getList()
    {
        global $LANG_SHOP;

        $retval = '';
        foreach ($LANG_SHOP['reports_avail'] as $key => $descrip) {
            $retval .= '<div><a href="' . SHOP_ADMIN_URL .
                '/report.php?configure=' . $key . '">' . $descrip . '</a></div>';
        }
        return $retval;
    }


    /**
     * Get an instance of a report class.
     *
     * @param   string  $rpt_name   Report Name
     * @return  object|null     Instantiated class if available, Null if not
     */
    public static function getInstance($rpt_name)
    {
        $cls = __NAMESPACE__ . '\\Reports\\' . $rpt_name;
        if (class_exists($cls)) {
            return new $cls;
        } else {
            return NULL;
        }
    }


    /**
     * Check if this report has a form to collect parameters.
     *
     * @return  boolean     True if a form should be dispalyed, False if not
     */
    public function hasForm()
    {
        return $this->hasForm;
    }


    /**
     * Set the start date object.
     *
     * @param   string  $dt     Starting date string
     */
    protected function setStartDate($dt)
    {
        $this->startDate = new \Date($dt, $_CONF['timezone']);
    }


    /**
     * Set the ending date object.
     *
     * @param   string  $dt     Ending date string
     */
    protected function setEndDate($dt)
    {
        $this->endDate = new \Date($dt, $_CONF['timezone']);
    }


    /**
     * Set the report type. HTML and CSV supported.
     *
     * @param   string  $type   Report output type
     */
    public function setType($type)
    {
        switch ($type) {
        case 'html':
        case 'csv':
            $this->type = $type;
            break;
        default:
            $this->type = 'html';
            break;
        }
        self::_setSessVar('output_type', $this->type);
    }


    /**
     * Get the report output type.
     *
     * @return  string      "html" or "csv"
     */
    protected function getType()
    {
        switch ($this->type) {
        case 'html':
        case 'csv':
            return $this->type;
            break;
        default:
            return 'html';
            break;
        }
    }


    /**
     * Get the report template.
     * Gets the configuration template if requested, or the appropriate
     * report template depending on the selected output type.
     *
     * @param   string|null $base   Base template name
     * @return  object      Template object
     */
    protected function getTemplate($base=NULL)
    {
        if ($base === NULL) $base = $this->getType();
        $T = new \Template(SHOP_PI_PATH . '/templates/reports/' . $this->key);
        $T->set_file(array(
            'report'    => $base . '.thtml',
        ) );
        return $T;
    }


    /**
     * Get the report output, either HTML or CSV
     *
     * @param   string  $text   Report text
     * @return  string      HTML, or display CSV and exit
     */
    protected function getOutput($text)
    {
        switch ($this->getType()) {
        case 'html':
            return $text;
            break;
        case 'csv':
            header('Content-type: text/csv');
            header('Content-Disposition: attachment; filename="' . $this->key . '.csv"');
            echo $text;
            exit;
            break;
        }
    }


    public static function getDates($period)
    {
        global $_CONF;

        $d2 = SHOP_now();
        switch ($period) {
        case 'tm':
            $d1 = new \Date('first day of this month', $_CONF['timezone']);
            break;
        case 'lm':
            $d1 = new \Date('first day of last month', $_CONF['timezone']);
            $d2 = new \Date('last day of last month', $_CONF['timezone']);
            break;
        case '30':
        case '60':
        case '90':
            $days = (int)substr($_REQUEST['period'], 1);
            $d1 = new \Date('-' . $days . ' days', $_CONF['timezone']);
            break;
        case 'lq':
            $tm = SHOP_now()->format('m');
            $lq = (int)(($tm + 2)/ 3) - 1;
            list($d1, $d2) = self::_getQtrDates($lq);
            break;
        case 'tq':
            $tm = SHOP_now()->format('m');
            $tq = (int)(($tm + 2)/ 3);
            list($d1, $d2) = self::_getQtrDates($tq);
            break;
        case 'ty':
            $d1 = new \Date(SHOP_now()->format('Y-01-01', $_CONF['timezone']));
            break;
        case 'ly':
            $year = SHOP_now()->format('Y') - 1;
            $d1 = new \Date($year . '-01-01 00:00:00', $_CONF['timezone']);
            $d2 = new \Date($year . '-12-31 23:59:59', $_CONF['timezone']);
            break;
        default:
            // All time, use default end
            $d1 = new \Date('1970-01-01', $_CONF['timezone']);
            break;
        }
        self::_setSessVar('period', $period);
        return array(
            'start' => $d1,
            'end'   => $d2,
        );
    }


    /**
     * Get the first and last dates for a quarter
     *
     * @param   $qtr    Quarter number, 1 - 4
     * @return  array   Array of (start, end) date objects
     */
    private static function _getQtrDates($qtr)
    {
        $qtrs = array(
            1 => array('01', '03'),
            2 => array('04', '06'),
            3 => array('07', '09'),
            4 => array('10', '12'),
        );

        $year = SHOP_now()->format('Y');
        if ($qtr == 0) {
            $year--;
            $qtr = 4;
        }
        // Get the last day of the last month in the quarter
        $ld = cal_days_in_month(CAL_GREGORIAN, $qtrs[$qtr][1], $year);
        $d1 = new \Date(
            sprintf('%d-%02d-01', $year, $qtrs[$qtr][0]),
            $_CONF['timezone']
        );
        $d2 = new \Date(
            sprintf('%d-%02d-%02d', $year, $qtrs[$qtr][1], $ld).
            $_CONF['timezone']
        );
        return array($d1, $d2);
    }


    /**
     * Set a session variable for a report config option.
     *
     * @param   string  $opt    Option name
     * @param   string  $val    Option value
     */
    protected static function _setSessVar($opt, $val)
    {
        SESS_setVar('shop.report.' . $opt, $val);
    }


    /**
     * Get a session variable for a report config option.
     *
     * @param   string  $opt        Option name
     * @#param  mixed   $default    Default value if session var not set
     * @return  string          Option value
     */
    protected static function _getSessVar($opt, $default=NULL)
    {
        $val = SESS_getVar('shop.report.' . $opt);
        return $val !== NULL ? $val : $default;
    }


    /**
     * Remove or replace quot characters.
     * For HTML, replace with the HTML entity.
     * For CSV, remove completely.
     *
     * @param   string  $str    Original string
     * @return  string          Sanitized string
     */
    protected function remQuote($str)
    {
        switch ($this->type) {
        case 'html':
            $str = str_replace('"', '&quot;', $str);
            break;
        case 'csv':
            $str = str_replace('"', '', $str);
            break;
        }
        return $str;
    }

}   // class Report


/**
 * Get an individual field for the history screen.
 *
 * @param   string  $fieldname  Name of field (from the array, not the db)
 * @param   mixed   $fieldvalue Value of the field
 * @param   array   $A          Array of all fields from the database
 * @param   array   $icon_arr   System icon array (not used)
 * @return  string              HTML for field display in the table
 */
function getReportField($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP, $_USER;

    static $dt = NULL;
    static $Cur = NULL;
    $retval = '';

    if ($dt === NULL) {
        // Instantiate a date object once
        $dt = new \Date('now', $_USER['tzid']);
    }
    if ($Cur === NULL) {
        $Cur = Currency::getInstance();
    }

    switch($fieldname) {
    case 'order_id':
        $retval = COM_createLink(
            $fieldvalue,
            SHOP_ADMIN_URL . '/index.php?order=' . $fieldvalue
        );
        $retval .= '&nbsp;&nbsp;' . COM_createLink(
            '<i class="uk-icon-mini uk-icon-print"></i>',
            COM_buildUrl(SHOP_URL . '/order.php?mode=print&id=' . $fieldvalue),
            array(
                'class' => 'tooltip',
                'title' => $LANG_SHOP['print'],
                'target' => '_new',
            )
        );
        $retval .= '&nbsp;&nbsp;' . COM_createLink(
            '<i class="uk-icon-mini uk-icon-list"></i>',
            COM_buildUrl(SHOP_URL . '/order.php?mode=packinglist&id=' . $fieldvalue),
            array(
                'class' => 'tooltip',
                'title' => $LANG_SHOP['packinglist'],
                'target' => '_new',
            )
        );
        break;

    case 'ipn_id':
        $retval = COM_createLink(
            $A['id'],
            SHOP_ADMIN_URL . '/index.php?ipnlog=x&amp;op=single&amp;id=' . $A['id']
        );
        break;

    case 'verified':
        $retval = $fieldvalue > 0 ? 'True' : 'False';
        break;

    case 'order_date':
        $dt->setTimestamp($fieldvalue);
        $retval = '<span class="tooltip" title="' .
                $dt->format($_SHOP_CONF['datetime_fmt'], false) . '">' .
                $dt->format($_SHOP_CONF['datetime_fmt'], true) . '</span>';
        break;

    case 'order_total':
        $sales = SHOP_getVar($A, 'sales_amt', 'float');
        $tax = SHOP_getVar($A, 'tax', 'float');
        $shipping = SHOP_getVar($A, 'shipping', 'float');
        $retval = $Cur->formatValue($sales + $tax + $shipping);
        break;

    case 'status':
        if (is_array($LANG_SHOP['orderstatus'])) {
            $retval = OrderStatus::Selection($A['order_id'], 0, $fieldvalue);
        } else {
            $retval = SHOP_getVar($LANG_SHOP['orderstatus'], $fieldvalue, 'string', 'Unknown');
        }
        break;

    case 'sales_amt':
    case 'total':
    case 'shipping':
    case 'tax':
        $retval = $Cur->formatValue($fieldvalue);
        break;

    case 'customer':
        if (!empty($A['billto_company'])) {
            $fieldvalue = $A['billto_company'];
        } else {
            $fieldvalue = $A['billto_name'];
        }
    default:
        $retval = str_replace('"', '&quot;', $fieldvalue);
        break;
    }
    return $retval;
}

?>
