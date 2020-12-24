<?php
/**
 * Class to handle company information from the configuration.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
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
    /** Company email address, site email by default.
     * @var string */
    private $email;


    /**
     * Load the company address values into the properties.
     *
     * @param   string|array    $data   Address data (not used)
     */
    public function __construct($data=array())
    {
        global $_CONF;

        // The store name may be set in the configuration but still be empty.
        // Use the site name if the store name is empty, site url as a fallback.
        if (empty(Config::get('company'))) {
            if (!empty($_CONF['site_name'])) {
                Config::set('company', $_CONF['site_name']);
            } else {
                Config::set(
                    'company',
                    preg_replace('/^https?\:\/\//i', '', $_CONF['site_url'])
                );
            }
        }

        // Same for the company email
        if (empty(Config::get('shop_email'))) {
            Config::set('shop_email', $_CONF['site_mail']);
        }

        // The data variable is disregarded, all values come from the config.
        $this
            ->setUid(0)         // not applicable
            ->setID(0)          // not applicable
            ->setBilltoDefault(0)   // not applicable
            ->setShiptoDefault(0)   // not applicable
            ->setCompany(Config::get('company'))
            ->setAddress1(Config::get('address1'))
            ->setAddress2(Config::get('address2'))
            ->setCity(Config::get('city'))
            ->setState(Config::get('state'))
            ->setPostal(Config::get('zip'))
            ->setCountry(Config::get('country'))
            ->setName(Config::get('remit_to'))
            ->setPhone(Config::get('shop_phone'))
            ->setEmail(Config::get('shop_email'));
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


    /**
     * Set the shop email address.
     *
     * @param   string  $email  Shop email address
     * @return  object  $this
     */
    private function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }


    /**
     * Get the shop email address.
     *
     * @return  string      Shop email address
     */
    public function getEmail()
    {
        return $this->email;
    }

}
