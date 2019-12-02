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
     * Get the sales tax rate.
     * The default function only returns the globally-configured rate.
     *
     * @return  float   Default configured tax rate.
     */
    public function getTaxRate()
    {
        global $_SHOP_CONF;
        return SHOP_getVar($_SHOP_CONF, 'tax_rate', 'float');
    }


    /**
     * Get all the tax elements, e.g. State, County, City, etc.
     *
     * @return  array       Array of tax data
     */
    public function getTaxBreakdown()
    {
        global $LANG_SHOP;

        return array(
            array(
                'rate'  => $this->getTaxRate(),
                'name'  => $LANG_SHOP['sales_tax'],
                'type'  => 'Total',
            ),
        );
    }

}

?>
