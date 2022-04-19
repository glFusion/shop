<?php
/**
 * Class to handle sessions.
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
 * Class for token creation.
 * @package shop
 */
class Session
{
    /** Session variable name for storing cart info.
     * @var string */
    private static $session_var = 'glShop';


    /**
     * Add a session variable.
     *
     * @param   string  $key    Name of variable
     * @param   mixed   $value  Value to set
     */
    public static function set($key, $value)
    {
        if (!isset($_SESSION[self::$session_var])) {
            $_SESSION[self::$session_var] = array();
        }
        $_SESSION[self::$session_var][$key] = $value;
    }


    /**
     * Retrieve a session variable.
     *
     * @param   string  $key    Name of variable
     * @return  mixed       Variable value, or NULL if it is not set
     */
    public static function get($key)
    {
        if (isset($_SESSION[self::$session_var][$key])) {
            return $_SESSION[self::$session_var][$key];
        } else {
            return NULL;
        }
    }


    /**
     * Remove a session variable.
     *
     * @param   string  $key    Name of variable, null to clear all
     */
    public static function clear($key=NULL)
    {
        if ($key === NULL) {
            unset($_SESSION[self::$session_var]);
        } else {
            unset($_SESSION[self::$session_var][$key]);
        }
    }

}
