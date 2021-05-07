<?php
/**
 * Class to render the catalog view.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Models\ProductType;
use Shop\Models\Views;


/**
 * Class to display the product catalog.
 * @package shop
 */
class Catalog
{
    /** Homepage layout - product list (default).
     * @const integer */
    public const HP_PRODUCT = 1;

    /** Homepage layout - category list, all categories.
     * @const integer */
    public const HP_CAT = 2;

    /** Homepage layout - category list, all categories including.
     * @const integer */
    public const HP_CATHOME = 4;

    /** Homepage layout - category list, top-level only, no root.
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
    public function setCatID($cat_id)
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
    public function withQuery($query)
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
        global $_SHOP_CONF, $LANG_SHOP;

        // Check access here in case centerblock is enabled.
        // This will cause a 404 for the homepage, it's up to the admin
        // to fix this.
        if (!SHOP_access_check()) {
            COM_404();
        }

        $content = '';
        if (
            ($_SHOP_CONF['hp_layout'] & self::HP_CAT) == self::HP_CAT &&
            $this->cat_id == 0 &&
            empty($this->query)
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
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_USER, $_PLUGINS;

        $isAdmin = plugin_ismoderator_shop() ? true : false;

        // Create product template
        if (empty($_SHOP_CONF['list_tpl_ver'])) $_SHOP_CONF['list_tpl_ver'] = 'v1';
        $T = new Template(array(
            'list/' . $_SHOP_CONF['list_tpl_ver'],
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

        $cat_name = '';
        $cat_img_url = '';
        $display = '';
        $cat_sql = '';
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
            $cat_desc = $Cat->getDscp();
            $cat_img_url = $Cat->getImage()['url'];
            if ($Cat->getParentID() > 0) {
                // Get the sql to limit by category
                $tmp = Category::getTree($Cat->getID());
                $cats = array();
                foreach ($tmp as $xcat_id=>$info) {
                    $cats[] = $xcat_id;
                }
                if (!empty($cats)) {
                    $cat_sql = implode(',', $cats);
                    $cat_sql = " AND c.cat_id IN ($cat_sql)";
                }
            }
            $brand_logo_url = '';
            $prod_by_brand = '';
            $brand_name = '';
            $brand_dscp = '';
        } else {
            $Sup = Supplier::getInstance($this->brand_id);
            if ($Sup->getID() > 0) {
                // Just borrow $cat_sql for this limit
                $cat_sql = " AND p.brand_id = {$Sup->getID()}";
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

        // Now get categories from plugins
        /*foreach ($_PLUGINS as $pi_name) {
            $pi_cats = PLG_callFunctionForOnePlugin('plugin_shop_getcategories_' . $pi_name);
            if (is_array($pi_cats) && !empty($pi_cats)) {
                foreach ($pi_cats as $data) {
                    $A[] = $data;
                }
            }
        }*/
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

        /*
         * Create the product sort selector
         */
        if (isset($_REQUEST['sortby'])) {
            $sortby = $_REQUEST['sortby'];
        } else {
            $sortby = SHOP_getVar($_SHOP_CONF, 'order', 'string', 'name');
        }
        switch ($sortby){
        case 'price_l2h':   // price, low to high
            $sql_sortby = 'price ASC';
            break;
        case 'price_h2l':   // price, high to low
            $sql_sortby = 'price DESC';
            break;
        case 'top_rated':
            $sql_sortby = 'rating DESC, votes DESC';
            break;
        case 'newest':
            $sql_sortby = 'dt_add DESC';
            break;
        case 'name':
        default:
            $sortby = 'name';
            $sql_sortby = 'short_description ASC';
            break;
        }
        $sortby_options = '';
        foreach ($LANG_SHOP['list_sort_options'] as $value=>$text) {
            $sel = $value == $sortby ? ' selected="selected"' : '';
            $sortby_options .= "<option value=\"$value\" $sel>$text</option>\n";
        }

        // Get products from database. "c.enabled is null" is to allow products
        // with no category defined
        $today = $_CONF['_now']->format('Y-m-d', true);
        $sql = " FROM {$_TABLES['shop.categories']} c
            INNER JOIN {$_TABLES['shop.prodXcat']} x
                ON x.cat_id = c.cat_id
            INNER JOIN {$_TABLES['shop.products']} p
                ON p.id = x.product_id
                WHERE p.enabled=1
                AND p.avail_beg <= '$today' AND p.avail_end >= '$today'
                AND (
                    (c.enabled=1 " . SEC_buildAccessSql('AND', 'c.grp_access') . ")
                    OR c.enabled IS NULL
                    )
                AND (
                    p.track_onhand = 0 OR p.onhand > 0 OR p.oversell < 2
                    ) $cat_sql";

        $search = '';
        // Add search query, if any
        if (!empty($this->query_str)) {
            $search = DB_escapeString($this->query_str);
            $fields = array(
                'p.name', 'c.cat_name', 'p.short_description', 'p.description',
                'p.keywords',
            );
            $srches = array();
            foreach ($fields as $fname) {
                $srches[] = "$fname like '%$search%'";
            }
            $srch = ' AND (' . implode(' OR ', $srches) . ')';
            $sql .= $srch;
        }
        $pagenav_args = array();
        if ($this->cat_id > 0) {
            $pagenav_args[] = 'category=' . $this->cat_id;
        }

        // If applicable, order by
        $sql .= " ORDER BY $sql_sortby";
        $sql_key = md5($sql);
        //echo $sql;die;

        // Count products from database
        $cache_key = Cache::makeKey('prod_cnt_' . $sql_key);
        $count = Cache::get($cache_key);
        if ($count === NULL) {
            $res = DB_query('SELECT DISTINCT p.id ' . $sql);
            $count = DB_numRows($res);
            Cache::set($cache_key, $count, array('products', 'categories'));
        }

        // If applicable, handle pagination of query
        $prod_per_page = SHOP_getVar($_SHOP_CONF, 'prod_per_page', 'integer', 20);
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
                $sql .= " LIMIT $start_limit, $prod_per_page";
            }
        }

        // Re-execute query with the limit clause in place
        $sql_key = md5($sql);
        $cache_key = Cache::makeKey('prod_list_' . $sql_key);
        $Products = Cache::get($cache_key);
        if ($Products === NULL) {
            $res = DB_query('SELECT DISTINCT p.id, p.short_description ' . $sql);
            $Products = array();
            while ($A = DB_fetchArray($res, false)) {
                $Products[] = Product::getById($A['id']);
            }
            Cache::set($cache_key, $Products, array('products', 'categories'));
        }

        // Create product template
        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            //'user_id'       => $_USER['uid'],
            'currency'      => $_SHOP_CONF['currency'],
            'breadcrumbs'   => $this->cat_id > 0 ? $Cat->Breadcrumbs() : '',
            'search_text'   => $search,
            'tpl_ver'       => $_SHOP_CONF['list_tpl_ver'],
            'sortby_options' => $sortby_options,
            'sortby'        => $sortby,
            'table_columns' => $_SHOP_CONF['catalog_columns'],
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
                'cat_desc'  => $cat_desc,
                'cat_img_url' => $cat_img_url,
            ) );
        } else {
            $T->set_var('title', $LANG_SHOP['blocktitle']);
        }
        $T->set_var('have_sortby', true);

        $display .= $T->parse('', 'start');

        if ($_SHOP_CONF['ena_ratings'] == 1) {
            $SHOP_ratedIds = SHOP_getRatedIds($_SHOP_CONF['pi_name']);
        }

        // Display each product
        $prodrows = 0;
        $T->set_block('wrapper', 'ProductItems', 'PI');
        foreach ($Products as $P) {
            // Don't display products if the viewer doesn't have access
            if (!$P->canDisplay()) {
                continue;
            }
            $P->setVariant();

            $prodrows++;
            $T->set_var(array(
                'item_id'       => $P->getID(),
                'name'          => htmlspecialchars($P->getName()),
                'short_description' => htmlspecialchars(PLG_replacetags($P->getShortDscp())),
                'img_cell_width' => ($_SHOP_CONF['max_thumb_size'] + 20),
                'encrypted'     => '',
                'item_url'      => $P->getLink(0, $this->query_str),
                'img_cell_width' => ($_SHOP_CONF['max_thumb_size'] + 20),
                'track_onhand'  => $P->trackOnhand() ? 'true' : '',
                'has_discounts' => $P->hasDiscounts() ? 'true' : '',
                'price'         => $P->getDisplayPrice(),
                'orig_price'    => $P->getDisplayPrice($P->getBasePrice()),
                'on_sale'       => $P->isOnSale(),
                'small_pic'     => $P->getImage('', 200)['url'],
                'tpl_ver'       => $_SHOP_CONF['list_tpl_ver'],
                'nonce'         => $Cart->makeNonce($P->getID() . $P->getName()),
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
                if ($_SHOP_CONF['ena_cart']) {
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
            $_SHOP_CONF['show_plugins']&&
            $page == 1 &&
            $this->brand_id == 0 &&
            ( $this->cat_id == 0 || $this->cat_id == $RootCat->getID()) &&
            empty($search)
        ) {
            // Clear out-of-stock flag which doesn't appply to plugins
            $T->clear_var('oos');
            $this->getPluginProducts($T);
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

        $display .= $T->parse('', 'end');
        return $display;
    }


    /**
     * Get plugin products for the catalog.
     * Shown on the homepage and if a plugin (non-integer) category is requested.
     *
     * @param   object  $T      Template object
     * @param   string  $pi_name    Plugin name, empty for all plugins
     */
    private function getPluginProducts(&$T, $pi_name='')
    {
        global $_SHOP_CONF, $_PLUGINS, $_USER;

        // Get products from plugins.
        // For now, this hack shows plugins only on the first page, since
        // they're not included in the page calculation.
        if (!$_SHOP_CONF['show_plugins']) {
            return;
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
            $status = LGLIB_invokeService(
                $pi_name,
                'getproducts',
                array(),
                $plugin_data,
                $svc_msg
            );
            if ($status != PLG_RET_OK || empty($plugin_data)) {
                continue;
            }

            foreach ($plugin_data as $A) {
                // Skip items that can't be shown
                if (isset($A['canDisplay']) && !$A['canDisplay']) {
                    continue;
                }

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
                    //$can_add_cart = false;
                //} else {
                  //  $can_add_cart = true;
                }

                $P = \Shop\Product::getByID($A['id']);
                if ($P->isNew()) {
                    // An error in getting the plugin product
                    continue;
                }
                $price = $P->getPrice();
                $T->set_var(array(
                    'id'        => $P->getID(),     // required
                    'item_id'   => $P->getItemID(), // required
                    'name'      => $P->getDscp(),
                    'short_description' => $P->getDscp(),
                    'encrypted' => '',
                    'item_url'  => $P->getLink(0, $this->query_str),
                    'track_onhand' => '',   // not available for plugins
                    'small_pic' => $P->getImage()['url'],
                    'on_sale'   => '',
                    'nonce'     => $Cart->makeNonce($P->getID(). $P->getName()),
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
                    if ($price > 0 && $_USER['uid'] == 1 && !$_SHOP_CONF['anon_buy']) {
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
                //$prodrows++;
                $T->parse('PI', 'ProductItems', true);
            }   // foreach plugin_data

        }   // foreach $plugins
    }


    /**
     * Display the shop home page as a collection of category tiles.
     *
     * @return  string  HTML for category homepage
     */
    public function Categories()
    {
        global $_SHOP_CONF;

        $display = '';
        $cat_sql = '';

        $RootCat = Category::getRoot();
        // If showing only top-level categories then get the children of Root,
        // otherwise get the whole category tree.
        if (($_SHOP_CONF['hp_layout'] & self::HP_CATTOP) == self::HP_CATTOP) {
            $Cats = $RootCat->getChildren();
            if (($_SHOP_CONF['hp_layout'] & self::HP_CATHOME) == self::HP_CATHOME) {
                array_unshift($Cats, $RootCat);
            }
        } else {
            $Cats = \Shop\Category::getTree();
            if (($_SHOP_CONF['hp_layout'] & self::HP_CATHOME) != self::HP_CATHOME) {
                unset($Cats[$RootCat->cat_id]);
            }
        }

        $T = new Template(array(
            'list/' . $_SHOP_CONF['list_tpl_ver'],
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
                'img_cell_width' => ($_SHOP_CONF['max_thumb_size'] + 20),
                'item_url'      => SHOP_URL . '/index.php?category='. $Cat->getID(),
                'small_pic'     => $Cat->getImage()['url'],
                'tpl_ver'       => $_SHOP_CONF['list_tpl_ver'],
            ) );
            $T->parse('PI', 'ProductItems', true);
            //var_dump($T);die;
        }
        $display .= $T->parse('', 'start');
        $display .= $T->parse('', 'wrapper');
        $display .= $T->parse('', 'end');
        return $display;
    }

}

?>
