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
     * Get the tax data for the current address.
     * Returns the "No Nexus" values if an entry is not found in the DB.
     *
     * @return  array   Decoded array of data from the JSON reply
     */
    protected function _getData()
    {
        global $_SHOP_CONF, $LANG_SHOP, $_TABLES;

        // Default data returned if there is no nexus, or a rate entry
        // is not found.
        $data = $this->default_rates;

        if ($this->hasNexus()) {
            $country = DB_escapeString($this->Address->getCountry());
            $zipcode = DB_escapeString($this->Address->getZip5());
            $sql = "SELECT * FROM {$_TABLES['shop.tax_rates']}
                WHERE country = '$country'
                AND (
                    zip_from = '$zipcode' OR
                    '$zipcode' BETWEEN zip_from AND zip_to
                ) ORDER BY zip_from DESC, zip_to ASC
                LIMIT 1";
            //echo $sql;die;
            $res = DB_query($sql, 1);
            if ($res) {
                $A = DB_fetchArray($res, false);
                if ($A) {           // Have to have found a record
                    $data = array(
                        'totalRate' => SHOP_getVar($A, 'combined_rate', 'float'),
                        'rates' => array(
                            array(
                                'rate'  => SHOP_getVar($A, 'state_rate', 'float'),
                                'name'  => $A['state'] . ' ' . $LANG_SHOP['state_rate'],
                                'type'  => 'State',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'county_rate', 'float'),
                                'name'  => $A['state'] . ' ' . $LANG_SHOP['county_rate'],
                                'type'  => 'County',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'city_rate', 'float'),
                                'name'  => $A['region'] . ' ' . $LANG_SHOP['city_rate'],
                                'type'  => 'City',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'special_rate', 'float'),
                                'name'  => $A['region'] . ' ' . $LANG_SHOP['special_rate'],
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
