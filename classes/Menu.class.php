<?php

namespace Shop;

class Menu
{
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

        /*$menu_arr = array();
        foreach ($LANG_SHOP['adminmenus'] as $key) {
            $menu_arr[] = array(
                'url' => SHOP_ADMIN_URL . '/index.php?' . $key,
                'text' => $LANG_SHOP[$key],
                'active' => $view == $key ? true : false,
            );
        }*/
        $menu_arr = array(
            array(
                'url' => SHOP_ADMIN_URL . '/index.php',
                'text' => $LANG_SHOP['product_list'],
                'active' => $view == 'productlist' ? true : false,
            ),
            array(
                'url' => SHOP_ADMIN_URL . '/index.php?catlist=x',
                'text' => $LANG_SHOP['category_list'],
                'active' => $view == 'catlist' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?attributes=x',
                'text' => $LANG_SHOP['attr_list'],
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
            /*array(
                'url'  => SHOP_ADMIN_URL . '/index.php?orderhist=x',
                'text' => $LANG_SHOP['purchase_history'],
                'active' => $view == 'orderhist' ? true : false,
            ),
            array(
                'url'  => SHOP_ADMIN_URL . '/index.php?ipnlog=x',
                'text' => $LANG_SHOP['ipnlog'],
                'active' => $view == 'ipnlog' ? true : false,
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
        );
        //if (isset($_SHOP_CONF['reports_enabled'])) { // TODO: Remove for release
            $menu_arr[] = array(
                'url'  => SHOP_ADMIN_URL . '/report.php',
                'text' => $LANG_SHOP['reports'],
                'active' => $view == 'reports' ? true : false,
            );
        //}
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
            'title' => $LANG_SHOP['admin_title'] . ' (Ver. ' . $_SHOP_CONF['pi_version'] . ')',
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
        $retval .= ADMIN_createMenu(
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

}

?>


