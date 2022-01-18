<?php
/**
 * Use the internally-configured sales tax rate.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Tax;
use Shop\Config;


/**
 * Get internal sales tax rate.
 * This class simply returns the tax rate configured for the plugin.
 * No address or cache is used.
 * @package shop
 */
class internal extends \Shop\Tax
{

    /**
     * Get the sales tax data for the internally-configured rate.
     * The default function only returns the globally-configured rate.
     *
     * @return  array   Array of data (just total rate)
     */
    protected function _getData()
    {
        if ($this->hasNexus()) {
            $rate = (float)Config::get('tax_rate');
        } else {
            $rate = 0;
        }
        return array(
            'totalRate' => $rate,
        );
    }


    /**
     * Get all the tax elements, e.g. State, County, City, etc.
     *
     * @return  array       Array of tax data
     */
    public function getRateBreakdown()
    {
        global $LANG_SHOP;

        return array(
            'country' => $this->Address->getCountry(),
            'totalRate' => $this->getRate(),
            'freightTaxable' => 0,
            'rates' => array(
                'rate'  => $this->getRate(),
                'name'  => $LANG_SHOP['sales_tax'],
                'type'  => 'Total',
            ),
        );
    }

}

