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
use Shop\Currency;
use Shop\Workflow;
use Shop\Shipment;
use Shop\Shipper;
use Shop\Template;
use Shop\Gateway;
use Shop\Company;
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
        $this->TPL = new Template;
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
        switch ($view) {
        case 'viewcart':
            $this->tplname = 'viewcart';
            break;
        case 'checkout':
        case 'order':
            $this->tplname = 'order';
            break;
        }
        $this->TPL = new Template;
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
        $output = $this->createHTML();
        return $output;
    }


    /**
     * View or print the current order.
     * Access is controlled by the caller invoking canView() since a token
     * may be required.
     *
     * @param  string  $view       View to display (cart, final order, etc.)
     * @param  integer $step       Current step, for updating next_step in the form
     * @return string      HTML for order view
     */
    public function createHTML()
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP, $LANG_SHOP_HELP;

        $icon_tooltips = array();

        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (\Shop\Workflow::getAll($this->Order) as $key => $wf) {
            $this->TPL->set_var('have_' . $wf->wf_name, 'true');
        }

        if ($this->tplname == 'viewcart') {
            $this->TPL->set_var(array(
                'not_final' => true,
                'ship_select'   => $this->Order->selectShipper(),
             ) );
        }
        $this->_renderCommon();
        $this->_renderAddresses();
        $this->_renderItems();

        $payer_email = $this->Order->getBuyerEmail();
        switch ($this->tplname) {
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
        }

        $this->TPL->set_var('payer_email', $payer_email);
        $icon_tooltips = implode('<br />', $icon_tooltips);
        $by_gc = (float)$this->Order->getInfo('apply_gc');
        $total = $this->Order->getTotal();     // also calls calcTax()
        $this->TPL->set_var(array(
            'apply_gc'      => $by_gc > 0 ? $this->Currency->FormatValue($by_gc) : 0,
            'net_total'     => $this->Currency->Format($total - $by_gc),
        ) );

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

}
