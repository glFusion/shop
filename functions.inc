<?php
/**
 * glFusion API functions for the Shop plugin.
 * Based on the Paypal Plugin for Geeklog CMS by Vincent Furia.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      Vincent Furia <vinny01@users.sourceforge.net
 * @copyright   Copyright (c) 2009-2019 Lee Garner
 * @copyright   Copyright (c) 2005-2006 Vincent Furia
 * @package     shop
 * @version     0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Include the system configuration class */
$pp_config = config::get_instance();
$_SHOP_CONF= $pp_config->get_config('shop');

/** Include plugin config file  */
require_once __DIR__ . '/shop.php';

$_SHOP_CONF['datetime_fmt'] = 'Y-m-d H:i:s T';

/** Define base path to plugin */
define('SHOP_PI_PATH', __DIR__);
/** Define base URL to plugin */
define('SHOP_URL', "{$_CONF['site_url']}/{$_SHOP_CONF['pi_name']}");
/** Define URL to plugin admin interface */
define('SHOP_ADMIN_URL',
        "{$_CONF['site_admin_url']}/plugins/{$_SHOP_CONF['pi_name']}");

// Define product types
define('SHOP_PROD_PHYSICAL',  1);
define('SHOP_PROD_DOWNLOAD',  2);
define('SHOP_PROD_VIRTUAL',   4);
define('SHOP_PROD_COUPON',    8);
define('SHOP_PROD_PLUGIN',    16);

// Define comment status
define('SHOP_COMMENTS_ENABLED',   0);
define('SHOP_COMMENTS_CLOSED' ,   1);
define('SHOP_COMMENTS_DISABLED',  -1);

// Order status codes
define('SHOP_STATUS_OPEN', 0);
define('SHOP_STATUS_PAID', 1);

// Log levels to emulate glFusion 2.0+ logging
define('SHOP_LOG_DEBUG',    100);
define('SHOP_LOG_INFO',     200);
define('SHOP_LOG_NOTICE',   250);
define('SHOP_LOG_WARNING',  300);
define('SHOP_LOG_ERROR',    400);
define('SHOP_LOG_CRITICAL', 500);
define('SHOP_LOG_ALERT',    550);
define('SHOP_LOG_EMERGEHCY', 600);

// Homepage layouts
define('SHOP_HP_PRODUCT',   1);     // Product list (default)
define('SHOP_HP_CAT',       2);     // Category list
define('SHOP_HP_CATHOME',   4);     // Include home category
define('SHOP_HP_CATTOP',    8);     // Category list, top-level only

// Session variables for TimThumb image url, if used
SESS_setVar($_SHOP_CONF['pi_name'], array(
    'origpath' => $_SHOP_CONF['image_dir'],
));

// Load the site's language file, falling back to English.
$langpath = __DIR__ . '/language';
$langfile = "{$_CONF['language']}.php";
if (!is_file("$langpath/$langfile")) {
    $langfile = 'english.php';
}
/** Include the proper language file */
require_once "$langpath/$langfile";

if (is_file("$langpath/custom/$langfile")) {
    /** Include the custom language file, if any */
    include_once "$langpath/custom/$langfile";
}

if (file_exists(__DIR__ . '/config.php')) {
    /** Include local configuration overrides */
    include_once __DIR__ . '/config.php';
}

//  Set user ID = 1 for anonymous users.
if (empty($_USER['uid']) || $_USER['uid'] < 2) {
    $_USER['uid'] = 1;
    $_USER['username'] = 'anonymous';
    $_USER['fullname'] = 'Anonymous';
}

spl_autoload_register(function ($class)
{
    // project-specific namespace prefix
    $prefix = 'Shop\\';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/classes/';

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.class.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/** Import plugin-specific functions */
function USES_shop_functions()
{   require_once __DIR__ . '/shop_functions.inc.php'; }


/**
 * Import a specific report class.
 *
 * @param   string  $name   Report class basename
 */
function USES_shop_class_report($name='')
{
    $classfile = __DIR__ . '/classes/reports/' . $name . '.class.php';
    if (is_file($classfile)) {
        require_once $classfile;
        return true;
    } else {
        return false;
    }
}


/**
 * Show the product catalog in the centerblock.
 *
 * @param   integer $where  Where to show the block (only after featured story)
 * @param   integer $page   Which page (Home page only)
 * @param   string  $topic  Which topics to show with (All)
 * @return  string      HTML code for submission form
 */
function plugin_centerblock_shop($where = 1, $page = 1, $topic = '')
{
    global $_SHOP_CONF, $LANG_SHOP;

    $display = '';

    // Only show if no topic is requested
    if ($_SHOP_CONF['centerblock'] == 1 && $topic == '') {
        USES_shop_functions();
        $display = \Shop\Menu::siteHeader();
        $T = new Template(__DIR__ . '/templates');
        $T->set_file('title', 'shop_title.thtml');
        $T->set_var('title', $LANG_SHOP['main_title']);
        $display .= $T->parse('', 'title');
        $display .= \Shop\ProductList();
        $display .= \Shop\Menu::siteFooter();
    }
    return $display;
}


/**
 * Upgrade the plugin to the currently-installed version.
 *
 * @param   boolean $dvlp   True if this is called from dvlpupdate.php
 * @return  boolean     True on success, False on failure
 */
function plugin_upgrade_shop($dvlp = false)
{
    require_once __DIR__ . '/upgrade.inc.php';
    return SHOP_do_upgrade($dvlp);
}


/**
 *  Returns the main menu items for the plugin.
 *
 *  @return array   Associative array of menu items to be added to menu
 */
function plugin_getmenuitems_shop()
{
    global $_CONF, $_SHOP_CONF, $LANG_SHOP;

    $menuitems = array();
    if ($_SHOP_CONF['menuitem'] && SHOP_access_check()) {
        $a = preg_replace(
            '/\{(.*)\}/i',
            '<i class="uk-icon-${1}"></i>',
            $LANG_SHOP['mnu_shop']);
        $menuitems[$a] = SHOP_URL . '/index.php';
    }
    return $menuitems;
}


/**
 *  Check to see if this plugin supports comments.
 *
 *  @return boolean     True if comments supported, false otherwise
 */
function plugin_commentsupport_shop()
{
    global $_SHOP_CONF;
    return $_SHOP_CONF['ena_comments'] == 0 ? false : true;
}


/**
 * Save a comment.
 *
 * @param   string  $title      Comment title
 * @param   string  $comment    Comment text
 * @param   string  $id         Item id to which $cid belongs
 * @param   integer $pid        Comment parent
 * @param   string  $postmode   'html' or 'text'
 * @return  mixed   false for failure, HTML string (redirect?) for success
 */
function plugin_savecomment_shop($title,$comment,$id,$pid,$postmode)
{
    global $LANG03, $_SHOP_CONF;

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_shop())
        return;

    $id = (int)$id;
    $title = strip_tags($title);    // No HTML in the comment title
    $pid = COM_applyFilter($pid, true);
    $postmode = COM_applyFilter ($postmode);

    // Actually save the comment
    $ret = CMT_saveComment($title, $comment, $id, $pid, $_SHOP_CONF['pi_name'], $postmode);

    if ($ret > 0) {     // failure
        return COM_siteHeader()
            . CMT_commentform ($title, $comment, $id, $pid,
                    $_SHOP_CONF['pi_name'], $LANG03[14], $postmode)
            . COM_siteFooter();
    } else {        // success - update the comment counter for this item
        return COM_refresh(COM_buildUrl(SHOP_URL . "/detail.php?id=$id"));
    }
}


/**
 * How the plugin will display specific comments.
 *
 * @param   string  $id     Unique idenifier for item comment belongs to
 * @param   integer $cid    Comment id to display
 * @param   string  $title  Page/comment title
 * @param   string  $order  'ASC' or 'DSC' or blank
 * @param   string  $format 'threaded', 'nested', or 'flat'
 * @param   integer $page   Page number of comments to display
 * @param   boolean $view   True to view comment (by cid), false to display (by $pid)
 * @return  mixed   Results of calling the plugin_displaycomment_ function
 */
function plugin_displaycomment_shop($id, $cid, $title, $order,$format, $page, $view)
{
    global $_USER, $LANG_ACCESS, $_SHOP_CONF;

    $retval = '';

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_shop())
        return $retval;

    $P = new \Shop\Product($id);
    if ($P->isNew)
        return $retval;

    $allowed = 1;

    if ($allowed == 1) {
        $delete_option = plugin_ismoderator_shop();
        $retval .= CMT_userComments($id, $title, $_SHOP_CONF['pi_name'], $order, $format,
                                     $cid, $page, $view, $delete_option,
                                     $P->comments_enabled, $_USER['uid']);
    } else {
        $retval .= COM_startBlock($LANG_ACCESS['accessdenied'], '',
                        COM_getBlockTemplate ('_msg_block', 'header'))
                . $LANG_ACCESS['storydenialmsg']
                . COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
    }

    return $retval;
}


/**
 * Delete a single comment.
 *
 * @param   integer $cid    Comment to be deleted
 * @param   string  $id     Item id to which $cid belongs
 * @return  mixed   false for failure, HTML string (redirect?) for success
 */
function plugin_deletecomment_shop($cid, $id)
{
    global $_CONF, $_TABLES, $_SHOP_CONF;

    if (plugin_ismoderator_shop()) {
        if (CMT_deleteComment($cid, $id, $_SHOP_CONF['pi_name']) == 0) {
            $count = DB_count($_TABLES['comments'],
                        array('sid', 'type'),
                        array($id, $_SHOP_CONF['pi_name']));
            $count--;
            if ($count < 0) $count = 0;
            DB_query("UPDATE {$_TABLES['shop.products']}
                    SET comments=$count
                    WHERE id='$id'");
            // Redirect back to the product detail page
            return COM_refresh(SHOP_URL . '/detail.php?id=' . $id);
        }
    }

    // Default return if anything fails
    return false;
}


/**
 * Handle any post-processing for an edited comment.
 * Just redirects back to the product detail page.
 * The comment is actually saved in comment.php.
 *
 * @param   integer $cid    Comment ID
 * @param   mixed   $id     Item ID
 */
function plugin_editcomment_shop($cid, $id)
{
    echo COM_refresh(SHOP_URL . '/detail.php?id=' . $id);
    exit;
}


/**
 * Returns the URL of the plugin's icon.
 *
 * @return  string      URL of the icon
 */
function plugin_geticon_shop()
{
    return SHOP_URL . '/images/shopping_cart2.png';
}


/**
 * Check if this is a plugin administrator.
 * There is no "moderator" for the Shop plugin.
 * Saves a static value to conserve calls to SEC_hasRights()
 *
 * @return  boolean     True for admins, False for regular users
 */
function plugin_ismoderator_shop()
{
    static $isadmin = NULL;
    if ($isadmin === NULL) {
        $isadmin = SEC_hasRights('shop.admin');
    }
    return $isadmin;
}


/**
 * Add an option for the plugin in the command and control area.
 *
 * @return  array   Array containing (pi_name, admin_url, icon_url)
 */
function plugin_cclabel_shop()
{
    global $_SHOP_CONF;

    if (plugin_ismoderator_shop()) {
        return array($_SHOP_CONF['pi_display_name'],
                    SHOP_ADMIN_URL . '/index.php',
                    plugin_geticon_shop());
    }
}


/**
 * Adds the shop plugin to the Admin menu.
 *
 * @return  array   Array containing (pi_name, pi_admin_url, # of items or '')
 */
function plugin_getadminoption_shop()
{
    global $_TABLES, $_SHOP_CONF;

    if (plugin_ismoderator_shop()) {
        return array(
            $_SHOP_CONF['pi_display_name'],
            SHOP_ADMIN_URL . '/index.php',
            DB_count($_TABLES['shop.products']),
        );
    }
}


/**
 * Returns the user menu option for this plugin.
 *
 * @return  array   Array containing (plugin name, plugin user url, 0)
 */
function plugin_getuseroption_shop()
{
    global $LANG_SHOP, $_TABLES, $_USER;

    $retval = array();
    if (SHOP_access_check()) {
        if (!COM_isAnonUser()) {
            $retval[] = array(
                $LANG_SHOP['my_account'],
                SHOP_URL . '/account.php',
            );
        }
        // View cart option is available to anonymous users
        if (\Shop\Cart::getCart() && \Shop\Cart::getInstance()->hasItems()) {
            $retval[] = array(
                $LANG_SHOP['viewcart'],
                COM_buildUrl(SHOP_URL . '/cart.php'),
            );
        }
    }
    return $retval;
}


/**
 * Returns the current version of the shop plugin.
 *
 * @return  string  Plugin version
 */
function plugin_chkVersion_shop()
{
    global $_SHOP_CONF;

    return $_SHOP_CONF['pi_version'];
}


/**
 * Performs operations when a user (or admin) deletes an account.
 * - Anonymize user data in orders
 * - Delete user address info
 *
 * @param   integer $uid    User ID that is being deleted
 */
function plugin_user_delete_shop($uid)
{
    global $_TABLES;

    $uid = (int)$uid;
    $sql = "UPDATE {$_TABLES['shop.orders']}
            SET uid = 1
            WHERE uid = '$uid'";

    \Shop\UserInfo::deleteUser($uid);
    \Shop\Cart::deleteUser($uid);
}


/**
 * Support merging local accounts into remote accounts.
 *
 * @param   integer $origUID    Original (remote) user ID
 * @param   integer $destUID    Merged (local) user ID
 */
function plugin_user_move_shop($origUID, $destUID)
{
    global $_TABLES;

    $origUID = (int)$origUID;
    $destUID = (int)$destUID;

    DB_query("UPDATE {$_TABLES['shop.address']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['shop.orders']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['shop.userinfo']}
            SET uid = $destUID WHERE uid = $origUID", 1);
}


/**
 * Implements the [shop:] autotag.
 *
 * Currently only allows for a single product ID, which will generate
 * a link to that product's detail page.
 *
 * @since   v0.4.0
 * @param   string  $op         Operation to perform (tagname or parse)
 * @param   string  $content    Content in which to replace autotag
 * @param   array   $autotag    Autotag details
 * @return  string              Updated $content
 */
function plugin_autotags_shop ($op, $content = '', $autotag = '')
{
    global $_CONF, $_TABLES, $_SHOP_CONF;

    $pi_name = $_SHOP_CONF['pi_name'];
    if ($op == 'tagname' ) {
        $tags = array(
            $pi_name,
            $pi_name . '_cat',
            $pi_name . '_headlines',
        );
        if (!function_exists('plugin_chkVersion_paypal')) {
            // Handle legacy paypal plugin tags unless that plugin is also
            // enabled.
            $tags[] = 'paypal';
            $tags[] = 'paypal_cat';
            $tags[] = 'paypal_headlines';
        }
        return $tags;
    } else if ($op == 'parse') {
        // Do nothing if the user can't view the catalog
        if (!SHOP_access_check() || !SHOP_isMinVersion()) return $content;

        $repl = false;      // assume no replacement to start off
        $t1 = explode(' ', $autotag['parm2']);
        $opts = array();
        foreach ($t1 as $tmp) {
            $t2 = explode(':', $tmp);
            $opts[$t2[0]] = isset($t2[1]) ? $t2[1] : '';
        }
        switch ($autotag['tag']) {
        case $pi_name:
        case 'paypal':
            $id = $autotag['parm1'];
            switch ($id) {
            case 'headlines':
                $AT = new \Shop\Autotags\headlines;
                $repl = $AT->parse($autotag['parm1'], $opts, $autotag['tagstr']);
                break;
            default:
                // Default tag [shop:prod_id]is a product link
                $P  = \Shop\Product::getInstance($id);
                if (!$P->isNew) {
                    $url = $P->getLink();
                    //$url = COM_buildUrl(SHOP_URL . '/detail.php?id=' . $id);
                    // Allow the author to use different text for the link.  Default
                    // is the stored product name.
                    if (empty($autotag['parm2'])) {
                        $linktext = $P->getDscp();
                    } else {
                        $linktext = $autotag['parm2'];
                    }
                    $repl = '<a href="' . $url . '">' . $linktext . '</a>';
                }
                break;
            }
            break;

        case $pi_name . '_cat':
        case 'paypal_cat':
            $id = COM_applyFilter($autotag['parm1'], true);
            $Cat = \Shop\Category::getInstance($id);
            if (!$Cat->isNew) {
                $url = SHOP_URL.'/index.php?category='.$id;
                if (empty($autotag['parm2'])) {
                    $linktext = DB_getItem($_TABLES['shop.categories'],
                        'cat_name', "cat_id = '$id'");
                } else {
                    $linktext = $autotag['parm2'];
                }
                $repl = '<a href="' . $url . '">' . $linktext . '</a>';
            }
            break;
        case $pi_name . '_headlines':
        case 'paypal_headlines':
            $AT = new \Shop\Autotags\headlines;
            $repl = $AT->parse($autotag['parm1'], $opts, $autotag['tagstr']);
            break;
        }
        if ($repl !== false) {
            $content = str_replace($autotag['tagstr'], $repl, $content);
        }
        return $content;
    }
}


/**
 * Checks to see if the user has sufficient rights to view the page.
 *
 * Check if the current user has rights (at least one of) specified by
 * $rights.  If not log the error, display a generic access denied message
 * and exit.
 *
 * @param   string  $rights     Comma-separated list of allowable rights
 */
function SHOP_access_check($rights = 'shop.user,shop.admin,shop.view')
{
    global $_PLUGINS, $_SHOP_CONF;
    static $access = array();

    if (!array_key_exists($rights, $access)) {
        $acc = true;
        if (!$_SHOP_CONF['shop_enabled'] && !SEC_hasRights('shop.admin')) {
            $acc = false;
        }
        // If this plugin or a required plugin isn't available, abort gracefully
        if (!in_array('lglib', $_PLUGINS) || !SEC_hasRights($rights, 'OR')) {
            $acc = false;
        }

        $access[$rights] = $acc;
    }
    return $access[$rights];
}


/**
 * Return the types to list in the search types selection.
 *
 * @return  array   Associative array (typename => title)
 */
function plugin_searchtypes_shop()
{
    global $_SHOP_CONF, $LANG_SHOP;

    return array($_SHOP_CONF['pi_name'] => $LANG_SHOP['srchtitle']);
}


/**
 * Create a plugin search object to search this plugin.
 *
 * @param   string  $query      Search query string
 * @param   string  $datestart  Starting date for search
 * @param   string  $dateend    Ending date for search
 * @param   string  $topic      Topic selected for search
 * @param   string  $keyType    Type selected during search
 * @param   string  $author     Limit results to this author (not used)
 * @return  object              Search object to be processed by Search class
 */
function plugin_dopluginsearch_shop(
    $query, $datestart, $dateend, $topic, $keyType, $author
)
{
    global $_CONF, $_TABLES, $_SHOP_CONF, $LANG_SHOP;

    // Products aren't "auhored", so return null if searching by author
    if (!empty($author) ||
            !SEC_hasRights('shop.user,shop.admin,shop.view', 'OR')) {
        return NULL;
    }

    if (empty($keyType)) {
        $type = 'all';
    }

    // Encode query for inclusion in result's url
    $htmlquery = urlencode($query);

    $query = trim(DB_escapeString(htmlspecialchars($query), ENT_QUOTES, COM_getEncodingt()));

    $sql = "SELECT
                p.id, p.name as title, p.description as description,
                dt_add as date,  views as hits,
                CONCAT('/{$_SHOP_CONF['pi_name']}/detail.php?id=',p.id,'&query=$htmlquery') as url
            FROM {$_TABLES['shop.products']} p
            LEFT JOIN {$_TABLES['shop.categories']} c
                ON p.cat_id=c.cat_id
            WHERE p.enabled = 1
            AND (c.enabled=1 OR c.enabled IS NULL)";

    $search = new SearchCriteria($_SHOP_CONF['pi_name'], $LANG_SHOP['blocktitle']);
    $columns = array('p.name', 'p.short_description', 'p.description',
                    'p.keywords', 'c.cat_name', 'c.description');
    list($sql, $ftsql) =
            $search->buildSearchSQL($keyType, $query, $columns, $sql);
    $search->setSQL($sql);
    return $search;
}


/**
 * Get additional Javascript files to import in the header.
 *
 * @return  array   Array of filenames to import
 */
function plugin_getheaderjs_shop()
{
    $files = array(
        //__DIR__ . '/js/toggleEnabled.js',
        __DIR__ . '/js/addcart.js',
    );
    return $files;
}


/**
 * Get additional CSS into the site header.
 * Checks first for layout-specific version, gets the plugin default if none.
 *
 * @return  array   List of paths to CSS files
 */
function plugin_getheadercss_shop()
{
    global $_CONF, $_SHOP_CONF;

    $styles = array();
    $pi_css = $_CONF['path_html'] . 'shop/css/';
    $layout_css = "{$_CONF['path_layout']}plugins/{$_SHOP_CONF['pi_name']}/";

    $files = array('style.css');

    foreach ($files as $file) {
        $styles[] = $pi_css . $file;
        if (@file_exists($pi_css . 'custom/' . $file)) {
            $styles[] = $pi_css . 'custom/' . $file;
        }
        if (@file_exists($layout_css . $file)) {
            $styles[] = $layout_css . $file;
        }
    }

    return $styles;
}


/**
 * Get the URL to the localized configuration help file.
 *
 * @uses    SHOP_getDocURL()
 * @param   string  $option     Configuration option to display
 * @param   string  $doclang    Documentation language requested
 * @return  array   Array of (url, window_type)
 */
function plugin_getconfigelementhelp_shop($option, $doclang='english')
{
    $doc_url = SHOP_getDocURL('config', $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $doc_url .= '#desc_' . $option;
    }

    return array($doc_url, 0);
}


/**
 * Get the link to a documentation file.
 * Returns the URL to the file, or to the directory if no file requested.
 *
 * @see    plugin_getconfigelementhelp_shop()
 * @see    \Shop\Product::showForm()
 * @param  string  $file       HTML file
 * @param  string  $doclang    Documentation language
 * @return string              URL to document directory or file
 */
function SHOP_getDocURL($file='', $doclang='english')
{
    $doc_path = __DIR__ . "/docs/$doclang/";
    $doc_url = '';

    $file .= '.html';

    if ($file != '') {
        if (!file_exists($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = SHOP_URL . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = SHOP_URL . "/docs/$doclang/";
    }
    return $doc_url;
}


/**
 * Return the items that should be removed with the plugin.
 *
 * @return  array   Array of items to be removed.
 */
function plugin_autouninstall_shop()
{
    $out = array (
        // Give the name of the tables, without $_TABLES[]
        'tables' => array(
            'shop.address',
            'shop.buttons',
            'shop.categories',
            'shop.currency',
            'shop.gateways',
            'shop.images',
            'shop.ipnlog',
            'shop.order_log',
            'shop.orderstatus',
            'shop.orders',
            'shop.prod_attr',
            'shop.products',
            'shop.orderitems',
            'shop.userinfo',
            'shop.workflows',
            'shop.coupons',
            'shop.coupon_log',
            'shop.sales',
            'shop.shipping',
        ),
        // Give the full name of the group, as in the db
        'groups' => array(
            'shop Admin',
        ),
        // Give the full name of the feature, as in the db
        'features' => array(
            'shop.admin',
            'shop.user',
            'shop.view'
        ),
        // Give the full name of the block, including 'phpblock_', etc
        'php_blocks' => array(
            'phpblock_shop_random',
            'phpblock_shop_featured',
            'phpblock_shop_categories',
            'phpblock_shop_popular',
            'phpblock_shop_cart',
            'phpblock_shop_recent',
            'phpblock_shop_search',
        ),
        // List all vars by name
        'vars'=> array(
            'shop_gid',
        ),
    );
    \Shop\Cache::clear();  // clear everything from cache
    return $out;
}


/**
 * Disable this plugin's blocks when the plugin is changed.
 *
 * Acts on all blocks with a phpblock function similar to the specified
 * functions.  Blocks are not enabled automatically when enabling the plugin.
 *
 * @param   boolean $enabled    Plugin's new "enabled" state
 */
function plugin_enablestatechange_shop($enabled)
{
    global $_TABLES, $_PLUGINS, $_SHOP_CONF;

    $enabled = $enabled == true ? 1 : 0;
    // If trying to enable the plugin but lgLib is not available,
    // take no action and return
    if ($enabled && !in_array('lglib', $_PLUGINS)) {
        // Re-disable the plugin
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', $_SHOP_CONF['pi_name']);
    }
}


/**
 * Receives notification of another plugin's deletion.
 *
 * @param  mixed   $id     Item ID
 * @param  string  $type   Plugin Name
 */
function plugin_itemdeleted_shop($id, $type)
{
    global $_TABLES, $_SHOP_CONF;

    // If the lgLib plugin is disabled or removed, disable thie plugin
    if ($type == 'lglib' && $id == '*') {
        plugin_enablestatechange_shop(false);
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', $_SHOP_CONF['pi_name']);
        unset($_POST['enabledplugins'][$_SHOP_CONF['pi_name']]);
        unset($_POST['pluginarray'][$_SHOP_CONF['pi_name']]);
    } elseif ($type == 'shop' && $id != '*') {
        $type = DB_escapeString($type);
        $id = DB_escapeString($id);
        $sql = "DELETE FROM {$_TABLES['shop.buttons']}
                WHERE pi_name = '$type' AND item_id = '{$id}'";
        DB_query($sql);
    }
}


/**
 * Get comment url and unique id fieldname.
 *
 * @return  array   Array containing main plugin URL and the unqiue id name
 */
function plugin_getcommenturlid_shop()
{
    return array(COM_buildUrl(SHOP_URL . '/detail.php'), 'product');
}


/**
 * Creates the shopping cart block, if the cart is not empty.
 *
 * @uses    phpblock_shop_cart_contents()
 * @return  string      HTML for the shopping cart block.
 */
function phpblock_shop_cart()
{
    if (!SHOP_access_check() || !SHOP_isMinVersion()) return '';

    $T = new Template(__DIR__ . '/templates');
    $T->set_file('shopping_cart', 'blk_cart.thtml');
    $contents = phpblock_shop_cart_contents();
    $vis = empty($contents) ? 'none' : 'block';
    $T->set_var(array(
        'cart_contents' => $contents,
        'visibility'    => $vis,
        'cart_url'      => COM_buildUrl(SHOP_URL . '/cart.php'),
    ) );
    $T->parse('output', 'shopping_cart');
    return $T->finish ($T->get_var('output'));
}


/**
 * Supplies the actual content for the shopping cart block.
 * Used to allow AJAX updates to the cart display
 *
 * Each item in the cart is shown along with its quantity, and links
 * to view the cart or check out.
 *
 * @see     phpblock_shop_cart()
 * @return  string      HTML for the shopping cart block.
 */
function phpblock_shop_cart_contents()
{
    global $_SHOP_CONF, $LANG_SHOP;

    if (!SHOP_access_check() || !SHOP_isMinVersion()) return '';

    // If the cart is not enabled, or there is no cart, return
    if ($_SHOP_CONF['ena_cart'] == 0 || !\Shop\Cart::getCart()) {
        //SHOP_log("Cart disabled or there is no cart", SHOP_LOG_DEBUG);
        return '';
    }

    $cart = \Shop\Cart::getInstance();
    if (!$cart->canView()) {
        \Shop\Cart::delAnonCart();
        return '';
    }
    // If the cart is empty, the block isn't shown
    if (!$cart->hasItems()) {
        //SHOP_log("Cart {$cart->order_id} has no items", SHOP_LOG_DEBUG);
        return '';
    }

    $T = new Template(__DIR__ . '/templates');
    $T->set_file('shopping_cart_contents', 'blk_cart_contents.thtml');
    $T->set_block('shopping_cart_contents', 'CartRow', 'cRow');
    $total = 0;
    $Cur = \Shop\Currency::getInstance($cart->currency);
    foreach ($cart->getItems() as $id=>$item) {
        $item_id = $item->product_id;
        if ($item->options != '') $item_id .= '|' . $item->options;
        $P = \Shop\Product::getInstance($item_id);

        $item_total = (float)$item->price * $item->quantity;
        $T->set_var(array(
            'item_id'       => $item_id,
//            'options'       => $P->getOptionDisplay($item),
            'item_name'     => $item->description,
            'price'         => $Cur->FormatValue($item->price),
            'quantity'      => $item->quantity,
            'item_total'    => $Cur->FormatValue($item_total),
//            'pi_url'        => SHOP_URL,
            'item_link'     => $P->getLink(),
        ) );
        $T->parse('cRow', 'CartRow', true);
        $total += $item_total;
    }
    $T->set_var(array(
        'grand_total' => $Cur->Format($total),
    ) );
    $T->parse('output', 'shopping_cart_contents');
    return $T->finish ($T->get_var('output'));
}


/**
 * Catalog search block.
 *
 * @return  string  HTML for search form
 */
function phpblock_shop_search()
{
    global $_TABLES;

    if (!SHOP_access_check() || !SHOP_isMinVersion()) return '';

    $T = new Template(__DIR__ . '/templates/');
    $T->set_file('search', 'blk_search.thtml');

    $cache_key = 'cat_options_cat_id_cat_name';
    $category_options = \Shop\Cache::get($cache_key);
    if ($category_options === NULL) {
        $category_options = COM_optionList($_TABLES['shop.categories'],
            'cat_id, cat_name', '', 1, SEC_buildAccessSql(''));
        // Configured cache age is not used since the cache will be
        // cleared when categories are changed.
        \Shop\Cache::set($cache_key, $category_options, 'categories');
    }
    $T->set_var(array(
        'category_options'  => $category_options,
        'action_url'        => SHOP_URL . '/index.php',
    ) );
    $T->parse('output', 'search');
    return $T->finish($T->get_var('output'));
}


/**
 * Create a random product block.
 * Shows a single product selected from all enabled products.
 *
 * @uses    SHOP_phpblock_product_sql()
 * @param   string  $cat    Optional single category
 * @return  string          HTML for the block
 */
function phpblock_shop_random($cat='')
{
    global $_SHOP_CONF;

    if (!SHOP_access_check() || !SHOP_isMinVersion()) return '';

    $limit = (int)$_SHOP_CONF['blk_random_limit'];
    if ($limit < 1)
        $limit = 1;

    $sql = ' show_random=1 ';
    if ($cat != '') {
        $sql .= "AND category='" . DB_escapeString($cat). "' ";
    }

    return SHOP_phpblock_product_sql($sql, $limit);
}


/**
 * Create a featured product block.
 * Shows a single product selected from all enabled featured products.
 *
 * @uses    SHOP_phpblock_product_sql()
 * @param   string  $cat    Optional single category
 * @return  string          HTML for the block
 */
function phpblock_shop_featured($cat='')
{
    global $_SHOP_CONF;

    if (!SHOP_access_check() || !SHOP_isMinVersion()) return '';

    $limit = (int)$_SHOP_CONF['blk_featured_limit'];
    if ($limit < 1)
        $limit = 1;

    $sql = ' featured=1 ';
    if ($cat != '') {
        $sql .= "AND category='" . DB_escapeString($cat) . "' ";
    }

    return SHOP_phpblock_product_sql($sql, $limit);
}


/**
 * Create a category-list block.
 * Returns HTML for a list of category names and the number of
 * products under each one.
 *
 * @return  string  HTML for the block
 */
function phpblock_shop_categories()
{
    global $_TABLES;

    if (!SHOP_access_check() || !SHOP_isMinVersion()) return '';

    $parents = '0, ' . \Shop\Category::getRoot()->cat_id;
    $sql = "SELECT c.*, count(p.id) AS cnt
            FROM {$_TABLES['shop.categories']} c
            LEFT JOIN {$_TABLES['shop.products']} p
                ON p.cat_id = c.cat_id
            WHERE c.cat_id > 0
            AND c.enabled = 1
            AND c.parent_id IN ($parents) " .
            SEC_buildAccessSql('AND', 'c.grp_access') .
            " GROUP BY c.cat_id
            ORDER BY c.cat_name";
    //echo $sql;die;
    $cache_key = md5($sql);
    $Cats = \Shop\Cache::get($cache_key);
    //var_dump($Cats);die;
    if ($Cats === NULL) {
        $Cats = array();
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $Cats[] = $A;
            }
        }
        \Shop\Cache::set($cache_key, $Cats, array('categories','products'));
    }
    $T = new Template(__DIR__ . '/templates');
    $T->set_file(array('blk_category' => 'blk_categories.thtml'));

    foreach ($Cats as $A) {
        $cnt = (int)$A['cnt'];
        if ($A['parent_id'] > 0) {
            // Doesn't render right if the home category counts all subcats
            $cnt += SHOP_child_cat_product_count($A);
        }
        if ($cnt == 0) continue;
        $T->set_block('blk_category', 'row', 'ItemRow');
        $T->set_var(array(
            'category'  => $A['cat_name'],
            'count'     => $cnt,
            'url'       => SHOP_URL . '/index.php?category=' . $A['cat_id'],
        ) );
        $T->parse('ItemRow', 'row', true);
    }
    return $T->parse('output', 'blk_category');
}


/**
 * Create a block showing one or more "popular" products.
 * Popularity is determined by counting purchase records.
 *
 * @return  string              HTML for "Popular Items" block
 */
function phpblock_shop_popular()
{
    global $_TABLES, $_SHOP_CONF;

    if (!SHOP_access_check() || !SHOP_isMinVersion()) return '';

    $limit = (int)$_SHOP_CONF['blk_popular_limit'];
    if ($limit < 1)
        $limit = 1;

    $sql = "SELECT DISTINCT
                pr.id,
                (SELECT count(*) FROM {$_TABLES['shop.orderitems']} pu
                    WHERE product_id = pr.id) AS cnt
            FROM {$_TABLES['shop.products']} pr
            LEFT JOIN {$_TABLES['shop.categories']} c
            ON pr.cat_id=c.cat_id
            WHERE pr.enabled=1
            AND (c.enabled=1 OR c.enabled IS NULL)
            AND pr.show_popular = 1 " .
            SEC_buildAccessSql('AND', 'c.grp_access') .
            " HAVING cnt > 0
            ORDER BY cnt DESC
            LIMIT $limit";
    //echo $sql;die;

    $retval = SHOP_phpblock_product($sql);
    return $retval;
}


/**
 * Create a "New Products" block.
 * Shows most recently-added products
 *
 * @uses    SHOP_phpblock_product_sql()
 * @param   string  $cat    Optional single category
 * @return  s tring          HTML for the block
 */
function phpblock_shop_recent($cat='')
{
    global $_TABLES, $_SHOP_CONF;

    if (!SHOP_access_check() || !SHOP_isMinVersion()) return '';

    $limit = (int)$_SHOP_CONF['blk_popular_limit'];
    if ($limit < 1)
        $limit = 1;

    $sql = "SELECT DISTINCT
                pr.id,
                (SELECT count(*) FROM {$_TABLES['shop.orderitems']} pu
                    WHERE product_id = pr.id) AS cnt
            FROM {$_TABLES['shop.products']} pr
            LEFT JOIN {$_TABLES['shop.categories']} c
            ON pr.cat_id=c.cat_id
            WHERE pr.enabled=1
            AND (c.enabled=1 OR c.enabled IS NULL)
            AND pr.show_popular = 1 " .
            SEC_buildAccessSql('AND', 'c.grp_access') .
            " HAVING cnt > 0
            ORDER BY dt_add DESC
            LIMIT $limit";
    //echo $sql;die;

    $retval = SHOP_phpblock_product($sql);
    return $retval;
}


/**
 * Display a formatted error message.
 * This returns the provided message formatted using glFusion's
 * typography options.
 *
 * @param   string $msg     Error message to display
 * @param   string $type    Type of message, used for style and header
 * @param   string $header  Optional header text.
 * @return  string          HTML for formatted error message
 */
function SHOP_errorMessage($msg = '', $type = '', $header = '')
{
    global $LANG_SHOP;

    // Require a valid message
    if ($msg == '')
        return '';

    // If no header text specified, we'll put in a default.
    // Also validate the message type in this switch.
    switch (strtolower($type)) {
    case 'info':
        $class = 'info';
        if ($header == '') $header = $LANG_SHOP['info'];
        break;

    case 'note':
    case 'warn':
        $class = 'note';
        if ($header == '') $header = $LANG_SHOP['warning'];
        break;

    case 'alert':
    default:
        $class = 'alert';
        if ($header == '') $header = $LANG_SHOP['alert'];
        break;
    }

    return "<span class=\"$class\">"
            . COM_startBlock($header)
            . $msg
            . COM_endBlock()
            . '</span>';
}


/**
 * Create the SQL for a product block and return the block contents.
 *
 * @uses    SHOP_phpblock_product()
 * @param   string  $where  Search condition, starting with AND
 * @param   integer $limit  Number of items to show.
 * @return  string      HTML for the random-product block
 */
function SHOP_phpblock_product_sql($where, $limit=1)
{
    global $_TABLES, $_SHOP_CONF, $LANG_SHOP;

    $retval = '';

    if ($where != '') {
        $where = ' AND ' . DB_escapeString($where) . ' ';
    }

    if ($limit != 0) {
        $limit = ' LIMIT ' . (int)$limit . ' ';
    }

    // The "c.enabled IS NULL" is to allow products which have
    // no category record, as long as the product is enabled.
    $sql = "SELECT  id
            FROM {$_TABLES['shop.products']} p
            LEFT JOIN {$_TABLES['shop.categories']} c
            ON p.cat_id=c.cat_id
            WHERE
                p.enabled=1 AND (c.enabled=1 OR c.enabled IS NULL) " .
            SEC_buildAccessSql('AND', 'c.grp_access') . "
            $where
            ORDER BY RAND()
            $limit";

    $retval = SHOP_phpblock_product($sql);
    return $retval;
}


/**
 * Create a product block.
 * Takes the supplied SQL and executes the query, formatting the block
 * and returning the HTML.
 * Only the product ID needs to be selected in the query.
 *
 * @param   string  $sql    Complete SQL query to be executed.
 * @return  string          HTML for the product block.
 */
function SHOP_phpblock_product($sql)
{
    global $_SHOP_CONF, $_TABLES, $_CONF;

    // By default, return nothing
    $retval = '';

    // Loop through all valid responses.  There may be more than one
    // item to show
    $res = DB_query($sql, 1);
    if (DB_error()) {
        SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
        return '';
    }
    if ($res && DB_numRows($res) > 0) {

        $T = new Template(__DIR__ . '/templates');
        $T->set_file(array(
            'blk_random' => 'blk_random.thtml',
            'btn_details' => 'buttons/btn_details.thtml',
        ));
        for ($i = 0; $A = DB_fetchArray($res, false); $i++) {
            $P = \Shop\Product::getInstance($A['id']);
            $T->set_var(array(
                'name'      => $P->name,
                'item_url'  => COM_buildUrl(SHOP_URL . '/detail.php?id=' .
                                $A['id']),
                'descrip'   => $P->short_description,
                'price'     => $P->price > 0 ?
                                \Shop\Currency::getInstance()->Format($P->price) : '',
                'currency'  => $_SHOP_CONF['currency'],
                'rec_num'   => $i,      // to separate items by <hr/>
                'id'        => $A['id'],
                'pi_url'    => SHOP_URL,
            ) );

            if (!$P->hasAttributes()) {
                $buttons = $P->PurchaseLinks();
                $btn_txt = '';
                foreach ($buttons as $name=>$html) {
                    $btn_txt .= $html . '<br />';
                }
                $T->set_var('button', $btn_txt);
            } else {
                if ($_SHOP_CONF['ena_cart']) {
                    // If the product has attributes, then the cart must be
                    // enabled to allow purchasing
                    $button = $T->parse('', 'btn_details') . '&nbsp;';
                    $T->set_var('button', $button);
                    //$T->parse('Btn', 'BtnBlock', true);
                }
            }

            $img = $P->getOneImage();
            if ($img != '') {
                $T->set_var('img_url', SHOP_ImageUrl($img));
            } else {
                $T->set_var('img_url', '');
            }
            $retval .= $T->parse('output', 'blk_random');
        }
    }
    return $retval;
}


/**
 * Updates the product table with the new rating and vote count.
 * Requires glFusion 1.1.7 or higher.
 *
 * @since   v0.4.0
 * @param   mixed   $id     Product ID
 * @param   float   $rating Rating Value
 * @param   integer $votes  Number of votes cast
 */
function plugin_itemrated_shop($id, $rating, $votes)
{
    return \Shop\Product::updateRating($id, $rating, $votes);
}


/**
 * Determine if the current user is allowed to rate an item.
 * Curretly, all logged-in users can rate (even admin).
 * Requires glFusion 1.1.7 or higher.
 *
 * @since   v0.4.0
 * @param   mixed   $item_id    ID of product to be rated
 * @param   integer $uid        User ID of the one doing the rating
 * @return  boolean             True if user is allowed, false if not
 */
function plugin_canuserrate_shop($item_id, $uid)
{
    global $_SHOP_CONF;
    static $canRate = NULL;

    if ($canRate === NULL) {
        if ($_SHOP_CONF['anon_can_rate'] == 0 && COM_isAnonUser()) {
            $canRate = false;
        } else {
            $canRate = true;
        }
    }
    return $canRate;
}


/**
 * Handle actions needed when the current user logs in.
 * Merges the saved cart with any values added while browsing anonymously
 *
 * @param   integer $uid    User ID
 */
function plugin_user_login_shop($uid)
{
    global $_SHOP_CONF;

    if ($uid < 2 || $_SHOP_CONF['ena_cart'] == 0) return;

    $anon_cart = \Shop\Cart::getAnonCartID();  // get the anonymous cart ID
    if (empty($anon_cart)) return;             // save a step if there's no anon cart
    $user_cart = \Shop\Cart::getInstance($uid);    // get the logged-in user's cart
    $user_cart->Merge($anon_cart);
    $user_cart->Validate();
}


/**
 * Handle actions needed when the current user logs out.
 *
 * @param   integer $uid    User ID
 */
function NOOP_plugin_user_logout_shop($uid)
{
/*    global $_SHOP_CONF;

    $C = \Shop\Cart::getInstance($uid);    // get the logged-in user's cart
    \Shop\Cart::storeCartID($C->order_id);    // save the cart ID for the anon user
*/
}


/**
 * Run scheduled tasks.
 * - Delete old carts that aren't assigned to any user.
 * - Call PLG_itemDeleted to notify plugins that an item is not available
 */
function plugin_runScheduledTask_shop()
{
    global $_SHOP_CONF, $_TABLES, $_CONF;

    $orders = array();
    $conds = array();
    $sql = "SELECT ord.order_id FROM {$_TABLES['shop.orders']} ord
        LEFT JOIN {$_TABLES['shop.orderitems']} pur
            ON ord.order_id = pur.order_id WHERE ";

    if ($_SHOP_CONF['ena_cart'] == 1) {
        // Get empty carts over 2 days old, no need to keep them around
        $conds[] = "(ord.status='cart'
                AND order_seq IS NULL
                AND ord.last_mod < DATE_SUB(NOW(), INTERVAL 2 DAY)
                AND pur.order_id IS NULL)";

        // Get all carts not updated in a while
        $days = SHOP_getVar($_SHOP_CONF, 'days_purge_cart', 'integer');
        if ($days > 0) {
            $conds[] = "(ord.status = 'cart' AND
                ord.order_seq IS NULL AND
                ord.last_mod < DATE_SUB(NOW(), INTERVAL $days DAY))";
        }
    }

    // Get pending (unpaid) orders not updated in a while
    $days = SHOP_getVar($_SHOP_CONF, 'days_purge_pending', 'integer');
    if ($days > 0) {
        $conds[] = "(ord.status = 'pending' AND
                ord.order_seq IS NULL AND
                ord.last_mod < DATE_SUB(NOW(), INTERVAL $days DAY))";
    }

    if (!empty($conds)) {
        $res = DB_query($sql . implode(' OR ', $conds));
        $A = DB_fetchAll($res, false);
        foreach ($A as $B) {
            $orders[] = DB_escapeString($B['order_id']);
        }
    }

    if (!empty($orders)) {
        foreach ($orders as $order_id) {
            \Shop\Order::Delete($order_id);
        }
    }

    // Clean up the HTML cache if used
    if ((int)$_SHOP_CONF['cache_max_age'] > 0) {
        $cachedir = "{$_CONF['path_data']}/layout_cache";
        $files = glob("$cachedir/__shop__*");
        if ($files) {
            $timeAgo = time() - (int)$_SHOP_CONF['cache_max_age'];
            foreach ($files as $file) {
                if (@filemtime($file) < $timeAgo) {
                    @unlink($file);
                }
            }
        }
    }

    // Treat disabled and expired products as "deleted" to get them out of
    // search results, etc.
    $sql = "SELECT id, prod_type FROM {$_TABLES['shop.products']}
            WHERE enabled = 1 AND avail_end < '" . SHOP_now()->format('Y-m-d', true) . "'";
    $res = DB_query($sql);
    while ($A = DB_fetchArray($res, false)) {
        PLG_itemDeleted($A['id'], $A['prod_type']);
        // Toggle to disabled, also clears the product cache
        \Shop\Product::toggleEnabled(1, $A['id']);
    }

    // Delete old cached buttons which are keyed by item_id and price.
    // Sale-priced items will get stuck in the DB.
    DB_query("DELETE FROM {$_TABLES['shop.buttons']}
            WHERE last_update < NOW() - INTERVAL 120 DAY");

    // Clean up the sale price table if so configured.
    if ($_SHOP_CONF['purge_sale_prices']) {
        \Shop\Sales::Clean();
    }

    // Handle any expired coupons
    if ($_SHOP_CONF['gc_enabled']) {
        \Shop\Products\Coupon::Expire();
    }
}


/**
 * Return true to enable service functions.
 *
 * @return  boolean     True, if webservices are supported
 */
function plugin_wsEnabled_shop()
{
    return true;
}


/**
 * Determine if a given item number belongs to a plugin.
 * For now, this simply checks whether the item number is numeric.  If
 * it is, it's assumed to be a catalog item.  If it's non-numeric, it's
 * assumed to be a plugin-supplied item where the item number is
 * formated as "pi_name:item_number:other_opts"
 *
 * @param   mixed   $item_number    Item Number to check
 * @return  boolean     True if it's a plugin item, false if it's ours
 */
function SHOP_is_plugin_item($item_number)
{
    if (!is_numeric($item_number)) {
        return true;
    } else {
        return false;
    }
}


/**
 * Helper function to get image urls from LGLIB_ImageUrl().
 *
 * @param   string  $imgname    Image name, no path
 * @param   integer $width      Optional width, assume thumbnail
 * @param   integer $height     Optional height, assume thumbnail
 * @return  string      URL to resized image
 */
function SHOP_ImageUrl($imgname, $width=0, $height=0)
{
    global $_SHOP_CONF;

    $width = $width == 0 ? $_SHOP_CONF['max_thumb_size'] : (int)$width;
    $height = $height == 0 ? $_SHOP_CONF['max_thumb_size'] : (int)$height;
    return LGLIB_ImageUrl($_SHOP_CONF['image_dir'] . '/' . $imgname, $width, $height);
}


/**
 * Send debug info to the error log.
 *
 * If the plugin configuration is set to log messages indicated by $type,
 * then the message is logged.
 *
 * @param   string  $msg    Text message to display, may be blank.
 * @param   string  $type   Type of message
 */
function SHOP_log($msg, $level = SHOP_LOG_ERROR)
{
    global $_SHOP_CONF;

    if ($level < $_SHOP_CONF['log_level']) {
        return;
    }

    $msg1 = 'SHOP ';
    switch ($level) {
    case SHOP_LOG_DEBUG:
        $msg1 .= 'DEBUG';
        break;
    case SHOP_LOG_INFO:
        $msg1 .= 'INFO';
        break;
    case SHOP_LOG_NOTICE:
        $msg1 .= 'NOTICE';
        break;
        case SHOP_LOG_ERROR;
    case SHOP_LOG_WARNING:
        $msg1 .= 'WARNING';
        break;
    case SHOP_LOG_ERROR:
        $msg1 .= 'ERROR';
        break;
    case SHOP_LOG_CRITICAL:
        $msg1 .= 'CRITICAL';
        break;
    case SHOP_LOG_ALERT:
        $msg1 .= 'ALERT';
        break;
    case SHOP_LOG_EMERGENCY:
        $msg1 .= 'EMERGENCY';
        break;
    }
    $msg1 .= ': ';

    $bk = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($bk[1])) {
        if (isset($bk[1]['class'])) {
            $msg1 .= $bk[1]['class'] . $bk[1]['type'];
        }
        $msg1 .= $bk[1]['function'] . '(): ';
    } else {
        $msg1 .= basename($bk[0]['file']) . '(' . $bk[0]['line'] . ') ';
    }
    COM_errorLog($msg1 . $msg, 1);
}


/**
 * Select the template to use for product detail pages, by default.
 *
 * @return  array   $option => path
 */
function plugin_configmanager_select_product_tpl_ver_shop()
{
    $A = array();
    $dirs = glob(__DIR__ . '/templates/detail/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $tmp = explode('/', $dir);
        $opt = end($tmp);
        $A[$opt] = $opt;
    }
    return $A;
}


/**
 * Select the template to use for product list pages.
 *
 * @return  array   $option => path
 */
function plugin_configmanager_select_list_tpl_ver_shop()
{
    $A = array();
    $dirs = glob(__DIR__ . '/templates/list/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $tmp = explode('/', $dir);
        $opt = end($tmp);
        $A[$opt] = $opt;
    }
    return $A;
}


/**
 * Create a currency dropdown list for selecting the default currency.
 *
 * @return  array   Array of description => value
 */
function plugin_configmanager_select_currency_shop()
{
    $A = array();
    $tmp = \Shop\Currency::getAll();
    foreach ($tmp as $code => $data) {
        $A["{$code} - {$data->name}"] = $code;
    }
    return $A;
}


/**
 * Display variables related to the shop in Account Settings.
 *
 * @param   integer $uid        User ID
 * @param   string  $panel      Panel being populated
 * @param   string  $fieldset   Name of fieldset being populated
 * @return  string              HTML for edit form. Never NULL.
 */
function plugin_profileedit_shop($uid, $panel, $fieldset)
{
    global $_SHOP_CONF, $_USER, $_TABLES, $LANG_SHOP;

    $retval = '';
    // Make sure that a valid UID was passed in.
    if (empty($uid) || (!plugin_ismoderator_shop() && $uid != $_USER['uid'])) {
        return $retval;
    }

    if ( ($panel == 'useredit') || ($panel == '' && $fieldset == '') ) {
    //if (($panel == '' || $panel == 'useredit') && $fieldset == '') {
        $LT = new \Template(__DIR__ . '/templates/');
        $LT->set_file(array(
            'block' => 'profileblock.thtml',
        ));
        $LT->set_var('is_uikit', $_SHOP_CONF['_is_uikit']);
        $U = \Shop\UserInfo::getInstance($uid);
        if ($_SHOP_CONF['gc_enabled']) {
            $gc_bal = \Shop\Products\Coupon::getUserBalance($uid);
            $LT->set_var('gc_bal', \Shop\Currency::getInstance()->Format($gc_bal));
        }
        if (!empty($U->cart)) {
            $cart = true;
            $LT->set_var('cart', true);
        } else {
            $cart = false;
        }
        $ord_count = DB_count($_TABLES['shop.orders'], 'uid', $uid);
        $LT->set_var('orders', $ord_count);

        // Only display the template if there's something to show
        /*if ($ord_count > 0 || $cart || $gc_bal > 0) {
            USES_shop_functions();
            //$LT->set_var('order_hist', \Shop\listOrders());
        }*/
        $LT->parse('output', 'block');
        $retval .= $LT->finish($LT->get_var('output'));
    }
    return $retval;
}


/**
 * Save the Shop profile settings when updated from Account Settings.
 *
 * @param   integer $uid    ID of user being saved
 */
function plugin_profilesave_shop($uid=0)
{
    global $_USER;

    if ($uid == 0) {
        $uid = (int)$_USER['uid'];
    }

    if (COM_isAnonUser()) {
        return;
    }

    $apply_gc = SHOP_getVar($_POST, 'pp_apply_gc', 'string');
    if (!empty($apply_gc)) {
        \Shop\Products\Coupon::Redeem($apply_gc, $uid);
    }
}




/**
 * Get all parent categories and a product count.
 * Recurses through all child categories.
 *
 * @param   array   $p_cat  Parent category ID to check
 * @return  integer         Product count for all child categories
 */
function SHOP_child_cat_product_count($p_cat)
{
    global $_TABLES;

    $parent_total = 0;
    $lft = (int)$p_cat['lft'];
    $rgt = (int)$p_cat['rgt'];
    $sql = "SELECT c.cat_id, c.cat_name, count(p.id) AS cnt
            FROM {$_TABLES['shop.categories']} c
            LEFT JOIN {$_TABLES['shop.products']} p
                ON p.cat_id = c.cat_id
            WHERE c.lft BETWEEN $lft AND $rgt
            AND c.enabled = 1 " .
            SEC_buildAccessSql('AND', 'c.grp_access') .
            " GROUP BY c.cat_id
            ORDER BY c.cat_name";
            //AND c.parent_id = $parent_cat " .
    //echo $sql;die;
    $result = DB_query($sql);
    while ($A = DB_fetchArray($result, false)) {
        // Add the total products in the child category
        $parent_total += (int)$A['cnt'];
        // And call again to get the child's children
        //$parent_total += SHOP_child_cat_product_count($A['cat_id']);
    }
    return $parent_total;
}


/**
 * Explode a full item ID into item_id and options.
 * If $opt_string is set then the option string is returned
 * unchanged, otherwise option IDs are returned as an array.
 *
 * @param   string  $item   Original item ID, e.g. "4|20,17"
 * @param   boolean $opt_string True to return options as a string
 * @return  array       Array of (item_id, options)
 */
function SHOP_explode_opts($item, $opt_string = false)
{
    if (strpos($item, '|')) {
        list($item_id, $options) = explode('|', $item);
    } else {
        $item_id = $item;
        $options = NULL;
    }
    if ($options === NULL) $options = '';
    if (!$opt_string) {
        if (empty($options)) {
            $options = array();
        } else {
            $options = explode(',', $options);
        }
    }
    return array($item_id, $options);
}


/**
 * Get a date object corresponding to "now".
 * Used to avoid creating a lot of Date objects or cluttering
 * the global variables
 *
 * @return  object      Date object
 */
function SHOP_now()
{
    global $_CONF;
    return $_CONF['_now'];
}


/**
 * Get a DateTimeZone object for the current timezone.
 *
 * @return  object  DateTimeZone object
 */
function SHOP_timezone()
{
    global $_CONF;
    static $tz = NULL;
    if ($tz === NULL) {
        $tz = new DateTimeZone($_CONF['timezone']);
    }
    return $tz;
}


/**
 * Provides the getItemInfo function for products.
 *
 * @param   string  $id         Product ID
 * @param   string  $what       Comma-separated list of fields to return
 * @param   integer $uid        User ID
 * @param   array   $options    Not used
 * @return  array|NULL          Array of product information, NULL if none found
 */
function plugin_getiteminfo_shop($id, $what, $uid=0, $options=array())
{
    global $_TABLES, $_CONF, $LANG_SHOP;

    $properties = explode(',', $what);
    $fields = array('p.id');        // always get item id
    $retval = array();
    $buildingSearchIndex = false;

    // If a user is getting this info, e.g. via a search, and the shop is not
    // open to the public, then return nothing unless the user is an admin.
    if ($uid > 0) {
        if ($_SHOP_CONF['shop_enabled'] || SHOP_access_check('shop.admin')) {
            $cat_where = SEC_buildAccessSql('AND', 'c.grp_access');
        } else {
            return NULL;
        }
    }

    foreach ($properties as $p) {
        switch ($p) {
        case 'title':
            $fields[] = 'p.name';
            break;
        case 'excerpt':
        case 'introtext':
            $fields[] = 'p.short_description';
            break;
        case 'bodytext':
            $fields[] = 'p.description';
            break;
        case 'description':
            $fields[] = 'p.short_description';
            $fields[] = 'p.description';
            break;
        case 'searchidx':
            $buildingSearchIndex = true;
            $fields[] = 'p.name';
            $fields[] = 'p.keywords';
        case 'content':
            $fields[] = 'p.short_description';
            $fields[] = 'p.description';
            break;
        case 'date':
            $fields[] = 'UNIX_TIMESTAMP(p.dt_add) as unixtime';
            break;
        case 'price':
            $fields[] = 'p.price';
            break;
        case 'search_index':
            $buildingSearchIndex = true;
            break;
        case 'perms':
            $fields[] = 'c.grp_access';
            break;
        }
    }
    if (count($fields) == 0) {
        return $retval;
    }
    $fields = array_unique($fields);
    $fields = implode(',', $fields);

    $retval = array();
    $sql = "SELECT $fields
             FROM {$_TABLES['shop.products']} p
            LEFT JOIN {$_TABLES['shop.categories']} c
                ON c.cat_id = p.cat_id ";
    $today = SHOP_now()->format('Y-m-d');
    if ($id == '*') {
        $where = ' WHERE 1 = 1 ';
    } else {
        $where = " WHERE (p.id = '" . DB_escapeString($id) . "')";
    }
    $where .= " AND p.enabled = 1 AND c.enabled = 1 AND avail_beg <= '$today' AND avail_end >= '$today' ";
    $where .= $cat_where;   // set above during check for shop_enabled setting
    if (isset($options['cat_id'])) {
        $where .= ' AND p.cat_id = ' . (int)$options['cat_id'];
    }
    $sql .= $where;
    $res = DB_query($sql);

    $props = array();
    while ($A = DB_fetchArray($res, false)) {
        foreach ($properties as $p) {
            switch ($p) {
            case 'introtext':
                $props[$p] = $A['short_description'];
                break;
            case 'bodytext':
                $props[$p] = $A['description'];
                break;
            case 'description':
                $props[$p] = $A['short_description'] . ' ' . $A['description'];
                break;
            case 'title':
                $props[$p] = $A['name'];
                break;
            case 'searchidx':
                $props[$p] = $A['name'] . ' ' .
                        $A['short_description'] . ' ' .
                        $A['description'] . ' ' .
                        $A['keywords'];
                break;
            case 'content':
                $props[$p] = $A['short_description'] . ' ' .
                            $A['description'];
                break;
            case 'date':
                $props[$p] = $A['unixtime'];
                break;
            case 'perms':
                $props['perms'] = array(
                    'owner_id' => 2,    // admin
                    'group_id' => $A['grp_access'],
                    'perm_owner' => 3,
                    'perm_group' => 2,
                    'perm_members' => 1,
                    'perm_anon' => 1,
                );
                break;
            default:
                $props[$p] = isset($A[$p]) ? $A[$p] : NULL;
                break;
            }
        }
        $props['url'] = COM_buildUrl($_CONF['site_url'] .
                    '/shop/detail.php?id=' . urlencode($A['id']));
        $mapped = array();
        foreach ($props as $key => $value) {
            if ($id == '*') {
                if ($value != '') {
                    $mapped[$key] = $value;
                }
            } else {
                $mapped[$key] = $value;
            }
        }

        if ($id == '*') {
            $retval[] = $mapped;
        } else {
            $retval = $mapped;
            break;
        }
    }

    if (($id != '*') && (count($retval) == 1)) {
        $tRet = array_values($retval);
        $retval = $tRet[0];
    }
    if ($retval === '' || (is_array($retval) && count($retval) == 0)) {
        return NULL;
    } else {
        return $retval;
    }
}


/**
 * Export user data when requested.
 *
 * @param   integer $uid        User ID
 * @param   string  $email      User Email Address (not used)
 * @param   string  $username   User Name (not used)
 * @param   string  $ip         IP address (not used)
 * @return  string              XML-formatted data
 */
function plugin_privacy_export_shop($uid, $email='', $username='', $ip='')
{
    $U = \Shop\UserInfo::getInstance($uid);

    $retval = "<shop>\n";

    foreach ($U->addresses as $addr) {
        foreach (\Shop\UserInfo::Fields() as $fld) {
            if ($addr[$fld] != '') {
                $d = addSlashes(htmlentities($addr[$fld]));
                $retval .= '<'.$fld.'>'.$d.'</'.$fld.">\n";
            }
        }
    }
    $retval .= "</shop>\n";

    if ( function_exists('tidy_repair_string')) {
        $retval = tidy_repair_string($retval, array('input-xml' => 1));
    }
    return $retval;
}


/**
 * Actions to perform when notified by a plugin that an item has been saved.
 * - Delete cached encrypted buttons
 *
 * @param  string  $id     Item ID
 * @param  string  $type   Item Type, e.g. plugin name
 * @param  string  $old_id Old ID, in case the ID was changed
 */
function plugin_itemsaved_shop($id, $type, $old_id = '')
{
    global $_TABLES;

    // Just deleting cached buttons, so don't currently care about new ID
    if ($old_id != $id) $id = $old_id;
    $type = DB_escapeString($type);
    $id = DB_escapeString($id);
    $sql = "DELETE FROM {$_TABLES['shop.buttons']}
            WHERE pi_name = '$type' AND item_id = '{$id}'";
    DB_query($sql);
}


/**
 * Get an array variable, first checking whether it's set.
 *
 * @param   array   $A          Array where var should be
 * @param   string  $varname    Name of array element
 * @param   string  $type       Type of return value
 * @param   mixed   $default    Default value, if any
 * @return  array       Array element or NULL, cast to $type
 */
function SHOP_getVar($A, $varname, $type = 'string', $default = NULL)
{
    if (!isset($A[$varname])) {
        return $default;
    } else {
        $val = $A[$varname];
    }
    switch ($type) {
    case 'int':
    case 'integer':
        return (int)$val;
        break;
    case 'bool':
        return (bool)$val;
        break;
    case 'float':
        return (float)$val;
        break;
    case 'array':
        if ($val === NULL) {
            $val = array();
        } elseif (!is_array($val)) {
            $val = array($val);
        }
        return $val;
        break;
    case 'object':
        return $val;
    case 'string':
    default:
        return (string)$val;
        break;
    }
}


/**
 * Get the sales tax rate to charge.
 * Currently just returns the globally-configured rate.
 *
 * @return  float   Tax Rate
 */
function SHOP_getTaxRate()
{
    global $_SHOP_CONF;

    return SHOP_getVar($_SHOP_CONF, 'tax_rate', 'float');
}


/**
 * Helper function to get a template object.
 * If a name is supplied as a string with no ID, an empty template
 * object will be returned with the default path set.
 *
 * @param   string  $name   Base name of template file, e.g. "editor"
 * @param   string  $id     ID of template, e.g. "editform"
 * @param   string  $path   Optional path if not "templates/"
 * @return  s tring      Full filename, e.g. "editor.uikit.thtml"
 */
function SHOP_getTemplate($name, $id='', $path='')
{
    global $_SHOP_CONF;

    $templates = array();

    // Put parameters into an array if not already.
    if (!is_array($name) && !empty($id)) {
        $templates = array($id => $name);
    } elseif (is_array($name)) {
        $templates = $name;
    }

    // Add additional path info if needed
    if (!empty($path) && $path[0] == '/') $path = substr($path, 1);
    if (substr($path, 0, 9) != 'templates') {
        $path = 'templates/' . $path;
    }

    // May use "switch" later, for now there are only two options
    foreach ($templates as $t_id =>$t_name) {
        $templates[$t_id] = "$t_name.thtml";
    }
    $T = new Template(__DIR__ . '/' . $path);
    $T->set_file($templates);
    return $T;
}


/**
 * Check that the currently installed version matches the code version.
 * This is to disable functions that might not work due to a mismatch.
 * Just check the major.minor.revision values.  Any fourth element is ignored.
 *
 * @param   string  $required   Required version, default to code version
 * @return  boolean     True if current >= installed version, False if not
 */
function SHOP_isMinVersion($required = '')
{
    global $_SHOP_CONF, $_PLUGIN_INFO;

    // If not specified, use the current code version as required.
    if ($required == '') $required = $_SHOP_CONF['pi_version'];

    $pi_name = $_SHOP_CONF['pi_name'];
    if (isset($_PLUGIN_INFO[$pi_name])) {
        if (is_array($_PLUGIN_INFO[$pi_name])) {
            // glFusion > = 1.6.6
            $installed = $_PLUGIN_INFO[$pi_name]['pi_version'];
        } else {
            $installed = $_PLUGIN_INFO[$pi_name];
        }
    }
    if (!COM_checkVersion($installed, $required)) {
        if (plugin_ismoderator_shop()) {
            COM_setMsg("Shop Plugin has not been updated from $installed to $required", 'error');
        }
        return false;
    } else {
        return true;
    }
}


/**
 * Get a datetime display in the local timezone with a popup showing UTC.
 *
 * @param   object  $Dt     Date object
 * @return  string          HTML for date display with tooltip
 */
function SHOP_dateTooltip($Dt)
{
    return '<span class="tooltip" title="' . $Dt->toMySQL(false) . ' UTC">'
            . $Dt->toMySQL(true) . '</span>';
}


/**
 * Set a url in the session to indicate where to return after taking an action.
 *
 * @param   string  $url    URL to save, current page url by default.
 */
function SHOP_setUrl($url='')
{
    if ($url == '') {
        $url = COM_getCurrentURL();
    }
    SESS_setVar('shop_ret_url', $url);
}


/**
 * Get the URL previously set in the session variable.
 *
 * @param   string  $def_url    Default url if one is not set in the session
 * @param   boolean $keep       True to keep the url in the session var
 * @return  string  URL
 */
function SHOP_getUrl($def_url = '', $keep=true)
{
    $url = SESS_getVar('shop_ret_url');
    if (!$url) {
        if ($def_url !== '') {
            $url = $def_url;
        } else {
            // final failsafe
            $url = SHOP_URL;
        }
    } elseif (!$keep) {
        // This is a one-time use value
        SESS_unSet('shop_ret_url');
    }
    return $url;
}


/**
 * Display an error message.
 * Uses glFusion's typography to display an "alert" type message.
 * The provided message may be a single message string or array of strings.
 * An array will be formatted as an unnumbered list.
 *
 * @param   array|string    $msg    Single message string or array
 * @param   string          $title  Optional title string, shown above list
 * @return  string          Complete error message
 */
function SHOP_errMsg($msg, $title = '')
{
    if (empty($msg)) return '';

    $retval = '<span class="alert shopErrorMsg">' . "\n";
    if (!empty($title)) {
        $retval .= "<p>$title</p>\n";
    }

    if (is_array($msg)) {
        $retval .= '<ul>';
        foreach ($msg as $m) {
            $retval .= '<li>' . $m . '</li>' . LB;
        }
        $retval .= '<ul>' . LB;
    } else {
        $retval .= $msg;
    }
    $retval .= "/span>\n";
    return $retval;
}

require_once __DIR__ . '/services.inc.php';

?>
