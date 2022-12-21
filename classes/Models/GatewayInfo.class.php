<?php
/**
 * Class to handle payment gateway configuration info.
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
 * Class for product view type.
 * @package shop
 */
class GatewayInfo extends DataArray
{
    /** Information properties.
     * @var array */
    protected $properties = array(
        'name' => '',
        'version' => 'unset',
        'repo' => array(
            'type' => 'github',
            'project_id' => '',
        ),
    );

}
