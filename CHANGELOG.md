# Shop plugin for glFusion - Changelog

## v0.7.0
Release TBD

First beta version under the new Shop name.

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
