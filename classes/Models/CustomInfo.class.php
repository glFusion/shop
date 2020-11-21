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
class CustomInfo implements \ArrayAccess
{
    private $properties = array();

    public function __construct($val='')
    {
        if (is_string($val) && !empty($val)) {
            $x = json_decode(base64_decode($val), true);
            if ($x) {
                $this->properties = $x;
            }
        } elseif (is_array($val)) {
            $this->properties = $val;
        }
    }


    public function offsetSet($key, $value)
    {
        $this->properties[$key] = $value;
    }

    public function offsetExists($key)
    {
        return isset($this->properties[$key]);
    }

    public function offsetUnset($key)
    {
        unset($this->properties[$key]);
    }

    public function offsetGet($key)
    {
        return isset($this->properties[$key]) ? $this->properties[$key] : NULL;
    }

    public function isEmpty()
    {
        return empty($this->properties);
    }


    public function merge(array $arr)
    {
        $this->properties = array_merge($this->properties, $arr);
        return $this;
    }

    public function toArray()
    {
        return $this->properties;
    }

    public function serialize()
    {
        return serialize($this->properties);
    }

    public function unserialize($data)
    {
        $this->properties = @unserialize($data);
    }


    public function encode()
    {
        return base64_encode(json_encode($this->properties));
    }

    public function decode($data)
    {
        $this->properties = json_decode(base64_decode($data), true);
    }

    public function __toString()
    {
        return $this->encode();
    }
}
