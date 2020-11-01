<?php
/**
 * Wrapper class to instantiate templates for the Shop plugin.
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
namespace Shop;

/**
 * Instantiate templates in the Shop plugin templates directory.
 * @package shop
 */
class Template extends \Template
{
    /**
     * Prepend the Shop template base directory to each requested path.
     *
     * @param   string|array    $root   Path under templates/
     * @param   string          $unknowns   What to do with unknown vars
     */
    public function __construct($root = '', $unknowns = "remove")
    {
        $pfx = SHOP_PI_PATH . '/templates/';
        if (is_array($root)) {
            foreach ($root as &$path) {
                $path = $pfx . $path;
            }
            //$root[] = $pfx;     // always include base dir
        } else {
            // also catches empty value for $root
            $root = $pfx . $root;
        }
        parent::__construct($root, $unknowns);
    }

}
