<?php
/**
 * Utility class to get values from URL parameters.
 * This should be instantiated via getInstance() to ensure consistency.
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
class PostGet extends DataArray
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


    public static function getInstance() : self
    {
        static $instance = NULL;
        if ($instance === NULL) {
            $instance = new self;
        }
        return $instance;
    }

}

