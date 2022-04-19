<?php
/**
 * Definition for a product button key, used with encrypted buy-now buttons.
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
 * Define button cache keys.
 * @package shop
 */
class ButtonKey implements \ArrayAccess
{
    /** Information properties.
     * @var array */
    private $properties = array(
        'btn_type' => '',       // "buy_now", etc.
        'price' => 0,           // product price
    );


    /**
     * Construct a key from a supplied array.
     *
     * @param   array   $A  Array of elements
     */
    public function __construct($A=array())
    {
        if (!empty($A)) {
            $this->properties['btn_type'] = SHOP_getVar($A, 'btn_type');
            $this->properties['price'] = SHOP_getVar($A, 'price', 'float', 0);
        }
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
     * @return  string      JSON string
     */
    public function __toString()
    {
        return implode('.', $this->properties);
    }

}
