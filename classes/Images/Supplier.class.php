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
class Supplier extends \Shop\Image
{
    /** Key into $_SHOP_CONF where the image path can be found.
     * @var string */
    protected static $pathkey = 'brands';

    /** Maximum width, in pixels. Used if no width is given in getImage functions.
     * @var integer */
    protected static $maxwidth = 300;

    /** Maximum height, in pixels. Used if no width is given in getImage functions.
     * @var integer */
    protected static $maxheight = 300;


    /**
     * Constructor.
     *
     * @param   integer $record_id Product ID number
     * @param   string  $varname    Name of form field
     */
/*    public function __construct($record_id, $varname='logofile')
    {
        global $_SHOP_CONF;

        $this->pathImage = "{$_SHOP_CONF['tmpdir']}images/brands";
        parent::__construct($record_id, $varname);
    }
 */

    /**
     * Create the target filename for the image file.
     * Suppliers/Brands simply names the image for the record ID.
     *
     * @return  string      File name
     */
    protected function makeFileName()
    {
        return $this->record_id . '.jpg';
    }


    /**
     * Delete a category image from disk and the table.
     * Intended to be called from ajax.php.
     *
     * @param   integer $rec_id     Record ID
     * @param   string  $nonce      Nonce, not used here
     * @return  boolean     True if image is deleted, False if not
     */
    public static function DeleteImage($rec_id, $nonce)
    {
        $rec_id = (int)$rec_id;
        @unlink("{$_SHOP_CONF['tmpdir']}images/brands/{$rec_id}.jpg");
        return true;
    }

}

?>
