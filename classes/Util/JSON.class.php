<?php
/**
 * Utility class to handle JSON encoding/decoding.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.6.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Util;


/**
 * Wrapper class to handle JSON encoding & decoding.
 * @package    shop
 */
class JSON
{
    /**
     * Encode an array to a JSON string.
     * Simple wrapper for json_encode(), makes sure a valid JSON array string
     * is returned.
     *
     * @return  string      JSON string
     */
    public static function encode(array $arr) : string
    {
        $retval = @json_encode($arr);
        if ($retval === false) {
            $retval = '[]';
        }
        return $retval;
    }


    /**
     * Decode a string into an array.
     * Returns an empty array if there's an error.
     *
     * @param   string  $str    JSON string
     * @return  array       Decoded array
     */
    public static function decode(string $str) : array
    {
        $retval = @json_decode($str, true);
        if (!is_array($retval)) {
            $retval = array();
        }
        return $retval;
    }

}

