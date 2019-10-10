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
    protected static $pathkey = 'catimgpath';

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
    public function __construct($record_id, $varname='photo')
    {
        global $_SHOP_CONF;

        $this->pathImage = $_SHOP_CONF[self::$pathkey];
        parent::__construct($record_id, $varname);
    }

}

?>
