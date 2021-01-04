<?php
/**
 * Class to present an view of an invoice (finalized order).
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
namespace Shop\Views;
use Shop\Currency;
use Shop\Workflow;
use Shop\Shipment;
use Shop\Shipper;
use Shop\Template;
use Shop\Gateway;
use Shop\Company;
use Shop\Payment;
use Shop\OrderStatus;
use Shop\ShipmentPackage;
use Shop\Models\OrderState;


/**
 * Invoice view class.
 * @package shop
 */
class Invoice extends OrderBaseView
{
    use \Shop\Traits\PDF;

    /** Order record ID.
     * @var string */
    protected $order_ids = array();

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


    /**
     * Set internal variables and read the existing order if an id is provided.
     */
    public function __construct()
    {
        $this->tplname = 'order';
        $this->is_invoice = true;
        $this->TPL = new Template;
        $this->TPL->set_file('order', $this->tplname . '.thtml');
    }


    /**
     * Set the order token to allow anonymous views.
     *
     * @param   string  $token  Order token string
     * @return  object  $this
     */
    public function withToken($token)
    {
        $this->token = $token;
        return $this;
    }


    /**
     * Set the administrator-view flag.
     * Enables additional information to be shown.
     *
     * @param   boolean $flag   True for admin view
     * @return  object  $this
     */
    public function setAdmin($flag)
    {
        $this->isAdmin = $flag ? true : false;
        return $this;
    }


    /**
     * Specify the type of output - html for normal display or pdf to print.
     *
     * @param   string  $out_type   Type of output
     * @return  object  $this
     */
    public function withOutput($out_type)
    {
        $this->output_type = $out_type;
        if ($out_type == 'pdf') {
            $this->tplname .= '.pdf';
            $this->withShopInfo(true);
        }
        $this->TPL->set_file('order', $this->tplname . '.thtml');
        return $this;
    }


    /**
     * View the invoice as a packing list.
     * Suppresses prices and other charges.
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
     * View the invoice normally.
     * Includes prices and other charges.
     *
     * @return  object  $this
     */
    public function asInvoice()
    {
        $this->type = 'order';
        $this->is_invoice = true;
        return $this;
    }


    public function withType($type)
    {
        $this->type = $type;
        return $this;
    }


    /**
     * Set flag to include shop address and phone.
     *
     * @param   boolean $flag   True to show shop info in header
     * @return  object  $this
     */
    public function withShopInfo($flag = true)
    {
        $this->with_shop_info = $flag;
        return $this;
    }


    /**
     * Set the order ID to show.
     *
     * @param   array|string  $order_id   Order record ID
     * @return  object  $this
     */
    public function withOrderIds($order_ids)
    {
        if (!is_array($order_ids)) {
            $this->order_ids = array($order_ids);
        } else {
            $this->order_ids = $order_ids;
        }
        return $this;
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
     * Render the output.
     *
     * @return  mixed   HTML or PDF output
     */
    public function Render()
    {
        if (empty($this->order_ids)) {
            $this->order_ids = array($this->order_id);
        }
        if ($this->output_type == 'pdf') {
            //$this->tplname .= '.pdf';
            $this->initPDF();
        }
        foreach ($this->order_ids as $order_id) {
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
     * Create the HTML output for the invoice.
     *
     * @return string      HTML for order view
     */
    public function createHTML()
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP;

        $this->_renderCommon();
        $this->_renderAddresses();
        $this->_renderItems();
        if ($this->with_shop_info) {
            $this->_renderShopAddress();
        }

        $status = $this->Order->getStatus();
        if ($this->isAdmin) {
            $this->TPL->set_block('order', 'StatusSelect', 'Sel');
            foreach (OrderStatus::getAll() as $key => $data) {
                if (!$data->enabled) continue;
                $this->TPL->set_var(array(
                    'selected' => $key == $status ? 'selected="selected"' : '',
                    'stat_key' => $key,
                    'stat_descr' => OrderStatus::getDscp($key),
                ) );
                $this->TPL->parse('Sel', 'StatusSelect', true);
            }
            $this->TPL->set_var(array(
                'is_admin'  => true,
                'itemsToShip'   => $this->Order->itemsToShip(),
                'oldstatus' => $this->Order->getStatus(),
            ) );
        } else {
            $this->TPL->set_var('status', $status);
        }

        // Show the log of payments and status changes
        $this->_renderLog();

        if (
            //$status == OrderState::PENDING &&
            !$this->Order->isPaid() &&
            !empty($this->Order->getPmtMethod())
        ) {
            $gw = \Shop\Gateway::getInstance($this->Order->getPmtMethod());
            if ($gw->canPayOnline()) {
                $this->TPL->set_var(
                    'pmt_btn',
                    $gw->payOnlineButton($this->Order, $LANG_SHOP['buttons']['pay_now'])
                );
            }
        }
        $this->TPL->set_var(array(
            'payer_email'   => $this->Order->getBuyerEmail(),
            'invoice_number'  => $this->Order->getInvoiceNumber(),
            'order_instr'   => htmlspecialchars($this->Order->getInstructions()),
            'shipment_block' => $this->getShipmentBlock(),
            'shipper_id' => $this->Order->getShipperID(),
            'ship_method' => $this->Order->getShipperDscp(),
        ) );
        if ($this->Order->getPmtMethod() != '') {
            $gw = Gateway::getInstance($this->Order->getPmtMethod());
            if ($gw !== NULL) {
                $pmt_method = $gw->getDscp();
            } else {
                $pmt_method = $this->Order->getPmtMethod();
            }
        }

        $Payments = $this->Order->getPayments();
        if ($this->Order->getPmtMethod() != '') {
            $this->TPL->set_var(array(
                'pmt_method' => $this->Order->getPmtMethod(),
                'pmt_dscp' => $this->Order->getPmtDscp(),
            ) );
        }
        $this->TPL->set_var('num_payments', count($Payments));
        $this->TPL->set_block('order', 'Payments', 'pmtRow');
        foreach ($Payments as $Payment) {
            $this->TPL->set_var(array(
                'gw_name' => Gateway::getInstance($Payment->getGateway())->getDscp(),
                'pmt_det_url' => Payment::getDetailUrl($Payment->getPmtID()),
                'pmt_txn_id' => $Payment->getRefID(),
                'pmt_amount' => $this->Currency->format($Payment->getAmount()),
                'pmt_date' => $Payment->getDt()->toMySQL(true),
            ) );
            $this->TPL->parse('pmtRow', 'Payments', true);
        }
        $this->TPL->set_var(array(
            'purch_name'    => COM_getDisplayName($this->Order->getUid()),
            'purch_uid'     => $this->Order->getUid(),
            //'stat_update'   => OrderStatus::Selection($this->order_id, 1, $this->Order->getStatus()),
            'amt_paid_fmt' => $this->Currency->Format($this->Order->getAmountPaid()),
        ) );
        if ($this->Order->getAmountPaid() > 0) {
            $paid = $this->Order->getAmountPaid();
            $this->TPL->set_var(array(
                'amt_paid_num' => $this->Currency->formatValue($paid),
                'due_amount' => $this->Currency->formatValue($this->Order->getBalanceDue()),
            ) );
        }
        $this->TPL->parse('output', 'order');
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
