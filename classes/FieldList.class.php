<?php
/**
 * Class to create fields for adminlists and other uses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * Class to create special list fields.
 * @package shop
 */
class FieldList extends \glFusion\FieldList
{
    /**
     * Return a cached template object to avoid repetitive path lookups.
     *
     * @return  object      Template object
     */
    protected static function init()
    {
        global $_CONF;

        static $t = NULL;

        if ($t === NULL) {
            $t = new \Template(SHOP_PI_PATH . '/templates');
            $t->set_file('field', 'fieldlist.thtml');
        } else {
            $t->unset_var('output');
            $t->unset_var('attributes');
        }
        return $t;
    }


    public static function buttonLink($args)
    {
        $def_args = array(
            'url' => '!#',
            'size' => '',   // mini
            'style' => 'default',  // success, danger, etc.
            'type' => '',   // submit, reset, etc.
            'class' => '',  // additional classes
        );
        $args = array_merge($def_args, $args);

        $t = self::init();
        $t->set_block('field','field-buttonlink');

        $t->set_var(array(
            'url' => $args['url'],
            'size' => $args['size'],
            'style' => $args['style'],
            'type' => $args['type'],
            'other_cls' => $args['class'],
            'text' => $args['text'],
        ) );

        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-button','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-buttonlink',true);
        return $t->finish($t->get_var('output'));
    }


    public static function text($args)
    {
        $t = self::init();
        $t->set_block('field','field-text');

        // Go through the required or special options
        $t->set_block('field', 'attr', 'attributes');
        foreach ($args as $name=>$value) {
            $t->set_var(array(
                'name' => $name,
                'value' => $value,
            ) );
            $t->parse('attributes', 'attr', true);
        }
        $t->parse('output', 'field-text');
        return $t->finish($t->get_var('output'));
    }

    public static function space() : string
    {
        $t = self::init();
        $t->set_block('field','field-space');
        $t->parse('output','field-space');
        return $t->finish($t->get_var('output'));
    }

    public static function print() : string
    {
        $t = self::init();
        $t->set_block('field','field-print');
        $t->parse('output','field-print');
        return $t->finish($t->get_var('output'));
    }

    public static function list() : string
    {
        $t = self::init();
        $t->set_block('field','field-list');
        $t->parse('output','field-list');
        return $t->finish($t->get_var('output'));
    }

    public static function left() : string
    {
        $t = self::init();
        $t->set_block('field','field-left');
        $t->parse('output','field-left');
        return $t->finish($t->get_var('output'));
    }

    public static function circle($args=array()) : string
    {
        $t = self::init();
        $t->set_block('field','field-circle');
        if (!isset($args['style'])) {
            $args['style'] = 'default';
        }
        $t->set_var('status', $args['style']);
        $t->parse('output','field-circle');
        return $t->finish($t->get_var('output'));
    }


    public static function icon($args=array()) : string
    {
        if (!isset($args['name'])) {
            return '';
        }
        $t = self::init();
        $t->set_block('field','field-icon');
        $t->set_var('icon_name', $args['name']);
        $t->parse('output','field-icon');
        return $t->finish($t->get_var('output'));
    }


    public static function add($args)
    {
        $t = self::init();
        $t->set_block('field','field-add');

        if (isset($args['url'])) {
            $t->set_var('url',$args['url']);
        } else {
            $t->set_var('url','#');
        }

        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-add','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-add',true);
        return $t->finish($t->get_var('output'));
    }

}
