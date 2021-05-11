<?php
/**
 * Class to handle input field creation using templates.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.1
 * @since       v1.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Create input fields using templates.
 * @package shop
 */
class Field
{
    /**
     * Create a checkbox field.
     *
     * @param   array   $args   Array of argument names=>values
     * @return  string      HTML for checkbox field
     */
    public static function checkbox($args=array())
    {
        static $T = NULL;
        if ($T === NULL) {
            $T = new Template('fields');
            $T->set_file('field', 'checkbox.thtml');
        }

        // Go through the required or special options
        $T->set_block('field', 'Attr', 'attribs');
        foreach ($args as $name=>$value) {
            switch ($name) {
            case 'checked':
            case 'disabled':
                if ($value) {
                    $value = $name;
                } else {
                    continue 2;
                }
                break;
            }
            $T->set_var(array(
                'name' => $name,
                'value' => $value,
            ) );
            $T->parse('attribs', 'Attr', true);
        }
        $T->parse('output', 'field');
        $T->clear_var('attribs');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Create a text input field.
     *
     * @param   array   $args   Array of argument names=>values
     * @return  string      HTML for input field
     */
    public static function text($args=array())
    {
        // List all possible options
        $opts = array('size', 'name', 'value', 'placeholder', 'style');
        static $T = NULL;
        if ($T === NULL) {
            $T = new Template('fields');
            $T->set_file('field', 'text.thtml');
        }
        foreach ($opts as $optname) {
            if (isset($args[$optname])) {
                $T->set_var($optname, $args[$optname]);
            } else {
                $T->set_var($optname, false);
            }
        }
        $T->parse('output', 'field');
        return $T->finish($T->get_var('output'));
    }


    /**
     *  $opts = array(
     *      'name' => 'testoption',
     *      'onchange' => "alert('here');",
     *      'options' => array(
     *          'option1' => array(
     *              'disabled' => true,
     *              'value' => 'value1',
     *          ),
     *          'option2' => array(
     *              'selected' => 'something',
     *              'value' => 'value2',
     *          ),
     *          'option3' => array(
     *              'selected' => '',
     *              'value' => 'XXXXX',
     *          ),
     *      )
     *  );
     */
    public static function select($args=array())
    {
        if (!isset($args['options']) && !isset($args['option_list'])) {
            return '';
        }
        static $T = NULL;
        if ($T === NULL) {
            $T = new Template('fields');
            $T->set_file('field', 'select.thtml');
        }

        $def_opts = array(
            'value' => '',
            'selected' => false,
            'disabed' => false,
        );

        // Create the main selection element.
        $other = '';
        foreach ($args as $name=>$value) {
            if ($name == 'options') {
                continue;
            } elseif ($name == 'option_list') {
                $T->set_var('option_list', $value);
            } else {
                $other .= "$name=\"$value\" ";
            }
        }
        $T->set_var('sel_info', $other);
        // Now loop through the options.
        if (isset($args['options']) && is_array($args['options'])) {
            $T->set_block('field', 'optionRow', 'OR');
            foreach ($args['options'] as $name=>$data) {
                $T->set_var('opt_name', $name);
                // Go through the required or special options
                foreach ($def_opts as $optname=>$def_val) {
                    if (isset($data[$optname])) {
                        $T->set_var($optname, $data[$optname]);
                        unset($data[$optname]);
                    } else {
                        $T->set_var($optname, $def_val);
                    }
                }
                // Now go through the remaining supplied args for this option
                $str = '';
                foreach ($data as $name=>$value) {
                    $str .= "$name=\"$value\" ";
                }
                $T->set_var('other', $str);
                $T->parse('OR', 'optionRow', true);
            }
        }
        $T->parse('output', 'field');
        $T->clear_var('OR');
        return $T->finish($T->get_var('output'));
    }

}
