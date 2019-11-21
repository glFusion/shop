<?php
namespace Shop;

class Webhook
{
    protected $whData = array();    // raw webhook data
    protected $whID;            // webhook identifier
    protected $whSource;        // webhook source, e.g. gateway name
    protected $whEvent;         // event type
    protected $whOrderID;       // Order ID
    protected $whPmtTotal;      // Total Payment Amount

    protected function saveToDB()
    {
        global $_TABLES;

        $sql ='';
    }

    public function setID($whID)
    {
        $this->whID = $whID;
        return $this;
    }

    public function getID()
    {
        return $this->whID;
    }

    public function setSource($source)
    {
        $this->whSource = $source;
        return $this;
    }

    public function getSource()
    {
        return $this->whSource;
    }

    public function setData($data)
    {
        $this->whData = $data;
        return $this;
    }

    public function getData()
    {
        return $this->whData;
    }


    public function setTimestamp($ts=NULL)
    {
        if ($ts === NULL) {
            $ts = time();
        }
        $this->whTS = $ts;
    }

    public function getTimestamp()
    {
        return $this->whTS;
    }

    public function setEvent($event)
    {
        $this->whEvent = $event;
        return $this;
    }

    public function getEvent()
    {
        return $this->whEvent;
    }

    public function setOrderID($order_id)
    {
        $this->whOrderID = $order_id;
        return $this;
    }

    public function getOrderID()
    {
        return $this->whOrderID;
    }

    public function setPayment($amount)
    {
        $this->whPmtTotal = (float)$amount;
        return $this;
    }

    public function getPayment()
    {
        return $this->whPmtTotal;
    }


    public function recordPayment()
    {

        $Pmt = new Payment();
        $Pmt->setRefID($this->whID)
            ->setGateway($this->whSource)
            ->setAmount($this->getPayment())
            ->setOrderID($this->whOrderID);
        return $Pmt->Save();
    }

}

?>
