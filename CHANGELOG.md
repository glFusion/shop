# Shop plugin for glFusion - Changelog

## v1.3.0
Release TBD
  * Discounts may not be applied to all products, e.g. gift cards.
  * Remove `paid` as an order status, check total paid instead.
  * Update Stripe API to 7.65.0 - 2020-11-19.
  * Update Square API to 3.20200325.0.
  * Add interface for customers to edit addresses outside of orders.
  * Add fast-checkout where the order allows it (no shipping or tax needed).
  * Enable sales tax charges on shipping and handling.
  * Add invoicing via Paypal, Square and Stripe invoice APIs.
  * Link to customer in order report now filters report.
  * Enable/disable donation buttons in Paypal gateway config.
  * Allow user-entered prices for some plugin items, e.g. Donations.
  * Shipping methods may not require a shipping address, e.g. will-call.
  * Shipping methods, e.g. will-call, may set tax based on origin vs. destination.
  * Use the shop.admin privilege for access control instead of a separate admin group.
  * SQL fixes to work with MySQL's strict mode.
  * Update checkout workflow, steps are easier on mobile devices.
  * Checkout workflows can no longer be disabled. Unnecessary steps are skipped.
  * Enable API-based shipping quotes for UPS, USPS and FedEx.
  * Enable free-shipping thresholds per shipper.
  * New address entry form defaults to the shop's city and country.
  * Zone rules can apply to downloads using IP geolocation.
  * Zone rules can be applied to categories as well as products.
  * Remove unnecessary `cache_max_age` setting for blocks.
  * Add button to reset multiple products' ratings.
  * Enable plugin-style installation of payment gateways.
  * Fix: OrderItem was using default option price instead of variant price.
  * Fix: Updated supplier/brand logos not shown due to image caching.

## v1.2.1
Release 2020-03-02
  * Make sure `supplier_id` and `brand_id` fields are added.

## v1.2.0
Release 2020-02-29
  * Add product image sorting.
  * Add static features/attributes.
  * Add Supplier Reference field to products and variants for ordering.
  * Product cloning includes Features, Categories, Variants and Images.
  * Allow a default variant to be set which will be shown first.
  * A subset of product images can be assigned to each variant.
  * New reorder report listing items at or below their reorder qty.
  * Allow plugins to use the catalog display by calling index.php with `category=pi_name`.
  * Restrict product sales by region.
  * Fixes to gateway return urls to remove url parameters.

## v1.1.2
Release 2020-02-15
  * Add missing phone field to address table for new installations.
  * Add missing discount code price to order item table for new installations.

## v1.1.1
Release 2020-01-27
  * Fix PDF creation.

## v1.1.0
Release 2020-01-22

  * Add sales tax calculation based on shipping address.
  * Add phone number field to addresses.
  * Add discount codes.
  * Allow products to be related to multiple categories.
  * Create product variants for specific option combinations.
  * Implement table to store saved supplier and brand information.
  * Implement bulk updates for some product fields.
  * Implement address validation for user-entered addresses.
  * Add Brand and Supplier information for products.
  * Add tables for regions, countries, states and allow sales restrictions.
  * Add specific gateway to handle free orders.

## v1.0.1
Release 2019-12-24

  * Fix UTF-8 key length issue for cache table

## v1.0.0
Release 2019-12-22

  * Install the Square SDK from composer
  * Use an OrderLineItem for tax with Square so it shows on the order form.
  * Allow products to set the `cancel_return` value in Buy Now buttons.
  * Move admin list functions into the respective classes.
  * Fix authorize.net setting paid status.
  * Fix shipping selection not correctly updating total on cart view.
  * Allow use of SKU as product ID in URLs.
  * Item options can now have SKU values.
  * Implement Option Groups to control option field ordering on product form.
  * Move catalog-related admin options under one menu to shorten the admin menu.
  * Product links from orders include selected options for logged-in users.
  * Add authorized group to shippers to limit usage to trusted members.
  * Add radio and checkbox-type product options.
  * No buy-now buttons for physical items that may require shipping.
  * Paypal data is not automatically migrated during installation.
  * Allow drag-and-drop uploads and rapiid AJAX deletion for images.
  * Enable Facebook catalog feeds. See https://developers.facebook.com/docs/marketing-api/catalog-feed-setup.
  * Add optional `brand` field for products to support catalog feeds.
  * Separate ProductImage class into Images\Product.
  * Pass query string to product detail view for highlighting.
  * Add `View Cart` button to shop header when cart has items.
  * Add shipping and tracking for orders.
  * Update PDF creation, require lgLib 1.0.9+
  * Replace Orders main menu option, add shipment listing.
  * Allow coupons to be voided by administrators.
  * Create packing lists for each shipment.
  * Add authorized group to payment gateways, for net terms.
  * Include a new FileUpload class for more consistent upload/download behavior.
  * Allow deletion of unused shippers.
  * Order and Packing List print icons now create PDF output.
  * Allow use of PNG and GIF product/category images as well as JPEG.
  * Add general comment input to orders.
  * Fix location of category links template for catalog listings.
  * Break up the shop address into component fields.
  * Implement package tracking API for UPS, FedEx, DHL and USPS.
  * Add configuration option to select the default administrative view.
  * Add default product type configuration option.
  * Just show raw IPN data array in reports to allow nested array formats.
  * Download all product files as attachments.
  * Key fields in gateways configurations are encrypted at rest.
  * Add `max_order_qty` field to products, use number input field to limit qty.
  * Refactor SQL queries to work with MySQL `strict mode`.

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
