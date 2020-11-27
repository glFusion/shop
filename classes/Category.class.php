<?php
/**
 * Class to manage product categories.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class for product categories.
 * Each product belongs to one category.
 * @package shop
 */
class Category
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Key field name.
     * @var string */
    protected static $TABLE = 'shop.categories';

    /** ID Field name.
     * @var string */
    protected static $F_ID = 'cat_id';

    /** Category ID.
     * @var integer */
    private $cat_id = 0;

    /** Parent Category ID.
     * @var integer */
    private $parent_id = 0;

    /** Category Name.
     * @var string */
    private $cat_name = '';

    /** Category text description.
     * @var string */
    private $dscp = '';

    /** Category group access. Default is "all users".
     * @var integer */
    private $grp_access = 2;

    /** Image filename.
     * @var string */
    private $image = '';

    /** Enabled flag.
     * @var boolean */
    private $enabled = 1;

    /** Display name for option lists. Includes indenting.
     * @var string */
    private $disp_name = '';

    /** Left value for MPTT.
     * @var integer */
    private $lft = 0;

    /** Right value for MPTT.
     * @var integer */
    private $rgt = 0;

    /** Google taxonomy string.
     * @var string */
    private $google_taxonomy = '';

    /** Indicate whether the current user is an administrator.
     * @var boolean */
    private $isAdmin;

    /** Indicate whether this is a new record or not.
     * @var boolean */
    private $isNew;

    /** Array of error messages, to be accessible by the calling routines.
     * @var array */
    private $Errors = array();

    /** Base tag for caching.
     * @var string */
    private static $tag = 'ppcat_';


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id Optional type ID
     */
    public function __construct($id=0)
    {
        global $_USER, $_VARS;

        $this->isNew = true;

        $this->cat_id = 0;
        $this->parent_id = 0;
        $this->cat_name = '';
        $this->dscp = '';
        $this->grp_access = 2;  // All users have access by default
        $this->image = '';
        $this->enabled = 1;
        $this->disp_name = '';
        $this->lft = 0;
        $this->rgt = 0;
        $this->google_taxonomy = '';
        if (is_array($id)) {
            $this->SetVars($id, true);
        } elseif ($id > 0) {
            $this->cat_id = $id;
            if (!$this->Read()) {
                $this->cat_id = 0;
            }
        }
        $this->isAdmin = plugin_ismoderator_shop() ? 1 : 0;
    }


    /**
     * Sets all variables to the matching values from the supplied array.
     *
     * @param   array   $row    Array of values, from DB or $_POST
     * @param   boolean $fromDB True if read from DB, false if from a form
     */
    public function SetVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        $this->cat_id = $row['cat_id'];
        $this->parent_id = $row['parent_id'];
        $this->dscp = $row['description'];
        $this->enabled = $row['enabled'];
        $this->cat_name = $row['cat_name'];
        $this->grp_access = $row['grp_access'];
        $this->disp_name = isset($row['disp_name']) ? $row['disp_name'] : $row['cat_name'];
        $this->lft = isset($row['lft']) ? $row['lft'] : 0;
        $this->rgt = isset($row['rgt']) ? $row['rgt'] : 0;
        $this->google_taxonomy = $row['google_taxonomy'];
        $this->image = $row['image'];
    }


    /**
     * Set the display name.
     *
     * @param   string  $disp_name  Displya name
     * @return  object  $this
     */
    public function setDisplayName($disp_name)
    {
        $this->disp_name = $disp_name;
        return $this;
    }


    /**
     * Read a specific record and populate the local values.
     * Caches the object for later use.
     *
     * @param   integer $id Optional ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->cat_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query("SELECT *
                    FROM {$_TABLES['shop.categories']}
                    WHERE cat_id='$id'");
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row, true);
            $this->isNew = false;
            Cache::set(self::_makeCacheKey($id), $this, 'categories');
            return true;
        }
    }


    /**
     * Get a category instance.
     * Checks cache first and creates a new object if not found.
     *
     * @param   integer $cat_id     Category ID
     * @return  object              Category object
     */
    public static function getInstance($cat_id)
    {
        static $cats = array();
        if (!isset($cats[$cat_id])) {
            $key = self::_makeCacheKey($cat_id);
            $cats[$cat_id] = Cache::get($key);
            if (!$cats[$cat_id]) {
                $cats[$cat_id] = new self($cat_id);
            }
        }
        return $cats[$cat_id];
    }


    /**
     * Determine if this category is a new record, or one that was not found
     *
     * @return  integer     1 if new, 0 if existing
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Save the current values to the database.
     *
     * @param  array   $A      Optional array of values from $_POST
     * @return boolean         True if no errors, False otherwise
     */
    public function Save($A = array())
    {
        global $_TABLES, $_SHOP_CONF;

        if (is_array($A)) {
            $this->SetVars($A);
        }

        // For new images, move the image from temp storage into the
        // main category image space.
        if ($this->isNew && $this->image != '') {
            $src_img = "{$_SHOP_CONF['tmpdir']}images/temp/{$this->image}";
            if (is_file($src_img)) {
                $dst_img = "{$_SHOP_CONF['catimgpath']}/{$this->image}";
                if (!@rename($src_img, $dst_img)) {
                    // If image not found, unset the image value.
                    $this->image = '';
                }
            }
        }

        // Insert or update the record, as appropriate, as long as a
        // previous error didn't occur.
        if (empty($this->Errors)) {
            if ($this->isNew) {
                $sql1 = "INSERT INTO {$_TABLES['shop.categories']} SET ";
                $sql3 = '';
            } else {
                $sql1 = "UPDATE {$_TABLES['shop.categories']} SET ";
                $sql3 = " WHERE cat_id='{$this->cat_id}'";
            }
            $sql2 = "parent_id='" . $this->parent_id . "',
                cat_name='" . DB_escapeString($this->cat_name) . "',
                description='" . DB_escapeString($this->dscp) . "',
                enabled='{$this->enabled}',
                grp_access ='{$this->grp_access}',
                image='" . DB_escapeString($this->image) . "',
                google_taxonomy = '" . DB_escapeString($this->google_taxonomy) . "'";
            $sql = $sql1 . $sql2 . $sql3;
            //echo $sql;die;
            //COM_errorLog($sql);
            SHOP_log($sql, SHOP_LOG_DEBUG);
            DB_query($sql);
            if (!DB_error()) {
                if ($this->isNew) {
                    $this->cat_id = DB_insertID();
                }
                if (isset($_POST['old_parent']) && $_POST['old_parent'] != $this->parent_id) {
                    self::rebuildTree();
                }
                /*if (isset($_POST['old_grp']) && $_POST['old_grp'] > 0 &&
                        $_POST['old_grp'] != $this->grp_access) {
                    $this->_propagatePerms($_POST['old_grp']);
                }*/
                Cache::clear('categories');
                Cache::clear('sitemap');
            } else {
                $this->AddError('Failed to insert or update record');
            }
        }

        if (empty($this->Errors)) {
            return true;
        } else {
            return false;
        }
    }   // function Save()


    /**
     * Propagate permissions to sub-categories.
     *
     * @param   integer $grp_id     Group ID to allow permission
     */
    private function _propagatePerms($grp_id)
    {
        global $_TABLES;

        if ($grp_id == $this->grp_access) return;   // nothing to do

        $c = self::getTree($this->cat_id);
        $upd_cats = array();
        foreach ($c as $cat) {
            if ($cat->cat_id == $this->cat_id) continue; // already saved
            $upd_cats[] = $cat->cat_id;
        }
        if (!empty($upd_cats)) {
            $upd_cats = implode(',', $upd_cats);
            $sql = "UPDATE {$_TABLES['shop.categories']}
                    SET grp_access = {$this->grp_access}
                    WHERE cat_id IN ($upd_cats)";
            Cache::clear('categories');
            Cache::clear('sitemap');
            DB_query($sql);
        }
    }


    /**
     *  Delete the current category record from the database.
     */
    public function Delete()
    {
        global $_TABLES, $_SHOP_CONF;

        if ($this->cat_id <= 1)
            return false;

        $this->deleteImage(false);
        DB_delete($_TABLES['shop.categories'], 'cat_id', $this->cat_id);
        PLG_itemDeleted($this->cat_id, 'shop_category');
        Cache::clear('categories');
        Cache::clear('sitemap');
        $this->cat_id = 0;
        return true;
    }


    /**
     * Deletes a single image from disk.
     * $del_db is used to save a DB call if this is called from Save().
     *
     * @param   boolean $del_db     True to update the database.
     */
    public function deleteImage($del_db = true)
    {
        global $_TABLES, $_SHOP_CONF;

        $filename = $this->image;
        if (is_file("{$_SHOP_CONF['catimgpath']}/{$filename}")) {
            @unlink("{$_SHOP_CONF['catimgpath']}/{$filename}");
        }

        if ($del_db) {
            DB_query("UPDATE {$_TABLES['shop.categories']}
                    SET image=''
                    WHERE cat_id='" . $this->cat_id . "'");
            Cache::clear('categories');
            Cache::clear('sitemap');
        }
        $this->image = '';
    }


    /**
     *  Determines if the current record is valid.
     *
     *  @return boolean     True if ok, False when first test fails.
     */
    public function isValidRecord()
    {
        // Check that basic required fields are filled in
        if ($this->cat_name == '') {
            return false;
        }

        return true;
    }


    /**
     *  Creates the edit form.
     *
     *  @param  integer $id Optional ID, current record used if zero
     *  @return string      HTML for edit form
     */
    public function showForm()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        // Clean up old upload images that never got assigned to a category
        Images\Category::cleanUnassigned();

        $T = new Template;
        $T->set_file('category', 'category_form.thtml');
        $id = $this->cat_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit'] . ': ' . $this->cat_name);
            $T->set_var('cat_id', $id);
            //$not = 'NOT';
            //$items = $id;
        } else {
            $retval = COM_startBlock($LANG_SHOP['create_category']);
            $T->set_var('cat_id', '');
            //$not = '';
            //$items = '';
        }

        // If this is the root category, don't display the option list.
        if ($this->cat_id > 0 && $this->parent_id == 0) {
            $T->set_var('parent_sel', false);
        } else {
            $T->set_var('parent_sel', self::optionList($this->parent_id, $this->cat_id));
        }

        $T->set_var(array(
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'cat_name'      => $this->cat_name,
            'description'   => $this->dscp,
            'ena_chk'       => $this->enabled == 1 ? 'checked="checked"' : '',
            'old_parent'    => $this->parent_id,
            'old_grp'       => $this->grp_access,
            'group_sel'     => SEC_getGroupDropdown($this->grp_access, 3, 'grp_access'),
            'doc_url'       => SHOP_getDocURL('category_form'),
            'google_taxonomy' => $this->google_taxonomy,
            'nonce'         => Images\Category::makeNonce(),
        ) );
        if ($this->image != '') {
            $T->set_var(array(
                'tn_url'    => Images\Category::getThumbUrl($this->image)['url'],
                'img_url'   => $this->getImage()['url']
            ) );
        }

        if (!self::isUsed($this->cat_id)) {
            $T->set_var('can_delete', 'true');
        }

        // Display any sales pricing for this category
        $DT = new Template;
        $DT->set_file('stable', 'sales_table.thtml');
        $DT->set_var('edit_sale_url', SHOP_ADMIN_URL . '/index.php?sales');
        $DT->set_block('stable', 'SaleList', 'SL');
        foreach (Sales::getCategory($this->cat_id) as $D) {
            $amount = $D->getFormattedValue();
            $DT->set_var(array(
                'sale_name' => htmlspecialchars($D->getName()),
                'sale_start' => $D->getStart(),
                'sale_end'  => $D->getEnd(),
                'sale_type' => $D->getValueType(),
                'sale_amt'  => $amount,
            ) );
            $DT->parse('SL', 'SaleList', true);
        }
        $DT->parse('output', 'stable');
        $T->set_var('sale_prices', $DT->finish($DT->get_var('output')));

        /*
        // Might want this later to set default buttons per category
        $T->set_block('product', 'BtnRow', 'BRow');
        foreach ($LANG_SHOP['buttons'] as $key=>$value) {
            $T->set_var(array(
                'btn_type'  => $key,
                'btn_chk'   => isset($this->buttons[$key]) ?
                                'checked="checked"' : '',
                'btn_name'  => $value,
            ));
            $T->parse('BRow', 'BtnRow', true);
        }*/

        // If there's an image for this category, display it and offer
        // a link to delete it
        if ($this->image != '') {
            $T->set_var('img_url', $this->getImage()['url']);
        }

        $retval .= $T->parse('output', 'category');

        SEC_setCookie(
            $_CONF['cookie_name'].'fckeditor',
            SEC_createTokenGeneral('advancededitor'),
            time() + 1200
        );

        $retval .= COM_endBlock();
        return $retval;

    }   // function showForm()


    /**
     * Toogles a boolean value to the opposite of the current value.
     *
     * @param   integer $oldvalue   Old value to change
     * @param   string  $varname    Field name to change
     * @param   integer $id         ID number of element to modify
     * @return  integer             New value, or old value upon failure
     */
    protected static function do_toggle($oldvalue, $varname, $id)
    {
        $newval = self::Toggle($oldvalue, $varname, $id);
        if ($newval != $oldvalue) {
            Cache::clear('categories');
            Cache::clear('sitemap');
        }
        return $newval;
    }


    /**
     * Sets the "enabled" field to the specified value.
     *
     * @param   integer $oldvalue   Original value to be changed
     * @param   integer $id         ID number of element to modify
     * @return  integer         New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        return self::do_toggle($oldvalue, 'enabled', $id);
    }


    /**
     * Check if there are any products directly under a category ID.
     *
     * @param   integer $cat_id     Category ID to check
     * @return  integer     Number of products under the category
     */
    public static function hasProducts($cat_id)
    {
        global $_TABLES;

        return DB_count($_TABLES['shop.prodXcat'], 'cat_id', (int)$cat_id);
    }


    /**
     * Determine if a category is used by any products.
     * Used to prevent deletion of a category if it would orphan a product.
     *
     *  @param  integer $cat_id     Category ID to check.
     * @return  boolean     True if used, False if not
     */
    public static function isUsed($cat_id=0)
    {
        global $_TABLES;

        $cat_id = (int)$cat_id;

        // Check if any products are under this category
        if (self::hasProducts($cat_id) > 0) {
            return true;
        }

        // Check if any categories are under this one.
        if (DB_count($_TABLES['shop.categories'], 'parent_id', $cat_id) > 0) {
            return true;
        }

        $C = self::getRoot();
        if ($C->cat_id == $cat_id) {
            return true;
        }

        return false;
    }


    /**
     * Add an error message to the Errors array.
     * Also could be used to log certain errors or perform other actions.
     *
     * @param   string  $msg    Error message to append
     */
    public function AddError($msg)
    {
        $this->Errors[] = $msg;
    }


    /**
     *  Create a formatted display-ready version of the error messages.
     *
     *  @return string      Formatted error messages.
     */
    public function PrintErrors()
    {
        $retval = '';

        foreach($this->Errors as $key=>$msg) {
            $retval .= "<li>$msg</li>\n";
        }
        return $retval;
    }


    /**
     * Determine if the current user has access to this category.
     *
     * @param   array|null  $groups     Array of groups, needed for sitemap
     * @return  boolean     True if user has access, False if not
     */
    public function hasAccess($groups = NULL)
    {
        global $_GROUPS;

        if ($groups === NULL) {
            $groups = $_GROUPS;
        }
        if ($this->enabled && in_array($this->grp_access, $groups)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get the URL to the category image.
     * Returns an empty string if no image defined or found.
     *
     * @return  string  URL of image, empty string if file not found
     */
    public function getImage()
    {
        return Images\Category::getUrl($this->image);
    }


    /**
     * Create the breadcrumb display, with links.
     * Creating a static breadcrumb field in the category record won't
     * work because of the group access control. If a category is
     * encountered that the current user can't access, it is simply
     * skipped.
     *
     * @param   integer $id ID of current category
     * @return  string      Location string ready for display
     */
    public function Breadcrumbs()
    {
        global $LANG_SHOP;

        $T = new Template;
        $T->set_file('cat_bc_tpl', 'cat_bc.thtml');
        $T->set_var('pi_url', SHOP_URL . '/index.php');
        $breadcrumbs = array(
            COM_createLink(
                $LANG_SHOP['home'],
                SHOP_URL
            )
        );
        if ($this->cat_id > 0 && !$this->isRoot()) {
            // A specific subcategory is being viewed
            $cats = $this->getPath();
            foreach ($cats as $cat) {
                // Root category already shown in top header
                if ($cat->isRoot()) continue;
                // Don't show a link if the user can't access it.
                if (!$cat->hasAccess()) continue;
                $breadcrumbs[] = COM_createLink(
                    $cat->cat_name,
                    SHOP_URL . '/index.php?category=' . (int)$cat->cat_id
                );
            }
        }
        $T->set_block('cat_bc_tpl', 'cat_bc', 'bc');
        foreach ($breadcrumbs as $bc_url) {
            $T->set_var('bc_url', $bc_url);
            $T->parse('bc', 'cat_bc', true);
        }
        $children = $this->getChildren();
        if (!empty($children)) {
            $T->set_var('bc_form', true);
            $T->set_block('cat_bc_tpl', 'cat_sel', 'sel');
            foreach ($children as $c) {
                if (!$c->hasAccess()) continue;
                $T->set_var(array(
                    'cat_id'    => $c->cat_id,
                    'cat_dscp'  => $c->cat_name,
                ) );
                $T->parse('sel', 'cat_sel', true);
            }
        }
        $T->parse('output', 'cat_bc_tpl');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Helper function to create the cache key.
     *
     * @param   string  $id     Unique cache ID
     * @return  string  Cache key
     */
    private static function _makeCacheKey($id)
    {
        return self::$tag . $id;
    }


    /**
     * Read all the categories into a static array.
     *
     * @param   integer $root_id    Root category ID
     * @param   string  $prefix     Prefix to prepend to sub-category display names
     * @return  array           Array of category objects
     */
    public static function getTree($root_id=0, $prefix='&nbsp;')
    {
        global $_TABLES;

        if (!SHOP_isMinVersion()) return array();

        $between = '';
        $root_id = (int)$root_id;
        $p = $prefix == '&nbsp;' ? 'nbsp_' : $prefix . '_';
        $cache_key = self::_makeCacheKey('cat_tree_' . $p . (string)$root_id);
        $All = Cache::get($cache_key);
        if ($All === NULL) {    // not found in cache, build the tree
            if ($root_id > 0) {
                $Root = self::getInstance($root_id);
                $between = " AND parent.lft BETWEEN {$Root->lft} AND {$Root->rgt}";
            }
            $prefix = DB_escapeString($prefix);
            $sql = "SELECT node.cat_id,
                    CONCAT( REPEAT( '$prefix', (COUNT(parent.cat_name) - 1) ), node.cat_name) AS disp_name
                FROM {$_TABLES['shop.categories']} AS node,
                    {$_TABLES['shop.categories']} AS parent
                WHERE node.lft BETWEEN parent.lft AND parent.rgt
                $between
                GROUP BY node.cat_id, node.cat_name
                ORDER BY node.lft";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $All[$A['cat_id']] = new self($A['cat_id']);
                $All[$A['cat_id']]->setDisplayName($A['disp_name']);
            }
            Cache::set($cache_key, $All, 'categories');
        }
        return $All;
    }


    /**
     * Get the full path to a category, optionally including sub-categories.
     *
     * @param   boolean $incl_sub   True to include sub-categories
     * @return  array       Array of category objects
     */
    public function getPath($incl_sub = false)
    {
        $key = 'cat_path_' . $this->cat_id . '_' . (int)$incl_sub;
        $path = Cache::get($key);
        if (!$path) {
            $cats = self::getTree();    // need the full tree to find parents
            $path = array();

            // if node doesn't exist, return. Don't bother setting cache
            if (!isset($cats[$this->cat_id])) return $path;

            $Cat = $cats[$this->cat_id];      // save info for the current node
            foreach ($cats as $id=>$C) {
                if ($C->lft < $Cat->lft && $C->rgt > $Cat->rgt) {
                    $path[$C->cat_id] = $C;
                }
            }

            // Now append the node, or the subtree
            if ($incl_sub) {
                $subtree = self::getTree($this->cat_id);
                foreach ($subtree as $id=>$C) {
                    $path[$C->cat_id] = $C;
                }
            } else {
                $path[$Cat->cat_id] = $Cat;
            }
            Cache::set($key, $path, 'categories');
        }
        return $path;
    }


    /**
     * Get the options for a selection list.
     * Used in the product form and to select a parent category.
     * $exclude indicates a category to disable, to prevent selecting a
     * category as its own parent.
     *
     * @uses    self::getTree()
     * @param   integer $sel        Selected category ID
     * @param   integer $exclude    Category to disable in the list
     * @return  string          Option elements for Select
     */
    public static function optionList($sel = 0, $exclude = 0)
    {
        $Cats = self::getTree(0, '-');
        $opts = '';
        foreach ($Cats as $Cat) {
            $disabled = $Cat->cat_id == $exclude ? 'disabled="disabled"' : '';
            $selected = $Cat->cat_id == $sel ? 'selected="selected"' : '';
            $opts .= '<option value="' . $Cat->cat_id . '" ' . $disabled .
                    ' ' . $selected . '>' . $Cat->disp_name . '</option>' . LB;
        }
        return $opts;
    }


    /**
     * Get the root category.
     * Depending on how Shop was installed or updated this might not be #1.
     *
     * @return  mixed   Category object
     */
    public static function getRoot()
    {
        global $_TABLES;

        $parent = (int)DB_getItem(
            $_TABLES['shop.categories'],
            'cat_id',
            'parent_id = 0'
        );
        return self::getInstance($parent);
    }


    /**
     * Helper function to check if this is the Root category.
     *
     * @return  boolean     True if this category is Root, False if not
     */
    public function isRoot()
    {
        return $this->parent_id == 0;
    }


    /**
     * Rebuild the MPT tree starting at a given parent and "left" value.
     *
     * @param  integer $parent     Starting category ID
     * @param  integer $left       Left value of the given category
     * @return integer         New Right value (only when called recursively)
     */
    public static function rebuildTree($parent = 0, $left = 1)
    {
        global $_TABLES;

        // If parent is undefined, get the root category ID
        if ($parent == 0) {
            $parent = self::getRoot();
            $parent = $parent->cat_id;
        }

        // the right value of this node is the left value + 1
        $right = $left + 1;

        // get all children of this node
        $sql = "SELECT cat_id FROM {$_TABLES['shop.categories']}
                WHERE parent_id ='$parent'";
        $result = DB_query($sql);
        while ($row = DB_fetchArray($result, false)) {
            // recursive execution of this function for each
            // child of this node
            // $right is the current right value, which is
            // incremented by the rebuild_tree function
            $right = self::rebuildTree($row['cat_id'], $right);
        }

        // we've got the left value, and now that we've processed
        // the children of this node we also know the right value
        $sql1 = "UPDATE {$_TABLES['shop.categories']}
                SET lft = '$left', rgt = '$right'
                WHERE cat_id = '$parent'";
        DB_query($sql1);
        Cache::clear('categories');
        Cache::clear('sitemap');

        // return the right value of this node + 1
        return $right + 1;
    }


    /**
     * Get the immediate children of a category.
     *
     * @param   integer $cat_id     Parent category ID
     * @return  array       Array of child categories
     */
    public function getChildren()
    {
        $retval = array();

        // Get the category tree, including the parent.
        $tree = self::getTree($this->cat_id);
        // Make sure the parent exists unless root was requested.
        if (empty($tree)) {
            return $retval;
        }
        // Remove the parent category and return the rest.
        unset($tree[$this->cat_id]);
        foreach ($tree as $cat_id=>$C) {
            if ($C->parent_id == $this->cat_id) {
                $retval[$cat_id] = $C;
            }
        }
        return $retval;
    }


    /**
     * Category Admin List View.
     *
     * @param   integer $cat_id     Optional category ID to limit listing
     * @return  string      HTML for the category list.
     */
    public static function adminList($cat_id=0)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT
                cat.cat_id, cat.cat_name, cat.description, cat.enabled,
                cat.grp_access, parent.cat_name as pcat
            FROM {$_TABLES['shop.categories']} cat
            LEFT JOIN {$_TABLES['shop.categories']} parent
            ON cat.parent_id = parent.cat_id";

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'cat_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['category'],
                'field' => 'cat_name',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['description'],
                'field' => 'description',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['parent_cat'],
                'field' => 'pcat',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['visible_to'],
                'field' => 'grp_access',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_ADMIN['delete'] .
                    '&nbsp;<i class="uk-icon uk-icon-question-circle tooltip" title="' .
                    $LANG_SHOP['del_cat_instr'] . '"></i>',
                'field' => 'delete', 'sort' => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'cat_id',
            'direction' => 'asc',
        );

        $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_category'],
            SHOP_ADMIN_URL . '/index.php?editcat=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );

        $query_arr = array(
            'table' => 'shop.categories',
            'sql' => $sql,
            'query_fields' => array('cat.cat_name', 'cat.description'),
            'default_filter' => 'WHERE 1=1',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?categories=x',
        );

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_catlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }



    /**
     * Get an individual field for the category list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $_TABLES, $LANG_ADMIN;

        $retval = '';
        static $grp_names = array();

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_SHOP['edit'] . '"></i>',
                SHOP_ADMIN_URL . "/index.php?editcat=x&amp;id={$A['cat_id']}"
            );
            break;

        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['cat_id']}\"
                onclick='SHOP_toggle(this,\"{$A['cat_id']}\",\"enabled\",".
                "\"category\");' />" . LB;
            break;

        case 'grp_access':
            $fieldvalue = (int)$fieldvalue;
            if (!isset($grp_names[$fieldvalue])) {
                $grp_names[$fieldvalue] = DB_getItem(
                    $_TABLES['groups'],
                    'grp_name',
                    "grp_id = $fieldvalue"
                );
            }
            $retval = $grp_names[$fieldvalue];
            break;

        case 'delete':
            if (!self::isUsed($A['cat_id'])) {
                $retval .= COM_createLink(
                    '<i class="uk-icon uk-icon-remove uk-text-danger"></i>',
                    SHOP_ADMIN_URL. '/index.php?deletecat=x&amp;cat_id=' . $A['cat_id'],
                    array(
                        'onclick' => "return confirm('{$LANG_SHOP['q_del_item']}');",
                        'title' => $LANG_SHOP['del_item'],
                        'class' => 'tooltip',
                    )
                );
            }
            break;

        case 'description':
            $retval = strip_tags($fieldvalue);
            if (utf8_strlen($retval) > 80) {
                $retval = substr($retval, 0, 80 ) . '...';
            }
            break;

        case 'cat_name':
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            $retval = COM_createlink(
                $retval,
                SHOP_URL . '/index.php?category=' . $A['cat_id'],
                array(
                    'title' => $LANG_SHOP['storefront'],
                    'class' => 'tooltip',
                )
            );
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Get the google taxonomy for this category.
     * If none defined, search for one in the parent categories.
     *
     * @return  string  Google taxonomy, empty string if none found
     */
    public function getGoogleTaxonomy()
    {
        $Paths = $this->getPath();
        $Paths = array_reverse($Paths);
        foreach ($Paths as $Path) {
            if ($Path->google_taxonomy != '') {
                return $Path->google_taxonomy;
            }
        }
        return '';
    }


    /**
     * Get all categories that are related to a given product ID.
     *
     * @param   integer $prod_id    Product ID
     * @return  array       Array of Category objexts
     */
    public static function getByProductId($prod_id)
    {
        global $_TABLES;

        $retval = array();
        if (Product::isPluginItem($prod_id)) {
            $Cat = self::getRoot();
            return array($Cat->getID() => $Cat);
        }

        $prod_id = (int)$prod_id;
        if ($prod_id < 1) {
            // No categories selected if this is a new product
            return array();
        }
        $cache_key = 'shop.categories_' . $prod_id;
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $sql = "SELECT cat_id FROM {$_TABLES['shop.prodXcat']}
                WHERE product_id = $prod_id";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['cat_id']] = self::getInstance($A['cat_id']);
            }

            // If no categories are found, add the root category to be sure
            // there is one category for the product.
            if (empty($retval)) {
                $Cat = self::getRoot();
                $retval[$Cat->getID()] = $Cat;
            }
            Cache::set($cache_key, $retval, array('products', 'categories'));
        }
        return $retval;
    }


    /**
     * Load all categories from the database into an array.
     *
     * @return  array       Array of category objects
     */
    public static function getAll()
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT cat_id FROM {$_TABLES['shop.categories']}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['cat_id']] = self::getInstance($A['cat_id']);
        }
        return $retval;
    }


    /**
     * Get the record ID for a category.
     *
     * @return  integer     Category DB record ID
     */
    public function getID()
    {
        return $this->cat_id;
    }


    /**
     * Get the category name.
     *
     * @return  string  Category name
     */
    public function getName()
    {
        return $this->cat_name;
    }


    /**
     * Get the parent category ID.
     *
     * @return  integer     Parent ID
     */
    public function getParentID()
    {
        return (int)$this->parent_id;
    }


    /**
     * Get the category description.
     *
     * @return  string      Category description
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    /**
     * Delete product->category mappings when a product is deleted.
     *
     * @param   integer $prod_id    Product record ID
     */
    public static function deleteProduct($prod_id)
    {
        global $_TABLES;

        $prod_id = (int)$prod_id;
        DB_delete($_TABLES['shop.prodXcat'], 'product_id', $prod_id);
    }


    /**
     * Clone the categories for a product to a new product.
     *
     * @param   integer $src    Source product ID
     * @param   integer $dst    Destination product ID
     * @return  boolean     True on success, False on error
     */
    public static function cloneProduct($src, $dst)
    {
        global $_TABLES;

        $src = (int)$src;
        $dst = (int)$dst;
        // Clear target categories, the Home category is probably there.
        DB_delete($_TABLES['shop.prodXcat'], 'product_id', $dst);
        $sql = "INSERT INTO {$_TABLES['shop.prodXcat']} (product_id, cat_id)
            SELECT $dst, cat_id FROM {$_TABLES['shop.prodXcat']}
            WHERE product_id = $src";
        DB_query($sql, 1);
        return DB_error() ? false : true;
    }


    /**
     * Get the zone rule ID for this category.
     * Traverses the tree in reverse and returns the first rule found.
     * TODO: stub function during testing
     *
     * @return  integer     Applicable rule ID
     */
    public function getRuleID()
    {
        return 3;
    }
}

?>
