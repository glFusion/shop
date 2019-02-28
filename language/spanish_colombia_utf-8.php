<?php
/**
 * Spanish-Columbian language file for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      John Toro <john.toro@newroute.net>
 * @copyright   Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.6.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Global array to hold all plugin-specific configuration items. */
$LANG_SHOP = array (
'plugin'            => 'Shop',
'main_title'        => 'Catalogo de Productos',
'admin_title'       => 'Shop - Administración',
'blocktitle'        => 'Productos',
'cart_blocktitle'   => 'Shopping Cart',
'srchtitle'         => 'Productos',
'products'          => 'Productos',
'category'          => 'Categoría',
'featured_product'  => 'Producto Destacado',
'popular_product'   => 'Productos Populares',
'product_categories' => 'Product Categories',
'mnu_shop'        => 'Productos',
'mnu_admin'         => 'Admin',
'product'           => 'Producto/SKU',
'qty'               => 'Cant.',
'description'       => 'Descripción',
'short_description' => 'Descripción Corta',
'keywords'          => 'Etiquetas',
'exp_time_days'     => 'Expiración (días)',
'purch_date'        => 'Purchase Date',
'txn_id'            => 'Txn ID',
'expiration'        => 'Expiración',
'download'          => 'Download',
'downloadable'      => 'Downloadable',
'price'             => 'Precio',
'quantity'          => 'Cantidad',
'item_total'        => 'Item Total',
'total'             => 'Total',
'cart_empty'        => 'Your cart is empty.',
'purchase_history'  => 'Purchase History',
'ipnlog'            => 'IPN Log',
'new_product'       => 'Crear (P)',
'new_category'      => 'Crear (C)',
'product_list'      => 'Productos',
'category_list'     => 'Categorías',
'admin_hdr'         => 'Create, delete and modify products in the catalog.  You may also view purchase histories and the Shop IPN data for each transaction.',
'admin_hdr_editattr' => 'Create or update a product attribute.',
'admin_hdr_catlist' => 'Edit product categories, or select "New Category" to create a new one.  A category may be deleted only if it is not associated with any products.',
'admin_hdr_ipnlog'  => 'This is a list of IPN messages that have been received.  Click on either the ID or the Txn ID to view the message detail.',
'admin_hdr_history' => 'This is a list of all purchases in the database.  Click on a link for more information about that item',
'admin_hdr_attributes' => 'Attributes can be associated with products.  For instance, you may wish to offer small, medium and large sizes, and charge extra for large.<br /><span class="pluginShopAlertText">Note:  Products with attributes cannot be purchased via "Buy-Now" buttons.  The shopping cart must be enabled.</span>',
'admin_hdr_wfadmin' => 'Enable, Disable, and Re-order the items that must be completed before checkout. Workflow items cannot be deleted. &quot;Confirm Order&quot; is always the last item in the workflow.',
'admin_hdr_wfstatus' => 'Update order status values. You can edit and order them, and indicate whether the buyer is notified when a status becomes active.',
'username'          => 'User Name',
'pmt_status'        => 'Payment Status',
'status'            => 'Status',
'update_status'     => 'Update Status',
'purchaser'         => 'Purchaser',
'gateway'           => 'Gateway',
'gateways'          => 'Payment Gateways',
'workflows'         => 'Workflow/Status',
'ip_addr'           => 'IP Address',
'datetime'          => 'Date/Time',
'verified'          => 'Verified',
'ipn_data'          => 'Full IPN Data',
'viewcart'          => 'View Cart',
'vieworder'         => 'Confirm Order',
'images'            => 'Imágenes',
'cat_image'         => 'Category Image',
'click_to_enlarge'  => 'Click to Enlarge Image',
'enabled'           => 'Habilitado',
'disabled'          => 'Deshabilitado',
'featured'          => 'Destacado',
'taxable'           => 'Imponible',
'delete'            => 'Borrar',
'thanks_title'      => 'Thank you for your order!',
'yes'               => 'Sí',
'no'                => 'No',
'closed'            => 'Closed',
'true'              => 'True',
'false'             => 'False',
'info'              => 'Información',
'warning'           => 'Warning',
'error'             => 'Error',
'alert'             => 'Alerta',
'invalid_product_id' => 'An invalid product ID was requested',
'access_denied_msg' => 'You do not have access to this page. <p />' .
    'If you believe you have reached this message in error, ' .
    'please contact your site administrator.<p />' .
    'All attempts to access this page are logged.',
'access_denied'     => 'Access Denied',
'select_file'       => 'Seleccionar Archivo',
'or_upload_new'     => 'O, cargar un archivo',
'random_product'    => 'Random Product',
'featured_product'  => 'Featured Product',
'invalid_form'      => 'The submitted form has missing or invalid fields, or could be a duplicate record.',
'buy_now'           => 'Comprar',
'add_to_cart'       => 'Add to Cart',
'donate'            => 'Donar',
'txt_buttons'       => 'Botones',
'incl_blocks'       => 'Include in Blocks',
'buttons'           => array(
        'buy_now'   => 'Buy Now',
        'add_cart'  => 'Add to Cart',
        'donation'  => 'Donar',
        'subscribe' => 'Subscribir',
        'pay_now'   => 'Pagar',
        'checkout'  => 'Checkout',
        'external'  => 'Productos Externos',
    ),
'prod_type'         => 'Tipo',
'prod_types'        => array(
        1 => 'Physical', 2 => 'Downloadable', 4 => 'Other Virtual',
        3 => 'Physical + Download',
    ),
'edit'              => 'Modificar',
'create_category'   => 'Create a New Category',
'cat_name'          => 'Category Name',
'parent_cat'        => 'Parent Category',
'top_cat'           => '-- Arriba --',
'saveproduct'       => 'Guardar',
'deleteproduct'     => 'Borrar',
'deleteopt'         => 'Delete Option',
'savecat'           => 'Save Category',
'saveopt'           => 'Save Option',
'deletecat'         => 'Delete Category',
'product_id'        => 'Product ID',
'other_func'        => 'Mantenimiento',
'q_del_item'        => 'Are you sure you want to delete this item?',
'clearform'         => 'Reiniciar',
'delivery_info'     => 'Delivery Information',
'product_info'      => 'Product Information',
'delete_image'      => 'Delete Image',
'weight'            => 'Peso',
'no_download_path'  => 'No file download path configured.',
'sortby'            => 'Ordenar Por',
'name'              => 'Nombre',
'dt_add'            => 'Date Added',
'ascending'         => 'Ascendentemente',
'descending'        => 'Descendentemente',
'sortdir'           => 'Sort Direction',
'comments'          => 'Comentarios',
'ratings_enabled'   => 'Calificar',
'no_shipping'       => 'No Shipping',
'fixed'             => 'Fixed',
'pp_profile'        => 'Use Gateway Profile',
'shipping_type'     => 'Shipping',
'shipping_amt'      => 'Amount',
'per_item'          => 'Per Item',
'storefront'        => 'Ir a la Tienda',
'options_msg'       => 'Adding options will prevent encrypted buttons from being created.',
'new_attr'          => 'Crear (A)',
'attr_list'         => 'Atributos',
'attr_name'         => 'Atributo',
'attr_value'        => 'Valor',
'attr_price'        => 'Precio',
'order'             => 'Orden',
'err_missing_name'  => 'Missing product name',
'err_missing_desc'  => 'Missing product description',
'err_missing_cat'   => 'Missing category',
'err_missing_file'  => 'Missing file for downloadable product',
'err_missing_exp'   => 'Missing expiration period for downloadable product',
'err_phys_need_price' => 'Non-downloadable items must have a positive price value',
'missing_fields'    => 'Missing Fields',
'no_javascript'     => 'Javascript is required for this site to function properly. Your cart may not be updated accurately, and your order may be delayed unless you enable Javascript in your browser.',
'clk_help'      => 'Click for Help',
'ind_req_fld'   => 'Indicates a required field',
'required'      => 'Required',
'ipnlog_id'     => 'IPN Log ID',
'trans_id'      => 'Transaction ID',
'paid_by'       => 'Paid by',
'pmt_method'    => 'Payment Method',
'pmt_gross'     => 'Gross Payment',
'billto_info'   => 'Payment Information',
'shipto_info'   => 'Shipping Information',
'home'          => 'Home',
'none'          => 'None',
'browse_cat'    => 'Browse Catalog',
'search_catalog' => 'Search Catalog',
'by_cat'        => 'By Category',
'by_name'       => 'By Name',
'search'        => 'Buscar',
'any'           => 'Any',
'customize'     => 'Customize',
'fullname'      => 'Nombre',
'lastname'      => 'Apellido',
'company'       => 'Compañía',
'address1'      => 'Address Line 1',
'address2'      => 'Address Line 2',
'country'       => 'País',
'city'          => 'Ciudad',
'state'         => 'Esatdo',
'zip'           => 'Código Postal',
'name_or_company' => 'Name or Company',
'make_def_addr' => 'Make default address',
'sel_shipto_addr' => 'Please select the shipping address from your address book, or enter a new one below.',
'sel_billto_addr' => 'Please select the billing address from your address book, or enter a new one below.',
'checkout'      => 'Check Out',
'bill_to'       => 'Bill To',
'ship_to'       => 'Ship To',
'submit_order'  => 'Submit Order',
'orderby'       => 'Order',
'billto'        => 'Billing Address',
'shipto'        => 'Shipping Address',
'gw_notinstalled' => 'Gateways not installed',
'empty_cart'    => 'Empty Cart',
'update_cart'   => 'Update Cart',
'order_summary' => 'Order Summary',
'order_date'    => 'Order Date',
'order_number'  => 'Order Number',
'new_address'   => 'New Address',
'shipping'      => 'Shipping',
'handling'      => 'Handling',
'tax'           => 'Tax',
'or'            => 'or',
'purch_signup'  => 'Create an Account',
'buyer_email'   => 'Buyer E-Mail',
'todo_noproducts' => 'No hay productos en el catalogo.',
'todo_nogateways' => 'No hay pasarelas de pago habilitadas.',
'orderstatus'   => array(
        'pending'   => 'Pendiente',
        'paid'      => 'Pagado',
        'shipped'   => 'Enviado',
        'processing' => 'Procesando',
        'closed'    => 'Cerrada',
        'refunded'  => 'Reembolsado',
    ),
'message' => 'Mensaje',
'timestamp' => 'Timestamp',
'notify' => 'Notificar',
'updated_x_orders' => 'Updated %d orders.',
'onhand' => 'Qty. on hand',
'available' => 'Disponible',
'track_onhand' => 'Track Quantity?',
'continue_shopping' => 'Continue Shopping',
'pmt_error' => 'There was an error processing your payment.',
'pmt_made_via' => 'Payment was made via %s on %s.',
'new_option' => 'Add a new option',
'oversell_action' => 'Allow overselling/backordering?',
'oversell_allow' => 'Allow, display product and accept orders',
'oversell_deny' => 'Deny, display product but prevent ordering',
'oversell_hide' => 'Hide product from catalog',
'list_sort_options' => array(
    //'most_popular' => 'Most Popular',
    'name' => 'Name A-Z',
    'price_l2h' => 'Price - Low to High',
    'price_h2l' => 'Price - High to Low',
    //'top_rated' => 'Top Rated',
    'newest' => 'Newest',
    ),
'qty_disc_text' => 'Discounts calculated at checkout',
'order_instr' => 'Special Order Instructions',
'copy_attributes' => 'Copy all attributes from one product to another product or category.<br />Existing attributes will not be changed',
'copy_from' => 'Copy From',
'target_prod' => 'Target Product',
'target_cat' => 'Target Category',
'custom' => 'Text Fields (separate by &quot;|&quot;&nbsp;)',
'visible_to' => 'Visible To',
'anon_and_empty' => 'There are no products available for purchase by anonymous users. Try logging into the site to view the catalog.',
'back_to_catalog' => 'Back to Catalog',
'del_item_instr' => 'Items that have no purchases can be deleted. If an item has been purchased it can only be disabled.',
'del_cat_instr' => 'Categories containing products cannot be deleted.',
'select_image' => 'Select New Image',
'discount' => 'Discount',
'min_purch' => 'Min. Purchase',
'qty_discounts' => 'Quantity Discounts',
'custom_instr' => '(separate by &quot;|&quot;&nbsp;)',
'sale_price' => 'Sale Price',
'qty_discounts_avail' => 'Quantity Discounts Available',
'from' => 'From',
'to' => 'To',
'terms_and_cond' => 'Terms and Conditions',
'item_history' => 'Item Purchase History',
'reset' => 'Reset',
'datepicker' => 'Date Selector',
'workflows' => 'Order Workflows',
'statuses' => 'Order Statuses',
'reports' => 'Reports',
'reports_avail' => array(
    'orderlist' => 'Order Listing',
    'paymentlist' => 'Payment Listing',
    ),
'my_orders' => 'My Orders',
'no_products_match' => 'No products match your search parameters',
'msg_updated' => 'Item has been updated',
'msg_nochange' => 'Item is unchanged',
'msg_item_added' => 'Item has been added to your cart',
'all' => 'All',
'print' => 'Print',
'resetbuttons' => 'Clear Encrypted Button Cache',
'orderhist_item' => 'View the order history for this item',
'notify_email' => 'Notification Email',
'recipient_email' => 'Recipient Email',
'sender_name' => 'Sender\'s Name',
'apply_gc' => 'Apply Gift Card',
'item_not_found' => 'Item not found',
'dscp_root_cat' => 'This is the root category and cannot be deleted.',
'no_del_item' => 'Product %s has purchase records, can&apos;t delete.',
'no_del_cat' => 'Category %s has related products or sub-categories, can&apos;t delete.',
'forgotten_user' => 'User Forgotten',
'tax_on_x_items' => '%.2f%% Tax on %s item(s)',
'amt_paid_gw' => '%0.2f paid via %s',
'balance' => 'Balance',
'all_items' => 'All items in your shopping cart',
'cart' => 'Cart',
'paid_by_gc' => 'Paid by Gift Card/Coupon',
'amount' => 'Amount',
'buyer' => 'Buyer',
'redeemer' => 'Redeemer',
'couponlist' => 'Coupon Management',
'code' => 'Code',
'coupons' => 'Coupons',
'gc_bal' => 'Gift Card Balance',
'hlp_gw_select' => 'Select your payment method below. You will be able to confirm your order on the next page.',
'confirm_order' => 'Confirm Order',
'coupon_apply_msg0' => 'The coupon amount of %s has been applied to your account.',
'coupon_apply_msg1' => 'This coupon has already been applied.',
'coupon_apply_msg2' => 'There was an error applying this coupon. Contact %s for assistance.',
'coupon_apply_msg3' => 'An invalid code was supplied. If you believe this is an error, contact %s for assistance.',
'see_details' => 'See Details',
'send_giftcards' => 'Send Gift Cards',
'my_account' => 'My Shopping Account',
'purge_cache' => 'Purge Cache',
'confirm_send_gc' => 'Are you sure you want to send gift cards to the selected users and groups?',
'sendgc_header' => 'Select a group and/or individual users to receive gift cards.',
'pmt_total' => 'Payment Total',
'del_existing' => 'Delete Existing?',
'err_gc_amt' => 'Must supply a positive amount for the gift cards.',
'err_gc_nousers' => 'No users specified, or none are in the specified group.',
'enter_gc' => 'Enter Coupon Code (click Update to apply)',
'update' => 'Update',
'apply_gc_title' => 'Apply a Gift Card to Your Account',
'apply_gc_help' => 'Enter the gift card code below and click the &quot;Update&quot; button to apply to your account.',
'apply_gc_email' => 'You may apply the gift card to your account by clicking <a href="%s">here</a>, or by visiting <a href="%s">%s</a> and entering the coupon code manually.<br />' . LB . 'NOTE: Do not apply this code to your account if this is a gift or the recipient will not be able to apply it.',
'subj_email_admin' => $_CONF['site_name'] . ': Order Notification',
'subj_email_user' => $_CONF['site_name'] . ': Purchase Receipt',
'sale_prices' => 'Sale Prices',
'new_sale' => 'New Sale',
'apply_disc_to' => 'Apply Discount To',
'disc_type' => 'Discount Type',
'percent' => 'Percent',
'start' => 'Start',
'end' => 'End',
'item_type' => 'Item Type',
'timepicker' => 'Click for Time Selector',
'gc_need_acct' => 'Before you can apply gift cards to your account, you need to have an account. You may still use the coupon code when placing an order.',
'msg_itemcat_req' => 'A product or category ID is required',
'msg_amount_req' => 'An amount is required',
'enter_email' => 'Your E-Mail Address',
'use_gc_part' => 'Use %s of your %s gift card balance',
'use_gc_full' => 'Use your %s gift card balance',
'apply' => 'Apply',
'some_gc_disallowed' => 'Some items cannot be paid with a gift card.',
'gift_cards' => 'Gift Cards',
'msg_gc_applied' => 'Applied to order %s',
'msg_gc_redeemed' => 'Claimed gift card %s',
'gc_activity' => 'Gift Card Activity',
'user_hdr_orderhist' => 'View all of your previous orders. Click on an order number to view the complete order.',
'user_hdr_couponlog' => 'This is a list of all transactions related to Gift Cards.',
'plus_shipping' => '(Plus %s Shipping)',
'notify_buyer' => 'Notify Buyer',
'notify_admin' => 'Notify Admin',
'user_history' => 'View this user&apos; purchase history',
'print_order' => 'Print this order',
'wf_statuses' => array(
    0   => 'Disabled',
    1   => 'Physical Only',
    3   => 'All Orders',
),
'status_changed' => 'Status updated from %1$s to %2$s',
'purge_carts' => 'Purge Shopping Carts',
'buttons_purged' => 'The encrypted button cache has been cleared.',
'cache_purged' => 'The data cache has been cleared.',
'carts_purged' => 'All shopping carts have been deleted.',
'q_purge_carts' => 'Are you sure you want to delete all active shopping carts?',
'dscp_purge_buttons' => 'Delete all of the stored enrypted buttons in the database. This will force the buttons to be recreated the next time they are needed.',
'dscp_purge_cache' => 'Purge all of the cached orders, items, logs, etc. This is typically needed if the database has been restored from a backup or changed manuallly and the cache is out of sync.',
'dscp_purge_carts' => 'Delete all customer shopping carts that have not been finalized as orders.<br /><b>This will impact the guest experience and should not normally be necessary.</b>',
'dscp_update_currency' => 'Update the currency code for all outstanding shopping carts to the configured currency.<br />Note that converting back and forth between currencies may result in rounding errors.',
'dscp_convert_cart_currency' => 'Check this box to convert the amounts to the new currency',
'include' => 'Include',
'exclude' => 'Exclude',
'buy_x_save' => 'Buy %1$d, save %2$s%%',
'out_of_stock' => 'This item is currently out of stock.',
'shipping_method' => 'Shipping Method',
'shipping_units' => 'Shipping Units',
'min_ship_units' => 'Min Shipping Units',
'max_ship_units' => 'Max Shipping Units',
'rate_table' => 'Rate Table',
'rate' => 'Parcel Rate',
'add_rate' => 'Click to add a new rate',
'new_ship_method' => 'New Shipment Method',
'admin_hdr_shipping' => 'Create and update shipping methods based on a number of product &quot;units&quot; shipped. Units provide a rough method of combining products into single shipments but do not consider weight, size or distance.',
'mnu_wfadmin' => 'Workflows/Statuses',
'edit_order' => 'Edit Order',
'go_back' => 'Go Back',
'packinglist' => 'Packing List',
'q_update_currency' => 'Are you sure you want to update the currency code for all outstanding carts?',
'x_carts_updated' => 'Updated %d carts',
'shop_closed'   => 'The Shop plugin is only available to administrators.',
'last_x_days'   => 'Last %d Days',
'periods' => array(
    'tm'    => 'This Month',
    'lm'    => 'Last Month',
    'ty'    => 'This Year',
    'ly'    => 'Last Year',
),
'gc'    => 'Gift Card',
'migrate_pp' => 'Migrate from Paypal',
'q_migrate_pp' => 'Are you sure? This will delete ALL existing data from the Shop plugin',
'dscp_migrate_pp' => 'Migrate data from the Paypal plugin version 0.6.0 or higher, if available, into the Shop plugin.<br /><b>This function empties ALL data from the Shop plugin before migration.</b>',
'migrate_pp_ok' => 'Paypal data was successfully migrated.',
'migrate_pp_error' => 'There was an error migrating from Paypal. Check the system log.',
);
if (isset($_SHOP_CONF['ena_ratings']) && $_SHOP_CONF['ena_ratings']) {
    $LANG_SHOP['list_sort_options']['top_rated'] = 'Top Rated';
}

$LANG_SHOP_EMAIL = array(
    'coupon_1' => 'You have received a gift card. Click on the link below to redeem it.',
    'coupon_2' => 'Act now, your gift card expires',
    'coupon_id' => 'Gift Card',
    'coupon_subject' => 'You have a gift card!',
);

$LANG_SHOP_HELP = array(
'enter_email' => 'Enter an e-mail address where your order receipt should be sent. It will not be used for any other purpose.',
'notify_email' => 'Enter an optional email address to receive the notification of this order. Your own email address will be used if this is empty.',
'hlp_cat_delete' => 'Only unused categories may be deleted',
'hlp_prod_delete' => 'Only products that have never been puchased may be deleted',
'recipient_email' => 'The gift card will be emailed to this address. If left blank, the gift card will be emailed to you.',
'orderlist_total' => 'This is the total of items on the order, excluding taxes and fees. Hover over an amount to see all charges.',
'sender_name' => 'Optionally enter your name to be shown to the recipient.',
);

$LANG_MYACCOUNT['pe_shop'] = 'Shopping';

/** Message indicating plugin version is up to date */
$PLG_shop_MESSAGE03 = 'Error retrieving current version number';
$PLG_shop_MESSAGE04 = 'Error performing the plugin upgrade';
$PLG_shop_MESSAGE05 = 'Error upgrading the plugin version number';
$PLG_shop_MESSAGE06 = 'Plugin is already up to date';
$PLG_shop_MESSAGE07 = 'Invalid download token given';
$PLG_shop_MESSAGE08 = 'There was an error finalizing your order. Please contact the site administrator.';

/** Language strings for the plugin configuration section */
$LANG_configsections['shop'] = array(
    'label' => 'PayPal',
    'title' => 'PayPal Configuration'
);

/** Language strings for the field names in the config section */
$LANG_confignames['shop'] = array(
    'currency'      => 'Currency',
    'anon_buy'      => 'Anonymous users can buy?',
    'purch_email_user' => 'Email User upon purchase?',
    'purch_email_user_attach' => 'Attach files to user\'s email message?',
    'purch_email_anon' => 'Email anonymous buyer upon purchase?',
    'purch_email_anon_attach' => 'Attach files to anonymous buyer email?',
    'prod_per_page' => 'Max products displayed per page',
    'order'         => 'Default sort order for product display',
    'menuitem'      => 'Add to main menu?',
    'cat_columns'   => 'Category Columns',
    'max_images'    => 'Max number of product images',
    'image_dir'     => 'Path to Images',
    'max_thumb_size' => 'Max Thumbnail Dimension (px)',
    'img_max_width' => 'Max Image Width (px)',
    'img_max_height' => 'Max Image Height (px)',
    'max_image_size' => 'Max. Product Image Size',
    'max_file_size' => 'Max size for downloadable files, in MB',
    'download_path' => 'Full path to downloadable files',
    'commentsupport' => 'Comments Supported?',
    'tmpdir'        => 'Temporary Working Directory',
    'ena_comments'  => 'Enable Comments?',
    'ena_ratings'   => 'Enable Product Ratings?',
    'anon_can_rate' => 'Anonymous can rate products?',
    'displayblocks'  => 'Display glFusion Blocks',
    'debug_ipn'     => 'Debug IPN Messages?',
    'debug'         => 'Program Debug?',
    'purch_email_admin' => 'Notify administrators of purchases?',
    'def_enabled'   => 'Product Enabled?',
    'def_featured'  => 'Product Featured?',
    'def_taxable'   => 'Product is Taxable?',
    'def_track_onhand' => 'Track Qty On Hand?',
    'def_oversell'  => 'Action when Qty On Hand = 0',
    'blk_random_limit' => 'Number of products in Random block',
    'blk_featured_limit' => 'Number of products in Featured block',
    'blk_popular_limit' => 'Number of products in Popular block',
    'def_expiration'    => 'Default Expiration Days for Downloads',
    'admin_email_addr' => 'Administrator E-Mail Address',
    'get_street' => 'Street Address',
    'get_city'  => 'City',
    'get_state' => 'State',
    'get_postal' => 'Postal Code',
    'get_country' => 'Country',
    'ena_cart' => 'Enable shopping cart?',
    'weight_unit' => 'Unit of Weight Measurement',
    'shop_name' => 'Shop Name',
    'shop_addr' => 'Shop Address',
    'shop_phone' => 'Shop Phone',
    'shop_email' => 'Shop E-Mail',
    'product_tpl_ver' => 'Product View Template',
    'list_tpl_ver' => 'Product List Template',
    'cache_max_age' => 'Max file cache age in seconds',
    'tc_link' => 'Link to Terms and Conditions',
    'show_plugins' => 'Include plugin products in catalog?',
    'gc_enabled'    => 'Enable Gift Cards',
    'gc_exp_days'   => 'Default Gift Card Expiration (days)',
    'tax_rate'      => 'Tax Rate',
    'gc_letters'    => 'Use Letters',
    'gc_numbers'    => 'Use Numbers',
    'gc_symbols'    => 'Use Symbols',
    'gc_prefix'     => 'Use Prefix',
    'gc_suffix'     => 'Use Suffix',
    'gc_length'     => 'Code Length',
    'gc_mask'       => 'Code Mask',
    'centerblock'   => 'Centerblock',
    'days_purge_cart' => 'Days before purging carts',
    'days_purge_pending' => 'Days before purging unpaid orders',
    'purge_sale_prices' => 'Purge Expired Sale Prices?',
    'catalog_columns' => 'Catalog Columns',
    'enable_svc_funcs' => 'Enable Service Functions',
    'shop_enabled'  => 'Enable public access?',
);

/** Language strings for the subgroup names in the config section */
$LANG_configsubgroups['shop'] = array(
    'sg_main' => 'Main Settings',
    'sg_shop'   => 'Shop Information',
    'sg_gc'     => 'Gift Cards',
);

/** Language strings for the field set names in the config section */
$LANG_fs['shop'] = array(
    'fs_main'   => 'General Settings',
    'fs_images' => 'Image Settings',
    'fs_paths'  => 'Images and Paths',
    'fs_encbtn' => 'Working Area',
    'fs_prod_defaults' => 'New Product Defaults',
    'fs_blocks' => 'Block Settings',
    'fs_debug'  => 'Debugging',
    'fs_addresses' => 'Address Collection',
    'fs_shop'   => 'Shop Details',
    'fs_gc'     => 'Gift Card Configuration',
    'fs_gc_format' => 'Gift Card Format',
);

/**
*   Language strings for the selection option names in the config section.
*
*   Item 4 is also used in functions.inc to provide a currency selector.
*/
$LANG_configselects['shop'] = array(
    0 => array('True' => 1, 'False' => 0),
    2 => array('Yes' => 1, 'No' => 0),
    5 => array('Nombre' => 'name', 'Pricio' => 'price', 'Product ID' => 'id'),
    12 => array('No access' => 0, 'Read-Only' => 2, 'Read-Write' => 3),
    13 => array('None' => 0, 'Left' => 1, 'Right' => 2, 'Both' => 3),
    14 => array('Not Available' => 0, 'Optional' => 1, 'Required' => 2),
    15 => array('Pounds' => 'lbs', 'Kilograms' => 'kgs'),
    16 => array('Allow Backordering' => 0,
            'Show in Catalog, Prevent Sales' => 1,
            'Hide from Catalog' => 2),
    17 => array('Upper-case' => 1, 'Lower-case' => 2, 'Mixed-case' => 3, 'None' => 0),
);


?>
