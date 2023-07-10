<?php
/**
 * Class to manage payment by gift card.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\_internal;
use Shop\Template;
use Shop\Product;
use Shop\Models\DataArray;


/**
 * Internal gateway class, just to support zero-balance orders.
 */
class Gateway extends \Shop\Gateway
{
    /** Gateway ID.
     * @var string */
    protected $gw_name = '_internal';

    /** Gateway provide. Company name, etc.
     * @var string */
    protected $gw_provider = 'Internal';

    /** Gateway service description.
     * @var string */
    protected $gw_desc = 'Internal Payment Gateway';


    /**
     * Constructor.
     * Set gateway-specific items and call the parent constructor.
     *
     * @param   array   $A      Array of fields from the DB
     */
    public function __construct($A=array())
    {
        // This gateway can service all button type by default
        $this->services = array(
            'buy_now'   => 1,
            'donation'  => 1,
            'pay_now'   => 1,
            'subscribe' => 1,
            'checkout'  => 1,
            'external'  => 1,
            'terms'     => 0,
        );
        parent::__construct($A);
        $this->enabled = 0;     // force disabled to hide from list
    }


    /**
     * Get the command value and template name for the requested button type.
     *
     * @param   string  $btn_type   Type of button being created
     * @return  array       Array ('cmd'=>command, 'tpl'=>template name
     */
    private function gwButtonType($btn_type='') : array
    {
        switch ($btn_type) {
        case 'donation':
            $cmd = '_donations';
            $tpl = 'donation';
            break;
        case 'buy_now':
        default:
            $cmd = '_xclick';
            $tpl = 'buy_now';
            break;
        }
        return array('cmd' => $cmd, 'tpl' => $tpl);
    }


    /**
     * Get a buy-now button for a catalog product.
     * Checks the button table to see if a button exists, and if not
     * a new button will be created.
     *
     * @uses    gwButtonType()
     * @uses    Gateway::_ReadButton()
     * @uses    Gateway::_SaveButton()
     * @param   object  $P      Product Item object
     * @param   DataArray   $vars   Optional overrides (price, return urls, etc.)
     * @return  string          HTML code for the button.
     */
    public function ProductButton($P, $Props=NULL) : string
    {
        global $LANG_SHOP;

        return '';      // TODO

        // Make sure we want to create a buy_now-type button
        $btn_type = $P->getBtnType();
        if (empty($btn_type)) return '';
        if (!$Props) {
            $Props= new DataArray;
        }

        $btn_info = self::gwButtonType($btn_type);
        $this->AddCustom('transtype', $btn_type);
        $gateway_vars = '';

        if (empty($gateway_vars)) {
            $vars = array(
                'cmd' => $btn_info['cmd'],
                'item_number' => htmlspecialchars($P->getID()),
                'item_name' => htmlspecialchars($P->getShortDscp()),
                'currency_code' => $this->currency_code,
                'custom' => $this->custom->encode(),
                'return' => $Props->getString('return_url', SHOP_URL . '/index.php?thanks=shop'),
                'cancel_return' => $Props->getString('cancel_url', SHOP_URL . '/index.php'),
                'amount' => $Props->getFloat('amount', $P->getPrice()),
                'notify_url' => $this->ipn_url,
                'ipn_type' => 'buy_now',      // Force the IPN type
            );
            // Get the allowed buy-now quantity. If not defined, set
            // undefined_quantity.
            $qty = $P->getFixedQuantity();
            if ($qty < 1) {
                $vars['undefined_quantity'] = '1';
            } else {
                $vars['quantity'] = $qty;
            }

            if ($P->getWeight() > 0) {
                $vars['weight'] = $P->getWeight();
            } else {
                $vars['no_shipping'] = '1';
            }

            switch ($P->getShippingType()) {
            case 0:
                $vars['no_shipping'] = '1';
                break;
            case 2:
                $vars['shipping'] = $P->getShipping();
                $vars['no_shipping'] = '1';
                break;
            case 1:
                $vars['no_shipping'] = '2';
                break;
            }

            /*if ($P->taxable) {
                $vars['tax_rate'] = sprintf("%0.4f", SHOP_getTaxRate() * 100);
            }*/

            // Buy-now product button, set default billing/shipping addresses
            $U = self::Customer();
            $Shipto = $U->getDefaultAddress('shipto');
            if (!empty($Shipto)) {
                if (strpos($Shipto->getName(), ' ')) {
                    list($fname, $lname) = explode(' ', $Shipto->getName());
                    $vars['first_name'] = $fname;
                    if ($lname) $vars['last_name'] = $lname;
                } else {
                    $vars['first_name'] = $Shipto->getName();
                }
                $vars['address1'] = $Shipto->getAddress1();
                $vars['address2'] = $Shipto->getAddress2();
                $vars['city'] = $Shipto->getCity();
                $vars['state'] = $Shipto->getState();
                $vars['zip'] = $Shipto->getPostal();
                $vars['country'] = $Shipto->getCountry();
            }

            $gateway_vars = '';
            // Create unencrypted buttons if not configured to encrypt,
            // or if encryption fails.
            foreach ($vars as $name=>$value) {
                $gateway_vars .= '<input type="hidden" name="' . $name .
                    '" value="' . $value . '" />' . "\n";
            }
        }

        // Set the text for the button, falling back to our Buy Now
        // phrase if not available
        $btn_text = $P->getBtnText();    // maybe provided by a plugin
        if ($btn_text == '') {
            $btn_text = isset($LANG_SHOP['buttons'][$btn_type]) ?
                $LANG_SHOP['buttons'][$btn_type] : $LANG_SHOP['buy_now'];
        }
        $T = new Template('buttons/' . $this->gw_name);
        $T->set_file('btn', 'btn_' . $btn_info['tpl'] . '.thtml');
        $T->set_var(array(
            'action_url'    => $this->getActionUrl(),
            'btn_text'      => $btn_text,
            'gateway_vars'  => $gateway_vars,
            'method'        => $this->getMethod(),
        ) );
        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
     * Get the invoice terms string for simple invoicing.
     *
     * @param   integer $due_days   Due days (terms)
     * @return  string      Proper terms string for Paypal
     */
    private function getInvoiceTerms($due_days=0) : string
    {
        $due_days = (int)$due_days;
        if ($due_days == 0) {
            $retval = 'Due Upon Receipt';
        } else {
            $day_arr = array(10, 15, 30, 45, 60, 90);
            $retval = 90;
            foreach ($day_arr as $days) {
                if ($due_days <= $days) {
                    $retval = $days;
                    break;
                }
            }
            $retval = 'Net ' . $retval . ' Days';
        }
        return $retval;
    }


    /**
     * Check that the current user is allowed to use this gateway.
     * This limits access to special gateways like 'check' or 'terms'.
     * The internal gateway can be used by all users if the order value
     * is zero.
     *
     * @param   float   $total  Order total
     * @return  boolean     True if access is allowed, False if not
     */
    public function hasAccess($total=0) : bool
    {
        return $this->isEnabled() && SEC_inGroup($this->grp_access);
    }

}
