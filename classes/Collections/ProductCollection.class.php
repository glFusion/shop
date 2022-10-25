<?php
/**
 * Class to handle product collections.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Collections;
use glFusion\Database\Database;
use Shop\Models\ProductType;
use Shop\Product;
use Shop\Category;


/**
 * Class to display the product catalog.
 * @package shop
 */
class ProductCollection extends Collection
{

    public function __construct()
    {
        global $_TABLES, $_CONF;

        parent::__construct();

        $this->addCacheTags(array('products'));
        $this->_qb->select('p.*', "(
                SELECT sum(stk.qty_onhand) FROM {$_TABLES['shop.stock']} stk
                WHERE stk.stk_item_id = p.id
                ) AS qty_onhand"
            )
                  ->from($_TABLES['shop.products'], 'p')
                  ->leftJoin('p', $_TABLES['shop.prodXcat'], 'pxc', 'p.id = pxc.product_id')
                  ->leftJoin('pxc', $_TABLES['shop.categories'], 'c', 'pxc.cat_id=c.cat_id')
                  ->where('p.enabled = 1')
                  ->andWhere(
                      '(c.enabled = 1 ' . $this->_db->getAccessSql('AND', 'c.grp_access') . ')
                        OR c.enabled IS NULL'
                    )
                    ->andWhere('p.avail_beg <= :today')
                    ->andwhere('p.avail_end >= :today')
                    ->setParameter('today', $_CONF['_now']->format('Y-m-d', true), Database::STRING)
                    ->groupBy('p.id');
    }


    /**
     * Add a search string.
     *
     * @param   string  $str    Search string
     * @return  object  $this
     */
    public function withSearchString(string $str) : self
    {
        $sql = 'p.name LIKE :str OR c.cat_name LIKE :str OR
            p.short_description LIKE :str OR p.description LIKE :str OR
            p.keywords LIKE :str';
        $this->_qb->andWhere($sql)
           ->setParameter('str', '%' . $str . '%', Database::STRING);
        return $this;
    }


    /**
     * Set the brand ID to limit results.
     *
     * @param   integer $brand_id   Brand ID
     * @return  object  $this
     */
    public function withBrandId(int $brand_id) : self
    {
        $this->_qb->andWhere('p.brand_id = :brand_id')
                  ->setParameter('brand_id', $brand_id, Database::INTEGER);
        return $this;
    }


    /**
     * Set the category to limit results shown.
     *
     * @param   integer $cat_id     Category ID
     * @return  object  $this
     */
    public function withCategoryId(int $cat_id) : self
    {
        $Cat = Category::getInstance($cat_id);
        if ($Cat->getID() > 0 && ($Cat->isNew() || !$Cat->hasAccess())) {
            // Verify category ID is valid and viewer has access
            return $this;
        }

        if ($Cat->getParentID() > 0) {
            $tmp = Category::getTree($Cat->getID());
            $cats = array();
            foreach ($tmp as $xcat_id=>$info) {
                $cats[] = $xcat_id;
            }
            if (!empty($cats)) {
                $this->_qb->andWhere('c.cat_id IN (:cat_ids)')
                          ->setParameter('cat_ids', $cats, Database::PARAM_INT_ARRAY);
            }
        }
        return $this;
    }


    /**
     * Add a field and direction for sorting results.
     *
     * @param   string  $fld    Field name
     * @param   string  $dir    Direction
     * @return  object  $this
     */
    public function withOrderBy(string $fld, string $dir='ASC') : self
    {
        $dir = strtoupper($dir);
        if ($dir != 'ASC') {
            $dir = 'DESC';
        }
        $fld = $tihs->_db->conn->quoteIdentifier($fld);
        $this->qb->addOrderBy($fld, $dir);
        return $this;
    }


    /**
     * Add a "having" clause to the sql.
     *
     * @param   string  $str    Full clause
     * @return  object  $this
     */
    public function withHaving(string $str) : self
    {
        $this->_qb->having($str);
        return $this;
    }


    /**
     * Get an array of product objects.
     *
     * @return  array   Array of Product objects
     */
    public function getObjects() : array
    {
        $Products = array();
        $rows = $this->getRows();
        foreach ($rows as $row) {
            $Products[$row['id']] = Product::getInstance($row);
        }
        return $Products;

        /*$stmt = $this->execute();
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                $Products[] = Product::getInstance($A);
            }
        }
        return $Products;*/
    }

}
