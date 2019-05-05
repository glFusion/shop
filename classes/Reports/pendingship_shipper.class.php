<?php
/**
 * Pending Shipments report.
 * For a selected item, list all the pending fulfillments.
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
 * Class for Pending Shipments Report.
 * @package shop
 */
class pendingship_shipper extends \Shop\Report
{
    protected $icon = 'truck';

    /**
     * Constructor. Override the allowed statuses.
     */
    public function __construct()
    {
        // This report doesn't show shipped or closed statuses.
        $this->allowed_statuses = array(
            'pending',
            'paid',
            'processing',
        );
        $this->filter_dates = false;
        $this->filter_uid = false;
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
        $shipper_id = self::_getSessVar('shipper_id');
        $shippers = \Shop\Shipper::getAll();
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
     * @return  string  HTML for report
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

        $sql = "SELECT ord.*, itm.quantity
            FROM {$_TABLES['shop.orderitems']} itm
            LEFT JOIN {$_TABLES['shop.orders']} ord
                ON itm.order_id = ord.order_id";

        $query_arr = array(
            'table' => 'shop.orders',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => "WHERE ord.status IN ($nonshipped)
                AND ord.shipper_id = '{$Shipper->id}'",
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
                    'shop_rep_pendingship',
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr,
                    '', $this->extra
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
        }
        return $retval;
    }

}

?>
