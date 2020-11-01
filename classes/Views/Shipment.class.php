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
        $this->tplname = 'shipment';

        parent::__construct($order_id);
    }


    /**
     * Set the shipment ID.
     *
     * @param   integer $shipment_id     Shipment record ID
     */
    public function setShipmentID($shipment_id)
    {
        $this->shipment_id = (int)$shipment_id;
    }


    /**
     * Create the display.
     *
     * @param   string  $title  Language key for form title
     * @return  string      HTML for display
     */
    public function Render($title='shiporder')
    {
        global $_SHOP_CONF, $LANG_SHOP;

        // Safety valve if order is invalid
        if ($this->Order === NULL) {
            COM_setMsg($LANG_SHOP['item_not_found']);
            return '';
        }

        $oi_shipped = array();
        if ($this->shipment_id > 0) {
            $Shp = new \Shop\Shipment($this->shipment_id);
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
            'page_title'    => $LANG_SHOP[$title],
            'shipment_id'    => $this->shipment_id,
            'shipper_select' => Shipper::optionList(0, true),
        ) );
        $T->set_block('order', 'ItemRow', 'iRow');
        foreach ($this->Order->getItems() as $Item) {
            $P = $Item->getProduct();
            // If this is not a physical product, don't show the qty field,
            // show the product type text instead.
            if (!$P->isPhysical()) {
                $T->set_var(array(
                    'can_ship' => false,
                    'toship_text' => $LANG_SHOP['prod_types'][$P->getProductType()],
                ) );
            } else {
                $shipped = \Shop\ShipmentItem::getItemsShipped($Item->getID());
                if ($this->shipment_id > 0) {
                    // existing, adjust prev shipped down and use this ship qty
                    if (isset($oi_shipped[$Item->getID()])) {
                        // some of the item was shipped on this shipment
                        $toship = $Shp->getItems()[$oi_shipped[$Item->getID()]]->getQuantity();
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
                'oi_id'         => $Item->getID(),
                'item_id'       => htmlspecialchars($Item->getProductID()),
                'item_dscp'     => htmlspecialchars($Item->getDscp()),
                'ordered'       => $Item->getQuantity(),
                'item_options'  => $Item->getOptionDisplay(),
                'sku'           => $P->getSKU($Item),
                'pi_url'        => SHOP_URL,
            ) );
            if ($P->isPhysical()) {
                $this->no_shipping = 0;
            }
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        if ($this->shipment_id > 0) {
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
            'shipment_id'   => $this->shipment_id,
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
