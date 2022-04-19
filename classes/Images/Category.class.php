<?php
/**
 * Class to handle category images.
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
class Category extends \Shop\Image
{
    /** Key into $_SHOP_CONF where the image path can be found.
     * @var string */
    protected static $pathkey = 'categories';

    /** Maximum width, in pixels. Used if no width is given in getImage functions.
     * @var integer */
    protected static $maxwidth = 300;

    /** Maximum height, in pixels. Used if no width is given in getImage functions.
     * @var integer */
    protected static $maxheight = 300;


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
            if ($this->record_id > 0) {     // existing category
                $sql = "UPDATE {$_TABLES['shop.categories']}
                    SET image = '" . DB_escapeString($basename) . "'
                    WHERE cat_id = '{$this->record_id}'";
                $result = DB_query($sql);
                if (!$result) {
                    $this->_addError("Category::uploadFiles() : Failed to upload {$basename}");
                } else {
                    $filenames[] = $basename;;
                }
            } else {
                // For a new record, just add the filename.
                // The DB will be updated when the record is saved.
                $filenames[] = $basename;
            }
        }
        return $filenames;
    }


    /**
     * Delete a category image from disk and the table.
     * Intended to be called from ajax.php.
     *
     * @param   integer $cat_id     Category ID
     * @param   string  $nonce      Nonce, used if $cat_id is zero
     * @return  boolean     True if image is deleted, False if not
     */
    public static function DeleteImage($cat_id, $nonce)
    {
        global $_SHOP_CONF;

        $cat_id = (int)$cat_id;
        if ($cat_id > 0) {
            $Cat = \Shop\Category::getInstance($cat_id);
            if (!$Cat->isNew) {
                $Cat->deleteImage();
            }
        }
        return true;
    }


    /**
     * Remove images that haven't been assigned to a category.
     * This happens when images ar uploaded via drag-and-drop but the category
     * is not actually created.
     */
    public static function cleanUnassigned()
    {
        global $_TABLES, $_SHOP_CONF;

        $sys_files = array();   // images on the filesystem
        $db_files = array();    // images in the database

        // Get all the image files on the filesystem
        $files = glob($_SHOP_CONF['catimgpath'] . '/*');
        foreach ($files as $file) {
            $fname = basename($file);
            if ($fname == 'index.html') continue;
            $sys_files[] = $fname;
        }

        // Get all the images in the database
        $files = DB_fetchAll(
            DB_query(
                "SELECT image FROM {$_TABLES['shop.categories']}
                WHERE image <> ''"
            ),
            false
        );
        foreach ($files as $key=>$data) {
            $db_files[] = $data['image'];
        }

        foreach(array_diff($sys_files, $db_files) as $filename) {
            // Try to remove the file, not a big deal if it fails
            @unlink($_SHOP_CONF['catimgpath'] . '/' . $filename);
        }
    }

}

?>
