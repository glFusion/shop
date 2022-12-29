<?php
namespace Shop;

class logIPN
{
    private $ip_addr;
    private $ts;
    private $verified;
    private $txn_id;
    private $gw_id;
    private $ipn_data;
    private $order_id;

    public function __construct()
    {
        $this->setIP($_SERVER['REMOTE_ADDR']);
        $this->verified = 0;
    }

    public function setIP($ip_addr)
    {
        $this->ip_addr = $ip_addr;
        return $this;
    }

    public function setTS($ts)
    {
        $this->ts = (int)$ts;
        return $this;
    }

    public function setVerified($verified)
    {
        $this->verified = $verified ? 1 : 0;
        return $this;
    }

    public function setTxnID($id)
    {
        $this->txn_id = $id;
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


    public function setData($data)
    {
        $this->ipn_data = $data;
        return $this;
    }

    public function Save()
    {
        global $_TABLES;

       // Log to database
        $sql = "INSERT INTO {$_TABLES['shop.ipnlog']} SET
                ip_addr = '" . DB_escapeString($this->ip_addr) . "',
                ts = UNIX_TIMESTAMP(),
                verified = '$this->verified',
                txn_id = '" . DB_escapeString($this->txn_id) . "',
                gateway = '{$this->gw_id}',
                order_id = '" . DB_escapeString($this->order_id) . "',
                ipn_data = '" . DB_escapeString(serialize($this->ipn_data)) . "'";
//echo $sql;die;
        // Ignore DB error in order to not block IPN
        DB_query($sql, 1);
        if (DB_error()) {
            Log::error("SQL error: $sql");
        }
        return DB_insertId();
    }


}
