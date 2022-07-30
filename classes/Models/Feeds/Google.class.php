<?php
/**
 * Class to manage catalog feeds.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models\Feeds;
use Shop\Log;
use Shop\Template;
use Shop\Currency;
use Shop\Product;
use Shop\Config;


/**
 * Class for product feeds.
 * @package shop
 */
class Google extends \Shop\Models\Syndication
{
    /**
     * Render a Catalog feed.
     * First checks the plugin configuration to verify that the feed is enabled.
     *
     * @return  boolean     True on success, False on error
     */
    protected function _generate() : bool
    {
        global $_SHOP_CONF, $_CONF;

        $T = new Template('feeds/catalog/');
        $T->set_file('feed', 'google.thtml');
        if (!empty($T->last_error)) {
            Log::write('shop_system', Log::ERROR, "Missing catalog feed template for {$this->fid}");
            return false;
        }

        $Cur = Currency::getInstance();
        $Products = Product::getAll();
        $product_ids = array();

        $T->set_var(array(
            'feed_title' => $this->getTitle(),
            'feed_dscp' => $this->getDescription(),
            'lb' => "\n",
        ) );
        $T->set_block('feed', 'itemRow', 'iRow');
        foreach ($Products as $P) {
            if (!$P->canDisplay()) {
                continue;
            }
            $base_price = $P->getPrice();
            $Sale = $P->getSale();
            if (!$Sale->isNew()) {
                $sale_price = $Cur->Format($Sale->calcPrice($base_price), false);
                $sale_eff_dt = $Sale->getStartDate()->Format(\DateTime::ATOM) .
                    '/' . $Sale->getStartDate()->Format(\DateTime::ATOM);
            } else {
                $sale_price = '';
                $sale_eff_dt = '';
            }
            $title = self::_fixText($P->getShortDscp());
            if ($P->getDscp() == '') {
                $dscp = $title;
            } else {
                $dscp = self::_fixText($P->getDscp());
            }
            if ($P->isInStock()) {
                $availability = 'in stock';
            } else {
                $availability = 'available for orde';
            }
            $img = $P->getOneImage();
            if (empty($img)) {
                $img = 'notavailable.jpg';
            }
            $brand = $P->getBrandName();
            if (empty($brand)) {
                $brand = 'none';
            }
            $img_link = $P->getImage($img, 480, 480)['url'];
            $Cats = $P->getCategories();
            $Cat = array_shift($Cats);  // get the first category (random)
            $taxonomy = $Cat->getGoogleTaxonomy();
            if (empty($taxonomy)) {
                $taxonomy = Config::get('def_google_category');
            }
            $T->set_var(array(
                'product_id'    => $P->getID(),
                'short_dscp'    => $title,
                'long_dscp'     => $dscp,
                'cat_name'      => self::_fixText($Cat->getName()),
                'product_url'   => $P->getLink(),
                'product_img_url' => $img_link,
                'availability'  => $P->isInStock() ? 'in stock' : 'preorder',
                'price'         => $Cur->Format($base_price, false),
                'sale_price'    => $sale_price,
                'sale_eff_dt'   => $sale_eff_dt,
                'brand'         => self::_fixText($brand),
                'google_taxonomy' => htmlspecialchars($taxonomy),
                'lb'            => "\n",
                'availability'  => $availability,
            ) );
            $T->parse('iRow', 'itemRow', true);
            $product_ids[] = $P->getID();
        }
        $content = $T->parse('output', 'feed');

        if ($this->writeFile($content)) {
            $this->setUpdateData(implode(',', $product_ids));
            return true;
        } else {
            return false;
        } 
    }

}
