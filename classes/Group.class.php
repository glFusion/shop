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
    private const OPT_OVERRIDE = 1;
    private const OPT_COMBINE = 2;
    private const OPT_IGNORE = 4;

    /** glFusion group ID.
     * @var integer */
    private $gid;

    /** Discounts applied for this group.
    * @var array */
    private $discount;

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
     * Read one record from the database.
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
            $this->setGid($A['gid'])
                ->setDiscount($A['discount'])
                ->setSaleOpt($A['sale_opt']);
        } else {
            $this->setGid(0);
        }
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
     * Set the discount associated with this glFusion group.
     *
     * @param   float   $discount   Discount amount
     * @return  object  $this
     */
    public function setDiscount($discount)
    {
        $this->discount = (float)$discount;
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
     * Save the current values to the database.
     * The $A parameter must contain the addr_id value if updating.
     *
     * @param   array   $A      Array of data ($_POST)
     * @param   string  $type   Type of address (billing or shipping)
     * @return  array       Array of DB record ID, -1 for failure and message
     */
    public function Save($A = NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            if (isset($A['gid'])) {
                $this->setGid($A['gid']);
            }
            if (isset($A['discount'])) {
                $this->setDiscount($A['discount']);
            }
        }

        $sql = "INSERT INTO {$_TABLES['shop.groups']} SET
            gid = {$this->gid},
            discount = {$this->discount}
            ON DUPLICATE KEY UPDATE
            discount = {$this->discount}";
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        return DB_error() ? false : true;
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

}

?>
