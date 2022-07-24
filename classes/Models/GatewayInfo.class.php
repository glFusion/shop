<?php
/**
 * Class to handle payment gateway configuration info.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;


/**
 * Class for product view type.
 * @package shop
 */
class GatewayInfo implements \ArrayAccess
{
    /** Information properties.
     * @var array */
    private $properties = array(
        'name' => '',
        'version' => 'unset',
        'repo' => array(
            'type' => 'github',
            'project_id' => '',
        ),
    );


    /**
     * Initialize the properties from a supplied string or array.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct($val='')
    {
        if (is_string($val) && !empty($val)) {
            $x = @json_decode($val, true);
            if ($x) {
                $val = $x;
            }
        }
        if (is_array($val)) {
            $this->properties = array_merge($val);
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
     * Check if the properties array is empty.
     *
     * @return  boolean     True if empty, False if not
     */
    public function isEmpty()
    {
        return empty($this->properties);
    }


    /**
     * Merge the supplied array into the internal properties.
     *
     * @param   array   $arr    Array of data to add
     * @return  object  $this
     */
    public function merge(array $arr)
    {
        $this->properties = array_merge($this->properties, $arr);
        return $this;
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
     * Encode the internal properties to a Base64-encode JSON string.
     *
     * @return  string      Encoded string containing the properties
     */
    public function encode()
    {
        return base64_encode(json_encode($this->properties));
    }


    /**
     * Sets the internal properties by decoding the supplied string.
     *
     * @param   string  $data   Base64-encoded JSON string
     * @return  array       Properties array
     */
    public function decode($data)
    {
        $this->properties = json_decode(base64_decode($data), true);
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
