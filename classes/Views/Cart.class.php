<?php
/**
 * Class to present an view of an order
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Views;
use Shop\Config;
use Shop\Currency;
use Shop\Workflow;
use Shop\Shipment;
use Shop\Shipper;
use Shop\Template;
use Shop\Gateway;
use Shop\Company;
use Shop\Customer;
use Shop\Address;
use Shop\IPN;
use Shop\OrderStatus;


/**
 * Order view class.
 * @package shop
 */
class Cart extends OrderBaseView
{
    /** View type, e.g. order or packing list.
     * @var string */
    protected $output_type = 'html';

    /** Packing List vs. full order display.
     * @var string */
    protected $type = 'cart';


    /**
     * Set internal variables and read the existing order if an id is provided.
     */
    public function __construct($order_id = NULL)
    {
        $this->tplname = 'viewcart';
        $this->TPL = new Template('workflow/');
        $this->TPL->set_file($this->tplname, 'viewcart.thtml');
        $this->Currency = Currency::getInstance();
        if ($order_id !== NULL) {
            $this->Order = \Shop\Cart::getInstance($order_id);
        }
    }


    /**
     * Set the order ID if this is for the entire order.
     *
     * @param   string  $order_id   Order record ID
     * @return  object  $this
     */
    public function withOrderId($order_id)
    {
        $this->order_id = $order_id;
        $this->Order = \Shop\Cart::getInstance($order_id);
        $this->Currency = $this->Order->getCurrency();
        return $this;

        if (!is_array($order_id)) {
            $this->order_ids = array($order_id);
        } else {
            $this->order_ids = $order_id;
        }
        return $this;
    }


    /**
     * Set the view type. Carts are either being updated or finalized.
     *
     * @param   string  $view   View type
     * @return  object  $this
     */
    public function withView($view)
    {
        $this->TPL = new Template;
        switch ($view) {
        case 'viewcart':
            $this->TPL = new Template('workflow/');
            $this->tplname = 'viewcart';
            break;
        case 'shipping':
            $this->tplname = 'shipping';
            break;
        case 'checkout':
        case 'order':
            $this->tplname = 'order';
            break;
        }
        $this->TPL->set_file($this->tplname, $this->tplname . '.thtml');
        return $this;
    }


    /**
     * Render the view.
     *
     * @return  string      HTML for cart view.
     */
    public function Render()
    {
        // Make sure there's a valid address object for the Shipto address
        // instead of NULL
        if (!$this->Order->getShipto()) {
            $this->Order->setShipto(
                Customer::getInstance($this->Order->getUid())->getDefaultAddress('shipto')
            );
        }
        $this->Order->checkRules();
        $output = $this->createHTML2();
        return $output;
    }


    /**
     * View or print the current order.
     * Access is controlled by the caller invoking canView() since a token
     * may be required.
     *
     * @param  boolean  $final      True if this is a final, non-editable fiew
     * @return string       HTML for order view
     */
    public function createHTML2($final = false)
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP, $LANG_SHOP_HELP;

        $icon_tooltips = array();

        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (\Shop\Workflow::getAll($this->Order) as $key => $wf) {
            $this->TPL->set_var('have_' . $wf->getName(), 'true');
        }

        if (!$this->Order->requiresShipto()) {
            $this->Order->setShipper(NULL);
            $this->Order->setShipto(NULL);
        }
        if (!$this->Order->requiresBillto()) {
            $this->Order->setBillto(NULL);
        }

        $this->Order->calcTotal();
        $this->TPL->set_var('final_checkout', $final);
        /*if ($this->tplname == 'viewcart') {
            $this->TPL->set_var(array(
                'not_final' => true,
                'ship_select'   => $this->Order->selectShipper(),
             ) );
        }*/
        $this->_renderCommon();
        $this->_renderAddresses();
        $this->_renderItems();
        if ($this->Order->hasInvalid()) {
            $this->TPL->set_var('rules_msg', $LANG_SHOP_HELP['hlp_rules_noitems']);
        }

        $payer_email = $this->Order->getBuyerEmail();
        if (empty($payer_email)) {
            $Cust = Customer::getInstance($this->Order->getUid());
            $payer_email = $Cust->getEmail();
        }
        /*switch ($this->tplname) {
        case 'viewcart':
            $this->TPL->set_var('gateway_radios', $this->Order->getCheckoutRadios());
            if ($this->Order->hasInvalid()) {
                $this->TPL->set_var('rules_msg', $LANG_SHOP_HELP['hlp_rules_noitems']);
            }
            if ($payer_email == '' && !COM_isAnonUser()) {
                $payer_email = $_USER['email'];
            }
            $focus_fld = SESS_getVar('shop_focus_field');
            if ($focus_fld) {
                $this->TPL->set_var('focus_element', $focus_fld);
                SESS_unSet('shop_focus_field');
            }
            $this->TPL->set_var('next_step', 1);
            break;
        case 'order':
            $this->TPL->set_var('checkout', true);
            if ($this->Order->hasInvalid()) {
                $this->TPL->set_var('rules_msg', $LANG_SHOP_HELP['hlp_rules_noitems']);
            } else {
                $gw = Gateway::getInstance($this->Order->getInfo('gateway'));
                if ($gw) {
                    $this->TPL->set_var(array(
                        'gateway_vars'  => $this->Order->checkoutButton($gw),
                        'pmt_method'    => $this->Order->getPmtMethod(),
                        'pmt_dscp'    => $this->Order->getPmtDscp(),
                    ) );
                }
            }
            break;
        }*/
        $icon_tooltips = implode('<br />', $icon_tooltips);
        $by_gc = (float)$this->Order->getGC();
        $total = $this->Order->getTotal();     // also calls calcTax()
        $this->TPL->set_var(array(
            'apply_gc'      => $by_gc > 0 ? $this->Currency->FormatValue($by_gc) : 0,
            'net_total'     => $this->Currency->Format($total - $by_gc),
            'discount_code_fld' => $this->Order->canShowDiscountEntry(),
            'ref_token_fld' => $_SHOP_CONF['aff_enabled'] && $_SHOP_CONF['aff_allow_entry'],
            'ref_token' => $this->Order->getReferralToken(),
            'discount_code' => $this->Order->getDiscountCode(),
            'dc_row_vis'    => $this->Order->getDiscountCode(),
            'dc_amt'        => $this->Currency->FormatValue($this->Order->getDiscountAmount() * -1),
            'dc_pct'        => $this->Order->getDiscountPct() . '%',
            'net_items'     => $this->Currency->Format($this->Order->getItemTotal()),
            'buyer_email'   => $payer_email,
        ) );
        if (!$final) {
            $T1 = new Template('workflow/');
            $T1->set_file('footer', 'footer.thtml');
            $this->TPL->set_var('footer', $T1->parse('', 'footer'));
        }
        $this->TPL->parse('output', $this->tplname);
        $form = $this->TPL->finish($this->TPL->get_var('output'));
        return $form;
    }


    /**
     * Get the shipping info block for display on order views.
     *
     * @return  string      HTML for shipping info block
     */
    public function getShipmentBlock()
    {
        global $_CONF;

        $Shipments = Shipment::getByOrder($this->Order->getOrderId());
        if (empty($Shipments)) {
            return '';
        }
        $T = new Template;
        $T->set_file('html', 'shipping_block.thtml');
        $T->set_block('html', 'Packages', 'packages');
        foreach ($Shipments as $Shipment) {
            $Packages = $Shipment->getPackages();
            if (empty($Packages)) {
                // Create a dummy package so something shows for the shipment
                $Packages = array(new ShipmentPackage());
            }
            $show_ship_info = true;
            foreach ($Packages as $Pkg) {
                $Shipper = Shipper::getInstance($Pkg->getShipperID());
                $url = $Shipper->getTrackingUrl($Pkg->getTrackingNumber());
                $T->set_var(array(
                    'show_ship_info' => $show_ship_info,
                    'ship_date'     => $Shipment->getDate()->toMySQL(true),
                    'shipment_id'   => $Shipment->getID(),
                    'shipper_info'  => $Pkg->getShipperInfo(),
                    'tracking_num'  => $Pkg->getTrackingNumber(),
                    'shipper_id'    => $Pkg->getShipperID(),
                    'tracking_url'  => $url,
                    'ret_url'       => urlencode($_SERVER['REQUEST_URI']),
                ) );
                $show_ship_info = false;
                $T->parse('packages', 'Packages', true);
            }
        }
        $T->parse('output', 'html');
        $html = $T->finish($T->get_var('output'));
        return $html;
    }


    /**
     * Display the shipping options available for this order.
     *
     * @return  string      HTML for shipping selection form
     */
    public function shippingSelection()
    {
        $methods = $this->Order->getShippingOptions();

        $T = new Template('workflow/');
        $T->set_file(array(
            'form' => 'shipping.thtml',
            'footer' => 'footer.thtml',
        ) );
        $T->set_block('form', 'shipMethods', 'SM');
        foreach ($methods as $method_id=>$method) {
            $s_amt = $method['method_rate'];
            $T->set_var(array(
                'method_sel'    => $method['method_sel'] ? 'checked="checked"' : '',
                'method_name'   => $method['method_name'],
                'method_rate'   => Currency::getInstance()->Format($s_amt),
                'method_id'     => $method_id,
            ) );
            $T->parse('SM', 'shipMethods', true);
            if (count($methods) == 1) {
                $this->Order->setShipper($method['method_id']);
                $this->Order->setShipping($s_amt);
            }
        }
        $T->set_var('form_footer', $T->parse('', 'footer'));
        $T->parse('output', 'form');
        return  $T->finish($T->get_var('output'));
    }


    /**
     * Display the payment selection options for this order.
     *
     * @return  string      HTML payment selection form
     */
    public function paymentSelection()
    {
        global $_SHOP_CONF;

        if (!$this->Order->hasItems()) {
            COM_refresh(Config::get('url'));
        }

        $retval = '';
        $T = new Template('workflow/');
        $T->set_file(array(
            'form' => 'payment.thtml',
            'footer' => 'footer.thtml',
        ) );
        $total = $this->Order->getTotal();
        if (!$_SHOP_CONF['anon_buy'] && COM_isAnonUser()) {
            return $retval;
        }
        $gw_sel = $this->Order->getPmtMethod();
        $gateways = Gateway::getEnabled();
        if (isset($gateways['_coupon'])) {
            $gc_bal = \Shop\Products\Coupon::getUserBalance();
            if (empty($gw_sel) && $gc_bal >= $total) {
                $gw_sel = '_coupon';
            }
        } else {
            $gc_bal = 0;
        }
        if (empty($gateways)) {
            return $retval;  // no available gateways
        }

        if ($total == 0) {
            // Automatically select the "free" gateway if appropriate.
            // Other gateways shouldn't be shown anyway.
            $gw_sel = 'free';
        /*} elseif (
            isset($this->m_info['gateway']) &&
            array_key_exists($this->m_info['gateway'], $gateways)
        ) {
            // Select the previously selected gateway
            $gw_sel = $this->m_info['gateway'];*/
        } elseif ($gc_bal >= $total) {
            // Select the coupon gateway as full payment
            $gw_sel = '_coupon';
        } elseif (empty($gw_sel)) {
            // Select the first if there's one, otherwise select none.
            $gw_sel = Gateway::getSelected();
            if ($gw_sel == '') {
                $gw_sel = Customer::getInstance($this->Order->getUid())->getPrefGW();
            }
        }

        $T->set_block('radios', 'Radios', 'row');
        foreach ($gateways as $gw_id=>$gw) {
            if (is_null($gw) || !$gw->hasAccess($total)) {
                continue;
            }
            if ($gw->Supports('checkout')) {
                if ($gw_sel == '') $gw_sel = $gw->getName();
                $opt = $gw->checkoutRadio($this->Order, $gw_sel == $gw->getName());
                if ($opt) {
                    $T->set_var(array(
                        'gw_id' => $opt['gw_name'],
                        'opt_type' => $opt['type'],
                        'opt_value' => $opt['value'],
                        'logo'  => $opt['logo'],
                        'sel'   => $opt['selected'],
                        'highlight' => $opt['highlight'],
                    ) );
                    $T->parse('row', 'Radios', true);
                }
            }
        }
        $T->set_var('wrap_form', true);
        $T->set_var('form_footer', $T->parse('', 'footer'));
        $T->parse('output', 'form');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Display the selection forms for shipping and billing addresses.
     * If this is a new customer and there are no addresses available,
     * go directly to the address entry form.
     *
     * @return  string      HTML for address selection form
     */
    public function addressSelection()
    {
        SHOP_setUrl(SHOP_URL . '/cart.php?addresses');
        $Cust = Customer::getInstance($this->Order->getUid());
        if (empty($Cust->getAddresses())) {
            COM_refresh(SHOP_URL . '/account.php?mode=editaddr&id=0&return=cart_addresses');
        }

        $T = new Template('workflow/');
        $T->set_file(array(
            'form' => 'addresses.thtml',
            'footer' => 'footer.thtml',
        ) );

        if ($this->Order->getBillto()->getID() < 1) {
            $this->Order->setBillto($Cust->getDefaultAddress('billto'));
        }
        if ($this->Order->getShipto()->getID() < 1) {
            $this->Order->setShipto($Cust->getDefaultAddress('shipto'));
        }
        $billto_opts = Address::optionList(
            $this->Order->getUid(),
            'billto',
            $this->Order->getBillto()->getID()
        );
        $shipto_opts = Address::optionList(
            $this->Order->getUid(),
            'shipto',
            $this->Order->getShipto()->getID()
        );
        $T->set_var(array(
            'billto_addr'   => $this->Order->getBillto()->toHTML(),
            'shipto_addr'   => $this->Order->getShipto()->toHTML(),
            'billto_opts'   => $billto_opts,
            'shipto_opts'   => $shipto_opts,
            'order_instr'   => $this->Order->getInstructions(),
            'buyer_email'   => $this->Order->getBuyerEmail(),
            'billto_id'     => $this->Order->getBillto()->getID(),
            'shipto_id'     => $this->Order->getShipto()->getID(),
            'wrap_form'     => true,
        ) );
        $T->set_var('form_footer', $T->parse('', 'footer'));
        $T->parse('output', 'form');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Display the final order confirmation page.
     * There is no editing on this page.
     *
     * @return  string      HTML for final checkout page
     */
    public function confirmCheckout()
    {
        if (!$this->Order->isCurrent()) {
            COM_refresh(SHOP_URL . '/cart.php');
        }

        $this->TPL = new Template('workflow/');
        $this->tplname = 'checkout';
        $this->TPL->set_file('checkout', 'checkout.thtml');

        $this->Order->verifyReferralTag();
        $this->Order->checkRules();
        $gw = Gateway::getInstance($this->Order->getPmtMethod());
        $this->TPL->set_var(array(
            'order_instr'   => $this->Order->getInstructions(),
            'shipping_method' => $this->Order->getShipperDscp(),
            'pmt_method' => $this->Order->getPmtDscp(),
            'buyer_email'   => $this->Order->getBuyerEmail(),
            'checkout_button' => $this->Order->checkoutButton($gw),
        ) );
        return $this->createHTML2(true);
    }

}
