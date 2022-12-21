<?php
/**
 * Sitemap driver for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2022 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Sitemap\Drivers;
use Sitemap\Models\Item;
use Shop\Category;
use Shop\Config;


/**
 * Sitemap driver class for Shop.
 * @package shop
 */
class shop extends BaseDriver
{
    /** Plugin name.
     * @var string */
    protected $name = 'shop';

    /**
     * Get the plugin main URL.
     *
     * @return  string  Entry URL
     */
    public function getEntryPoint() : ?string
    {
        return Config::get('shop_enabled') ? Config::get('url') . '/index.php' : NULL;
    }


    /**
     * Get the plugin display name.
     *
     * @return  string  Display name
     */
    public function getDisplayName()
    {
        global $LANG_SHOP;
        return Config::get('shop_enabled') ? $LANG_SHOP['main_title'] : '';
    }


    /**
     * Get items for the sitemap under a specific category.
     *
     * @param   integer $cat_id     Category ID
     * @return  array       Array of sitemap entries
     */
    public function getItems($cat_id = 0)
    {
        $entries = array();
        if (!Config::get('shop_enabled')) {
            return $entries;
        }
        $opts = array(
            'groups' => $this->groups,
        );
        if ($cat_id > 0) {
            $opts['cat_id'] = $cat_id;
        }
        $items = PLG_getItemInfo('shop', '*', 'id,introtext,date,url', $this->uid, $opts);
        if (is_array($items)) {
            foreach ($items as $A) {
                $item = new Item;
                $item->withItemId($A['id'])
                     ->withTitle($A['introtext'])
                     ->withUrl($A['url'])
                     ->withDate($A['date']);
                $entries[] = $item->toArray();
            }
        }
        return $entries;
    }


    /**
     * Get the immediate child categories under a given base category.
     *
     * @param   integer $base   Category ID
     * @return  array       Array of categories
     */
    public function getChildCategories($base = false) : array
    {
        $retval = array();
        if (!Config::get('shop_enabled')) {
            return $retval;
        }

        if ($base === false) {
            $Root = Category::getRoot();
        } else {
            $Root = Category::getInstance((int)$base);
        }
        $cats = $Root->getChildren();
        foreach ($cats as $Cat) {
            if ($Cat->hasAccess($this->groups)) {
                $item = new Item;
                $item->withItemId($Cat->getID())
                     ->withTitle($Cat->getName())
                     ->withUrl(SHOP_URL . '/index.php?category=' . $Cat->getID())
                     ->withImageUrl($Cat->getImage()['url']);
                $retval[] = $item->toArray();
            }
        }
        return $retval;
    }

}

