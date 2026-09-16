# IT-Kayali Translate

**Current development version: v0.12.13**

IT-Kayali Translate is a modular multilingual WordPress plugin focused first on a complete, stable multilingual storefront for the current project. Commercial licensing/distribution work is intentionally postponed until the plugin itself is finished and stable.

## Current confirmed state

- v0.12.10 fixed the WooCommerce **My Account** language-endpoint problem on the real staging site.
- The repository root contains the installable plugin source directly.
- WooCommerce products remain one physical product ID across all languages.
- WordPress/Elementor content, WooCommerce product fields, taxonomy labels, routing, string translation, frontend live translation and SEO foundations are already present.

## v0.12.11–v0.12.12 – frontend/dynamic runtime phase

The backend page **IT-Kayali Translate → Frontend Texte** automatically discovers supported visible storefront strings while an administrator browses the configured default language.

The catalog covers Shop, Search, Product, Category/archive, Filters, Mini-Cart, Cart, Checkout, My Account, Wishlist, Popups/Offcanvas, Notices/Errors, Header, Footer, Menu and Other.

Discovery also includes supported `placeholder`, `aria-label`, `title`, button-value and select-option text. A modular `ITKT_Dynamic_Runtime` re-applies translations after WooCommerce/WoodMart/AJAX/Blocks updates and adds JavaScript plural handling for `@wordpress/i18n` `ngettext` and `ngettext_with_context`.

## v0.12.13 – Backup / Restore

A new **IT-Kayali Translate → Backup / Restore** page provides a plugin-owned JSON backup for:

- ITKT settings and language configuration
- string/source/translation tables
- frontend/global string translations
- product translation metadata
- taxonomy translation metadata
- linked translated WordPress content snapshots

Restore replaces only ITKT-managed translation data. It does **not** create duplicate products, pages, posts or taxonomy terms. Existing translated content is updated by its existing WordPress ID; missing IDs are reported and skipped. Derived rewrite/search/runtime state is rebuilt after restore.

Export and restore are restricted to administrators and protected by WordPress nonces. Restore accepts only the ITKT backup format and requires an explicit confirmation checkbox.

### Verification status

v0.12.13 passes local PHP syntax validation and installation ZIP integrity validation. The Backup/Restore workflow still needs one real staging export-and-restore test before P6 is marked complete.

The v0.12.11/v0.12.12 frontend-catalog and dynamic-runtime work also still requires systematic real-site verification across the WooCommerce flows below.

## Product priorities before calling the plugin finished

The current goal is a finished production plugin, not a sellable marketplace product. The remaining work is:

1. Complete WooCommerce end-to-end verification in DE/EN/AR: Shop, Search, Product, Categories, Filters, Mini-Cart, Cart, Checkout, My Account, Wishlist and Popups/Offcanvas. Language switching must stay on the same logical destination.
2. Finish/refine the backend frontend-text table: reduce false/duplicate technical strings, improve area detection, and verify direct translation for all active languages.
3. Complete real-site dynamic WooCommerce/WoodMart/AJAX/Blocks coverage, including payment, shipping, validation, notices and checkout labels.
4. Expand/verify live translation for categories, menus, global strings, Header/Footer, WoodMart content, Popups and Offcanvas/Drawer.
5. Finish and verify JavaScript i18n for dynamic strings, safe plural handling and AJAX/fragment rerenders.
6. Verify the new Backup/Restore flow on staging with a real export, deliberate test change and successful restore.
7. Complete performance/cache testing for many products/strings, WooCommerce/WoodMart AJAX, WooCommerce Blocks, WP Fastest Cache, IONOS cache and full-page caching.

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
