# IT-Kayali Translate

**Current development version: v0.12.17**

IT-Kayali Translate is a modular multilingual WordPress plugin focused on a stable multilingual storefront for the current production project. Commercial licensing, customer updater infrastructure and marketplace preparation are intentionally postponed until later.

## Current state

- WooCommerce products remain one physical product ID across all languages.
- WordPress/Elementor content, product fields, taxonomies, attributes, string translation, routing, SEO, frontend live translation and RTL foundations are implemented.
- v0.12.10 fixed the WooCommerce **My Account** non-default-language endpoint problem and was confirmed on the real staging site.
- v0.12.11 added the admin-only **Frontend Texte** catalog with direct translation fields.
- v0.12.12 added dynamic WooCommerce/WoodMart/AJAX/Blocks runtime translation and JavaScript plural support.
- v0.12.13 added JSON **Backup / Restore** without creating duplicate products, pages, posts or terms.
- v0.12.17 is the current production-finalization candidate with a version-bound final acceptance checklist and exportable report.


## v0.12.17 – final production acceptance assistant

**Systemstatus** now contains a version-bound real-browser acceptance matrix. It is intentionally manual: routes, WoodMart drawers, AJAX/Blocks refreshes, checkout validation, cache behaviour and mobile interaction cannot be proven safely by a server-only self-test.

For every active language the matrix covers Shop, Search, Product, Category, Filters, Mini-Cart, Cart, Checkout, My Account, Wishlist, Popups/Offcanvas and same-context language switching. Cross-cutting checks cover Frontend Texte, WooCommerce AJAX/Blocks, Live Editor, Backup/Restore, cache visibility, transactional e-mail language and mobile/tablet behaviour.

The Systemstatus dashboard shows a production-acceptance percentage. A version is shown as ready only when all current-version manual checks are marked complete and no critical automated self-test is failing. The complete result can be exported as a JSON acceptance report for support or handoff.

## v0.12.16 – production preflight

The existing **Systemstatus** self-test now checks more of the real production prerequisites before final sign-off:

- all three String Translation database tables
- whether the Frontend Texte catalog has already captured live storefront strings
- WooCommerce Shop, Cart, Checkout and My Account page assignments
- central My Account endpoint configuration
- registration of the WooCommerce transactional-email language isolation
- availability of Dynamic Runtime, Backup/Restore and cache invalidation
- WP Fastest Cache integration when the cache plugin is detected

These checks do not replace the browser acceptance matrix, but they catch configuration/schema problems before manual DE/EN/AR testing.

## v0.12.15 – default-language frontend overrides

The **Frontend Texte** table now includes the configured default language as an optional override column. This is intended for third-party labels that remain in their source language even on the default storefront, for example English WoodMart/WooCommerce UI labels on a German shop. Leaving the default-language field empty keeps the original/native value.

The same explicit override model is available to live/global/native frontend strings. Global, visual, PHP gettext and JavaScript runtime maps can now honor a saved override for the default language. The frontend also refreshes the runtime map after page load for the default language, so a full-page cache cannot permanently pin an older default-language label.

## v0.12.14 – production finalization

### WooCommerce route stability

The language switcher now treats WooCommerce system pages as physical system pages before normal translated page routing:

- Shop
- Cart
- Checkout
- Checkout `order-pay`
- Checkout `order-received`
- My Account and its endpoints

This prevents a language switch from accidentally jumping to a translated duplicate, dashboard or unrelated page. Existing filter/search/query parameters are preserved when safe.

### Frontend live translation coverage

The live translation runtime now distinguishes more reusable areas:

- Header
- Footer / widgets
- Menu
- Mini-Cart
- Cart
- Checkout
- My Account
- Wishlist
- Popups / Offcanvas / Drawer
- Notices / Errors
- Filters
- Shop / Search
- shared WooCommerce and widget strings

Reusable shared areas are stored as global strings, while product/category-specific content can continue to use product/taxonomy translation models instead of unsafe global replacement.

### JavaScript i18n / plurals

Dynamic `@wordpress/i18n` plural handling now uses `Intl.PluralRules` when available and publishes locale-aware native forms. Arabic receives zero/one/two/few/many/other categories from the native catalog where available. Browsers without `Intl.PluralRules` keep the safe singular/plural fallback.

### Performance and cache handling

- Runtime gettext/global/visual/JS/plural maps use a translation-generation cache key.
- Repeated frontend database work is reduced when an object cache is available.
- Translation saves invalidate ITKT runtime generations.
- Cache invalidation requests are coalesced and run once per request.
- WordPress object cache is flushed after translation changes.
- WP Fastest Cache is cleared automatically when its supported programmatic API is available.
- A public `itkt_after_cache_purge` action remains available for hosting/server-cache integrations without hard-coding vendor internals.
- Successful Backup/Restore also invalidates runtime/cache state.

## Frontend Texte

**IT-Kayali Translate → Frontend Texte** automatically discovers supported visible storefront text while an administrator browses the configured default language. It groups strings by Shop, Search, Product, Category/archive, Filters, Mini-Cart, Cart, Checkout, My Account, Wishlist, Popups/Offcanvas, Notices/Errors, Header, Footer, Menu and Other.

Discovery includes supported visible text plus `placeholder`, `aria-label`, `title`, button values and select options. Dynamic WooCommerce/AJAX/fragment content is rescanned after rerenders. Common prices, quantities and technical values are intentionally excluded.

## Backup / Restore

**IT-Kayali Translate → Backup / Restore** exports a plugin-owned JSON backup containing:

- ITKT settings and active language configuration
- ITKT string/source/translation tables
- frontend/global string translations
- product translation metadata
- taxonomy/attribute translation metadata
- linked translated WordPress content snapshots

Restore updates only existing IDs and never creates duplicate products/pages/posts/terms. Missing IDs are skipped and reported.

## Final acceptance before calling the production plugin fully finished

The code-side work for the requested production scope is implemented. The remaining step is one complete real-site acceptance pass on staging/production-like data:

1. DE / EN / AR: Shop, Search, Product, Category, Filters, Mini-Cart, Cart, Checkout, My Account, Wishlist, Popups/Offcanvas.
2. Switch language on each relevant screen and confirm the same logical target remains open.
3. Verify Frontend Texte classification and direct EN/AR translation.
4. Trigger WooCommerce notices/errors, shipping/payment updates and AJAX/Blocks rerenders and confirm translations remain applied.
5. Check Live Translation in Header/Footer/Menu/Popup/Offcanvas/Wishlist/Notices.
6. Export a backup, change one harmless translation, restore the backup and confirm the old value returns without duplicates.
7. Test public pages with WP Fastest Cache and the IONOS/server cache enabled, including after saving a translation.

Do not mark an item complete until the real-site test confirms it.

## Main architecture rules

- WooCommerce products are never duplicated per language.
- Prices, SKU, stock, images, variants and technical product data stay shared.
- Theme/plugin source files are never modified for translations.
- Frontend language remains independent from the WordPress admin language.
- RTL can affect text/content without forcing Header/Footer/layout mirroring.
- Automatic translation handling must not alter CSS, IDs, markup structure, images or technical URLs.
- Transactional emails/invoices stay in the configured default storefront language unless deliberately changed later.

## Supported languages

DE, AR, EN, FR, ES, TR, SV and NL are available. Active languages determine the translation columns/workflows shown in the admin.

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

The repository root is the plugin source. GitHub **Code → Download ZIP** can be used as a source archive; tested development builds are also delivered as installable WordPress ZIP files in chat.

## New-chat handoff rule

Before changing the plugin in a new chat, read `README.md`, `CHANGELOG.md`, `AGENDA.md` and the current source. Every functional release must synchronize the plugin header, `ITKT_VERSION`, `readme.txt` Stable tag, GitHub source/docs and the installable ZIP.

## Website

IT-Kayali: https://it-kayali.de
