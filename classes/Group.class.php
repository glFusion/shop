<?php
/**
 * Class to interface with glFusion groups for discounts and other access.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
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
    private const OPT_OVERRIDE = 0;
    private const OPT_COMBINE = 1;
    private const OPT_IGNORE = 2;

    /** glFusion group ID.
     * @var integer */
    private $gid;

    /** Group order number.
     * When a buyer is a member of multiple groups, determines which
     * group is used.
     */
    private $orderby;

    /** Discounts applied for this group.
    * @var array */
    private $percent;

    /** Combine group discounts with sale prices?
     * Combine with, override, or ignore and use sale price.
     * @var integer */
    private $sale_opt;


    /**
     * Constructor.
     * Reads in the specified user, if $id is set.  If $id is zero,
     * then the current user is used.
     *
     * @param   integer     $uid    Optional user ID
     */
    public function __construct($gid=0)
    {
        $this->setGid($gid);
        if ($this->gid > 0) {
            $this->getRecord();
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
     * Read one record from the database.
     *
     * @return  object  $this
     */
    private function getRecord()
    {
        global $_TABLES;

        $res = DB_query(
            "SELECT * FROM {$_TABLES['shop.groups']} g
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
            ->setOrderby(SHOP_getVar($A, 'orderby', 'integer', 999))
            ->setPercent(SHOP_getVar($A, 'percent', 'float', 0))
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
    public static function ReOrder()
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
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN,
            $LANG32;

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
            $filter, '', $options, ''
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
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
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
