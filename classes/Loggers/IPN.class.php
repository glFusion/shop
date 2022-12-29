<?php
/**
 * IPN Log class to save IPN information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.6.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Loggers;
use Shop\Log;
use glFusion\Database\Database;


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

    /** Reference ID, e.g. payment ID.
     * @var string */
    private $ref_id = '';

    /** Gateway name.
     * @var string */
    private $gw_id = '';

    /** Raw IPN data array.
     * @var array */
    private $ipn_data = '';

    /** Type of message, typically "payment".
     * @var string */
    private $event = 'payment';

    /** Event timestamp, default will be current Unix time.
     * @var integer */
    private $ts = 0;

    /** Related order ID.
     * @var string */
    private $order_id = '';

    /** Processing status message.
     * @var string */
    private $status_msg = '';


    /**
     * Set any default values.
     */
    public function __construct()
    {
        $this->setIP($_SERVER['REMOTE_ADDR']);
        $this->verified = 0;
        $this->ts = time();
    }


    /**
     * Set the remote IP address.
     *
     * @param   string  $ip_addr    IP Address
     * @return  object  $this
     */
    public function setIP(string $ip_addr) : self
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
    public function setVerified(bool $verified) : self
    {
        $this->verified = $verified ? 1 : 0;
        return $this;
    }


    /**
     * Set the webhook ID.
     *
     * @param   string  $id     Transaction ID
     * @return  object  $this
     */
    public function setTxnID(string $id) : self
    {
        $this->txn_id = $id;
        return $this;
    }


    /**
     * Set the transaction reference ID.
     *
     * @param   string  $id     Transaction ID
     * @return  object  $this
     */
    public function setRefID(string $id) : self
    {
        $this->ref_id = $id;
        return $this;
    }


    /**
     * Set the Gateway ID
     *
     * @param   string  $gw_id  Gateway ID (name)
     * @return  object  $this
     */
    public function setGateway(string $gw_id) : self
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
    public function setEvent(string $event) : self
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
    public function setData(array $data) : self
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
    public function setOrderID(string $order_id) : self
    {
        $this->order_id = $order_id;
        return $this;
    }


    /**
     * Override the event timestamp to get the actual creation time.
     *
     * @param   integer $ts     Event timestamp
     * @return  object  $this
     */
    public function setTimestamp(int $ts) : self
    {
        $this->ts = $ts;
        return $this;
    }


    /**
     * Set the processing status message.
     *
     * @param   string  $msg    Message to record
     * @return  object  $this
     */
    public function setStatusMsg(string $msg) : self
    {
        $this->status_msg = $msg;
        return $this;
    }


    /**
     * Write the log entry
     *
     * @return  integer     New log record ID, 0 on error
     */
    public function Write() : int
    {
        global $_TABLES;

        if (!is_string($this->ipn_data)) {
            $data = @json_encode($this->ipn_data);
        } else {
            $data = $this->ipn_data;
        }

        // Log to database
        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['shop.ipnlog'],
                array(
                    'ip_addr' => $this->ip_addr,
                    'ts' => time(),
                    'verified' => $this->verified,
                    'txn_id' => $this->txn_id,
                    'ref_id' => $this->ref_id,
                    'gateway' => $this->gw_id,
                    'event' => $this->event,
                    'order_id' => $this->order_id,
                    'ipn_data' => $data,
                    'status_msg' => $this->status_msg,
                ),
                array(
                    Database::STRING,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                )
            );
            return $db->conn->lastInsertId();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage());
            return 0;
        }
    }

}
