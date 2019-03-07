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
class itempurchase extends \Shop\Report
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->filter_item = true;
        parent::__construct();
    }


    /**
     * Create and render the report contents.
     *
     * @return  string  HTML for report
     */
    public function Render()
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        $this->setType($_GET['out_type']);
        $this->setItem($_GET['item_id']);
        $dates = parent::getDates($_GET['period'], $_GET['from_date'], $_GET['to_date']);
        $item_id = SHOP_getVar($_GET, 'item_id');
        self::_setSessVar('item_id', $item_id);
        $this->startDate = $dates['start'];
        $this->endDate = $dates['end'];
        $T = $this->getTemplate();
        $from_date = $this->startDate->toUnix();
        $to_date = $this->endDate->toUnix();
        if (is_numeric($item_id)) {
            $Item = new \Shop\Product($item_id);
            $item_dscp = $Item->short_description;
        } else {
            $item_dscp = $item_id;
            $item_id = DB_escapeString($item_id);
        }

        $sql = "SELECT purch.*, purch.quantity as qty, ord.order_date, ord.uid,
            ord.billto_name, ord.billto_company
            FROM {$_TABLES['shop.orderitems']} purch
            LEFT JOIN {$_TABLES['shop.orders']} ord ON ord.order_id = purch.order_id";

        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['purch_date'],
                'field' => 'order_date',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['quantity'],
                'field' => 'qty',
                'sort' => false,
            ),
            array(
                'text'  => $LANG_SHOP['order'],
                'field' => 'order_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'customer',
                'sort' => true,
            ),
        );

        $defsort_arr = array(
            'field'     => 'ord.order_date',
            'direction' => 'DESC',
        );

        $where = " WHERE purch.product_id = '$item_id' AND (ord.order_date >= '$from_date' AND ord.order_date <= '$to_date')";
        $uid = SHOP_getVar($_GET, 'uid', 'integer');
        self::_setSessVar('uid', $uid);
        if ($uid > 0) {
            $where .= " AND uid = $uid";
        }

        $query_arr = array(
            'table' => 'shop.orderstatus',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => $where,
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/report.php?run=' . $this->key .
                '&period=' . $_GET['period'] . '&item_id=' . $item_id,
            'has_limit' => true,
            'has_paging' => true,
        );

        parse_str($_SERVER['QUERY_STRING'], $params);
        $params['run'] = 'orderlist';
        unset($params['uid']);
        $q_str = http_build_query($params);
        if ($this->isAdmin) {
            $this->extra['uid_link'] = SHOP_ADMIN_URL . '/report.php?' . $q_str . '&uid='
        }
        if (!isset($_REQUEST['query_limit'])) {
            $_GET['query_limit'] = 20;
        }

        switch ($this->type) {
        case 'html':
            $T->set_var(
                'output',
                \ADMIN_list(
                    'shop_rep_itemhistory',
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr, '', $this->extra
                )
            );
            break;
        case 'csv':
            $sql .= ' ' . $query_arr['default_filter'];
            $res = DB_query($sql);
            $T->set_block('report', 'ItemRow', 'row');
            $order_date = SHOP_now();   // Create an object to be updated later
            while ($A = DB_fetchArray($res, false)) {
                if (!empty($A['billto_company'])) {
                    $customer = $A['billto_company'];
                } else {
                    $customer = $A['billto_name'];
                }
                $order_date->setTimestamp($A['order_date']);
                $order_total = $A['sales_amt'] + $A['tax'] + $A['shipping'];
                $T->set_var(array(
                    'item_name'     => $item_dscp,
                    'order_id'      => $A['order_id'],
                    'order_date'    => $order_date->format('Y-m-d', true),
                    'customer'      => $this->remQuote($customer),
                    'qty'           => $A['qty'],
                    'uid'           => $A['uid'],
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
            'report_key' => $this->key,
            'item_id'   => $item_id,
            'item_dscp' => $item_dscp,
            'startDate' => $this->startDate->format($_CONF['shortdate'], true),
            'endDate'   => $this->endDate->format($_CONF['shortdate'], true),
            'nl'        => "\n",
        ) );
        $T->parse('output', 'report');
        $report = $T->finish($T->get_var('output'));
        return $this->getOutput($report);
    }

}   // class orderlist

?>
