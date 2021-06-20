<?php
/**
 * Handle the headline autotag for the Shop plugin.
 * Based on the glFusion headline autotag.
 *
 * @copyright   Copyright (c) 2009-2020 Lee Garner
 * @package     shop
 * @version     v1.1.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Autotags;
use Shop\Template;
use Shop\Cache;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}

/**
 * Headline autotag class.
 * @package shop
 */
class headlines
{
    /**
     * Parse the autotag and render the output.
     *
     * @param   string  $p1         First option after the tag name
     * @param   string  $opts       Name=>Vaue array of other options
     * @param   string  $fulltag    Full autotag string
     * @return  string      Replacement HTML, if applicable.
     */
    public function parse($p1, $opts=array(), $fulltag='')
    {
        global $_CONF, $_TABLES, $_USER, $LANG01;

        // display = how many stories to display, if 0, then all
        // meta = show meta data (i.e.; who when etc)
        // titleLink - make title a hot link
        // featured - 0 = show all, 1 = only featured, 2 = all except featured
        // frontpage - 1 = show only items marked for frontpage - 0 = show all
        // cols - number of columns to show
        // sort - sort by date, views, rating, featured (implies date)
        // order - desc, asc
        // template - the template name

        $cacheID = md5($p1 . $fulltag);
        $retval = Cache::get($cacheID);
        $retval = NULL;
        if ($retval !== NULL) {
            return $retval;
        } else {
            $retval = '';
        }

        $display    = 10;       // display 10 articles
        $featured   = 0;        // 0 = show all, 1 = only featured
        $cols       = 3;        // number of columns
        $truncate   = 0;        // maximum number of characters to include in story text
        $sortby     = 'rating'; // sort by: date, views, rating, featured
        $orderby    = 'desc';   // order by - desc or asc
        $autoplay   = 'true';
        $interval   = 7000;
        $template   = 'headlines.thtml';
        $category   = 0;

        $valid_sortby = array('dt_add','views','rating','featured');
        foreach ($opts as $key=>$val) {
            $val = strtolower($val);
            switch ($key) {
            case 'sortby':
                // Make sure the selected sortby value is valid
                if (in_array($val, $valid_sortby)) {
                    $sortby = $val;
                } else {
                    $sortby = 'featured';
                }
                break;
            case 'orderby':
                $valid_order = array('desc','asc');
                if (in_array($val, $valid_order)) {
                    $orderby = $val;
                } else {
                    $orderby = 'desc';
                }
                break;
            case 'featured':
                $$key = $val == 1 ? 1 : 0;
                break;
            case 'autoplay':
                $autoplay = $val == 'true' ? 'true' : 'false';
                break;
            case 'display':
            case 'cols':
            case 'interval':
            case 'category':
                $$key = (int)$val;
                break;
            default:
                $$key = $val;
                break;
            }
        }

        $where = '';
        if ($display != 0) {
            $limit = " LIMIT $display";
        } else {
            $limit = '';
        }
        if ($featured == 1) {
            $where .= ' AND featured = 1';
        }
        if ($category > 0) {
            $objects = \Shop\Category::getTree($category);
            foreach ($objects as $Obj) {
                $cats[] = $Obj->getID();
            }
            if (!empty($cats)) {
                $cats = DB_escapeString(implode(',', $cats));
                $where .= ' AND c.cat_id IN (' . $cats . ')';
            }
        }

        $today = $_CONF['_now']->format('Y-m-d');

        // The "c.enabled IS NULL" is to allow products which have
        // no category record, as long as the product is enabled.
        $sql = "SELECT p.id, p.track_onhand, p.oversell, (
                SELECT sum(stk.qty_onhand) FROM {$_TABLES['shop.stock']} stk
                WHERE stk.item_id = p.id
            ) as qty_onhand
            FROM {$_TABLES['shop.products']} p
            LEFT JOIN {$_TABLES['shop.prodXcat']} pxc
                ON p.id = pxc.product_id
            LEFT JOIN {$_TABLES['shop.categories']} c
                ON pxc.cat_id=c.cat_id
            WHERE
                p.enabled=1 AND
                (c.enabled=1 OR c.enabled IS NULL) AND
                p.avail_beg <= '$today' AND
                p.avail_end >= '$today'
                $where " . //AND " .
                SEC_buildAccessSql('AND', 'c.grp_access') . "
            GROUP BY p.id
            HAVING p.track_onhand = 0 OR p.oversell < 2 OR qty_onhand > 0
            ORDER BY $sortby $orderby
            $limit";
                //"(p.track_onhand = 0 OR qty_onhand > 0 OR p.oversell < 2) " .
        //echo $sql;die;
        $res = DB_query($sql);
        $allItems = DB_fetchAll($res, false);
        $numRows = @count($allItems);

        if ($numRows < $cols) {
            $cols = $numRows;
        }
        if ($cols > 6) {
            $cols = 6;
        }

        if ($numRows > 0) {
            $T = new Template('autotags');
            $T->set_file('page', $template);
            $T->set_var('columns' ,$cols);
            $T->set_block('page', 'headlines', 'hl');
            foreach ($allItems as $A) {
                $P = \Shop\Product::getByID($A['id']);
                $tn = $P->getThumb();
                $image = COM_createImage(
                    $tn['url'],
                    '',
                    array(
                        'width' => $tn['width'],
                        'height' => $tn['height'],
                    )
                );
                $T->set_var(array(
                    'url'       => $P->getLink(),
                    'text'      => $P->getText(),
                    'title'     => $P->getDscp(),
                    'thumb_url' => $image,
                    'tn_url'    => $tn['url'],
                    'tn_width'  => $tn['width'],
                    'tn_height' => $tn['height'],
                    'large_url' => $P->getImage('', 1024, 1024)['url'],
                    'autoplay'  => $autoplay,
                    'autoplay_interval' => $interval,
                ) );
                $T->parse('hl', 'headlines', true);
            }
            $retval = $T->finish($T->parse('output', 'page'));
            Cache::set($cacheID, $retval, array('products', 'categories'));
        }
        return $retval;
    }

}

?>
