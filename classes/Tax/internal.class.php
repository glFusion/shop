<?php
/**
 * Get tax rates from ServiceObjects' FastTax API.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Tax;


/**
 * Get internal sales tax rate.
 * This class simply returns the tax rate configured for the plugin.
 * No address or cache is used.
 * @package shop
 */
class internal extends \Shop\Tax
{
    /**
     * Included for compatibility but not used by the Internal tax rate class.
     *
     * @param   object  $Address    Address to look up
     */
    public function __construct($Address=NULL)
    {
    }

}

?>
