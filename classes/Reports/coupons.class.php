<?php
/**
 * Coupon Activity Report.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Reports;

/**
 * Class for Coupon activity report
 * @package shop
 */
class coupons extends \Shop\Report
{
    /** Coupon code to use for report.
     * @var string */
    protected $code;

    /** Name of icon to use in report selection.
     * @var string */
    protected $icon = 'gift';

    /** Component name used for admin list.
     * @var string */
    static $component = 'shop_rep_coupons';


    /**
     * Constructor. Set report-specific values.
     */
    public function __construct()
    {
        $this->filter_status = false;
        $this->filter_uid = false;
        // For now, this does not support CSV output
        $this->sel_output = false;
        parent::__construct();

        if (isset($_REQUEST['code'])) {
            $this->setParam('code', $_REQUEST['code']);
        }
    }


    /**
     * Create and render the report contents.
     *
     * @return  string  HTML for report
     */
    public function Render()
    {
        global $_TABLES, $_CONF, $LANG_SHOP, $LANG_SHOP_HELP, $_USER;

        $this->setType('html');
        $from = $this->startDate->toUnix();
        $to = $this->endDate->toUnix();
        if ($this->isAdmin) {
            // Get the URL with query string, but remove the query for use
            // in the form_url.
            $url = SHOP_ADMIN_URL . '/report.php?' . self::getQueryString(array('q'=>''));
            //$url = $_SERVER['REQUEST_URI'];
            $this->setExtra('uid_link', $_CONF['site_url'] . '/users.php?mode=profile&uid=');
            $cust_hdr = array(
                array(
                    'text'  => $LANG_SHOP['customer'],
                    'field' => 'uid',
                    'sort'  => true,
                ),
                array(
                    'text'  => $LANG_SHOP['code'],
                    'field' => 'code',
                    'sort'  => true,
                ),
            );
        } else {
            $url = SHOP_URL . '/account.php?mode=couponlog';
            $cust_hdr = array();
        }
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['datetime'],
                'field' => 'ts',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['message'],
                'field' => 'msg',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['amount'],
                'field' => 'amount',
                'sort'  => true,
                'align' => 'right',
            ),
        );
        $header_arr = array_merge(
            $cust_hdr,
            $header_arr
        );

        $defsort_arr = array(
            'field' => 'ts',
            'direction' => 'DESC',
        );

        $sql = "SELECT log.*,u.fullname, u.username
            FROM {$_TABLES['shop.coupon_log']} log
            LEFT JOIN {$_TABLES['users']} u
                ON u.uid = log.uid ";
        $this->setUid();
        $where = "WHERE log.ts BETWEEN $from AND $to ";
        if ($this->uid > 0) {
            $where .= "AND log.uid = {$this->uid} ";
        }
        $query_arr = array(
            'table' => 'shop.orders',
            'sql' => $sql,
            'query_fields' => array('code'),
            'default_filter' => $where,
            'group_by' => '',
        );
        $text_arr = array(
            'has_extras' => false,
            'form_url' => $url,
            'has_limit' => true,
            'has_paging' => true,
            'has_search' => $this->isAdmin ? true : false,
        );
        $this->setExtra('class', __CLASS__);

        $Cur = \Shop\Currency::getInstance();
        $T = $this->getTemplate();
        switch ($this->type) {
        case 'html':
            $T->set_var(
                'output',
                \ADMIN_list(
                    self::$component,
                    array(__CLASS__, 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr,
                    '', $this->extra
                )
            );
            break;
        case 'csv':
            $sql .= ' ' . $query_arr['default_filter'];
            $res = DB_query($sql);
            $T->set_block('report', 'ItemRow', 'row');
            $dt = new \Date('now', $_CONF['timezone']);
            while ($A = DB_fetchArray($res, false)) {
                if (!empty($A['billto_company'])) {
                    $customer = $A['billto_company'];
                } else {
                    $customer = $A['billto_name'];
                }
                $dt->setTimestamp($A['order_date']);
                $T->set_var(array(
                    'order_id'      => $A['order_id'],
                    'order_date'    => $dt->format('Y-m-d', true),
                    'customer'      => $this->remQuote($customer),
                    'sales_amt'     => $Cur->FormatValue($A['sales_amt']),
                    'tax'           => $Cur->FormatValue($A['tax']),
                    'shipping'      => $Cur->FormatValue($A['shipping']),
                    'total'         => $Cur->FormatValue($order_total),
                    'nl'            => "\n",
                ) );
                $T->parse('row', 'ItemRow', true);
            }
            break;
        }

        $T->set_var(array(
            'startDate'         => $this->startDate->format($_CONF['shortdate'], true),
            'endDate'           => $this->endDate->format($_CONF['shortdate'], true),
            'nl'                => "\n",
        ) );
        $T->parse('output', 'report');
        $report = $T->finish($T->get_var('output'));
        return $this->getOutput($report);
    }


    /**
     * Get the display value for a field specific to this report.
     * This function takes over the "default" handler in Report::getReportField().
     * @access  protected as it is only called from Report::getReportField().
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra verbatim values
     * @return  string              HTML for field display in the table
     */
    protected static function fieldFunc($fieldname, $fieldvalue, $A, $icon_arr, $extra)
    {
        global $LANG_SHOP;

        static $Cur = NULL;
        if ($Cur === NULL) {
            $Cur = \Shop\Currency::getInstance();
        }

        $retval = NULL;
        switch ($fieldname) {
        case 'msg':
            switch ($fieldvalue) {
            case 'gc_redeemed':
            case 'gc_expired':
                $var = $extra['isAdmin'] ? '' : $A['code'];
                break;
            case 'gc_applied':
                $var = $A['order_id'];
                break;
            }
            $retval = sprintf(
                SHOP_getVar($LANG_SHOP, 'msg_' . $fieldvalue, 'string', 'Undefined'),
                $var
            );
            break;

        case 'amount':
            switch ($A['msg']) {
            case 'gc_redeemed':
                break;
            default:
                $fieldvalue *= -1;
                break;
            }
            $retval = $Cur->FormatValue($fieldvalue);
            break;

        case 'code':
            if ($extra['isAdmin']) {
                $url = SHOP_ADMIN_URL . '/report.php?' . self::getQueryString(array('q'=>$fieldvalue));
                //$url = SHOP_ADMIN_URL . '/report.php?' . self::getQueryString();
            } else {
                $url = SHOP_URL . '/account.php?mode=couponlog&code=' . $fieldvalue;
            }
            $retval = COM_createLink(
                $fieldvalue,
                $url
            );
            break;
        }
        return $retval;
    }

}   // class orderlist

?>
