<?php
/**
 * Class to handle token creation.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;


/**
 * Class for token creation.
 * @package shop
 */
class Token
{
    public const NUMERIC = 1;
    public const UPPERCASE = 2;
    public const LOWERCASE = 4;
    public const RANDOM = 8;
    public const ALPHABETIC = 6;
    public const ALPHANUMERIC = 7;
    public const SID = 16;      // create a story ID

    /**
     * Create a random token string.
     *
     * @param   integer $len    Token length, default = 12 characters
     * @return  string      Token string
     */
    public static function create(?int $format=self::RANDOM, int $len=12) : string
    {
        $retval = '';
        if ($len < 1) {
            $len = 12;
        }
        if (empty($format)) {
            $format = self::RANDOM;
        }
        if ($format & self::RANDOM) {
            try {
                $bytes = random_bytes(ceil($len / 2));
                $retval = substr(bin2hex($bytes), 0, $len);
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
        } elseif ($format & self::SID) {
            $retval = COM_makeSid();
        }

        if ($retval == '') {    // other format requested, or random_bytes failed
            $str = '';
            if ($format & self::NUMERIC) {
                $str .= '0123456789';
            }
            if ($format & self::LOWERCASE) {
                $str .= 'abcdefghijklmnopqrstuvwxyz';
            }
            if ($format & self::UPPERCASE) {
                $str .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            }
            $chars = strlen($str)-1;
            for ($i = 0; $i < $len; $i++) {
                $retval .= $str[rand(0, $chars)];
            }
        }
        return $retval;
    }


    /**
     * Returns a v4 UUID.
     *
     * @return  string
     */
    public static function uuid()
    {
        $arr = \array_values(\unpack('N1a/n4b/N1c', random_bytes(16)));
        $arr[2] = ($arr[2] & 0x0fff) | 0x4000;
        $arr[3] = ($arr[3] & 0x3fff) | 0x8000;
        return \vsprintf('%08x-%04x-%04x-%04x-%04x%08x', $arr);
    }


    /**
     * Encrypt a string. Currently just a wrapper for COM_encrypt().
     *
     * @param   string  $val    Value to encrypt
     * @param   string  $key    Optional encryption key
     * @return  string      Encrypted value
     */
    public static function encrypt(string $val, string $key='') : string
    {
        return COM_encrypt($val, $key);
    }


    /**
     * Decrypt a string. Currently just a wrapper for COM_encrypt().
     *
     * @param   string  $val    Encrypted value to decrypt
     * @param   string  $key    Optional encryption key
     * @return  string      Decrypted value
     */
    public static function decrypt(string $enc, string $key='') : string
    {
        return COM_decrypt($enc, $key);
    }

}
