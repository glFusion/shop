<?php
/**
 * Class to manage reports.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016 Lee Garner <lee@leegarner.com>
 * @package     paypal
 * @version     v0.5.8
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Paypal;

/**
 * Class for reports.
 * @package paypal
 */
class Report
{
    /** Property fields accessed via `__set()` and `__get()`.
    * @var array */
    protected $properties;

    /** Report shortname.
     * @var string */
    protected $key;


    /**
     * Initializes the report.
     */
    protected function __construct()
    {
    }


    /**
     * Render the report.
     */
    public function Render()
    {
        echo "Running {$this->key} report";
    }


    /**
     * Get the list of available reports for selection.
     *
     * @return  string  HTML for report listing
     */
    public static function getList()
    {
        global $LANG_PP;

        $retval = '';
        foreach ($LANG_PP['reports_avail'] as $key => $descrip) {
            $retval .= '<div><a href="' . PAYPAL_ADMIN_URL .
                '/index.php?configreport=' . $key . '">' . $descrip . '</a></div>';
        }
        return $retval;
    }


    /**
     * Get an instance of a report class.
     *
     * @param   string  $rpt_name   Report Name
     * @return  object|null     Instantiated class if available, Null if not
     */
    public static function getInstance($rpt_name)
    {
        $cls = __NAMESPACE__ . '\\Reports\\' . $rpt_name;
        if (class_exists($cls)) {
            return new $cls;
        } else {
            return NULL;
        }
    }

}   // class Report

?>
