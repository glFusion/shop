<?php
/**
 * Class to handle category images.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
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
    /** Key into the configuration where the image path can be found.
     * @var string */
    protected static $pathkey = 'brands';

    /** Maximum width, in pixels. Used if no width is given in getImage functions.
     * @var integer */
    protected static $maxwidth = 300;

    /** Maximum height, in pixels. Used if no width is given in getImage functions.
     * @var integer */
    protected static $maxheight = 300;


    /**
     * Delete a supplier logo image from disk and the table.
     * Intended to be called from ajax.php.
     *
     * @param   integer $rec_id     Record ID
     * @param   string  $nonce      Nonce, not used here
     * @return  boolean     True if image is deleted, False if not
     */
    public static function DeleteImage($rec_id, $nonce)
    {
        $Supplier = Supplier::getInstance($rec_id);
        if ($Supplier->getID()) {
            @unlink(Config::get('tmpdir') . "/images/brands/" . $Supplier->getImageName());
            $Supplier->setImageName('')->Save();
        }
        return true;
    }

}
