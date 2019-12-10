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
        );
    }


    /**
     * Get the tax data for the current address.
     * Returns the "No Nexus" values if an entry is not found in the DB.
     *
     * @return  array   Decoded array of data from the JSON reply
     */
    private function _getData()
    {
        global $_SHOP_CONF, $LANG_SHOP, $_TABLES;

        // Default data returned if there is no nexus, or a rate entry
        // is not found.
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

        if ($this->haveNexus()) {
            $sql = "SELECT * FROM {$_TABLES['shop.tax_rates']}
                WHERE country = '" . DB_escapeString($this->Address->getCountry()) . "'
                AND zipcode = '" . DB_escapeString($this->Address->getZip5()) . "'";
            $res = DB_query($sql, 1);
            if ($res) {
                $A = DB_fetchArray($res, false);
                if ($A) {           // Have to have found a record
                    $data = array(
                        'totalRate' => SHOP_getVar($A, 'combined_rate', 'float'),
                        'rates' => array(
                            array(
                                'rate'  => SHOP_getVar($A, 'state_rate', 'float'),
                                'name'  => $A['state'] .' State',
                                'type'  => 'State',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'county_rate', 'float'),
                                'name'  => $A['state'] .' County',
                                'type'  => 'County',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'city_rate', 'float'),
                                'name'  => $A['region'] . ' City',
                                'type'  => 'City',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'special_rate', 'float'),
                                'name'  => $A['region'] . ' Special',
                                'type'  => 'Special',
                            ),
                        ),
                    );
                }
            }
        }
        return $data;
    }

}

?>
