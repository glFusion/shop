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
class PackingList extends Shipment
{
    /** Shipment object.
     * @var object */
    protected $Shipment;


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $order_id   Optional order ID to read
     */
    public function __construct($order_id='')
    {
        global $_USER, $_SHOP_CONF;

        $this->isFinalView = true;
        parent::__construct($order_id);
        $this->tplname = 'packinglist';
    }


    /**
     * Create the display.
     *
     * @param   string  $title  Language key for form title
     * @return  string      HTML for display
     */
    public function Render($title='packinglist')
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file(array(
            'order'     => $this->tplname . '.thtml',
            'tracking'  => 'shipment_tracking_2.thtml',
        ) );
        $T->set_var(array(
            'page_title'    => $LANG_SHOP[$title],
            'shipment_id'    => $this->shipment_id,
        ) );
        $T->set_block('order', 'ItemRow', 'iRow');
        foreach ($this->Shipment->getItems() as $Item) {
            $OI = $Item->getOrderItem();
            $P = $OI->getProduct();
            $T->set_var(array(
                'oi_id'         => $OI->id,
                'item_dscp'     => htmlspecialchars($OI->description),
                'item_options'  => $OI->getOptionDisplay(),
                'shipped'       => $Item->quantity,
                'sku'           => $P->getSKU($OI),
            ) );
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        $T->set_block('order', 'trackingPackages', 'TP');
        foreach ($this->Shipment->Packages as $Pkg) {
            $T->set_var(array(
                'shipper_code'  => $Pkg->getShipper()->code,
                'shipper_name'  => $Pkg->shipper_info,
                'tracking_num'  => $Pkg->tracking_num,
                'pkg_id'        => $Pkg->pkg_id,
                'tracking_url'  => \Shop\Shipper::getInstance($Pkg->shipper_id)->getTrackingUrl($Pkg->tracking_num),
            ) );
            $T->parse('TP', 'trackingPackages', true);
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
            'ship_method'   => Shipper::getInstance($this->Order->shipper_id)->getName(),
            'tracking_form' => $T->parse('order', 'tracking'),
        ) );

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
     * Associate a Shipment object with this packing list.
     *
     * @param   object  Shipment object
     */
    public function setShipment($Shipment)
    {
        $this->Shipment = $Shipment;
    }

}

?>
