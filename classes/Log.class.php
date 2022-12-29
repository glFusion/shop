<?php
/**
 * Log class wrapper to include the plugin name and class/function info.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Log wrapper class.
 * @package shop
 */
class Log extends \glFusion\Log\Log
{

    /**
     * Write the log message, including debug info.
     *
     * @param   string  $scope      Log scope
     * @param   integer $logLevel   Severity level
     * @param   string  $logEntry   Log entry text
     * @param   array   $context
     * @param   array   $extra
     */
    public static function write(
        $scope,
        $logLevel = self::INFO,
        $logEntry = '',
        $context = array(),
        $extra = array()
    ) {
        $msg1 = '(Shop) ';
        /*if ($logLevel != self::INFO) {
            // For Info just log the message, others log the offending file info.
            $bk = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            if (isset($bk[1])) {
                if (isset($bk[1]['class'])) {
                    $msg1 .= $bk[1]['class'] . $bk[1]['type'];
                }
                $msg1 .= $bk[1]['function'] . '(): ';
            } else {
                $msg1 .= basename($bk[0]['file']) . '(' . $bk[0]['line'] . ') ';
            }
        }*/
        parent::write($scope, $logLevel, $msg1 . $logEntry, $context, $extra);
    }


    public static function system(
        $logLevel = self::INFO,
        $logEntry = '',
        $context = array(),
        $extra = array()
    ) {
        parent::write('system', $logLevel, $logEntry, $context, $extra);
    }


    public static function info(
        $logEntry = '',
        $context = array(),
        $extra = array()
    ) {
        parent::write('shop', Log::INFO, $logEntry, $context, $extra);
    }


    public static function error(
        $logEntry = '',
        $context = array(),
        $extra = array()
    ) {
        parent::write('shop', Log::ERROR, $logEntry, $context, $extra);
    }


    public static function warn(
        $logEntry = '',
        $context = array(),
        $extra = array()
    ) {
        parent::write('shop', Log::WARNING, $logEntry, $context, $extra);
    }


    public static function debug(
        $logEntry = '',
        $context = array(),
        $extra = array()
    ) {
        parent::write('shop', Log::DEBUG, $logEntry, $context, $extra);
    }

}
