<?php
/**
 * Configuration Defaults Shop plugin for glFusion.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (C) 2005-2006 Vincent Furia <vinny01@users.sourceforge.net>
 * @package     shop
 * @version     v1.4.1
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 *
 */

// This file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}
use Shop\Log;

// Check if the Paypal plugin is installed and has service functions defined.
// If so, disable Shop service functions.
if (function_exists('plugin_getCurrency_paypal')) {
    $enable_svc_funcs = 0;
} else {
    $enable_svc_funcs = 1;
}

/** @var global config data */
global $shopConfigData;
$shopConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'currency',
        'default_value' => 'USD',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'anon_buy',
        'default_value' => true,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'menuitem',
        'default_value' => true,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'order',
        'default_value' => 'name',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 5,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'prod_per_page',
        'default_value' => '10',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'show_plugins',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'displayblocks',
        'default_value' => '3',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 13,
        'sort' => 80,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'weight_unit',
        'default_value' => 'lbs',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 15,
        'sort' => 90,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'uom_size',
        'default_value' => 'IN',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 23,
        'sort' => 100,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tc_link',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 110,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'days_purge_cart',
        'default_value' => '14',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 120,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'days_purge_pending',
        'default_value' => '180',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 130,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'years_redact_data',
        'default_value' => '7',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 140,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'redact_action',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 25,
        'sort' => 150,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'product_tpl_ver',
        'default_value' => 'v2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 160,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'list_tpl_ver',
        'default_value' => 'v2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 170,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'hp_layout',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 19,
        'sort' => 180,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ipn_url',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 190,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'use_sku',
        'default_value' => false,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 200,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'adm_def_view',
        'default_value' => 'products',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 20,
        'sort' => 210,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ena_fast_checkout',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 220,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'order_id_format',
        'default_value' => '16',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 11,
        'sort' => 230,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'order_id_prefix',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 240,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'inv_start_num',
        'default_value' => '10000',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 250,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'link_orders_new_user',
        'default_value' => false,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 260,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'virt_ord_paid_status',
        'default_value' => 'processing',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 270,
        'set' => true,
        'group' => 'shop',
    ),

    array (
        'name' => 'fs_features',            // Enabling features, etc.
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'shop_enabled',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 2,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'catalog_enabled',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 2,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ena_cart',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'enable_svc_funcs',
        'default_value' => $enable_svc_funcs,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 2,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ena_comments',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 2,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ena_ratings',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 2,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'anon_can_rate',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 2,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_paths',               // Paths fieldset
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'max_thumb_size',
        'default_value' => '250',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'max_file_size',
        'default_value' => '8',     // MB
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_prod_defaults',   // Products Defaults and Views
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_prod_type',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_enabled',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_taxable',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 2,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_featured',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 2,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_expiration',
        'default_value' => '3',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_track_onhand',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 2,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_oversell',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 16,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_blocks',   // Plugin block control
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'blk_random_limit',
        'default_value' => '1',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'blk_featured_limit',
        'default_value' => '1',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'blk_popular_limit',
        'default_value' => '1',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'centerblock',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 2,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_debug',   // Debugging settings
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 40,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'log_level',
        'default_value' => '200',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 40,
        'selection_array' => 18,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_addresses',   // Address Collection
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'address_validator',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 22,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'smartystreets_id',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'smartystreets_token',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'smartystreets_license',
        'default_value' => 'us-core-cloud',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fset_address_required',
        'default_value' => NULL,
        'type' => 'fset',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => NULL,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_name',
        'default_value' => '3',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_company',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_address1',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 80,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_address2',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 90,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_city',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 100,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_state',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 110,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_country',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 120,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_zip',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 130,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'req_addr_phone',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 4,
        'sort' => 140,
        'set' => true,
        'group' => 'shop',
    ),

    // Feeds FS
    array(
        'name' => 'fs_feeds',
        'default_value' => 0,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 60,
        'selection_array' => 0,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'feed_facebook',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 60,
        'selection_array' => 2,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_google_category',
        'default_value' => '988',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 60,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),

    // Shop Information SG
    array(
        'name' => 'sg_shop',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_shop',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'company',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'address1',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'address2',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'city',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'state',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'zip',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'country',
        'default_value' => 'US',
        'type' => 'select',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'remit_to',       // Remittance/Support contact
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'shop_phone',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 90,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'shop_email',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 100,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'logo_url',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 110,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'logo_width',
        'default_value' => '100',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 120,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'logo_height',
        'default_value' => '100',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 130,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'purge_sale_prices',
        'default_value' => '30',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 140,
        'set' => true,
        'group' => 'shop',
    ),
    // Gift Card SG
    array(
        'name' => 'sg_gc',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 20,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_gc',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 20,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_enabled',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 20,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_exp_days',
        'default_value' => '365',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_gc_format',       // Gift Card Formatting
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_letters',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 17,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_numbers',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_symbols',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 2,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_prefix',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_suffix',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_length',
        'default_value' => '10',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_mask',
        'default_value' => 'XXXX-XXXX-XXXX-XXXX',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),

    // Sales Tax Processing SG
    array(
        'name' => 'sg_tax',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 30,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_tax',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_nexuses',
        'default_value' => '',
        'type' => '%text',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_provider',
        'default_value' => 'internal',
        'type' => 'select',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 21,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_rate',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_test_mode',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 2,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_nexus_virt',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 12,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),

    // Geolocation API selection
    array(
        'name' => 'sg_geo',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 40,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_geo',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 40,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ipgeo_provider',
        'default_value' => '',
        'type' => 'select',
        'subgroup' => 40,
        'fieldset' => 10,
        'selection_array' => 0,     // helper function
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ipgeo_api_key',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 40,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ipstack_api_key',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 40,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),

    // Affiliate Program SG
    array(
        'name' => 'sg_affiliate',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 50,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_aff_general',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_enabled',
        'default_value' => false,
        'type' => 'select',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_cookie_exp_days',
        'default_value' => '3',
        'type' => 'text',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_cart_exp_days',
        'default_value' => '90',
        'type' => 'text',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_allow_entry',
        'default_value' => false,
        'type' => 'select',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_pct',
        'default_value' => 5,
        'type' => 'text',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_delay_days',
        'default_value' => 30,
        'type' => 'text',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_min_payment',
        'default_value' => 10,
        'type' => 'text',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_key',
        'default_value' => 'shop_ref',
        'type' => 'text',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 90,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_eligible',
        'default_value' => 'customers',
        'type' => 'select',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 24,
        'sort' => 100,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_auto_enroll',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 2,
        'sort' => 110,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'aff_form_id',
        'default_value' => 'shop_ref',
        'type' => 'select',
        'subgroup' => 50,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 120,
        'set' => true,
        'group' => 'shop',
    ),
);


/**
 * Initialize Shop plugin configuration
 *
 * No longer imports a pre-0.4.0 config.php. Only configuration items shown
 * above are imported.
 *
 * @return  boolean             True
 */
function plugin_initconfig_shop()
{
    global $shopConfigData;

    $c = config::get_instance();
    if (!$c->group_exists('shop')) {
        USES_lib_install();
        foreach ($shopConfigData AS $cfgItem) {
            _addConfigItem($cfgItem);
        }
    } else {
        Log::write('shop_system', Log::ERROR, 'initconfig error: Shop config group already exists');
    }
    return true;
}

