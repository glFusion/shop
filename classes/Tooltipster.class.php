<?php
/**
 * Class to centralize the creation of tooltip popups.
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
namespace Shop;


/**
 * Retrieve and cache sales tax information.
 * @package shop
 */
class Tooltipster
{

    public static function get($doc)
    {
        $doc_url = SHOP_getDocUrl($doc);
        $T = new Template;
        $T->set_file('tips', 'tooltipster.thtml');
        $T->set_var('doc_url', $doc_url);
        $T->parse('output', 'tips');
        return $T->finish($T->get_var('output'));
    }

}
