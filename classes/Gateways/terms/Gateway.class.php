<?php
/**
 * Gateway to handle Net terms.
 * This offers Net Terms as a checkout method to authorized buyers, then
 * calls the configured gateway (e.g. "paypal") to process the invoice.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\terms;
use Shop\Models\OrderState;
use Shop\Gateway as GW;


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

    /** Flag this gateway as bundled with the Shop plugin.
     * @var integer */
    protected $bundled = 1;

    /** Number of days for net terms, default = "Net 30"
     * @var integer */
    private $net_days = 30;


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
                'after_inv_status' => OrderState::PROCESSING,
            ),
        );
        $this->cfgFields= array(
            'global' => array(
                'gateway'   => 'select',
                'net_days'  => 'string',
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


    /**
     * Get all the configuration fields specifiec to this gateway.
     *
     * @param   string  $env    Environment (test, prod or global)
     * @return  array   Array of fields (name=>field_info)
     */
    public function getConfigFields($env='global')
    {
        global $LANG_SHOP;

        $fields = array();
        foreach($this->config[$env] as $name=>$value) {
            $other_label = '';
            switch ($name) {
            case 'gateway':
                $field = '<select name="' . $name . '[global]">' . LB;
                if ($this->gw_name == '') {
                    $sel = 'selected="selected"';
                } else {
                    $sel = '';
                }
                $field .= '<option value=""' . $sel . '>-- ' .
                    $LANG_SHOP['none'] . ' --</option>' . LB;
                foreach (self::getAll() as $gw) {
                    if ($gw->gw_name == $this->getConfig('gateway')) {
                        $sel = 'selected="selected"';
                    } else {
                        $sel = '';
                    }
                    if ($gw->Supports($this->gw_name)) {
                        $field .= '<option value="' . $gw->gw_name . '" ' . $sel . '>' .
                            $gw->gw_desc . '</option>' . LB;
                    }
                }
                $field .= '</select>' . LB;
                break;
            case 'after_inv_status':
                $field = '<select name="' . $name . '[global]">' . LB;
                foreach (array(
                    OrderState::INVOICED, OrderState::PROCESSING
                ) as $status) {
                    $sel = $status == $this->getConfig($name) ? 'selected="selected"' : '';
                    $field .= '<option value="' . $status . '" ' . $sel . '>' .
                        $LANG_SHOP['orderstatus'][$status] . '</option>' . LB;
                }
                $field .= '</select>' . LB;
                break;

            default:
                $field = '<input type="text" name="' . $name . '[global]" value="' .
                    $value . '" size="60" />';
                break;
            }
            $fields[$name] = array(
                'param_field'   => $field,
                'other_label'   => $other_label,
                'doc_url'       => '',
            );
        }
        return $fields;
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
            $status = parent::getInstance($gw_name)->createInvoice($Order, $this);
        } else {
            $status = false;
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
            COM_setMsg($this->getLang('invoice_created'));
        } else {
            COM_setMsg($this->getLang('invoice_error'));
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

        return sprintf($LANG_SHOP['net_x_days'], $this->net_days);
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
            $link = COM_createLink(
                '<button class="uk-button uk-button-success">' .
                    $LANG_SHOP['buttons']['pay_now'] .
                    '</button>',
                $url,
                array(
                    'target' => '_blank',
                )
            );
        } else {
            $link = '';
        }
        return $link;
    }

}
