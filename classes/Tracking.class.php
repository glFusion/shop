<?php
/**
 * Class to standardize shipment tracking information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Standardize the display of tracking information.
 * @package shop
 */
class Tracking
{
    /** Steps recorded along the way.
     * @var array */
    private $steps = array();

    /** Metadata to be shown at the top of the tracking window.
     * @var array */
    private $meta = array();

    /** Error messages to be shown.
     * @var array */
    private $errors = array();


    /**
     * Add a tracking step.
     *
     * @param   array   $info   Array of step information
     */
    public function addStep($info)
    {
        if (isset($info['datetime'])) {
            $datetime = $info['datetime'];
        } else {
            $datetime = NULL;
        }
        $this->steps[] = array(
            'date'      => SHOP_getVar($info, 'date'),
            'time'      => SHOP_getVar($info, 'time'),
            'datetime'  => $datetime,   // date object option
            'location'  => SHOP_getVar($info, 'location'),
            'message'   => SHOP_getVar($info, 'message'),
        );
    }


    /**
     * Add a metadata item.
     * This would be info global to the package, such as carrier name,
     * tracking number, etc. May also include current status.
     * There is no defined layout for this since the information may vary
     * by carrier.
     *
     * @param   string  $lang_str   Translated language string
     * @param   string  $value      Value
     * @param   string  $type       Type of data, e.g. date for special formatting
     */
    public function addMeta($lang_str, $value, $type='string')
    {
        $this->meta[] = array(
            'name'  => $lang_str,
            'value' => $value,
            'type'  => $type,
        );
    }


    /**
     * Add an error messsage.
     *
     * @param   string  $value      Value
     */
    public function addError($value)
    {
        $this->errors[] = $value;
    }


    /**
     * Get the display version of the tracking information.
     *
     * @return  string      HTML for tracking info display
     */
    public function getDisplay()
    {
        global $_CONF, $LANG_SHOP;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('tracking', 'tracking.thtml');
        $T->set_block('tracking', 'trackingMeta', 'mRow');
        $T->set_var(array(
            'steps_count'  => count($this->steps),
        ) );
        foreach ($this->meta as $meta) {
            if ($meta['type'] == 'date') {
                $dt = new \Date($meta['value'], $_CONF['timezone']);
                $value = $dt->format($_CONF['dateonly'], true);
            } else {
                $value = $meta['value'];
            }
            $T->set_var(array(
                'meta_name'  => htmlspecialchars($meta['name']),
                'meta_value' => $value,
            ) );
            $T->parse('mRow', 'trackingMeta', true);
        }
        $T->set_block('tracking', 'trackingSteps', 'tRow');
        foreach ($this->steps as $step) {
            if ($step['datetime'] !== NULL) {
                $date = $step['datetime']->format($_CONF['dateonly'], true);
                $time = $step['datetime']->format($_CONF['timeonly'], true);
            } else {
                $date = $step['date'];
                $time = $step['time'];
            }
            $T->set_var(array(
                'date'  => $date,
                'time'  => $time,
                'message' => htmlspecialchars($step['message']),
                'location' => htmlspecialchars($step['location']),
            ) );
            $T->parse('tRow', 'trackingSteps', true);
        }

        if (!empty($this->errors)) {
            $err_msg = '<p>' . implode('</p><p>', $this->errors) . '</p>';
        }
        $T->set_var('err_msg', $err_msg);
        $T->parse('output', 'tracking');
        return $T->finish($T->get_var('output'));
    }

}

?>
