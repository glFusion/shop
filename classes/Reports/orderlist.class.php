<?php
/**
 * Order History Report.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016 Lee Garner <lee@leegarner.com>
 * @package     paypal
 * @version     0.5.8
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Paypal\Reports;

/**
 * Class for Order History Report.
 * @package paypal
 */
class orderlist extends \Paypal\Report
{
    /**
     * Constructor.
     * Sets the report key name and calls the parent constructor.
     */
    public function __construct()
    {
        $this->key = 'orderlist';
        parent::__construct();
    }


    /**
     * Creates the report form.
     *
     * @return  string          HTML for edit form
     */
    public function showForm()
    {
        global $_PP_CONF, $LANG_PP, $_SYSTEM;

        $retval = '';
        $T = PP_getTemplate($this->key, 'report', 'reports');
        $T->set_var(array(
            'from_date' => '1900-01-01',
            'to_date' => '9999-12-31',
        ) );

        $T->set_block('report', 'statusOpts', 'statOpt');
        foreach ($LANG_PP['orderstatus'] as $key => $data) {
            $T->set_var(array(
                'status_key' => $key,
                'status' => isset($LANG_PP['orderstatus'][$key]) ?
                        $LANG_PP['orderstatus'][$key] : $key,
            ) );
            $T->parse('statOpt', 'statusOpts', true);
        }
        $retval .= $T->parse('output', 'report');
        return $retval;

    }   // function showForm()


    /**
     * Create and render teh report contents.
     *
     * @return  string  HTML for report
     */
    public function Render()
    {
        global $_TABLES;

        $from_date = DB_escapeString($_POST['from_date']);
        $to_date = DB_escapeString($_POST['to_date']);
        $sql = "SELECT * FROM {$_TABLES['paypal.orders']}
                WHERE (order_date >= '$from_date' AND order_date <= '$to_date')";
        if (!empty($_POST['orderstatus'])) {
            $status_sql = "'" . implode("','", $_POST['orderstatus']) . "'";
            $sql .= " AND status in ($status_sql)";
        }
        echo $sql;die;
    }

}   // class orderlist

?>
