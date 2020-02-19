<?php
/**
 * DataBase Object class to provide common functions for other classes.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Base class for database operations.
 * Requires derived classes to define the `$TABLE` variable.
 * @package shop
 */
abstract class DBO
{
    /** Key field name.
     * @var string */
    protected static $F_ID = 'id';

    /** Order field name.
     * @var string */
    protected static $F_ORDERBY = 'orderby';

    /** Table key. Blank value will cause no action to be taken.
     * @var string */
    protected static $TABLE = '';


    /**
     * Move a record up or down the admin list.
     *
     * @param   string  $id     ID field value
     * @param   string  $where  Direction to move (up or down)
     */
    public static function moveRow($id, $where)
    {
        global $_TABLES;

        // Do nothing if the derived class did not specify a table key.
        if (static::$TABLE == '') {
            return;
        }

        switch ($where) {
        case 'up':
            $oper = '-';
            break;
        case 'down':
            $oper = '+';
            break;
        default:
            $oper = '';
            break;
        }

        $f_orderby = static::$F_ORDERBY;
        $f_id = static::$F_ID;
        if (!empty($oper)) {
            $sql = "UPDATE {$_TABLES[static::$TABLE]}
                    SET $f_orderby = $f_orderby $oper 11
                    WHERE $f_id = '" . DB_escapeString($id) . "'";
            //echo $sql;die;
            DB_query($sql);
            self::ReOrder();
        }
    }


    /**
     * Reorder all records.
     */
    public static function ReOrder()
    {
        global $_TABLES;

        // Do nothing if the derived class did not specify a table key.
        if (static::$TABLE == '') {
            return;
        }

        $f_id = static::$F_ID;
        $f_orderby = static::$F_ORDERBY;
        $table = $_TABLES[static::$TABLE];
        $sql = "SELECT $f_id, $f_orderby
                FROM $table
                ORDER BY $f_orderby ASC;";
        $result = DB_query($sql);

        $order = 10;
        $stepNumber = 10;
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $sql = "UPDATE $table
                    SET $f_orderby = '$order'
                    WHERE $f_id = '" . DB_escapeString($A[$f_id]) . "'";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
    }

}

?>
