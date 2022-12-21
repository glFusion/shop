<?php
/**
 * Upgrade to version 1.3.0
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Upgrades;
use Shop\Payment;
use Shop\Config;
use Shop\OrderItem;
use Shop\Log;


class v1_3_0 extends Upgrade
{
    private static $ver = '1.3.0';

    public static function upgrade()
    {
        global $_TABLES;

        $upd_shipping = !self::tableHasColumn('shop.orders', 'shipping_method');
        $upd_oi_sku = !self::tableHasColumn('shop.orderitems', 'sku');
        $upd_sup_logos = !self::tableHasColumn('shop.suppliers', 'logo_image');
        $load_pmt_table = !DB_checkTableExists('shop.payments');

        if (!self::doUpgradeSql(self::$ver, self::$dvlp)) {
            return false;
        }
        if ($upd_shipping) {
            // Now update the shipping_method and shipping_dscp fields
            // using defaults from the shipping table.
            $sql = "UPDATE {$_TABLES['shop.orders']} orders
                LEFT JOIN {$_TABLES['shop.shipping']} shipping
                ON shipping.id = orders.shipper_id
                SET
                    orders.shipping_method = shipping.module_code,
                    orders.shipping_dscp = shipping.name
                WHERE orders.shipper_id > 0";
            DB_query($sql);
        }
        if ($upd_oi_sku) {
            // If the OrderItem SKU field was added, update the SKUs from
            // the product/variant info
            $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $OI = OrderItem::fromArray($A);
                $OI->setSKU()->Save();
            }
        }
        if ($upd_sup_logos) {
            // Update the suppliers table with the logo image filenames.
            $img_path = Config::get('tmpdir') . '/images/brands/';
            $sql = "SELECT * FROM {$_TABLES['shop.suppliers']}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                if (is_file($img_path . $A['sup_id'] . '.jpg')) {
                    $sql1 = "UPDATE {$_TABLES['shop.suppliers']}
                        SET logo_image = '{$A['sup_id']}.jpg'
                        WHERE sup_id = {$A['sup_id']}";
                    DB_query($sql1);
                }
            }
        }
        // Change shipper configuration to json_encoded
        $sql = "SELECT code, data FROM {$_TABLES['shop.carrier_config']}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $arr = @unserialize($A['data']);
            if ($arr !== false) {
                $json = DB_escapeString(json_encode($arr));
                DB_query("UPDATE {$_TABLES['shop.carrier_config']}
                    SET data = '$json'
                    WHERE code = '{$A['code']}'");
            }
        }

        if ($load_pmt_table) {
            self::loadPaymentsFromIPN();
        }

        // Change IPN log data from serialized to json_encoded
        $sql = "SELECT id, ipn_data FROM {$_TABLES['shop.ipnlog']}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $arr = @unserialize($A['ipn_data']);
            if ($arr !== false) {
                $json = DB_escapeString(json_encode($arr));
                DB_query("UPDATE {$_TABLES['shop.ipnlog']}
                    SET ipn_data = '$json'
                    WHERE id = {$A['id']}");
            }
        }
        return self::setVersion(self::$ver);
    }


    /**
     * Migrate payment information from the IPN log to the Payments table.
     * This is done during upgrade to v1.3.0.
     */
    private static function loadPaymentsFromIPN()
    {
        global $_TABLES, $LANG_SHOP;

        Log::write('system', Log::INFO, "Loading payments from IPN log");
        $sql = "SELECT * FROM {$_TABLES['shop.ipnlog']}
            ORDER BY ts ASC";
            //WHERE id = 860
        $res = DB_query($sql);
        $done = array();        // Avoid duplicates
        while ($A = DB_fetchArray($res, false)) {
            if (empty($A['gateway'])) {
                continue;
            }

            $ipn_data = @unserialize($A['ipn_data']);
            if ($ipn_data === false) {
                $ipn_data = @json_decode($A['ipn_data'], true);
                if ($ipn_data === NULL) {
                    Log::write('system', Log::ERROR, "Invalid IPN data found: " . var_export($A['ipn_data']));
                    continue;
                }
            }

            $cls = 'Shop\\ipn\\' . $A['gateway'];
            if (!class_exists($cls)) {
                Log::write('system', Log::ERROR, "Class $cls does not exist");
                continue;
            }
            $ipn = new $cls($ipn_data);
            if (isset($ipn_data['pmt_gross']) && $ipn_data['pmt_gross'] > 0) {
                $pmt_gross = $ipn_data['pmt_gross'];
            } else {
                $pmt_gross = $ipn->getPmtGross();
            }
            if ($pmt_gross < .01) {
                continue;
            }

            if (!empty($A['order_id'])) {
                $order_id = $A['order_id'];
            } elseif ($ipn->getOrderId() != '') {
                $order_id = $ipn->getOrderID();
            } elseif ($ipn->getTxnId() != '') {
                $order_id = DB_getItem(
                    $_TABLES['shop.orders'],
                    'order_id',
                    "pmt_txn_id = '" . DB_escapeString($ipn->getTxnId()) . "'"
                );
            } else {
                $order_id = '';
            }
            if (!array_key_exists($A['txn_id'], $done)) {
                $Pmt = new Payment;
                $Pmt->setRefID($A['txn_id'])
                    ->setAmount($pmt_gross)
                    ->setTS($A['ts'])
                    ->setIsMoney(true)
                    ->setGateway($A['gateway'])
                    ->setMethod($ipn->getGW()->getDisplayName())
                    ->setOrderID($order_id)
                    ->setComment('Imported from IPN log')
                    ->Save();
                $done[$Pmt->getRefId()] = 'done';
            }
        }

        // Get all the "payments" via coupons.
        $sql = "SELECT * FROM {$_TABLES['shop.coupon_log']}
            WHERE msg = 'gc_applied'";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $Pmt = new Payment;
            $Pmt->setRefID(uniqid())
                ->setAmount($A['amount'])
                ->setTS($A['ts'])
                ->setIsMoney(false)
                ->setGateway('_coupon')
                ->setMethod('Apply Coupon')
                ->setComment($LANG_SHOP['gc_pmt_comment'])
                ->setOrderID($A['order_id'])
                ->Save();
        }
    }

}
