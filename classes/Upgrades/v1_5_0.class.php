<?php
/**
 * Upgrade to version 1.5.0.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Upgrades;
use Shop\Config;
use Shop\OrderItem;
use Shop\ProductVariant;
use glFusion\Database\Database;
use glFusion\Log\Log;


class v1_5_0 extends Upgrade
{
    private static $ver = '1.5.0';

    public static function upgrade()
    {
        global $_TABLES, $SHOP_UPGRADE;

        $db = Database::getInstance();
        $admin_email = Config::get('admin_email_addr');
        $shop_email = Config::get('shop_email');
        if (empty($shop_email) && !empty($admin_email)) {
            $c = \config::get_instance();
            $c->set('shop_email', $admin_mail, Config::PI_NAME);
        }

        // Update the payment ref_id column in the IPN log.
        $upd_ref_id = !self::tableHasColumn('shop.ipnlog', 'ref_id');
        if ($upd_ref_id) {
            // For gateways that have only one webhook sent, do it the easy way.
            // Update the more complicated gateways after the column is added.
            $SHOP_UPGRADE[self::$ver][] = "UPDATE {$_TABLES['shop.ipnlog']}
                SET ref_id = txn_id WHERE gateway IN
                ('paypal', 'test', 'check', 'coingate', 'authorizenet', 'paylike');";
        }
        global $_DB_name;
        try {
            $keys = $db->conn->executeQuery(
                "SELECT index_name FROM information_schema.statistics
                WHERE table_schema = ?
                AND table_name = ?
                AND index_name like 'name_%'",
                array($_DB_name, $_TABLES['shop.orderstatus']),
                array(Database::STRING, Database::STRING)
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $keys = false;
        }
        if (!empty($keys)) {
            foreach ($keys as $key) {
                $SHOP_UPGRADE['1.5.0'][] = "ALTER TABLE {$_TABLES['shop.orderstatus']} DROP KEY `{$key['index_name']}`";
            }
        }

        if (!self::doUpgradeSql(self::$ver, self::$dvlp)) {
            return false;
        }

        // Now update gateways where the payment ID is different than the Webhook ID.
        if ($upd_ref_id) {
            try {
                $ipns = $db->conn->executeQuery(
                    "SELECT id, gateway, ipn_data, txn_id
                    FROM {$_TABLES['shop.ipnlog']}
                    WHERE gateway IN ('stripe', 'square', 'ppcheckout')"
                )->fetchAllAssociative();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $ipns = false;
            }
            if (!empty($ipns)) {
                foreach ($ipns as $ipn) {
                    $ref_id = self::getRefFromIpnJson($ipn);
                    if (!empty($ref_id)) {
                        try {
                            $db->conn->executeStatement(
                                "UPDATE {$_TABLES['shop.ipnlog']}
                                SET ref_id = ? WHERE id = ?",
                                array($ref_id, $ipn['id']),
                                array(Database::STRING, Database::INTEGER)
                            );
                        } catch (\Exception $e) {
                            // Log the error but no reason to stop
                            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}";
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $weight = 0;
                $OI = OrderItem::fromArray($A);
                $weight = (float)$OI->getProduct()->getWeight();
                if ($OI->getVariantId() > 0) {
                    $weight += (float)ProductVariant::getInstance($OI->getVariantId())->getWeight();
                }
                if ($weight > 0) {
                    DB_query(
                        "UPDATE {$_TABLES['shop.orderitems']}
                        SET shipping_weight = $weight
                        WHERE id = " . $OI->getId()
                    );
                }
            }
        }
        return self::setVersion(self::$ver);
    }


    /**
     * Get the payment reference ID from the IPN data.
     * This could be handled by each gateway, but some are installable modules
     * and the latest version may not be available.
     *
     * @param   array   $A  Record array from the ipnlog table
     * @return  string      Payment ref_id, empty string if unable to decode.
     */
    public static function getRefFromIPNJson(array $A) : string
    {
        $obj = json_decode($A['ipn_data']);
        $retval = '';
        switch ($A['gateway']) {
        case 'ppcheckout':
            if (isset($obj->resource->id)) {
                $retval = $obj->resource->id;
            }
            break;
        case 'stripe':
            if (isset($obj->data->object->payment_intent)) {
                $retval = $obj->data->object->payment_intent;
            }
            break;
        case 'square':
            if (isset($obj->data->id)) {
                $retval = $obj->data->id;
            }
            break;
        }
        return $retval;
    }

}
