<?php
/**
 * Utility class to get values from URL parameters.
 * This should be instantiated via getInstance() to ensure consistency in case
 * parameters are "stuffed" into the parameter array.
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
 * User Information class.
 * @package    shop
 */
class Request extends DataArray
{

    /**
     * Initialize the properties from a supplied string or array.
     * Use array_merge to preserve default properties by child classes.
     *
     * @param   string|array    $val    Optonal initial properties (ignored here)
     */
    public function __construct(?array $A=NULL)
    {
        $this->properties = array_merge($_GET, $_POST);
    }


    /**
     * Get the current request instance.
     *
     * @return  object  Request object
     */
    public static function getInstance() : self
    {
        static $instance = NULL;
        if ($instance === NULL) {
            $instance = new self;
        }
        return $instance;
    }


    /**
     * Determine if the current request is via AJAX.
     * Mimics COM_isAjax();
     *
     * @return  boolean     True if AJAX, False if not.
     */
    public function isAjax() : bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }


    /**
     * Get the action and action value from the parameters.
     *
     * @param   array   $expected   Array of expected options
     * @return  array       Array of (action, actionvalue)
     */
    public function getAction(array $expected, string $defaction='') : array
    {
        $action = $defaction;
        $actionval = '';
        foreach($expected as $provided) {
            if (isset($this[$provided])) {
                $action = $provided;
                $actionval = $this->getString($provided);
                break;
            }
        }
        return array($action, $actionval);
    }


    /**
     * Get the HTTP query string. Useful for debugging.
     *
     * @return  string      HTTP query string (a=x&b=7...)
     */
    public function asQueryString() : string
    {
        return http_build_query($this->properties);
    }

}

