<?php
/**
 * Base Class to handle images for products and categories
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
namespace Shop;


/**
 * Image-handling class.
 * @package shop
 */
class Image extends UploadDownload
{
    /** Path to actual image (without filename).
     * @var string */
    protected $pathImage;

    /** ID of the current Category or Product.
     * @var string */
    protected $record_id;

    /** Array of the names of successfully uploaded files.
     * @var array */
    protected $goodfiles = array();

    /** Random nonce value, used to identify images uploaded before the item is created.
     * @var string */
    protected $nonce = '';


    /**
     * Constructor.
     * Sets various elements from the base `upload` class.
     *
     * @param   integer $record_id Product ID number
     * @param   string  $varname    Name of form field
     */
    public function __construct($record_id, $varname='photo')
    {
        $this->record_id = trim($record_id);
        $this->setFieldName($varname);
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
        global $_CONF;

        // Before anything else, check the upload directory
        if (!$this->setPath($this->pathImage)) {
            return;
        }
        $this->setContinueOnError(true);
        $this->setLogFile($_CONF['path'] . 'logs/error.log');
        $this->setDebug(true);
        // Only images are allowed
        $this->setAllowedMimeTypes(array(
            'image/gif'     => array('gif'),
            'image/pjpeg'   => array('jpg','jpeg'),
            'image/jpeg'    => array('jpg','jpeg'),
            'image/png'     => array('png'),
            'image/x-png'   => array('png'),
        ));
        // Allow any size image
        $this->setMaxDimensions(0, 0);

        $filenames = array();
        for ($i = 0; $i < $this->numFiles(); $i++) {
            $filenames[] =  uniqid($this->record_id . '_' . rand(100,999)) . '.jpg';
        }
        $this->setFileNames($filenames);

        // Perform the actual upload
        parent::uploadFiles();
    }


    /**
    * Seed the image cache with the product image thumbnails.
    *
    * @uses     LGLIB_ImageUrl()
    * @return   string      Blank, error messages are now in parent::_errors
    */
    protected function MakeThumbs()
    {
        global $_SHOP_CONF;

        $thumbsize = (int)$_SHOP_CONF['max_thumb_size'];
        if ($thumbsize < 50) $thumbsize = 100;

        if (!is_array($this->getFilenames())) {
            return '';
        }

        foreach ($this->getFileNames() as $filename) {
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
    protected function _deleteOneImage($imgpath)
    {
        if (file_exists($imgpath . '/' . $this->filename))
            unlink($imgpath . '/' . $this->filename);
    }


    /**
     * Set the internal property value for a nonce.
     *
     * @param   string  $nonce  Nonce value to set
     */
    public function setNonce($nonce)
    {
        $this->nonce = $nonce;
    }


    /**
     * Create a unique key based on some string.
     *
     * @param   string  $str    Base string
     * @return  string  Nonce string
     */
    public static function makeNonce($str='')
    {
        return uniqid() . rand(100,999);
    }


    /**
     * Get the image information for a thumbnail image.
     *
     * @uses    self::getUrl()
     * @param   string  $filename   Image filename
     * @return  array       Array of (url, width, height)
     */
    public static function getThumbUrl($filename)
    {
        global $_SHOP_CONF;

        return self::getUrl($filename, $_SHOP_CONF['max_thumb_size']);
    }


    /**
     * Static function to get an image URL from a filename.
     * Also returns width and height for use in the image tag.
     *
     * @param   string  $filename   Image filename
     * @param   integer $width      Desired display width
     * @param   integer $height     Desired display height
     * @return  array       Array of (url, width, height)
     */
    public static function getUrl($filename, $width=0, $height=0)
    {
        global $_SHOP_CONF;

        // If the filename is empty, return nothing.
        if ($filename == '') {
            return array(
                'url'   => '',
                'width' => 0,
                'height' => 0,
            );
        }
        if ($width == 0 && $height == 0) {
            // Default to a standard display size if no sizes given
            $width = static::$maxwidth;
            $height = static::$maxheight;
        } elseif ($width > 0 && $height == 0) {
            // default to square if one size given
            $height = $width;
        }
        $args = array(
            'filepath'  => $_SHOP_CONF[static::$pathkey] . DIRECTORY_SEPARATOR . $filename,
            'width'     => $width,
            'height'    => $height,
        );
        $status = LGLIB_invokeService('lglib', 'imageurl', $args, $output, $svc_msg);
        return $output;
    }

}

?>
