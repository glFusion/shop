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

/**
 * Order view class.
 * @package shop
 */
class Order
{
    /** Order record ID.
     * @var string */
    protected $order_id;

    /** Order Object.
     * @var object */
    protected $Order;

    /** Template filename for the view.
     * @var string */
    protected $tplname;

    /** Tracking info template, if used.
     * @var string */
    protected $tracking_tpl;

    /** Indicate if this is editable or final.
     * @var boolean */
    protected $isFinalView;


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $order_id   Optional order ID to read
     */
    public function __construct($order_id='')
    {
        global $_USER, $_SHOP_CONF;

        if (!empty($order_id)) {
            $this->order_id = $order_id;
            $this->getOrder();
        }
    }


    /**
     * Load the order information from the database.
     *
     * @param   string  $id     Order ID
     * @return  object      Order object
     */
    public function getOrder($id = '')
    {
        global $_TABLES;

        if ($id != '') {
            $this->order_id = $id;
        }
        $this->Order = \Shop\Order::getInstance($this->order_id);
        return $this->Order;
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
    public function Render()
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP;

        $this->isFinalView = false;
        $is_invoice = true;    // normal view/printing view
        $icon_tooltips = array();

/*        switch ($view) {
        case 'order':
        case 'adminview';
            $this->isFinalView = true;
        case 'checkout':
            $tplname = 'order';
            break;
        case 'viewcart':
            $tplname = 'viewcart';
            break;
        case 'packinglist':
            // Print a packing list. Same as print view but no prices or fees shown.
            $is_invoice = false;
        case 'print':
        case 'printorder':
            $this->isFinalView = true;
            $tplname = 'order.print';
            break;
        case 'pdfpl':
            $is_invoice = false;
        case 'pdforder':
            $this->isFinalView = true;
            $tplname = 'order.pdf';
            break;
        case 'shipment':
            $this->isFinalView = true;
            $tplname = 'shipment';
            break;
        }
        $step = (int)$step;
 */
        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('order', $this->tplname . '.thtml');
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->Order->_addr_fields as $name) {
                $fldname = $type . '_' . $name;
                $T->set_var($fldname, $this->$fldname);
            }
        }

        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (\Shop\Workflow::getAll($this->Order) as $key => $wf) {
            $T->set_var('have_' . $wf->wf_name, 'true');
        }

        $T->set_block('order', 'ItemRow', 'iRow');

        $Currency = Currency::getInstance($this->Order->currency);
        $this->no_shipping = 1;   // no shipping unless physical item ordered
        $this->subtotal = 0;
        $item_qty = array();        // array to track quantity by base item ID
        $have_images = false;
        foreach ($this->Order->items as $item) {
            $P = $item->getProduct();
            if ($is_invoice) {
                $img = $P->getImage('', $_SHOP_CONF['order_tn_size']);
                if (!empty($img['url'])) {
                    $img_url = COM_createImage(
                        $img['url'],
                        '',
                        array(
                            'width' => $img['width'],
                            'height' => $img['height'],
                        )
                    );
                    $T->set_var('img_url', $img_url);
                    $have_images = true;
                } else {
                    $T->clear_var('img_url');
                }
            }

            if ($item->qty_discount > 0) {
                $discount_items ++;
                $price_tooltip = sprintf(
                    $LANG_SHOP['reflects_disc'],
                    ($item->qty_discount * 100)
                );
            } else {
                $price_tooltip = '';
            }
            $item_total = $item->price * $item->quantity;
            $this->subtotal += $item_total;
            if ($P->isTaxable()) {
                $this->tax_items++;       // count the taxable items for display
            }
            $T->set_var(array(
                'cart_item_id'  => $item->id,
                'fixed_q'       => $P->getFixedQuantity(),
                'item_id'       => htmlspecialchars($item->product_id),
                'item_dscp'     => htmlspecialchars($item->description),
                'item_price'    => $Currency->FormatValue($item->price),
                'item_quantity' => $item->quantity,
                'item_total'    => $Currency->FormatValue($item_total),
                'is_admin'      => $this->isAdmin,
                'is_file'       => $item->canDownload(),
                'taxable'       => $this->tax_rate > 0 ? $P->isTaxable() : 0,
                'tax_icon'      => $LANG_SHOP['tax'][0],
                'discount_icon' => 'D',
                'discount_tooltip' => $price_tooltip,
                'token'         => $item->token,
                //'item_options'  => $P->getOptionDisplay($item),
                'item_options'  => $item->getOptionDisplay(),
                'sku'           => $P->getSKU($item),
                'item_link'     => $P->getLink($item->id),
                'pi_url'        => SHOP_URL,
                'is_invoice'    => $is_invoice,
                'del_item_url'  => COM_buildUrl(SHOP_URL . "/cart.php?action=delete&id={$item->id}"),
            ) );
            if ($P->isPhysical()) {
                $this->no_shipping = 0;
            }
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        if ($discount_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['discount'][0] . ' = Includes discount';
        }
        if ($this->tax_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['taxable'][0] . ' = ' . $LANG_SHOP['taxable'];
        }
        $this->total = $this->Order->getTotal();     // also calls calcTax()
        $icon_tooltips = implode('<br />', $icon_tooltips);
        $by_gc = (float)$this->Order->getInfo('apply_gc');

        // Call selectShipper() here to get the shipping amount into the local var.
        $shipper_select = $this->Order->selectShipper();
        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'ship_select'   => $this->isFinalView ? NULL : $shipper_select,
            'shipper_id'    => $this->shipper_id,
            'total'         => $Currency->Format($this->total),
            'not_final'     => !$this->isFinalView,
            'order_date'    => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], false),
            'order_number'  => $this->Order->order_id,
            'shipping'      => $Currency->FormatValue($this->shipping),
            'handling'      => $this->handling > 0 ? $Currency->FormatValue($this->handling) : 0,
            'subtotal'      => $this->subtotal == $this->total ? '' : $Currency->Format($this->subtotal),
            'order_instr'   => htmlspecialchars($this->instructions),
            'shop_name'     => $_SHOP_CONF['shop_name'],
            'shop_addr'     => $_SHOP_CONF['shop_addr'],
            'shop_phone'    => $_SHOP_CONF['shop_phone'],
            'apply_gc'      => $by_gc > 0 ? $Currency->FormatValue($by_gc) : 0,
            'net_total'     => $Currency->Format($this->total - $by_gc),
            'cart_tax'      => $this->tax > 0 ? $Currency->FormatValue($this->tax) : 0,
            //'lang_tax_on_items'  => sprintf($LANG_SHOP['tax_on_x_items'], $this->tax_rate * 100, $this->tax_items),
            'lang_tax_on_items'  => $LANG_SHOP['sales_tax'],
            'status'        => $this->status,
            'token'         => $this->token,
            'allow_gc'      => $_SHOP_CONF['gc_enabled']  && !COM_isAnonUser() ? true : false,
            'next_step'     => $step + 1,
            'not_anon'      => !COM_isAnonUser(),
            'ship_method'   => Shipper::getInstance($this->shipper_id)->name,
            'total_prefix'  => $Currency->Pre(),
            'total_postfix' => $Currency->Post(),
            'total_num'     => $Currency->FormatValue($this->total),
            'cur_decimals'  => $Currency->Decimals(),
            'item_subtotal' => $Currency->FormatValue($this->subtotal),
            'return_url'    => SHOP_getUrl(),
            'is_invoice'    => $is_invoice,
            'icon_dscp'     => $icon_tooltips,
            'print_url'     => $this->Order->buildUrl('print'),
            'have_images'   => $is_invoice ? $have_images : false,
            'linkPackingList' => \Shop\Order::linkPackingList($this->order_id),
            'linkPrint'     => \Shop\Order::linkPrint($this->order_id, $this->token),
        ) );

        if ($this->isAdmin) {
            $T->set_var(array(
                'is_admin'      => true,
                'purch_name'    => COM_getDisplayName($this->uid),
                'purch_uid'     => $this->uid,
                'stat_update'   => OrderStatus::Selection($this->order_id, 1, $this->status),
            ) );
        }

        // Instantiate a date objet to handle formatting of log timestamps
        $dt = new \Date('now', $_USER['tzid']);
        $log = $this->Order->getLog();
        $T->set_block('order', 'LogMessages', 'Log');
        foreach ($log as $L) {
            $dt->setTimestamp($L['ts']);
            $T->set_var(array(
                'log_username'  => $L['username'],
                'log_msg'       => $L['message'],
                'log_ts'        => $dt->format($_SHOP_CONF['datetime_fmt'], true),
                'log_ts_tip'    => $dt->format($_SHOP_CONF['datetime_fmt'], false),
            ) );
            $T->parse('Log', 'LogMessages', true);
        }

        $payer_email = $this->buyer_email;
        if ($payer_email == '' && !COM_isAnonUser()) {
            $payer_email = $_USER['email'];
        }
        $focus_fld = SESS_getVar('shop_focus_field');
        if ($focus_fld) {
            $T->set_var('focus_element', $focus_fld);
            SESS_unSet('shop_focus_field');
        }
        $T->set_var('payer_email', $payer_email);

        switch ($view) {
        case 'viewcart':
            $T->set_var('gateway_radios', $this->getCheckoutRadios());
            break;
        case 'checkout':
            $gw = Gateway::getInstance($this->getInfo('gateway'));
            if ($gw) {
                $T->set_var(array(
                    'gateway_vars'  => $this->checkoutButton($gw),
                    'checkout'      => 'true',
                    'pmt_method'    => $gw->getDscp(),
                ) );
            }
        default:
            break;
        }

        $status = $this->status;
        if ($this->pmt_method != '') {
            $gw = Gateway::getInstance($this->pmt_method);
            if ($gw !== NULL) {
                $pmt_method = $gw->getDscp();
            } else {
                $pmt_method = $this->pmt_method;
            }

            $T->set_var(array(
                'pmt_method' => $pmt_method,
                'pmt_txn_id' => $this->pmt_txn_id,
                'ipn_det_url' => IPN::getDetailUrl($this->pmt_txn_id, 'txn_id'),
            ) );
        }

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }

}

?>
