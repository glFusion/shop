<?php
/**
 * Class to manage reports.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Select and run reports.
 * This base clase includes common configuration items applicable to most reports.
 * If not used, these elements can be disabled by setting the filter_* values to false.
 *
 * @package shop
 */
class Report
{
    /** Report icon for the selection page.
     * Intended to be overridden by child classes.
     * @var string */
    protected $icon = 'undefined';

    /** Report icon class, e.g. "uk-text-success".
     * @var string */
    protected $icon_cls = '';

    /** Report shortname.
     * @var string */
    protected $key = '';

    /** Flag to indicate whether this report has a parameter form.
     * Most reports do have a form.
     * @var boolean */
    protected $hasForm = true;

    /** Period designator from the selection form.
     * @var string */
    protected $period = '';

    /** Starting date.
     * @var object */
    protected $startDate = NULL;

    /** Ending date.
     * @var object */
    protected $endDate = NULL;

    /** Output type (html or csv)
     * @var string */
    protected $type = 'html';

    /** Allowed order statuses to show in the config form.
     * @var array */
    protected $allowed_statuses = array();

    /** Array of status selections.
     * @var array */
    protected $statuses = array();

    /** Extra values to pass into getReportField() verbatim.
     * @var array */
    protected $extra = array();

    /** Indicate if this is an administrator report or regular user.
     * @var boolean */
    protected $isAdmin = false;

    /** Indicate if the report header should be shown.
     * Suppress header if report is embedded in another function such as
     * a normal admin list.
     * @var boolean */
    protected $showHeader = true;

    /** Indicate whether the config uses date ranges.
     * @var boolean */
    protected $filter_dates = true;

    /** Indicate whether the config uses order statuses.
     * @var boolean */
    protected $filter_status = true;

    /** Indicate whether the report can filter on user ID.
     * @var boolean */
    protected $filter_uid = true;

    /** Indicate whether the report can filter on user ID.
     * @var boolean */
    protected $filter_item = false;

    /** Status selected to filter paid vs. unpaid orders.
     * 1 = unpaid, 2 = paid, 4 = either
     * @var integer */
    protected $paid_status = 4;

    /** Indicate whether the report supports multiplt output types
     * @var boolean */
    protected $sel_output = true;

    /** User ID, used if filter_uid is true.
     * @var integer */
    protected $uid = 0;

    /** Limit per-page results.
     * @var integer */
    protected $limit = 50;


    /**
     * Initializes the report.
     */
    protected function __construct()
    {
        $this->key = (new \ReflectionClass($this))->getShortName();
        $this->setAdmin(false);
        if ($this->filter_dates) {
            $this->setStartDate('1970-01-01');
            $this->setEndDate('2037-12-31');
        }
        $this->setStatuses();
    }


    /**
     * Set parameters in object and session vars.
     *
     * @param   array   $get    Array of parameters, typically $_GET
     * @return  object  $this
     */
    public function setParams($get)
    {
        if ($get === NULL) {
            return;
        }

        $this->setType(SHOP_getVar($get, 'out_type', 'string', $this->type));
        $this->allowed_statuses = SHOP_getVar($get, 'orderstatus', 'array');
        self::_setSessVar('orderstatus', $this->allowed_statuses);
        $this->setUid(SHOP_getVar($get, 'uid', 'integer'));
        $period = SHOP_getVar($get, 'period');
        $from = SHOP_getVar($get, 'from_date');
        $to = SHOP_getVar($get, 'to');
        $dates = $this->getDates($period, $from, $to);
        $this->startDate = $dates['start'];
        $this->endDate = $dates['end'];
        $this->paid_status = SHOP_getVar($get, 'paid', 'integer', 4);
        $this->limit = SHOP_getVar($get, 'query_limit', 'integer', 50);
        return $this;
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
     * @return  string  HTML for report listing
     */
    public static function getList()
    {
        global $LANG_SHOP;

        $T = new Template('reports');
        $T->set_file('list', 'list.thtml');
        $T->set_block('list', 'reportList', 'rlist');
        foreach ($LANG_SHOP['reports_avail'] as $key=>$data) {
            $info = self::getInstance($key)->getInfo();
            $T->set_var(array(
                'rpt_key'   => $key,
                'icon'      => $info['icon']['name'],
                'icon_cls'  => $info['icon']['cls'],
                'rpt_dscp'  => $info['dscp'],
                'rpt_name'  => $info['name'],
            ) );
            $T->parse('rlist', 'reportList', true);
        }
        return $T->parse('output', 'list');
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
        global $_CONF;

        $this->startDate = new \Date($dt, $_CONF['timezone']);
    }


    /**
     * Set the ending date object.
     *
     * @param   string  $dt     Ending date string
     */
    protected function setEndDate($dt)
    {
        global $_CONF;

        $this->endDate = new \Date($dt, $_CONF['timezone']);
    }


    /**
     * Set the item ID, if used.
     *
     * @param   string|integer  $item_id    Item ID for filtering
     */
    protected function setItem($item_id)
    {
        self::_setSessVar('item_id', $item_id);
    }

    /**
     * Set the user ID for the report. Can be overridden by administrators.
     *
     * @param   integer $uid    User ID
     */
    public function setUid($uid = 0)
    {
        global $_USER;

        if (!$this->isAdmin) {
            $uid = (int)$_USER['uid'];
        }
        $this->uid = (int)$uid;
        self::_setSessVar('uid', $this->uid);
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
        self::_setSessVar('out_type', $this->type);
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
     * Includes common elements such as dates and user ID filter, and reports
     * can include their own config elements by providing a getReportConfig()
     * function.
     *
     * @return  string  HTML for the report configuration form
     */
    public function Configure()
    {
        global $LANG_SHOP, $_TABLES, $_CONF;

        $T = new Template('reports');
        $T->set_file(array(
            'main'  => 'config.thtml',
        ) );
        $period = self::_getSessVar('period');
        $from_date = self::_getSessVar('from_date', 'string', '');
        $to_date = self::_getSessVar('to_date', 'string', $_CONF['_now']->format('Y-m-d', true));
        $gateway = self::_getSessVar('gateway');

        // Get previously-selected statuses from the session var
        $T->set_var(array(
            'title' => $LANG_SHOP['reports_avail'][$this->key]['name'],
            'from_date' => $from_date,
            'to_date' => $to_date,
            $this->type . '_sel' => 'checked="checked"',
            'period_options' => self::getPeriodSelection($period),
            'report_key'    => $this->key,
            'period'        => $period,
            'filter_dates'  => $this->filter_dates,
            'filter_status' => $this->filter_status,
            'filter_uid'    => $this->filter_uid,
            'filter_item'   => $this->filter_item,
            'pd_chk_' . $this->paid_status => 'checked="checked"',
            'sel_output'    => $this->sel_output,
            'report_configs' => $this->getReportConfig(),
        ) );

        if ($this->filter_uid) {
            $uid = self::_getSessVar('uid', 'int', 0);
            $T->set_var(array(
                'user_select' =>  COM_optionList($_TABLES['users'], 'uid,username', $uid),
            ) );
        }

        if ($this->filter_item) {
            $item_id = self::_getSessVar('item_id', 'int', 0);
            $T->set_var(array(
                'item_select' => COM_optionList($_TABLES['shop.products'], 'id,name', $item_id),
            ) );
        }

        if ($this->filter_status) {
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
        global $LANG_SHOP;

        if ($base === NULL) $base = $this->getType();
        switch ($base) {
        case 'html':
            $T = new Template('reports');
            $T->set_file(array(
                'report'    => $base . '.thtml',
            ) );
            break;
        case 'csv':
        case 'config':
        default:
            $T = new Template('reports/' . $this->key);
            $T->set_file(array(
                'report'    => $base . '.thtml',
            ) );
            break;
        }
        $T->set_var(array(
            'report_key'    => $this->key,
            'report_title'  => $this->getTitle(),
            'filter_dates'  => $this->filter_dates,
            'filter_status' => $this->filter_status,
            'filter_uid'    => $this->filter_uid,
            'filter_item'   => $this->filter_item,
            'is_admin_report' => $this->isAdmin,
            'show_header'   => $this->showHeader,
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
     * @param   boolean $incl_cust  True to include custom date option
     * @return  string      HTML for the period selector
     */
    public static function getPeriodSelection($period=NULL, $incl_cust=true)
    {
        global $LANG_SHOP;

        $retval = '';
        foreach ($LANG_SHOP['periods'] as $key=>$text) {
            if ($key == 'cust' && !$incl_cust) {
                continue;
            }
            $sel = $key === $period ? 'selected="selected"' : '';
            $retval .= "<option value=\"$key\" $sel>$text</option>" . LB;
        }
        return $retval;
    }


    /**
     * Given a period designation return the starting and ending date objects.
     *
     * @param   string  $period     Period designator
     * @param   string  $from   Starting date, only for a custom date range
     * @param   string  $to     Ending date, only for a custom date range
     * @return  array       Array of (start date, end date) objects
     */
    protected function getDates($period, $from=NULL, $to=NULL)
    {
        global $_CONF;

        $t1 = '00:00:00';
        $t2 = '23:59:59';
        $d2 = clone $_CONF['_now'];
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
            $tm = $_CONF['_now']->format('m', true);
            $lq = (int)(($tm + 2)/ 3) - 1;
            list($d1, $d2) = self::_getQtrDates($lq);
            break;
        case 'tq':
            $tm = $_CONF['_now']->format('m', true);
            $tq = (int)(($tm + 2)/ 3);
            list($d1, $d2) = self::_getQtrDates($tq);
            break;
        case 'ty':
            $d1 = new \Date($_CONF['_now']->format('Y-01-01' . $t1, $_CONF['timezone']));
            break;
        case 'ly':
            $year = $_CONF['_now']->format('Y', true) - 1;
            $d1 = new \Date($year . '-01-01 ' . $t1, $_CONF['timezone']);
            $d2 = new \Date($year . '-12-31 ' . $t2, $_CONF['timezone']);
            break;
        case 'cust':
            if ($from < '1970' || $to < '1970') {   // catch invalid dates
                $dates = $this->getDates('ty');
                $from = $dates['start']->format('Y-m-d ' . $t1);
                $to = $dates['end']->format('Y-m-d ' . $t2);
            }
            $d1 = new \Date($from, $_CONF['timezone']);
            $d2 = new \Date($to, $_CONF['timezone']);
            self::_setSessVar('from_date', $d1->format('Y-m-d'));
            self::_setSessVar('to_date', $d2->format('Y-m-d'));
            break;
        default:
            // All time, use default end
            $d1 = new \Date('1970-01-01 ' . $t1, $_CONF['timezone']);
            break;
        }
        self::_setSessVar('period', $period);
        $this->period = $period;
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
        global $_CONF;

        $qtrs = array(
            1 => array('01', '03'),
            2 => array('04', '06'),
            3 => array('07', '09'),
            4 => array('10', '12'),
        );

        $year = $_CONF['_now']->format('Y', true);
        if ($qtr == 0) {
            $year--;
            $qtr = 4;
        }
        // Get the last day of the last month in the quarter
        $ld = cal_days_in_month(CAL_GREGORIAN, $qtrs[$qtr][1], $year);
        $d1 = new \Date(
            sprintf('%d-%02d-01 ' . $t1, $year, $qtrs[$qtr][0]),
            $_CONF['timezone']
        );
        $d2 = new \Date(
            sprintf('%d-%02d-%02d 23:59:59', $year, $qtrs[$qtr][1], $ld).
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
     * @param   string  $type       Expected data type, default=string
     * @param   mixed   $default    Default value if session var not set
     * @return  string          Option value
     */
    protected static function _getSessVar($opt, $type='string', $default=NULL)
    {
        $val = SESS_getVar('shop.report.' . $opt);
        switch ($type) {
        case 'string':
            if (!is_string($val)) $val = (string)$default;
            break;
        case 'integer':
        case 'int':
            if (!is_int($val)) $val = (int)$default;
            break;
        default:
            if ($val === NULL) $val = $default;
            break;
        }
        return $val;
    }


    /**
     * Remove or replace quote characters.
     * For HTML, replace with the HTML entity.
     * For CSV, remove completely.
     * For PDF, no change.
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
            $str = str_replace('"', '""', $str);
            break;
        }
        return $str;
    }


    /**
     * Sets the array of allowed statuses.
     * Called by child classes that want to restrict the order status options.
     *
     * @param   array   $allowed    Allowed statuses to include in report.
     * @return  object  $this
     */
    public function setAllowedStatuses($allowed = array())
    {
        $this->allowed_statuses = $allowed;
        self::_setSessVar('orderstatus', $this->allowed_statuses);
        return $this;
    }


    /**
     * Set the order statuses into an object variable.
     * Includes the checkbox status.
     *
     * @return  array   Array of (status_key, checked)
     */
    protected function setStatuses()
    {
        global $LANG_SHOP;

        $this->statuses = array();
        $statuses = OrderStatus::getAll();
        $status_sess = self::_getSessVar('orderstatus');
        foreach ($statuses as $key=>$data) {
            // Check if this is in the allowed statuses array
            if (
                !empty($this->allowed_statuses) &&
                !in_array($key, $this->allowed_statuses)
            ) {
                continue;
            }
            $chk = 'checked="checked"';
            // If there is a session var but it doesn't contain this status,
            // then it was unchecked.
            if (is_array($status_sess)) {
                if (!in_array($key, $status_sess)) {
                    $chk = '';
                }
            }
            $this->statuses[$key] = array(
                'dscp'  => OrderStatus::getDscp($key),
                'chk'   => $chk,
            );
        }
        return $this->statuses;
    }


    /**
     * Set the admin status, used to determine return URLs
     *
     * @param   boolean $isAdmin    True for admin access, False for user
     * @return  object  $this
     */
    public function setAdmin($isAdmin)
    {
        $this->isAdmin = $isAdmin ? true : false;
        $this->setExtra('isAdmin', $this->isAdmin);
        return $this;
    }


    /**
     * Set the show_header flag.
     *
     * @param   boolean $flag   True to show the report header, False to not
     * @return  object  $this
     */
    public function setShowHeader($flag)
    {
        $this->showHeader = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Get an individual field for the history screen.
     * Reports may provide a protected static fieldFunc() function to handle
     * report-specific fields. Those functions shoule *not* include a default
     * handler but should return NULL for unhandled fields.
     *
     * @access  public so ADMIN_list() can access it.
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra verbatim values
     * @return  string              HTML for field display in the table
     */
    public static function getReportField($fieldname, $fieldvalue, $A, $icon_arr, $extra=array())
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_SHOP_HELP, $_USER;

        static $dt = NULL;
        $retval = '';

        // Calls a class-specific field function, if defined.
        // Use the result if one is returned, otherwise fall through to the
        // default field functions.
        if (isset($extra['class'])) {
            $cls = $extra['class'];
            $retval = $cls::fieldFunc($fieldname, $fieldvalue, $A, $icon_arr, $extra);
            if ($retval !== NULL) {
                return $retval;
            }
        }

        if ($dt === NULL) {
            // Instantiate a date object once
            $dt = new \Date('now', $_USER['tzid']);
        }

        switch($fieldname) {
        case 'order_id':
            if ($extra['isAdmin']) {
                $url = SHOP_ADMIN_URL . '/orders.php?order=' . $fieldvalue;
                $opts = array('target' => '_blank');
            } else {
                $url = COM_buildUrl(SHOP_URL . '/order.php?mode=view&id=' . $fieldvalue);
                $opts = array();
            }
            $retval = COM_createLink($fieldvalue, $url, $opts);
            break;

        case 'order_date':
        case 'ts':
            $dt->setTimestamp($fieldvalue);
            $retval = '<span class="tooltip" title="' .
                $dt->format($_SHOP_CONF['datetime_fmt'], false) . '">' .
                $dt->format($_SHOP_CONF['datetime_fmt'], true) . '</span>';
            break;

        case 'status':
            // Show the order status. Admins can update the status, Users can view only.
            if ($extra['isAdmin']) {
                $retval = OrderStatus::Selection($A['order_id'], 0, $fieldvalue);
            } else {
                $txt = OrderStatus::getDscp($fieldvalue);
                if (isset($LANG_SHOP_HELP[$fieldvalue])) {
                    $tip = $LANG_SHOP_HELP[$fieldvalue];
                    $retval = '<span class="tooltip" title="' . $tip . '">' .
                        $txt . '</span>';
                } else {
                    $retval = $txt;
                }
            }
            break;

        case 'sales_amt':
            if (!$extra['isAdmin']) {
                $total = (float)$fieldvalue;
                $tip = '<table width=&quot;50%&quot; align=&quot;center&quot;>' . LB;
                $tip .= '<tr><td>' . $LANG_SHOP['item_total'] .
                    ': </td><td style=&quot;text-align:right&quot;>' .
                    self::formatMoney($fieldvalue) . '</td></tr>' . LB;
                $disc_amt = $A['gross_items'] - $A['net_nontax'] - $A['net_taxable'];
                if ($disc_amt > 0) {
                    $total -= $disc_amt;
                    $tip .= '<tr><td>' . $LANG_SHOP['discount'] .
                        ': </td><td style=&quot;text-align:right&quot;>- ' .
                        self::formatMoney($disc_amt) . '</td></tr>' . LB;
                }
                foreach (array('tax', 'shipping', 'handling') as $fld) {
                    if (isset($A[$fld]) && is_numeric($A[$fld]) && $A[$fld] > 0) {
                        $tip .= '<tr><td>' . $LANG_SHOP[$fld] .
                                ': </td><td style=&quot;text-align:right&quot;>' .
                                self::formatMoney($A[$fld]) .
                                '</td></tr>' . LB;
                        $total += (float)$A[$fld];
                    }
                }
                if ($total > $fieldvalue) {
                    $tip .= '<tr><td>' . $LANG_SHOP['total'] .
                        ': </td><td style=&quot;text-align:right&quot;>' .
                        self::formatMoney($total) . '</td></tr>' . LB;
                }
                $tip .= '</table>' . LB;
                $retval = '<span class="tooltip" title="' . $tip . '">' . self::formatMoney($fieldvalue) . '</span>';
            } else {
                $retval = self::formatMoney($fieldvalue);
            }
            break;

        case 'order_total':
        case 'total':
        case 'shipping':
        case 'handling':
        case 'net_taxable':
        case 'net_nontax':
        case 'tax':
        case 'paid':
            $retval = self::formatMoney($fieldvalue);
            break;

        case 'customer':
            if (isset($A['billto_company']) && !empty($A['billto_company'])) {
                $fieldvalue = $A['billto_company'];
            } elseif (isset($A['billto_name']) && !empty($A['billto_name'])) {
                $fieldvalue = $A['billto_name'];
            } elseif (isset($A['shipto_name']) && !empty($A['shipto_name'])) {
                $fieldvalue = SHOP_getVar($A, 'shipto_name');
            } else {
                $fieldvalue = COM_getDisplayName($A['uid']);
            }
            $retval = str_replace('"', '&quot;', $fieldvalue);
            if (isset($extra['uid_link'])) {
                $retval = COM_createLink(
                    $retval,
                    $extra['uid_link'] . $A['uid']
                );
            }
            break;

        case 'uid':
            $retval = COM_getDisplayName($fieldvalue) . ' (' . $fieldvalue . ')';
            break;

        default:
            $retval = str_replace('"', '&quot;', $fieldvalue);
            break;
        }
        return $retval;
    }


    /**
     * Format a money field.
     * Helper function to access the Currency class from various namespaces.
     *
     * @param   float   $amt    Amount
     * @return  string  Formatted currency string
     */
    protected static function formatMoney($amt)
    {
        return Currency::formatMoney($amt);
    }


    /**
     * Safety function in case the child class doesn't have this.
     * If a class name is in the $extra array, this may be called and
     * just returns NULL to use the default field function above.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra verbatim values
     * @return  NULL        Null to force the use of the above field function
     */
    protected static function fieldFunc($fieldname, $fieldvalue, $A, $icon_arr, $extra)
    {
        return NULL;
    }


    /**
     * Sets a generic parameter into an object variable of the same name.
     * Also saves the variable in the session for later use.
     *
     * @param   string  $key    Name of parameter
     * @param   mixed   $value  Value of parameter
     */
    public function setParam($key, $value)
    {
        $this->$key = $value;
        self::_setSessVar($key, $value);
        return $this;
    }


    /**
     * Get a new query string based on the current one, replacing some values.
     * Null values in the supplied array are removed from the string while
     * others are added or replaced.
     *
     * @param   array   $p  Array of (key->value) replacement parameters
     * @return  string  Revised query string.
     */
    protected static function getQueryString($p = array())
    {
        parse_str($_SERVER['QUERY_STRING'], $params);
        foreach ($p as $key=>$val) {
            if ($val === NULL && isset($params[$key])) {
                unset($params[$key]);
            } else {
                $params[$key] = $val;
            }
        }
        $q_str = http_build_query($params);
        return $q_str;
    }


    /**
     * Get report info into an array.
     *
     * @return  array   Array of (name, description, icon info)
     */
    protected function getInfo()
    {
        global $LANG_SHOP;

        $key = $this->key;
        $retval = array(
            'name'  => $LANG_SHOP['reports_avail'][$key]['name'],
            'dscp'  => $LANG_SHOP['reports_avail'][$key]['dscp'],
            'icon'  => array(
                'name'  => $this->icon,
                'cls'   => $this->icon_cls,
            ),
        );
        return $retval;
    }


    /**
     * Get the report title when rendering HTML.
     * Allows reports to use a different language string as the title.
     *
     * @return  string  Report title.
     */
    protected function getTitle()
    {
        global $LANG_SHOP;

        if (array_key_exists('title', $LANG_SHOP['reports_avail'][$this->key])) {
            $retval = $LANG_SHOP['reports_avail'][$this->key]['title'];
        } else {
            $retval = $LANG_SHOP['reports_avail'][$this->key]['name'];
        }
        return $retval;
    }


    /**
     * Set a value into the "extra" variable for getReportField() to use.
     *
     * @access  public  To allow setting from anywhere
     * @param   string  $key    Array key
     * @param   mixed   $val    Value to set
     */
    public function setExtra($key, $val)
    {
        $this->extra[$key] = $val;
    }



    /**
     * Get the list options to handle selection checkboxes.
     * Default is for order lists to create PDF versions of orders
     * or packing lists.
     *
     * @return  array   Array of options to be given to ADMIN_list()
     */
    protected function _getListOptions()
    {
        global $LANG_SHOP;

        // Print selected packing lists
        $prt_pl = '<button type="submit" name="pdfpl" value="x" ' .
            'class="uk-button uk-button-mini tooltip" ' .
            'formtarget="_blank" ' .
            'title="' . $LANG_SHOP['print_sel_pl'] . '" ' .
            '><i name="pdfpl" class="uk-icon uk-icon-list"></i>' .
            '</button>';
        // Print selected orders
        $prt_ord = '<button type="submit" name="pdforder" value="x" ' .
            'class="uk-button uk-button-mini tooltip" ' .
            'formtarget="_blank" ' .
            'title="' . $LANG_SHOP['print_sel_ord'] . '" ' .
            '><i name="pdfpl" class="uk-icon uk-icon-print"></i>' .
            '</button>';
        $statuses = OrderStatus::getAll();
        $upd_stat = '<select name="newstatus" onchange="SHOP_enaBtn(bulk_stat_upd, \'\', this.value);">';
        $upd_stat .= '<option value="">--' . $LANG_SHOP['update_status'] . '--</option>';
        foreach ($statuses as $name=>$obj) {
            $upd_stat .= '<option value="' . $name . '">' . OrderStatus::getDscp($name) . '</option>';
        }
        $upd_stat .= '</select>';
        $upd_stat .= '<button type="submit" name="updstatus" value="x" ' .
            'id="bulk_stat_upd" ' .
            'class="uk-button uk-button-mini tooltip" ' .
            'formtarget="_self" ' .
            'title="' . $LANG_SHOP['update_status'] . '" ' .
            'onclick="return confirm(\'' . $LANG_SHOP['q_upd_stat_all'] . '\');"' .
            '><i name="updstat" class="uk-icon uk-icon-check"></i>' .
            '</button>';

        $options = array(
            'chkselect' => 'true',
            'chkname'   => 'orders',
            'chkfield'  => 'order_id',
            'chkactions' => $prt_pl . '&nbsp;&nbsp;' . $prt_ord . '&nbsp;&nbsp;' . $upd_stat,
        );
        return $options;
    }

}   // class Report

?>
