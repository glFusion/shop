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
}
?>
