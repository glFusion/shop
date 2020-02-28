<?php

namespace Shop\Trackers;

class Matomo extends \Shop\Tracker
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


    private function addCode($code_txt)
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
            '_id'           => self::makeCid(),
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
        return $this;
    }


    public function delCart($sku)
    {
        $this->addCode("_paq.push(['removeEcommerceItem', '$sku']");
        return $this;
    }


    public function clearCart()
    {
        $this->addCode("_paq.push(['clearEcommerceCart']");
        return $this;
    }


    public function confirmOrder($Ord, $session_id)
    {
        $cid = self::makeCid($session_id);
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
            'action_name=Order/Confirm',
            'idsite=' . $this->getSiteID(),
            'rec=1',
            '_id=' . $cid,
            'ec_id='  . $Ord->getOrderID(),
            'ec_items=' . $items,
            'revenue=' . $Ord->getTotal(),
            'ec_tx=' . $Ord->getTax(),
            'ec_sh=' . $Ord->getShipping(),
            'ec_st=' . $net_items,
            'rand=' . rand(100,900),
        );
        $params = implode('&', $params);
        return self::_curlExec($this->getMatomoUrl() . '/matomo.php?' . $params);
    }

}

?>
