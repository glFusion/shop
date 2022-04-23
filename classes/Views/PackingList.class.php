<?php
/**
 * Class to create a packing list for an order.
 *
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Views;
use Shop\Shipper;
use Shop\Shipment;
use Shop\Template;
use Shop\Log;


/**
 * Shipment Packinglist class.
 * @package shop
 */
class PackingList extends Invoice
{
    /** Shipment object.
     * @var object */
    protected $Shipment = NULL;

    /** Shipment record ID.
     * @var integer */
    private $shipment_id = 0;


    /**
     * Set internal variables and read the existing order if an id is provided.
     */
    public function __construct()
    {
        $this->tplname = 'packinglist';
        //$this->Shipment = new Shipment($shipment_id);
        //$this->Order = $this->Shipment->getOrder();
    }


    /**
     * Set the local Shipment object.
     *
     * @param   string  $shipment_id    Shipment record ID
     * @return  object  $this
     */
    public function withShipmentId($shipment_id)
    {
        $this->shipment_id = (int)$shipment_id;
        $this->Shipment = new Shipment($shipment_id);
        $this->Order = $this->Shipment->getOrder();
        $this->Items = $this->Shipment->getItems();
        return $this;
    }


    /**
     * Create the output.
     * Either returns the HTML page or creates a PDF display,
     * depending on the current value of `output_type`.
     *
     * @return  string|void     HTML or nothing
     */
    public function Render()
    {
        if ($this->output_type == 'html') {
            return $this->createHTML();
        } else {
            self::printPDF(array($this->shipment_id));
        }
    }


    /**
     * Create the display.
     *
     * @param   string  $title  Language key for form title
     * @param   string  $type   Type of output, HTML or PDF
     * @return  string      HTML or PDF content
     */
    public function createHTML($title='packinglist', $type='html')
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $T = new Template;
        $T->set_file(array(
            'order'     => $type == 'pdf' ? 'packinglist.pdf.thtml' : 'packinglist.thtml',
            'tracking'  => 'shipment_tracking_2.thtml',
        ) );
        $T->set_var(array(
            'page_title'    => $LANG_SHOP[$title],
            'shipment_id'    => $this->shipment_id,
        ) );
        $T->set_block('order', 'ItemRow', 'iRow');
        foreach ($this->Items as $Item) {
            if ($this->Shipment === NULL) {
                // This is an order packing list
                $OI = $Item;
            } else {
                $OI = $Item->getOrderItem();
            }
            $P = $OI->getProduct();
            $T->set_var(array(
                'oi_id'         => $OI->getID(),
                'item_dscp'     => htmlspecialchars($OI->getDscp()),
                'item_options'  => $OI->getOptionDisplay(),
                'item_quantity' => $Item->getQuantity(),
                'sku'           => $OI->getSKU(),
            ) );
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        if ($this->Shipment !== NULL) {
            $T->set_block('order', 'trackingPackages', 'TP');
            foreach ($this->Shipment->Packages as $Pkg) {
                $T->set_var(array(
                    'shipper_code'  => $Pkg->getShipper()->getCode(),
                    'shipper_name'  => $Pkg->getShipperInfo(),
                    'tracking_num'  => $Pkg->getTrackingNumber(),
                    'pkg_id'        => $Pkg->getID(),
                    'tracking_url'  => Shipper::getInstance($Pkg->getShipperID())->getTrackingUrl($Pkg->getTrackingNumber()),
                ) );
                $T->parse('TP', 'trackingPackages', true);
            }
            $T->set_var(array(
                'tracking_info' => count($this->Shipment->getPackages()),
            ) );
        }

        $Shop = \Shop\Company::getInstance();
        $T->set_var(array(
            'shipment_id'   => $this->shipment_id,
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'order_date'    => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], false),
            'order_id'      => $this->Order->getOrderID(),
            'order_instr'   => htmlspecialchars($this->Order->getInstructions()),
            'shop_name'     => $Shop->getCompany(),
            'shop_addr'     => $Shop->toHTML('address'),
            'shop_phone'    => $Shop->getPhone(),
            'shop_email'    => $Shop->getEmail(),
            'billto_addr'   => $this->Order->getBillto()->toHTML(),
            'shipto_addr'   => $this->Order->getShipto()->toHTML(),
            'status'        => $this->Order->getStatus(),
            'ship_method'   => Shipper::getInstance($this->Order->getShipperID())->getName(),
            'tracking_form' => $T->parse('order', 'tracking'),
        ) );
        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
     * Create PDF output of one or more packing lists.
     *
     * @param   array   $ids    Array of order IDs
     * @param   string  $type   View type, 'pl' or 'order'
     * @return  boolean     True on success, False on error
     */
    public static function printPDF($ids, $type='pdfpl')
    {
        try {
            USES_lglib_class_html2pdf();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en');
            //$html2pdf->setModeDebug();
            $html2pdf->setDefaultFont('Arial');
        } catch(HTML2PDF_exception $e) {
            Log::write('shop_system', Log::ERROR, $e);
            return false;
        }

        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $shp_id) {
            $PL = new self;
            $PL->withShipmentID($shp_id);
            if ($PL->Shipment->isNew()) {
                continue;
            }
            $content = $PL->createHTML('packinglist', 'pdf');
            try {
                $html2pdf->writeHTML($content);
            } catch(HTML2PDF_exception $e) {
                Log::write('shop_system', Log::ERROR, $e);
                return false;
            }
        }
        $html2pdf->Output($type . 'list.pdf', 'I');
        return true;
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

