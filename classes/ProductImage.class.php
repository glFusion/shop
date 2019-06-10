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
     */
    public function uploadFiles()
    {
        global $_TABLES;

        // Perform the actual upload
        parent::uploadFiles();

        // Seed image cache with thumbnails
        $this->MakeThumbs();

        foreach ($this->goodfiles as $filename) {
            $sql = "INSERT INTO {$_TABLES['shop.images']}
                    (product_id, filename)
                VALUES (
                    '{$this->product_id}', '".
                    DB_escapeString($filename)."'
                )";
            $result = DB_query($sql);
            if (!$result) {
                $this->_addError("uploadFiles() : Failed to insert {$filename}");
            }
        }
 
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

}   // class ProductImage

?>
