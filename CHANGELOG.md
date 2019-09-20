# Shop plugin for glFusion - Changelog

## v1.0.0
Release TBD

  * Install the Square SDK from composer
  * Use an OrderLineItem for tax with Square so it shows on the order form.
  * Allow products to set the `cancel_return` value in Buy Now buttons.
  * Move admin list functions into the respective classes.
  * Fix authorize.net setting paid status.
  * Fix shipping selection not correctly updating total on cart view.
  * Allow use of SKU as product ID in URLs.
  * Item options can now have SKU values.
  * Implement Attribute Groups to control attribute ordering on product form.
  * Move catalog-related admin options under one menu to shorten the admin menu.
  * Product links from orders include selected options for logged-in users.
  * Add authorized group to shippers to limit usage to trusted members.
  * Add radio and checkbox-type product options.
  * No buy-now buttons for physical items that may require shipping.
  * Paypal data is not automatically migrated during installation.
  * Allow drag-and-drop uploads and rapiid AJAX deletion for product images.
  * Enable Facebook catalog feeds.
  * Separate ProductImage class into Images\Product.
  * Pass query string to product detail view for highlighting.
  * Add `View Cart` button to shop header when cart has items.

## v0.7.1
Release 2019-08-02

  * Add pending shipments by shipper report to ship items in batches.
  * New option to update multiple order statuses from certain reports.
  * Further secure the payment cancellation URL using a token.
  * Fix error creating new carts for anon buyers.
  * Log order numbers with IPN messages.
  * Don't log zero payment amounts to order log.
  * Fix setting closed status on download-only orders.
  * Add shipper option to ignore product per-item shipping.
  * Reset product rating if ratings are later disabled.
  * Incorporate sort option into product search form.
  * Fix setting enabled flag for plugin products.
  * Return to editing page when adding a product and the image uploads fail.
  * Fix showing previous item's image on order when next item has no image.
  * Clear sitemap cache when changing products or categories.
  * Add links to admin area and account page from the catalog list.
  * Make IPN URL override global to all gateways.
  * Fix `valid_to` date entry for shippers.
  * Fix empty shipping rates not being saved, e.g. free shipping.
  * Add stripe.com payment gateway.
  * Don't check cart email address when logging in

## v0.7.0
Release 2019-06-02

First beta version under the new Shop name.
These are the changes from version 0.6.1 of the Paypal plugin (https://github.com/leegarner-glfusion/paypal).

  * Allow the shop to be disabled except for administrators.
  * Properly merge anonymous user cart to user cart upon login.
  * Implement more friendly URLs.
  * Log all non-money payment messages, e.g. payment by gift card, to the order.
  * Update Authorize.Net IPN to handle Webhook (preferred) or Silent URL.
  * Save the last-selected gateway to pre-fill subsequent order forms.
  * Speed checkout by pre-filling default addresses
  * Remove masking of gift card numbers for display
  * Order Workflows and Statuses can no longer be re-ordered.
  * Add order, payment and pending shipment reports.
  * Enable language localization for order status notifications.
  * Move original product and category images under private/data.
  * Add service function for plugins to send gift cards.
  * Return no search results if shop is not publicly enabled.
  * Fix quantity discount price if items are added to the cart to meet the qty.
  * Indicate that unit prices in the cart include discounts, if applicable.
  * Allow items to be removed from cart by setting qty to zero.
  * Return to original page after editing a product.
  * Remove payment status from packing list.
  * Add option to purge all transactions, to purge test data before go-live.
  * Better interface to add and remove shipping rates.
  * Fix extracting IPN data from Paypal Buy-Now and Donate functions.
  * Add item thumbnail image to order.
  * Original price wasn't updated if attributes were selected for sale items.
  * Smarter shipping module with better packing.
  * Validate that products are still available prior to final checkout.
  * Allow special fields for products other than simple text input.
  * Put plugin and coupon products in their own namespace.
  * Strip HTML tags from special field data values.
  * Use a checkbox instead of link for deleting images from products.
  * Standardize logging, use glFusion 2.0-compatible log levels.
  * Add shipper name to printed order and packing list.
  * Enable add-to-cart button on catalog list page, enabled in V3 list.
  * Deprecate max image size, images are resized before display.
  * Enable batch printing of PDF orders and packing lists.
  * Add option to show categories on homepage instead of product list.
  * Remove `max_images` config, allow multiple images uploaded from product form.
  * Automatically close paid orders that have only downloadable items.
  * Enable product ratings for plugin products.
