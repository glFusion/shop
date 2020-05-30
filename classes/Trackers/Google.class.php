<?php

namespace Shop\Trackers;

class Google extends \Shop\Tracker
{

    private $codes = array();

    private function getSiteID()
    {
        return "1";
    }


    private function addCode($code_txt)
    {
        $this->codes[] = $code_txt;
        return $this;
    }


    public function getCode()
    {
        global $_CONF, $_SHOP_CONF;

        $T = new \Template(SHOP_PI_PATH . '/templates/trackers');
        $T->set_file('tracker', 'google.thtml');
        $code_txt = implode("\n", $this->codes);
        $T->set_var(array(
            'site_id'       => $_SHOP_CONF['trk_google_id'],
            'code_txt'      => $code_txt,
        ) );

        $T->parse('output', 'tracker');
        //var_dump($T->finish ($T->get_var('output')));die;
        return $T->finish ($T->get_var('output'));
    }


    public function XaddProductView($P)
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
        $qty = $Item->getQuantity();
        $cats = array();
        foreach ($Item->getProduct()->getCategories() as $Cat) {
            $cats[] = $Cat->getName();
        }
        $cats = !empty($cats) ? json_encode($cats) : '';
        $this->addCode("gtag('event', 'add_to_cart', {
            'value: " . ($qty * $price) . ",
            'items: [
                'id': '{$sku}',
                'name': '{$dscp}',
                'brand': '" . $Item->getProduct()->getBrandName() . "',
                'category': 'shirts',
                'variant': 'black',
                'quantity': {$Item->getQuantity()},
                'price': {$price}
        ]);");
        
        /// Records the cart for this visit
        //$this->addCode("_paq.push(['trackEcommerceCartUpdate', $price]);");
        return $this;
    }


    public function addCartView($Ord)
    {
        $net_items = 0;
        $items = array();
        foreach ($Ord->getItems() as $Item) {
            $this->addCartItem($Item);
            $net_items += $Item->getNetPrice() * $Item->getQuantity();
            $items[] = array(
                'id' => $Item->getProductID(),
                'name' => $Item->getDscp(),
                'brand' => $Item->getProduct()->getBrandName(),
                'category' => 'shirts',
                'variant' => 'black',
                'quantity' => $Item->getQuantity(),
                'price' => $Item->getPrice(),
            );
        }
        $items = json_encode($items);
        $this->addCode("gtag('event', 'begin_checkout', {
            \"transaction_id\": \"" . $Ord->getOrderID() . "\",
            \"affiliation\": \"Google online store\",
            \"value\": {$net_items},
            \"currency\": \"{$Ord->getCurrency()->getCode()}\",
            \"tax\": {$Ord->getTax()},
            \"shipping\": {$Ord->getShipping()},
            \"items\": {$items}
        });");
        return $this;
    }


    public function XdelCart($sku)
    {
        $this->addCode("_paq.push(['removeEcommerceItem', '$sku']");
        return $this;
    }


    public function XclearCart()
    {
        $this->addCode("_paq.push(['clearEcommerceCart']");
        return $this;
    }


    public function getAPIUrl()
    {
        return 'https://gldev.leegarner.com';
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
        return self::_curlExec($this->getAPIUrl() . '/matomo.php?' . $params);
    }


    public function XaddCategoryView($cat_name)
    {
        $this->addCode("_paq.push(['setEcommerceView',
            productSku = false,
            productName = false,
            category = '{$cat_name}'
            ]);"
        );
        return $this;
    }

}

?>
