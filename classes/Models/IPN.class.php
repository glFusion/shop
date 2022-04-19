<?php
/**
 * Class to handle IPN messsages for consistent data format.
 * This data is supplied to items during purchase handling and
 * may be sent to plugins.
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
 * Class for IPN messages for consistent data.
 * @package shop
 */
class IPN implements \ArrayAccess
{
    /** Information properties.
     * @var array */
    private $properties = array(
        'sql_date' => '',       // SQL-formatted date string
        'uid' => 0,             // user ID to receive credit
        'pmt_gross' => 0,       // gross amount paid
        'txn_id' => '',         // transaction ID
        'gw_name' => '',        // gateway short name
        'memo' => '',           // misc. comment
        'first_name' => '',     // payer's first name
        'last_name' => '',      // payer's last name
        'payer_name' => '',     // payer's full name
        'payer_email' => '',    // payer's email address
        'custom' => array(  // backward compatibility for plugins
            'uid' => 0,
        ),
        'data' => array(),
        'reserved_stock' => false,
    );


    /**
     * Initialize the properties from a supplied string or array.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct($val='')
    {
        global $_CONF;

        if (is_string($val) && !empty($val)) {
            $x = json_decode($val, true);
            if ($x) {
                $this->properties = $x;
            }
        } elseif (is_array($val)) {
            $this->properties = $val;
        } else {
            // Make sure required fields are available.
            $this->sql_date = $_CONF['_now']->toMySQL(true);
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
     * Return the string representation of the class.
     *
     * @return  string      JSON string
     */
    public function __toString()
    {
        return json_encode($this->properties);
    }


    public function setCustom($key, $value)
    {
        $this->properties['custom'][$key] = $value;
        return $this;
    }


    public function setUid($uid)
    {
        $this->properties['uid'] = (int)$uid;
        $this->properties['custom']['uid'] = (int)$uid;
        return $this;
    }


    public function getData($key=NULL)
    {
        if ($key === NULL) {
            return $this->properties['data'];
        } elseif (isset($this->properties['data'][$key])) {
            return $this->properties['data'][$key];
        } else {
            return NULL;
        }
    }

}
