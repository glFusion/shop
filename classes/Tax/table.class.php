<?php
/**
 * Use the static databsae table to retrieve sales tax rates.
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
 * Get the sales tax rate from the DB tables.
 * @package shop
 */
class table extends \Shop\Tax
{

    /**
     * Get the sales tax rate.
     * The default function only returns the globally-configured rate.
     *
     * @return  float   Default configured tax rate.
     */
    public function getRate()
    {
        global $_SHOP_CONF;

        if ($this->haveNexus()) {
            return $this->_getData()['totalRate'];
        } else {
            return 0;
        }
    }


    /**
     * Get all the tax elements, e.g. State, County, City, etc.
     *
     * @return  array       Array of tax data
     */
    public function getRateBreakdown()
    {
        global $LANG_SHOP;

        $data = $this->_getData();
        return array(
            'country' => $this->Address->getCountry(),
            'totalRate' => $data['totalRate'],
            'freightTaxable' => 0,
            'rates' => $data['rates'],
            /*array(
                'rate'  => $data['totalRate'],
                'name'  => $data['rates'][0]['name'],
                'type'  => 'Total',
            ),*/
        );
    }


    /**
     * Get the tax data for the current address.
     *
     * @return  array   Decoded array of data from the JSON reply
     */
    private function _getData()
    {
        global $_SHOP_CONF, $LANG_SHOP, $_TABLES;

        if (!$this->haveNexus()) {
            $data = array(
                'totalRate' => 0,
                'rates' => array(
                    array(
                        'rate'  => 0,
                        'name'  => 'No Nexus',
                        'type'  => 'Total',
                    ),
                ),
            );
        } else {
            $sql = "SELECT * FROM {$_TABLES['shop.tax_rates']}
                WHERE country = '" . DB_escapeString($this->Address->getCountry() . "'
                AND zipcode = '" . DB_escapeString($this->Address->getZip5()) . "'";
            $res = DB_query($sql, 1);
            if ($res) {
                $A = DB_fetchArray($res, false);
                $data = array(
                    'totalRate' => $A['combined_rate'],
                    'rates' => array(
                        array(
                            'rate'  => $A['state_rate'],
                            'name'  => $A['state'] .' State',
                            'type'  => 'State',
                        ),
                        array(
                            'rate'  => $A['county_rate'],
                            'name'  => $A['state'] .' County',
                            'type'  => 'County',
                        ),
                        array(
                            'rate'  => $A['city_rate'],
                            'name'  => $A['region'] . ', ' . $A['state'] .' City',
                            'type'  => 'City',
                        ),
                        array(
                            'rate'  => $A['special_rate'],
                            'name'  => $A['region'] . ', ' . $A['state'] .' Special',
                            'type'  => 'Special',
                        ),
                    ),
                );
            }
        }
        return $data;
    }
}

?>
