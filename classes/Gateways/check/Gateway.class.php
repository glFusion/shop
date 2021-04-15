<?php
/**
 * Class to manage payment by check.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2013-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\check;
use Shop\Config;
use Shop\Models\ProductType;
use Shop\Template;


/**
 * Class for check payments.
 * @package shop
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = 'check';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'Pay by Check';

    /** Flag this gateway as bundled with the Shop plugin.
     * @var integer */
    protected $bundled = 1;


    /**
     * Constructor.
     * Sets gateway-specific variables and calls the parent constructor.
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        global $LANG_SHOP;

        // Set the services array to override the default.  Only checkout
        // is supported by this gateway.
        $this->services = array(
            'checkout'  => 1,
        );

        // Only out-of-band payments are accpeted.
        $this->can_pay_online = 0;

        // The parent constructor reads our config items from the database to
        // override defaults
        parent::__construct($A);

        $this->gw_desc = $this->getLang('gw_dscp');
        $this->gw_url = SHOP_URL;
        $this->ipn_url = '';
        $this->do_redirect = false; // handled internally
    }


    /**
     * Get the main website URL for this payment gateway.
     * Used to tell the buyer where to log in to check their account.
     *
     * @return  string      Gateway's website URL
     */
    private function getMainUrl()
    {
        global $_CONF;
        return $_CONF['site_url'];
    }


    /**
     * Get a purchase button.
     * This takes separate parameters so it can be called directly or via
     * ExternalButton or ProductButton
     *
     * @uses    PaymentGw::AddCustom()
     * @param   string  $btn_type       Button Type (optional)
     * @return  string                  HTML for button code
     */
    private function _getButton($btn_type)
    {
        global $_USER;

        // Make sure we have at least one item
        if (!$this->Supports($btn_type) || empty($this->items)) return '';
        $items = array();
        $total_amount = 0;
        foreach ($this->items as $item) {
            $total_amount += (float)$item['price'] * (float)$item['quantity'];
        }

        $this->AddCustom('transtype', $btn_type);
        $custom = $this->custom->encode();

        $vars = array(
            'accessKey'         => $this->access_key,
            'amount'            => $this->currency_code . ' ' . $total_amount,
            'referenceId'       => $custom,
            'description'       => $description,
            'returnUrl'         => SHOP_URL . '/index.php?thanks=amazon',
            'abandonUrl'        => SHOP_URL,
        );

        $gateway_vars .= '<input type="hidden" name="signature" value="' .
            $signature . '">' . "\n";

        return $gateway_vars;
    }


    /**
     * Get the action url for the payment button.
     * Overridden from the parent since we need to append to the url.
     *
     * @return  string      Payment URL
     */
    public function getActionUrl()
    {
        return $this->gw_url . '/index.php';
    }


    /**
     * Get the variables from the return URL to display a "thank-you" message to the buyer.
     *
     * @uses    getMainUrl()
     * @uses    Gateway::getDscp()
     * @param   array   $A      Optionally override the $_GET parameters
     * @return  array           Array of standard name=>value pairs
     */
    public function thanksVars($A='')
    {
        if (empty($A)) {
            $A = $_GET;     // Amazon's returnUrl uses $_GET
        }
        list($currency, $amount) = preg_split('/\s+/', $A['transactionAmount']);
        $amount = COM_numberFormat($amount, 2);
        $R = array(
            'payment_date'  => strftime('%d %b %Y @ %H:%M:%S', $A['transactionDate']),
            'currency'      => $currency,
            'payment_amount' => $amount,
            'gateway_url'   => self::getMainUrl(),
            'gateway_name'  => self::getDscp(),
        );
        return $R;
    }


    /**
     * Make sure that the button type is one of our valid types.
     *
     * @param   string  $btn_type   Button type, typically from product record
     * @return  string              Valid button type
     */
    private function gwButtonType($btn_type)
    {
        switch ($btn_type) {
        case 'donation':
        case 'buy_now':
            $retval = $btn_type;
            break;
        default:
            $retval = 'buy_now';
            break;
        }
        return $retval;
    }


    /**
     * Add a single item to our item array.
     *
     * @param   mixed   $item_id    ID of item, including options
     * @param   float   $price      Item price
     * @param   integer $qty        Quantity
     */
    private function _addItem($item_id, $price, $qty=0)
    {
        if ($qty == 0) $qty = 1;
        $qty = (float)$qty;
        $price = (float)$price;
        $this->items[] = array('item_id' => $item_id,
            'price' => $price,
            'quantity' => $qty);
    }


    /**
     * Get the variables to display with the IPN log.
     * This gets the variables from the gateway's IPN data into standard
     * array values to be displayed in the IPN log view
     *
     * @param   array   $data       Array of original IPN data
     * @return  array               Name=>Value array of data for display
     */
    public function ipnlogVars($data)
    {
        if (!is_array($data)) {
            return array();
        }

        list($currency, $amount) = explode(' ', $data['transactionAmount']);
        $retval = array(
            'pmt_gross'     => $amount . ' ' . $currency,
            'verified'      => 'verified',
            'pmt_status'    => 'complete',
            'buyer_email'   => $data['buyerEmail'],
        );
        return $retval;
    }


    /**
     * Handle the prurchase of an item via this gateway.
     *
     * @param   array   $vals   IPN variables
     */
    public function handlePurchase($vals = array())
    {
        $cart_id = SHOP_getVar($vals, 'cart_id');
        if (empty($cart_id)) {
            return '';
        }
        $Order = \Shop\Order::getInstance($cart_id);
        if ($Order->isNew()) {
            return '';
        }

        $T = new Template;
        $T->set_file('remit', 'remit_form.thtml');
        $T->set_var(array(
            'order_url' => $Order->buildUrl('pdforder'),
            'order_id'  => $Order->getOrderID(),
            'token'     => $Order->getToken(),
            'lang_print' => $this->getLang('print'),
            'pmt_instructions' => $this->getLang('pmt_instructions'),
        ) );
        $T->parse('output', 'remit');
        $content = $T->finish($T->get_var('output'));
        $Order->setStatus('pending')->Save();
        $content .= $Order->View();
        return $content;
    }


    /**
     * Handle the purchase of a product via this gateway.
     *
     * @param   array   $vals   IPN variables
     */
    private function _handlePurchase($vals)
    {
        global $_TABLES, $_CONF;

        if (!empty($vals['cart_id'])) {
            $cart = Cart::getInstance($vals['cart_id']);
            if (!$cart->hasItems()) return; // shouldn't be empty
            $items = $cart->getItems();
        } else {
            $cart = new Cart();
        }

        // Create an order record to get the order ID
        //$order_id = $this->CreateOrder($vals, $cart);
        //$db_order_id = DB_escapeString($order_id);
        $Order = $this->CreateOrder($vals, $cart);
        $db_order_id = DB_escapeString($Order->getOrderID());

        $prod_types = 0;

        // For each item purchased, record purchase in purchase table
        foreach ($items as $id=>$item) {
            $Order->AddItem($id, $item);

            //SHOP_log("Processing item: $id", SHOP_LOG_DEBUG);
            list($item_number, $item_opts) = explode('|', $id);

            // If the item number is numeric, assume it's an
            // inventory item.  Otherwise, it should be a plugin-supplied
            // item with the item number like pi_name:item_number:options
            if (SHOP_is_plugin_item($item_number)) {
                SHOP_log("handlePurchase for Plugin item " . $item_number, SHOP_LOG_DEBUG);

                // Initialize item info array to be used later
                $A = array();

                $status = LGLIB_invokeService(
                    $pi_info[0], 'productinfo',
                    array($item_number, $item_opts),
                    $product_info,
                    $svc_msg
                );
                if ($status != PLG_RET_OK) {
                    $product_info = array();
                }

                if (!empty($product_info)) {
                    $items[$id]['name'] = $product_info['name'];
                }
                SHOP_log("Got name " . $items[$id]['name'], SHOP_LOG_DEBUG);
                $vars = array(
                        'item' => $item,
                        'ipn_data' => array(),
                        'status' => 'pending',
                );
                // TODO: should plugin handlePurchase be called here, or when
                // the order is paid.
                $status = LGLIB_invokeService(
                    $pi_info[0], 'handlePurchase',
                    $vars,
                    $A,
                    $svc_msg
                );
                if ($status != PLG_RET_OK) {
                    $A = array();
                }

                // Mark what type of product this is
                $prod_types |= ProductType::VIRTUAL;

            } else {
                SHOP_log("Shop item " . $item_number, SHOP_LOG_DEBUG);
                $P = new \Shop\Product($item_number);
                $A = array('name' => $P->name,
                    'short_description' => $P->short_description,
                    'expiration' => $P->getExpiration(),
                    'prod_type' => $P->getProductType(),
                    'file' => $P->getFilename(),
                    'price' => $item['price'],
                );

                if (!empty($item_opts)) {
                    $opts = explode(',', $itemopts);
                    $opt_str = $P->getOptionDesc($opts);
                    if (!empty($opt_str)) {
                        $A['short_description'] .= " ($opt_str)";
                    }
                    $item_number .= '|' . $item_opts;
                }

                // Mark what type of product this is
                $prod_types |= $P->getProductType();
            }

            // An invalid item number, or nothing returned for a plugin
            if (empty($A)) {
                SHOP_log("Item {$item['item_number']} not found");
                continue;
            }

            // If it's a downloadable item, then get the full path to the file.
            if (!empty($A['file'])) {
                $this->items[$id]['file'] = Config::get('download_path') . $A['file'];
                $token_base = $this->pp_data['txn_id'] . time() . rand(0,99);
                $token = md5($token_base);
                $this->items[$id]['token'] = $token;
            } else {
                $token = '';
            }
            $items[$id]['prod_type'] = $A['prod_type'];

            // If a custom name was supplied by the gateway's IPN processor,
            // then use that.  Otherwise, plug in the name from inventory or
            // the plugin, for the notification email.
            if (empty($item['name'])) {
                $items[$id]['name'] = $A['short_description'];
            }

            // Add the purchase to the shop purchase table
            $uid = isset($vals['uid']) ? (int)$vals['uid'] : $_USER['uid'];

            $sql = "INSERT INTO {$_TABLES['shop.orderitems']} SET
                        order_id = '{$db_order_id}',
                        product_id = '{$item_number}',
                        description = '{$items[$id]['name']}',
                        quantity = '{$item['quantity']}',
                        txn_type = '{$this->gw_id}',
                        txn_id = '',
                        status = 'pending',
                        token = '$token',
                        price = " . (float)$item['price'] . ",
                        options = '" . DB_escapeString($item_opts) . "'";

            //echo $sql;die;
            SHOP_log($sql, SHOP_LOG_DEBUG);
            DB_query($sql);

        }   // foreach item

        $Order->Save();

        // If this was a user's cart, then clear that also
        if (isset($vals['cart_id']) && !empty($vals['cart_id'])) {
            DB_delete($_TABLES['shop.cart'], 'cart_id', $vals['cart_id']);
        }

        $Company = Company::getInstance();
        $gw_msg = $this->getLang('make_check_to') . ':<br />' .
                Config::get('shop_name') . '<br /><br />' .
                $this->getLang('remit_to') . ':<br />' .
                $Company->getName() . '<br />' .
                $Company->toHTML('address');
        $Order->Notify('pending', $gw_msg);
    }


    /**
     * Get the form variables for this checkout button.
     *
     * @param   object  $cart   Shopping cart
     * @return  string          HTML for input vars
     */
    public function gatewayVars($cart)
    {
        $gatewayVars = array(
            '<input type="hidden" name="processorder" value="' . $this->gw_name . '" />',
            '<input type="hidden" name="gateway" value="' . $this->gw_name . '" />',
            '<input type="hidden" name="cart_id" value="' . $cart->getOrderID() . '" />',
        );
        return implode("\n", $gatewayVars);
    }


    /**
     * Check if this gateway allows an order to be processed without an IPN msg.
     * The Check gateway does allow this as it just presents a remittance form.
     *
     * @return  boolean     True
     */
    public function allowNoIPN()
    {
        return true;
    }

}   // class check

?>
