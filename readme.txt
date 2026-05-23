=== ALMC Electronic Invoicing for VeriFactu ===
Contributors: almcsecurity
Tags: verifactu, aeat, billing, invoicing, sii
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send your store invoices to the Spanish Tax Agency (AEAT) using the public VeriFactu specification, via the ALMC SaaS. Compatible with WooCommerce.

== Description ==

ALMC Electronic Invoicing for VeriFactu connects your online store with the ALMC SaaS API (almc.es) so you can comply with the electronic invoicing specification published by the Spanish Tax Agency (AEAT - Agencia Estatal de Administracion Tributaria) under Royal Decree 1007/2023 (the "VeriFactu" specification). The plugin is compatible with WooCommerce.

This plugin is **not affiliated with or endorsed by the AEAT**. "VeriFactu" is the public technical name of the AEAT specification it implements.

Verifactu is mandatory for businesses and self-employed individuals in Spain that issue invoices using electronic billing software. This plugin automates the entire signing and submission flow so you do not have to think about it.

= Main features =

* Automatic submission of invoices to AEAT when orders are completed
* Manual submission from the order detail page
* Full mapping of WooCommerce orders to VeriFactu invoices (items, taxes, shipping, fees)
* Status panel on each order with visual badges (draft, accepted, rejected, etc.)
* Webhook receiver for automatic status updates
* Customer NIF/CIF support via customizable meta fields
* Compatible with HPOS (High-Performance Order Storage)
* Support for corrective invoices (R1-R5) substitutive or by differences

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 6.0 or higher
* PHP 7.4 or higher
* An active account on VeriFactu SaaS (almc.es/verifactu)
* A VeriFactu API key

== Installation ==

= Step 1 - Create your VeriFactu SaaS account =

Go to https://almc.es/verifactu/register and create an account with your company details (business name, VAT/Tax ID). You will receive a welcome email.

= Step 2 - Upload your digital certificate =

Log in to https://almc.es/verifactu/login and go to the "Certificates" section. Upload your representative certificate (.p12 or .pfx) along with its password. The system will encrypt it using HKDF; the password is NEVER stored in plain text. This certificate is what VeriFactu uses to sign invoices sent to AEAT.

If you do not have a digital certificate yet, you can obtain one for free from https://www.sede.fnmt.gob.es/certificados (for company representatives) or use your electronic ID.

= Step 3 - Create an invoicing series =

In the VeriFactu panel, "Series" section, create a new series (e.g. "A", "WC-2026" or any code you prefer). This series will be associated with the invoices the plugin sends from your online store.

= Step 4 - Generate an API key =

In the VeriFactu panel, "API Keys" section, click "Create new key". Give it a descriptive name (e.g. "WooCommerce - mystore.com"). The system will display the key ONLY ONCE in the format `vfk_test_...` (sandbox) or `vfk_live_...` (production). Copy it to a safe place: if you lose it you will have to generate another one.

= Step 5 - Install the plugin in WordPress =

Three options:

a) From the WordPress repository: go to Plugins > Add New, search for "ALMC Electronic Invoicing for VeriFactu" and click "Install Now" then "Activate".

b) Manually (.zip): download the `almc-electronic-invoicing-verifactu.zip` file, go to Plugins > Add New > Upload Plugin, select the zip and click "Install Now" then "Activate".

c) Via FTP: unzip the file and upload the `almc-electronic-invoicing-verifactu` folder to the `/wp-content/plugins/` directory of your installation. Then activate it from the Plugins menu.

= Step 6 - Configure the VeriFactu connection =

Go to WooCommerce > VeriFactu in the admin sidebar. Fill in:

* "API Key": paste the key generated in Step 4
* "Series Code": the series code created in Step 3 (e.g. "A")
* "Environment": choose "Sandbox" for testing or "Production" when ready to issue real invoices

Click "Save changes". Then click "Test connection" to verify everything is working. If everything is fine you will see a green message with the active plan.

= Step 7 - Configure the customer NIF field =

On the same settings page, in "NIF meta field", indicate the name of the meta field where your checkout stores the customer NIF. The most common values:

* `_billing_nif` (Spanish checkout plugins such as Spain Tax ID)
* `_billing_vat` (European variants)
* `_billing_cif`
* `billing_dni` (some custom checkout plugins)

If you do not know which one your checkout uses, leave the default `_billing_nif` or check the database of your first test order (table `wp_postmeta`).

= Step 8 - Enable automatic submission (optional but recommended) =

In the "Automatic submission" section, check "Enable automatic submission" and choose the order statuses that trigger the submission. The most common:

* `completed` (recommended for cash-on-delivery stores)
* `processing` (if you bill before physical shipment is complete)

You can also leave it disabled and submit manually from each order.

= Step 9 - Configure the webhook (optional) =

If you want VeriFactu to notify you of invoice status changes (accepted / rejected by AEAT) in real time, copy the URL shown in the plugin "Webhook" section and paste it into the VeriFactu panel, "Webhooks" section. The HMAC signing secret is generated automatically.

= Step 10 - First test order =

With the environment set to "Sandbox":

1. Create a test product in your store
2. Place an order to yourself (use a customer with a valid NIF/CIF)
3. Mark the order as "Completed"
4. In the order detail you will see the "VeriFactu" metabox with the status and assigned invoice number
5. If everything is fine: the status will become "Accepted" in a few seconds and you will see the AEAT receipt number (CSV)

Once this flow works in sandbox, switch the environment to "Production", save and start issuing real invoices.

= Troubleshooting =

* **"Invalid recipient NIF"**: the plugin pre-validates the NIF before sending. Make sure the customer has a NIF/CIF that complies with the Spanish control algorithm. If your checkout does not validate it, consider using a plugin such as "Spain Tax ID" for WooCommerce.

* **"API key not configured"**: double-check that you pasted the key correctly (starts with `vfk_test_` or `vfk_live_`).

* **The order status changes but the invoice is not sent**: check that you marked the corresponding status under "Automatic submission" and that the API connection responds OK with "Test connection".

== Frequently Asked Questions ==

= Where do I get my API key? =

Register at almc.es/verifactu to get an account and your API key. The free plan includes 10 invoices per month.

= What field is used for the customer NIF? =

By default, the plugin looks for the meta field `_billing_nif`. If you use a checkout plugin that stores the NIF in another field (for example `_billing_vat` or `_billing_cif`), you can configure it in the settings.

= Can I submit invoices manually? =

Yes. On the order detail page you will find a VeriFactu metabox with a button to submit manually.

= Are invoices submitted automatically? =

Only if you enable the "Automatic submission" option in the settings. You can choose which order statuses trigger the submission (completed, processing, etc.).

= Is it compatible with HPOS? =

Yes. The plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= What happens if an invoice is rejected? =

The plugin records the error in the order notes and shows the "Rejected" status in the metabox. You can correct the data and resubmit.

== External services ==

This plugin connects to the ALMC SaaS API for VeriFactu (`https://almc.es/api/verifactu/v1/`) to register your order invoices with AEAT (the Spanish Tax Agency) under the VeriFactu specification (Royal Decree 1007/2023). The plugin is not affiliated with AEAT.

**What data is sent and when:**

Every time submission is triggered (manually from an order or automatically when the order changes to a configured status), the plugin sends the following order data to the API:

* Order data: number, date, description, totals (taxable base, VAT, total)
* Order items: description, quantity, unit price, tax rate
* Recipient data: name/business name, NIF/CIF, country
* Authentication credentials: your VeriFactu API key (header `X-Api-Key`)

This data is processed by ALMC and signed with your digital certificate (stored encrypted in VeriFactu SaaS) before being forwarded to AEAT.

**When data is NOT sent:**

* If you have not entered an API key in the settings
* If the order already has an associated invoice (duplicates are avoided)
* If you have disabled automatic submission and do not submit manually

**Service provider:**

* Name: ALMC Security S.L.U.
* Website: https://almc.es/verifactu
* Terms of service: https://almc.es/verifactu/terminos
* Privacy policy: https://almc.es/verifactu/privacidad
* Data Processing Agreement (DPA): https://almc.es/verifactu/dpa

By activating this plugin and entering your API key you accept that the data indicated above is processed by ALMC for the purpose described. The invoice is delivered to AEAT under your fiscal obligation as the issuer; ALMC acts as data processor.

== Privacy ==

This plugin does not store additional personal data in your WordPress installation beyond the standard WooCommerce order meta fields (which are already subject to your store privacy policy). The UUID of the issued invoice and its current status are stored as order meta to avoid duplications and display the status in the panel.

== Changelog ==

= 1.1.0 =
* Security hardening release.
* Added `uninstall.php` that cleans up plugin options, transients and object-cache entries when the plugin is deleted from the WordPress admin. Per-order invoice metadata is intentionally preserved (4-year fiscal retention under Spanish RD 1007/2023).
* Added replay-attack protection on the webhook receiver: when the SaaS sends an `X-Webhook-Timestamp` header it is bound into the HMAC signature, and timestamps drifting more than 5 minutes from local time are rejected (backwards-compatible with v1.0.x SaaS that did not yet send the header).
* Added rate limiting on the webhook endpoint: after 60 failed-signature attempts per IP in a 60-second window the endpoint returns HTTP 429 to throttle brute-force / DoS attempts.
* Added body-size guardrail on the webhook endpoint: requests larger than 100 KiB are rejected with HTTP 413 before reading `php://input`.
* Added at-rest encryption for sensitive options (`almc_vf_api_key`, `almc_vf_api_secret`, `almc_vf_webhook_secret`) using `sodium_crypto_secretbox` with a 32-byte key derived from `wp_salt('auth')` via HKDF-SHA256. Migration is lazy: previously-saved plaintext values are still readable, and the first re-save upgrades them in place.

= 1.0.1 =
* Renamed plugin to "ALMC Electronic Invoicing for VeriFactu" and slug to "almc-electronic-invoicing-verifactu" to clarify the AEAT specification reference and remove any implied affiliation.
* Replaced inline `<style>` block in the onboarding panel with `wp_enqueue_style()` (assets/css/almc-onboarding.css).
* Replaced inline `<script>` in the setup-notice dismiss handler with `wp_enqueue_script()` (assets/js/almc-setup-notice.js).
* Hardened webhook input handling: decoded JSON payload now goes through an explicit whitelist + per-field sanitization before being passed to any `do_action` callback.
* Internationalisation: updated text-domain to the new slug across all PHP files.

= 1.0.0 =
* Initial version.
* Automatic and manual invoice submission.
* Full mapping of orders to VeriFactu invoices.
* Order status panel with visual badges.
* Webhook receiver.
* HPOS compatibility.
* Settings page with connection test.
* Support for corrective invoices R1-R5.

== Upgrade Notice ==

= 1.1.0 =
Security hardening release: webhook replay-attack protection, rate limit, body-size guard, at-rest encryption for stored credentials, and a proper uninstall cleanup. Upgrade strongly recommended.

= 1.0.1 =
Name/slug change and security hardening for WordPress.org compliance. Update recommended.

= 1.0.0 =
Initial plugin release.
