<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Template;
use Shop\Cart;


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
        global $_CONF, $LANG_SHOP, $_USER;

        USES_lib_admin();

        if (isset($LANG_SHOP['user_hdr_' . $view])) {
            $hdr_txt = $LANG_SHOP['user_hdr_' . $view];
        } else {
            $hdr_txt = '';
        }
        $menu_arr = array(
            array(
                'url'  => SHOP_URL . '/index.php',
                'text' => $LANG_SHOP['back_to_catalog'],
            ),
            array(
                'url'  => COM_buildUrl(SHOP_URL . '/account.php?mode=orderhist'),
                'text' => $LANG_SHOP['purchase_history'],
                'active' => $view == 'orderhist' ? true : false,
            ),
            array(
                'url' => COM_buildUrl(SHOP_URL . '/account.php?mode=addresses'),
                'text' => $LANG_SHOP['addresses'],
                'active' => $view == 'addresses' ? true : false,
            ),
        );

        // Show the Gift Cards menu item only if enabled.
        if (Config::get('gc_enabled')) {
            $active = $view == 'couponlog' ? true : false;
            $menu_arr[] = array(
                'url'  => COM_buildUrl(SHOP_URL . '/account.php?mode=couponlog'),
                'text' => $LANG_SHOP['gc_activity'],
                'active' => $active,
                'link_admin' => plugin_ismoderator_shop(),
            );
        }

        // Show the Affiliate Sales item only if enabled.
        if (Config::get('aff_enabled')) {
            $Aff = new Affiliate($_USER['uid']);
            if ($Aff->isValid()) {
                $menu_arr[] = array(
                    'url' => COM_buildUrl(SHOP_URL . '/affiliate.php'),
                    'text' => $LANG_SHOP['affiliates'],
                    'active' => $view == 'affiliate' ? true : false,
                );
            }
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
        global $_CONF, $LANG_ADMIN, $LANG_SHOP;

        USES_lib_admin();
        if (isset($LANG_SHOP['admin_hdr_' . $view]) &&
            !empty($LANG_SHOP['admin_hdr_' . $view])) {
            $hdr_txt = $LANG_SHOP['admin_hdr_' . $view];
        } else {
            $hdr_txt = '';
        }

        $menu_arr = array(
            array(
                'url' => SHOP_ADMIN_URL . '/index.php?products',
                'text' => $LANG_SHOP['catalog'],
                'active' => $view == 'products' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/orders.php',
                'text' => $LANG_SHOP['orders'],
                'active' => $view == 'orders' || $view == 'shipments' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?shipping=x',
                'text' => $LANG_SHOP['shipping'],
                'active' => $view == 'shipping' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/gateways.php',
                'text' => $LANG_SHOP['gateways'],
                'active' => $view == 'gwadmin' ? true : false,
            ),
            /*array(
                'url'  => SHOP_ADMIN_URL . '/index.php?wfadmin=x',
                'text' => $LANG_SHOP['mnu_wfadmin'],
                'active' => $view == 'wfadmin' ? true : false,
            ),*/
            array(
                'url'  => SHOP_ADMIN_URL . '/report.php',
                'text' => $LANG_SHOP['reports'],
                'active' => $view == 'reports' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/rules.php',
                'text' => $LANG_SHOP['rules'],
                'active' => $view == 'rules' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?other=x',
                'text' => $LANG_SHOP['other_func'],
                'active' => $view == 'other' ? true : false,
            ),
        );
        if (Config::get('aff_enabled')) {
            $menu_arr[] = array(
                'url' => SHOP_ADMIN_URL . '/affiliates.php',
                'text' => $LANG_SHOP['affiliates'],
                'active' => $view == 'affiliates' ? true : false,
            );
        }
        $menu_arr[] = array(
            'url'  => $_CONF['site_admin_url'],
            'text' => $LANG_ADMIN['admin_home'],
        );

        $T = new Template;
        $T->set_file('title', 'shop_title.thtml');
        $T->set_var(array(
            'title' => $LANG_SHOP['admin_title'] . ' (' . Config::get('pi_version') . ')',
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
     * Create the administrator sub-menu for the Region option.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminRules($view='')
    {
        global $LANG_SHOP;

        $menu_arr = array(
            array(
                'url'  => SHOP_ADMIN_URL . '/rules.php?pr_list',
                'text' => $LANG_SHOP['product_rules'],
                'active' => $view == 'pr_list' ? true : false,
                'help' => $LANG_SHOP['adm_mnu_pr'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/rules.php?zr_list',
                'text' => $LANG_SHOP['zone_rules'],
                'active' => $view == 'zr_list' ? true : false,
                'help' => $LANG_SHOP['adm_mnu_zr'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/regions.php?regions',
                'text' => $LANG_SHOP['regions'],
                'active' => $view == 'regions' ? true : false,
                'help' => $LANG_SHOP['adm_mnu_region'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/regions.php?countries',
                'text' => $LANG_SHOP['countries'],
                'active' => $view == 'countries' ? true : false,
                'help' => $LANG_SHOP['adm_mnu_region'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/regions.php?states',
                'text' => $LANG_SHOP['states'],
                'active' => $view == 'states' ? true : false,
                'help' => $LANG_SHOP['adm_mnu_states'],
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Create the administrator sub-menu for the Catalog option.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminCatalog($view='')
    {
        global $LANG_SHOP, $LANG_SHOP_HELP, $LANG01;

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
                'help' => $LANG_SHOP_HELP['opt_groups'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?options=x',
                'text' => $LANG_SHOP['options'],
                'active' => $view == 'options' ? true : false,
                'help' => $LANG_SHOP_HELP['options'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?variants=x',
                'text' => $LANG_SHOP['variants'],
                'active' => $view == 'variants' ? true : false,
                'help' => $LANG_SHOP_HELP['variants'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?sales=x',
                'text' => $LANG_SHOP['sale_prices'],
                'active' => $view == 'sales' ? true : false,
                'help' => $LANG_SHOP_HELP['sale_prices'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?codes=x',
                'text' => $LANG_SHOP['codes'],
                'active' => $view == 'codes' ? true : false,
                'help' => $LANG_SHOP_HELP['discount_codes'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?suppliers',
                'text' => $LANG_SHOP['suppliers'],
                'active' => $view == 'suppliers' ? true : false,
                'help' => $LANG_SHOP_HELP['suppliers'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?features',
                'text' => $LANG_SHOP['features'],
                'active' => $view == 'features' ? true : false,
                'help' => $LANG_SHOP_HELP['features'],
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?pi_products',
                'text' => $LANG01[77],
                'active' => $view == 'pi_products' ? true : false,
                'help' => $LANG_SHOP_HELP['pi_products'],
            ),
        );
        if (Config::get('gc_enabled')) {
            // Show the Coupons menu option only if enabled
            $menu_arr[] = array(
                'url'  => SHOP_ADMIN_URL . '/index.php?coupons=x',
                'text' => $LANG_SHOP['coupons'],
                'active' => $view == 'coupons' ? true : false,
                'help' => $LANG_SHOP_HELP['coupons'],
            );
        }
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
        global $LANG_SHOP, $LANG_SHOP_HELP;

        $menu_arr = array(
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?shipping=x',
                'text' => $LANG_SHOP['shippers'],
                'active' => $view == 'shipping' ? true : false,
                'help'  => $LANG_SHOP_HELP['shipping_methods'],
            ),
            /*array(
                'url' => SHOP_ADMIN_URL . '/index.php?shipments=x',
                'text' => $LANG_SHOP['shipments'],
                'active' => $view == 'shipments' ? true : false,
            ),*/
            array(
                'url'   => SHOP_ADMIN_URL . '/index.php?carriers=x',
                'text'  => $LANG_SHOP['carriers'],
                'active' => $view == 'carriers' ? true : false,
                'help'  => $LANG_SHOP_HELP['carrier_modules'],
            ),
            array(
                'url'   => SHOP_ADMIN_URL . '/packages.php',
                'text'  => $LANG_SHOP['packages'],
                'active' => $view == 'packages' ? true : false,
                'help'  => $LANG_SHOP_HELP['packages'],
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
                'url'  => SHOP_ADMIN_URL . '/orders.php',
                'text' => $LANG_SHOP['orders'],
                'active' => $view == 'orders' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/shipments.php',
                'text' => $LANG_SHOP['shipments'],
                'active' => $view == 'shipments' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/payments.php?payments',
                'text' => $LANG_SHOP['payments'],
                'active' => $view == 'payments' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/payments.php?webhooks',
                'text' => $LANG_SHOP['webhooks'],
                'active' => $view == 'webhooks' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Show the title and submenu when viewing order info.
     *
     * @param   string  $view   View option selected
     * @param   string  $Order  Order object currently being viewed
     * @return  string      HTML for title and menu options
     */
    public static function viewOrder($view, $Order)
    {
        global $LANG_SHOP;

        $retval = '';
        $menu_arr = array(
            array(
                'url'  => SHOP_ADMIN_URL . '/orders.php?order=' . $Order->getOrderID(),
                'text' => $LANG_SHOP['order'],
                'active' => $view == 'order' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/shipments.php?shipments=' . $Order->getOrderID(),
                'text' => $LANG_SHOP['shipments'],
                'active' => $view == 'shipments' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/payments.php?payments=' . $Order->getOrderID(),
                'text' => $LANG_SHOP['payments'],
                'active' => $view == 'payments' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/payments.php?webhooks=' . $Order->getOrderID(),
                'text' => $LANG_SHOP['webhooks'],
                'active' => $view == 'webhooks' ? true : false,
            ),
        );
        $retval .= self::_makeSubMenu($menu_arr);
        /*$retval .= COM_startBlock(
            $LANG_SHOP['order'] . ' ' . $Order->getOrderID(), '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));*/
        return $retval;
    }


    /**
     * Create the administrator sub-menu for the Affiliate management.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminAffiliates($view='')
    {
        global $LANG_SHOP, $LANG_SHOP_HELP;

        $menu_arr = array(
            array(
                'url'  => SHOP_ADMIN_URL . '/affiliates.php',
                'text' => $LANG_SHOP['all'],
                'active' => $view == 'affiliates' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/affiliates.php?payout',
                'text' => $LANG_SHOP['pending_payout'],
                'active' => $view == 'payout' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Create a submenu using a standard template.
     *
     * @param   array   $menu_arr   Array of menu items
     * @return  string      HTML for the submenu
     */
    private static function _makeSubMenu($menu_arr)
    {
        $T = new Template;
        $T->set_file('menu', 'submenu.thtml');
        $T->set_block('menu', 'menuItems', 'items');
        $hlp = '';
        foreach ($menu_arr as $mnu) {
            if ($mnu['active'] && isset($mnu['help'])) {
                $hlp = $mnu['help'];
            }
            $url = COM_createLink($mnu['text'], $mnu['url']);
            $T->set_var(array(
                'active'    => $mnu['active'],
                'url'       => $url,
            ) );
            $T->parse('items', 'menuItems', true);
        }
        $T->set_var('help', $hlp);
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
        if (MigratePP::canMigrate()) {
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

        $T = new Template;
        $T->set_file('title', 'shop_title.thtml');
        $T->set_var(array(
            'title' => $page_title,
            'is_admin' => plugin_ismoderator_shop(),
            'link_admin' => plugin_ismoderator_shop(),
            'link_account' => ($page != 'account' && $_USER['uid'] > 1),
        ) );
        if ($page != 'cart' && Cart::getCartID()) {
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
        global $LANG_SHOP;

        $retval = '';
        if ($title == '') {
            $title = $LANG_SHOP['main_title'];
        }

        switch(Config::get('displayblocks')) {
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

        if (!Config::get('shop_enabled')) {
            $retval .= SHOP_errorMessage($LANG_SHOP['shop_closed'], 'danger');
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
        $retval = '';

        switch(Config::get('displayblocks')) {
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


    /**
     * Show the submenu for the checkout workflow.
     *
     * @param   object  $Cart   Cart object, to see what steps are needed
     * @param   string  $step   Current step name
     * @return  string      HTML for workflow menu
     */
    public static function checkoutFlow(Cart $Cart, string $step = 'viewcart') : string
    {
        $Flows = Workflow::getAll();
        $flow_count = 0;
        $T = new Template('workflow/');
        $T->set_file('menu', 'menu.thtml');
        $T->set_block('menu', 'Flows', 'Flow');
        foreach ($Flows as $Flow) {
            if (!$Flow->isNeeded($Cart)) {
                // Skip unneeded flows, such as shipping for virtual goods.
                continue;
            }
            $flow_count++;
            $T->set_var(array(
                'mnu_cls' => 'completed',
                'wf_name' => $Flow->getName(),
                'wf_title' => $Flow->getTitle(),
                'is_done' => $Flow->isSatisfied($Cart) ? 1 : 0,
                'is_active' => $Flow->getName() == $step ? 1 : 0,
                'current_wf' => $step,
            ) );
            $T->parse('Flow', 'Flows', true);
        }
        $T->set_var(array(
            'wrap_form' => $step != 'confirm',
            'flow_count' => $flow_count,
            'pi_url' => Config::get('url'),
        ) );
        $T->parse('output', 'menu');
        return $T->finish($T->get_var('output'));
    }

}
