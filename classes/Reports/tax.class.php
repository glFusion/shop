<?php
/**
 * Sales Tax Report.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.0
 * @since       v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Reports;
use Shop\Currency;
use Shop\Company;
use Shop\Country;
use Shop\State;


/**
 * Class for Sales Tax Report.
 * @package shop
 */
class tax extends \Shop\Report
{
    /** Report icon name
     * @var string */
    protected $icon = 'institution';


    /**
     * Constructor.
     * Override the allowed statuses.
     */
    public function __construct()
    {
        // This report doesn't show shipped or closed statuses.
        $this->allowed_statuses = array(
            'paid',
            'processing',
            'shipped', 'closed', 'complete',
        );
        $this->filter_uid = false;
        parent::__construct();
    }


    /**
     * Get additional config fields for this report.
     *
     * @return  string      HTML for form fields
     */
    protected function getReportConfig()
    {
        $C = new Company;   // use shop location as default region
        $T = $this->getTemplate('config');
        $state = self::_getSessVar('state');
        $country = self::_getSessVar('country');
        $incl_nontax = self::_getSessVar('incl_nontax');
        if (empty($country)) {
            $country = $C->getCountry();
        }
        if (empty($state)) {
            $state = $C->getState();
        }
        $T->set_var(array(
            'state_options' => State::optionList($country, $state),
            'country_options' => Country::optionList($country),
            'zip' => $this->_getSessVar('zip'),
            'incl_nontax_chk' => $incl_nontax ? 'checked="checked"' : '',
        ) );
        return $T->parse('output', 'report');
    }


    /**
     * Create and render the report contents.
     *
     * @return  string  HTML for report
     */
    public function Render()
    {
        global $_TABLES, $_CONF, $LANG_SHOP, $LANG_SHOP_HELP, $_USER;

        $T = $this->getTemplate();
        $from_date = $this->startDate->toUnix();
        $to_date = $this->endDate->toUnix();
        $this->setParam('country', SHOP_getVar($_GET, 'country'));
        $this->setParam('state', SHOP_getVar($_GET, 'state'));
        $this->setParam('zip', SHOP_getVar($_GET, 'zip'));
        $this->setParam('incl_nontax', SHOP_getVar($_GET, 'incl_nontax', 'integer', 0));
        $this->setParam('orderstatus', SHOP_getVar($_GET, 'orderstatus', 'array', array()));

        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['customer'],
                'field' => 'customer',
                'sort'  => true,
            ),
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
                'text'  => $LANG_SHOP['taxable'],
                'field' => 'net_taxable',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['nontaxable'],
                'field' => 'net_nontax',
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
                'text'  => $LANG_SHOP['handling'],
                'field' => 'handling',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['total'],
                'field' => 'order_total',
                'sort'  => true,
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
            array(
                'text'  => $LANG_SHOP['region'],
                'field' => 'region',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['zip'],
                'field' => 'shipto_zip',
                'sort'  => true,
            ),
        );
        $this->setExtra('uid_link', $_CONF['site_url'] . '/users.php?mode=profile&uid=');
        //$listOptions = $this->_getListOptions();
        $listOptions = '';
        $form_url = SHOP_ADMIN_URL . '/report.php?' . self::getQueryString(); //run=' . $this->key;
        $defsort_arr = array(
            'field' => 'order_date',
            'direction' => 'DESC',
        );

        $sql = "SELECT ord.* FROM {$_TABLES['shop.orders']} ord ";
        $orderstatus = $this->orderstatus;
        if (empty($orderstatus)) {
            $orderstatus = $this->allowed_statuses;
        }
        if (!empty($orderstatus)) {
            $status_sql = "'" . implode("','", $orderstatus) . "'";
            $status_sql = " ord.status in ($status_sql) AND ";
        }

        $where = "$status_sql (ord.order_date >= '$from_date' AND ord.order_date <= '$to_date')";
        if ($this->country != '') {
            $where .= " AND shipto_country = '" . DB_escapeString($this->country) . "'";
        }
        if ($this->state != '') {
            $where .= " AND shipto_state = '" . DB_escapeString($this->state) . "'";
        }
        if ($this->zip != '') {
            $where .= " AND shipto_zip LIKE '%" . DB_escapeString($this->zip) . "%'";
        }
        if ($this->uid > 0) {
            $where .= " AND uid = {$this->uid}";
        }
        if (!$this->incl_nontax) {
            $where .= ' AND net_taxable > 0';
        }
        $query_arr = array(
            'table' => 'shop.orders',
            'sql' => $sql,
            'query_fields' => array(
                'billto_name', 'billto_company', 'billto_address1',
                'billto_address2','billto_city', 'billto_state',
                'billto_country', 'billto_zip',
                'shipto_name', 'shipto_company', 'shipto_address1',
                'shipto_address2','shipto_city', 'shipto_state',
                'shipto_country', 'shipto_zip',
                'phone', 'buyer_email', 'ord.order_id',
            ),
            'default_filter' => "WHERE $where",
            //'group_by' => 'ord.order_id',
        );
        //echo $sql . ' WHERE ' . $where;die;
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
        $order_date = clone $_CONF['_now'];   // Create an object to be updated later
        $Cur = \Shop\Currency::getInstance();

        switch ($this->type) {
        case 'html':
            $this->setExtra('class', __CLASS__);
            // Get the totals, have to use a separate query for this.
            $s = "SELECT SUM(itm.quantity * itm.price) as total_sales,
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
            $filter = '<select name="period">' . $this->getPeriodSelection($this->period, false) . '</select>';
            //$filter = '';
            $T->set_var(
                'output',
                \ADMIN_list(
                    'shop_rep_orderlist',
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr,
                    $filter, $this->extra, $listOptions
                )
            );
            break;
        case 'csv':
            // Assemble the SQL manually from the Admin list components
            $sql .= ' ' . $query_arr['default_filter'];
            $sql .= ' ORDER BY ' . $defsort_arr['field'] . ' ' . $defaort_arr['direction'];
            $res = DB_query($sql);
            $T->set_block('report', 'ItemRow', 'row');
            while ($A = DB_fetchArray($res, false)) {
                if (!empty($A['shipto_company'])) {
                    $customer = $A['shipto_company'];
                } else {
                    $customer = $A['shipto_name'];
                }
                $order_date->setTimestamp($A['order_date']);
                $T->set_var(array(
                    'order_id'      => $A['order_id'],
                    'order_date'    => $order_date->format('Y-m-d', true),
                    'customer'      => $this->remQuote($customer),
                    'taxable'       => $Cur->FormatValue($A['net_taxable']),
                    'nontax'        => $Cur->FormatValue($A['net_nontax']),
                    'tax'           => $Cur->FormatValue($A['tax']),
                    'shipping'      => $Cur->FormatValue($A['shipping']),
                    'handling'      => $Cur->FormatValue($A['handling']),
                    'total'         => $Cur->FormatValue($A['order_total']),
                    'region'        => $A['shipto_state'] . ', ' . $A['shipto_country'],
                    'zip'           => $A['shipto_zip'],
                    'nl'            => "\n",
                ) );
                $T->parse('row', 'ItemRow', true);
                $total_taxable += $A['net_taxable'];
                $total_nontax += $A['net_nontax'];
                $total_tax += $A['tax'];
                $total_shipping += $A['shipping'];
                $total_handling += $A['handling'];
                $total_total += $A['order_total'];
            }
            break;
        }

        $T->set_var(array(
            'startDate'         => $this->startDate->format($_CONF['shortdate'], true),
            'endDate'           => $this->endDate->format($_CONF['shortdate'], true),
            'total_taxable'     => $Cur->FormatValue($total_taxable),
            'total_nontax'      => $Cur->FormatValue($total_nontax),
            'total_tax'         => $Cur->FormatValue($total_tax),
            'total_shipping'    => $Cur->FormatValue($total_shipping),
            'total_handling'    => $Cur->FormatValue($total_handling),
            'total_total'       => $Cur->FormatValue($total_total),
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

        $retval = NULL;
        switch ($fieldname) {
        case 'region':
            $url = SHOP_ADMIN_URL . '/report.php?' . self::getQueryString();
            $c_url = $url . "&country={$A['shipto_country']}&state=";
            $s_url = $url . "&country={$A['shipto_country']}&state={$A['shipto_state']}";
            $retval = COM_createLink($A['shipto_state'], $s_url) . ', ' .
                COM_createLink($A['shipto_country'], $c_url);
            break;
        case 'shipto_zip':
            $url = SHOP_ADMIN_URL . '/report.php?' . self::getQueryString(array('zip' => $fieldvalue));
            $retval = COM_createLink($A['shipto_zip'], $url);
            break;
        case 'status':
            $retval = ucfirst($fieldvalue);
        }
        return $retval;
    }

}   // class orderlist

?>
