<?php
/**
 * Class to handle token creation.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;
use Shop\Config;


/**
 * Class for token creation.
 * @package shop
 */
class Token
{
    const VAR_NAME = 'glShopReferrer';

    /**
     * Create a random token string.
     *
     * @param   integer $len    Token length, default = 12 characters
     * @return  string      Token string
     */
    public static function create($len=12)
    {
        try {
            $bytes = random_bytes(ceil($len / 2));
            $retval = substr(bin2hex($bytes), 0, $len);
        } catch (\Exception $e) {
            $retval = '';
            $str = 'abcdefghijklmnopqrstuvwxyz1234567890';
            for ($i = 0; $i < $len; $i++) {
                $idx = rand(0, 35);
                $retval .= $str[$idx];
            }
        }
        return $retval;
    }


    /**
     * Set the referral token.
     *
     * @param   string  $token  Referral token value
     */
    public static function set($token)
    {
        SEC_setCookie(
            self::VAR_NAME,
            $token,
            time() + (Config::get('aff_ref_exp_days') * 86400)
        );
    }


    /**
     * Get the current referral token value.
     *
     * @return  string  Referral token
     */
    public static function get()
    {
        if (isset($_COOKIE[self::VAR_NAME])) {
            return $_COOKIE[self::VAR_NAME];
        } else {
            return '';
        }
    }


    public static function unset()
    {
        SEC_setCookie(
            self::VAR_NAME,
            '',
            time() -3600
        );
        unset($_COOKIE[self::VAR_NAME]);    // for immediate effect
    }


    /**
     * Returns a v4 UUID.
     *
     * @return  string
     */
    public static function uuid()
    {
        $arr = \array_values(\unpack('N1a/n4b/N1c', \openssl_random_pseudo_bytes(16)));
        $arr[2] = ($arr[2] & 0x0fff) | 0x4000;
        $arr[3] = ($arr[3] & 0x3fff) | 0x8000;
        return \vsprintf('%08x-%04x-%04x-%04x-%04x%08x', $arr);
    }

}
