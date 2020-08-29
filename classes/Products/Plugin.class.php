<?php
/**
 * Class to interface with plugins for product information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Products;
use Shop\Currency;
use Shop\Models\ProductType;


/**
 * Class for a plugin-supplied product.
 * @package shop
 */
class Plugin extends \Shop\Product
{
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
        global $_USER;

        $this->pi_info = array();
        $item = explode('|', $id);  // separate full item ID from option string
        $item_id = $item[0];
        $this->currency = Currency::getInstance();
        $this->item_id = $item_id;  // Full item id
        $this->id = $item_id;       // TODO: convert Product class to use item_id
        $item_parts = explode(':', $item_id);   // separate the plugin name and item ID
        $this->pi_name = $item_parts[0];
        array_shift($item_parts);         // Remove plugin name
        $this->pi_info['item_id'] = $item_parts;
        $this->product_id = $item_parts[0];

        // Get the user ID to pass to the plugin in case it's needed.
        if (!isset($mods['uid'])) {
            $mods['uid'] = $_USER['uid'];
        }
        $this->pi_info['mods'] = $mods;
        $this->prod_type = ProductType::PLUGIN;

        // Try to call the plugin's function to get product info.
        $status = LGLIB_invokeService(
            $this->pi_name,
            'productinfo',
            $this->pi_info,
            $A,
            $svc_msg
        );
        if ($status == PLG_RET_OK) {
            $this->price = SHOP_getVar($A, 'price', 'float', 0);
            $this->name = SHOP_getVar($A, 'name');
            $this->item_name = SHOP_getVar($A, 'name');
            $this->short_description = SHOP_getVar($A, 'short_description');
            $this->description = SHOP_getVar($A, 'description', 'string', $this->short_description);
            $this->taxable = SHOP_getVar($A, 'taxable', 'integer', 0);
            $this->url = SHOP_getVar($A, 'url');
            $this->override_price = SHOP_getVar($A, 'override_price', 'integer', 0);
            $this->btn_type = SHOP_getVar($A, 'btn_type', 'string', 'buy_now');
            $this->btn_text = SHOP_getVar($A, 'btn_text');
            $this->_have_detail_svc = SHOP_getVar($A, 'have_detail_svc', 'boolean', false);
            $this->_fixed_q = SHOP_getVar($A, 'fixed_q', 'integer', 0);
            $this->img_url = SHOP_getVar($A, 'img_url');
            $this->isNew = false;
            // Plugins normally can't allow more than one purchase,
            // so default to "true"
            $this->isUnique = SHOP_getVar($A, 'isUnique', 'boolean', true);
            $this->rating_enabled = SHOP_getVar($A, 'supportsRatings', 'boolean', false);
            //$this->rating_enabled = true;   // TODO testing
            $this->votes = SHOP_getVar($A, 'votes', 'integer');
            $this->rating = SHOP_getVar($A, 'rating', 'float');
            // Set enabled flag, assume true unless set
            $this->enabled = SHOP_getVar($A, 'enabled', 'boolean', true);
            $this->cancel_url = SHOP_getVar($A, 'cancel_url', 'string', SHOP_URL . '/index.php');
            if (array_key_exists('canApplyDC', $A) && !$A['canApplyDC']) {
                $this->canApplyDC = false;
            }
            if (array_key_exists('custom_price', $A) && $A['custom_price']) {
                $this->custom_price = true;
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
     * @param   object  $Item       Item object, to get options, etc.
     * @param   object  $Order      Optional order object (not used yet)
     * @param   array   $ipn_data   IPN data
     * @return  integer     Zero or error value
     */
    public function handlePurchase(&$Item, $Order=NULL, $ipn_data=array())
    {
        SHOP_log('pi_info: ' . $this->pi_name, SHOP_LOG_DEBUG);
        $status = PLG_RET_OK;       // Assume OK in case the plugin does nothing

        // The custom field needs to exist and be an array.
        if (isset($ipn_data['custom'])) {
           if (is_string($ipn_data['custom'])) {
               $ipn_data['custom'] = @unserialize($ipn_data['custom']);
               // Final check in case serialization failed
               if (!is_array($ipn_data['custom'])) {
                   $ipn_data['custom'] = array();
               }
           }
        } else {
            $ipn_data['custom'] = array();  // should be set, but just in case.
        }

        $args = array(
            'item'  => array(
                'item_id' => $Item->getProductID(),
                'quantity' => $Item->getQuantity(),
                'name' => $Item->getDscp(),
                'price' => $Item->getPrice(),
                'paid' => $Item->getPrice(),
            ),
            'ipn_data'  => $ipn_data,
            'order' => $Order,      // Pass the order object, may be used in the future
        );
        if ($ipn_data['status'] == 'paid') {
            $status = LGLIB_invokeService(
                $this->pi_name,
                'handlePurchase',
                $args,
                $output,
                $svc_msg
            );
        }
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
    public function getLink($oi_id=0, $q='')
    {
        if ($this->_have_detail_svc) {
            $url = SHOP_URL . '/detail.php?id=' . $this->item_id;
            if ($oi_id > 0 || $q != '') {
                $url .= '&oi_id=' . (int)$oi_id;
                if ($q != '') {
                    $url .= '&query=' . $q;
                }
            }
            return $url;
        } else {
            return $this->url;
        }
    }


    /**
     * Get additional text to add to the buyer's recipt for a product.
     *
     * @param   object  $item   Order Item object (not used)
     * @return  string          Additional message to include in email
     */
    public function EmailExtra($item)
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
     * @param   boolean $isadmin    True if this is an admin, can view all
     * @return  boolean True if on sale, false if not
     */
    public function canDisplay($isadmin = false)
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
    public function getDiscountedPrice($qty=1, $opts_price=0)
    {
        return (float)$this->price;
    }


}   // class Plugin

?>
