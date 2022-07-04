<?php
/**
 * Order History Report.
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
namespace Shop\Reports;
use Shop\Models\OrderStatus;
use Shop\FieldList;


/**
 * Class for Order History Report.
 * @package shop
 */
class orderlist extends \Shop\Report
{
    /** Report icon name
     * @var string */
    protected $icon = 'list';

    /** All possible allowed order statuses.
     * Excludes cart.
     * @var array */
    private $default_statuses = array(
        OrderStatus::INVOICED,
        OrderStatus::PENDING,
        OrderStatus::PROCESSING,
        OrderStatus::SHIPPED,
        OrderStatus::CLOSED,
        OrderStatus::REFUNDED,
    );

    /**
     * Create and render the report contents.
     *
     * @return  string  HTML for report
     */
    public function Render()
    {
        global $_TABLES, $_CONF, $LANG_SHOP, $LANG_SHOP_HELP, $_USER;

        USES_lib_admin();

        $T = $this->getTemplate();
        $from_date = $this->startDate->toUnix();
        $to_date = $this->endDate->toUnix();
        if ($this->isAdmin) {
            $cust_hdr = array(
                'text'  => $LANG_SHOP['customer'],
                'field' => 'customer',
                'sort'  => false,
            );
        } else {
            $cust_hdr = array();
        }
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['order_number'],
                'field' => 'order_id',
                'sort'  => true,
            ),
            array(
                'text'  => '',
                'field' => 'action',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['order_date'],
                'field' => 'order_date',
                'sort'  => true,
                'align' => 'center',
            ),
        );
        if ($this->isAdmin) {
            $header_arr = array_merge(
                $header_arr,
                array(
                    $cust_hdr,
                    array(
                        'text'  => $LANG_SHOP['amount'],
                        'field' => 'sales_amt',
                        'sort'  => true,
                        'align' => 'right',
                    ),
                    array(
                        'text'  => $LANG_SHOP['tax'],
                        'field' => 'tax',
                        'sort'  => true,
                        'align' => 'right',
                    ),
                    array(
                        'text'  => $LANG_SHOP['shipping'],
                        'field' => 'shipping',
                        'sort'  => true,
                        'align' => 'right',
                    ),
                    array(
                        'text'  => $LANG_SHOP['total'],
                        'field' => 'order_total',
                        'sort'  => false,
                        'align' => 'right',
                    ),
                    array(
                        'text'  => $LANG_SHOP['paid'],
                        'field' => 'paid',
                        'sort'  => false,
                        'align' => 'right',
                    ),
                    array(
                        'text'  => $LANG_SHOP['balance'],
                        'field' => 'balance',
                        'sort'  => false,
                        'align' => 'right',
                    ),
                    array(
                        'text'  => $LANG_SHOP['status'],
                        'field' => 'status',
                        'sort'  => true,
                    ),
                    array(
                        'text'  => $LANG_SHOP['order_seq'],
                        'field' => 'order_seq',
                        'sort'  => true,
                        'align' => 'right',
                    ),
                )
            );
        } else {
            $header_arr = array_merge(
                $header_arr,
                array(
                    array(
                        'text'  => $LANG_SHOP['item_total'] . '&nbsp;' .
                        FieldList::info(array(
                            'title' => $LANG_SHOP_HELP['orderlist_total'],
                        ) ),
                        'field' => 'sales_amt',
                        'sort'  => true,
                        'align' => 'right',
                    ),
                    array(
                        'text'  => $LANG_SHOP['status'],
                        'field' => 'status',
                        'sort'  => true,
                    ),
                )
            );
        }

        if ($this->isAdmin) {
            // Rebuild the query string, excluding the uid parameter, and make
            // sure the report name is present.
            $q_str = $_GET;
            if (!isset($q_str['run'])) {
                $q_str['run'] = 'orderlist';
            }
            unset($q_str['uid']);
            unset($q_str['query_limit']);
            $q_str = http_build_query($q_str);
            $form_url = SHOP_ADMIN_URL . '/report.php?' . $q_str;
            $this->setExtra('uid_link', SHOP_ADMIN_URL . '/report.php?' . $q_str . '&uid=');
            $listOptions = $this->_getListOptions();
        } else {
            $listOptions = '';
            $form_url = '';
        }

        $defsort_arr = array(
            'field' => 'order_date',
            'direction' => 'DESC',
        );

//                SELECT sum(itm.price * itm.quantity)
//                FROM {$_TABLES['shop.orderitems']} itm
//                WHERE itm.order_id = ord.order_id
//            ) as sales_amt,
        $sql = "SELECT ord.*, ord.net_nontax + ord.net_taxable AS sales_amt,
            ( SELECT sum(pmt_amount) FROM {$_TABLES['shop.payments']} pmt
                WHERE pmt.pmt_order_id = ord.order_id
            ) as paid
            FROM {$_TABLES['shop.orders']} ord ";
        $orderstatus = $this->allowed_statuses;
        if (empty($orderstatus)) {
            $orderstatus = self::_getSessVar('orderstatus');
        }
        if (empty($orderstatus)) {
            // If still empty, may come from a direct link instead of the
            // report config page. Allow all valid "order" statuses.
            // Excludes cart.
            $orderstatus = $this->default_statuses;
        }
        if (!empty($orderstatus)) {
            $status_sql = "'" . implode("','", $orderstatus) . "'";
            $status_sql = " ord.status in ($status_sql) AND ";
        }

        $where = "$status_sql (ord.order_date >= '$from_date' AND ord.order_date <= '$to_date')";
        if ($this->uid > 0) {
            $where .= " AND uid = {$this->uid}";
        }
        if ($this->paid_status == 2) {
            $where .= " HAVING paid >= sales_amt";
        } elseif ($this->paid_status == 1) {
            $where .= " HAVING paid < sales_amt";
        }
        $query_arr = array(
            'table' => 'shop.orders',
            'sql' => $sql,
            'query_fields' => array(
                'order_id',
                'billto_name', 'billto_company', 'billto_address1',
                'billto_address2','billto_city', 'billto_state',
                'billto_country', 'billto_zip',
                'shipto_name', 'shipto_company', 'shipto_address1',
                'shipto_address2','shipto_city', 'shipto_state',
                'shipto_country', 'shipto_zip',
                'billto_phone', 'shipto_phone',
                'buyer_email', 'ord.order_id',
            ),
            'default_filter' => "WHERE $where",
            //'group_by' => 'ord.order_id',
        );
        $text_arr = array(
            'has_extras' => false,
            'form_url' => $form_url,
            'has_limit' => true,
            'has_paging' => true,
            'has_search' => true,
        );
        $total_sales = 0;
        $total_tax = 0;
        $total_shipping = 0;
        $total_total = 0;
        $total_paid = 0;
        $order_date = clone $_CONF['_now'];   // Create an object to be updated later

        switch ($this->type) {
        case 'html':
            $this->setExtra('class', __CLASS__);
            // Get the totals, have to use a separate query for this.
            $sql = "SELECT SUM(itm.quantity * itm.price) as total_sales,
                SUM(ord.tax) as total_tax, SUM(ord.shipping) as total_shipping
                FROM {$_TABLES['shop.orders']} ord
                LEFT JOIN {$_TABLES['shop.orderitems']} itm
                    ON itm.order_id = ord.order_id {$query_arr['default_filter']}";
            $res = DB_query($sql);
            if ($res) {
                $A = DB_fetchArray($res, false);
                $total_sales = $A['total_sales'];
                $total_tax = $A['total_tax'];
                $total_shipping = $A['total_shipping'];
                $total_total = $total_sales + $total_tax + $total_shipping;
            }
            //var_dump($_POST);die;
            $filter = FieldList::select(array(
                'name' => 'period',
                'option_list' => $this->getPeriodSelection($this->period, false),
            ) );
            $T->set_var(
                'output',
                ADMIN_list(
                    'shop_rep_orderlist',
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr,
                    $filter, $this->extra, $listOptions
                )
            );
            break;
        case 'csv':
            $total_sales = 0;
            $total_tax = 0;
            $total_shipping = 0;
            $total_total = 0;
            // Assemble the SQL manually from the Admin list components
            $sql .= ' ' . $query_arr['default_filter'];
            $sql .= ' ORDER BY ' . $defsort_arr['field'] . ' ' . $defsort_arr['direction'];
            $res = DB_query($sql);
            $T->set_block('report', 'ItemRow', 'row');
            while ($A = DB_fetchArray($res, false)) {
                if (!empty($A['billto_company'])) {
                    $customer = $A['billto_company'];
                } elseif (!empty($A['billto_name'])) {
                    $customer = $A['billto_name'];
                } else {
                    $customer = COM_getDisplayName($A['uid']);
                }
                $order_date->setTimestamp($A['order_date']);
                $order_total = $A['sales_amt'] + $A['tax'] + $A['shipping'];
                $T->set_var(array(
                    'order_id'      => $A['order_id'],
                    'invoice'       => $A['order_seq'],
                    'order_date'    => $order_date->format('Y-m-d', true),
                    'customer'      => $this->remQuote($customer),
                    'sales_amt'     => self::formatMoney($A['sales_amt']),
                    'tax'           => self::formatMoney($A['tax']),
                    'shipping'      => self::formatMoney($A['shipping']),
                    'total'         => self::formatMoney($order_total),
                    'paid'          => self::formatMoney($A['paid']),
                    'nl'            => "\n",
                ) );
                $T->parse('row', 'ItemRow', true);
                $total_sales += $A['sales_amt'];
                $total_tax += $A['tax'];
                $total_shipping += $A['shipping'];
                $total_total += $order_total;
                $total_paid += $A['paid'];
            }
            break;
        }

        $T->set_var(array(
            'startDate'         => $this->startDate->format($_CONF['shortdate'], true),
            'endDate'           => $this->endDate->format($_CONF['shortdate'], true),
            'total_sales'       => self::formatMoney($total_sales),
            'total_tax'         => self::formatMoney($total_tax),
            'total_shipping'    => self::formatMoney($total_shipping),
            'total_total'       => self::formatMoney($total_total),
            'total_paid'        => self::formatMoney($total_paid),
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
        global $LANG_SHOP, $_SHOP_CONF, $_USER;

        static $dt = NULL;
        if ($dt === NULL) {
            // Instantiate a date object once
            $dt = new \Date('now', $_USER['tzid']);
        }

        $retval = NULL;
        switch ($fieldname) {
        case 'action':
            $retval = '<span style="white-space:nowrap" class="nowrap">';
            $retval .= \Shop\Order::linkPrint($A['order_id']);
            if ($extra['isAdmin']) {
                $retval .= '&nbsp;' . \Shop\Order::linkPackingList($A['order_id']);
            }
            $retval .= '</span>';
            break;
        case 'balance':
            $bal = $A['order_total'] - $A['paid'] - $A['by_gc'];;
            $retval = self::formatMoney($bal);
            if ($A['by_gc'] > .0001) {
                $by_gc = self::formatMoney($A['by_gc']);
                $retval = '<span class="tooltip" title="' . $by_gc . ' paid by gift card">' .
                    $retval . '</span>';
            }
            break;
        case 'order_date':
            $dt->setTimestamp($fieldvalue);
            $retval = '<span class="tooltip" title="' .
                $dt->format($_SHOP_CONF['datetime_fmt'], false) . '<br />' .
                $dt->format($_SHOP_CONF['datetime_fmt'], true) . '">' .
                $dt->format('Y-m-d', true) . '</span>';
            break;
        }
        return $retval;
    }

}   // class orderlist

?>
