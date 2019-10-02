<?php
/**
 * Pending Shipments report by Shipper.
 * For a selected shipper, list all the pending fulfillments.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.1
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Reports;

/**
 * Class for Pending Shipments Report by Shipper.
 * @package shop
 */
class pendingship_shipper extends pendingship
{
    /**
     * Constructor. Override the allowed statuses.
     */
    public function __construct()
    {
        parent::__construct();
        if (isset($_GET['shipper_id'])) {
            $this->setParam('shipper_id', $_GET['shipper_id']);
        }
    }


    /**
     * Creates the configuration form for elements unique to this report.
     *
     * @return  string          HTML for edit form
     */
    protected function getReportConfig()
    {
        global $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $retval = '';
        $T = $this->getTemplate('config');
        $shipper_id = self::_getSessVar('shipper_id', 'int');
        $shippers = \Shop\Shipper::getAll(false);
        $T->set_block('report', 'shipperSelect', 'shipsel');
        foreach ($shippers as $id => $obj) {
            $T->set_var(array(
                'shipper_name' => $obj->name,
                'shipper_id'   => $id,
                'selected'  => $id == $shipper_id ? 'selected="selected"' : '',
            ) );
            $T->parse('shipsel', 'shipperSelect', true);
        }
        $retval .= $T->parse('output', 'report');
        return $retval;
    }


    /**
     * Create and render the report contents.
     *
     * @return  string  Output for report
     */
    public function Render()
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        $nonshipped = array();
        foreach ($this->statuses as $key=>$info) {
            if (!empty($info['chk'])) {
                $nonshipped[] = $key;
            }
        }
        $nonshipped = "'" . implode("','", $nonshipped) . "'";
        $Shipper = \Shop\Shipper::getInstance($this->shipper_id);
        if ($Shipper->isNew) {
            return $LANG_SHOP['no_data'];
        }
        self::_setSessVar('shipper_id', $Shipper->id);

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

        $query_arr = array(
            'table' => 'shop.orders',
            'sql' => $this->sql,
            'query_fields' => array(),
            'default_filter' => "WHERE ord.status IN ($nonshipped)
                AND ord.shipper_id = '{$Shipper->id}'",
            /*'default_filter' => "WHERE itm.product_id = '{$Item->id}'
            HAVING qty_shipped < itm.quantity",*/
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/report.php?run=' . $this->key .
                '&shipper_id=' . $Shipper->id,
            'has_limit' => true,
            'has_paging' => true,
        );

        $T = $this->getTemplate();
        switch ($this->type) {
        case 'html':
            $this->extra['class'] = __CLASS__;
            $T->set_var(array(
                'report_title' => sprintf($this->getTitle(), $Shipper->name),
                'output'    => \ADMIN_list(
                    'shop_rep_' . $this->key,
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr,
                    '', $this->extra, $this->_getListOptions()
                ),
            ) );
            break;
        case 'csv':
            // Create the report manually, this only uses the query parts
            $res = DB_query($sql . ' ' . $query_arr['default_filter']);
            $order_date = clone $_CONF['_now'];   // Create an object to be updated later
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
                'shopper_name'  => $Shipper->name,
                'report_key'    => $this->key,
                'nl'            => "\n",
            ) );
        $T->parse('output', 'report');
        $report = $T->finish($T->get_var('output'));
        return $this->getOutput($report);
    }

}

?>
