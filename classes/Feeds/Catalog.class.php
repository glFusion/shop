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
namespace Shop\Feeds;

/**
 * Class for product feeds.
 * @package shop
 */
class Catalog
{
    /**
     * Set the feed type from the supplied parameter.
     *
     * @param   string  $type       Type of feed
     */
    public function __construct($type)
    {
        $this->type = $type;
    }


    /**
     * Render a Catalog feed.
     * First checks the plugin configuration to verify that the feed is enabled.
     *
     * @uses    self::_Render()
     */
    public function Render()
    {
        global $_SHOP_CONF;

        if (
            isset($_SHOP_CONF['feed_' . $this->type]) &&
            $_SHOP_CONF['feed_' . $this->type]
        ) {
            return $this->_Render($this->type);
        } else {
            COM_404();
        }
    }


    /**
     * Render a feed for a single provicer.
     * The template must exist and the provider must be enabled.
     *
     * @param   string  $feed   Name of feed, e.g. 'facebook'
     */
    private function _Render($feed)
    {
        global $_CONF;

        $T = new \Template(SHOP_PI_PATH . '/templates/feeds/catalog/');
        $T->set_file('feed', $feed . '.thtml');
        if (!empty($T->last_error)) {
            SHOP_log("Missing catalog feed template for $feed");
            return false;
        }
        $Cur = \Shop\Currency::getInstance();
        $Products = \Shop\Product::getAll();
        $T->set_var('newline', "\n");
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
            $img_link = $P->getImage($img, 480, 480)['url'];
            $Cats = $P->getCategories();
            $Cat = array_shift($Cats);  // get the first category (random)
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
                'brand'         => self::_fixText($P->getBrandName()),
                'google_taxonomy' => htmlspecialchars($Cat->getGoogleTaxonomy()),
                'lb'            => "\n",
                'availability'  => $availability,
            ) );
            $T->parse('iRow', 'itemRow', true);
        }
        $content = $T->parse('output', 'feed');
        return $content;
    }


    /**
     * Fixes the text for the CSV file.
     *
     * @param   string  $str        Original text string
     * @param   string  $default    Optional default if $str is empty
     * @return  string      Sanitized string.
     */
    private static function _fixText($str, $default=NULL)
    {
        $search = array(
            '"',
            //"\n\n",
        );
        $replace = array(
            '""',
            //"\n",
        );
        $str = strip_tags($str);
        $str = trim(str_replace($search, $replace, $str));
        if ($str == '' && $default !== NULL) {
            $str = $default;
        }
        return $str;
    }

}

?>
