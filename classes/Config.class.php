<?php
/**
 * Class to read and manipulate Shop configuration values.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to get plugin configuration data.
 * @package shop
 */
final class Config
{
    /** Plugin Name.
     */
    public const PI_NAME = 'shop';

    /** Array of config items (name=>val).
     * @var array */
    private $properties = NULL;

    /** Config class singleton instance.
     * @var object */
    static private $instance = NULL;


    /**
     * Get the Shop configuration object.
     * Creates an instance if it doesn't already exist.
     *
     * @return  object      Configuration object
     */
    public static function getInstance() : self
    {
        if (self::$instance === NULL) {
            self::$instance = new self;
        }
        return self::$instance;
    }


    /**
     * Create an instance of the Shop configuration object.
     */
    private function __construct()
    {
        global $_CONF, $_SHOP_CONF;

        $cfg = \config::get_instance();
        $this->properties = $cfg->get_config(self::PI_NAME);

        $this->properties['pi_name'] = self::PI_NAME;
        $this->properties['pi_display_name'] = 'Shop';
        $this->properties['pi_url'] = 'https://www.glfusion.org';
        $this->properties['url'] = $_CONF['site_url'] . '/' . self::PI_NAME;
        $this->properties['admin_url'] = $_CONF['site_admin_url'] . '/plugins/' . self::PI_NAME;
        $this->properties['logfile'] = "{$_CONF['path']}/logs/" . self::PI_NAME . '_downloads.log';
        $this->properties['tmpdir'] = "{$_CONF['path']}/data/" . self::PI_NAME . '/';
        $this->properties['download_path'] = $this->properties['tmpdir'] . 'files/';
        $this->properties['image_dir'] = $this->properties['tmpdir'] . 'images/products';
        $this->properties['catimgpath'] = $this->properties['tmpdir'] . 'images/categories';
        $this->properties['order_tn_size'] = 65;
        $this->properties['buttons'] = array(
            'buy_now'   => 1,   // enabled by default
            'donation'  => 0,   // disabled by default
        );
        $this->properties['datetime_fmt'] = 'Y-m-d H:i:s T';
        $this->properties['path'] = $_CONF['path'] . 'plugins/' . self::PI_NAME . '/';

        $_SHOP_CONF = $this->properties;
    }


    /**
     * Returns a configuration item.
     * Returns all items if `$key` is NULL.
     *
     * @param   string|NULL $key        Name of item to retrieve
     * @param   mixed       $default    Default value if item is not set
     * @return  mixed       Value of config item
     */
    private function _get(?string $key=NULL, $default=NULL)
    {
        if ($key === NULL) {
            return $this->properties;
        } elseif (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
           return $default;
        }
    }


    /**
     * Set a configuration value.
     *
     * @param   string  $key    Configuration item name
     * @param   mixed   $val    Value to set
     * @param   boolean $save   True to save in the DB
     * @return  object  $this
     */
    private function _set(string $key, $val, bool $save=false) : self
    {
        global $_SHOP_CONF;

        $this->properties[$key] = $val;
        $_SHOP_CONF[$key] = $val;
        if ($save) {
            \config::get_instance()->set($key, $val, self::PI_NAME);
        }
        return $this;
    }


    /**
     * Set a configuration value, optionally saving permanently.
     *
     * @param   string  $key    Configuration item name
     * @param   mixed   $val    Value to set, NULL to unset
     * @param   boolean $save   True to save in the DB
     * @return  object  $this
     */
    public static function set(string $key, $val=NULL, bool $save=false) : self
    {
        return self::getInstance()->_set($key, $val, $save);
    }


    /**
     * Delete a configuration value.
     * Always removes the value from the properties and the DB, since there's
     * no need to simply remove from the properties.
     *
     * @param   string  $key    Configuration item name
     * @return  object  $this
     */
    private function _del(string $key) : self
    {
        global $_SHOP_CONF;

        unset($this->properties[$key]);
        unset($_SHOP_CONF[$key]);
        \config::get_instance()->del($key, self::PI_NAME);
        return $this;
    }


    /**
     * Returns a configuration item.
     * Returns all items if `$key` is NULL.
     *
     * @param   string|NULL $key        Name of item to retrieve
     * @param   mixed       $default    Default value if item is not set
     * @return  mixed       Value of config item
     */
    public static function get(?string $key=NULL, $default=NULL)
    {
        return self::getInstance()->_get($key, $default);
    }


    /**
     * Permanently delete a config option from the database.
     */
    public static function del(string $key) : self
    {
        return self::agetInstance()->_del($key);
    }


    /**
     * Convenience function to get the base plugin path.
     *
     * @return  string      Path to main plugin directory.
     */
    public static function path() : string
    {
        return self::_get('path');
    }


    /**
     * Convenience function to get the path to plugin templates.
     *
     * @return  string      Template path
     */
    public static function path_template() : string
    {
        return self::get('path') . 'templates/';
    }

}
