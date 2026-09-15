# IT-Kayali Translate

**Current development version: v0.12.11**

IT-Kayali Translate is a modular multilingual WordPress plugin focused first on a complete, stable multilingual storefront for the current project. Commercial licensing/distribution work is intentionally postponed until the plugin itself is finished and stable.

## Current confirmed state

- v0.12.10 fixed the WooCommerce **My Account** language-endpoint problem on the real staging site.
- The repository root contains the installable plugin source directly.
- WooCommerce products remain one physical product ID across all languages.
- WordPress/Elementor content, WooCommerce product fields, taxonomy labels, routing, string translation, frontend live translation and SEO foundations are already present.

## v0.12.11 – current work

v0.12.11 starts the finalization phase requested for the production plugin.

### New: Frontend Texte

A new backend page **IT-Kayali Translate → Frontend Texte** automatically collects supported visible texts while an administrator browses the configured default language.

The catalog groups texts into areas such as:

- Shop
- Search
- Product page
- Category/archive
- Filters
- Mini-Cart
- Cart
- Checkout
- My Account
- Wishlist
- Popups / Offcanvas
- Header
- Footer
- Menu
- Other

Translations can be entered directly in the table for every active target language. Captured texts reuse the existing ITKT global-string runtime, so saved translations can be applied without changing WooCommerce, WoodMart or theme source files.

The collector is intentionally **admin-only** and runs only in the default storefront language. It rescans dynamically inserted AJAX/fragment content and WooCommerce cart/checkout events. Common price, quantity and technical-value containers are excluded from collection.

### Verification status

The v0.12.11 source passes PHP syntax checks, JavaScript syntax checks and ZIP integrity validation. **Real staging verification of the new Frontend Texte workflow is still required before this feature is marked complete.**

## Product priorities before calling the plugin finished

The current goal is a finished production plugin, not a sellable marketplace product. The remaining priorities are:

1. Complete WooCommerce end-to-end verification in DE/EN/AR first: Shop, Search, Product, Categories, Filters, Mini-Cart, Cart, Checkout, My Account, Wishlist and Popups/Offcanvas. Language switching must stay on the same logical destination.
2. Finish the backend frontend-text table and make every discovered supported frontend string directly editable per language.
3. Complete dynamic WooCommerce/WoodMart/AJAX/Blocks translation coverage, including payment, shipping, validation, notices and checkout labels.
4. Expand live translation to categories, menus, global strings, Header/Footer, WoodMart content and Popups.
5. Finish JavaScript i18n, including dynamic strings and safe plural handling.
6. Add Backup/Restore for plugin settings and translation data.
7. Complete performance/cache testing for large shops, AJAX, WP Fastest Cache, IONOS cache and related caching layers.

Commercial licensing, customer updater infrastructure and marketplace preparation are deferred until later.

## Main architecture rules

- WooCommerce products are never duplicated per language.
- Prices, SKU, stock, images, variants and technical product data stay shared.
- Theme/plugin source files are read-only; translations are stored separately.
- Frontend language is independent from WordPress admin language.
- RTL can affect text/content without forcing Header/Footer/layout mirroring.
- Automatic translation handling must not modify CSS, IDs, markup structure, images or technical URLs.
- Transactional emails/invoices should remain in the configured standard language unless explicitly changed later.
- New integrations should remain modular/adaptable rather than project-specific core hacks.

## Supported languages

Current language management includes DE, AR, EN, FR, ES, TR, SV and NL. Active languages determine which translation columns/workflows are shown.

## Repository structure

```text
admin/
includes/
public/
it-kayali-translate.php
readme.txt
uninstall.php
README.md
CHANGELOG.md
AGENDA.md
```

The repository root is the plugin source. GitHub **Code → Download ZIP** can be used as a source archive; finished development versions are additionally delivered as installable WordPress ZIPs in chat.

## New-chat handoff rule

Before changing the plugin in a new chat, read:

1. `README.md`
2. `CHANGELOG.md`
3. `AGENDA.md`
4. the current plugin source

For every new functional version, keep synchronized:

- plugin header version
- `ITKT_VERSION`
- WordPress `readme.txt` Stable tag
- `README.md`
- `CHANGELOG.md`
- `AGENDA.md`
- GitHub source
- installable ZIP delivered in chat

Do not mark a bug/feature complete until the relevant real-site test confirms it.

## Website

IT-Kayali: https://it-kayali.de
