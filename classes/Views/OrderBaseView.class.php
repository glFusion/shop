<?php
/**
 * Class to present an view of an order.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2023 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
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
use Shop\IPN;
use Shop\FieldList;
use Shop\Models\OrderStatus;


/**
 * Order view class.
 * @package shop
 */
class OrderBaseView
{
    use \Shop\Traits\PDF;

    /** Order record ID.
     * @var string */
    protected $order_ids = array();

    /** Single Order Record ID.
     * @var string */
    protected $order_id = '';

    /** Order Object.
     * @var object */
    protected $Order = NULL;

    /** Items to show.
     * Depends on whether this is for the order or a partial shipment.
     * @var array */
    protected $Items = array();

    /** Token string to authenticate anonymous views.
     * @var string */
    protected $token = '';

    /** Template filename for the view. Default = HTML template.
     * @var string */
    protected $tplname = 'order';

    /** Tracking info template, if used.
     * @var string */
    protected $tracking_tpl = '';

    /** Indicate if this is editable or final.
     * @var boolean */
    protected $isFinalView = false;

    /** Flag to indicate this is an admin.
     * @var boolean */
    protected $isAdmin = false;

    /** View type, e.g. order or packing list.
     * @var string */
    protected $output_type = 'html';

    /** Packing List vs. full order display.
     * @var string */
    protected $type = 'order';

    /** Flag to include the shop address information.
     * @var boolean */
    protected $with_shop_info = false;

    /** Template object in use.
     * @var object */
    protected $TPL = NULL;

    /** Order currency.
     * @var object */
    protected $Currency = NULL;

    /** Flag to indicate this is an invoiced (not cart) order.
     * @var boolean */
    protected $is_invoice = true;


    /**
     * Set internal variables and read the existing order if an id is provided.
     */
    public function __construct($order_id = NULL)
    {
        $this->Currency = Currency::getInstance();
        if ($order_id !== NULL) {
            $this->getOrder($order_id);
        }

    }


    /**
     * Set the order ID if this is for the entire order.
     *
     * @param   string  $order_id   Order record ID
     * @return  object  $this
     */
    public function withOrderId(string $order_id) : self
    {
        if (!is_array($order_id)) {
            $this->order_ids = array($order_id);
            $this->order_id = $order_id;
            $this->Order = \Shop\Order::getInstance($order_id);
            $this->Currency = $this->Order->getCurrency();
        } else {
            $this->order_ids = $order_id;
        }
        return $this;
    }


    /**
     * Set the order object to display.
     *
     * @param   object  $Order  Order object
     * @return  object  $this
     */
    public function withOrder($Order)
    {
        $this->Order = $Order;
        $this->order_id = $Order->getOrderID();
        return $this;
    }


    /**
     * Set the authorization token being used to view the order.
     * Allows for anonymous buyers to view their order.
     *
     * @param   string  $token  Token string
     * @return  object  $this
     */
    public function withToken($token)
    {
        $this->token = $token;
        return $this;
    }


    /**
     * Set the flag to indicate that this is an administrative view.
     *
     * @param   boolean $flag   True for administration, False for normal view
     * @return  object  $this
     */
    public function setAdmin($flag)
    {
        $this->isAdmin = $flag ? true : false;
        return $this;
    }


    /**
     * Set the desired output type, either on-screen HTML or a PDF file.
     *
     * @param   string  $out_type   `html` or `pdf`
     * @return  object  $this
     */
    public function withOutput($out_type)
    {
        $this->output_type = $out_type;
        if ($out_type == 'pdf') {
            $this->isFinalView = true;
        }
        return $this;
    }


    /**
     * Set the view type as "packing list" to exclude pricing fields.
     *
     * @return  object  $this
     */
    public function asPackingList()
    {
        $this->type = 'packinglist';
        $this->is_invoice = false;
        return $this;
    }


    /**
     * Set the view type as "invoice" to include pricing fields.
     *
     * @return  object  $this
     */
    public function asInvoice()
    {
        $this->type = 'order';
        $this->is_invoice = true;
        return $this;
    }


    /**
     * Set the view type, normally either "packinglist" or "invoice".
     *
     * @param   string  $type       View type
     * @return  object  $this
     */
    public function withType(string $type) : self
    {
        $this->type = $type;
        return $this;
    }


    /**
     * Check if the output format is on-screen HTML.
     *
     * @return  boolean     True if html, False if not
     */
    protected function isHTML() : bool
    {
        return $this->output_type == 'html';
    }


    /**
     * Check if the output format is a PDF file.
     *
     * @return  boolean     True if pdf, False if not
     */
    protected function isPDF()
    {
        return $this->output_type == 'pdf';
    }


    /**
     * Load the order information from the database.
     *
     * @param   string  $id     Order ID
     * @return  object      Order object
     */
    public function getOrder($id = '')
    {
        if ($id != '') {
            $this->order_id = $id;
        }
        $this->Order = \Shop\Order::getInstance($this->order_id);
        return $this->Order;
    }


    /**
     * Render multiple orders, normally as PDF.
     * Called when multiple orders are checked for printing.
     *
     * @param   array   $order_ids  Array of order IDs
     * @return  mixed   HTML or PDF output
     */
    public function RenderMulti($order_ids)
    {
        if ($this->output_type == 'pdf') {
            $this->tplname .= '.pdf';
            $this->initPDF();
        }
        foreach ($order_ids as $order_id) {
            $View = new self;
            //$this->Order = \Shop\Order::getInstance($order_id);
            //$this->Items = $this->Order->getItems();
            /*if (!$this->Order->canView($this->token)) {
                continue;
        }*/
            $output = $this->withOrderId($order_id)->createHTML();
            if ($this->output_type == 'html') {
                // HTML is only available for single orders, so return here.
                return $output;
            } elseif ($this->output_type == 'pdf') {
                $this->writePDF($output);
            }
        }
        if ($this->output_type == 'pdf') {
            $this->finishPDF();
        }
    }


    /**
     * Render a single order.
     *
     * @return  string      HTML for order display
     */
    public function Render()
    {
        if ($this->output_type == 'pdf') {
            $this->tplname .= '.pdf';
            $this->initPDF();
        }
        foreach ($this->order_ids as $order_id) {
            $this->Order = \Shop\Order::getInstance($order_id);
            $this->Items = $this->Order->getItems();
            if (!$this->Order->canView($this->token)) {
                continue;
            }
            $output = $this->withOrderId($order_id)->createHTML();
            if ($this->output_type == 'html') {
                // HTML is only available for single orders, so return here.
                return $output;
            } elseif ($this->output_type == 'pdf') {
                $this->writePDF($output);
            }
        }
        if ($this->output_type == 'pdf') {
            $this->finishPDF();
        }
        /*if (!$this->Order->canView($this->token)) {
            return '';
        }
        if ($this->output_type == 'html') {
            $output = $this->createHTML();
            return $output;
        } elseif ($this->output_type == 'pdf') {
            $this->tplname .= '.pdf';
            $output = $this->createHTML();
            return $this->createPDF($output);
        }*/
    }


    /**
     * Render common data elements for multiple views.
     *
     * @return  string      HTML for common elements in the order view
     */
    protected function _renderCommon()
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $this->TPL->set_var(array(
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'not_anon'      => !COM_isAnonUser(),
            'linkPackingList' => \Shop\Order::linkPackingList($this->order_id),
            'linkPrint'     => \Shop\Order::linkPrint($this->order_id, $this->Order->getToken()),
            'print_url'     => $this->Order->buildUrl('print'),
            'return_url'    => SHOP_getUrl(),
            'order_date'    => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], false),
            'order_number'  => $this->Order->getOrderID(),
            'order_number_esc' => urlencode($this->Order->getOrderID()),
            'order_pub_link' => FieldList::link(array(
                'url' => COM_buildUrl(Config::get('url') . '/order.php?mode=view&id=' . $this->Order->getOrderID() . '&token=' . $this->Order->getToken()),
                'attr' => array(
                    'target' => '_new',
                    'title' => $LANG_SHOP['order_pub_link'],
                    'class' => 'tooltip',
                ),
            ) ),
        ) );
    }


    /**
     * Display the shop address for invoices.
     *
     * @return  object  $this
     */
    protected function _renderShopAddress()
    {
        $ShopAddr = new Company;
        $this->TPL->set_var(array(
            'shop_name'     => $ShopAddr->toHTML('company'),
            'shop_addr'     => $ShopAddr->toHTML('address'),
            'shop_phone'    => $ShopAddr->getPhone(),
            'shop_email'    => $ShopAddr->getEmail(),
        ) );
        return $this;
    }


    /**
     * Show the billing and shipping addresses.
     *
     * @return  object  $this
     */
    protected function _renderAddresses()
    {
        if (
            $this->Order->getBillto()->getID() != 0 ||
            $this->Order->getShipto()->getID() != 0
        ) {
            $this->TPL->set_var(array(
                'billto_addr'   =>$this->Order->getBillto()->toHTML(),
                'billto_phone'  => $this->Order->getBillto()->toText('phone'),
                'shipto_addr'   =>$this->Order->getShipto()->toHTML(),
                'shipto_phone'  => $this->Order->getShipto()->toText('phone'),
                'show_addresses' => true,
            ) );
        }
        return $this;
    }


    /**
     * Display the order items and other charges.
     *
     * @return  object  $this
     */
    protected function _renderItems()
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $no_shipping = 1;   // no shipping unless physical item ordered
        $subtotal = 0;
        $item_qty = array();        // array to track quantity by base item ID
        $have_images = false;
        $total = 0;
        $tax_items = 0;
        $discount_items = 0;
        $handling = $this->Order->getHandling();
        $shipping = $this->Order->getShipping();
        $icon_tooltips = array();

        $this->TPL->set_block('order', 'ItemRow', 'iRow');
        foreach ($this->Order->getItems() as $Item) {
            $P = $Item->getProduct();
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
                $this->TPL->set_var('img_url', $img_url);
                $have_images = true;
            } else {
                $this->TPL->clear_var('img_url');
            }

            if ($Item->getDiscount() > 0) {
                $discount_items ++;
                $price_tooltip = sprintf(
                    $LANG_SHOP['reflects_disc'],
                    ($Item->getDiscount())
                );
                $this->TPL->set_var(array(
                    'discount_icon' => $LANG_SHOP['discount'][0],
                    'discount_tooltip' => $price_tooltip,
                ) );
            } else {
                $this->TPL->set_var(array(
                    'discount_icon' => '',
                    'discount_tooltip' => '',
                ) );
            }
            if ($Item->getPrice() > $Item->getNetPrice()) {
                $this->TPL->set_var(array(
                    'dc_icon' => 'C',
                    'dc_tip' => sprintf(
                        $LANG_SHOP['dc_applied_tip'],
                        $this->Order->getDiscountPct()
                    ),
                ) );
            } else {
                $this->TPL->set_var(array(
                    'dc_icon' => '',
                    'dc_tip' => '',
                ) );
            }
            $item_total = $Item->getPrice() * $Item->getQuantity();
            $subtotal += $item_total;
            if ($P->isTaxable()) {
                $tax_items++;       // count the taxable items for display
                $this->TPL->set_var(array(
                    'tax_icon' => $LANG_SHOP['tax'][0],
                    'tax_tooltip' => sprintf(
                        '%s%% %s',
                        $Item->getTaxRate() * 100,
                        $LANG_SHOP['sales_tax']
                    ),
                ) );
            } else {
                $this->TPL->set_var(array(
                    'tax_icon' => '',
                    'tax_tooltip' => '',
                ) );
            }

            $item_id = $Item->getID();      // since used so often
            $this->TPL->set_var(array(
                'cart_item_id'  => $item_id,
                'oi_id'         => $item_id,    // record ID, better name
                'fixed_q'       => $P->getFixedQuantity(),
                'item_id'       => htmlspecialchars($Item->getProductID()),
                'item_dscp'     => htmlspecialchars($Item->getDscp()),
                'item_price'    => $this->Currency->FormatValue($Item->getPrice()),
                'item_quantity' => $Item->getQuantity(),
                'item_total'    => $this->Currency->FormatValue($item_total),
                'is_admin'      => $this->isAdmin,
                'adm_edit_icon'     => FieldList::edit(array(
                    'url' => '#!',
                    'attr' => array(
                        'onclick' => "popupOIeditor('$item_id');",
                        'title' => $LANG_SHOP['edit_item'],
                        'class' => 'tooltip',
                    )
                )),
                'adm_delete_icon'   => FieldList::delete(array(
                    'delete_url' => Config::get('admin_url') . '/orders.php?order_id=' . $this->order_id . '&oi_delete=' . $item_id,
                    'attr' => array(
                        'onclick' => "return confirm('{$LANG_SHOP['q_del_item']} {$LANG_SHOP['change_permanent']}')",

                        'title' => $LANG_SHOP['remove_item'],
                        'class' => 'tooltip',
                    )
                )),
                'is_file'       => $Item->canDownload(),
                'taxable'       => $this->Order->getTaxRate() > 0 ? $Item->isTaxable() : 0,
                'token'         => $Item->getToken(),
                'item_options'  => $Item->getOptionDisplay(),
                'item_extras'   => $Item->getExtraDisplay(),
                'sku'           => $Item->getSKU(),
                'item_link'     => $P->withOrderItem($item_id)->getLink(),
                'pi_url'        => SHOP_URL,
                'is_invoice'    => $this->is_invoice,
                'del_item_url'  => COM_buildUrl(SHOP_URL . "/cart.php?action=delete&id={$Item->getID()}"),
                'embargoed'     => $Item->getInvalid(),
                'del_item_link' => FieldList::delete(array(
                    'delete_url' => COM_buildUrl(SHOP_URL . "/cart.php?action=delete&id={$Item->getID()}"),
                    'attr' => array(
                        'title' => $LANG_SHOP['remove_item'],
                        'class' => 'tooltip',
                    )
                ) ),
            ) );

            if ($P->isPhysical()) {
                $no_shipping = 0;
            }
            $this->TPL->parse('iRow', 'ItemRow', true);
            $this->TPL->clear_var('iOpts');
        }

        if ($discount_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['discount'][0] . ' = Includes discount';
        }
        if ($tax_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['taxable'][0] . ' = ' . $LANG_SHOP['taxable'];
        }
        $total = $this->Order->getTotal();     // also calls calcTax()
        $icon_tooltips = implode('<br />', $icon_tooltips);
        $by_gc = (float)$this->Order->getGC();

        $this->TPL->set_var(array(
            'total_prefix'  => $this->Currency->Pre(),
            'total_postfix' => $this->Currency->Post(),
            'total_num'     => $this->Currency->FormatValue($total),
            'cur_decimals'  => $this->Currency->Decimals(),
            'item_subtotal' => $this->Currency->Format($subtotal),
            'is_invoice'    => $this->is_invoice,
            'icon_dscp'     => $icon_tooltips,
            'have_images'   => $this->is_invoice ? $have_images : false,
            'shipping'      => $shipping,
            'shipping_fmt'  => $this->Currency->FormatValue($shipping),
            'ship_method'   => $this->Order->getShipperDscp(),
            'handling'      => $handling > 0 ? $this->Currency->FormatValue($handling) : 0,
            'subtotal'      => $subtotal == $total ? '' : $this->Currency->Format($subtotal),
            'total'         => $this->Currency->Format($total),
            'cart_tax'      => $this->Order->getTax() > 0 ? $this->Currency->FormatValue($this->Order->getTax()) : 0,
        ) );
        $this->TPL->set_var(array(
            // commented for issue #66
            //'apply_gc'      => $by_gc > 0 ? $this->Currency->FormatValue($by_gc) : 0,
            'net_total'     => $this->Currency->Format($total - $by_gc),
            'discount_code' => $this->Order->getDiscountCode(),
            'dc_row_vis'    => $this->Order->getDiscountCode(),
            'dc_amt'        => $this->Currency->FormatValue($this->Order->getDiscountAmount() * -1),
            'dc_pct'        => $this->Order->getDiscountPct() . '%',
            'net_items'     => $this->Currency->Format($this->Order->getNetItems()),
            'pmt_status'    => $this->Order->getPaymentStatus(),
        ) );
        return $this;
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
        global $_SHOP_CONF, $_USER, $LANG_SHOP;

        $is_invoice = $this->type == 'order';
        $icon_tooltips = array();

        $T = new Template;
        $T->set_file('order', $this->tplname . '.thtml');

        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (\Shop\Workflow::getAll($this->Order) as $key => $wf) {
            $T->set_var('have_' . $wf->getView(), 'true');
        }

        $T->set_block('order', 'ItemRow', 'iRow');

        $ShopAddr = new Company;
        $Currency = $this->Order->getCurrency();
        $no_shipping = 1;   // no shipping unless physical item ordered
        $subtotal = 0;
        $item_qty = array();        // array to track quantity by base item ID
        $have_images = false;
        $total = 0;
        $tax_items = 0;
        $discount_items = 0;
        $shipping = 0;
        $handling = 0;
        foreach ($this->Items as $item) {
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

            if ($item->getDiscount() > 0) {
                $discount_items ++;
                $price_tooltip = sprintf(
                    $LANG_SHOP['reflects_disc'],
                    ($item->getDiscount() * 100)
                );
            } else {
                $price_tooltip = '';
            }
            $item_total = $item->getPrice() * $item->getQuantity();
            $subtotal += $item_total;
            if ($P->isTaxable()) {
                $tax_items++;       // count the taxable items for display
            }
            $T->set_var(array(
                'cart_item_id'  => $item->getID(),
                'fixed_q'       => $P->getFixedQuantity(),
                'item_id'       => htmlspecialchars($item->getProductID()),
                'item_dscp'     => htmlspecialchars($item->getDscp()),
                'item_price'    => $Currency->FormatValue($item->getPrice()),
                'item_quantity' => $item->getQuantity(),
                'item_total'    => $Currency->FormatValue($item_total),
                'is_admin'      => $this->isAdmin,
                'is_file'       => $item->canDownload(),
                'taxable'       => $this->Order->getTaxRate() > 0 ? $P->isTaxable() : 0,
                'tax_icon'      => $item->isTaxable() ? $LANG_SHOP['tax'][0] : '',
                'discount_icon' => 'D',
                'discount_tooltip' => $price_tooltip,
                'token'         => $item->getToken(),
                //'item_options'  => $P->getOptionDisplay($item),
                'item_options'  => $item->getOptionDisplay(),
                'sku'           => $item->getSKU(),
                'item_link'     => $P->withOrderItem($item->getID())->getLink(),
                'pi_url'        => SHOP_URL,
                'is_invoice'    => $is_invoice,
                'del_item_url'  => COM_buildUrl(SHOP_URL . "/cart.php?action=delete&id={$item->getID()}"),
            ) );
            if ($P->isPhysical()) {
                $no_shipping = 0;
            }
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        if ($discount_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['discount'][0] . ' = Includes discount';
        }
        if ($tax_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['taxable'][0] . ' = ' . $LANG_SHOP['taxable'];
        }
        $total = $this->Order->getTotal();     // also calls calcTax()
        $icon_tooltips = implode('<br />', $icon_tooltips);
        $by_gc = (float)$this->Order->getInfo('apply_gc');

        // isFinalView will be forced for PDF views, otherwise
        // use the order status to check.
        if ($this->isFinalView || $this->Order->isFinal()) {
            $T->set_var(array(
                'ship_method' => $this->Order->getShipperDscp(),
            ) );
            $shipping = $this->Order->getShipping();
        } else {
            // Call selectShipper() here to get the shipping amount
            // into the local variable.
            $shipper_select = $this->Order->selectShipper();
            $T->set_var(array(
                'ship_select'   => $this->isFinalView ? NULL : $shipper_select,
            ) );
        }
        $T->set_var(array(
            'billto_addr'   => $this->Order->getBillto()->toHTML(),
            'billto_phone'  => $this->Order->getBillto()->toText('phone'),
            'shipto_addr'   => $this->Order->getShipto()->toHTML(),
            'shipto_phone'  => $this->Order->getShipto()->toText('phone'),
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'shipper_id'    => $this->Order->getShipperID(),
            'total'         => $Currency->Format($total),
            'not_final'     => !$this->isFinalView,
            'order_date'    => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], false),
            'order_number'  => $this->Order->getOrderID(),
            'shipping'      => $Currency->FormatValue($shipping),
            'handling'      => $handling > 0 ? $Currency->FormatValue($handling) : 0,
            'subtotal'      => $subtotal == $total ? '' : $Currency->Format($subtotal),
            'order_instr'   => htmlspecialchars($this->Order->getInstructions()),
            'shop_name'     => $ShopAddr->toHTML('company'),
            'shop_addr'     => $ShopAddr->toHTML('address'),
            'shop_phone'    => $ShopAddr->getPhone(),
            'shop_email'    => $ShopAddr->getEmail(),
            'apply_gc'      => $by_gc > 0 ? $Currency->FormatValue($by_gc) : 0,
            'net_total'     => $Currency->Format($total - $by_gc),
            'cart_tax'      => $this->Order->getTax() > 0 ? $Currency->FormatValue($this->Order->getTax()) : 0,
            //'lang_tax_on_items'  => sprintf($LANG_SHOP['tax_on_x_items'], $this->tax_rate * 100, $this->tax_items),
            'lang_tax_on_items'  => $LANG_SHOP['sales_tax'],
            'status'        => $this->Order->getStatus(),
            'token'         => $this->Order->getToken(),
            'allow_gc'      => $_SHOP_CONF['gc_enabled']  && !COM_isAnonUser() ? true : false,
            //'next_step'     => $step + 1,
            'not_anon'      => !COM_isAnonUser(),
            //'ship_method'   => Shipper::getInstance($this->Order->getShipperId())->getName(),
            'total_prefix'  => $Currency->Pre(),
            'total_postfix' => $Currency->Post(),
            'total_num'     => $Currency->FormatValue($total),
            'cur_decimals'  => $Currency->Decimals(),
            'item_subtotal' => $Currency->FormatValue($subtotal),
            'return_url'    => SHOP_getUrl(),
            'is_invoice'    => $is_invoice,
            'icon_dscp'     => $icon_tooltips,
            'print_url'     => $this->Order->buildUrl('print'),
            'have_images'   => $is_invoice ? $have_images : false,
            'linkPackingList' => \Shop\Order::linkPackingList($order_id),
            'linkPrint'     => \Shop\Order::linkPrint($order_id, $this->Order->getToken()),
        ) );

        if ($this->isAdmin) {
            $T->set_var(array(
                'is_admin'      => true,
                'purch_name'    => COM_getDisplayName($this->Order->getUid()),
                'purch_uid'     => $this->Order->getUid(),
                'stat_update'   => OrderStatus::Selection($order_id, 1, $this->Order->getStatus()),
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

        $payer_email = $this->Order->getBuyerEmail();
        if ($payer_email == '' && !COM_isAnonUser()) {
            $payer_email = $_USER['email'];
        }
        $focus_fld = SESS_getVar('shop_focus_field');
        if ($focus_fld) {
            $T->set_var('focus_element', $focus_fld);
            SESS_unSet('shop_focus_field');
        }
        $T->set_var('payer_email', $payer_email);

        /*switch ($view) {
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
         */
        $status = $this->Order->getStatus();
        if ($this->Order->getPmtMethod() != '') {
            $gw = Gateway::getInstance($this->Order->getPmtMethod());
            if ($gw !== NULL) {
                $pmt_method = $gw->getDscp();
            } else {
                $pmt_method = $this->Order->getPmtMethod();
            }

            /*$T->set_var(array(
                'pmt_method' => $pmt_method,
                //'pmt_txn_id' => $this->pmt_txn_id,
                'ipn_det_url' => IPN::getDetailUrl($this->pmt_txn_id, 'txn_id'),
            ) );*/
        }

        $Payments = $this->Order->getPayments();
        if ($this->Order->getPmtMethod() != '') {
            $T->set_var(array(
                'pmt_method' => $this->Order->getPmtMethod(),
                'pmt_dscp' => $this->Order->getPmtDscp(),
            ) );
        }
        $T->set_block('order', 'Payments', 'pmtRow');
        foreach ($Payments as $Payment) {
            $T->set_var(array(
                'gw_name' => Gateway::getInstance($Payment->getGateway())->getDscp(),
                'ipn_det_url' => IPN::getDetailUrl($Payment->getRefID(), 'txn_id'),
                'pmt_txn_id' => $Payment->getRefID(),
                'pmt_amount' => $Currency->formatValue($Payment->getAmount()),
            ) );
            $T->parse('pmtRow', 'Payments', true);
        }

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
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
     * Display all the log entries for an order.
     *
     * @return  string      HTML for log display
     */
    public function _renderLog()
    {
        global $_SHOP_CONF, $_USER;

        // Instantiate a date objet to handle formatting of log timestamps
        $dt = new \Date('now', $_USER['tzid']);
        $log = $this->Order->getLog();
        $this->TPL->set_block('order', 'LogMessages', 'Log');
        foreach ($log as $L) {
            $dt->setTimestamp($L['ts']);
            $this->TPL->set_var(array(
                'log_username'  => $L['username'],
                'log_msg'       => $L['message'],
                'log_ts'        => $dt->format($_SHOP_CONF['datetime_fmt'], true),
                'log_ts_tip'    => $dt->format($_SHOP_CONF['datetime_fmt'], false),
            ) );
            $this->TPL->parse('Log', 'LogMessages', true);
        }
    }

}
