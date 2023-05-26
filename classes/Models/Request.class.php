<?php
/**
 * Utility class to get values from URL parameters.
 * This should be instantiated via getInstance() to ensure consistency in case
 * parameters are "stuffed" into the parameter array.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022-2023 Lee Garner <lee@leegarner.com>
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
     * Get arguments from friendly URLs.
     * Expands urls like '/index.php/var1/var2/var3.... to an array of
     * name->value using the supplied names.
     *
     * If there are more arguments than names the remaining args are named `argX`.
     * If there are more names than args then the names are added with empty
     * values.
     *
     * @param   array   $names  Array of argument names.
     * @return  object  $this
     */
    public function withArgNames(array $names) : self
    {
        $args = array();
        if (isset ($_SERVER['PATH_INFO'])) {
            if ($_SERVER['PATH_INFO'] == '') {
                if (isset ($_ENV['ORIG_PATH_INFO'])) {
                    $arguments = explode('/', $_ENV['ORIG_PATH_INFO']);
                } else {
                    $arguments = array();
                }
            } else {
                $arguments = explode('/', $_SERVER['PATH_INFO']);
            }
            array_shift ($arguments);
            if (isset($arguments[0]) && $arguments[0] == substr($_SERVER['SCRIPT_NAME'], 1)) {
                array_shift($arguments);
            }
        } elseif (isset($_ENV['ORIG_PATH_INFO'])) {
            $scriptName = array();
            $scriptName = explode('/', $_SERVER['SCRIPT_NAME']);
            array_shift($scriptName);
            $arguments = explode('/', substr($_ENV['ORIG_PATH_INFO'], 1));
            if ($arguments[0] == $scriptName[0]) {
                $arguments = array();
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
            $arguments = explode('/', substr($_SERVER['ORIG_PATH_INFO'], 1));
            array_shift ($arguments);
            $script_name = strrchr($_SERVER['SCRIPT_NAME'], '/');
            if ($script_name == '') {
                $script_name = $_SERVER['SCRIPT_NAME'];
            }
            $indexArray = 0;
            $search_script_name = substr($script_name, 1);
            if (array_search($search_script_name, $arguments) !== false) {
                $indexArray = array_search($search_script_name,$arguments);
            }
            for ($x=0; $x < $indexArray; $x++) {
                array_shift($arguments);
            }
            if (isset($arguments[0]) && $arguments[0] == substr($script_name, 1)) {
                array_shift($arguments);
            }
        } else {
            $arguments = array ();
        }

        $namecount = count($names);
        $argcount = count($arguments);
        for ($i = 0; $i < $namecount && $i < $argcount; $i++) {
            $args[$names[$i]] = $arguments[$i];
        }
        if ($i >= $namecount) {     // got to the end of names, add more args
            for (; $i < $argcount; $i++) {
                $args['arg' . $i+1] = $arguments[$i];
            }
        } elseif ($i >= $argcount) {    // got to end of args, add empty names
            for (; $i < $namecount; $i++) {
                $args[$names[$i]] = '';
            }
        }

        if (!empty($args)) {
            $this->properties = array_merge($args, $this->properties);
        }
        return $this;
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


    /**
     * Builds crawler friendly URL if URL rewriting is enabled.
     *
     * This function will attempt to build a crawler friendly URL. If this feature is
     * disabled because of platform issue it just returns original $url value.
     *
     * https://site.com/index.php?a=1&b=2 becomes https://site.com/index.php/1/2
     *
     * @param    string  $url    URL to try and convert
     * @return   string      Rewritten if _isenabled is true otherwise original url
     */
    public static function buildUrl(string $url) : string
    {
        global $_CONF;

        // Do nothing if not configured to rewrite URLs
        if (!$_CONF['url_rewrite']) {
            return $url;
        }

        // Get the URL parts and parse any query string into an array
        $parts = parse_url($url);
        if (!isset($parts['query']) || empty($parts['query'])) {
            // No rewrite needed if no query string
            return $url;
        }
        parse_str($parts['query'], $qparts);
        $str = implode('/', $qparts);
        $retval = $parts['scheme']. '://' . $parts['host'] . $parts['path'] . '/' . $str;
        return COM_sanitizeURL($retval);
    }

}
