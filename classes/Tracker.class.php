<?php

namespace Shop;

class Tracker
{
    public function getInstance()
    {
        static $T = NULL;
        if ($T === NULL) {
            $T = new Trackers\Matomo;
        }
        return $T;
    }


    protected static function makeCid($session_id=NULL)
    {
        if ($session_id === NULL) {
            $session_id = session_id();
        }
        return substr(md5($session_id, 0, 16));
    }

}
?>
