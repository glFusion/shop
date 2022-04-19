<?php

namespace Shop\Trackers;
use Shop\Order;

class Matomo extends \Shop\Tracker
{
    /** Array of code snippets to include in the tracking code.
     * @var array */
    private $codes = array();


    /**
     * Get the site ID for Matomo.
     *
     * @return  integer     Matomo site ID
     */
    private function getSiteID()
    {
        global $_SHOP_CONF;
        return $_SHOP_CONF['trk_matomo_siteid'];
    }


    /**
     * Get the Matomo url.
     *
     * @return  string      URL to matomo installation
     */
    private function getAPIUrl()
    {
        global $_SHOP_CONF;
        return $_SHOP_CONF['trk_matomo_apiurl'];
    }


    /**
     * Add a single snippet to be included with the tracking code.
     *
     * @param   string  $code_txt   Code snippet
     * @return  object  $this
     */
    private function _addCode($code_txt)
    {
        $this->codes[] = $code_txt;
        return $this;
    }


    /**
     * Get the final tracking code to include in the site header.
     *
     * @return  string      Tracking code
     */
    public function getCode() : string
    {
        global $_CONF;

        $T = new \Template(SHOP_PI_PATH . '/templates/trackers');
        $T->set_file('tracker', 'matomo.thtml');

        $code_txt = implode("\n", $this->codes);
        $T->set_var(array(
            'matomo_url'    => $this->getAPIUrl(),
            'matomo_site_id' => $this->getSiteID(),
            '_id'           => self::makeCid(),
            'code_txt'      => $code_txt,
        ) );
        
        $T->parse('output', 'tracker');
        //var_dump($T->finish ($T->get_var('output')));die;
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Add tracking for a product view page.
     *
     * @param   object  $P      Product object
     * @return  object  $this
     */
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

        $this->_addCode("_paq.push(['setEcommerceView',
            '$sku',
            '$dscp',
            $cats,
            $price
        ]);");
        return $this;
    }


    /**
     * Add a cart item to the code.
     *
     * @param   object  $OI     OrderItem object
     * @return  object  $this
     */
    private function _addCartItem($OI)
    {
        $sku = $OI->getProductId();
        $dscp = $OI->getDscp();
        $price = $OI->getPrice();
        $qty = $OI->getQuantity();
        $cats = array();
        foreach ($OI->getProduct()->getCategories() as $Cat) {
            $cats[] = $Cat->getName();
        }
        $cats = !empty($cats) ? json_encode($cats) : '';
        $this->_addCode("_paq.push(['addEcommerceItem',
            '$sku',
            '$dscp',
            $cats,
            $price,
            $qty
        ]);");

        /// Records the cart for this visit
        //$this->_addCode("_paq.push(['trackEcommerceCartUpdate', $price]);");
        return $this;
    }


    /**
     * Add tracking for a cart view page.
     *
     * @param   object  $Ord    Order object
     * @return  object  $this
     */
    public function addCartView($Ord)
    {
        $net_items = 0;
        foreach ($Ord->getItems() as $Item) {
            $this->_addCartItem($Item);
            $net_items += $Item->getNetPrice() * $Item->getQuantity();
        }
        $this->_addCode("_paq.push(['trackEcommerceCartUpdate', {$net_items}]);");
        return $this;
    }


    /**
     * Record that a cart item was removed.
     *
     * @param   string  $sku    Item sku
     * @return  object  $this
     */
    public function delCartItem($sku)
    {
        $this->_addCode("_paq.push(['removeEcommerceItem', '$sku']");
        return $this;
    }


    /**
     * Record that the cart was emptied
     *
     * @return  object  $this
     */
    public function clearCart()
    {
        $this->_addCode("_paq.push(['clearEcommerceCart']");
        return $this;
    }


    public function confirmOrder(Order $Ord, ?string $session_id=NULL) : bool
    {
        $cid = self::makeCid($session_id);
        $net_items = 0;
        $items = array();
        foreach ($Ord->getItems() as $Item) {
            $sku = $Item->getProductId();
            $dscp = $Item->getDscp();
            $price = $Item->getPrice();
            $qty = $Item->getQuantity();
            /*$cats = array();
            foreach ($Item->getProduct()->getCategories() as $Cat) {
                $cats[] = $Cat->getName();
            }
            //$cats = !empty($cats) ? json_encode($cats) : '';
            $cats = !empty($cats) ? implode(',', $cats) : '';*/
            $items[] = array(
                $sku,
                $dscp,
                '',   //$cats,
                $price,
                $qty,
            ); 
            $net_items += $Item->getNetPrice() * $Item->getQuantity();
        }
        $items = urlencode(json_encode($items));
        $params = array(
            'url'       => urlencode('https://gldev.leegarner.com/shop/ipn/paypal.php'),
            'idgoal'    => 0,
            'action_name' => 'Order/Confirm',
            'idsite'    => $this->getSiteID(),
            'rec'       => 1,
            '_id'       => $cid,
            'ec_id'     => $Ord->getOrderID(),
            'ec_items'  => $items,
            'revenue'   => $Ord->getTotal(),
            'ec_tx'     => $Ord->getTax(),
            'ec_sh'     => $Ord->getShipping(),
            'ec_st'     => $net_items,
            'rand'      => rand(100,900),
            'apiv'      => 1,
        );
        /*$params = array(
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
            'apiv=1',
        );
        $params = implode('&', $params);*/
        $params = http_build_query($params);
        return self::_curlExec($this->getAPIUrl() . '/matomo.php?' . $params);
    }


    /**
     * Add tracking for a category view page.
     *
     * @param   string  $cat_name   Category name
     * @return  object  $this
     */
    public function addCategoryView($cat_name)
    {
        $this->_addCode("_paq.push(['setEcommerceView',
            productSku = false,
            productName = false,
            category = '{$cat_name}'
            ]);"
        );
        return $this;
    }

}

