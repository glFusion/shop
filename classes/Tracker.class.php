<?php

namespace Shop;

class Tracker
{
    public static function getInstance()
    {
        global $_SHOP_CONF;

        static $T = NULL;
        if ($T === NULL) {
            $tracker = ucfirst($_SHOP_CONF['tracker']);
            $cls = '\\Shop\\Trackers\\' . $tracker;
            if (class_exists($cls)) {
                $T = new $cls;
            } else {
                // use stub functions to avoid errors.
                $T = new self;
            }
        }
        return $T;
    }


    public function getCode()
    {
        return '';
    }


    public static function makeCid($session_id=NULL)
    {
        if ($session_id === NULL) {
            $session_id = session_id();
        }
        return substr(md5($session_id), 0, 16);
    }


    public function addProductView($P)
    {
        return $this;
    }


    public function addCartItem($Item)
    {
        return $this;
    }


    public function addCartView($Ord)
    {
        return $this;
    }


    public function delCart($sku)
    {
        return $this;
    }


    public function clearCart()
    {
        return $this;
    }

    public function confirmOrder($Ord, $session_id)
    {
        return true;
    }

    protected function _curlExec($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            SHOP_log("Error sending tracking code: code $code", SHOP_LOG_ERROR);
            return false;
        }
        return true;
    }

}

?>
