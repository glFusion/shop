<?php
/**
 * Class to standardize shipment tracking information.
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
        global $_TABLES;

        $key = self::_makeCacheKey($shipper, $tracknum);
        if (version_compare(GVERSION, Cache::MIN_GVERSION, '<')) {
            $key = DB_escapeString($key);
            $exp = time();
            $data = DB_getItem(
                $_TABLES['shop.cache'],
                'data',
                "cache_key = '$key' AND expires >= $exp"
            );
            if ($data !== NULL) {
                $data = @unserialize(base64_decode($data));
            }
        } else {
            $data = Cache::get($key);
        }
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
        global $_TABLES;

        $key = self::_makeCacheKey($shipper, $tracknum);
        if (version_compare(GVERSION, Cache::MIN_GVERSION, '<')) {
            $key = DB_escapeString($key);
            $data = DB_escapeString(base64_encode(serialize($this)));
            $exp = time() + 600;
            $sql = "INSERT IGNORE INTO {$_TABLES['shop.cache']} SET
                cache_key = '$key',
                expires = $exp,
                data = '$data'
                ON DUPLICATE KEY UPDATE
                    expires = $exp,
                    data = '$data'";
            DB_query($sql);
        } else {
            Cache::set($key, $this, 'shop.tracking', 600);
        }
    }

}

?>
