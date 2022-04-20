<?php
/**
 * Class to manage catalog feeds.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.0
 * @since       v1.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;
use Shop\Template;
use Shop\Category;
use Shop\Log;
use glFusion\Database\Database;


/**
 * Class for product feeds.
 * @package shop
 */
class Syndication extends \glFusion\Syndication\Feed
{
    protected $type = '';


    /**
     * Set the feed type from the supplied parameter.
     *
     * @param   string  $type       Type of feed
     */
    public function __construct($type)
    {
        $this->type = $type;
        echo $this->type;die;
    }


    /**
     * Accessor to set the format.
     *
     * @param   string  $format     Feed format
     * @return  object  $this
     */
    public function withFormat(string $format) : self
    {
        $this->type = $format;
        return $this;
    }


    /**
     * Get the available feed formats.
     * Only Google catalog is supporteb by Shop.
     *
     * @return  array   Array of name & version arrays
     */
    public static function getFormats() : array
    {
        return array(
            array(
                'name' => 'Google',
                'version' => '2.0',
            ),
        );
    }


    /**
     * Get all the products to be included in the feed.
     *
     * @return  array       Array of Product objects
     */
    private function _getProducts() : array
    {
        static $Products = NULL;

        if ($Products === NULL) {
            $Products = \Shop\Product::getAll();
            foreach ($Products as $id=>$P) {
                if (!$P->canDisplay()) {
                    unset($Products[$id]);
                }
            }
        }
        return $Products;
    }


    /**
     * Checks to see if the RSS feed is up-to-date.
     * For now, return false always since there are so many external actors
     * that affect the feed - sale pricing, quantity onhand, etc.
     *
     * @todo    Handle changes made by sale prices, quantity depletion, etc.
     * @param   integer $feed   Feed ID from the RSS configuration
     * @param   integer $topic  Topic ID being requested
     * @param   string  $update_data    Comma-separated string of current item IDs
     * @param   integer $limit  Configured limit on item count for this feed
     * @return  boolean         True if feed needs updating, False otherwise
     */
    public static function feedUpdateCheck(
        $feed, $topic, $update_data, $limit,
        $updated_type = '', $updated_topic = '', $updated_id = ''
    ) {
        global $_CONF;

        $Feed = self::_getFeedInfo($feed);
        $Now = clone $_CONF['_now'];
        $Now->sub(new \DateInterval('PT1H'));
        return $Feed['updated'] < $Now->toMySQL(true);
    }


    /**
     * Render a Catalog feed.
     * First checks the plugin configuration to verify that the feed is enabled.
     *
     * @uses    self::_getProducts()
     */
    public function Render(&$update_data)
    {
        global $_CONF;

        $T = new Template('feeds/catalog/');
        $T->set_file('feed', $this->type . '.thtml');
        if (!empty($T->last_error)) {
            Log::write('shop_system', Log::ERROR, "Missing catalog feed template for {$this->type}");
            return false;
        }
        $Cur = \Shop\Currency::getInstance();
        $Products = self::_getProducts();
        $T->set_var('newline', "\n");
        $T->set_block('feed', 'itemRow', 'iRow');
        //$content = array();
        $item_ids = array();
        foreach ($Products as $id=>$P) {
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
/*            $tmp = array(
//                'description' => $title,
//<item>
                'g:id' => $P->getID(),
                'g:title' => $title,
                'g:description' => $dscp,
                'g:link' => $P->getLink(),
                'g:image_link' => $img_link,
                'g:brand' => self::_fixText($P->getBrandName()),
                'g:condition' => 'new',
                'g:availability' => $P->isInStock() ? 'in stock' : 'preorder',
                'g:price' => $Cur->Format($base_price, false),
                'g:product_type' => self::_fixText($Cat->getName()),
            );
            $taxonomy = htmlspecialchars($Cat->getGoogleTaxonomy());
            if (!empty($taxonomy)) {
                $tmp['g:google_product_category'] = $taxonomy;
            }
            $content[] = $tmp;*/

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
            $item_ids[] = $P->getID();
        }
        $update_data = implode(',', $item_ids);
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


    /**
     * Get the feed information from the database.
     *
     * @param   integer $fid    Feed ID
     * @return  array       Array of key->values from the DB
     */
    private static function _getFeedInfo($fid)
    {
        global $_TABLES;
        static $feeds = array();

        $fid = (int)$fid;
        if (!isset($feeds[$fid])) {
            $res = DB_query(
                "SELECT * FROM {$_TABLES['syndication']}
                WHERE fid = $fid"
            );
            if ($res) {
                $feeds[$fid] = DB_fetchArray($res, false);
                if (!$feeds[$fid]) {
                    $feeds[$fid] = array(
                        'fid'       => 0,
                        'type'      => 'shop',
                        'topic'     => '::all',
                        'header_tid' => 'none',
                        'format'    => 'RSS-2.0',
                        'limits'    => 10,
                        'content_length' => 0,
                        'title'     => '',
                        'description' => '',
                        'feedlogo'  => '',
                        'filename'  => 'glfusion.rss',
                        'charset'   => 'UTF-8',
                        'language'  => 'en-gb',
                        'is_enabled' => 1,
                        'updated'   => '1970-01-01 00:00:00',
                        'update_info' => '',
                    );
                }
            }
        }
        return $feeds[$fid];
    }


    public static function getFeedNames()
    {
        global $LANG_SHOP;

        $feeds = array(
            // Always include "All" as an option
            array(
                'id' => '0',
                'name' => $LANG_SHOP['all_categories']
            ),
        );
        $Cats = Category::getAll(true);
        foreach ($Cats as $Cat) {
            if ($Cat->isFeedEnabled()) {
                $feeds[] = array(
                    'id' => $Cat->getID(),
                    'name' => $Cat->getName(),
                );
            }
        }
        return $feeds;
    }



    public static function getFeedContent(
        $feed, &$link, &$update_data, $feedType, $feedVersion, $A=array()
    ) {
        global $_TABLES, $_CONF;

        switch ($feedType) {
        default:
            $F = self::_getFeedInfo($feed);
            $fp = fopen(SYND_getFeedPath($F['filename']), "w+");
            if ($fp) {
                $Feed = new self($F['topic']);
                fwrite($fp, $Feed->Render($update_data));
                $db = Database::getInstance();
                $db->conn->executeUpdate(
                    "UPDATE {$_TABLES['syndication']} SET updated = ?, update_info = ? WHERE fid = ?",
                    array(
                        $_CONF['_now']->toMySQL(true),
                        $update_data,
                        $feed
                    ),
                    array(Database::STRING,Database::STRING,Database::STRING)
                );
            }
            return NULL;
                //$Feed = new self($F['topic']);
                //return $Feed->Render();
            break;
        }
    }

}
