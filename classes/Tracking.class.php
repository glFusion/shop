<?php
/**
 * Class to standardize shipment tracking information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
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
    /** Time in minutes to cache tracking info.
     * @const integer */
    private const CACHE_MINUTES = 60;

    /** Steps recorded along the way.
     * @var array */
    private $steps = array();

    /** Metadata to be shown at the top of the tracking window.
     * @var array */
    private $meta = array();

    /** Error messages to be shown.
     * @var array */
    private $errors = array();

    /** Timestamp when data was retrieved from the shipper.
     * @var string */
    private $timestamp = '';


    /**
     * Add a tracking step.
     * Parameter array should include:
     * - location
     * - message
     * - date
     * - time
     * - Alternatively, a datetime object which will supercede the date/time
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
            'value' => (string)$value,  // Could be SimpleXmlElement
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
        $this->errors[] = (string)$value;
    }


    /**
     * Get the display version of the tracking information.
     *
     * @return  string      HTML for tracking info display
     */
    public function getDisplay()
    {
        global $_CONF, $LANG_SHOP;

        $dt_format = 'd M Y';
        $T = new Template;
        $T->set_file('tracking', 'tracking.thtml');
        $T->set_block('tracking', 'trackingMeta', 'mRow');
        $T->set_var(array(
            'steps_count'  => count($this->steps),
        ) );
        foreach ($this->meta as $meta) {
            if ($meta['type'] == 'date') {
                $dt = new \Date($meta['value'], $_CONF['timezone']);
                $value = $dt->format($dt_format, true);
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
                $date = $step['datetime']->format($dt_format, true);
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
            $T->set_var('err_msg', $err_msg);
        }
        $T->set_var('current_as_of', sprintf($LANG_SHOP['current_as_of'], $this->getTimestamp()));
        $T->parse('output', 'tracking');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Make a cache key for a specific tracking request.
     *
     * @param   string  $shipper    Shipper ID code
     * @param   string  $tracknum   Tracking Number
     * @return  string      Cache key
     */
    private static function _makeCacheKey($shipper, $tracknum)
    {
        return "shop.tracking.{$shipper}.{$tracknum}";
    }


    /**
     * Read a Tracking object from cache.
     *
     * @param   string  $shipper    Shipper ID code
     * @param   string  $tracknum   Tracking Number
     * @return  object|null     Tracking object, NULL if not found
     */
    public static function getCache($shipper, $tracknum)
    {
        $key = self::_makeCacheKey($shipper, $tracknum);
        $data = Cache::get($key);
        return $data;
    }


    /**
     * Set the current Tracking object into cache.
     *
     * @param   string  $shipper    Shipper ID code
     * @param   string  $tracknum   Tracking Number
     */
    public function setCache($shipper, $tracknum)
    {
        $key = self::_makeCacheKey($shipper, $tracknum);
        $this->setTimestamp();
        Cache::set($key, $this, 'shop.tracking', self::CACHE_MINUTES);
    }


    /**
     * Set the timestamp string when data was retrieved.
     *
     * @param   string|null $ts Timestamp, null for current date/time
     * @return  object  $this
     */
    protected function setTimestamp($ts=NULL)
    {
        global $_CONF;

        if ($ts === NULL) {
            $ts = $_CONF['_now']->toMySQL(true);
        }
        $this->timestamp = $ts;
        return $this;
    }


    /**
     * Get the timestamp when data was updated.
     *
     * @return  string      Timestamp string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

}
