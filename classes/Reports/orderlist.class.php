<?php
/**
 * Order History Report.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     0.5.8
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Reports;

/**
 * Class for Order History Report.
 * @package shop
 */
class orderlist extends \Shop\Report
{
    /**
     * Create and render the report contents.
     *
     * @return  string  HTML for report
     */
    public function Render()
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        $this->setType($_GET['out_type']);
        self::_setSessVar('orderstatus', $_GET['orderstatus']);
        $dates = parent::getDates($_GET['period'], $_GET['from_date'], $_GET['to_date']);
        $this->startDate = $dates['start'];
        $this->endDate = $dates['end'];
        $T = $this->getTemplate();
        $from_date = $this->startDate->toUnix();
        $to_date = $this->endDate->toUnix();
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['order_number'],
                'field' => 'order_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['order_date'],
                'field' => 'order_date',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['customer'],
                'field' => 'customer',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['sales'],
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
                'text'  => $LANG_SHOP['status'],
                'field' => 'status',
                'sort'  => true,
            ),
        );

        $defsort_arr = array(
            'field' => 'order_date',
            'direction' => 'ASC',
        );

        $sql = "SELECT ord.*, sum(itm.price * itm.quantity) as sales_amt
            FROM {$_TABLES['shop.orders']} ord
            LEFT JOIN {$_TABLES['shop.orderitems']} itm
                ON itm.order_id = ord.order_id";
        if (!empty($_GET['orderstatus'])) {
            $status_sql = "'" . implode("','", $_GET['orderstatus']) . "'";
            $status_sql = " ord.status in ($status_sql) AND ";
        } else {
            $status_sql = '';
        }

        $where = "$status_sql (ord.order_date >= '$from_date' AND ord.order_date <= '$to_date')";
        $uid = SHOP_getVar($_GET, 'uid', 'integer');
        if ($uid > 0) {
            $where .= " AND uid = $uid";
        }
        $query_arr = array(
            'table' => 'shop.orders',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => "WHERE $where",
            'group_by' => 'ord.order_id',
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/report.php?run=' . $this->key .
                '&period=' . $_GET['period'],
            'has_limit' => true,
            'has_paging' => true,
        );

        $total_sales = 0;
        $total_tax = 0;
        $total_shipping = 0;
        $total_total = 0;
        $order_date = SHOP_now();   // Create an object to be updated later
        $Cur = \Shop\Currency::getInstance($A['currency']);

        switch ($this->type) {
        case 'html':
            // Get the totals, have to use a separate query for this.
            $s = "SELECT SM(itm.quantity * itm.price) as total_sales,
                SUM(ord.tax) as total_tax, SUM(ord.shipping) as total_shipping
                FROM {$_TABLES['shop.orders']} ord
                LEFT JOIN {$_TABLES['shop.orderitems']} itm
                    ON item.order_id = ord.order_id {$query_arr['default_filter']}";
            $res = DB_query($sql);
            if ($res) {
                $A = DB_fetchArray($res, false);
                $total_sales = $A['total_sales'];
                $total_tax = $A['total_tax'];
                $total_shipping = $A['total_shipping'];
                $total_total = $total_sales + $total_tax + $total_shipping;
            }
            $T->set_var(
                'output',
                \ADMIN_list(
                    'shop_rep_orderlist',
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr
                )
            );
            break;
        case 'csv':
            $sql .= ' ' . $query_arr['default_filter'];
            $sql .= ' GROUP BY ' . $query_arr['group_by'];
            $sql .= ' ORDER BY ' . $defsort_arr['field'] . ' ' . $defaort_arr['direction'];
            $res = DB_query($sql);
            $T->set_block('report', 'ItemRow', 'row');
            while ($A = DB_fetchArray($res, false)) {
                if (!empty($A['billto_company'])) {
                    $customer = $A['billto_company'];
                } else {
                    $customer = $A['billto_name'];
                }
                $order_date->setTimestamp($A['order_date']);
                $order_total = $A['sales_amt'] + $A['tax'] + $A['shipping'];
                $T->set_var(array(
                    'order_id'      => $A['order_id'],
                    'order_date'    => $order_date->format('Y-m-d', true),
                    'customer'      => $this->remQuote($customer),
                    'sales_amt'     => $Cur->formatValue($A['sales_amt']),
                    'tax'           => $Cur->formatValue($A['tax']),
                    'shipping'      => $Cur->formatValue($A['shipping']),
                    'total'         => $Cur->formatValue($order_total),
                    'nl'            => "\n",
                ) );
                $T->parse('row', 'ItemRow', true);
                $total_sales += $A['sales_amt'];
                $total_tax += $A['tax'];
                $total_shipping += $A['shipping'];
                $total_total += $order_total;
            }
            break;
        }

        $T->set_var(array(
            'startDate'         => $this->startDate->format($_CONF['shortdate'], true),
            'endDate'           => $this->endDate->format($_CONF['shortdate'], true),
            'total_sales'       => $Cur->formatValue($total_sales),
            'total_tax'         => $Cur->formatValue($total_tax),
            'total_shipping'    => $Cur->formatValue($total_shipping),
            'total_total'       => $Cur->formatValue($total_total),
            'nl'                => "\n",
        ) );
        $T->parse('output', 'report');
        $report = $T->finish($T->get_var('output'));
        return $this->getOutput($report);
    }

}   // class orderlist

?>
