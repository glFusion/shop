<?php
/**
 * Payment report.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Reports;
use Shop\Currency;
use Shop\Gateway;
use Shop\GatewayManager;
use Shop\Payment as pmtClass;
use Shop\FieldList;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for Order History Report.
 * @package shop
 */
class payment extends \Shop\Report
{
    /** Icon to use in report selection.
     * @var string */
    protected $icon = 'money';

    /** Gateway name filter.
     * @var string */
    protected $gateway = '';

    /** Payment type filter.
     * `-1` indicates all types.
     * @var integer */
    protected $pmt_type = -1;

    /** Order ID if viewing payments against one order only.
     * @var string */
    protected $order_id = '';


    /**
     * Constructor. Set overrides for parent class.
     */
    public function __construct()
    {
        $this->filter_status = false;
        parent::__construct();
        if (isset($_REQUEST['gateway'])) {
            $this->setParam('gateway', $_REQUEST['gateway']);
        }
        if (isset($_REQUEST['pmt_type'])) {
            $this->setParam('pmt_type', $_REQUEST['pmt_type']);
        }
    }


    /**
     * Creates the configuration form for elements unique to this report.
     *
     * @return  string          HTML for edit form
     */
    protected function getReportConfig()
    {
        $retval = '';
        $T = $this->getTemplate('config');
        $gws = GatewayManager::getAll();
        $gateway = self::_getSessVar('gateway');
        $T->set_block('config', 'gw_opts', 'opt');
        foreach ($gws as $GW) {
            $T->set_var(array(
                'gw_name'   => $GW->getName(),
                'gw_dscp'   => $GW->getDisplayName(),
                'sel'       => $gateway == $GW->getName() ? 'selected="selected"' : '',
                'pt_sel_' . $this->_getSessVar('pmt_type', 'string', '-1') => 'checked="checked"',
            ) );
            $T->parse('opt', 'gw_opts', true);
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
        global $_TABLES, $_CONF, $LANG_SHOP, $LANG_ADMIN, $_SHOP_CONF;

        $sql = "SELECT pmt.* FROM {$_TABLES['shop.payments']} pmt";

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'pmt_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['order'],
                'field' => 'pmt_order_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['datetime'],
                'field' => 'pmt_ts',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['pmt_method'],
                'field' => 'pmt_method',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['txn_id'],
                'field' => 'pmt_ref_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['comment'],
                'field' => 'pmt_comment',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['amount'],
                'field' => 'pmt_amount',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['complete'],
                'field' => 'is_complete',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort'  => 'false',
                'align' => 'center',
            ),
        );
        $this->setExtra('order_id', $this->order_id);
        $defsort_arr = array(
            'field' => 'pmt_ts',
            'direction' => 'DESC',
        );

        $filter = "WHERE pmt_ts BETWEEN {$this->startDate->toUnix()} AND {$this->endDate->toUnix()}";
        if ($this->order_id != 'x' && !empty($this->order_id)) {
            $filter .= " AND pmt.pmt_order_id = '" . DB_escapeString($this->order_id) . "'";
            $title = $LANG_SHOP['order'] . ' ' . $this->order_id;
        } else {
            $title = '';
        }

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $options = array(
            'chkselect' => 'true',
            'chkname'   => 'payments',
            'chkfield'  => 'pmt_id',
        );
        if (!empty($this->gateway)) {
            $filter .= " AND pmt_gateway = '" . DB_escapeString($this->gateway) . "'";
        }
        if ($this->pmt_type > -1) {
            $filter .= " AND is_money = '{$this->pmt_type}'";
        }
        /*if (!empty($filter)) {
            $filter = 'WHERE 1=1' . $filter;
        }*/
        $query_arr = array(
            'table' => 'shop.payments',
            'sql' => $sql,
            'query_fields' => array('pmt_gateway', 'pmt_method', 'pmt_ref_id', 'pmt_comment'),
            'default_filter' => $filter,
        );
        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/report.php?run=' . $this->key .
                '&perod=' . $this->period . '&gateway=' . $this->gateway,
        );

        $T = $this->getTemplate();
        switch ($this->type) {
        case 'html':
            $this->setExtra('class', __CLASS__);
            $T->set_var(array(
                'output' => \ADMIN_list(
                    $_SHOP_CONF['pi_name'] . '_payments',
                    array(get_parent_class(), 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr,
                    '', $this->extra, '', ''
                ),
            ) );
            break;
        case 'csv':
            $total_paid = 0;
            $sql .= ' ' . $query_arr['default_filter'];
            $res = DB_query($sql);
            $T->set_block('report', 'ItemRow', 'row');
            while ($A = DB_fetchArray($res, false)) {
                $T->set_var(array(
                    'order_id'  => $A['pmt_order_id'],
                    'datetime'  => $A['pmt_ts'],
                    'pmt_method' => $A['pmt_method'],
                    'txn_id'    => $A['pmt_ref_id'],
                    'comment'   => $A['pmt_comment'],
                    'amount'    => $A['pmt_amount'],
                    'nl'            => "\n",
                ) );
                $T->parse('row', 'ItemRow', true);
                $total_paid += $A['pmt_amount'];
            }
            $T->set_var('total_paid', $total_paid);
            break;
        }

        $T->set_var(array(
            'startDate'     => $this->startDate->format($_CONF['shortdate'], true),
            'endDate'       => $this->endDate->format($_CONF['shortdate'], true),
            'report_key'    => $this->key,
        ) );
        $T->parse('output', 'report');
        $report = $T->finish($T->get_var('output'));
        return $this->getOutput($report);
    }


    /**
     * Display the detail for a single item.
     *
     * @param   mixed   $val    Value to display
     * @param   string  $key    Name of variable used in Admin list
     * @return  string      HTML for item detail report
     */
    public function RenderDetail($pmt_id)
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        $pmt_id = (int)$pmt_id;
        $db = Database::getInstance();
        try {
            $A = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.payments']} pmts
                WHERE pmts.pmt_id = ?",
                array($pmt_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A = false;
        }
        if (empty($A)) {
            return "Nothing Found";
        }

        try {
            $ipns = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.ipnlog']}
                WHERE gateway = ? AND ref_id = ?
                ORDER BY ts ASC",
                array($A['pmt_gateway'], $A['pmt_ref_id']),
                array(Database::STRING, Database::STRING)
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $ipns = false;
        }
        if (is_array($ipns)) {
            $ipn_count = count($ipns);
        } else {
            $ipn_count = 0;
        }

        // Allow all json-encoded data to be available to the template
        $Dt = new \Date($A['pmt_ts'], $_CONF['timezone']);
        $T = $this->getTemplate('single');
        $T->set_var(array(
            'pmt_id'    => $A['pmt_id'],
            'pmt_amount' => Currency::getInstance()->Format($A['pmt_amount']),
            'time'      => SHOP_dateTooltip($Dt),
            'order_link' => COM_createLink(
                $A['pmt_order_id'],
                SHOP_ADMIN_URL . '/orders.php?order=' . $A['pmt_order_id']
            ),
            'txn_id'    => $A['pmt_ref_id'],
            'gateway'   => $A['pmt_gateway'],
            'comment'   => $A['pmt_comment'],
            'ipn_count' => $ipn_count,
        ) );

        switch ($ipn_count) {
        case 0:
            // Nothing to display
            break;
        case 1:
            // Set the single ipn on the page, not in a dropdown block
            $T->set_var('single_ipn', var_export(json_decode($ipns[0]['ipn_data'], true), true));
            break;
        default:
            // Show the clickable links to expand each IPN log
            $T->set_block('report', 'ipnRows', 'ipnRow');
            foreach ($ipns as $ipn) {
                $dt = new \Date($ipn['ts']);
                $T->set_var(array(
                    'ipn_id' => $ipn['id'],
                    'ipn_date' => $dt->toMySQL(true),
                    'ipn_event' => $ipn['event'],
                    'ipn_data' => var_export(json_decode($ipn['ipn_data'], true), true),
                ) );
                $T->parse('ipnRow', 'ipnRows', true);
            }
            break;
        }
        $retval = $T->parse('output', 'report');
        return $retval;
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
        global $LANG_SHOP, $_CONF;

        $retval = NULL;
        switch ($fieldname) {
        case 'pmt_order_id':
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/orders.php?order=' . $fieldvalue
            );
            break;

        case 'pmt_ref_id':
            $retval = COM_createLink(
                $fieldvalue,
                pmtClass::getDetailUrl($A['pmt_id']),
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_SHOP['see_details'],
                )
            );
            break;

        case 'pmt_amount':
            $retval = self::formatMoney($fieldvalue);
            break;

        case 'pmt_ts':
            $D = new \Date($fieldvalue, $_CONF['timezone']);
            $retval = $D->toMySQL(true);
            break;

        case 'is_complete':
            if ((int)$fieldvalue > 0) {
                $retval = FieldList::checkmark(array(
                    'active' => true,
                ) );
            }
            break;

        case 'delete':
            $retval = FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL . '/payments.php?delpayment=' . $A['pmt_id'] . '&order_id=' . $extra['order_id'],
                'attr' => array(
                    'onclick' => "return confirm('{$LANG_SHOP['q_del_item']}');",
                ),
            ) );
            break;
        }

        return $retval;
    }

}

?>
