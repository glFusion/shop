<?php

namespace Shop;

class Payment
{
    private $ref_id;
    private $ts;
    private $amount;
    private $gw_id;
    private $order_id;


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

        
    public function setRefID($ref_id)
    {
        $this->ref_id = $ref_id;
        return $this;
    }

    public function setTS($timestamp)
    {
        $this->ts = (int)$timestamp;
        return $this;
    }

    public function setAmount($amount)
    {
        $this->amount = (float)$amount;
        return $this;
    }

    public function setGateway($gw_id)
    {
        $this->gw_id = $gw_id;
        return $this;
    }

    public function setOrderID($order_id)
    {
        $this->order_id = $order_id;
        return $this;
    }

    public function getRefID()
    {
        return $this->ref_id;
    }

    public function getGateway()
    {
        return $this->gw_id;
    }

    public function getOrderID()
    {
        return $this->order_id;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getTS()
    {
        return $this->ts;
    }

    public function getDt()
    {
        global $_CONF;
        return new \Date($this->ts, $_CONF['timezone']);
    }

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
        return DB_error() ? false : true;
    }

}

?>
