<?php
/**
 * Class to handle file uploads for downloadable products.
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


/**
 * File upload and download class.
 * This is for uploading and downloading data files to be sold.
 * @package shop
 */
class File extends UploadDownload
{
    /** Array of uploaded filenames.
     * @var array */
    private $filenames;

    /** Array of the names of successfully uploaded files.
     * @var array */
    private $goodfiles = array();

    /**
     * Constructor.
     * @param   string  $varname        Optional form variable name
     */
    function __construct($varname='uploadfile')
    {
        global $_SHOP_CONF, $_CONF;

        parent::__construct();
        $this->filenames = array();
        $this->setContinueOnError(true);
        $this->setLogFile('/tmp/warn.log');
        $this->setDebug(true);

        // Before anything else, check the upload directory
        if (!$this->setPath($_SHOP_CONF['download_path'])) {
            return;
        }

        // For now, this is ok.  Later maybe duplicate the $_SHOP_CONF array
        // for downloaded mime-types.  For some reason, upload.class.php and
        // download.class.php have their array key=>values reversed.
        $this->setAllowAnyMimeType(true);
        //$this->setAllowedMimeTypes();

        // Max size for uploads?  This is only accessible to admins anyway.
        $this->setMaxFileSize((int)$_SHOP_CONF['max_file_size'] * 1048576);

        // Set the name of the form variable used.
        $this->setFieldName($varname);

        $this->filenames[] = $_FILES[$varname]['name'];
        $this->setFileNames($this->filenames);
    }


    /**
     * Actually handle the file upload.
     * Currently, all this does is return the filename so it can be
     * updated in the product record.
     *
     * @return  string      Name of uploaded file
     */
    function uploadFiles()
    {
        parent::uploadFiles();
        return $this->filenames[0];
    }


    /**
     * Delete this image using the current name and supplied path.
     */
    public function Delete()
    {
        if (file_exists($this->getPath() . '/' . $this->filename))
            unlink($this->getPath() . '/' . $this->filename);
    }

}   // class File

?>
