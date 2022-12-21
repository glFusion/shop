<?php
/**
 * Item purchase report.
 * Shows each order item with pricing and option info.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Reports;
use Shop\Product;
use Shop\ProductVariant;
use Shop\ProductOptionGroup;
use Shop\Models\Request;
use Shop\Models\ProductCheckbox;


/**
 * Class for Order History Report.
 * @package shop
 */
class itempurchase extends \Shop\Report
{
    /** Icon to display on report menu
     * @var string
     */
    protected $icon = 'shopping-basket';

    /** Item ID being reported
     * @var integer
     */
    private $item_id;

    /** Item short description for report title
     * @var string
     */
    private $item_dscp;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->filter_item = true;
        $this->filter_uid = false;
        $this->filter_status = false;
        // This report doesn't show carts
        parent::__construct();
    }


    /**
     * Create and render the report contents.
     *
     * @return  string  HTML for report
     */
    public function Render() : string
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        $Request = Request::getInstance();
        $this->item_id = $Request->getString('item_id');
        $from_date = $this->startDate->toUnix();
        $to_date = $this->endDate->toUnix();
        $Product = Product::getByID($this->item_id);
        $this->item_dscp = $Product->getShortDscp();
        $this->item_id = DB_escapeString($this->item_id);
        $T = $this->getTemplate();

        $text_flds = $Product->getCustom();
        if (is_array($text_flds) && (count($text_flds) > 1 || !empty($text_flds[0]))) {
            $has_custom = true;
        } else {
            $has_custom = false;
        }

        $sql = "SELECT oi.*, oi.quantity as qty, ord.order_date, ord.uid,
            ord.billto_name, ord.billto_company, ord.status, ord.buyer_email
            FROM {$_TABLES['shop.orderitems']} oi
            LEFT JOIN {$_TABLES['shop.orders']} ord ON ord.order_id = oi.order_id";
//            LEFT JOIN {$_TABLES['shop.prodXcbox']} x ON ord.product_id = x.item_id
//            LEFT JOIN {$_TABLES['shop.product_option_vals']} pov ON x.item_id";

        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['purch_date'],
                'field' => 'order_date',
                'sort'  => true,
            ),
            array(
                'text'  => 'SKU',
                'field' => 'sku',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['order'],
                'field' => 'order_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['status'],
                'field' => 'status',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'customer',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['quantity'],
                'field' => 'qty',
                'sort'  => false,
                'align' => 'right',
            ),
        );

        $defsort_arr = array(
            'field'     => 'ord.order_date',
            'direction' => 'DESC',
        );

        $where = " WHERE ord.status <> 'cart'
            AND oi.product_id = '{$this->item_id}'
            AND (ord.order_date >= '$from_date'
            AND ord.order_date <= '$to_date')";
        if ($this->uid > 0) {
            $where .= " AND uid = {$this->uid}";
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
                '&period=' . $this->period . '&item_id=' . $this->item_id,
            'has_limit' => true,
            'has_paging' => true,
        );

        $q_str = $this->getQueryString(array('run' => 'orderlist'));
        if ($this->isAdmin) {
            $this->extra['uid_link'] = SHOP_ADMIN_URL . '/report.php?' . $q_str . '&uid=';
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
            if (!empty($text_flds)) {
                foreach ($text_flds as $idx=>$val) {
                    $text_flds[$idx] = $this->remQuote($val);
                }
                $text_flds = ',"' . implode('","', $text_flds) . '"';
                $T->set_var('custom_header', $text_flds);
            }
            $order_date = clone $_CONF['_now'];   // Create an object to be updated later
            $total_sales = 0;
            $total_shipping = 0;
            $total_tax = 0;
            $total_total = 0;
            $items = array();
            $variant_headers = array();
            $POGs = ProductOptionGroup::getByProduct($this->item_id);
            foreach ($POGs as $POG) {
                $variant_headers['pog_' . $POG->getID()] = $this->remQuote($POG->getName());
            }
            $cbox_headers = array();
            $Checkboxes = ProductCheckbox::getByProduct($this->item_id);
            foreach ($Checkboxes as $cBox) {
                $cbox_headers[$cBox->getOptionID()] = $cBox->getOptionValue();
            }

            while ($A = DB_fetchArray($res, false)) {
                if (!empty($A['billto_company'])) {
                    $customer = $A['billto_company'];
                } elseif (!empty($A['billto_name'])) {
                    $customer = $A['billto_name'];
                } else {
                    $customer = COM_getDisplayName($A['uid']);
                }
                $order_date->setTimestamp($A['order_date']);
                $item_total = $A['net_price'] * $A['quantity'];
                $order_total = $item_total + $A['tax'] + $A['shipping'] + $A['handling'];
                $total_sales += $item_total;
                $total_tax += $A['tax'];
                $total_shipping += $A['shipping'];
                $total_total += $order_total;
                $items[$A['id']] = array(
                    'item_name'     => $this->remQuote($this->item_dscp),
                    'sku'           => $this->remQuote($A['sku']),
                    'order_id'      => $this->remQuote($A['order_id']),
                    'order_date'    => $order_date->format('Y-m-d', true),
                    'customer'      => $this->remQuote($customer),
                    'qty'           => (float)$A['qty'],
                    'uid'           => (int)$A['uid'],
                    'email'         => $this->remQuote($A['buyer_email']),
                    'variants'      => array(),
                    'custom'        => '',
                );
                if ($A['variant_id'] > 0) {
                    $PV = ProductVariant::getInstance($A['variant_id']);
                    if ($PV->getID() > 0) {     // make sure it's a good record
                        foreach ($PV->getDscp() as $dscp) {
                            //$variant_headers[$dscp['name']] = $this->remQuote($dscp['name']);
                            $items[$A['id']]['variants'][$dscp['name']] = $this->remQuote($dscp['value']);
                        }
                    }
                }
                $extras = json_decode($A['extras'], true);
                if (isset($extras[0])) {
                    $extras = json_decode($extras[0],true);
                }
                if ($has_custom) {
                    if (!empty($extras) && isset($extras['custom']) && is_array($extras['custom'])) {
                        foreach ($extras['custom'] as $idx=>$val) {
                            $extras['custom'][$idx] = $this->remQuote($val);
                        }
                        $items[$A['id']]['custom'] = ',"' . implode('","', $extras['custom']) . '"';
                    }
                }
                if (!empty($cbox_headers)) {
                    $cbox_flds = array();
                    foreach ($cbox_headers as $opt_id=>$dummy) {
                        if (
                            isset($extras['options']) &&
                            is_array($extras['options']) &&
                            in_array($opt_id, $extras['options'])
                        ) {
                            $cbox_flds[$opt_id] = 'X';
                        } else {
                            $cbox_flds[$opt_id] = '';
                        }
                    }
                    $items[$A['id']]['cbox_flds'] = ',"' . implode('","', $cbox_flds) . '"';
                }
            }
            if (!empty($variant_headers)) {
                $T->set_var('variant_header', ',"' . implode('","', $variant_headers) . '"');
            }
            if (!empty($cbox_headers)) {
                $T->set_var('cbox_header', ',"' . implode('","', $cbox_headers) . '"');
            }


            $T->set_block('report', 'ItemRow', 'row');
            foreach ($items as $item) {
                $T->set_var(array(
                    'item_name'     => $item['item_name'],
                    'sku'           => $item['sku'],
                    'order_id'      => $item['order_id'],
                    'order_date'    => $item['order_date'],
                    'customer'      => $item['customer'],
                    'qty'           => $item['qty'],
                    'uid'           => $item['uid'],
                    'email'         => $item['email'],
                    'custom_flds'   => $item['custom'],
                    'nl'            => "\n",
                ) );
                $variants = array();
                if (!empty($variant_headers)) {
                    // Accumulate variant values, making sure every variant name
                    // is included with a blank value if necessary.
                    foreach ($variant_headers as $var_name) {
                        if (!isset($item['variants'][$var_name])) {
                            $item['variants'][$var_name] = '';
                        }
                        $variants[] = $item['variants'][$var_name];
                    }
                    $T->set_var('variants', ',"' . implode('","', $variants) . '"');
                }
                if (!empty($cbox_headers)) {
                    $T->set_var('cbox_flds', $item['cbox_flds']);
                }
                $T->parse('row', 'ItemRow', true);
            }
            break;
        }

        $T->set_var(array(
            'report_key' => $this->key,
            'item_id'   => $this->item_id,
            'item_dscp' => $this->item_dscp,
            'startDate' => $this->startDate->format($_CONF['shortdate'], true),
            'endDate'   => $this->endDate->format($_CONF['shortdate'], true),
            'nl'        => "\n",
        ) );
        $T->parse('output', 'report');
        $report = $T->finish($T->get_var('output'));
        return $this->getOutput($report);
    }


    /**
     * Get the report title, default is the report name.
     * This report appends the item number to the default title.
     *
     * @return  string  Report title
     */
    protected function getTitle()
    {
        return parent::getTitle() . ': ' . $this->item_dscp;
    }

}

