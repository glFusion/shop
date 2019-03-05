<?php
/**
 * Pending Shipments report.
 * For a selected item, list all the pending fulfillments.
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
class pendingship extends \Shop\Report
{
    /**
     * Creates the report form.
     *
     * @return  string          HTML for edit form
     */
    public function Configure()
    {
        global $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $retval = '';
        $T = $this->getTemplate('config');
        $type = self::_getSessVar('output_type', 'html');
        $item_id = self::_getSessVar('item_id');
        $items = \Shop\Product::getAll();
        $T->set_block('report', 'itemSelect', 'itemsel');
        foreach ($items as $id => $obj) {
            if (!$obj->isPhysical()) {
                continue;
            }
            $T->set_var(array(
                'item_name' => $obj->name,
                'item_id'   => $id,
                'selected'  => $id == $item_id ? 'selected="selected"' : '',
            ) );
            $T->parse('itemsel', 'itemSelect', true);
        }
        $T->set_var(array(
            $type . '_sel'  => 'checked="checked"',
            'report_key'    => $this->key,
        ) );
        $retval .= $T->parse('output', 'report');
        return $retval;
    }   // function showForm()


    /**
     * Create and render the report contents.
     *
     * @return  string  HTML for report
     */
    public function Render()
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        $this->setType($_GET['out_type']);
        self::_setSessVar('item_id', $_GET['item_id']);
        $T = $this->getTemplate();
        $statuses = \Shop\OrderStatus::getAll();
        if (!array_key_exists('shipped', $statuses)) {
            // TODO, actual error message
            echo "no shipping status defined";die;
        }
        $shipped_orderby = (int)$statuses['shipped']->orderby;
        $nonshipped = array();
        foreach ($statuses as $s) {
            if ($s->orderby < $shipped_orderby) {
                $nonshipped[] = $s->getName();
            } else {
                // getAll() sorts by orderby, so we can quit checking here
                break;
            }
        }
        $nonshipped = "'" . implode("','", $nonshipped) . "'";
        $Item = \Shop\Product::getInstance($_GET['item_id']);

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
                'text'  => $LANG_SHOP['quantity'],
                'field' => 'quantity',
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

        $sql = "SELECT ord.*, itm.quantity
            FROM {$_TABLES['shop.orderitems']} itm
            LEFT JOIN {$_TABLES['shop.orders']} ord
                ON itm.order_id = ord.order_id";

            /*WHERE ord.status IN ($nonshipped)
                AND itm.product_id = '{$Item->id}'*/
//            ORDER BY ord.order_date ASC";

        $query_arr = array(
            'table' => 'shop.orders',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => "WHERE ord.status IN ($nonshipped)
                AND itm.product_id = '{$Item->id}'",
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/report.php?run=' . $this->key .
                '&item_id=' . $Item->id,
            'has_limit' => true,
            'has_paging' => true,
        );

        switch ($this->type) {
        case 'html':
            $T->set_var(array(
                'output'    => \ADMIN_list(
                    'shop_rep_pendingship', '\Shop\getReportField',
                    $header_arr, $text_arr, $query_arr, $defsort_arr
                ),
            ) );
            break;
        case 'csv':
            // Create the report manually, this only uses the query parts
            $res = DB_query($sql . ' ' . $query_arr['default_filter']);
            $order_date = SHOP_now();   // Create an object to be updated later
            $qty_sum = 0;
            $T->set_block('report', 'ItemRow', 'row');
            while ($A = DB_fetchArray($res, false)) {
                if (!empty($A['billto_company'])) {
                    $customer = $A['billto_company'];
                } else {
                    $customer = $A['billto_name'];
                }
                $order_date->setTimestamp($A['order_date']);
                $qty_sum = $A['quantity'];
                $T->set_var(array(
                    'order_id'      => $A['order_id'],
                    'order_date'    => $order_date->format('Y-m-d', true),
                    'customer'      => $this->remQuote($customer),
                    'quantity'      => $A['quantity'],
                    'status'        => $A['status'],
                    'nl'            => "\n",
                ) );
                $T->parse('row', 'ItemRow', true);
                $total_quantity+= $qty_sum;
            }
            break;
        }
            $T->set_var(array(
                'item_name'     => $Item->name,
                'report_key'    => $this->key,
                'nl'            => "\n",
            ) );
        $T->parse('output', 'report');
        $report = $T->finish($T->get_var('output'));
        return $this->getOutput($report);
    }

}   // class orderlist

?>
