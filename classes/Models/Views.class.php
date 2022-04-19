<?php
/**
 * Class to manage product views such as detail page, block, list.
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
namespace Shop\Models;


/**
 * Class for product view type.
 * @package shop
 */
class Views
{
    /** List page, such as product catalog.
     */
    public const LIST = 1;

    /** Product detail page.
     */
    public const DETAIL = 2;

    /** In a block such as random or popular products.
     */
    public const BLOCK = 4;
}

?>
