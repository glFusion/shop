<?php
/**
 * Class to handle logo images for gateways.
 * These are the basically the same functions as found in lib-image.php,
 * but force the use of the GD library to handle transparent PNG.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 *  Image-handling class
 */
class Logo
{
    /** Target image width.
     * @var integer */
    private $d_width = 0;

    /** Target image height.
     * @var integer */
    private $d_height = 0;

    /** Image MIME type.
     * @var string */
    private $mime_type = '';

    /** Source image width.
     * @var integer */
    private $s_width = 0;

    /** Source image height.
     * @var integer */
    private $s_height = 0;

    /** Target image filename.
     * @var string */
    private $d_filename = '';

    /** Full filespec for source image.
     * @var string */
    private $s_path = '';

    /** Target image directory.
     * @var string */
    private $d_path = '';

    /** Source image path components.
     * @var array */
    private $s_pinfo = array(
        'dirname' => '',
        'basename' => '',
        'extension' => '',
        'filename' => '',
    );

    /** Status flag, assume success.
     * @var boolean */
    private $status = true;


    /**
     * Set the path without filename for the target image.
     *
     * @param   string  $path   Directory name
     * @return  object  $this
     */
    public function withDestPath($path)
    {
        $this->d_path = $path;
        return $this;
    }


    /**
     * Set the full filespec to the source image.
     *
     * @param   string  $img_path   Full directory/filename for the source
     * @return  object  $this
     */
    public function withImage($img_path)
    {
        $this->s_path = $img_path;
        $this->s_pinfo = pathinfo($img_path);
        return $this;
    }


    /**
     * Set the target image filename, no path.
     *
     * @param   string  $filenam    Target filename
     * @return  object  $this
     */
    public function withDestFilename($filename)
    {
        $this->d_filename = $filename;
        return $this;
    }


    /**
     *  Set the target image width in pixels.
     *
     *  @param  integer $width  Target image width
     * @return  object  $this
     */
    public function withDestWidth($width)
    {
        $this->d_width = (int)$width;
        return $this;
    }


    /**
     * Get the actual image width calculated during resizing.
     *
     * @return  integer     Target image width in pixels
     */
    public function getDestWidth()
    {
        return (int)$this->d_width;
    }


    /**
     * Set the desired targe image height, in pixels.
     *
     * @param   integer $height     Target height
     * @return  object  $this
     */
    public function withDestHeight($height)
    {
        $this->d_height = (int)$height;
        return $this;
    }


    /**
     * Get the actual target image height calculated during resizing.
     *
     * @return  integer     Actual target image height in pixels
     */
    public function getDestHeight()
    {
        return (int)$this->d_height;
    }


    /**
     * Check the last operation status.
     *
     * @return  boolean     True on success, False on error
     */
    public function isValid()
    {
        return $this->status;
    }


    /**
     * Get the target image filename.
     *
     * @return  string      Target filename
     */
    public function getFilename()
    {
        return $this->d_filename;
    }


    /**
     * Get the target image path components.
     *
     * @return  array   Target image pathinfo() result
     */
    public function getDestPathifo()
    {
        return pathinfo($this->d_path . '/' . $this->d_filename);
    }


    /**
     * Calculate dimensions to resize an image, preserving the aspect ratio.
     *
     * @param   string  $origpath   Original image path
     * @param   integer $width      New width, in pixels
     * @param   integer $height     New height, in pixels
     * @param   boolean $expand     True to allow expanding the image
     * @return  mixed       array of dimensions, or false on error
     */
    public function reDim($width=0, $height=0, $expand=false)
    {
        $dimensions = @getimagesize($this->s_path);
        if ($dimensions === false) {
            $this->status = false;
            return $this;
        }
        $this->s_width = $dimensions[0];
        $this->s_height = $dimensions[1];
        $this->mime_type = $dimensions['mime'];

        // get both sizefactors that would resize one dimension correctly
        if ($width > 0 && ($this->s_width > $width || $expand)) {
            $sizefactor_w = (double)($width / $this->s_width);
        } else {
            $sizefactor_w = 1;
        }
        if ($height > 0 && ($this->s_height > $height || $expand)) {
            $sizefactor_h = (double)($height / $this->s_height);
        }else {
            $sizefactor_h = 1;
        }

        // Use the smaller factor to stay within the parameters
        $sizefactor = min($sizefactor_w, $sizefactor_h);

        $this->d_width = (int)($this->s_width * $sizefactor);
        $this->d_height = (int)($this->s_height * $sizefactor);

        /*return array(
            's_width'   => $s_width,
            's_height'  => $s_height,
            'd_width'   => $newwidth,
            'd_height'  => $newheight,
            'mime'      => $mime_type,
        );*/
        return $this;
    }


    /**
     * Resize an image to the specified dimensions into the new location.
     * At least one of $newWidth or $newHeight must be specified.
     *
     * @param   string  $type       Either 'thumb' or 'disp'
     * @param   integer $newWidth   New width, in pixels
     * @param   integer $newHeight  New height, in pixels
     * @param   boolean $expand     True to allow expanding the image
     * @return  mixed   Array of new width,height if successful, false if failed
     */
    public function reSize($newWidth=0, $newHeight=0, $expand=false)
    {
        global $_CONF;

        if (empty($this->s_path) || empty($this->d_path)) {
            return false;
        }

        if ($newWidth > 0) {
            $this->d_width = (int)$newWidth;
        }
        if ($newHeight > 0) {
            $this->d_height = (int)$newHeight;
        }

        // Calculate the new dimensions
        self::reDim($this->d_width, $this->d_height, $expand);
        if ($this->status == false) {
            SHOP_log(__CLASS__ . ": Invalid image {$this->src_path}");
            return false;
        }

        $this->d_filename = $this->s_pinfo['filename'] .
            '_' . $this->d_width .
            '_' . $this->d_height .
            '.' . $this->s_pinfo['extension'];
        $fullpath = $this->d_path . '/' . $this->d_filename;

        // If the file already exists, just return the current info.
        if (is_file($this->d_path . '/' . $this->d_filename)) {
            return $this;
        }
        $JpegQuality = 85;

        if ($_CONF['debug_image_upload']) {
            SHOP_log(
                __CLASS__ . '::' . __FUNCTION__ . ': ' .
                ": Resizing using GD2: Src = " . $this->s_path . " mimetype = " . $this->mime_type
            );
        }
        switch ($this->mime_type) {
        case 'image/jpeg' :
        case 'image/jpg' :
            $image = @imagecreatefromjpeg($this->s_path);
            break;
        case 'image/png' :
            $image = @imagecreatefrompng($this->s_path);
            break;
        case 'image/bmp' :
            $image = @imagecreatefromwbmp($this->s_path);
            break;
        case 'image/gif' :
            $image = @imagecreatefromgif($this->s_path);
            break;
        case 'image/x-targa' :
        case 'image/tga' :
            SHOP_log("IMG_resizeImage: TGA files not supported by GD2 Libs");
            return false;
        default :
            SHOP_log("IMG_resizeImage: GD2 only supports JPG, PNG and GIF image types.");
            return false;
        }

        if (!$image) {
            SHOP_log("IMG_resizeImage: GD Libs failed to create working image.");
            return false;
        }
        if (
            !$expand &&
            $this->d_height > $this->s_height &&
            $this->d_width > $this->s_width
        ) {
            $this->d_width = $this->s_width;
            $this->d_height = $this->s_height;
        }

        $newimage = imagecreatetruecolor($this->d_width, $this->d_height);
        imagealphablending($newimage, false);
        imagesavealpha($newimage, true);
        imagecopyresampled(
            $newimage, $image,
            0, 0,
            0, 0,
            $this->d_width, $this->d_height,
            $this->s_width, $this->s_height
        );

        switch ($this->mime_type) {
        case 'image/jpeg' :
        case 'image/jpg' :
            imagejpeg($newimage, $fullpath, $JpegQuality);
            break;
        case 'image/png' :
            $pngQuality = ceil(intval(($JpegQuality / 100) + 8));
            imagepng($newimage, $fullpath, $pngQuality);
            break;
        case 'image/bmp' :
            imagewbmp($newimage, $fullpath);
            break;
        case 'image/gif' :
            imagegif($newimage, $fullpath);
            break;
        }
        imagedestroy($newimage);
        return $this;
    }

}
