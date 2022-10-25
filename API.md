## Plugin APIs
Plugins may leverage this plugin to process payments and have their products included in the catalog.
Functions are called via `PLG_callFunctionForOnePlugin()`.

### `service_getproductinfo_<plugin_name>`
Gets general information about the product for inclusion in the catalog or to determine pricing when processing an order.
```
$args = array(
    // Item ID components
    'item_id' => array(item_id, sub_item1, sub_item2),
    // Item modifiers. May be periodically updated
    'mods'    => array('uid' => current user ID),
);

$output = array(
    'product_id'        => implode(':', $args['item_id'],
    'name'              => 'Product Name or SKU',
    'short_description' => 'Short Product Description',
    'price'             => Unit price
    'override_price' => 1,      // set if the payment price will be accepted as full payment
    'fixed_q'       => 0,     // Optional, 0 = buyer enters quanty, >1 means only that number can be purchased
);
```

### `service_handlePurchase_<plugin_name>`
Handles the purchase of the item
```
$args = array(
    'item'  => array(
        'item_id' => $Item->product_id, // Product ID as a string (item:subitem1:subitem2)
        'quantity' => $Item->quantity,  // Quantity
        'name' => $Item->item_name,     // Item name supplied by IPN
        'price' => $Item->price,        // Unit price determined from getproductinfo()
        'paid' => $Item->paid,          // Total amount paid for the line item
    ),
    'ipn_data'  => $ipn_data,   // Complete IPN information array
    'order' => $Order,      // Pass the order object, may be used in the future
 );

$output = array(        // Note: currently not used for plugin items
    'name' => $item['name'],                // Product Name
    'short_description' => $item['name'],   // Short Description
    'price' => (float)$item['price'],   // Unit price
    'expiration' => NULL,       // expiration, for downloads
    'download' => 0,            // 1 if this is a downloadable product
    'file' => '',               // download file
);
```

### `service_addCartItem_shop()`
