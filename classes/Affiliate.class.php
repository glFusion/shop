<?php
/**
 * Class to handle affiliate/referral information.
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
namespace Shop;


/**
 * Class for affiliate tracking info.
 * @package shop
 */
class Affiliate
{
    /** User ID.
     * @var integer */
    private $aff_uid = 0;

    /** Customer object.
     * @var object */
    private $Customer = NULL;

    /** Sales objects.
     * @var array */
    private $sales = array();

    /** Payment objects.
     * @var array */
    private $payments = array();


    /**
     * Constructor.
     * Reads in the specified user, if $id is set.  If $id is zero,
     * then the current user is used.
     *
     * @param   integer     $uid    Optional user ID
     */
    public function __construct($uid=0)
    {
        global $_USER;

        if ($uid < 1) {
            $uid = $_USER['uid'];
        }
        $this->uid = (int)$uid;  // Save the user ID
        $this->Customer = Customer::getInstance($this->uid);
    }


    /**
     * Display a list of all affiliates.
     * Only includes those buyers in the Shop "userinfo" table.
     *
     * @param   boolean $pending_payout    True to only show those pending payouts
     * @return  string      HTML for admin list
     */
    public static function adminList($pending_payout=false)
    {
        global $_TABLES, $LANG_SHOP, $LANG_ADMIN, $LANG_SHOP_HELP, $LANG28;

        $display = '';
        $pending_where = '';
        if ($pending_payout) {
            $pending_sql = ", '_coupon' AS payout_method,
                (SELECT sum(aff_pmt_amount)
                FROM {$_TABLES['shop.affiliate_payments']}
                WHERE aff_pmt_uid = u.uid) as sent_payout";
            if ($pending_payout != 'all') {
                $pending_where = " AND af.aff_pmt_method = '" . DB_escapeString($pending_payout) . "' ";
            }
        } else {
            $pending_sql = '';
        }

        $sql = "SELECT u.uid, u.fullname, u.email, af.affiliate_id,
            sum(si.aff_item_total) as total_sales,
            sum(si.aff_item_pmt) as total_payout
            $pending_sql
            FROM {$_TABLES['users']} u
            RIGHT JOIN {$_TABLES['shop.userinfo']} af
                ON af.uid = u.uid
            LEFT JOIN {$_TABLES['shop.affiliate_sales']} as s
                ON s.aff_sale_uid = u.uid
            LEFT JOIN {$_TABLES['shop.affiliate_saleitems']} as si
                ON s.aff_sale_id = si.aff_sale_id";
        //echo $sql;die;

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'uid',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'fullname',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG28[7],
                'field' => 'email',
            ),
            array(
                'text'  => 'Affiliate ID',
                'field' => 'affiliate_id',
            ),
            array(
                'text'  => 'Total Sales',
                'field' => 'total_sales',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => 'Total Payouts',
                'field' => 'total_payout',
                'sort'  => true,
                'align' => 'right',
            ),
        );

        $defsort_arr = array(
            'field' => 'uid',
            'direction' => 'asc',
        );

        $query_arr = array(
            'table' => 'users',
            'sql'   => $sql,
            'query_fields' => array(
                'u.fullname',
                'u.email',
            ),
            'default_filter' => "WHERE af.affiliate_id <> '' $pending_where",
            'group_by' => 'u.uid',
        );
        if ($pending_payout) {
            $header_arr[] = array(
                'text'  => $LANG_SHOP['pending_payout'],
                'field' => 'pending_payout',
                'sort'  => true,
                'align' => 'right',
            );
            $header_arr[] = array(
                'text'  => $LANG_SHOP['pmt_method'],
                'field' => 'payout_method',
                'sort'  => true,
            );
            $sess_key = 'payout';
            $query_arr['group_by'] .= ' HAVING (total_payout > 0 AND sent_payout IS NULL) OR (total_payout - sent_payout) > 0';
            $bulk_update = '<button type="submit" name="do_payout" value="x" ' .
                'class="uk-button uk-button-primary uk-button-mini tooltip" ' .
                'title="' . $LANG_SHOP['payout'] . '">' .
                $LANG_SHOP['payout'] .
                '</button>';
            $chkboxes = true;
        } else {
            $sess_key = 'all';
            $bulk_update = '';
            $chkboxes = false;
        }

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . "/affiliates.php?$sess_key",
        );
        if ($pending_payout) {
            $text_arr['form_url'] .= '&method=' . $pending_payout;
        }

        $options = array(
            'chkselect' => $chkboxes,
            'chkall' => $chkboxes,
            'chkfield' => 'uid',
            'chkname' => 'aff_uid',
            'chkactions' => $bulk_update,
        );
        $filter = '';
        $extras = array(
            'Currency' => Currency::getInstance(),
        );

        return ADMIN_list(
            Config::PI_NAME . '_afflist_' . $sess_key,
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, $extras, $options, ''
        );

    }


    /**
     * Display the affiliate sales information for a single affiliate.
     *
     * @param   integer $cat_id     Category ID to limit listing
     * @return  string      Display HTML
     */
    public static function userList($uid = 0)
    {
        global $_TABLES, $LANG_SHOP, $_SHOP_CONF, $_USER, $_CONF;

        USES_lib_admin();

        $tz_offset = $_CONF['_now']->format('P', true);
        $Cur = Currency::getInstance();
        if ($uid == 0 || !plugin_ismoderator_shop()) {
            $uid = (int)$_USER['uid'];
            $uid = 4;       // TODO testing
        } else {
            // For administrator viewing
            $uid = (int)$uid;
        }

        $comm_total = $comm_paid = $comm_due = 0;
        $sql = "SELECT SUM(IF(aff_pmt_id = 0, 0, aff_item_pmt)) AS total_paid,
            SUM(aff_item_pmt) AS total_payout,
            u.fullname
            FROM {$_TABLES['users']} u
            LEFT JOIN {$_TABLES['shop.affiliate_sales']} sale
                ON u.uid = sale.aff_sale_uid
            LEFT JOIN {$_TABLES['shop.affiliate_saleitems']} item
                ON sale.aff_sale_id = item.aff_sale_id
            WHERE u.uid = $uid
            GROUP BY u.uid";

        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            if ($A) {
                $comm_total = (float)$A['total_payout'];
                $comm_paid = (float)$A['total_paid'];
                $comm_due = $comm_total - $comm_paid;
            }
        }

        $sql = "SELECT item.*, sale.aff_pmt_id,
            CONVERT_TZ(sale.aff_sale_date, '+00:00', '$tz_offset') as sale_date,
            oi.sku, oi.description,oi.net_price,
            (oi.net_price * oi.quantity) as item_sale_amount
            FROM {$_TABLES['shop.affiliate_saleitems']} item
            RIGHT JOIN {$_TABLES['shop.affiliate_sales']} sale
            ON sale.aff_sale_id = item.aff_sale_id
            RIGHT JOIN {$_TABLES['shop.orderitems']} oi
            ON oi.id = item.aff_oi_id
            WHERE sale.aff_sale_uid = $uid";

        $header_arr = array(
            array(
                'text' => $LANG_SHOP['purch_date'],
                'field' => 'sale_date',
                'sort' => 'true',
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'sku',
            ),
            array(
                'text' => $LANG_SHOP['amount'],
                'field' => 'aff_item_total',
                'sort' => true,
                'align' => 'right',
            ),
            array(
                'text' => 'Percent',
                'field' => 'aff_percent',
                'sort' => true,
                'align' => 'right',
            ),
            array(
                'text' => 'Commission',
                'field' => 'aff_item_pmt',
                'align' => 'right',
                'sort' => true,
            ),
            array(
                'text' => 'Due',
                'field' => 'comm_due',
                'align' => 'right',
                'sort' => false,
            ),
        );

        $defsort_arr = array(
            'field' => 'aff_sale_date',
            'direction' => 'DESC',
        );

        $query_arr = array(
            'table' => 'shop.affiliate_sales',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/affiliate.php?sales=x',
        );
        $extras = array(
            'Currency' => $Cur,
        );
        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('header', 'aff_header.thtml');
        $T->set_var(array(
            'comm_total' => $Cur->Format($comm_total),
            'comm_paid' => $Cur->Format($comm_paid),
            'comm_due' => $Cur->Format($comm_due),
            'lang_commissions' => $LANG_SHOP['commissions'],
            'lang_total' => $LANG_SHOP['total'],
            'lang_paid' => $LANG_SHOP['paid'],
            'lang_due' => $LANG_SHOP['bal_due'],
        ) );
        $T->parse('output', 'header');
        $display .= $T->finish($T->get_var('output'));
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_affsalelist',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', $extras, '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the affiliate list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extras     Extra verbatim parameters
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extras)
    {
        switch($fieldname) {
        case 'uid':
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/affiliates.php?uid=' . $fieldvalue
            );
            break;
        case 'comm_due':
            if ($A['aff_pmt_id'] > 0) {
                $fieldvalue = 0;
            } else {
                $fieldvalue = $A['aff_item_pmt'];
            }
        case 'total_sales':
        case 'total_payout':
        case 'total_paid':
        case 'aff_item_pmt':
            $retval = $extras['Currency']->formatValue($fieldvalue);
            break;
        case 'pending_payout':
            $retval = $extras['Currenncy']->formatValue($A['total_payout'] - $A['sent_payout']);
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
