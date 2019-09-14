<?php
/**
 * Class to handle images.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

// Import core glFusion upload functions
//USES_class_upload();

/**
 * Image-handling class.
 * @package shop
 */
class ProductImage extends \upload
{
    /** Path to actual image (without filename).
     * @var string */
    private $pathImage;

    /** ID of the current product.
     * @var string */
    private $product_id;

    /** Array of the names of successfully uploaded files.
     * @var array */
    private $goodfiles = array();


    private $_nonce = '';

    /**
     * Constructor.
     *
     * @param   integer $product_id Product ID number
     * @param   string  $varname    Name of form field
     */
    public function __construct($product_id, $varname='photo')
    {
        global $_SHOP_CONF, $_CONF;

        $this->setContinueOnError(true);
        $this->setLogFile('/tmp/warn.log');
        $this->setDebug(true);
        $this->_setAvailableMimeTypes();

        // Before anything else, check the upload directory
        if (!$this->setPath($_SHOP_CONF['image_dir'])) {
            return;
        }
        $this->product_id = trim($product_id);
        $this->pathImage = $_SHOP_CONF['image_dir'];
        $this->setAllowedMimeTypes(array(
            'image/pjpeg' => '.jpg,.jpeg',
            'image/jpeg'  => '.jpg,.jpeg',
        ));
        $this->setMaxDimensions(0, 0);
        $this->setFieldName($varname);

        $filenames = array();
        for ($i = 0; $i < $this->numFiles(); $i++) {
            $filenames[] =  uniqid($this->product_id . '_' . rand(100,999)) . '.jpg';
        }
        $this->setFileNames($filenames);
    }


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
            $sql = "INSERT INTO {$_TABLES['shop.images']} SET
                product_id = '{$this->product_id}',
                nonce = '" . DB_escapeString($this->_nonce) . "',
                filename = '" . DB_escapeString($filename) . "'";
            SHOP_log($sql, SHOP_LOG_DEBUG);
            $result = DB_query($sql);
            if (!$result) {
                $this->_addError("uploadFiles() : Failed to insert {$filename}");
            } else {
                $filenames[DB_insertID()] = $filename;
            }
        }
        return $filenames;
    }


    /**
    * Seed the image cache with the product image thumbnails.
    *
    * @uses     LGLIB_ImageUrl()
    * @return   string      Blank, error messages are now in parent::_errors
    */
    private function MakeThumbs()
    {
        global $_SHOP_CONF;

        $thumbsize = (int)$_SHOP_CONF['max_thumb_size'];
        if ($thumbsize < 50) $thumbsize = 100;

        if (!is_array($this->_fileNames))
            return '';

        foreach ($this->_fileNames as $filename) {
            $src = "{$this->pathImage}/{$filename}";
            $url = LGLIB_ImageUrl($src, $thumbsize, $thumbsize, true);
            if (!empty($url)) {
                $this->goodfiles[] = $filename;
            }
        }
        return '';
    }   // function MakeThumbs()


    /**
     * Delete an image from disk.
     * Called by Entry::Delete if disk deletion is requested.
     */
    public function Delete()
    {
        // If we're deleting from disk also, get the filename and
        // delete it and its thumbnail from disk.
        if ($this->filename == '') {
            return;
        }
        $this->_deleteOneImage($this->pathImage);
    }


    /**
     * Delete a single image using the current name and supplied path.
     *
     * @param   string  $imgpath    Path to file
     */
    private function _deleteOneImage($imgpath)
    {
        if (file_exists($imgpath . '/' . $this->filename))
            unlink($imgpath . '/' . $this->filename);
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
        Cache::clear('products');
        return true;
    }


    /**
     * Set the internal property value for a nonce.
     *
     * @param   string  $nonce  Nonce value to set
     */
    public function setNonce($nonce)
    {
        $this->_nonce = $nonce;
    }


    /**
     * Create a unique key based on some string.
     *
     * @param   string  $str    Base string
     * @return  string  Nonce string
     */
    public static function makeNonce($str='')
    {
        return uniqid();
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
        DB_query("UPDATE {$_TABLES['shop.images']}
            SET product_id = '$item_id'
            WHERE nonce = '$nonce'");
    }

}   // class ProductImage

?>
