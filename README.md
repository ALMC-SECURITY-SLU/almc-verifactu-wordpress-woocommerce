# ALMC Electronic Invoicing for VeriFactu (WooCommerce)

> Sends your WooCommerce invoices to the Spanish Tax Agency (AEAT) under the VeriFactu e-invoicing specification (RD 1007/2023). Automatic, signed, sandbox-tested. Not affiliated with AEAT.

[![Plugin on WordPress.org](https://img.shields.io/wordpress/plugin/v/almc-verifactu)](https://wordpress.org/plugins/almc-verifactu/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/almc-verifactu)](https://wordpress.org/plugins/almc-verifactu/)
[![Rating](https://img.shields.io/wordpress/plugin/rating/almc-verifactu)](https://wordpress.org/plugins/almc-verifactu/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

By [**ALMC Security S.L.U.**](https://almc.es) — Spanish security & compliance engineering.

---

## What this plugin does

When a WooCommerce order changes to a configured status (typically `completed`), this plugin automatically:

1. Builds a Verifactu-compliant invoice from the order data
2. Sends it to the **ALMC VeriFactu SaaS** (`https://almc.es/api/verifactu/v1/`)
3. The SaaS signs it with your digital certificate (FNMT / AC representante, stored encrypted with HKDF + AES-256)
4. Submits it to **AEAT** (Spanish Tax Agency) under the Verifactu protocol
5. Stores the response (CSV receipt, hash, status) back in the order

No manual exports. No month-end panic.

## Features

- Automatic submission on order status change (configurable)
- Manual submission from each order detail page
- Full mapping of WooCommerce orders → Verifactu invoices (items, taxes, shipping, fees)
- Visual status badges per order: draft, queued, accepted, rejected, error
- Webhook receiver to keep order status in sync with AEAT
- Customizable customer NIF/CIF meta field
- **HPOS** (High-Performance Order Storage) compatible
- Support for **corrective invoices** R1–R5 (substitutive or by differences)
- Pre-validation of recipient NIF/CIF using the official Spanish control algorithm — invalid IDs are blocked locally before hitting AEAT
- Onboarding panel with 5-step checklist that auto-marks progress

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- A free [ALMC VeriFactu](https://almc.es/verifactu/register) account
- A Spanish digital certificate (FNMT or equivalent, type *AC Representación*)

## Installation

See the full step-by-step guide on the [plugin landing page](https://almc.es/verifactu/plugin/woocommerce) or in `readme.txt` (the version delivered to WordPress.org).

Short version:

```bash
# Via WP-CLI
wp plugin install https://almc.es/downloads/almc-verifactu-1.0.1.zip --activate
```

Or upload the ZIP from `Plugins > Add New > Upload Plugin`.

Then in `WooCommerce > VeriFactu`:

- Paste your API key (`vfk_test_…` for sandbox, `vfk_live_…` for production)
- Pick your series code (e.g. `A`)
- Choose the order statuses that trigger submission (recommended: `completed`)
- Save

## Architecture

```
WooCommerce order
       │ (status changes to configured trigger)
       ▼
ALMC VeriFactu plugin (this repo)
       │ POST /api/verifactu/v1/invoices  (X-Api-Key)
       ▼
ALMC VeriFactu SaaS  (https://almc.es)
       │ signs with tenant cert (HKDF + AES-256)
       │ builds Verifactu SOAP body
       ▼
AEAT (Agencia Tributaria)
       │ accepts / rejects / acknowledges
       ▼
SaaS stores CSV receipt + hash + status
       │ (optional) webhook back to plugin
       ▼
WooCommerce order metabox updated
```

## Documentation

- [Plugin landing](https://almc.es/verifactu/plugin/woocommerce)
- [Terms of service](https://almc.es/verifactu/terminos)
- [Privacy policy](https://almc.es/verifactu/privacidad)
- [Data Processing Agreement](https://almc.es/verifactu/dpa)

## Support

Open an issue here on GitHub or contact us through [almc.es/contacto](https://almc.es/contacto).

## Contributing

Pull requests welcome. Please:

1. Fork the repo
2. Create a feature branch (`git checkout -b feat/something`)
3. Run [Plugin Check](https://wordpress.org/plugins/plugin-check/) locally before opening the PR — errors must be 0
4. Open the PR against `main`

## License

GPL-2.0-or-later — same as WordPress. See [LICENSE](LICENSE).

Copyright © 2026 ALMC Security S.L.U.
