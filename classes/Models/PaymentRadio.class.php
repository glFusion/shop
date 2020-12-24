<?php
/**
 * Class to create payment selection radio or checkbox buttons.
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
class PaymentRadio implements \ArrayAccess
{
    /** Shipping information properties.
     * @var array */
    private $properties = array(
        'gw_name' => '',
        'type' => 'radio',
        'varname' => 'gateway',
        'value' => '',
        'selected' => '',
        'dscp' => '',
        'logo' => '',
    );


    /**
     * Create a new shipping quote, optionally with information included.
     */
    public function __construct($val='')
    {
        if (is_string($val) && !empty($val)) {
            $x = json_decode($val, true);
            if ($x) {
                $this->setVars($x);
            }
        } elseif (is_array($val)) {
            $this->setVars($val);
        }
    }


    /**
     * Set a property value.
     *
     * @param   string  $key    Key name
     * @param   mixed   $value  Value to set
     */
    public function offsetSet($key, $value)
    {
        $this->properties[$key] = $value;
    }


    /**
     * Check if a key exists.
     *
     * @param   mixed   $key    Key name
     * @return  boolean     True if the key exists.
     */
    public function offsetExists($key)
    {
        return isset($this->properties[$key]);
    }


    /**
     * Remove a property.
     *
     * @param   mixed   $key    Key name
     */
    public function offsetUnset($key)
    {
        unset($this->properties[$key]);
    }


    /**
     * Get a value from the properties.
     *
     * @param   mixed   $key    Key name
     * @return  mixed       Value of property, NULL if not set
     */
    public function offsetGet($key)
    {
        return isset($this->properties[$key]) ? $this->properties[$key] : NULL;
    }


    /**
     * Get the shipping quote properties as an array.
     *
     * @return  array       Properties array
     */
    public function toArray()
    {
        return $this->properties;
    }

}
