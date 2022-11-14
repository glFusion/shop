<?php
/**
 * Class to render the catalog view.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Views;
use Shop\Models\ProductType;
use Shop\Models\Views;
use Shop\Collections\ProductCollection;
use Shop\Product;
use Shop\Category;
use Shop\Cart;
use Shop\Currency;
use Shop\Config;
use Shop\Template;
use Shop\Rules\Zone as ZoneRule;
use Shop\Rules\Product as ProductRule;


/**
 * Class to display the product catalog.
 * @package shop
 */
class Catalog
{
    /** Homepage layout - product list (default).
     * @const integer */
    public const HP_PRODUCT = 1;

    /** Homepage layout - category list.
     * @const integer */
    public const HP_CAT = 2;

    /** Homepage layout - category list, include home category.
     * @const integer */
    public const HP_CATHOME = 4;

    /** Homepage layout - category list, top-level only.
     * @const integer */
    public const HP_CATTOP = 8;

    /** Brand ID, to limit results.
     * @var integer */
    private $brand_id = 0;

    /** Category ID, to limit results.
     * @var mixed */
    private $cat_id = 0;

    /** Cart object for creating nonce values.
     * @var object */
    private $Cart = NULL;

    /** Query string received from the URL.
     * @var string */
    private $query_str = '';


    /**
     * Set the brand ID to limit results.
     *
     * @param   integer $brand_id   Brand ID
     * @return  object  $this
     */
    public function setBrandID($brand_id)
    {
        $this->brand_id = (int)$brand_id;
        return $this;
    }


    /**
     * Set the category to limit results shown.
     * `$cat_id` can be an integer for a Shop category, or a string for a
     * plugin name.
     *
     * @param   integer|string  $cat_id     Category ID/Plugin Name
     * @return  object      $this
     */
    public function setCatID(?int $cat_id) : self
    {
        if (is_numeric($cat_id) || empty($cat_id)) {
            $this->cat_id = (int)$cat_id;
        } else {
            $this->cat_id = $cat_id;
        }
        return $this;
    }


    /**
     * Set the query string.
     *
     * @param   string  $query  Search string
     * @return  object  $this
     */
    public function withQuery(string $query) : self
    {
        $this->query_str = $query;
        return $this;
    }


    /**
     * Show the default catalog layout based on plugin configuration.
     *
     * @return  string      HTML for catalog display
     */
    public function defaultCatalog()
    {
        global $LANG_SHOP;

        $content = '';

        // Check access here in case centerblock is enabled.
        if (!SHOP_access_check()) {
            return $content;
        }

        if (
            $this->cat_id == 0 &&
            empty($this->query) &&
            (Config::get('hp_layout') & self::HP_CAT) == self::HP_CAT
        ) {
            $content .= $this->Categories();
        } else {
            $content .= $this->Products();
            $menu_opt = $LANG_SHOP['products'];
            $page_title = $LANG_SHOP['main_title'];
        }
        return $content;
    }


    /**
     * Diaplay the product catalog items.
     *
     * @return  string      HTML for product catalog.
     */
    public function Products()
    {
        global $_TABLES, $_CONF, $LANG_SHOP, $_USER, $_PLUGINS;

        $isAdmin = plugin_ismoderator_shop() ? true : false;

        $T = new Template(array(
            'list/' . Config::get('list_tpl_ver', 'v1'),
            'buttons',
            '',
        ) );
        $T->set_file(array(
            'wrapper'   => 'wrapper.thtml',
            'start'   => 'product_list_start.thtml',
            'end'     => 'product_list_end.thtml',
            'download'  => 'btn_download.thtml',
            'login_req' => 'btn_login_req.thtml',
            'btn_details' => 'btn_details.thtml',
        ) );

        // If a string is submitted as the category ID, treat it as a plugin and
        // show all the products under that category.
        if (!is_int($this->cat_id) && !empty($this->cat_id)) {
            $display = $T->parse('', 'start');
            $this->getPluginProducts($T, $this->cat_id);
            $T->set_block('wrapper', 'ProductItems', 'PI');
            $display .= $T->parse('', 'wrapper');
            $display .= $T->parse('', 'end');
            return $display;
        }

        $PrColl = new ProductCollection;

        $cat_name = '';
        $cat_dscp = '';
        $cat_img_url = '';
        $brand_logo_url = '';
        $prod_by_brand = '';
        $brand_name = '';
        $brand_dscp = '';
        $display = '';
        $Cat = Category::getInstance($this->cat_id);
        $Cart = Cart::getInstance();

        // If a cat ID is requested but doesn't exist or the user can't access
        // it, redirect to the homepage.
        if ($Cat->getID() > 0 && ($Cat->isNew() || !$Cat->hasAccess())) {
            echo COM_refresh(SHOP_URL);
            exit;
        }

        // Get the root category and see if the requested category is root.
        $RootCat = Category::getRoot();
        if ($this->brand_id == 0) {       // no brand limit, check for a category ID
            $cat_name = $Cat->getName();
            $cat_dscp = $Cat->getDscp();
            $cat_img_url = $Cat->getImage()['url'];
            $PrColl->withCategoryId($Cat->getID());
        } else {
            $Sup = Supplier::getInstance($this->brand_id);
            if ($Sup->getID() > 0) {    // to check validity
                $PrColl->withBrandId($this->brand_id);
            }
            $brand_logo_url = $Sup->getImage()['url'];
            $prod_by_brand = sprintf($LANG_SHOP['prod_by_brand'], $Sup->getName());
            $brand_name = $Sup->getName();
            $brand_dscp = $Sup->getDscp();
        }

        // Display top-level categories
        $A = array(
            $RootCat->getID() => array(
                'name' => $RootCat->getName(),
            ),
        );
        $tmp = $RootCat->getChildren();
        foreach ($tmp as $tmp_cat_id=>$C) {
            if ($C->getParentID() == $RootCat->getID() && $C->hasAccess()) {
                $A[$C->getID()] = array(
                    'name' => $C->getName(),
                    //'count' => $C->cnt,
                );
            }
        }

        if (count($A) > 1) {
            // Only include the categories if there is more than the root cat.
            $CT = new Template;
            $CT->set_file('catlinks', 'category_links.thtml');
            if ($cat_img_url != '') {
                $CT->set_var('catimg_url', $cat_img_url);
            }
            $CT->set_block('catlinks', 'CatLinks', 'link');
            foreach ($A as $category => $info) {
                if (isset($info['url'])) {
                    $url = $info['url'];
                } elseif ($category == $RootCat->getID()) {
                    $url = SHOP_URL;
                } else {
                    $url = SHOP_URL . '/index.php?category=' . urlencode($category);
                }
                $CT->set_var(array(
                    'category_name' => $info['name'],
                    'category_link' => $url,
                ) );
                $CT->parse('link', 'CatLinks', true);
            }
            $display .= $CT->parse('', 'catlinks');
        }

        // Set the product sorting and create the sort selector.
        if (isset($_REQUEST['sortby'])) {
            $sortby = $_REQUEST['sortby'];
        } else {
            $sortby = Config::get('order', 'name');
        }
        switch ($sortby){
        case 'price_l2h':   // price, low to high
            $PrColl->orderBy('price', 'ASC');
            break;
        case 'price_h2l':   // price, high to low
            $PrColl->orderBy('price', 'DESC');
            break;
        case 'top_rated':
            $PrColl->orderBy('rating', 'DESC')
                   ->orderBy('votes', 'DESC');
            break;
        case 'newest':
            $PrColl->orderBy('dt_add', 'DESC');
            break;
        case 'name':
        default:
            $sortby = 'name';
            $PrColl->orderBy('short_description', 'ASC');
            break;
        }
        $sortby_options = '';
        foreach ($LANG_SHOP['list_sort_options'] as $value=>$text) {
            $sel = $value == $sortby ? ' selected="selected"' : '';
            $sortby_options .= "<option value=\"$value\" $sel>$text</option>\n";
        }

        // Add search query, if any
        $search = '';
        if (!empty($this->query_str)) {
            $PrColl->withSearchString($this->query_str);
        }

        $pagenav_args = array();
        if ($this->cat_id > 0) {
            $pagenav_args[] = 'category=' . $this->cat_id;
        }

        // Count total products from database.
        $count = $PrColl->getCount();

        // If applicable, handle pagination of query
        $prod_per_page = Config::get('prod_per_page', 20);
        if ($prod_per_page > 0) {
            // Make sure page requested is reasonable, if not, fix it
            if (!isset($_REQUEST['page']) || $_REQUEST['page'] <= 0) {
                $_REQUEST['page'] = 1;
            }
            $page = (int)$_REQUEST['page'];
            $start_limit = ($page - 1) * $prod_per_page;
            if ($start_limit > $count) {
                $page = ceil($count / $prod_per_page);
            }
            // Add limit for pagination (if applicable)
            if ($count > $prod_per_page) {
                $PrColl->withLimit($start_limit, $prod_per_page);
            }
        }

        // Re-execute query with the limit clause in place
        $Products = $PrColl->getObjects();

        // Create product template
        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            //'user_id'       => $_USER['uid'],
            'currency'      => Config::get('currency'),
            'breadcrumbs'   => $this->cat_id > 0 ? $Cat->Breadcrumbs() : '',
            'search_text'   => $search,
            'tpl_ver'       => Config::get('list_tpl_ver'),
            'sortby_options' => $sortby_options,
            'sortby'        => $sortby,
            'cat_id'        => $this->cat_id == 0 ? '' : $this->cat_id,
            'brand_id'      => $this->brand_id,
            'prod_by_brand' => $prod_by_brand,
            'brand_logo_url' => $brand_logo_url,
            'brand_dscp'    => $brand_dscp,
            'brand_name'    => $brand_name,
            'query'         => $this->query_str,
        ) );

        if (!empty($cat_name)) {
            $T->set_var(array(
                'title'     => $cat_name,
                'cat_dscp'  => $cat_dscp,
                'cat_img_url' => $cat_img_url,
            ) );
        } else {
            $T->set_var('title', $LANG_SHOP['blocktitle']);
        }
        $T->set_var('have_sortby', true);

        $display .= $T->parse('', 'start');

        // Display each product
        $prodrows = 0;
        $T->set_block('wrapper', 'ProductItems', 'PI');
        foreach ($Products as $P) {
            // Don't display products if the viewer doesn't have access
            if (!$P->canDisplay()) {
                continue;
            }
            $P->setVariant();
            $link = $P->withQuery($this->query_str)->getLink();
            $prodrows++;
            $T->set_var(array(
                'item_id'       => $P->getID(),
                'name'          => htmlspecialchars($P->getName()),
                'short_description' => htmlspecialchars(PLG_replacetags($P->getShortDscp())),
                'encrypted'     => '',
                'item_url'      => $link,
                'aff_link'      => $P->getAffiliateLink(),
                'img_cell_width' => (Config::get('max_thumb_size') + 20),
                'track_onhand'  => $P->trackOnhand() ? 'true' : '',
                'has_discounts' => $P->hasDiscounts() ? 'true' : '',
                'price'         => $P->getDisplayPrice(),
                'orig_price'    => $P->getDisplayPrice($P->getBasePrice()),
                'on_sale'       => $P->isOnSale(),
                'small_pic'     => $P->getImage('', 200)['url'],
                'tpl_ver'       => Config::get('list_tpl_ver'),
                //'nonce'         => $Cart->makeNonce($P->getID() . $P->getName()),
                'can_add_cart'  => $P->canBuyNow(), // must have no attributes
                'rating_bar'    => $P->ratingBar(true),
                'oos'           => !$P->isInStock(),
            ) );
            if ($isAdmin) {
                $T->set_var(array(
                    'is_admin'  => 'true',
                    'pi_admin_url' => SHOP_ADMIN_URL,
                ) );
            }

            // Get the product buttons for the list
            $T->set_block('product', 'BtnBlock', 'Btn');
            if (
                !$P->hasOptions() &&
                !$P->hasCustomFields() &&
                !$P->hasSpecialFields()
            ) {
                // Buttons only show in the list if there are no options to select
                $buttons = $P->PurchaseLinks(Views::LIST);
                foreach ($buttons as $name=>$html) {
                    $T->set_var('button', $html);
                    $T->parse('Btn', 'BtnBlock', true);
                }
            } else {
                if (Config::get('ena_cart')) {
                    // If the product has attributes, then the cart must be
                    // enabled to allow purchasing
                    $button = $T->parse('', 'btn_details') . '&nbsp;';
                    $T->set_var('button', $button);
                    $T->parse('Btn', 'BtnBlock', true);
                }
            }

            $T->parse('PI', 'ProductItems', true);
            $T->clear_var('Btn');
        }

        // Get products from plugins.
        // For now, this hack shows plugins only on the first page, since
        // they're not included in the page calculation.
        if (
            Config::get('show_plugins') &&
            $page == 1 &&
            $this->brand_id == 0 &&
            ( $this->cat_id == 0 || $this->cat_id == $RootCat->getID()) &&
            empty($search)
        ) {
            // Clear out-of-stock flag which doesn't appply to plugins
            $T->clear_var('oos');
            $prodrows += $this->getPluginProducts($T);
        }

        //$T->parse('output', 'wrapper');
        $display .= $T->parse('', 'wrapper');

        if ($prodrows == 0 && COM_isAnonUser()) {
            $T->set_var('anon_and_empty', 'true');
        }

        $pagenav_args = empty($pagenav_args) ? '' : '?'.implode('&', $pagenav_args);
        // Display pagination
        if ($prod_per_page > 0 && $count > $prod_per_page) {
            $T->set_var(
                'pagination',
                COM_printPageNavigation(
                    SHOP_URL . '/index.php' . $pagenav_args,
                    $page,
                    ceil($count / $prod_per_page)
                )
            );
        } else {
            $T->set_var('pagination', '');
        }

        // Display a "not found" message if count == 0
        if ($prodrows == 0) {
            $T->set_var('no_rows', true);
        }

        // Show the category rules in the footer, if any.
        $notes = array();
        if ($Cat->getRuleId() > 0) {
            $have_rules = true;
            $Rule = ZoneRule::getInstance($Cat->getRuleId());
            $notes[] = $Rule->getDscp();

        }
        if (is_int($this->cat_id) && $this->cat_id > 0) {
            $Rules = ProductRule::getByCategory($Cat);
            if (!empty($Rules)) {
                foreach ($Rules as $Rule) {
                    $notes[] = $Rule->getDscp();
                }
                $T->set_var('rule_notes', '<li>' . implode('</li><li>', $notes) . '</li>');
            }
        }
        $display .= $T->parse('', 'end');
        return $display;
    }


    /**
     * Get plugin products for the catalog.
     * Shown on the homepage and if a plugin (non-integer) category is requested.
     *
     * @param   object  $T      Template object
     * @param   string  $pi_name    Plugin name, empty for all plugins
     * @return  integer     Number of plugin products added to the template
     */
    private function getPluginProducts(&$T, $pi_name='')
    {
        global $_PLUGINS, $_USER;

        $num_products = 0;

        // Get products from plugins.
        // For now, this hack shows plugins only on the first page, since
        // they're not included in the page calculation.
        if (!Config::get('show_plugins')) {
            return $num_products;
        }

        // Get the currency class for formatting prices
        $Cur = Currency::getInstance();
        $Cart = Cart::getInstance();

        if (!empty($pi_name)) {
            $plugins = array($pi_name);
        } else {
            $plugins = $_PLUGINS;
        }
        foreach ($plugins as $pi_name) {
            $plugin_data = array();
            $status = PLG_callFunctionForOnePlugin(
                'service_getproducts_' . $pi_name,
                array(
                    1 => array(),
                    2 => &$plugin_data,
                    3 => &$svc_msg,
                )
            );
            if ($status !== PLG_RET_OK || empty($plugin_data)) {
                continue;
            }

            foreach ($plugin_data as $A) {
                // Skip items that can't be shown
                if (isset($A['canDisplay']) && !$A['canDisplay']) {
                    continue;
                }

                $P = Product::getByID($A['id']);

                // Reset button values
                $buttons = '';
                if (!isset($A['buttons'])) {
                    $A['buttons'] = array();
                }
                if (
                    (isset($A['add_cart']) && !$A['add_cart']) ||
                    (isset($A['canPurchase']) && !$A['canPurchase'])
                ) {
                    $P->enablePurchase(false);
                }

                if ($P->isNew()) {
                    // An error in getting the plugin product
                    continue;
                }
                $link = $P->withQuery($this->query_str)->getLink();
                $price = $P->getPrice();
                $T->set_var(array(
                    'id'        => $P->getID(),     // required
                    'item_id'   => $P->getItemID(), // required
                    'name'      => $P->getDscp(),
                    'short_description' => $P->getDscp(),
                    'encrypted' => '',
                    'item_url'  => $link,
                    'track_onhand' => '',   // not available for plugins
                    'small_pic' => $P->getImage()['url'],
                    'on_sale'   => '',
                    //'nonce'     => $Cart->makeNonce($P->getID(). $P->getName()),
                    'can_add_cart'  => $P->canPurchase(),
                    'rating_bar' => $P->ratingBar(true),
                ) );
                if ($price > 0) {
                    $T->set_var('price', $Cur->Format($price));
                } else {
                    $T->clear_var('price');
                }

                // Skip button display if the item can't be purchased
                if (!isset($A['canPurchase']) || $A['canPurchase']) {
                    if ($price > 0 && $_USER['uid'] == 1 && !Config::get('anon_buy')) {
                        $buttons .= $T->set_var('', 'login_req') . '&nbsp;';
                    /*} elseif (
                        (!isset($A['prod_type']) || $A['prod_type'] > ProductType::PHYSICAL) &&
                        $A['price'] == 0
                    ) {
                        // Free items or items purchased and not expired, allow download.
                        $buttons .= $T->set_var('', 'download') . '&nbsp;';*/
                    } elseif (is_array($A['buttons'])) {
                        // Buttons for everyone else
                        $T->set_block('wrapper', 'BtnBlock', 'Btn');
                        foreach ($A['buttons'] as $type=>$html) {
                            $T->set_var('button', $html);
                            $T->parse('Btn', 'BtnBlock', true);
                        }
                    }
                }
                $T->clear_var('Btn');
                $T->parse('PI', 'ProductItems', true);
                $num_products++;
            }   // foreach plugin_data

        }   // foreach $plugins
        return $num_products;
    }


    /**
     * Display the shop home page as a collection of category tiles.
     *
     * @return  string  HTML for category homepage
     */
    public function Categories()
    {
        $display = '';

        $RootCat = Category::getRoot();
        // If showing only top-level categories then get the children of Root,
        // otherwise get the whole category tree.
        if ((Config::get('hp_layout') & self::HP_CATTOP) == self::HP_CATTOP) {
            $Cats = $RootCat->getChildren();
            if ((Config::get('hp_layout') & self::HP_CATHOME) == self::HP_CATHOME) {
                // Add the root category.
                array_unshift($Cats, $RootCat);
            }
        } else {
            $Cats = \Shop\Category::getTree();
            if ((Config::get('hp_layout') & self::HP_CATHOME) != self::HP_CATHOME) {
                // Not including the root category.
                unset($Cats[$RootCat->getID()]);
            }
        }

        $T = new Template(array(
            'list/' . Config::get('list_tpl_ver'),
            '',
        ) );
        $T->set_file(array(
            'wrapper'   => 'wrapper.thtml',
            'start'   => 'product_list_start.thtml',
            'end'     => 'product_list_end.thtml',
        ) );
        $T->set_var('pi_url', SHOP_URL);

        $T->set_block('wrapper', 'ProductItems', 'PI');
        foreach ($Cats as $Cat) {
            if (!$Cat->hasAccess() || !Category::hasProducts($Cat->getID())) {
                // Skip categories that have no products
                continue;
            }
            $T->set_var(array(
                'item_id'       => $Cat->getID(),
                'short_description' => htmlspecialchars($Cat->getName()),
                'img_cell_width' => (Config::get('max_thumb_size') + 20),
                'item_url'      => SHOP_URL . '/index.php?category='. $Cat->getID(),
                'small_pic'     => $Cat->getImage()['url'],
                'tpl_ver'       => Config::get('list_tpl_ver'),
            ) );
            $T->parse('PI', 'ProductItems', true);
        }
        $display .= $T->parse('', 'start');
        $display .= $T->parse('', 'wrapper');
        $display .= $T->parse('', 'end');
        return $display;
    }

}
