<?php
/**
 * Webhook class for the Shop plugin.
 * Base class for webhooks, each webhook provider (gateway) will have its
 * own class based on this one.
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
use Shop\Logger\IPN as logIPN;

/**
 * Base webhook class.
 * @package shop
 */
class Webhook
{
    /** Standard event type for invoice payment.
     * @const string */
    const EV_PAYMENT = 'invoice_paid';

    /** Standard event type for unidentified events.
     * @const string */
    const EV_UNDEFINED = 'undefined';

    /** Raw webhook data.
     * @var array */
    protected $whData = array();

    /** Webhook Reference ID.
     * @var string */
    protected $whID;

    /** Webhook source, eg. gateway name.
     * @var string */
    protected $whSource;

    /** Event type, e.g. "payment received".
     * @var string */
    protected $whEvent;

    /** Order ID related to this notification.
     * @var string */
    protected $whOrderID;

    /** Total Payment Amount.
     * @var float */
    protected $whPmtTotal;

    /** Status of webhook verification via callback.
     * @var boolean */
    protected $whVerified;


    /**
     * Save the webhook to the database.
     */
    protected function saveToDB()
    {
        global $_TABLES;

        $sql ='';
    }


    /**
     * Set the webhook ID variable.
     *
     * @param   string  $whID   Webhook ID
     * @return  object  $this
     */
    public function setID($whID)
    {
        $this->whID = $whID;
        return $this;
    }


    /**
     * Get the webhook ID variable.
     *
     * @return  string      Webhook ID
     */
    public function getID()
    {
        return $this->whID;
    }


    /**
     * Set the webhook source.
     *
     * @param   string  $source     Webhook source (gateway name)
     * @return  object  $this
     */
    public function setSource($source)
    {
        $this->whSource = $source;
        return $this;
    }


    /**
     * Get the webhook source.
     *
     * @return  string      Webhook source (gateway name)
     */
    public function getSource()
    {
        return $this->whSource;
    }


    /**
     * Set the webhook data.
     *
     * @param   array   $data   Raw webhook data array
     * @return  object  $this
     */
    public function setData($data)
    {
        $this->whData = $data;
        return $this;
    }


    /**
     * Get the raw webhook data.
     *
     * @return  array   Webhook data
     */
    public function getData()
    {
        return $this->whData;
    }


    /**
     * Set the webhook timestamp.
     *
     * @param   integer $ts     Timestamp value
     * @return  object  $this
     */
    public function setTimestamp($ts=NULL)
    {
        if ($ts === NULL) {
            $ts = time();
        }
        $this->whTS = $ts;
    }


    /**
     * Get the webhook timestamp.
     *
     * @return  integer     Timestamp value
     */
    public function getTimestamp()
    {
        return $this->whTS;
    }


    /**
     * Set the webhook event type.
     *
     * @param   string  $event      Webhook event type
     * @return  object  $this
     */
    public function setEvent($event)
    {
        $this->whEvent = $event;
        return $this;
    }


    /**
     * Get the webhook event type.
     *
     * @return  string      Event string
     */
    public function getEvent()
    {
        return $this->whEvent;
    }


    /**
     * Set the order ID related to this webhook.
     *
     * @param   string  $order_id   Order ID
     * @return  object  $this
     */
    public function setOrderID($order_id)
    {
        $this->whOrderID = $order_id;
        return $this;
    }


    /**
     * Get the order ID for this webhook.
     *
     * @return  string      Order ID
     */
    public function getOrderID()
    {
        return $this->whOrderID;
    }


    /**
     * Set the payment amount for this webhook.
     *
     * @param   float   $amount     Payment amount
     * @return  object  $this
     */
    public function setPayment($amount)
    {
        $this->whPmtTotal = (float)$amount;
        return $this;
    }


    /**
     * Get the payment amount for this webhook.
     *
     * @return  float       Payment amount
     */
    public function getPayment()
    {
        return $this->whPmtTotal;
    }


    /**
     * Check if this webhook has been verified.
     *
     * @return  integer     1 if verified, 0 if not
     */
    public function isVerified()
    {
        return $this->whVerified ? 1 : 0;
    }


    /**
     * Record a payment via webhook in the database.
     * Creates a Payment object from the webhook data and saves it.
     *
     * @return  integer     Record ID returned from Payment::Save()
     */
    public function recordPayment()
    {

        $Pmt = new Payment();
        $Pmt->setRefID($this->whID)
            ->setGateway($this->whSource)
            ->setAmount($this->getPayment())
            ->setOrderID($this->whOrderID);
        return $Pmt->Save();
    }


    /**
     * Log the webhook to the IPN log.
     *
     * @return  integer     Record ID returned from Logger\IPN::Write()
     */
    public function logIPN()
    {
        $ipn = new logIPN();
        $ipn->setOrderID($this->whOrderID)
            ->setTxnID($this->whID)
            ->setGateway($this->whSource)
            ->setVerified($this->isVerified())
            ->setData($this->whData);
        return $ipn->Write();
    }

}

?>
