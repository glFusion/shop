<?php

namespace Shop;

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

        if (isset($LANG_SHOP['admin_hdr_' . $view]) &&
            !empty($LANG_SHOP['admin_hdr_' . $view])) {
            $hdr_txt = $LANG_SHOP['admin_hdr_' . $view];
        } else {
            $hdr_txt = '';
        }

        $menu_arr = array(
            array(
                'url' => SHOP_ADMIN_URL . '/index.php',
                'text' => $LANG_SHOP['products'],
                'active' => $view == 'products' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/index.php?categories=x',
                'text' => $LANG_SHOP['categories'],
                'active' => $view == 'categories' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?attributes=x',
                'text' => $LANG_SHOP['attributes'],
                'active' => $view == 'attributes' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?shipping=x',
                'text' => $LANG_SHOP['shipping'],
                'active' => $view == 'shipping' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?sales=x',
                'text' => $LANG_SHOP['sale_prices'],
                'active' => $view == 'sales' ? true : false,
            ),
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

        $T = SHOP_getTemplate('shop_title', 'title');
        $T->set_var(array(
            'title' => $LANG_SHOP['admin_title'] . ' (' . $_SHOP_CONF['pi_version'] . ')',
            'icon'  => plugin_geticon_shop(),
            'is_admin' => true,
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
     * Get the to-do list to display at the top of the admin screen.
     * There's probably a less sql-expensive way to do this.
     *
     * @return  array   Array of strings (the to-do list)
     */
    public static function AdminTodo()
    {
        global $_TABLES, $LANG_SHOP;

        $todo = array();
        if (DB_count($_TABLES['shop.products']) == 0) {
            $todo[] = $LANG_SHOP['todo_noproducts'];
        }

        if (DB_count($_TABLES['shop.gateways'], 'enabled', 1) == 0) {
            $todo[] = $LANG_SHOP['todo_nogateways'];
        }

        return $todo;
    }


    public static function PageTitle($page_title = '')
    {
        $T = SHOP_getTemplate('shop_title', 'title');
        $T->set_var(array(
            'title' => $page_title,
            'is_admin' => plugin_ismoderator_shop(),
        ) );
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


