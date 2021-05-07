<?php
namespace Shop;

class Field
{
    public static function checkbox($args=array())
    {
        $opts = array('selected', 'disabled');
        static $T = NULL;
        if ($T === NULL) {
            $T = new Template('fields');
            $T->set_file('field', 'checkbox.thtml');
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

}
