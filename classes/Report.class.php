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
     * Allowed order statuses to show in the config form.
     * @var array */
    protected $allowed_status = array();

    /**
     * Array of status selection info
     * @var array */
    protected $statuses = array();

    /**
     * Indicate whether the config uses date ranges.
     * @var boolean */
    protected $uses_dates = true;

    /**
     * Indicate whether the config uses order statuses.
     * @var boolean */
    protected $uses_status = true;


    /**
     * Initializes the report.
     */
    protected function __construct()
    {
        $this->key = (new \ReflectionClass($this))->getShortName();
        $type = self::_getSessVar('output_type');
        if ($type) {
            $this->setType($type);
        }
        $this->setStatuses($status_sess);
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

        $T = new \Template(SHOP_PI_PATH . '/templates/reports');
        $T->set_file('list', 'list.thtml');
        $T->set_block('list', 'reportList', 'rlist');
        foreach ($LANG_SHOP['reports_avail'] as $key=>$data) {
            $T->set_var(array(
                'rpt_key'   => $key,
                'rpt_dscp'  => $data['dscp'],
                'rpt_name'  => $data['name'],
            ) );
            $T->parse('rlist', 'reportList', true);
        }
        $retval .= $T->parse('output', 'list');
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
     * Get the report configuration form.
     *
     * @param   string|null $base   Base template name
     * @return  object      Template object
     */
    public function Configure()
    {
        global $LANG_SHOP;

        $T = new \Template(SHOP_PI_PATH . '/templates/reports');
        $T->set_file(array(
            'main'  => 'config.thtml',
        ) );
        $period = self::_getSessVar('period');
        $from_date = self::_getSessVar('from_date', '1970-01-01');
        $to_date = self::_getSessVar('to_date', SHOP_now());
        $gateway = self::_getSessVar('gateway');
        // Get previously-selected statuses from the session var
        $T->set_var(array(
            'title' => $LANG_SHOP['reports_avail'][$this->key]['name'],
            'from_date' => $from_date,
            'to_date' => $to_date,
            $this->type . '_sel' => 'checked="checked"',
            'period_options' => self::getPeriodSelection($period),
            'report_key'    => $this->key,
            'period'    => $period,
            'uses_dates'    => $this->uses_dates,
            'uses_status'   => $this->uses_status,
            'report_configs' => $this->getReportConfig(),
        ) );

        if ($this->uses_status) {
            $T->set_block('report', 'statusOpts', 'statOpt');
            foreach ($this->statuses as $key => $data) {
                $T->set_var(array(
                    'status_key' => $key,
                    'status' => $data['dscp'],
                    'checked' => $data['chk'],
                ) );
                $T->parse('statOpt', 'statusOpts', true);
            }
        }

        $T->parse('output', 'main');
        return $T->finish ($T->get_var('output'));
    }


    /**
     * Get the report-specific config elements.
     * This is a stub that may be overridden.
     *
     * @return  string  HTML for report-specific configuration items
     */
    protected function getReportConfig()
    {
        return '';
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


    /**
     * Get the time period selector.
     *
     * @param   string  $period     Selected period
     * @return  string      HTML for the period selector
     */
    public static function getPeriodSelection($period=NULL)
    {
        global $LANG_SHOP;

        foreach ($LANG_SHOP['periods'] as $key=>$text) {
            $sel = $key == $period ? 'selected="selected"' : '';
            $retval .= "<option value=\"$key\" $sel>$text</option>" . LB;
        }
        return $retval;
    }


    /**
     * Given a period designation return the starting and ending date objects.
     *
     * @param   string  $period     Period designator
     * @return  array       Array of (start date, end date) objects
     */
    public static function getDates($period, $from=NULL, $to=NULL)
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
        case'cust':
            $d1 = new \Date($from, $_CONF['timezone']);
            $d2 = new \Date($to, $_CONF['timezone']);
            self::_setSessVar('from_date', $from);
            self::_setSessVar('to_date', $to);
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


    protected function setStatuses()
    {
        global $LANG_SHOP;

        $this->statuses = array();
        $statuses = \Shop\OrderStatus::getAll();
        $status_sess = self::_getSessVar('orderstatus');
        foreach ($statuses as $key=>$data) {
            // Check if this is in the allowed statuses array
            if (!empty($this->allowed_statuses) &&
                !in_array($key, $this->allowed_statuses)) {
                continue;
            }
            $chk = 'checked="checked"';
            // If there is a session var but it doesn't contain this status,
            // then it was unchecked.
            if (is_array($status_sess) && !in_array($key, $status_sess)) {
                $chk = '';
            }
            $this->statuses[$key] = array(
                'dscp'  => SHOP_getVar($LANG_SHOP['orderstatus'], $key, 'string', $key),
                'chk'   => $chk,
            );
        }
        return $this->statuses;
    }

    /**
     * Get an individual field for the history screen.
     * @access  public so ADMIN_list() can access it.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getReportField($fieldname, $fieldvalue, $A, $icon_arr)
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
        case 'ts':
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

}   // class Report

?>
