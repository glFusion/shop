# Shop Plugin for glFusion

This plugin provides a product catalog and shopping cart for physical
and virtual products. The following payment gateways are supported.
The fee schedule is generally accurate as of Sept 25 2022. Check the provider
websites for current information.
| Provider | Account Type | Fees | Invoicing |
| --- | --- | --- | :---: |
| Paypal Web Payments Standard | Business | 3.49% + .50 | :x: |
| Paypal Checkout | Business | 3.49% + .50 | :heavy_check_mark: |
| Square | Normal | 2.9% + .30 | :heavy_check_mark: |
| Stripe | Normal | 2.9% + .30 | :heavy_check_mark: |
| Paylike (EU) | Normal | 1.35%+ .25 EUR | :x: |
| Pay by Check | n/a | n/a | :x: |

This plugin is a replacement for the Paypal plugin and reflects the additional functionality and payment options that are included.

You must sign up with the payment providers and enter your keys in the
gateway configuration. You should also sign up for a developer or
sandbox account for testing.

If you use the Bad Behavior plugin, be sure that you whitelist your Shop IPN
URL (`shop/ipn/ipn.php`). Bad Behavior may otherwise block IPN messages
from your gateway provider.

This version of the Shop plugin requires at least version 1.0.10 of the lgLib plugin for supporting functions.

## Considerations if you have the Paypal Plugin installed
The Shop plugin includes wrapper functions to match the Paypal functions used
by external plugins, e.g. Subscription and Evlist. Since these function names
are the same they cannot be enabled while the Paypal plugin is enabled.

The wrapper functions are disabled by default during the installation of the
Shop plugin if the Paypal plugin is detected. After disabling the Paypal plugin
you should visit the Configuration area for the Shop plugin and enable them.

  - If you have Paypal version 0.6.0 or later, you can migrate data by clicking
the `Migrate from Paypal` button under the `Maintenance` menu of Shop.
This option is only shown if there are no products or categories already created
in Shop.
The Paypal plugin does not have to be enabled for this migration to work.
  - The Shop plugin is initially visible only to Administrators. Before opening the store publicly you should disable the Paypal plugin. A message is displayed on the Shop pages as a reminder.
  - You will need to manually change any autotags or other explicit links to the Paypal plugin.
  - You may wish to enable rewrite rules on your server to redirect `/paypal/` to `/shop/` for search engines and external links.

## Installation
Installation is accomplished by using the glFusion automated plugin installer.

When the Shop plugin is first installed, it is only available to members of the Root group. To open the shop publicly, set the `Enable public access` setting to `Yes` in the plugin's global configuration.


