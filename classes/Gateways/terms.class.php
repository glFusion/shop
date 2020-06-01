<?php
/**
 * Gateway to handle Net terms.
 * This offers Net Terms as a checkout method to authorized buyers, then
 * calls the configured gateway (e.g. "paypal") to process the invoice.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways;
use Shop\Gateway;


/**
 *  Net Terms gateway class.
 *  @package shop
 */
class terms extends \Shop\Gateway
{
    /** Number of days for net terms, default = "Net 30"
     * @var integer */
    private $net_days = 30;


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     */
    public function __construct()
    {
        global $LANG_SHOP;

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'terms';
        $this->gw_desc = 'Net Terms';
        $this->req_billto = true;
        // This gateway processes the order via AJAX and just returns to the shopping page.
        $this->gw_url = SHOP_URL . '/index.php?msg=09&plugin=shop';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->config = array(
            'global' => array(
                'gateway'   => '',
                'net_days'  => 30,
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
        parent::__construct();
    }


    /**
     * Check if the current gateway supports a specific button type.
     * This gateway always supports "checkout" if it's enabled.
     *
     * @uses    self::Supports()
     * @param   string  $btn_type   Button type to check
     * @return  boolean             True if the button is supported
     */
    protected function X_Supports($btn_type)
    {
        $arr_parms = array(
            'enabled' => $this->enabled,
            'services' => array('checkout'),
        );
        return self::Supports($btn_type, $arr_parms);
    }


    /**
     *  Get the form variables for this checkout button.
     *  Used if the entire order is being paid by the gift card balance.
     *
     *  @param  object  $cart   Shopping cart
     *  @return string          HTML for input vars
     */
    public function gatewayVars($cart)
    {
        return '';      // TODO not needed? Probably call Gateway::createInvoice() here.
        global $_USER;

        // Add custom info for the internal ipn processor
        $cust = $cart->custom_info;
        $cust['uid'] = $_USER['uid'];
        $cust['transtype'] = 'coupon';
        $cust['cart_id'] = $cart->CartID();
        $cust['token'] = $cart->getToken();

        $pmt_gross = $cart->getTotal();
        if (isset($cust['by_gc'])) {
            $pmt_gross -= (float)$cust['by_gc'];
        }
        $gatewayVars = array(
            '<input type="hidden" name="cart_id" value="' . $cart->CartID() . '" />',
            '<input type="hidden" name="custom" value=\'' . htmlspecialchars(@serialize($cust)) . '\' />',
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
     * Prepare to save the configuraiton.
     * This copies the new config values into our local variables, then
     * calls the parent function to save to the database.
     *
     * @param   array   $A      Array of name=>value pairs (e.g. $_POST)
     */
    public function SaveConfig($A = NULL)
    {
        if (is_array($A)) {
            foreach ($this->config as $name=>$value) {
                switch ($name) {
                default:
                    $this->config[$name] = $A[$name];
                    break;
                }
            }
        }
        return parent::SaveConfig($A);
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
    public function processOrder($order_id)
    {
        $gw_name = $this->getConfig('gateway');
        if (empty($gw_name)) {
            return false;           // unconfigured
        }
        return Gateway::getInstance($gw_name)->createInvoice($order_id);
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

}

?>
