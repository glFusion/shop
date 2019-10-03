<?php
/**
 * Class to present the shipment form for an order.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Views;
use Shop\Shipper;

/**
 * Order class.
 * @package shop
 */
class Shipment extends Order
{
    /** Shipment record ID.
     * @var integer */
    private $shipment_id = 0;


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $order_id   Optional order ID to read
     */
    public function __construct($order_id='')
    {
        global $_USER, $_SHOP_CONF;

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
     * @return  string      HTML for display
     */
    public function Render()
    {
        global $_SHOP_CONF;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file(array(
            'order'     => $this->tplname . '.thtml',
            'tracking'  => 'shipment_tracking_1.thtml',
        ) );
        $T->set_block('order', 'ItemRow', 'iRow');
        foreach ($this->Order->getItems() as $item) {
            $P = $item->getProduct();
            $shipped = \Shop\ShipmentItem::getItemsShipped($item->id);
            $toship = max($item->quantity - $shipped, 0);
            $T->set_var(array(
                'oi_id'         => $item->id,
                'fixed_q'       => $P->getFixedQuantity(),
                'item_id'       => htmlspecialchars($item->product_id),
                'item_dscp'     => htmlspecialchars($item->description),
                'ordered'       => $item->quantity,
                'shipped'       => $shipped,
                'toship'        => $toship,
                'is_admin'      => $this->isAdmin,
                'is_file'       => $item->canDownload(),
                'taxable'       => $this->tax_rate > 0 ? $P->taxable : 0,
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

        $T->set_var(array(
            'shipment_id'    => $this->shipment_id,
            'shipper_select' => Shipper::optionList(),
        ) );
        if ($this->shipment_id > 0) {
            $T->set_block('order', 'trackingPackages', 'TP');
            $Shp = new \Shop\Shipment($this->shipment_id);
            foreach ($Shp->Packages as $Pkg) {
                $T->set_var(array(
                    'shipper_code'  => $Pkg->getShipper()->code,
                    'shipper_name'  => $Pkg->shipper_info,
                    'tracking_num'  => $Pkg->tracking_num,
                    'pkg_id'        => $Pkg->pkg_id,
                    'tracking_url'  => \Shop\Shipper::getInstance($Pkg->shipper_id)->getTrackingUrl($Pkg->tracking_num),
                ) );
                $T->parse('TP', 'trackingPackages', true);
            }
        }

        $T->set_var(array(
            'shipment_id'        => $this->shipment_id,
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'order_date'    => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], false),
            'order_id'      => $this->Order->order_id,
            'order_instr'   => htmlspecialchars($this->instructions),
            'shop_name'     => $_SHOP_CONF['shop_name'],
            'shop_addr'     => $_SHOP_CONF['shop_addr'],
            'shop_phone'    => $_SHOP_CONF['shop_phone'],
            'billto_addr'   => $this->Order->getBillto()->toHTML(),
            'shipto_addr'   => $this->Order->getShipto()->toHTML(),
            'status'        => $this->Order->status,
            'next_step'     => $step + 1,
            'not_anon'      => !COM_isAnonUser(),
            'ship_method'   => Shipper::getInstance($this->Order->shipper_id)->getName(),
            'return_url'    => SHOP_getUrl(),
            'tracking_form' => $T->parse('order', 'tracking'),
        ) );

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }

}

?>
