<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class to provide admin and user-facing menus.
 * @package shop
 */
class Menu
{
    /**
     * Create the user menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function User($view='')
    {
        global $_CONF, $LANG_SHOP, $_SHOP_CONF;

        USES_lib_admin();

        $hdr_txt = SHOP_getVar($LANG_SHOP, 'user_hdr_' . $view);
        $menu_arr = array(
            array(
                'url'  => SHOP_URL . '/index.php',
                'text' => $LANG_SHOP['back_to_catalog'],
            ),
        );

        $active = $view == 'orderhist' ? true : false;
        $menu_arr[] = array(
            'url'  => COM_buildUrl(SHOP_URL . '/account.php'),
            'text' => $LANG_SHOP['purchase_history'],
            'active' => $active,
        );

        // Show the Gift Cards menu item only if enabled.
        if ($_SHOP_CONF['gc_enabled']) {
            $active = $view == 'couponlog' ? true : false;
            $menu_arr[] = array(
                'url'  => COM_buildUrl(SHOP_URL . '/account.php?mode=couponlog'),
                'text' => $LANG_SHOP['gc_activity'],
                'active' => $active,
                'link_admin' => plugin_ismoderator_shop(),
            );
        }
        return \ADMIN_createMenu($menu_arr, $hdr_txt);
    }


    /**
     * Create the administrator menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function Admin($view='')
    {
        global $_CONF, $LANG_ADMIN, $LANG_SHOP, $_SHOP_CONF;

        USES_lib_admin();
        if (isset($LANG_SHOP['admin_hdr_' . $view]) &&
            !empty($LANG_SHOP['admin_hdr_' . $view])) {
            $hdr_txt = $LANG_SHOP['admin_hdr_' . $view];
        } else {
            $hdr_txt = '';
        }

        $menu_arr = array(
            array(
                'url' => SHOP_ADMIN_URL . '/index.php',
                'text' => $LANG_SHOP['catalog'],
                'active' => $view == 'products' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/index.php?orders',
                'text' => $LANG_SHOP['orders'],
                'active' => $view == 'orders' || $view == 'shipments' ? true : false,
            ),
            /*array(
                'url' => SHOP_ADMIN_URL . '/index.php?categories=x',
                'text' => $LANG_SHOP['categories'],
                'active' => $view == 'categories' ? true : false,
            ),*/
            /*array(
                'url'  => SHOP_ADMIN_URL . '/index.php?opt_grp=x',
                'text' => $LANG_SHOP['options'],
                'active' => $view == 'options' ? true : false,
            ),*/
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?shipping=x',
                'text' => $LANG_SHOP['shipping'],
                'active' => $view == 'shipping' ? true : false,
            ),
            /*array(
                'url'  => SHOP_ADMIN_URL . '/index.php?sales=x',
                'text' => $LANG_SHOP['sale_prices'],
                'active' => $view == 'sales' ? true : false,
            ),*/
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?gwadmin=x',
                'text' => $LANG_SHOP['gateways'],
                'active' => $view == 'gwadmin' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?wfadmin=x',
                'text' => $LANG_SHOP['mnu_wfadmin'],
                'active' => $view == 'wfadmin' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?other=x',
                'text' => $LANG_SHOP['other_func'],
                'active' => $view == 'other' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/report.php',
                'text' => $LANG_SHOP['reports'],
                'active' => $view == 'reports' ? true : false,
            ),
        );
        if ($_SHOP_CONF['gc_enabled']) {
            // Show the Coupons menu option only if enabled
            $menu_arr[] = array(
                'url'  => SHOP_ADMIN_URL . '/index.php?coupons=x',
                'text' => $LANG_SHOP['coupons'],
                'active' => $view == 'coupons' ? true : false,
            );
        }
        $menu_arr[] = array(
            'url'  => $_CONF['site_admin_url'],
            'text' => $LANG_ADMIN['admin_home'],
        );

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('title', 'shop_title.thtml');
        $T->set_var(array(
            'title' => $LANG_SHOP['admin_title'] . ' (' . $_SHOP_CONF['pi_version'] . ')',
            'link_store' => true,
            'icon'  => plugin_geticon_shop(),
            'is_admin' => true,
            'link_catalog' => true,
        ) );
        $todo_arr = self::AdminTodo();
        if (!empty($todo_arr)) {
            $todo_list = '';
            foreach ($todo_arr as $item_todo) {
                $todo_list .= "<li>$item_todo</li>" . LB;
            }
            $T->set_var('todo', '<ul>' . $todo_list . '</ul>');
        }
        $retval = $T->parse('', 'title');
        $retval .= \ADMIN_createMenu(
            $menu_arr,
            $hdr_txt,
            plugin_geticon_shop()
        );
        return $retval;
    }


    /**
     * Create the administrator sub-menu for the Catalog option.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminCatalog($view='')
    {
        global $LANG_SHOP;

        $menu_arr = array(
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?products=x',
                'text' => $LANG_SHOP['products'],
                'active' => $view == 'products' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/index.php?categories=x',
                'text' => $LANG_SHOP['categories'],
                'active' => $view == 'categories' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?opt_grp=x',
                'text' => $LANG_SHOP['opt_grps'],
                'active' => $view == 'opt_grp' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?options=x',
                'text' => $LANG_SHOP['options'],
                'active' => $view == 'options' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?sales=x',
                'text' => $LANG_SHOP['sale_prices'],
                'active' => $view == 'sales' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Create the administrator sub-menu for the Shipping option.
     * Includes shipper setup and shipment listing.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminShipping($view='')
    {
        global $LANG_SHOP;

        $menu_arr = array(
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?shipping=x',
                'text' => $LANG_SHOP['shippers'],
                'active' => $view == 'shipping' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/index.php?shipments=x',
                'text' => $LANG_SHOP['shipments'],
                'active' => $view == 'shipments' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Create the administrator sub-menu for the Orders option.
     * Includes orders and shipment listing.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminOrders($view='')
    {
        global $LANG_SHOP;

        $menu_arr = array(
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?orders',
                'text' => $LANG_SHOP['orders'],
                'active' => $view == 'orders' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/index.php?shipments=x',
                'text' => $LANG_SHOP['shipments'],
                'active' => $view == 'shipments' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Show the title and submenu when viewing order info.
     *
     * @param   string  $view   View option selected
     * @param   string  $order_id   Order ID being viewed
     * @return  string      HTML for title and menu options
     */
    public static function viewOrder($view, $Order)
    {
        global $LANG_SHOP;

        $retval = COM_startBlock(
            $LANG_SHOP['order'] . ' ' . $Order->order_id, '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $menu_arr = array(
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?order=' . $Order->order_id,
                'text' => $LANG_SHOP['order'],
                'active' => $view == 'order' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/index.php?shipments=' . $Order->order_id,
                'text' => $LANG_SHOP['shipments'],
                'active' => $view == 'shipments' ? true : false,
            ),
        );
        $retval .= self::_makeSubMenu($menu_arr);
        $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $retval;
    }


    /**
     * Create a submenu using a standard template.
     *
     * @param   array   $menu_arr   Array of menu items
     * @return  string      HTML for the submenu
     */
    private static function _makeSubMenu($menu_arr)
    {
        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('menu', 'submenu.thtml');
        $T->set_block('menu', 'menuItems', 'items');
        foreach ($menu_arr as $mnu) {
            $url = COM_createLink($mnu['text'], $mnu['url']);
            $T->set_var(array(
                'active'    => $mnu['active'],
                'url'       => $url,
            ) );
            $T->parse('items', 'menuItems', true);
        }
        $T->parse('output', 'menu');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Get the to-do list to display at the top of the admin screen.
     * There's probably a less sql-expensive way to do this.
     *
     * @return  array   Array of strings (the to-do list)
     */
    public static function AdminTodo()
    {
        global $_TABLES, $LANG_SHOP, $_PLUGIN_INFO;

        $todo = array();
        if (DB_count($_TABLES['shop.products']) == 0) {
            $todo[] = $LANG_SHOP['todo_noproducts'];
        }

        if (DB_count($_TABLES['shop.gateways'], 'enabled', 1) == 0) {
            $todo[] = $LANG_SHOP['todo_nogateways'];
        }
        if (!empty($todo) && array_key_exists('paypal', $_PLUGIN_INFO)) {
            $todo[] = $LANG_SHOP['todo_migrate_pp'];
        }
        return $todo;
    }


    /**
     * Display only the page title.
     * Used for pages that do not feature a menu, such as the catalog.
     *
     * @param   string  $page_title     Page title text
     * @param   string  $page           Page name being displayed
     * @return  string      HTML for page title section
     */
    public static function pageTitle($page_title = '', $page='')
    {
        global $_USER;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('title', 'shop_title.thtml');
        $T->set_var(array(
            'title' => $page_title,
            'is_admin' => plugin_ismoderator_shop(),
            'link_admin' => plugin_ismoderator_shop(),
            'link_account' => ($page != 'account' && $_USER['uid'] > 1),
        ) );
        if ($page != 'cart' && Cart::getCart()) {
            $item_count = Cart::getInstance()->hasItems();
            if ($item_count) {
                $T->set_var('link_cart', $item_count);
            }
        }
        return $T->parse('', 'title');
    }


    /**
     * Display the site header, with or without blocks according to configuration.
     *
     * @param   string  $title  Title to put in header
     * @param   string  $meta   Optional header code
     * @return  string          HTML for site header, from COM_siteHeader()
     */
    public static function siteHeader($title='', $meta='')
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $retval = '';

        switch($_SHOP_CONF['displayblocks']) {
        case 2:     // right only
        case 0:     // none
            $retval .= COM_siteHeader('none', $title, $meta);
            break;

        case 1:     // left only
        case 3:     // both
        default :
            $retval .= COM_siteHeader('menu', $title, $meta);
            break;
        }

        if (!$_SHOP_CONF['shop_enabled']) {
            $retval .= '<div class="uk-alert uk-alert-danger">' . $LANG_SHOP['shop_closed'] . '</div>';
        }
        return $retval;
    }


    /**
     * Display the site footer, with or without blocks as configured.
     *
     * @return  string      HTML for site footer, from COM_siteFooter()
     */
    public static function siteFooter()
    {
        global $_SHOP_CONF;

        $retval = '';

        switch($_SHOP_CONF['displayblocks']) {
        case 2 : // right only
        case 3 : // left and right
            $retval .= COM_siteFooter();
            break;

        case 0: // none
        case 1: // left only
        default :
            $retval .= COM_siteFooter();
            break;
        }
        return $retval;
    }

}

?>


