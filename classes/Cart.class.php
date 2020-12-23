<?php
/**
 * Shopping cart class for the Shop plugin.
 *
 * Based partially on work done for the unreleased "ecommerce" plugin
 * by Josh Pendergrass <cendent AT syndicate-gaming DOT com>
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 *
 */
namespace Shop;
use Shop\Models\OrderState;


/**
 * Shopping cart class.
 * @package shop
 */
class Cart extends Order
{
    /**
     * Read the cart contents from the "cart" table, if available.
     * Does not read from the userinfo table- that's up to the uesr
     * login and logout functions.
     * `$interactive` is set to false if the cart is instantiated for a system
     * call such as an IPN notification.
     *
     * @param   string  $cart_id    ID of an existing cart to read
     * @param   boolean $interactive    True if this is an interactive session
     */
    public function __construct($cart_id='', $interactive=true)
    {
        global $_TABLES, $_SHOP_CONF, $_USER;

        if (empty($cart_id)) {
            $cart_id = self::getCartID();
        }

        parent::__construct($cart_id);
        if ($this->isNew) {
            $this->status = OrderState::CART;
            if (!COM_isAnonUser()) {
                $this->buyer_email = $_USER['email'];
            }
            $this->Save();    // Save to reserve the ID
        }
        self::_setCookie($this->order_id);
    }


    /**
     * Get the cart for the current user.
     *
     * @param   integer $uid        User ID, current user by default
     * @param   string  $cart_id    Specific cart ID to read
     * @return  object  Cart object
     */
    public static function getInstance($cart_id = '', $uid = 0)
    {
        global $_TABLES, $_USER;

        static $carts = array();

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        if ($uid < 2) {
            if ($cart_id == '') {
                $cart_id = self::getSession('cart_id');
            }
        } else {
            $cart_id = self::getCartID($uid);
        }
        if (isset($carts[$cart_id])) {
            $Cart = $carts[$cart_id];
        } else {
            $Cart = new self($cart_id);
            $carts[$Cart->getOrderID()] = $Cart;
        }

        // If the cart user ID doesn't match the requested one, then the
        // cookie may have gotten out of sync. This can happen when the
        // user leaves the browser and the glFusion session expires.
        if ($Cart->getUid() != $uid || $Cart->getStatus() != OrderState::CART) {
            self::_expireCookie();
            $Cart = new self();
            $carts[$Cart->getOrderID()] = $Cart;
        }
        return $Cart;
    }


    public static function exists($cart_id)
    {
        global $_TABLES;
        return DB_count(
            $_TABLES['shop.orders'],
            'order_id',
            DB_escapeString($cart_id)
        );
    }


    /**
     * Get all active carts.
     *
     * @return  array   Array of cart objects
     */
    public static function getAll()
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT order_id FROM {$_TABLES['shop.orders']}
            WHERE status = '" . OrderState::CART . "'";
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['order_id']] = new self($A['order_id']);
            }
        }
        return $retval;
    }


    /**
     * Merge the saved cart for Anonymous into the current user's cart.
     * Calls addItem() for each item rather than changing the cart ID for each
     * item in order to combine duplicate line items.
     * Saves the updated cart to the database.
     *
     * @param   string  $cart_id    ID of cart being merged into this one
     */
    public function Merge($cart_id)
    {
        global $_TABLES, $_USER;

        if ($_USER['uid'] < 2) return;

        $AnonCart = self::getInstance($cart_id, 1);
        if (empty($AnonCart->getItems())) {
            return;
        }
        // Merge the items into the user cart
        foreach ($AnonCart->getItems() as $Item) {
            $opts = array();
            foreach ($Item->getOptions() as $Opt) {
                $opts[] = $Opt->getOptionID();
            }
            $args = array(
                'item_number'   => $Item->getProductID(),
                'variant'       => $Item->getVariantId(),
                'attributes'    => $opts,
                'extras'        => $Item->getExtras(),
                'description'   => $Item->getDscp(),
                'quantity'      => $Item->getQuantity(),
            );
            $this->addItem($args);
        }

        // Remove the anonymous cart and save this user's cart
        $AnonCart->Clear();
        $this->Save();
    }


    /**
     *  Add a single item to the cart.
     *  Formats the argument array to match the format used by the Order class
     *  and calls that class's addItem() function to actually add the item.
     *
     *  Some values are straight from the item table, but may be overridden
     *  to handle special cases or customization.
     *
     *  @param  array   $args   Array of arguments. item_number is required.
     *  @return integer             Current item quantity
     */
    public function addItem($args)
    {
        global $_SHOP_CONF, $_USER;

        if (
            !isset($args['item_number'])
            ||
            $this->uid != $_USER['uid']
        ) {
            return false;
        }

        $need_save = false;     // assume the cart doesn't need to be re-saved
        $item_id = $args['item_number'];    // may contain options
        $P = Product::getByID($item_id);
        $quantity   = SHOP_getVar($args, 'quantity', 'float', $P->getMinOrderQty());
        $override   = isset($args['override']) ? $args['price'] : NULL;
        $extras     = SHOP_getVar($args, 'extras', 'array');
        $options    = SHOP_getVar($args, 'options', 'array');
        $item_name  = SHOP_getVar($args, 'item_name');
        $item_dscp  = SHOP_getVar($args, 'description');
        $uid        = SHOP_getVar($args, 'uid', 'int', 1);
        if (isset($args['variant'])) {
            $PV = ProductVariant::getInstance($args['variant']);
        } else {
            $PV = ProductVariant::getByAttributes($P->getID(), $options);
        }
        if (!is_array($this->items)) {
            $this->items = array();
        }

        // Extract the attribute IDs from the options array to create
        // the item_id.
        // Options are an array(id1, id2, id3, ...)
        $opts = array();
        if (is_array($options) && !empty($options)) {
            foreach($options as $opt_id) {
                $opts[] = new ProductOptionValue($opt_id);
            }
            // Add the option numbers to the item ID to create a new ID
            // to check whether the product already exists in the cart.
            //$opt_str = implode(',', $options);
            //$item_id .= '|' . $opt_str;
        } else {
            $opts = array();
        }
        if ($PV->getID() > 0) {
            $P->setVariant($PV);
            $item_id .= '|' . $PV->getID();
        }

        // Look for identical items, including options (to catch
        // attributes). If found, just update the quantity.
        if ($P->cartCanAccumulate()) {
            $have_id = $this->Contains($item_id, $extras);
        } else {
            $have_id = false;
        }

        $quantity = $P->validateOrderQty($quantity);
        if ($have_id !== false) {
            $new_quantity = $this->items[$have_id]->getQuantity();
            $new_quantity += $quantity;
            $this->items[$have_id]->setQuantity($new_quantity);
            $this->items[$have_id]->Save();
        } elseif ($quantity == 0) {
            return false;
        } else {
            $tmp = array(
                'item_id'   => $item_id,
                'quantity'  => $quantity,
                'name'      => $P->getName($item_name),
                'description'   => $P->getDscp($item_dscp),
                'variant'   => $PV->getID(),
                'options'   => $opts,
                'extras'    => $extras,
                'taxable'   => $P->isTaxable() ? 1 : 0,
            );
            if (
                Product::isPluginItem($item_id) &&
                isset($args['price'])
            ) {
                $tmp['price'] = (float)$args['price'];
            }
            parent::addItem($tmp);
            $new_quantity = $quantity;
        }
        if ($this->applyQtyDiscounts($item_id)) {
            // If discount pricing was recalculated, save the new item prices
            $need_save = true;
        }

        // If an update was done that requires re-saving the cart, do it now
        if ($need_save) {
            $this->Save();
        }
        return $new_quantity;
    }


    /**
     * Update an existing cart item.
     * This only works where items are unique since the caller has no access
     * to the cart ID.
     *
     * @param   string  $item_number    Product ID of item to update
     * @param   array   $updates        Array (field=>value) of new values
     */
    public function updateItem($item_number, $updates)
    {
        // Search through the cart for the item number
        foreach ($this->items as $id=>$OI) {
            if ($OI->getProductID() == $item_number) {
                // If the item is found, loop through the updates and apply
                $OI->updateItem($updates);
                break;
            }
        }
        $this->Save();
    }


    /**
     * Update the quantity for all cart items.
     * Called from the View Cart form to update any quantities that have
     * changed.
     * Also applies a coupon code, if entered.
     *
     * @see     Cart::UpdateQty()
     * @param   array   $A  Array if items as itemID=>newQty
     * @return  array       Updated cart contents
     */
    public function Update($A)
    {
        global $_SHOP_CONF;

        $items = $A['quantity'];
        if (!is_array($items)) {
            // No items in the cart?
            return $this;
        }
        foreach ($items as $id=>$qty) {
            // Make sure the item object exists. This can get out of sync if a
            // cart has been finalized and the user updates it from another
            // browser window.
            if (array_key_exists($id, $this->items)) {
                $qty = (float)$qty;
                $item_id = $this->items[$id]->getProductId();
                $old_qty = $this->items[$id]->getQuantity();
                // Check that the order hasn't exceeded the max allowed qty.
                $max = Product::getById($item_id)
                    ->setVariant($this->items[$id]->getVariantID())
                    ->getMaxOrderQty();
                if ($qty > $max) {
                    $qty = $max;
                }
                if ($qty == 0) {
                    // If zero is entered for qty, delete the item.
                    // Save the item ID to update any affected qty-based
                    // discounts.
                    $this->Remove($id);
                    // Re-apply qty discounts in case there are other items
                    // with the same base ID
                    $this->applyQtyDiscounts($item_id);
                } elseif ($old_qty != $qty) {
                    // The number field on the viewcart form should prevent this,
                    // but just in case ensure that the qty ordered is allowed.
                    $this->items[$id]->setQuantity($qty);
                    $this->applyQtyDiscounts($item_id);
                    $this->items[$id]->Save();
                }
            }
        }
        $this->calcItemTotals();

        // Now look for a coupon code to redeem against the user's account.
        if ($_SHOP_CONF['gc_enabled']) {
            $gc = SHOP_getVar($A, 'gc_code');
            if (!empty($gc)) {
                if (\Shop\Products\Coupon::Redeem($gc) == 0) {
                    unset($this->m_info['apply_gc']);
                }
            }
        }
        /*if (isset($A['gateway'])) {
            $this->setGateway($A['gateway']);
        }
        if (isset($A['by_gc'])) {
            $this->setGC($A['by_gc']);
        }*/
        /*if (isset($_POST['shipper_id'])) {
            $this->setShipper($_POST['shipper_id']);
        }*/
        /*if (isset($A['payer_email']) && COM_isEmail($A['payer_email'])) {
            $this->buyer_email = $A['payer_email'];
        }*/
        if (isset($A['discount_code']) && !empty($A['discount_code'])) {
            $dc = $A['discount_code'];
        } else {
            $dc = $this->getDiscountCode();
        }
        $this->validateDiscountCode($dc);
        $this->Save();  // Save cart vars, if changed, and update the timestamp
        return $this;
    }


    /**
     * Remove an item from the cart.
     * Saves the updated cart after removal.
     *
     * @param   string  $id     Item ID to remove
     * @return  array           Current cart contents
     */
    public function Remove($id)
    {
        global $_TABLES;

        if (isset($this->items[$id])) {
            OrderItem::Delete($id);
            unset($this->items[$id]);
            $this->Save();
        }
        return $this->items;
    }


    /**
     * Empty and destroy the cart.
     *
     * @param   boolean $del_order  True to delete any related order
     * @return  object  $this
     */
    public function Clear($del_order = true)
    {
        global $_TABLES, $_USER;

        // Only clear if this is actually a cart, not a finalized order.
        if ($this->status == OrderState::CART) {
            foreach ($this->items as $Item) {
                OrderItem::Delete($Item->getID());
            }
            $this->items = array();
            $vals = array(
                "gross_items = 0",
                "net_nontax = 0",
                "net_taxable = 0",
                "order_total = 0",
                "tax = 0",
                "shipping = 0",
                "handling = 0",
                "by_gc = 0",
                "tax_rate = 0",
                "shipper_id = 0",
                "discount_code = ''",
                "discount_pct = 0",
                "instructions = 0",
                "tax_shipping = 0",
                "tax_handling = 0",
            );
            $this->updateRecord($vals);
            /*if ($del_order) {
                DB_delete($_TABLES['shop.orders'], 'order_id', $this->cartID());
                self::delAnonCart();
            }*/
        }
        return $this;
    }


    /**
     * Create a fake checkout button to be used when the order value is zero due to coupons.
     * This takes the user directly to the internal IPN processor.
     *
     * @param  object  $gw Selected Payment Gateway
     * @return string      HTML for final checkout button
     */
    public function checkoutButton($gw)
    {
        global $_SHOP_CONF, $_USER;

        $by_gc = (float)$this->getInfo('apply_gc');
        $net_total = $this->order_total - $by_gc;
        if ($gw->Supports('checkout')) {
            // Else, if amount > 0, regular checkout button
            $this->custom_info['by_gc'] = $by_gc;   // pass GC amount used via gateway
            $this->by_gc = $by_gc;                  // pass GC amount used via gateway
            return $gw->checkoutButton($this);
        } else {
            return 'Gateway does not support checkout';
        }
    }


    /**
     * Get the payment gateway checkout buttons.
     *
     * @uses    Gateway::CheckoutButton()
     * @return  string      HTML for checkout buttons
     */
    public function getCheckoutButtons()
    {
        global $_SHOP_CONF;

        $gateway_vars = '';
        if ($_SHOP_CONF['anon_buy'] || !COM_isAnonUser()) {
            foreach (Gateway::getAll() as $gw) {
                if ($gw->hasAccess($this->order_total) && $gw->Supports('checkout')) {
                    $gateway_vars .= '<div class="shopCheckoutButton">' .
                        $gw->CheckoutButton($this) . '</div>';
                }
            }
        } else {
            $L = new Template;
            $L->set_file('login', 'btn_req_login.thtml');
            $L->parse('login_btn', 'login');
            $gateway_vars = $L->finish($L->get_var('login_btn'));
        }
        return $gateway_vars;
    }


    /**
     * Get the payment gateway checkout buttons.
     * If there is only one possible gateway, pre-select it. If more than one
     * then leave all unselected, unless m_info['gateway'] has already been
     * set for this order.
     *
     * @deprecate
     * @uses    Gateway::CheckoutButton()
     * @return  string      HTML for checkout buttons
     */
    public function getCheckoutRadios()
    {
        global $_SHOP_CONF;

        echo __CLASS__ . '::' . __FUNCTION__ . ': Deprecated';die;

        $retval = '';
        $total = $this->getTotal();
        $T = new Template;
        $T->set_file('radios', 'gw_checkout_select.thtml');
        $T->set_block('radios', 'Radios', 'row');
        if ($_SHOP_CONF['anon_buy'] || !COM_isAnonUser()) {
            $gateways = Gateway::getAll();
            if ($_SHOP_CONF['gc_enabled']) {
                $gateways['_coupon'] = Gateway::getInstance('_coupon');
            }
            $gc_bal = $_SHOP_CONF['gc_enabled'] ? \Shop\Products\Coupon::getUserBalance() : 0;
            if (empty($gateways)) {
                return NULL;  // no available gateways
            }

            if ($total == 0) {
                // Automatically select the "free" gateway if appropriate.
                // Other gateways shouldn't be shown anyway.
                $gw_sel = 'free';
            } elseif (
                isset($this->m_info['gateway']) &&
                array_key_exists($this->m_info['gateway'], $gateways)
            ) {
                // Select the previously selected gateway
                $gw_sel = $this->m_info['gateway'];
            } elseif ($gc_bal >= $total) {
                // Select the coupon gateway as full payment
                $gw_sel = '_coupon';
            } else {
                // Select the first if there's one, otherwise select none.
                $gw_sel = Gateway::getSelected();
                if ($gw_sel == '') {
                    $gw_sel = Customer::getInstance($this->uid)->getPrefGW();
                }
            }
            foreach ($gateways as $gw_id=>$gw) {
                if (is_null($gw) || !$gw->hasAccess($total)) {
                    continue;
                }
                if ($gw->Supports('checkout')) {
                    if ($gw_sel == '') $gw_sel = $gw->getName();
                    $T->set_var(array(
                        'gw_id' => $gw->getName(),
                        'radio' => $gw->checkoutRadio($gw_sel == $gw->getName()),
                    ) );
                    $T->parse('row', 'Radios', true);
                }
            }
        }
        $T->parse('output', 'radios');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Get the cart ID.
     * Returns either the native cart ID, or a version escaped for SQL
     *
     * @param   boolean $escape True to escape return value for DB
     * @return  string      Cart ID string
     */
    public function cartID($escape=false)
    {
        if ($escape)
            return DB_escapeString($this->order_id);
        else
            return $this->order_id;
    }


    /**
     * Set the address values for a single address.
     *
     * @param   array   $A      Array of address elements
     * @param   string  $type   Type of address, billing or shipping
     */
    public function setAddress($A, $type = 'billto')
    {
        switch ($type) {
        case 'billto':
            $this->setBillto($A);
            break;
        default:
            $this->setShipto($A);
            break;
        }
    }


    /**
     * Set the selected shipping option information in the cart.
     * The method_id parameter is an index into the shipping quotes available,
     * not the shipper's DB record ID.
     *
     * @param   integer $method_id  Shipping method ID
     * @return  object  $this
     */
    public function setShippingOption($method_id)
    {
        // Get the shipping rates
        $options = $this->getShippingOptions();
        if (is_array($options) && array_key_exists($method_id, $options)) {
            $shipper = $options[$method_id];
            // Have to convert some of the shipper fields
            $method = array(
                'shipper_id' => $shipper['shipper_id'],
                'cost' => $shipper['method_rate'],
                'svc_code' => $shipper['svc_code'],
                'title' => $shipper['method_name'],
            );
            $this->setShipper($method);
        }
        return $this;
    }


    /**
     * Get a cart ID for a given user.
     * Gets the latest cart, and cleans up extra carts that may accumulate
     * due to expired sessions.
     *
     * @param   integer $uid    User ID
     * @return  string          Cart ID
     */
    public static function getCartID($uid = 0)
    {
        global $_USER, $_TABLES, $_SHOP_CONF, $_PLUGIN_INFO;

        // Flag indicating the cart was read, so deleting old
        // carts happens only once.
        static $read_cart = array();

        // Guard against invalid SQL if the DB hasn't been updated
        if (!SHOP_isMinVersion()) return NULL;

        $uid = $uid > 0 ? (int)$uid : (int)$_USER['uid'];
        if (COM_isAnonUser()) {
            $cart_id = self::getAnonCartID();
            // Check if the order exists but is not a cart.
            $status = DB_getItem(
                $_TABLES['shop.orders'],
                'status',
                "order_id = '" . DB_escapeString($cart_id) . "'"
            );
            if ($status != NULL && $status != OrderState::CART) {
                $cart_id = NULL;
            }
        } else {
            $cart_id = DB_getItem(
                $_TABLES['shop.orders'],
                'order_id',
                "uid = $uid AND status = '" . OrderState::CART .
                "' ORDER BY last_mod DESC limit 1"
            );
            if (!empty($cart_id) && !isset($read_cart[$uid])) {
                // For logged-in usrs, delete superfluous carts
                DB_query("DELETE FROM {$_TABLES['shop.orders']}
                    WHERE uid = $uid
                    AND status = '" . OrderState::CART . "'
                    AND order_id <> '" . DB_escapeString($cart_id) . "'");
            }
            $read_cart[$uid] = true;
        }
        return $cart_id;
    }


    /**
     * Delete any cart(s) for a user.
     *
     * @param   integer $uid    User ID
     * @param   string  $save   Optional order ID to preserve
     */
    public static function deleteUser($uid = 0, $save = '')
    {
        global $_TABLES, $_USER;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        if ($uid < 2) return;       // Don't delete anonymous carts
        $msg = "All carts for user {$uid} deleted";
        $sql = "DELETE FROM {$_TABLES['shop.orders']}
            WHERE uid = $uid AND status = '" . OrderState::CART . "'";
        if ($save != '') {
            $sql .= " AND order_id <> '" . DB_escapeString($save) . "'";
            $msg .= " except $save";
        }
        DB_query($sql);
        SHOP_log($msg, SHOP_LOG_DEBUG);
    }


    /**
     * Set the anonymous user's cart ID.
     * Used so the anonymous cart can be located and merged when a user
     * logs in.
     *
     * @param  string  $cart_id    Cart ID
     */
    public static function storeCartID($cart_id)
    {
        self::_setCookie($cart_id);
    }


    /**
     * Delete the anonymous user's cart.
     * This is done after merging the cart during login to prevent it from
     * being left behind and possibly re-merged during a subsequent login
     */
    public static function delAnonCart()
    {
        global $_TABLES;

        $cart_id = self::getAnonCartID();
        if ($cart_id) {
            // Remove the cookie - always
            self::_expireCookie();
            // And delete the cart record - only if it's anonymous
            //$C = new self($cart_id);
            $C = new self();
            if (!$C->isNew && $C->uid == 1) {
                Order::Delete($cart_id);
            }
        }
    }


    /**
     * Get the anonymous user's cart ID from the cookie.
     *
     * @return  mixed   Cart ID, or Null if not set
     */
    public static function getAnonCartID()
    {
        $cart_id = NULL;
        if (
            isset($_COOKIE[self::$session_var]) &&
            !empty($_COOKIE[self::$session_var])
        ) {
            $cart_id = $_COOKIE[self::$session_var];
        } else {
            $cart_id = self::getSession('cart_id');
        }
        return $cart_id;
    }


    /**
     * Get the order view based on the current step in the checkout process.
     *
     * @param   integer $step   Checkout step
     * @return  string          Order view page
     */
    public function getView($step = 0)
    {
        // $step == 0 is just to view the cart. Any step beyond 0 goes
        // into the workflow and displays the first incomplete step..
        $wf = Workflow::getAll($this);
        if ($step > 0) {
            $wf_name = 'checkout';
            $step = 9;
            foreach ($wf as $w) {
                if (!$w->isSatisfied($this)) {
                    $wf_name = $w->getName();
                    break;
                }
            }
        } else {
            $wf_name = 'viewcart';
        }

        switch($wf_name) {
        case 'viewcart':
            // Initial cart view. Check here and populate the billing and
            // shipping addresses if not already set, and only for logged-in
            // users.
            // This allows subsequent steps to go directly to the checkout
            // page if all other workflows are complete.
            /*if ($this->uid > 1) {
                // Determine the minimum value for a workflow to be "required"
                $wf_required = $this->requiresShipto() ? 1 : 3;
                $U = Customer::getInstance($this->uid);
                if (
                    $this->billto_id == 0 &&
                    Workflow::getInstance(2)->enabled >= $wf_required
                ) {
                    $A = $U->getDefaultAddress('billto');
                    if ($A) {
                        $this->setAddress($A->toArray(), 'billto');
                    }
                }
                if (
                    $this->shipto_id == 0 &&
                    Workflow::getInstance(3)->enabled >= $wf_required
                ) {
                    $A = $U->getDefaultAddress('shipto');
                    if ($A) {
                        $this->setAddress($A->toArray(), 'shipto');
                    }
                }
        }*/
            // Fall through to the checkout view
        case 'checkout':
            $V = new Views\Cart;
            $V->withView($wf_name)->withOrderId($this->order_id);
            return $V->Render();
            return $this->View($wf_name, $step);
        case 'billto':
        case 'shipto':
            $U = new \Shop\Customer();
            $A = isset($_POST['address1']) ? $_POST : $this->getAddress($wf_name);
            return $U->AddressForm($wf_name, $A, $step);
        case 'finalize':
            $V = new Views\Cart;
            $V->withView('checkout')->withOrderId($this->order_id);
            return $V->Render();
            return $this->View('checkout');
        default:
            return $this->View();
        }
    }


    /**
     * Determine if the current user can view this cart.
     * Checks that this is actually a cart, then calls Order::_checkAcess()
     * to check user permissions.
     *
     * @param   string  $token  Item token, not used in the Cart class
     * @return  boolean     True if allowed to view, False if denied.
     */
    public function canView($token='')
    {
        global $_USER;

        $canview = false;

        // Check that this is an existing record
        if ($this->isNew() || $this->status != OrderState::CART) {
            $canview  = false;
        } elseif ($this->getUid() > 1 && $_USER['uid'] == $this->getUid()) {
            // Logged-in cart owner
            $canview = true;
        } elseif ($this->getUid() == 1 && self::getSession('order_id') == $this->getOrderID()) {
            // Anonymous with this cart ID set in the session
            $canview = true;
        }
        return $canview;
    }


    /**
     * Delete all carts.
     * Called from the special administrative functions, may be needed after upgrading.
     */
    public static function Purge()
    {
        global $_TABLES;
        DB_delete($_TABLES['shop.orders'], 'status', OrderState::CART);
        SHOP_log("All carts for all users deleted", SHOP_LOG_DEBUG);
    }


    /**
     * Helper function to set a cookie that expires after days_purge_cart days.
     * Also sets the cart in the session since the cookie may not be immediately
     * available the first time something is added to the cart.
     *
     * @param   mixed   $value      Value to set
     */
    private static function _setCookie($value)
    {
        global $_SHOP_CONF;

        $exp = time() + ($_SHOP_CONF['days_purge_cart'] * 86400);
        SEC_setCookie(self::$session_var, $value, $exp);
        self::setSession('cart_id', $value);
    }


    /**
     * Helper function to expire the cart ID cookie.
     * Also removes from the $_COOKIE array for immediate effect.
     */
    private static function _expireCookie()
    {
        unset($_COOKIE[self::$session_var]);
        self::clearSession();
        SEC_setCookie(self::$session_var, '', time()-3600);
    }


    /**
     * Create a unique key based on some string.
     *
     * @param   string  $str    Base string
     * @return  string  Nonce string
     */
    public function makeNonce($str='')
    {
        return md5($str . $this->order_id);
    }


    /**
     * Validate all the items on an order.
     * Called just prior to final checkout to ensure that all the items are
     * available for ordering.
     * - Removes any unavailable products.
     * - Update all the cart items to the current catalog price.
     *
     * @return  array   Array of invalid order item objects.
     */
    public function updateItems()
    {
        global $LANG_SHOP;

        $invalid = array();     // Holder for product objects
        $msg = array();         // Message to be displayed
        foreach ($this->items as $id=>$Item) {
            $P = $Item->getProduct();
            if (!$P->canOrder()) {
                if (!isset($invalid['removed'])) {
                    $invalid['removed'] = array();
                }
                $this->Remove($id);
                $msg[] = $LANG_SHOP['removed'] . ': ' . $P->getShortDscp();
                $invalid['removed'][] = $P;
            } else {
                $this->applyQtyDiscounts($P->getId());
            }
        }
        if (!empty($msg)) {
            $msg = '<ul><li>' . implode('</li><li>', $msg) . '</li></ul>';
            $msg = $LANG_SHOP['msg_cart_invalid'] . $msg;
            COM_setMsg($msg, 'info');
        }
        return $invalid;
    }


    /**
     * Make sure all the required cart fields are valid prior to checkout.
     *
     * @param   boolean $login  True if logging in, to suppress some checks
     * @return  array   Array of error messages
     */
    public function Validate($login=false)
    {
        global $LANG_SHOP;

        $errors = array();
        $new_wf = '';
        if (!$login) {
            if ($this->buyer_email == '') {
                SESS_setVar('shop_focus_field', 'payer_email');
                $errors['payer_email'] = $LANG_SHOP['err_missing_email'];
            }
        }

        if ($this->requiresShipto()) {
            $Addr = new Address($this->Shipto->toArray());
            if ($Addr->isValid(true) != '') {
                $errors['shipto'] = $LANG_SHOP['req_shipto'];
            }
        }

        if ($this->requiresBillto()) {
            $Addr = new Address($this->Billto->toArray());
            if ($Addr->isValid(true) != '') {
                $errors['billtoto'] = $LANG_SHOP['req_billto'];
                $new_wf = 'addresses';
            }
        }

        if (!empty($errors)) {
            $msg = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
            COM_setMsg($msg, 'error');
        }
        return $new_wf;
    }


    /**
     * See if the buyer is able to skip directly to the order confirmation.
     * Typically possible for simple virtual items or downloads tha thave
     * no billing, shipping or tax, and where only one payment gateway and
     * no discounts are applicable.
     *
     * @return  boolean     True if cart editing page can be skipped
     */
    public function canFastCheckout()
    {
        global $_SHOP_CONF;

        $Gateways = Gateway::getAll();
        if (
            !$_SHOP_CONF['ena_fast_checkout'] ||    // not allowed
            COM_isAnonUser() ||         // can't be anonymous, need email addr
            count($Gateways) != 1 ||    // must have only one gateway
            DiscountCode::countCurrent() > 0 ||     // can't have active codes
            (
                $_SHOP_CONF['gc_enabled'] &&    // gift cards enabled
                count(Products\Coupon::getUserCoupons()) > 0
            )
        ) {
            return false;
        }

        // Get the first gateway (should be only one anyway)
        $gateway = array_shift($Gateways);

        // Get the customer information to set addresses and email addr
        $Customer = Customer::getInstance($this->uid);
        if ($this->billto_id < 1) {
            $this->setBillto($Customer->getDefaultAddress('billto'));
        }
        if ($this->shipto_id < 1) {
            $this->setShipto($Customer->getDefaultAddress('shipto'));
        }
        if ($this->billto_id == 0 || $this->shipto_id == 0) {
            // No address found
            return false;
        }

        // Go ahead and save the gateway as preferred for future use
        $Customer->setPrefGW($gateway->getName())
            ->saveUser();

        // Populate required elements of this order
        $this->setGateway($gateway->getName())
            ->setGC(0)
            ->setEmail($Customer->getEmail())
            ->setShipper(0)
            ->setInstructions('');

        SHOP_setUrl(SHOP_URL . '/index.php');
        return true;
    }


    /**
     * Determine if the discount code entry field can be shown.
     * This wrapper allows for future conditions based on group menbership,
     * existence of sale prices, etc. but currently just shows the field if
     * there are any active codes.
     *
     * @return  boolean     True if the field can be shown, False if not.
     */
    public function canShowDiscountEntry()
    {
        return DiscountCode::countCurrent() > 0 ? true : false;
    }


    /**
     * Check if the cart is up to date.
     * This uses the last_mod timestamp to see if it is outdated and should
     * be recalculated due to expiring/new sale prices, etc.
     *
     * @return  boolean     True if up to date, False if not.
     */
    public function isCurrent()
    {
        global $_CONF;

        $now = $_CONF['_now']->toUnix();
        $cart = $this->getLastMod(true);

        // Consider current if last updated with a day.
        if (($now - $cart) > 86400) {
            return false;
        } else {
            return true;
        }
    }

}
