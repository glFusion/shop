<?php
/**
 * Class to interface with plugins for product information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Products;
use Shop\Currency;
use Shop\Models\ProductType;
use Shop\Models\CustomInfo;
use Shop\Config;
use Shop\Icon;
use Shop\Field;
use Shop\Template;
use Shop\Tooltipster;
use Shop\OrderItem;
use Shop\Models\IPN;


/**
 * Class for a plugin-supplied product.
 * @package shop
 */
class Plugin extends \Shop\Product
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Table key. Blank value will cause no action to be taken.
     * @var string */
    protected static $TABLE = 'shop.plugin_products';


    /** URL to product detail page, if any.
     * @var string */
    public $url;

    /** Plugin info with item_id and other vars.
     * @var array */
    public $pi_info;

    /** Plugin has its own detail page service function.
     * @var boolean */
    private $_have_detail_svc = false;

    /** Indicate that only a single unique purchase can be made.
     * Normally true for plugin products.
     * @var boolean */
    private $isUnique = true;

    /** Indicate whether this product can have a discount code applied.
     * Default is true for backward compatibility. Plugins can turn this off.
     * @var boolean */
    private $canApplyDC = true;

    /** Image URL. One image can be include for the catalog display.
     * @var string */
    private $img_url = '';

    /** Flag to indicate that the buyer can enter their own price value.
     * Mainly used for donations.
     * @var array */
    private $custom_price = false;

    /** Flag indicating that the product is valid.
     * Used to check if a good array was received from plugin_iteminfo_*.
     * @var boolean */
    private $is_valid = true;


    /**
     * Constructor.
     * Creates an object for a plugin product and gets data from the
     * plugin's service function
     *
     * @param   string  $id     Item ID - plugin:item_id|opt1,opt2...
     * @param   array   $mods   Array of modifiers from parent::getInstance()
     */
    public function __construct($id, $mods=array())
    {
        global $_USER, $_TABLES;

        $this->pi_info = array();
        $item = explode('|', $id);  // separate full item ID from option string
        $item_id = $item[0];
        $this->currency = Currency::getInstance();
        $this->item_id = $item_id;  // Full item id
        $this->id = $item_id;       // TODO: convert Product class to use item_id
        $item_parts = explode(':', $item_id);   // separate the plugin name and item ID
        $this->pi_name = DB_escapeString($item_parts[0]);
        array_shift($item_parts);         // Remove plugin name
        $this->pi_info['item_id'] = $item_parts;
        $this->product_id = $item_parts[0];
        $this->aff_apply_bonus = false;     // typical for plugins

        // Get the admin-supplied configs for the plugin
        $sql = "SELECT * FROM {$_TABLES[self::$TABLE]}
            WHERE pi_name = '{$this->pi_name}' LIMIT 1";
        $res = DB_query($sql);
        if ($res && DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $def_price = (float)$A['price'];
            $def_taxable = $A['taxable'] ? 1 : 0;
            $def_prod_type = (int)$A['prod_type'];
        } else {
            $def_price = 0;
            $def_taxable = 0;
            $def_prod_type = ProductType::PLUGIN;
        }

        // Get the user ID to pass to the plugin in case it's needed.
        if (!isset($mods['uid'])) {
            $mods['uid'] = $_USER['uid'];
        }
        $this->pi_info['mods'] = $mods;
        $this->prod_type = $def_prod_type;
        $this->taxable = $def_taxable;

        // Try to call the plugin's function to get product info.
        // TODO - Deprecate, this is legacy. Plugins should return product info
        // from plugin_iteminfo functions.
        $status = LGLIB_invokeService(
            $this->pi_name,
            'productinfo',
            $this->pi_info,
            $A,
            $svc_msg
        );
        if ($status == PLG_RET_OK) {
            $this->price = SHOP_getVar($A, 'price', 'float', $def_price);
            $this->name = SHOP_getVar($A, 'name');
            $this->item_name = SHOP_getVar($A, 'name');
            $this->short_description = SHOP_getVar($A, 'short_description');
            $this->description = SHOP_getVar($A, 'description', 'string', $this->short_description);
            $this->taxable = SHOP_getVar($A, 'taxable', 'integer', $def_taxable);
            $this->url = SHOP_getVar($A, 'url');
            $this->override_price = SHOP_getVar($A, 'override_price', 'integer', $def_price);
            $this->btn_type = SHOP_getVar($A, 'btn_type', 'string', 'buy_now');
            $this->btn_text = SHOP_getVar($A, 'btn_text');
            $this->_have_detail_svc = SHOP_getVar($A, 'have_detail_svc', 'boolean', false);
            $this->_fixed_q = SHOP_getVar($A, 'fixed_q', 'integer', 0);
            $this->img_url = SHOP_getVar($A, 'img_url');
            $this->isNew = false;
            // Plugins normally can't allow more than one purchase,
            // so default to "true"
            $this->isUnique = SHOP_getVar($A, 'isUnique', 'boolean', true);
            $this->rating_enabled = (bool)SHOP_getVar($A, 'supportsRatings', 'boolean', false);
            //$this->rating_enabled = true;   // TODO testing
            $this->votes = SHOP_getVar($A, 'votes', 'integer');
            $this->rating = SHOP_getVar($A, 'rating', 'float');
            // Set enabled flag, assume true unless set
            $this->enabled = SHOP_getVar($A, 'enabled', 'boolean', true);
            $this->cancel_url = SHOP_getVar($A, 'cancel_url', 'string', SHOP_URL . '/index.php');
            if (isset($A['canApplyDC']) && !$A['canApplyDC']) {
                $this->canApplyDC = false;
            }
            if (isset($A['custom_price']) && $A['custom_price']) {
                $this->custom_price = true;
            }
            $this->aff_percent = SHOP_getVar($A, 'aff_percent', 'float', 0);
            if ($this->aff_percent > 0) {
                $this->aff_apply_bonus = SHOP_getVar($A, 'aff_apply_bonus', 'boolean', false);
            } else {
                $this->aff_apply_bonus = false;
            }
            if (isset($A['canPurchase'])) {
                $this->enablePurchase($A['canPurchase']);
            }
        } else {
            // Get item info from plugin_getiteminfo function
            $what = 'id,title,excerpt,description,price,taxable,url,override_price,btn_type,btn_text,have_detail_svc,fixed_q,img_url,isUnique,supportsRatings,votes,rating,enabled,cancel_url,canApplyDC,custom_price,aff_percent,aff_apply_bonus,canPurcase';
            $A = PLG_callFunctionForOnePlugin(
                'plugin_getiteminfo_' . $this->pi_name,
                array(
                    1 => $this->product_id,
                    2 => $what,
                    3 => $_USER['uid'],
                    4 => $this->pi_info,
                )
            );
            if (is_array($A) && !empty($A)) {
                $this->price = SHOP_getVar($A, 'price', 'float', $def_price);
                $this->name = SHOP_getVar($A, 'title');
                $this->item_name = SHOP_getVar($A, 'title');
                $this->short_description = $this->name;
                /*if (empty($this->short_description)) {
                    $this->short_description = SHOP_getVar($A, 'title', 'string', '');
                }*/
                $this->description = SHOP_getVar($A, 'description', 'string', $this->short_description);
                if (isset($A['taxable']) && is_integer($A['taxable'])) {
                    $this->taxable = $A['taxable'] ? 1 : 0;
                }
                $this->url = SHOP_getVar($A, 'url');
                $this->override_price = SHOP_getVar($A, 'override_price', 'integer', $def_price);
                $this->btn_type = SHOP_getVar($A, 'btn_type', 'string', 'buy_now');
                $this->btn_text = SHOP_getVar($A, 'btn_text');
                $this->_have_detail_svc = SHOP_getVar($A, 'have_detail_svc', 'boolean', false);
                $this->_fixed_q = SHOP_getVar($A, 'fixed_q', 'integer', 0);
                $this->img_url = SHOP_getVar($A, 'img_url');
                $this->isNew = false;
                // Plugins normally can't allow more than one purchase,
                // so default to "true"
                $this->isUnique = SHOP_getVar($A, 'isUnique', 'boolean', true);
                $this->rating_enabled = (bool)SHOP_getVar($A, 'supportsRatings', 'boolean', false);
                //$this->rating_enabled = true;   // TODO testing
                $this->votes = SHOP_getVar($A, 'votes', 'integer');
                $this->rating = SHOP_getVar($A, 'rating', 'float');
                // Set enabled flag, assume true unless set
                $this->enabled = SHOP_getVar($A, 'enabled', 'boolean', true);
                $this->cancel_url = SHOP_getVar($A, 'cancel_url', 'string', SHOP_URL . '/index.php');
                if (isset($A['canApplyDC']) && !$A['canApplyDC']) {
                    $this->canApplyDC = false;
                }
                if (isset($A['custom_price']) && $A['custom_price']) {
                    $this->custom_price = true;
                }
                $this->aff_percent = SHOP_getVar($A, 'aff_percent', 'float', 0);
                if ($this->aff_percent > 0) {
                    $this->aff_apply_bonus = SHOP_getVar($A, 'aff_apply_bonus', 'boolean', false);
                } else {
                    $this->aff_apply_bonus = false;
                }
                if (isset($A['canPurchase'])) {
                    $this->enablePurchase($A['canPurchase']);
                }
            } else {
                // probably an invalid product ID
                $this->price = 0;
                $this->item_name = '';
                $this->short_description = '';
                $this->item_id = NULL;
                $this->isNew = true;
                $this->url = '';
                $this->taxable = 0;
                $this->_have_detail_svc = false;
                $this->isUnique = true;
                $this->enabled = false;
            }
        }
        //var_dump($this);die;
    }


    /**
     * Dummy function since plugin items don't support saving.
     *
     * @param   array   $A      Optional record array to save
     * @return  boolean         True, always
     */
    public function Save($A = '')
    {
        return true;
    }


    /**
     * Dummy function since plugin items can't be deleted here.
     *
     * @return  boolean     True, always
     */
    public function Delete()
    {
        return true;
    }


    /**
     * Handle the purchase of this item.
     * - Update qty on hand if track_onhand is set (min. value 0).
     *
     * @param   object  $Item       OrderItem object, to get options, etc.
     * @param   array   $ipn_data   IPN data
     * @return  integer     Zero or error value
     */
    public function handlePurchase(OrderItem &$Item, IPN $IPN) : int
    {
        SHOP_log('handlePurchase pi_info: ' . $this->pi_name, SHOP_LOG_DEBUG);
        $status = PLG_RET_OK;       // Assume OK in case the plugin does nothing

        if (!isset($ipn_data['uid'])) {
            $ipn_data['uid'] = $Item->getOrder()->getUid();
        }
        $args = array(
            'item'  => array(
                'item_id' => $Item->getProductID(),
                'quantity' => $Item->getQuantity(),
                'name' => $Item->getDscp(),
                'price' => $Item->getPrice(),
                'paid' => $Item->getPrice(),
                'order_id' => $Item->getOrder()->getOrderID(),
            ),
            'ipn_data' => $ipn_data,
            'referrer' => array(
                'ref_uid' => $Item->getOrder()->getReferrerId(),
                'ref_token' => $Item->getOrder()->getReferralToken(),
            ),
        );
        $status = LGLIB_invokeService(
            $this->pi_name,
            'handlePurchase',
            $args,
            $output,
            $svc_msg
        );
        return $status == PLG_RET_OK ? true : false;
    }


    /**
     * Handle a refund for this product.
     *
     * @param   object  $Order      Order being refunded
     * @param   array   $pp_data    Shop IPN data
     * @return  integer         Status from plugin's handleRefund function
     */
    public function handleRefund($Order, $pp_data = array())
    {
        if (empty($pp_data)) return false;
        $args = array(
            'item_id'   => explode(':', $this->item_id),
            'ipn_data'  => $pp_data,
        );
        $status = LGLIB_invokeService(
            $this->pi_name,
            'handleRefund',
            $args,
            $output,
            $svc_msg
        );
        return $status == PLG_RET_OK ? true : false;
    }


    /**
     * Cancel the purchase of this product.
     * Currently no action taken.
     *
     * @param   float   $qty    Item quantity
     * @param   string  $order_id   Order ID number
     */
    public function cancelPurchase($qty, $order_id='')
    {
    }


    /**
     * Get the unit price of this product, considering the specified options.
     * Plugins don't currently support option prices or discounts so the
     * price is just the fixed unit price.
     *
     * @param   array   $options    Array of integer option values (unused)
     * @param   integer $quantity   Quantity, used to calculate discounts (unused)
     * @param   array   $override   Array of override options (price, uid)
     * @return  float       Product price, including options
     */
    public function getPrice($options = array(), $quantity = 1, $override = array())
    {
        if ($this->override_price && isset($override['price'])) {
            $this->price = (float)$override['price'];
        } else {
            if (isset($override['uid'])) {
                $this->pi_info['mods']['uid'] = $override['uid'];
            }
            $status = LGLIB_invokeService(
                $this->pi_name,
                'productinfo',
                $this->pi_info,
                $A,
                $svc_msg
            );
            if ($status == PLG_RET_OK && isset($A['price'])) {
                $this->price = (float)$A['price'];
            }
        }
        return $this->price;
    }

    /**
     * See if this product allows a custom price to be entered by the user.
     * Certain plugins, such as donations, may allow custom user-entered prices.
     *
     * @return  boolean     True if allowed, False if not
     */
    public function allowCustomPrice()
    {
        return $this->custom_price ? true : false;
    }


    /**
     * Get the prompt to show for the custom pricing field, if allowed.
     *
     * @return  string      Field prompt
     */
    public function getPricePrompt()
    {
        global $LANG_DON;
        return $LANG_DON['amount'];
    }


    /**
     * Determine if a given item number belongs to a plugin.
     * Overrides the parent function, always returns true for a plugin item.
     *
     * @param   mixed   $item_number    Item Number to check
     * @return  boolean     Always true for this class
     */
    public static function isPluginItem($item_number)
    {
        return true;
    }


    /**
     * Get the URL to the item detail page.
     * If the plugin supplies its own detail service function, then use the Shop
     * detail page. Otherwise the plugin should supply its own url.
     * The order item ID is supplied so the plugin can instantiate an OrderItem
     * object and get more information.
     *
     * @param   integer $oi_id  Order Item ID
     * @param   string  $q      Query string. Should be url-encoded already
     * @return  string      Item detail URL
     */
    public function getLink()
    {
        if ($this->_have_detail_svc) {
            $url = SHOP_URL . '/detail.php?id=' . $this->item_id;
            if ($this->oi_id > 0 || $this->query != '') {
                $url .= '&oi_id=' . (int)$this->oi_id;
                if ($this->query != '') {
                    $url .= '&query=' . $this->query;
                }
            }
            return COM_buildUrl($url);
        } else {
            return $this->url;
        }
    }


    /**
     * Get additional text to add to the buyer's recipt for a product.
     *
     * @param   object  $OI     Order Item object (not used)
     * @return  string          Additional message to include in email
     */
    public function EmailExtra(OrderItem $OI) : string
    {
        $text = '';
        // status from the service function isn't used.
        LGLIB_invokeService(
            $this->pi_name,
            'emailReceiptInfo',
            $this->pi_info,
            $text,
            $svc_msg
        );
        return $text;
    }


    /**
     * Determine if a product can be shown in the catalog.
     * For plugin items, just return true for now.
     *
     * @param   integer $uid    User ID, current user if null
     * @return  boolean True if on sale, false if not
     */
    public function canDisplay(?int $uid = NULL) : bool
    {
        return true;
    }


    /**
     * Determine if a product is available to order.
     * For plugin items, just return true for now.
     *
     * @return  boolean True if on sale, false if not
     */
    public function canOrder()
    {
        return true;
    }


    /**
     * Check if only one of this product may be added to the cart.
     * Normally this is set for plugin products.
     *
     * @return  boolean     True if product can be purchased only once
     */
    public function isUnique()
    {
        return $this->isUnique;;
    }


    /**
     * Verify that the product ID matches the specified value.
     * Used to ensure that the correct product was retrieved.
     *
     * @param   integer|string  $id     Expected product ID or name
     * @return  boolean     True if product matches.
     */
    public function verifyID($id)
    {
        $parts = explode(':', $id);
        return $parts[1] == $this->pi_info['item_id'][0];
    }


    /**
     * Check if a discount code can be applied to this product.
     *
     * @return  boolean     True if a code can apply, False if not
     */
    public function canApplyDiscountCode()
    {
        return $this->canApplyDC;
    }


    /**
     * Get the image to include in the catalog.
     * Overrides the parent function.
     *
     * @param   string  $filename   Specific filename (not used)
     * @param   integer $width      Desired width (not used)
     * @param   integer $height     Desired height (not used)
     * @return  array   Array of image information
     */
    public function getImage($filename = '', $width = 0, $height = 0)
    {
        return array(
            'url' => $this->img_url,
            'width' => $width,
            'height' => $height,
        );
    }


    /**
     * Get the discounted price for the product, including options.
     * Plugins don't have discounts.
     *
     * @param   integer $qty            Quantity purchased
     * @param   float   $opts_price     Not used
     * @return  float       Net price considering sales and quantity discounts
     */
    public function getDiscountedPrice($qty=1, $opts_price=0, $override=NULL)
    {
        if ($override === NULL) {
            COM_errorLog("returning price {$this->price}");
            return (float)$this->price;
        } else {
            return (float)$override;
        }
    }


    /**
     * Dummy function to reserve stock.
     * Stock level tracking is not available for plugins.
     *
     * @param   float   $qty    Qty to reserve (not used)
     */
    public function reserveStock($qty)
    {
        return;
    }


    /**
     * Product Admin List View.
     *
     * @param   integer $cat_id     Optional category ID to limit listing (not used)
     * @return  string      HTML for the product list.
     */
    public static function adminList($cat_id=0)
    {
        global $_SHOP_CONF, $_TABLES, $LANG_SHOP,
            $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT * FROM {$_TABLES[self::$TABLE]}";

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['pi_name'],
                'field' => 'pi_name',
                'sort' => true,
            ),
            array(
                'text'  => $LANG_SHOP['prod_type'],
                'field' => 'prod_type',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['price'],
                'field' => 'price',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['taxable'],
                'field' => 'taxable',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'pi_name',
            'direction' => 'asc',
        );

        $display .= COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $display .= COM_createLink($LANG_SHOP['new_item'],
            SHOP_ADMIN_URL . '/index.php?pi_edit=0',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );

        // Filter on category, brand and supplier
        $cat_id = SHOP_getVar($_GET, 'cat_id', 'integer', 0);
        $brand_id = SHOP_getVar($_GET, 'brand_id', 'integer', 0);
        $supplier_id = SHOP_getVar($_GET, 'supplier_id', 'integer', 0);
        $def_filter = 'WHERE 1=1';

        $query_arr = array(
            'table' => 'shop.plugin_products',
            'sql'   => $sql,
            'query_fields' => array(
                'pi_name',
            ),
            'default_filter' => $def_filter,
        );

        $text_arr = array();
/*            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . "/index.php?products&cat_id=$cat_id&brand+id=$brand_id&supplier_id=$supplier_id",
        );
 */
        $options = array(
            'chkdelete' => true,
            'chkall' => true,
            'chkfield' => 'id',
        );

        $extra = array(
            'currency' => Currency::getInstance(),
        );
        $filter = '';
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_productlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, $extra, $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the plugin product list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra=array())
    {
        global $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'copy':
            $retval .= COM_createLink(
                Icon::getHTML('copy', 'tooltip', array('title' => $LANG_SHOP['copy_product'])),
                SHOP_ADMIN_URL . "/index.php?prod_clone=x&amp;id={$A['id']}"
            );
            break;

        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array('title' => $LANG_ADMIN['edit'])),
                SHOP_ADMIN_URL . "/index.php?return=products&pi_edit={$A['id']}"
            );
            break;
        case 'prod_type':
            $retval = $LANG_SHOP['prod_types'][$fieldvalue];
            break;
        case 'taxable':
            $retval .= Field::checkbox(array(
                'name' => 'taxable',
                'id' => "togenabled{$A['id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['id']}','taxable','pi_product');",
                'value' => 1,
            ) );
//            $retval = $fieldvalue ? 'Yes' : 'No';
            break;

        case 'price':
            if ($fieldvalue !== NULL) {
                $retval = $extra['currency']->formatValue((float)$fieldvalue);
            } else {
                $retval = 'n/a';
            }
            break;
        case 'delete':
            $retval .= COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/index.php?pi_del=' . $A['id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;
        default:
            $retval = $fieldvalue;
            break;
        }

        return $retval;
    }


    public static function edit($id = 0)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_SHOP_HELP, $_TABLES;

        $pi_name = '';
        $taxable = 0;
        $price = 0;
        $prod_type = ProductType::PLUGIN;
        $id = (int)$id;
        if ($id > 0) {
            $sql = "SELECT * FROM {$_TABLES[self::$TABLE]}
                WHERE id = $id LIMIT 1";
            $res = DB_query($sql);
            if ($res && DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
                $pi_name = $A['pi_name'];
                $taxable = $A['taxable'] ? 1 : 0;
                $price = $A['price'];
                $prod_type = $A['prod_type'];
            }
        }

        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit_item'] . ': ' . $pi_name);
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_item'] . ': ' . $LANG_SHOP['pi_name']);
        }

        $T = new Template('admin');
        $T->set_file('form', 'pi_form.thtml');
        $T->set_var(array(
            'id' => $id,
            'pi_name' => $pi_name,
            'pi_options' => COM_optionList($_TABLES['plugins'], 'pi_name,pi_name', $pi_name, 1, 'pi_enabled=1'),
            'taxable' => $taxable,
            'prod_type' => $prod_type,
            'price' => COM_numberFormat($price,2),
            'tooltipster_js' => Tooltipster::get('pi_form'),
        ) );
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
     * Delete a plugin configuration.
     *
     * @param   integer $id     DB record ID of plugin info
     */
    public static function deleteConfig($id)
    {
        global $_TABLES;

        DB_delete($_TABLES[self::$TABLE], 'id', (int)$id);
    }


    /**
     * Save a plugin configuration.
     *
     * @param   array   $A      Array of values from form or DB
     * @return  boolean     True on success, False on error
     */
    public static function saveConfig($A)
    {
        global $_TABLES;

        $pi_name = DB_escapeString($A['pi_name']);
        $taxable = isset($A['taxable']) && $A['taxable'] ? 1 : 0;
        $prod_type = (int)$A['prod_type'];
        $price = (float)$A['price'];

        $sql = "INSERT INTO {$_TABLES[self::$TABLE]} SET
            pi_name = '$pi_name',
            price = $price,
            prod_type = $prod_type,
            taxable = $taxable
            ON DUPLICATE KEY UPDATE
            price = $price,
            prod_type = $prod_type,
            taxable = $taxable";
        DB_query($sql);
    }


    /**
     * Verify that the order quantity is valid.
     * For plugins, the item_id will be null if it is not a valid item, so
     * return zero.
     *
     * @param   float   $qty    Desired quantity
     * @return  float   Valid quantity that may be ordered.
     */
    public function validateOrderQty($qty) : float
    {
        if (!$this->item_id) {
            return 0;
        } else {
            return parent::validateOrderQty($qty);
        }
    }

}
