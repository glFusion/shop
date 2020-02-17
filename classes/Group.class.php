<?php
/**
 * Class to interface with glFusion groups for discounts and other access.
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
 * Class to interact with glFusion groups.
 * @package shop
 */
class Group
{
    const OPT_OVERRIDE = 0;
    const OPT_COMBINE = 1;
    const OPT_IGNORE = 2;
    const OPT_BEST = 3;

    /** glFusion group ID.
     * @var integer */
    private $gid = 0;

    /** Group name/description from the glFusion group table.
     * @var string */
    private $dscp = '';

    /** Group order number.
     * When a buyer is a member of multiple groups, determines which
     * group is used.
     */
    private $orderby = 999;

    /** Discounts applied for this group.
    * @var array */
    private $percent = 0;

    /** Combine group discounts with sale prices?
     * Combine with, override, or ignore and use sale price.
     * @var integer */
    private $sale_opt = 0;


    /**
     * Constructor.
     * Reads in the specified user, if $id is set.  If $id is zero,
     * then the current user is used.
     *
     * @param   integer     $uid    Optional user ID
     */
    public function __construct($gid=0)
    {
        if (is_array($gid)) {
            $this->setVars($gid);
        } else {
            $this->setGid($gid);
            if ($this->gid > 0) {
                $this->getRecord();
            }
        }
    }


    /**
     * Get an instance of a group.
     *
     * @param   string  $gid    Group ID
     * @return  object          Gateway object
     */
    public static function getInstance($gid)
    {
        global $_TABLES, $_SHOP_CONF;
        static $grps = array();

        if (!array_key_exists($gid, $grps)) {
            $grps[$gid] = new self($gid);
        }
        return $grps[$gid];
    }


    /**
     * Get all groups into an array.
     *
     * @retrun  array       Array of Group objects
     */
    public static function getAll()
    {
        global $_TABLES;

        $cache_key = 'shop.groups.all';
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $retval = array();
            $sql = "SELECT g.*, ug.grp_name FROM {$_TABLES['shop.groups']} g
                LEFT JOIN {$_TABLES['groups']} ug
                    ON g.gid = ug.grp_id
                ORDER BY g.orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['gid']] = new self($A);
            }
            Cache::set($cache_key, $retval, 'groups', 86400);
        }
        return $retval;
    }


    /**
     * Find and return the first group object for a user ID.
     *
     * @param   integer $uid    User ID
     * @return  object          Group object
     */
    public static function findByUser($uid)
    {
        $groups = \Group::getAssigned($uid);
        foreach (self::getAll() as $Grp) {
            if (in_array($Grp->getGid(), $groups)) {
                return $Grp;
            }
        }
        return new self;
    }


    /**
     * Apply the group dicount to an order item.
     * Updates the Item object in-place.
     *
     * @param   object  $Item   Order Item object
     * @return  object  $this
     */
    public function applyDiscount(&$Item)
    {
        COM_errorLog("applying discount {$this->percent}");
        if ($this->percent == 0) {
            // Nothing to do, perhaps no group object was found.
            return $this;
        }

        $factor = (100 - $this->percent) / 100;
        switch ($this->sale_opt) {
        case self::OPT_OVERRIDE:
            // Use the group discount in place of any sale pricing.
            $price = round($Item->getPrice() * $factor, 2);
            $Item->setNetPrice($price);
            break;
        case self::OPT_COMBINE:
            // Apply the group discount to sale prices.
            $price = round($Item->getNetPrice() & $factor, 2);
            $Item->setNetPrice($price);
            break;
        case self::OPT_IGNORE:
            // Only apply group discounts to non-sale items.
            $gross_price = $Item->getPrice();
            $net_price = $Item->getNetPrice();
            if ($gross_price == $net_price) {
                $price = round($net_price * $factor, 2);
                $Item->setNetPrice($price);
            }
            break;
        case self::OPT_BEST:
            // Apply the group or sale price, whichever is lower.
            $price = round($Item->getPrice() * $factor, 2);
            if ($price < $Item->getNetPrice()) {
                $Item->setNetPrice($price);
            }
            break;
        }
        COM_errorLog("set price to " . $Item->getNetPrice());
        return $this;
    }


    /**
     * Read one record from the database.
     *
     * @return  object  $this
     */
    private function getRecord()
    {
        global $_TABLES;

        $res = DB_query(
            "SELECT g.*, gl.grp_name FROM {$_TABLES['shop.groups']} g
            LEFT JOIN {$_TABLES['groups']} gl
                ON g.gid = gl.grp_id
                WHERE g.gid = {$this->gid}"
        );
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $this->setVars($A);
        } else {
            $this->setGid(0);
        }
        return $this;
    }


    /**
     * Set the variables from a DB record into object properties.
     *
     * @param   array   $A      Array of properties
     * @return  object  $this
     */
    public function setVars($A)
    {
        $this
            ->setGid(SHOP_getVar($A, 'gid', 'integer', 0))
            ->setDscp(SHOP_getVar($A, 'grp_name'))
            ->setOrderby(SHOP_getVar($A, 'orderby', 'integer', 999))
            ->setDiscount(SHOP_getVar($A, 'percent', 'float', 0))
            ->setSaleOpt(SHOP_getVar($A, 'sale_opt', 'integer', 0));
        return $this;
    }

    /**
     * Set the glFusion group ID related to this shopping group.
     *
     * @param   integer $gid    Group ID
     * @return  object  $this
     */
    public function setGid($gid)
    {
        $this->gid = (int)$gid;
        return $this;
    }


    /**
     * Get the group ID property.
     *
     * @return  integer     Group ID
     */
    public function getGid()
    {
        return (int)$this->gid;
    }


    /**
     * Set the description from the glFusion group table.
     *
     * @param   string  $dscp   Group Name/Description
     * @return  object  $this
     */
    private function setDscp($dscp)
    {
        $this->dscp = $dscp;
        return $this;
    }


    /**
     * Set the orderby value.
     *
     * @param   integer $val    Orderby value
     * @return  object  $this
     */
    private function setOrderby($val)
    {
        $this->orderby = (int)$val;
        return $this;
    }


    /**
     * Set the discount associated with this glFusion group.
     *
     * @param   float   $percent    Discount percentage
     * @return  object  $this
     */
    public function setDiscount($percent)
    {
        $this->percent = min((float)$percent, 100);
        return $this;
    }


    /**
     * Set the action when a sale price is also available.
     *
     * @param   float   $sale_opt   Sales interaction option.
     * @return  object  $this
     */
    public function setSaleOpt($sale_opt)
    {
        $this->sale_opt = (int)$sale_opt;
        return $this;
    }


    /**
     * Delete the group information.
     *
     * @param   integer $gid    Group ID
     */
    public static function deleteGroup($gid)
    {
        global $_TABLES;

        $gid = (int)$gid;
        DB_delete($_TABLES['shop.groups'], 'gid', $gid);
    }


    /**
     * Edit a group record.
     *
     * @return  string      HTML for editing form
     */
    public function Edit()
    {
        global $_TABLES, $LANG_SHOP;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file(array(
            'form' => 'group_form.thtml',
            'tips' => 'tooltipster.thtml',
        ) );

        $sale_opt_sel = '';
        foreach ($LANG_SHOP['sale_opt'] as $key=>$text) {
            $sale_opt_sel .= '<option value="' . $key . '">' . $text . '</option>' . LB;
        }
        $T->set_var(array(
            'gid'     => $this->gid,
            'grp_sel' => COM_optionList(
                $_TABLES['groups'],
                'grp_id,grp_name',
                $this->gid
            ),
            'percent' => $this->percent,
            'sale_opt_select' => $sale_opt_sel,
        ) );
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output','form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Move a group definition up or down the admin list.
     *
     * @param   string  $id     Gateway IDa
     * @param   string  $where  Direction to move (up or down)
     */
    public static function moveRow($id, $where)
    {
        global $_TABLES;

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

        if (!empty($oper)) {
            $id = DB_escapeString($id);
            $sql = "UPDATE {$_TABLES['shop.groups']}
                    SET orderby = orderby $oper 11
                    WHERE gid = '$id'";
            //echo $sql;die;
            DB_query($sql);
            self::ReOrder();
        }
    }


    /**
     * Reorder all groups.
     */
    public static function reOrder()
    {
        global $_TABLES;

        $sql = "SELECT gid, orderby
                FROM {$_TABLES['shop.groups']}
                ORDER BY orderby ASC;";
        $result = DB_query($sql);

        $order = 10;
        $stepNumber = 10;
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $sql = "UPDATE {$_TABLES['shop.groups']}
                    SET orderby = '$order'
                    WHERE gid = {$A['gid']}";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
        Cache::clear('groups');
    }


    /**
     * Save the current values to the database.
     *
     * @param   array   $A      Array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save($A = array())
    {
        global $_TABLES, $_SHOP_CONF;

        if (is_array($A)) {
            $this->setVars($A);
        }

        $sql = "INSERT INTO {$_TABLES['shop.groups']} SET
            gid = {$this->gid},
            percent = {$this->percent},
            sale_opt = {$this->sale_opt}
            ON DUPLICATE KEY UPDATE
            percent = {$this->percent},
            sale_opt = {$this->sale_opt}";
        SHOP_log($sql, SHOP_LOG_DEBUG);
        //echo $sql;die;
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            self::reOrder();
            return true;
        } else {
            return false;
        }
    }


    /**
     * Member Group Admin View.
     *
     * @return  string      HTML for the gateway listing
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT g.*, ug.grp_name FROM {$_TABLES['shop.groups']} g
            LEFT JOIN {$_TABLES['groups']} ug
                ON g.gid = ug.grp_id";
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['orderby'],
                'field' => 'orderby',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => 'ID',
                'field' => 'gid',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['description'],
                'field' => 'grp_name',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['discount'],
                'field' => 'percent',
                'sort'  => false,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['grp_sale_opt'],
                'field' => 'sale_opt',
                'sort'  => false,
            ),
        );
        $query_arr = array(
            'table' => 'shop.groups',
            'sql'   => $sql,
            'query_fields' => array(
            ),
            'default_filter' => '',
        );
        $defsort_arr = array(
            'field' => 'orderby',
            'direction' => 'asc',
        );
        $extra = array(
            'grp_count' => DB_count($_TABLES['shop.groups']),
        );

        $display .= '<div class="uk-alert">' . $LANG_SHOP_HELP['hlp_grpadmin'] . '</div>';
        $display .= COM_createLink(
            $LANG_SHOP['new_group'],
            SHOP_ADMIN_URL . '/index.php?grpedit=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $display .= COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_grplist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', $extra, '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the group admin list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra verbatim values
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;
        static $today = NULL;

        if ($today === NULL) {
            $today = SHOP_now()->format('Y-m-d');
        }
        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML(
                    'edit',
                    'tooltip',
                    array(
                        'title' => $LANG_ADMIN['edit'],
                    )
                ),
                SHOP_ADMIN_URL . "/index.php?grpedit=x&amp;id={$A['gid']}"
            );
            break;
        case 'orderby':
            if ($fieldvalue == 999) {
                return '';
            } elseif ($fieldvalue > 10) {
                $retval = COM_createLink(
                    Icon::getHTML('arrow-up', 'uk-icon-justify'),
                    SHOP_ADMIN_URL . '/index.php?grpmove=up&id=' . $A['gid']
                );
            } else {
                $retval = '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            if ($fieldvalue < $extra['grp_count'] * 10) {
                $retval .= COM_createLink(
                    Icon::getHTML('arrow-down', 'uk-icon-justify'),
                    SHOP_ADMIN_URL . '/index.php?grpmove=down&id=' . $A['gid']
                );
            } else {
                $retval .= '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            break;
        case 'percent':
            $retval = sprintf('%.02f%%', $fieldvalue);
            break;
        case 'sale_opt':
            $retval = $LANG_SHOP['sale_opt'][(int)$fieldvalue];
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}

?>
