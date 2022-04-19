<?php
/**
 * Data structure class for the payout header.
 * One header is submitted along with any number of actual payouts.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;


/**
 * Standard data structure for payouts.
 * @package shop
 */
class PayoutHeader implements \ArrayAccess
{
    /** Information properties.
     * @var array */
    private $properties = array(
        'email_subject' => 'Your payment is ready',
        'email_message' => 'A payout has been sent to you.',
    );


    /** Initialize the properties from a supplied string or array.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct($val='')
    {
        if (is_string($val) && !empty($val)) {
            $x = json_decode($val, true);
            if ($x) {
                $this->properties = $x;
            }
        } elseif (is_array($val)) {
            foreach ($val as $key=>$value) {
                $this->properties[$key] = $value;
            }
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
