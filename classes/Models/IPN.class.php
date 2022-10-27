<?php
/**
 * Class to handle IPN messsages for consistent data format.
 * This data is supplied to items during purchase handling and
 * may be sent to plugins.
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
 * Class for IPN messages for consistent data.
 * @package shop
 */
class IPN extends DataArray
{
    /** Information properties.
     * @var array */
    protected $properties = array(
        'sql_date' => '',       // SQL-formatted date string
        'uid' => 0,             // user ID to receive credit
        'pmt_gross' => 0,       // gross amount paid
        'txn_id' => '',         // transaction ID
        'gw_name' => '',        // gateway short name
        'memo' => '',           // misc. comment
        'first_name' => '',     // payer's first name
        'last_name' => '',      // payer's last name
        'payer_name' => '',     // payer's full name
        'payer_email' => '',    // payer's email address
        'custom' => array(  // backward compatibility for plugins
            'uid' => 0,
        ),
        'data' => array(),
        'reserved_stock' => false,
    );


    /**
     * Initialize the properties from a supplied string or array.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct(array $vals=array)
    {
        global $_CONF;

        if (empty($vals)) {
            // Make sure required fields are available.
            $vals['sql_date'] = $_CONF['_now']->toMySQL(true);
        }
        parent::__construct($vals);
    }


    /**
     * Set an element into the "custom" property array.
     *
     * @param   string  $key    Array key
     * @param   mixed   $value  Value to set
     * @return  object  $this
     */
    public function setCustom(string $key, $value) : self
    {
        $this->properties['custom'][$key] = $value;
        return $this;
    }


    /**
     * Set the buyer's user ID.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    public function setUid(int $uid) : self
    {
        $this->properties['uid'] = $uid;
        $this->properties['custom']['uid'] = $uid;
        return $this;
    }


    /**
     * Get an element from the IPN data, or the whole data array.
     *
     * @param   string  $key    Key to retrieve, empty string for all
     * @return  mixed       Data item, array of all data, or null
     */
    public function getData(?string $key=NULL)
    {
        if ($key === NULL) {
            return $this->properties['data'];
        } elseif (isset($this->properties['data'][$key])) {
            return $this->properties['data'][$key];
        } else {
            return NULL;
        }
    }

}
