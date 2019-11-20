<?php
/**
 * Gateway to handle Net terms.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways;

use Shop\Cart;
use Shop\Coupon;
use Shop\Currency;

/**
 *  Coupon gateway class, just to provide checkout buttons for coupons
 */
class terms extends \Shop\Gateway
{
    /**
     * Number of days for net terms, default = "Net 30"
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
        $this->gw_url = SHOP_URL . '/ipn/terms.php';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->config = array(
            'gateway'   => '',
            'net_days'  => 30,
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
    protected function _Supports($btn_type)
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
     * No config fields for the Terms gateway.
     *
     * @return  array   Empty array
     */
    public function getConfigFields()
    {
        $fields = array();
        foreach($this->config as $name=>$value) {
            $other_label = '';
            switch ($name) {
            case 'gateway':
                $field = '<select name="' . $name . '">' . LB;
                foreach (self::getAll() as $gw) {
                    if ($gw->Supports($this->gw_name)) {
                        $field .= '<option value="' . $gw->gw_name . '">' .
                            $gw->gw_desc . '</option>' . LB;
                    }
                }
                $field .= '</select>' . LB;
                break;
            default:
                $field = '<input type="text" name="' . $name . '" value="' .
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

}

?>
