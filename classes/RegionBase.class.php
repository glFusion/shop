<?php
/**
 * Base class for Regions, Countries and States.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to handle country information.
 * @package shop
 */
class RegionBase
{
    /** Error messages returned to the caller.
     * @var array */
    protected $messages = array();


    /**
     * Create an object and set the variables.
     *
     * @param   array   $A      Array from form or DB record
     */
    public function __construct($A)
    {
        $this->setVars($A);
    }


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $varname    Name of DB field to set
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function Toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        if (is_array($id)) {
            $id = implode(',', $id);
        }
        $id = DB_escapeString($id);
        switch ($varname) {     // allow only valid field names
        case static::$KEY . '_enabled':
            // Determing the new value (opposite the old)
            $oldvalue = $oldvalue == 1 ? 1 : 0;
            $newvalue = $oldvalue == 1 ? 0 : 1;

            $sql = "UPDATE {$_TABLES[static::$TABLE]}
                SET $varname = $newvalue
                WHERE " . static::$KEY . "_id IN ($id)";
            // Ignore SQL errors since varname is indeterminate
            //echo $sql;die;
            DB_query($sql, 1);
            if (DB_error()) {
                SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
                return $oldvalue;
            } else {
                Cache::clear('regions');
                return $newvalue;
            }
        }
    }


    /**
     * Toggle a flag for several records at once and sets a status message.
     *
     * @uses    self::Toggle()
     * @param   integer $oldval     Old (current) value
     * @param   string  $varname    Name of DB field to set
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function BulkToggle($oldval, $varname, $id)
    {
        global $LANG_SHOP;

        $newval = self::Toggle($oldval, $varname, $id);
        if ($newval != $oldval) {
            COM_setMsg($LANG_SHOP['msg_updated']);
        } else {
            COM_setMsg($LANG_SHOP['msg_nochange']);
        }
    }


    /**
     * Add an error message into the message array.
     *
     * @param   string  $msg    Message to add
     * @param   boolean $clear  Clear array first?
     */
    protected function addError($msg, $clear=false)
    {
        if ($clear) {
            $this->messages = array();
        }
        $this->messages[] = $msg;
    }


    /**
     * Get the errors that were encountered when saving.
     *
     * @param   boolean $list   True to get as a list, False for a raw array
     * @return  array|string    Array of errors, or HTML list
     */
    public function getErrors($list=true)
    {
        if ($list) {
            if (empty($this->messages)) {
                $retval = '';
            } else {
                $retval = '<ul><li>';
                $retval .= implode('</li><li>', $this->messages);
                $retval .= '</li></ul>';
            }
        } else {
            $retval = $this->messages;
        }
        return $retval;
    }
    

    /**
     * Get the bulk operation options for admin lists.
     * These are the same for all region types, differing only in the
     * variable naming.
     *
     * @return  string      HTML for action buttons
     */
    protected static function getAdminListOptions()
    {
        global $LANG_ADMIN;

        $options = array(
            'chkdelete' => 'true',
            'chkfield' => static::$KEY . '_id',
            'chkname' => static::$KEY. '_id',
            'chkactions' => '<button type="submit" name="ena_' . static::$KEY . '" value="x" ' .
                'class="uk-button uk-button-mini uk-button-success tooltip" ' .
                'title="' . $LANG_ADMIN['enable'] . '" ' .
                '><i class="uk-icon uk-icon-check"></i>' .
                '</button>&nbsp;'.
                '<button type="submit" name="disa_' . static::$KEY . '" value="x" ' .
                'class="uk-button uk-button-mini uk-button-danger tooltip" ' .
                'title="' . $LANG_ADMIN['disable'] . '" ' .
                '><i class="uk-icon uk-icon-ban"></i>' .
                '</button>&nbsp;',
                /*'<button type="submit" name="del_' . static::$KEY . '" value="x" ' .
                'class="uk-button uk-button-mini uk-button-danger tooltip" ' .
                'title="' . $LANG_SHOP['delete'] . '" ' .
                'onclick="return confirm(\'' . $LANG_SHOP['q_del_items'] . '\');" ' .
                '><i class="uk-icon uk-icon-trash"></i>' .
                '</button>',*/
        );
        return $options;
    }

}

?>
