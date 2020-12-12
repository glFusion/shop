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


    /**
     * Create a template object based on a language prefix.
     * Looks in template/$root/$lang for templates.
     * Adds english_utf-8.php if that is not the selected language.
     *
     * @param   string  $root   Template base directory
     * @param   string  $lang   Language string, user's language by default
     * @return  object      Shop\Template object
     */
    public static function getByLang($root = '', $lang = NULL)
    {
        global $_CONF;

        if ($lang === NULL) {
            $lang = COM_getLanguageName();
        } else {
            $charset = '_' . strtolower(COM_getCharset());
            if (substr($lang, -strlen($charset)) == $charset) {
                $lang = substr($lang, 0, -strlen($charset));
            }
        }

        if(!empty($root) && substr($root, -1) != '/') {
            $root .= '/';
        }
        $roots = array(
            $root . $lang,
        );
        // Add english as a failsafe if not already the selected language
        if ($lang != 'english') {
            $roots[] = $root . 'english';
        }
        $roots[] = $root;
        return new self($roots);
    }

}
