<?php
/**
 * General Log class to save messages to the database.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Log messags to the database.
 * Includes accessor functions for common log variables.
 * @package shop
 */
class Logger
{
    /** Log Timestamp.
     * @var integer */
    private $ts;

    /** Log message.
     * @var string */
    private $msg;

    /** Order ID related to this message.
     * @var string */
    private $order_id;


    /**
     * Set the text message to log.
     *
     * @param   string  $msg    Message to log
     * @return  object  $this
     */
    public function setMsg($msg)
    {
        $this->msg = $msg;
        return $this;
    }


    /**
     * Set the Timestamp.
     * Used when reading a record, when saving the current time is used.
     *
     * @param   integer $ts     Timestamp to set
     * @return  object  $this
     */
    public function setTS($ts)
    {
        $this->ts = (int)$ts;
        return $this;
    }


    /**
     * Set the Order ID
     *
     * @param   string  $order_id   Order ID
     * @return  object  $this
     */
    public function setOrderID($order_id)
    {
        $this->order_id = $order_id;
        return $this;
    }

}

?>
