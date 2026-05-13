# ALMC VeriFactu for WordPress

> Sends your WooCommerce invoices to the Spanish Tax Agency (AEAT) under the Verifactu e-invoicing regulation (RD 1007/2023). Automatic, signed, sandboxed-tested.

[![Plugin on WordPress.org](https://img.shields.io/wordpress/plugin/v/almc-verifactu)](https://wordpress.org/plugins/almc-verifactu/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/almc-verifactu)](https://wordpress.org/plugins/almc-verifactu/)
[![Rating](https://img.shields.io/wordpress/plugin/rating/almc-verifactu)](https://wordpress.org/plugins/almc-verifactu/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

By [**ALMC Security S.L.U.**](https://almc.es) — Spanish security & compliance engineering since 2024.

---

### What this plugin does

When a WooCommerce order changes to a configured status (typically `completed`), this plugin automatically:

1. Builds a Verifactu-compliant invoice from the order data
2. Sends it to the **ALMC VeriFactu SaaS** (`https://almc.es/api/verifactu/v1/`)
3. The SaaS signs it with your digital certificate (FNMT / AC representante, stored encrypted with HKDF + AES-256)
4. Submits it to **AEAT** (Spanish Tax Agency) under the Verifactu protocol
5. Stores the response (CSV receipt, status) back in the order

No more manual exports. No more month-end panic.

### Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- A free [ALMC VeriFactu](https://almc.es/verifactu/register) account
- A Spanish digital certificate (FNMT or equivalent)

### Quickstart

See the [Installation guide](https://almc.es/verifactu/plugin/woocommerce) on our website.

### License

GPL-2.0-or-later — same as WordPress.
