<?php
/**
 * Gateway to handle Net terms.
 * This offers Net Terms as a checkout method to authorized buyers, then
 * calls the configured gateway (e.g. "paypal") to process the invoice.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\terms;
use Shop\Models\OrderStatus;
use Shop\Gateway as GW;
use Shop\GatewayManager;
use Shop\FieldList;


/**
 *  Net Terms gateway class.
 *  @package shop
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = 'terms';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'Net Terms';


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        global $LANG_SHOP;

        // These are used by the parent constructor, set them first.
        $this->req_billto = true;
        // This gateway processes the order via AJAX and just returns to the shopping page.
        $this->gw_url = SHOP_URL . '/confirm.php';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->config = array(
            'global' => array(
                'gateway'   => '',
                'net_days'  => 30,
                'after_inv_status' => OrderStatus::PROCESSING,
                //'email_invoice' => 0,
            ),
        );
        $this->cfgFields= array(
            'global' => array(
                'gateway'   => 'select',
                'net_days'  => 'string',
                'after_inv_status' => 'select',
                //'email_invoice' => 'checkbox',
            ),
        );
        $this->services = array(
            'checkout'  => 1,
        );
        $this->do_redirect = false; // handled internally
        parent::__construct($A);
        $this->gw_desc = $this->getLang('gw_dscp');
    }


    /**
     *  Get the form variables for this checkout button.
     *  Used if the entire order is being paid by the gift card balance.
     *
     *  @param  object  $cart   Shopping cart
     *  @return string          HTML for input vars
     */
    public function gatewayVars($Cart)
    {
        global $_USER;

        // Add custom info for the internal ipn processor
        $pmt_gross = $Cart->getTotal();
        $gatewayVars = array(
            '<input type="hidden" name="order_id" value="' . $Cart->getOrderID() . '" />',
            '<input type="hidden" name="pmt_gross" value="' . $pmt_gross . '" />',
        );
        return implode("\n", $gatewayVars);
    }


    protected function getConfigOptions($name, $env='global')
    {
        global $LANG_SHOP;

        $opts = array();
        switch ($name) {
        case 'gateway':
            foreach (GatewayManager::getAll() as $gw) {
                if (!$gw->Supports($this->gw_name)) {
                    continue;
                }
                $opts[] = array(
                    'name' => $gw->getDscp(),
                    'value' => $gw->getName(),
                    'selected' => ($gw->getName() == $this->getConfig('gateway')),
                );
            }
            break;
        case 'after_inv_status':
            foreach (array(
                OrderStatus::INVOICED, OrderStatus::PROCESSING
            ) as $status) {
                $opts[] = array(
                    'name' => $LANG_SHOP['orderstatus'][$status],
                    'value' => $status,
                    'selected' => ($status == $this->getConfig($name)),
                );
            }
            break;
        }
        return $opts;
    }


    /**
     * Check if this gateway allows an order to be processed without an IPN msg.
     * The Check gateway does allow this as it just presents a remittance form.
     *
     * @return  boolean     True
     */
    public function allowNoIPN()
    {
        return true;
    }


    /**
     * Process the order confirmation. Called via AJAX.
     * Gets the actual payment gateway name from the config and
     * calls on it to create the invoice.
     *
     * @param   string  $order_id   Order ID
     * @return  boolean     True on success, False on error
     */
    public function processOrder($Order)
    {
        $gw_name = $this->getConfig('gateway');
        if (empty($gw_name)) {
            return false;           // unconfigured
        }
        $gw = parent::getInstance($gw_name);
        if ($gw && $gw->Supports($this->gw_name)) {
            $status = $gw->createInvoice($Order, $this);
        } else {
            $status = false;
        }
        if ($status) {      // if invoice creation was successful
            $Order->updateStatus($this->getConfig('after_inv_status'));
        }
        return $status;
    }


    /**
     * Confirm an order.
     * For Net Terms, this is the same as processOrder().
     *
     * @param   object  $Order  Order object
     * @return  boolean     True on success, False on error
     */
    public function confirmOrder($Order)
    {
        $status = $this->processOrder($Order);
        if ($status) {
            SHOP_setMsg($this->getLang('invoice_created'));
        } else {
            SHOP_setMsg($this->getLang('invoice_error'));
        }
        return SHOP_URL . '/index.php';
     }


    /**
     * Get the logo (display string) to show in the gateway radio buttons.
     *
     * @return  string      Description string
     */
    public function getLogo()
    {
        global $LANG_SHOP;

        return sprintf($LANG_SHOP['net_x_days'], $this->getConfig('net_days'));
    }


    /**
     * Override the gateway description to show the net days due.
     *
     * @return  string      Net X Days
     */
    public function getDscp()
    {
        return $this->getLogo();
    }


    /**
     * Get the Javascript to include in the checkout button.
     * The terms gateway doesn't use any, the order is finalized
     * by the return or webhook from the handling gateway.
     *
     * @return  string      Empty string
     */
    public function getCheckoutJS($cart)
    {
        return '';
    }


    /**
     * Get the instruction text to show at the top of the gateway config page.
     *
     * @return  string      Instructional text
     */
    protected function getInstructions()
    {
        return $this->getLang('config_instr');
    }


    /**
     * Check if the order can be processed.
     * For invoicing, orders can be processed upon invoice creation.
     *
     * @return  boolean     True to process the order, False to hold
     */
    public function okToProcess($Order)
    {
        return true;
    }


    /**
     * Create the "pay now" button for orders.
     * For invoices, get the link to the invoice payment screen if available.
     *
     * @return  string  HTML for payment button
     */
    public function payOnlineButton($Order)
    {
        global $LANG_SHOP;

        // Get the URL that was recorded during invoice creation or
        // webhook processing, if available.
        $url = $Order->getInfo('gw_pmt_url');
        if (!empty($url)) {
            $link = FieldList::buttonLink(array(
                'text' => $LANG_SHOP['buttons']['pay_now'],
                'style' => 'success',
                'target' => '_blank',
                'url' => $url,
            ) );
        } else {
            $link = '';
        }
        return $link;
    }


    /**
     * Check that a valid config has been set for the environment.
     *
     * @return  boolean     True if valid, False if not
     */
    public function hasValidConfig()
    {
        return !empty($this->getConfig('gateway'));
    }


    /**
     * Check if payments can be made "later", e.g. pay a pending order.
     * Invoiced orders support this.
     *
     * @return  boolean     True if pay-later is supported, False if not
     */
    public function canPayLater()
    {
        return true;
    }

}
