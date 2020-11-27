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
use Shop\IPN;
use Shop\OrderStatus;
use Shop\ShipmentPackage;


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

    /** Flag to indicate this is an admin.
     * @var boolean */
    protected $isAdmin = false;

    /** View type, e.g. order or packing list.
     * @var string */
    protected $output_type = 'html';


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


    public function withToken($token)
    {
        $this->token = $token;
        return $this;
    }


    public function setAdmin($flag)
    {
        $this->isAdmin = $flag ? true : false;
        return $this;
    }


    public function withOutput($out_type)
    {
        $this->output_type = $out_type;
        $this->tplname .= '.pdf';
        $this->TPL->set_file('order', $this->tplname . '.thtml');
        return $this;
    }


    public function asPackingList()
    {
        $this->type = 'packinglist';
        $this->is_invoice = false;
        return $this;
    }

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

        $this->_renderCommon();
        $this->_renderAddresses();
        $this->_renderItems();

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
            ) );
            $this->_renderLog();
        } else {
            $this->TPL->set_var('status', $status);
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
        $this->TPL->set_block('order', 'Payments', 'pmtRow');
        foreach ($Payments as $Payment) {
            $this->TPL->set_var(array(
                'gw_name' => Gateway::getInstance($Payment->getGateway())->getDscp(),
                'ipn_det_url' => $Payment->getDetailUrl(),
                'pmt_txn_id' => $Payment->getRefID(),
                'pmt_amount' => $this->Currency->formatValue($Payment->getAmount()),
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
                'due_amount' => $this->Currency->formatValue($this->Order->getTotal() - $paid),
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

?>
