<?php
/**
 * Class to handle payment gateway collections.
 * Used to get specific sub-categories of gateways.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2023 Lee Garner
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Collections;
use glFusion\Database\Database;
use Shop\Models\ProductType;
use Shop\GatewayManager;


/**
 * Class to display the product catalog.
 * @package shop
 */
class GatewayCollection extends Collection
{

    public function __construct()
    {
        global $_TABLES, $_CONF;

        parent::__construct();

        $this->addCacheTags(array('gateways'));
        $this->_qb->select('*')
                  ->from($_TABLES['shop.gateways']);
    }


    /**
     * Specify whether to get enabled or disabled gateways.
     *
     * @param   boolean $enabled    True for enabled, False for disabled.
     * @return  object  $this
     */
    public function withEnabled(bool $enabled=true) : self
    {
        $val = $enabled ? 1 : 0;
        $this->_qb->andWhere('enabled = :enabled')
                  ->setParameter('enabled', $val, Database::INTEGER);
        return $this;
    }


    /**
     * Get only bundled gateways.
     *
     * @param   boolean $bundled    True for bundled, False for 3rd-party
     * @return  object  $this
     */
    public function withBundled(bool $bundled=true) : self
    {
        if ($bundled) {
            $this->_qb->andWhere('id IN (:bundled)');
        } else {
            $this->_qb->andWhere('id NOT IN (:bundled)');
        }
        $this->_qb->setParameter('bundled', GatewayManager::$_bundledGateways, Database::PARAM_STR_ARRAY);
        return $this;
    }


    /**
     * Get an array of gateway objects.
     *
     * @return  array   Array of Gateway objects
     */
    public function getObjects() : array
    {
        $gateways = array();
        $rows = $this->getRows();
        foreach ($rows as $row) {
            $cls = 'Shop\\Gateways\\' . $row['id'] . '\\Gateway';
            if (class_exists($cls)) {
                $gw = new $cls($row);
            } else {
                $gw = NULL;
            }
            if (is_object($gw)) {
                $gateways[$row['id']] = $gw;
            } else {
                continue;       // Gateway found but not installed
            }
        }
        return $gateways;
    }

}
