<?php

namespace Shop\Trackers;

class Matomo
{

    private $codes = array();

    public function __construct()
    {
    }


    private function getSiteID()
    {
        return "1";
    }


    private function getMatomoUrl()
    {
        return "https://gldev.leegarner.com/matomo";
    }


    public static function getInstance()
    {
        static $M = NULL;
        if ($M === NULL) {
            $M = new self;
        }
        return $M;
    }

    public function addCode($code_txt)
    {
        $this->codes[] = $code_txt;
        return $this;
    }


    public function getCode()
    {
        global $_CONF;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('tracker', 'matomo_tracking.thtml');

        $code_txt = implode("\n", $this->codes);
        $T->set_var(array(
            'matomo_url'    => $_CONF['site_url'] . '/matomo/',
            'matomo_site_id' => 1,
            '_id'           => session_id(),
            'code_txt'      => $code_txt,
        ) );
        
        $T->parse('output', 'tracker');
        return $T->finish ($T->get_var('output'));
    }


    public function addProductView($P)
    {
        $sku = $P->getID();
        $dscp = $P->getDscp();
        $price = $P->getPrice();
        $cats = array();
        foreach ($P->getCategories() as $Cat) {
            $cats[] = $Cat->getName();
        }
        $cats = !empty($cats) ? json_encode($cats) : '';

        $this->addCode("_paq.push(['setEcommerceView',
            '$sku',
            '$dscp',
            $cats,
            $price
        ]);");
        return $this;
    }


    public function addCartItem($Item)
    {
        $sku = $Item->getProductId();
        $dscp = $Item->getDscp();
        $price = $Item->getPrice();
        $cats = array();
        foreach ($Item->getProduct()->getCategories() as $Cat) {
            $cats[] = $Cat->getName();
        }
        $cats = !empty($cats) ? json_encode($cats) : '';
        $this->addCode("_paq.push(['addEcommerceItem',
            '$sku',
            '$dscp',
            $cats,
            $price,
            $qty
        ]);");
        
        /// Records the cart for this visit
        //$this->addCode("_paq.push(['trackEcommerceCartUpdate', $price]);");
        return $this;
    }


    public function addCart($Ord)
    {
        $net_items = 0;
        foreach ($Ord->getItems() as $Item) {
            $this->addCartItem($Item);
            $net_items += $Item->getNetPrice() * $Item->getQuantity();
        }
        $this->addCode("_paq.push(['trackEcommerceCartUpdate', {$net_items}]);");
    }


    public function clearCart()
    {
        $this->addCode("_paq.push(['clearEcommerceCart']);");
    }


    public function confirmOrder($Ord, $session_id)
    {
        $net_items = 0;
        $items = array();
        foreach ($Ord->getItems() as $Item) {
            $sku = $Item->getProductId();
            $dscp = $Item->getDscp();
            $price = $Item->getPrice();
            $cats = array();
            foreach ($Item->getProduct()->getCategories() as $Cat) {
                $cats[] = $Cat->getName();
            }
            $cats = !empty($cats) ? json_encode($cats) : '';
            $items[] = array(
                $Item->getProductId(),
                $Item->getDscp(),
                $cats,
                $Item->getPrice(),
                $Item->getQuantity(),
            ); 
            $net_items += $Item->getNetPrice() * $Item->getQuantity();
        }
        $items = urlencode(json_encode($items));
        $params = array(
            'url=' . urlencode('https://gldev.leegarner.com/shop/ipn/paypal.php'),
            'idgoal=0',
            'idsite=' . $this->getSiteID(),
            'rec=1',
            '_id=' . $session_id,
            'ec_id='  . $Ord->getOrderID(),
            'ec_items=' . $items,
            'revenue=' . $Ord->getTotal(),
            'ec_tx=' . $Ord->getTax(),
            'ec_sh=' . $Ord->getShipping(),
            'ec_st=' . $net_items,
        );
        $params = implode('&', $params);
        return self::curlExec($this->getMatomoUrl() . '/matomo.php?' . $params);
    }


    private function curlExec($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            SHOP_log("Error sending tracking code to Matomo: code $code", SHOP_LOG_ERROR);
            return false;
        }
        return true;
    }

}

?>
