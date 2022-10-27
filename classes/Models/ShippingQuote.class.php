<?php
/**
 * Class to handle shipping quote information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;


/**
 * Class for shipping rate quotes.
 * @package shop
 */
class ShippingQuote extends DataArray   //implements \ArrayAccess
{
    /** Shipping information properties.
     * @var array */
    protected $properties = array(
        'id'        => 0,
        'shipper_id' => 0,      // numeric DB record for shipping method
        'carrier_code' => '',   // class name for shipper, e.g. "ups"
        'carrier_title' => '',  // full name of the shipping company
        'svc_code'  => '',      // shipper code + service ID, e.g. "ups.01"
        'svc_id'    => '',      // service ID obtained from the shipper
        'svc_title' => '',      // service description, e.g. "Next Day Air"
        'cost'      => 0,       // total shipping cost for all packages
        'pkg_count' => 1,       // number of packages in shipment
        'error'     => false,   // flag if an error occurred retrieving info
    );


    /**
     * Set the shipping quote ID for reference.
     *
     * @param   string  $id     Quote ID
     * @return  object  $this
     */
    public function setID($id) : self
    {
        $this->properties['id'] = $id;
        return $this;
    }


    /**
     * Set the shipper record ID.
     *
     * @param   integer $id     DB record ID of shipping method
     * @return  object  $this
     */
    public function setShipperID(int $id) : self
    {
        $this->properties['shipper_id'] = (int)$id;
        return $this;
    }


    /**
     * Set the short carrier code (shipper object class name)
     *
     * @param   string  $code   Carrier code
     * @return  object  $this
     */
    public function setCarrierCode(string $code) : self
    {
        $this->properties['carrier_code'] = $code;
        return $this;
    }


    /**
     * Set the full name of the carrier.
     *
     * @param   string  $title  Name of the carrier
     * @return  object  $this
     */
    public function setCarrierTitle(string $title) : self
    {
        $this->properties['carrier_title'] = $title;
        return $this;
    }


    /**
     * Set the service code, e.g. "ups.03".
     *
     * @param   string  $code   Service code
     * @return  object  $this
     */
    public function setServiceCode(string $code) : self
    {
        $this->properties['svc_code'] = $code;
        return $this;
    }


    /**
     * Set the service ID received from the carrier.
     *
     * @param   string  $id     Service ID
     * @return  object  $this
     */
    public function setServiceID(string $id) : self
    {
        $this->properties['svc_id'] = $id;
        return $this;
    }


    /**
     * Set the descriptive title for the service.
     *
     * @param   string  $title  Service description
     * @return  object  $this
     */
    public function setServiceTitle(string $title) : self
    {
        $this->properties['svc_title'] = $title;
        return $this;
    }


    /**
     * Set the total shipping cost.
     *
     * @param   float   $cost   Total cost
     * @return  object  $this
     */
    public function setCost(float $cost) : self
    {
        $this->properties['cost'] = (float)$cost;
        return $this;
    }


    /**
     * Set the count of packages in the shipment.
     *
     * @param   integer $count  Number of packages
     * @return  object  $this
     */
    public function setPackageCount(int $count) : self
    {
        $this->properties['pkg_count'] = (int)$count;
        return $this;
    }


    /**
     * Sort a set of ShippingQuote objects by cost, ascending.
     *
     * @param   ShippingQuote   $a  First quote
     * @param   ShippingQuote   $b  Second quote
     * @return  float       Difference in cost
     */
    public static function sortByCost(ShippingQuote $a, ShippingQuote $b) : float
    {
        return $a['cost'] - $b['cost'];
    }

}
