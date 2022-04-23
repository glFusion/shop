<?php
/**
 * Tracker class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Order;
use Shop\Config;


/**
 * Tracker class to interface with the Analytics plugin.
 * @package shop
 */
class Tracker
{
    /**
     * Get an instance of the Analytics tracker used for Ecommerce.
     * If the Analytics plugin is not installed, return NULL;
     *
     * @return  mixed   Tracker object if available, NULL otherwise
     */
    private static function getTracker() : ?object
    {
        static $Trk = NULL;
        $tracker = Config::get('tracker');
        if ($Trk === NULL && !empty($tracker)) {
            if (function_exists('plugin_chkVersion_analytics')) {
                $Trk = \Analytics\Tracker::getInstance($tracker);
            }
        }
        return $Trk;
    }


    /**
     * Create a unique ID based on the session ID.
     *
     * @param   string  $session_id     Optional session ID
     * @return  string      Unique ID
     */
    public static function makeCid(?string $session_id=NULL) : string
    {
        if ($session_id === NULL) {
            $session_id = session_id();
        }
        return substr(md5($session_id), 0, 16);
    }


    /**
     * Adds an OrderItem object to the tracker list view array.
     *
     * @param   object  $OI     OrderItem object
     * @param   string  $list_name  Optional list name
     * @return  array       Array of tracker items
     */
    public static function addOrderListItem(OrderItem $OI, ?string $list_name=NULL) : array
    {
        $Trk = self::getTracker();
        if (!$Trk) {
            return array();
        }

        $LV = \Analytics\Models\Ecommerce\ItemListView::getInstance();
        $LV->addItem(self::makeOrderItemView($OI, $list_name));
        return $LV->items;
    }


    /**
     * Adds Product object to the tracker list view array.
     *
     * @param   object  $P      Product object
     * @param   string  $list_name  Optional list name
     */
    public static function addProductListViewItem(Product $P, ?string $list_name=NULL) : void
    {
        $Trk = self::getTracker();
        if (!$Trk) {
            return;
        }

        $LV = \Analytics\Models\Ecommerce\ItemListView::getInstance();
        $LV->addItem(self::makeProductView($P, $list_name));
    }


    /**
     * Gets the product list view items as a basic array of item info.
     *
     * @return  array   Simple array of item info
     */
    public static function getProductListItems() : array
    {
        $Trk = self::getTracker();
        if (!$Trk) {
            return array();
        }

        $retval = array();
        $LV = \Analytics\Models\Ecommerce\ItemListView::getInstance();
        foreach ($LV->items as $IV) {
            $retval[] = $IV->toArray();
        }
        return $retval;
    }


    /**
     * Sets the product list view items using a basic array of item info.
     *
     * @param   array   $items      Simple array of item info
     */
    public static function setProductListItems(array $items) : void
    {
        $Trk = self::getTracker();
        if (!$Trk) {
            return;
        }

        $LV = \Analytics\Models\Ecommerce\ItemListView::getInstance();
        foreach ($items as $item) {
            $LV->addItem(new \Analytics\Models\Ecommerce\ItemView($item));
        }
    }



    /**
     * Adds a product list view to the tracker codes.
     *
     * @param   string  $event      Event type
     */
    public static function addProductListView(?string $event=NULL) : void
    {
        $Trk = self::getTracker();
        if ($Trk) {
            $LV = \Analytics\Models\Ecommerce\ItemListView::getInstance();
            $Trk->addProductListView($LV, $event);
        }
    }


    /**
     * Adds a single product view, e.g. viewing product detail.
     *
     * @param   object  $P      Product object
     */
    public static function addProductView(Product $P) : void
    {
        $Trk = self::getTracker();
        if ($Trk) {
            $Trk->addProductView(self::makeProductView($P));
        }
    }


    /**
     * Converts a single Product into an Analytics ItemView object.
     *
     * @param   object  $P      Product object
     * @param   string  $list_name  Optional list name
     * @return  object      ItemView object
     */
    private static function makeProductView($P, ?string $list_name=NULL) : ?object
    {
        $Trk = self::getTracker();
        if (!$Trk) {
            return NULL;
        }

        $IV = new \Analytics\Models\Ecommerce\ItemView;
        $IV->item_id = $P->getID();
        $IV->sku = $P->getName();
        $IV->short_dscp = $P->getShortDscp();
        $IV->long_dscp = $P->getDscp();
        $IV->price = $P->getPrice();
        $IV->brand = $P->getBrandName();

        $Variants = $P->getVariants();
        if (count($Variants) > 0) {
            $IV->variant = $Variants[0]->getDscpString();
        }
        $cats = array();
        foreach ($P->getCategories() as $Cat) {
            $cats[] = $Cat->getName();
        }
        if (!empty($cats)) {
            $IV->categories = implode(',', $cats);
        }
        if (!empty($list_name)) {
            $IV->list_name = $list_name;
        }
        return $IV;
    }


    /**
     * Converts a single OrderItem into an Analytics ItemView object.
     *
     * @param   object  $OI     OrderItem object
     * @param   string  $list_name  Optional list name
     * @return  object      ItemView object
     */
    private static function makeOrderItemView($OI, ?string $list_name=NULL) : ?object
    {
        $Trk = self::getTracker();
        if (!$Trk) {
            return NULL;
        }

        $IV = new \Analytics\Models\Ecommerce\ItemView;
        $P = $OI->getProduct();
        $IV->item_id = $OI->getID();
        $IV->sku = $OI->getSku();
        $IV->short_dscp = $OI->getDscp();
        $IV->price = $OI->getPrice();
        $IV->brand = $P->getBrandName();
        $IV->variant = $OI->getVariant()->getDscpString();
        $IV->quantity = $OI->getQuantity();
        $cats = array();
        foreach ($P->getCategories() as $Cat) {
            $cats[] = $Cat->getName();
        }
        if (!empty($cats)) {
            $IV->categories = implode(',', $cats);
        }
        if (!empty($list_name)) {
            $IV->list_name = $list_name;
        }
        return $IV;
    }


    /**
     * Creates a purchase view from an Order object.
     *
     * @param   object  $Ord        Order object
     * @param   string  $event      Event name, default = purchase
     */
    public static function addPurchaseView(Order $Ord, ?string $event=NULL) : void
    {
        $Trk = self::getTracker();
        if (!$Trk) {
            return;
        }

        if ($event === NULL) {
            $event = 'purchase';
        }
        $OV = \Analytics\Models\Ecommerce\OrderView::getInstance();
        foreach ($Ord->getItems() as $OI) {
            $OV->addItem(self::makeOrderItemView($OI));
        }
        $OV->transaction_id = $Ord->getOrderId();
        $OV->value= $Ord->getTotal();
        $OV->affiliation = Config::get('company');
        $OV->currency = \Shop\Currency::getInstance()->getCode();
        $OV->tax = $Ord->getTax();
        $OV->shipping = $Ord->getShipping();
        $Trk->addTransactionView($OV, $event);
    }

}

