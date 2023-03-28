<?php
/**
 * Class to handle information about product variants.
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
 * Class for product view type.
 * @package shop
 */
class ProductVariantInfo extends DataArray
{
    /** Information properties.
     * @var array */
    private static $def_properties = array(
        'status'    => 0,
        'msg'       => '',
        'allowed'   =>  false,
        'orig_price' => 0,
        'sale_price' => 0,
        'onhand'    => 0,
        'weight'    => '--',
        'sku'       => '',
        'leadtime'  => '',
        'images'    => array(),
    );


    /**
     * Initialize the properties.
     */
    public function __construct()
    {
        $this->reset();
    }


    /**
     * Reset the properties.
     */
    public function reset() : void
    {
        global $LANG_SHOP;

        $this->properties = self::$def_properties;
        $this->properties['msg'] = $LANG_SHOP['opts_not_avail'];
    }

}
