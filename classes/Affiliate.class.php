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

        return ADMIN_list(
            Config::PI_NAME . '_afflist_' . $sess_key,
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );

    }


    /**
     * Get an individual field for the affiliate list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        static $Cur = NULL;
        if ($Cur === NULL) {
            $Cur = Currency::getInstance();
        }

        switch($fieldname) {
        case 'total_sales':
        case 'total_payout':
            $retval = $Cur->formatValue($fieldvalue);
            break;
        case 'pending_payout':
            $retval = $Cur->formatValue($A['total_payout'] - $A['sent_payout']);
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
