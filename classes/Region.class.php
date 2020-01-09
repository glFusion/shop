<?php
/**
 * Class to handle Regions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to handle country information.
 * @package shop
 */
class Region
{
    /** Region DB record ID.
     * @var integer */
    private $region_id;

    /** Region Name.
     * @var string */
    private $region_name;

    /** Sales are allowed to this region?
     * @var integer */
    private $region_enabled;


    /**
     * Create an object and set the variables.
     *
     * 
     */
    public function __construct($A)
    {
        $this->setID($A['region_id'])
            ->setName($A['region_name'])
            ->setEnabled($A['region_enabled']);
    }


    /**
     * Get an instance of a country object.
     *
     * @param   string  $code   2-letter country code
     * @return  object  Country object
     */
    public static function getInstance($code)
    {
        global $_TABLES;
        static $instances = array();

        if (isset($instances[$code])) {
            return $instances[$code];
        } else {
            $sql = "SELECT * FROM gl_shop_regions WHERE region_id = " . (int)$code;
            $res = DB_query($sql);
            if ($res && DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
            } else {
                $A = array(
                    'region_id'     => 0,
                    'region_name'  => '',
                    'region_enabled' => 0,
                );
            }
            return new self($A);
        }
    }


    /**
     * Set the record ID.
     * 
     * @param   integer $id     DB record ID
     * @return  object  $this
     */
    private function setID($id)
    {
        $this->region_id = (int)$id;
        return $this;
    }


    /**
     * Return the DB record ID for the country.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->region_id;
    }


    /**
     * Set the Region record ID.
     * 
     * @param   integer $id     DB record ID for the region
     * @return  object  $this
     */
    private function setEnabled($enabled)
    {
        $this->region_enabled = $enabled == 0 ? 0 : 1;
        return $this;
    }


    public function isEnabled()
    {
        return (int)$this->region_enabled;
    }


    /**
     * Set the Country Name.
     * 
     * @param   string  $name   Name of country
     * @return  object  $this
     */
    private function setName($name)
    {
        $this->region_name = $name;
        return $this;
    }


    /**
     * Return USPS country name by country ISO 3166-1-alpha-2 code.
     * Return empty string for unknown countries.
     *
     * @return  string      Country name, empty string if not found
     */
    public function getName()
    {
        return $this->data['region_name'];
    }

}

?>
