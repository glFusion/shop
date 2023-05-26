<?php
/**
 * Class to present the packing list for an order.
 * This assumes that there is no shipment information entered and the entire
 * order is being shipped together.
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
use Shop\Order;
use Shop\Template;
use Shop\Models\Request;


/**
 * Shipment Packinglist class.
 * @package shop
 */
class OrderPL extends Shipment
{
    /** Shipment object.
     * @var object */
    protected $Order;


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $order_id   Optional order ID to read
     */
    public function __construct($order_id='')
    {
        $this->Order = Order::getInstance($order_id);
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

        $T = new Template;
        $T->set_file(array(
            'order'     => 'packinglist.thtml',
            'tracking'  => 'shipment_tracking_2.thtml',
        ) );
        $T->set_var(array(
            'page_title'    => $LANG_SHOP[$title],
            'shipment_id'    => $this->shipment_id,
        ) );
        $T->set_block('order', 'ItemRow', 'iRow');
        foreach ($this->Order->getItems() as $OI) {
            $P = $OI->getProduct();
            $T->set_var(array(
                'oi_id'         => $OI->id,
                'item_dscp'     => htmlspecialchars($OI->description),
                'item_options'  => $OI->getOptionDisplay(),
                'item_quantity' => $OI->quantity,
                'sku'           => $OI->getSKU(),
            ) );
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        $T->set_var(array(
            'shipment_id'   => $this->shipment_id,
            'pi_url'        => Config::get('url'),
            'account_url'   => Config::get('url') . '/account.php',
            'pi_admin_url'  => Config::get('admin_url'),
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
            'tracking_form' => false,
        ) );

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
     * Determine if the current user can view this packing list.
     *
     * @return  boolean     True if access is allowed, False if not.
     */
    public function canView()
    {
        return $this->Order->canView();
    }

}

?>
