<?php
/**
 * Class to handle referrer tokens
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
 * Class for referral tag handling.
 * @package shop
 */
class ReferralTag extends Token
{
    const VAR_NAME = 'glShopReferrer';

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
        $_COOKIE[self::VAR_NAME] = $token;  // for immediate effect
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


    /**
     * Delete a referrer token.
     */
    public static function remove()
    {
        SEC_setCookie(
            self::VAR_NAME,
            '',
            time() -3600
        );
        unset($_COOKIE[self::VAR_NAME]);    // for immediate effect
    }

}
