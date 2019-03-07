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
class ipnlog extends \Shop\Report
{
    /**
     * Creates the configuration form for elements unique to this report.
     *
     * @return  string          HTML for edit form
     */
    protected function getReportConfig()
    {
        $retval = '';
        $this->filter_status = false;
        $T = $this->getTemplate('config');
        $gws = \Shop\Gateway::getAll();
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

        $this->setType($_GET['out_type']);
        $dates = parent::getDates($_GET['period'], $_GET['from_date'], $_GET['to_date']);
        $this->startDate = $dates['start'];
        $this->endDate = $dates['end'];
        $gateway = $this->setGateway($_GET['gateway']);
        $T = $this->getTemplate();

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
        );

        $defsort_arr = array(
            'field'     => 'ts',
            'direction' => 'desc',
        );

        $query_arr = array(
            'table' => 'shop.ipnlog',
            'sql'   => $sql,
            'query_fields' => array('ip_addr', 'txn_id'),
            'default_filter' => "WHERE ts BETWEEN {$this->startDate->toUnix()} AND {$this->endDate->toUnix()}",
        );
        if (!empty($gateway)) {
            $query_arr['default_filter'] .= " AND gateway = '" .
                DB_escapeString($gateway) . "'";
        }

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/report.php?run=' . $this->key .
                '&perod=' . $period . '&gateway=' . $gateway,
        );

        if (!isset($_REQUEST['query_limit'])) {
            $_GET['query_limit'] = 20;
        }

        switch ($this->type) {
        case 'html':
            $T->set_var(array(
                'output' => \ADMIN_list(
                    $_SHOP_CONF['pi_name'] . '_ipnlog',
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr,
                    '', '', '', ''
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

}

?>
