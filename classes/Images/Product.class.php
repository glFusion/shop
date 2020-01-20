<?php
/**
 * Class to handle product images.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Images;


/**
 * Image-handling class.
 * @package shop
 */
class Product extends \Shop\Image
{
    /** Key into $_SHOP_CONF where the image path can be found.
     * @var string */
    static $pathkey = 'products';

    /** Maximum width, in pixels. Used if no width is given in getImage functions.
     * @var integer */
    static $maxwidth = 800;

    /** Maximum height, in pixels. Used if no width is given in getImage functions.
     * @var integer */
    static $maxheight = 600;


    /**
     * Constructor.
     *
     * @param   integer $record_id Product ID number
     * @param   string  $varname    Name of form field
     */
    /*public function __construct($record_id, $varname='photo')
    {
        global $_SHOP_CONF;

        parent::__construct($record_id, $varname);
    }*/


    /**
     * Perform the file upload.
     * Calls the parent function to upload the files, then calls
     * MakeThumbs() to create thumbnails.
     *
     * @return  array   Array of filenames
     */
    public function uploadFiles()
    {
        global $_TABLES;

        // Perform the actual upload
        parent::uploadFiles();

        // Seed image cache with thumbnails
        $this->MakeThumbs();
        $filenames = array();
        foreach ($this->goodfiles as $filename) {
            $parts = pathinfo($filename);
            $basename = $parts['basename'];
            $sql = "INSERT INTO {$_TABLES['shop.images']} SET
                product_id = '{$this->record_id}',
                nonce = '" . DB_escapeString($this->nonce) . "',
                filename = '" . DB_escapeString($basename) . "'";
            SHOP_log($sql, SHOP_LOG_DEBUG);
            $result = DB_query($sql);
            if (!$result) {
                $this->_addError("uploadFiles() : Failed to insert {$filename}");
            } else {
                $filenames[DB_insertID()] = $filename;
            }
        }
        self::reOrder($this->record_id);
        return $filenames;
    }


    /**
     * Delete a product image from disk and the table.
     * Intended to be called from ajax.php.
     *
     * @param   integer $img_id     Image database ID
     * @return  boolean     True if image is deleted, False if not
     */
    public static function DeleteImage($img_id)
    {
        global $_TABLES, $_SHOP_CONF;

        $img_id = (int)$img_id;
        if ($img_id < 1) {
            return false;
        }
        $filename = DB_getItem(
            $_TABLES['shop.images'],
            'filename',
            "img_id = '$img_id'"
        );
        if (empty($filename)) {
            return false;
        }
        $img_file = $_SHOP_CONF['image_dir'] . '/' . $filename;
        if (is_file($img_file)) {
            @unlink($img_file);
        }
        DB_delete($_TABLES['shop.images'], 'img_id', $img_id);
        \Shop\Cache::clear('products');
        return true;
    }


    /**
     * Sets the default image for a product.
     *
     * @param   integer $img_id     Image record ID
     * @param   integer $prod_id    Product record ID
     * @return  boolean     True on success, False on error
     */
    public static function setAsDefault($img_id, $prod_id)
    {
        global $_TABLES;

        $img_id = (int)$img_id;
        $prod_id = (int)$prod_id;
        \Shop\Cache::clear('products');
        $sql = "UPDATE {$_TABLES['shop.images']}
            SET orderby = 5
            WHERE product_id = $prod_id AND img_id = $img_id";
        DB_query($sql);
        if (DB_error()) {
            return false;
        }
        self::reOrder($prod_id);
        return true;
    }


    /**
     * Reorder images for a product.
     *
     * @param   integer $prod_id    Product record ID
     */
    public static function reOrder($prod_id)
    {
        global $_TABLES;

        $prod_id = (int)$prod_id;
        $sql = "SELECT img_id, orderby
                FROM {$_TABLES['shop.images']}
                WHERE product_id = $prod_id
                ORDER BY orderby, img_id ASC";
        $result = DB_query($sql);

        $order = 10;        // First orderby value
        $stepNumber = 10;   // Increment amount
        $changed = false;   // Assume no changes
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $changed = true;
                $sql = "UPDATE {$_TABLES['shop.images']}
                    SET orderby = '$order'
                    WHERE img_id = '{$A['img_id']}'";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
        if ($changed) {
            \Shop\Cache::clear('products');
        }
    }


    /**
     * Sort image IDs in the order provided.
     *
     * @param   string  $img_ids    Comma-separated list of IDs, in order
     */
    public static function updateOrder($img_ids)
    {
        global $_TABLES;

        $ids = explode(',', $img_ids);
        $orderby = 10;
        foreach ($ids as $id) {
            $id = (int)$id;     // sanitize
            $sql = "UPDATE {$_TABLES['shop.images']}
                SET orderby = $orderby
                WHERE img_id = $id";
            DB_query($sql, 1);
            $orderby += 10;
        }
    }


    /**
     * Update the image record with the product ID.
     * Used where the ID of a new product is not known until saving
     * so images are identified by a nonce value.
     *
     * @param   string  $nonce      Nonce used to identify images
     * @param   integer $item_id    New product ID
     */
    public static function setProductID($nonce, $item_id)
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        $nonce = DB_escapeString($nonce);
        $sql = "UPDATE {$_TABLES['shop.images']}
            SET product_id = '$item_id'
            WHERE nonce = '$nonce'";
        //COM_errorLog($sql);
        DB_query($sql);
    }


    /**
     * Remove images that haven't been assigned to a product.
     * This happens when images ar uploaded via drag-and-drop but the product
     * is not created.
     */
    public static function cleanUnassigned()
    {
        global $_TABLES;

        $sql = "SELECT img_id FROM {$_TABLES['shop.images']}
            WHERE product_id = 0 AND last_update < DATE_SUB(NOW(), INTERVAL 90 minute)";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            self::DeleteImage($A['img_id']);
        }
    }

}

?>
