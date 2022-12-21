<?php
/**
 * Definition for a product button key, used with encrypted buy-now buttons.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;


/**
 * Define button cache keys.
 * @package shop
 */
class ButtonKey extends DataArray
{
    /** Information properties.
     * @var array */
    private $properties = array(
        'btn_type' => '',       // "buy_now", etc.
        'price' => 0,           // product price
    );

}
