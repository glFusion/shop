<?php
/**
 * Sitemap driver for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2020 Lee Garner
 * @package     shop
 * @version     v1.1.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Sitemap\Drivers;
use Shop\Category;

/**
 * Sitemap driver class for Shop.
 * @paackage shop
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
    public function getEntryPoint()
    {
        global $_SHOP_CONF;
        return $_SHOP_CONF['shop_enabled'] ? SHOP_URL : '';
    }


    /**
     * Get the plugin display name.
     *
     * @return  string  Display name
     */
    public function getDisplayName()
    {
        global $LANG_SHOP, $_SHOP_CONF;
        return $_SHOP_CONF['shop_enabled'] ? $LANG_SHOP['main_title'] : '';
    }


    /**
     * Get items for the sitemap under a specific category.
     *
     * @param   integer $cat_id     Category ID
     * @return  array       Array of sitemap entries
     */
    public function getItems($cat_id = 0)
    {
        global $_TABLES, $_USER, $_SHOP_CONF;

        $entries = array();
        if (!$_SHOP_CONF['shop_enabled']) {
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
                $entries[] = array(
                    'id'    => $A['id'],
                    'title' => $A['introtext'],
                    'uri'   => $A['url'],
                    'date'  => $A['date'],
                    'image_uri' => false,
                );
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
    public function getChildCategories($base = false)
    {
        global $_SHOP_CONF;

        $retval = array();
        if (!$_SHOP_CONF['shop_enabled']) {
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
                $retval[] = array(
                    'id'        => $Cat->getID(),
                    'title'     => $Cat->getName(),
                    'uri'       => SHOP_URL . '/index.php?category=' . $Cat->getID(),
                    'date'      => false,
                    'image_uri' => $Cat->getImage()['url'],
                );
            }
        }
        return $retval;
    }

}

?>
