<?php
/**
 * Class to present the shipment form for an order.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.3
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Views;
use Shop\Shipper;
use Shop\Template;


/**
 * Order class.
 * @package shop
 */
class Shipment extends Order
{
    /** Shipment record ID.
     * @var integer */
    protected $shipment_id = 0;


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $order_id   Optional order ID to read
     */
    public function __construct($order_id = '')
    {
        $this->isFinalView = true;

        parent::__construct($order_id);
        $this->tplname = 'packinglist';
    }


    /**
     * Set the shipment ID.
     *
     * @param   integer $shipment_id     Shipment record ID
     */
    public function withShipmentID($shipment_id)
    {
        if (!is_array($shipment_id)) {
            $this->shipment_ids = array($shipment_id);
        } else {
            $this->shipment_ids = $shipment_id;
        }
        return $this;
    }


    public function Edit()
    {
        $this->tplname = 'shipment';
        return $this->Render();
    }

    public function Render()
    {
        if ($this->output_type == 'pdf') {
            $this->tplname .= '.pdf';
            $this->initPDF();
        }
        foreach ($this->shipment_ids as $shipment_id) {
            $this->Shipment = new \Shop\Shipment($shipment_id);
            $this->Order = \Shop\Order::getInstance($this->Shipment->getOrderId());
            if (!$this->Order->canView($this->token)) {
                continue;
            }
            if ($this->output_type == 'html') {
                // HTML is only available for single orders, so return here.
                $output = $this->createHTML($shipment_id);
                return $output;
            } elseif ($this->output_type == 'pdf') {
                $output = $this->createHTML($shipment_id);
                $this->writePDF($output);
            }
        }
        if ($this->output_type == 'pdf') {
            $this->finishPDF();
        }
    }


    /**
     * Create the display.
     *
     * @return  string      HTML for display
     */
    public function createHTML($shipment_id)
    {
        global $_SHOP_CONF, $LANG_SHOP;

        // Safety valve if order is invalid
        if ($this->Order === NULL) {
            COM_setMsg($LANG_SHOP['item_not_found']);
            return '';
        }

        $oi_shipped = array();
        if ($shipment_id > 0) {
            $Shp = new \Shop\Shipment($shipment_id);
            foreach ($Shp->getItems() as $key=>$data) {
                $oi_shipped[$data->getOrderitemID()] = $key;
            }
        }

        $T = new Template;
        $T->set_file(array(
            'order'     => $this->tplname . '.thtml',
            'tracking'  => 'shipment_tracking_1.thtml',
        ) );
        $T->set_var(array(
            'page_title'    => $LANG_SHOP['shiporder'],
            'shipment_id'    => $shipment_id,
            'shipper_select' => Shipper::optionList(0, true),
        ) );
        $T->set_block('order', 'ItemRow', 'iRow');
        foreach ($this->Shipment->getItems() as $Item) {
            $OI = $Item->getOrderItem();
            $P = $OI->getProduct();
            // If this is not a physical product, don't show the qty field,
            // show the product type text instead.
            if (!$P->isPhysical()) {
                $T->set_var(array(
                    'can_ship' => false,
                    'toship_text' => $LANG_SHOP['prod_types'][$P->getProductType()],
                ) );
            } else {
                $shipped = \Shop\ShipmentItem::getItemsShipped($OI->getID());
                if ($shipment_id > 0) {
                    // existing, adjust prev shipped down and use this ship qty
                    if (isset($oi_shipped[$OI->getID()])) {
                        // some of the item was shipped on this shipment
                        $toship = $Shp->getItems()[$oi_shipped[$OI->getID()]]->getQuantity();
                    } else {
                        // Item was not shipped on this order.
                        $toship = 0;
                    }
                    $newshipment = false;
                } else {
                    $toship = $Item->getQuantity() - $shipped;
                    $newshipment = true;
                }
                $T->set_var(array(
                    'can_ship'  => true,
                    'shipped'   => $shipped,
                    'toship'    => $toship,
                    'newship'   => $newshipment,
                ) );
            }

            $T->set_var(array(
                'oi_id'         => $OI->getID(),
                'item_id'       => htmlspecialchars($OI->getProductID()),
                'item_dscp'     => htmlspecialchars($OI->getDscp()),
                'item_quantity' => $OI->getQuantity(),
                'shipped'       => $Item->getQuantity(),
                'item_options'  => $OI->getOptionDisplay(),
                'sku'           => $P->getSKU($OI),
                'pi_url'        => SHOP_URL,
            ) );
            if ($P->isPhysical()) {
                $this->no_shipping = 0;
            }
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        if ($shipment_id > 0) {
            $T->set_block('order', 'trackingPackages', 'TP');
            foreach ($Shp->Packages as $Pkg) {
                $T->set_var(array(
                    'shipper_code'  => $Pkg->getShipper()->getCode(),
                    'shipper_name'  => $Pkg->getShipperInfo(),
                    'tracking_num'  => $Pkg->getTrackingNumber(),
                    'pkg_id'        => $Pkg->getID(),
                    'tracking_url'  => \Shop\Shipper::getInstance($Pkg->getShipperID())->getTrackingUrl($Pkg->getTrackingNumber()),
                ) );
                $T->parse('TP', 'trackingPackages', true);
            }
        }

        $T->set_var(array(
            'shipment_id'   => $shipment_id,
            'pi_url'        => SHOP_URL,
            'order_date'    => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], false),
            'order_id'      => $this->Order->getOrderID(),
            'order_instr'   => htmlspecialchars($this->Order->getInstructions()),
            'billto_addr'   => $this->Order->getBillto()->toHTML(),
            'shipto_addr'   => $this->Order->getShipto()->toHTML(),
            'ship_method'   => Shipper::getInstance($this->Order->getShipperID())->getName(),
            'tracking_form' => $T->parse('order', 'tracking'),
        ) );

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }

}

?>
