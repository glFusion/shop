<?php
/**
 * Class to handle custom order information.
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
 * Class for product view type.
 * @package shop
 */
class ProductVariantInfo implements \ArrayAccess
{
    /** Information properties.
     * @var array */
    private static $def_properties = array(
        'status'    => 0,
        'msg'       => '',
        'allowed'   =>  false,
        'orig_price' => 0,
        'sale_price' => 0,
        'onhand'    => 0,
        'weight'    => '--',
        'sku'       => '',
        'leadtime'  => '',
        'images'    => array(),
    );

    private $properties = array();

    /**
     * Initialize the properties from a supplied string or array.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct()
    {
        $this->reset();
    }


    public function reset()
    {
        global $LANG_SHOP;

        $this->properties = self::$def_properties;
        $this->properties['msg'] = $LANG_SHOP['opts_not_avail'];
    }


    /**
     * Set a property when accessing as an array.
     *
     * @param   string  $key    Property name
     * @param   mixed   $value  Property value
     */
    public function offsetSet($key, $value)
    {
        $this->properties[$key] = $value;
    }


    /**
     * Check if a property is set when calling `isset($this)`.
     *
     * @param   string  $key    Property name
     * @return  boolean     True if property exists, False if not
     */
    public function offsetExists($key)
    {
        return isset($this->properties[$key]);
    }


    /**
     * Remove a property when using unset().
     *
     * @param   string  $key    Property name
     */
    public function offsetUnset($key)
    {
        unset($this->properties[$key]);
    }


    /**
     * Get a property when referencing the class as an array.
     *
     * @param   string  $key    Property name
     * @return  mixed       Property value, NULL if not set
     */
    public function offsetGet($key)
    {
        return isset($this->properties[$key]) ? $this->properties[$key] : NULL;
    }


    /**
     * Get the internal properties as a native array.
     *
     * @return  array   $this->properties
     */
    public function toArray()
    {
        return $this->properties;
    }


    /**
     * Return the string representation of the class.
     *
     * @return  string      Base64-encoded json string
     */
    public function __toString()
    {
        return json_encode($this->properties);
    }

}
