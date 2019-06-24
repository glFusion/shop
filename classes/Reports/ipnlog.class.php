<?php
/**
 * Order History Report.
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
 * Class for Order History Report.
 * @package shop
 */
class ipnlog extends \Shop\Report
{
    /** Icon to use in report selection.
     * @var string */
    protected $icon = 'money';


    /**
     * Constructor. Set overrides for parent class.
     */
    public function __construct()
    {
        $this->filter_uid = false;
        $this->filter_status = false;
        parent::__construct();
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
        $gws = \Shop\Gateway::getAll();
        $gateway = self::_getSessVar('gateway');
        $T->set_block('config', 'gw_opts', 'opt');
        foreach ($gws as $GW) {
            $T->set_var(array(
                'gw_name'   => $GW->Name(),
                'gw_dscp'   => $GW->DisplayName(),
                'sel'       => $gateway == $GW->Name() ? 'selected="selected"' : '',
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
        global $_TABLES, $_CONF, $LANG_SHOP;

        $this->setParam('gateway', SHOP_getVar($_GET, 'gateway'));

        $sql = "SELECT * FROM {$_TABLES['shop.ipnlog']} ";

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'ipn_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['ip_addr'],
                'field' => 'ip_addr',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['datetime'],
                'field' => 'ts',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['verified'],
                'field' => 'verified',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['txn_id'],
                'field' => 'txn_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['gateway'],
                'field' => 'gateway',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['order_number'],
                'field' => 'order_id',
                'sort'  => true,
            ),
        );

        $defsort_arr = array(
            'field'     => 'ts',
            'direction' => 'desc',
        );

        $where = "WHERE ts BETWEEN {$this->startDate->toUnix()} AND {$this->endDate->toUnix()}";
        if (!empty($this->gateway)) {
            $where .= " AND gateway = '" . DB_escapeString($this->gateway) . "'";
        }

        $query_arr = array(
            'table' => 'shop.ipnlog',
            'sql'   => $sql,
            'query_fields' => array('ip_addr', 'txn_id', 'ipn_data'),
            'default_filter' => $where,
        );
        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/report.php?run=' . $this->key .
                '&perod=' . $period . '&gateway=' . $gateway,
        );

        switch ($this->type) {
        case 'html':
            $T = $this->getTemplate();
            $this->setExtra('class', __CLASS__);
            $T->set_var(array(
                'output' => \ADMIN_list(
                    $_SHOP_CONF['pi_name'] . '_ipnlog',
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr,
                    '', $this->extra, '', ''
                ),
            ) );
            break;
        case 'csv':
            $sql .= ' ' . $query_arr['default_filter'];
            $res = DB_query($sql);
            $T->set_block('report', 'ItemRow', 'row');
            while ($A = DB_fetchArray($res, false)) {
                $T->set_var(array(
                    'ipn_id'    => $A['id'],
                    'ipn_date'  => $A['ts'],
                    'txn_id'    => $A['txn_id'],
                    'ip_addr'   => $A['ip_addr'],
                    'gateway'   => $A['gateway'],
                    'nl'            => "\n",
                ) );
                $T->parse('row', 'ItemRow', true);
            }
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
    public function RenderDetail($val, $key='id')
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        switch($key) {
        case 'txn_id':
            $val = DB_escapeString($val);
            break;
        case 'id':
        default:
            $key = 'id';
            $val = (int)$val;
            break;
        }
        $sql = "SELECT * FROM {$_TABLES['shop.ipnlog']}
            WHERE $key = '$val'";
        $res = DB_query($sql);
        $A = DB_fetchArray($res, false);
        if (empty($A)) {
            return "Nothing Found";
        }

        // Allow all serialized data to be available to the template
        $ipn = @unserialize($A['ipn_data']);
        $gw = \Shop\Gateway::getInstance($A['gateway']);
        if ($gw !== NULL && $ipn !== NULL) {
            $vals = $gw->ipnlogVars($ipn);

            // Create ipnlog template
            $T = $this->getTemplate('single');

            // Display the specified ipnlog row
            $Dt = new \Date($A['ts'], $_CONF['timezone']);
            $T->set_var(array(
                'id'        => $A['id'],
                'ip_addr'   => $A['ip_addr'],
                'time'      => SHOP_dateTooltip($Dt),
                'txn_id'    => $A['txn_id'],
                'gateway'   => $A['gateway'],
                 //'pmt_gross' => $vals['pmt_gross'],
                //'verified'  => $vals['verified'],
                //'pmt_status' => $vals['pmt_status'],
            ) );

            if (!empty($vals)) {
                $T->set_block('report', 'DataBlock', 'Data');
                foreach ($vals as $key=>$value) {
                    $T->set_var(array(
                        'prompt'    => isset($LANG_SHOP[$key]) ? $LANG_SHOP[$key] : $key,
                        'value'     => htmlspecialchars($value, ENT_QUOTES, COM_getEncodingt()),
                    ) );
                    $T->parse('Data', 'DataBlock', true);
                }
            }
            if ($ipn) {
                $T->set_block('report', 'rawBlock', 'Raw');
                $T->set_var('ipn_data', true);
                foreach ($ipn as $name => $value) {
                    $T->set_var(array(
                        'name'  => $name,
                        'value' => $value,
                    ) );
                    $T->parse('Raw', 'rawBlock', true);
                }
            }
            $retval = $T->parse('output', 'report');
        }
        return $retval;
    }


    /**
     * Set the selected gateway name and return a DB-safe version of it.
     *
     * @param   string  $gw_name    Gateway name
     * @return  string      Sanitized version of the gateway name
     */
    private function setGateway($gw_name)
    {
        $this->_setSessVar('gateway', $gw_name);
        return DB_escapeString($gw_name);
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

        switch ($fieldname) {
        case 'ipn_id':
            $retval = COM_createLink(
                $A['id'],
                SHOP_ADMIN_URL . '/index.php?ipndetail=x&amp;id=' . $A['id'],
                array(
                    'target' => '_blank',
                    'class' => 'tooltip',
                    'title' => $LANG_SHOP['see_details'],
                )
            );
            break;

        case 'verified':
            $retval = $fieldvalue > 0 ? 'True' : 'False';
            break;
        }
        return $retval;
    }

}

?>
