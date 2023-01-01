<?php
/**
 * Class to model customer payment gateway informatiln.
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
 * Class for affiliate payouts.
 * @package shop
 */
class CustomerGateway extends DataArray
{
    /** Information properties.
     * @var array */
    protected $properties = array(
        'uid' => 1,
        'email' => '',
        'gw_id' => '',
        'cust_id' => '',
    );


    public function __toString() : string
    {
        return $this->properties['cust_id'];
    }
}
