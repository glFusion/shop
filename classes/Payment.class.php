<?php
/**
 * Payment class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Handle payment recording.
 * @package shop
 */
class Payment
{
    /** Transaction reference ID provided by the payment gateway.
     * @var string */
    private $ref_id;

    /** Timestamp for when the payment notification was received.
     * @var integer */
    private $ts;

    /** Gross amount of the payment.
     * @var float */
    private $amount;

    /** Payment Gateway ID.
     * @var string */
    private $gw_id;

    /** Order ID.
     * @var string */
    private $order_id;


    /**
     * Set internal variables from a data array.
     *
     * @param   array|null  $A  Optional data array
     */
    public function __construct($A=NULL)
    {
        if (is_array($A)) {
            $this->setRefID($A['pmt_ref_id']);
            $this->setAmount($A['pmt_amount']);
            $this->setTS($A['pmt_ts']);
            $this->setGateway($A['pmt_gateway']);
            $this->setOrderID($A['pmt_order_id']);
        }
    }


    /**
     * Get a payment record from the database by record ID.
     *
     * @param   integer $id     DB record ID
     * @return  object      Payment object
     */
    public static function getInstance($id)
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['shop.payments']}
            WHERE pmt_id = " . (int)$id;
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, true);
        } else {
            $A = NULL;
        }
        return new self($A);
    }


    /**
     * Accessor function to set the Reference ID.
     *
     * @param   string  $ref_id     Reference ID
     * @return  object  $this
     */
    public function setRefID($ref_id)
    {
        $this->ref_id = $ref_id;
        return $this;
    }


    /**
     * Accessor function to sset the Timestamp.
     *
     * @param   integer $ts     Timestamp
     * @return  object  $this
     */
    public function setTS($timestamp)
    {
        $this->ts = (int)$timestamp;
        return $this;
    }


    /**
     * Accessor function to set the Payment Amount.
     *
     * @param   float   $amount     Payment amount
     * @return  object  $this
     */
    public function setAmount($amount)
    {
        $this->amount = (float)$amount;
        return $this;
    }


    /**
     * Accessor function to set the Gateway ID.
     *
     * @param   string  $gw_id      Gateway ID
     * @return  object  $this
     */
    public function setGateway($gw_id)
    {
        $this->gw_id = $gw_id;
        return $this;
    }


    /**
     * Accessor function to set the Order ID.
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
     * Accessor function to get the Reference ID.
     *
     * @return  string      Reference ID
     */
    public function getRefID()
    {
        return $this->ref_id;
    }


    /**
     * Accessor function to get the Gateway ID.
     *
     * @return  string      Gateway ID
     */
    public function getGateway()
    {
        return $this->gw_id;
    }


    /**
     * Accessor function to get the Order ID.
     *
     * @return  string      Order ID
     */
    public function getOrderID()
    {
        return $this->order_id;
    }


    /**
     * Accessor function to get the Payment Amount.
     *
     * @return  float       Payment Amount.
     */
    public function getAmount()
    {
        return $this->amount;
    }


    /**
     * Accessor function to get the Timestamp.
     *
     * @return  integer     Timestamp value
     */
    public function getTS()
    {
        return $this->ts;
    }


    /**
     * Accessor function to get the Payment Date.
     *
     * @return  object      Date object
     */
    public function getDt()
    {
        global $_CONF;
        return new \Date($this->ts, $_CONF['timezone']);
    }


    /**
     * Save the payment object to the database.
     *
     * @return  integer     New DB record ID, 0 on error
     */
    public function Save()
    {
        global $_TABLES;

        $sql = "INSERT INTO {$_TABLES['shop.payments']} SET
            pmt_ts = UNIX_TIMESTAMP(),
            pmt_gateway = '" . DB_escapeString($this->getGateway()) . "',
            pmt_amount = '" . $this->getAmount() . "',
            pmt_ref_id = '" . DB_escapeString($this->getRefID()) . "',
            pmt_order_id = '" . DB_escapeString($this->getOrderID()) . "'";
        //echo $sql;die;
        $res = DB_query($sql);
        return DB_error() ? 0 : DB_insertID();
    }

}

?>
