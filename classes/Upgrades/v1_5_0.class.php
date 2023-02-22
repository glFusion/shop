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
use glFusion\Database\Database;
use Shop\Config;
use Shop\OrderItem;
use Shop\ProductVariant;
use Shop\Log;


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

        // Create invoice records only if not already done.
        $add_invoice_table = !self::tableExists($_TABLES['shop.invoices']);

        // Update the payment ref_id column in the IPN log.
        $upd_ref_id = !self::tableHasColumn('shop.ipnlog', 'ref_id');
        if (!self::tableHasColumn('shop.orderstatus', 'order_valid')) {
            $SHOP_UPGRADE[self::$ver][] = "UPDATE {$_TABLES['shop.orderstatus']}
                SET order_valid = 0 WHERE name IN ('refunded', 'canceled')";
        }
        if (!self::tableHasColumn('shop.orderstatus', 'order_valid')) {
            $SHOP_UPGRADE[self::$ver][] = "UPDATE {$_TABLES['shop.orderstatus']}
                SET aff_eligible = 1 WHERE name IN ('processing', 'shipped', 'closed', 'invoiced')";
        }
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

        // See if we already have the customer email column in the gateway
        // cross-reference.
        $have_custGWemail = self::tableHasColumn('shop.customerXgateway', 'email');

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

        // Add the 'canceled' order status to the table
        $count = $db->getCount(
            $_TABLES['shop.orderstatus'],
            array('name'),
            array('canceled'),
            array(Database::STRING)
        );
        if ($count == 0) {
            // We'll use this flag to update other statuses as well.
            try {
                // DB field defaults are all appropriate for this status.
                $db->conn->insert(
                    $_TABLES['shop.orderstatus'],
                    array(
                        'name' => 'canceled',
                    ),
                    array(
                        Database::STRING,
                    )
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
            self::updateOrderStatus('processing', array(
                'valid' => 1, 'closed' => 0, 'aff' => 0, 'viewable' => 1,
            ) );
            self::updateOrderStatus('shipped', array(
                'valid' => 1, 'closed' => 0, 'aff' => 1, 'viewable' => 1,
            ) );
            self::updateOrderStatus('closed', array(
                'valid' => 1, 'closed' => 1, 'aff' => 1, 'viewable' => 1,
            ) );
            self::updateOrderStatus('invoiced', array(
                'valid' => 1, 'closed' => 0, 'aff' => 1, 'viewable' => 1,
            ) );
            self::updateOrderStatus('canceled', array(
                'valid' => 0, 'closed' => 1, 'aff' => 0, 'viewable' => 0,
            ) );
        }

        // Populate the invoice table with the order sequence values.
        // This is conditional since the order_seq field may be removed in a
        // later version.
        if ($add_invoice_table) {
            try {
                $stmt = $db->conn->executeQuery(
                    "SELECT order_id, order_seq, UNIX_TIMESTAMP(last_mod) AS last_mod
                    FROM {$_TABLES['shop.orders']} WHERE order_seq > 0 ORDER BY order_seq ASC"
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($stmt) {
                while ($A = $stmt->fetchAssociative()) {
                    try {
                        $db->conn->insert(
                            $_TABLES['shop.invoices'],
                            array(
                                'invoice_id' => $A['order_seq'],
                                'order_id' => $A['order_id'],
                                'invoice_dt' => $A['last_mod'],
                            ),
                            array(Database::INTEGER, Database::STRING, Database::INTEGER)
                        );
                    } catch (\Exception $e) {
                        Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                    }
                }
            }
        }

        // Update the ena_ratings config to include anonymous, if set.
        if ((int)Config::get('anon_can_rate')) {
            Config::set('ena_ratings', 2, true);
        }

        // Fix the SKU valuse in OrderItems. Previously did not include variants
        try {
            $stmt = $db->conn->executeQuery(
                "SELECT oi.id, oi.sku, oi.variant_id, pv.sku AS pv_sku
                FROM {$_TABLES['shop.orderitems']} oi
                LEFT JOIN {$_TABLES['shop.product_variants']} pv ON pv.pv_id = oi.variant_id
                WHERE oi.variant_id > 0 AND pv.pv_id IS NOT NULL"
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                if (!empty($A['pv_sku'])) {
                    try {
                        $db->conn->update(
                            $_TABLES['shop.orderitems'],
                            array('sku' => $A['pv_sku']),
                            array('id' => $A['id']),
                            array(Database::STRING, Database::INTEGER)
                        );
                    } catch (\Throwable $e) {
                        Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                        // just continue on error
                    }
                }
            }
        }

        if (!$have_custGWemail) {
            // Add the emails of known customers to the table.
            try {
                $rows = $db->conn->executeQuery(
                    "SELECT uid FROM {$_TABLES['shop.customerXgateway']} WHERE uid > 1"
                )->fetchAllAssociative();
            } catch (\Throwable $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $rows = false;
            }
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $email = $db->getItem($_TABLES['users'], 'email', array('uid' => $row['uid']));
                    if (!empty($email)) {
                        try {
                            $db->conn->update(
                                $_TABLES['shop.customerXgateway'],
                                array('email' => $email),
                                array('uid' => $row['uid']),
                                array(Database::STRING, Database::INTEGER)
                            );
                        } catch (\Throwable $e) {
                            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                            // Just go on to the next
                        }
                    }
                }
            }
        }

        // Fix the Authorize.Net gateway file if it has not been updated.
        // The version included in previous versions does not have the
        // correct function declarations. Otherwise, the gateway manager
        // admin list will throw errors.
        foreach (array('authorizenet', 'square', 'stripe') as $gwname) {
            $path = Config::get('path') . 'classes/Gateways/' . $gwname;
            $file = $path . '/gateway.json';
            if (is_dir($path) && is_file($file)) {
                $json = @file_get_contents($file);
                $arr = @json_decode($json, true);
                if (is_array($arr) && !isset($arr['version'])) {
                    // Original gateway file from 1.4.1 and prior. Replace
                    // the gateway class with the updated one.
                    $arr['version'] = '1.3.0';  // to force upgrade option
                    copy(__DIR__ . '/files/1.5.0/' . $gwname . '_gw.class.php', $path . '/Gateway.class.php');
                    $json = @json_encode($arr);
                    if ($json) {
                        @file_put_contents($file, $json);
                    }
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


    /**
     * Update new fields in the OrderStatus table with better values.
     * Called only once here when the fields are added.
     *
     * @param   string  $name   OrderStatus name
     * @param   integer $valid  Order Valid flag
     * @param   integer $aff    Affiliate Eligible flag
     */
    private static function updateOrderStatus(string $name, array $vals) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->update(
                $_TABLES['shop.orderstatus'],
                array(
                    'order_valid' => $vals['order_valid'],
                    'order_closed' => $vals['order_closed'],
                    'aff_eligible' => $vals['aff_eligible'],
                    'cust_viewable' => $vals['cust_viewable'],
                ),
                array('name' => $name),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                )
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }

}
