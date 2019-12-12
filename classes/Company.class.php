<?php
/**
 * Class to handle company information from the configuration.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to handle company address formatting.
 * @package shop
 */
class Company extends Address
{
    /**
     * Load the company address values into the properties.
     *
     * @param   string|array    $data   Address data (not used)
     */
    public function __construct($data=array())
    {
        global $_SHOP_CONF;

        // The data variable is disregarded, all values come from the config.
        $this
            ->setUid(0)         // not applicable
            ->setID(0)          // not applicable
            ->setBilltoDefault(0)   // not applicable
            ->setShiptoDefault(0)   // not applicable
            ->setCompany(SHOP_getVar($_SHOP_CONF, 'company'))
            ->setAddress1(SHOP_getVar($_SHOP_CONF, 'address1'))
            ->setAddress2(SHOP_getVar($_SHOP_CONF, 'address2'))
            ->setCity(SHOP_getVar($_SHOP_CONF, 'city'))
            ->setState(SHOP_getVar($_SHOP_CONF, 'state'))
            ->setPostal(SHOP_getVar($_SHOP_CONF, 'zip'))
            ->setCountry(SHOP_getVar($_SHOP_CONF, 'country'))
            ->setName(SHOP_getVar($_SHOP_CONF, 'remit_to'));
    }


    /**
     * Get an instance of the Company object.
     *
     * @param   integer $addr_id    Address ID to retrieve (not used)
     * @return  object      Company Address object
     */
    public static function getInstance($addr_id=NULL)
    {
        static $Obj = NULL;

        if ($Obj === NULL) {
            $Obj = new self;
        }
        return $Obj;
    }

}

?>
