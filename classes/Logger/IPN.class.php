<?php
/**
 * IPN Log class to save IPN information.
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
namespace Shop\Logger;


/**
 * Log IPN messags to the database.
 * @package shop
 */
class IPN extends \Shop\Logger
{
    /** Source IP addres of IPN message.
     * @var string */
    private $ip_addr = '';

    /** Indicator that the message has been verified.
     * @var boolean */
    private $verified = 0;

    /** IPN or Webhook transaction ID.
     * @var string */
    private $txn_id = '';

    /** Gateway name.
     * @var string */
    private $gw_id = '';

    /** Raw IPN data array.
     * @var array */
    private $ipn_data = '';

    /** Type of message, typically "payment".
     * @var string */
    private $event = 'payment';

    /** Related order ID.
     * @var string */
    private $order_id = '';


    /**
     * Set any default values.
     */
    public function __construct()
    {
        $this->setIP($_SERVER['REMOTE_ADDR']);
        $this->verified = 0;
    }


    /**
     * Set the remote IP address.
     *
     * @param   string  $ip_addr    IP Address
     * @return  object  $this
     */
    public function setIP($ip_addr)
    {
        $this->ip_addr = $ip_addr;
        return $this;
    }


    /**
     * Set the verified status.
     *
     * @param   boolean $verified   Zero if not verified, Nonzero if verified
     * @return  object  $this
     */
    public function setVerified($verified)
    {
        $this->verified = $verified ? 1 : 0;
        return $this;
    }


    /**
     * Set the transactdion ID
     *
     * @param   string  $id     Transaction ID
     * @return  object  $this
     */
    public function setTxnID($id)
    {
        $this->txn_id = $id;
        return $this;
    }


    /**
     * Set the Gateway ID
     *
     * @param   string  $gw_id  Gateway ID (name)
     * @return  object  $this
     */
    public function setGateway($gw_id)
    {
        $this->gw_id = $gw_id;
        return $this;
    }


    /**
     * Set the message type.
     *
     * @param   string  $event  Type of IPN message
     * @return  object  $this
     */
    public function setEvent($event)
    {
        $this->event = substr($event, 0, 128);
        return $this;
    }


    /**
     * Set the raw IPN data array.
     *
     * @param   array   $data   Raw IPN data array
     * @return  object  $this
     */
    public function setData($data)
    {
        $this->ipn_data = $data;
        return $this;
    }


    /**
     * Set the order ID for the log record.
     *
     * @param   string  $order_id   Order ID
     * @return  object  $this
     */
    public function setOrderID($order_id)
    {
        $this->order_id = $order_id;
        return $this;
    }


    /**
     * Write the log entry
     *
     * @return  integer     New log record ID, 0 on error
     */
    public function Write()
    {
        global $_TABLES;

        if (!is_string($this->ipn_data)) {
            $data = @serialize($this->ipn_data);
        } else {
            $data = $this->ipn_data;
        }
        // Log to database
        $sql = "INSERT INTO {$_TABLES['shop.ipnlog']} SET
                ip_addr = '" . DB_escapeString($this->ip_addr) . "',
                ts = UNIX_TIMESTAMP(),
                verified = '$this->verified',
                txn_id = '" . DB_escapeString($this->txn_id) . "',
                gateway = '{$this->gw_id}',
                event = '" . DB_escapeString($this->event) . "',
                order_id = '" . DB_escapeString($this->order_id) . "',
                ipn_data = '" . DB_escapeString($data) . "'";
        // Ignore DB error in order to not block IPN
        DB_query($sql, 1);
        if (DB_error()) {
            SHOP_log(__CLASS__ . "::Write() SQL error: $sql", SHOP_LOG_ERROR);
            return 0;
        } else {
            return DB_insertId();
        }
    }

}

?>
