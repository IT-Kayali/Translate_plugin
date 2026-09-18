# Changelog

All notable development changes to **IT-Kayali Translate** are recorded here.

Current development version: **0.12.22**.



## [0.12.22]

### Runtime / AJAX production hardening
- Added a shared debounced runtime-refresh scheduler for cached/AJAX WooCommerce storefront updates.
- Concurrent refresh attempts are coalesced; changes arriving during an active request trigger at most one follow-up refresh.
- Removed duplicate WooCommerce cart/fragment listeners from the dynamic runtime and kept only its additional Blocks/checkout events.
- Public runtime-string AJAX is now GET-only and requires the plugin's XMLHttpRequest request shape.
- Runtime cart translation no longer calls `wc_load_cart()` for browsers with no existing WooCommerce/cart session cookies.

### Validation
- PHP and JavaScript syntax checks pass.
- Static regression checks verify the shared scheduler, duplicate-listener removal, runtime endpoint guards and cart-session hint guard.
- Real checkout/cart/Blocks testing remains required before production confirmation.

## [0.12.21]

### Frontend routing / action replay hardening
- Centralized query-argument sanitization for language-switch URLs.
- Read-only scalar storefront state remains preserved, while WooCommerce/cart/wishlist/order action parameters, AJAX action parameters and nonce/security values are removed.
- Checkout `order-pay` and `order-received` keep their required `key` / `pay_for_order` context only when the current request is one of those endpoints.
- Translated-slug canonical redirects now use the same safe query-state sanitizer.
- SEO translated-slug redirects now bypass Cart, Checkout/order-pay/order-received and My Account routes so stateful WooCommerce endpoint paths are preserved.
- Added the `itkt_language_switch_query_args` filter for site-specific read-only query parameters.

### Validation
- PHP and JavaScript syntax checks pass.
- Query sanitizer regression harness verifies filter preservation, action/nonce removal and checkout-auth exception handling.

## [0.12.20]

### Product import hardening
- Added a 25 MB upload limit for product translation CSV/XLSX imports.
- Added row, column, cell and parsed XML size limits to reduce memory/CPU abuse from malformed imports.
- XLSX XML now rejects `DOCTYPE` / `ENTITY` declarations and is parsed with `LIBXML_NONET`.
- Workbook relationship targets are restricted to worksheet XML under `xl/worksheets/`.
- Duplicate normalized headers are rejected before data changes start.
- Duplicate rows resolving to the same product are skipped.
- Rows containing both Product ID and SKU now require the current SKU to match, preventing stale/cross-site IDs from updating the wrong product.
- Product-level `edit_post` permission is verified for each resolved product before saving translations.

### Validation
- PHP and JavaScript syntax checks remain required before ZIP handoff.
- Real-site import and storefront acceptance are still required before marking the build production-confirmed.

## [0.12.19]

### Backup / Restore production hardening
- Restore now verifies that the backup belongs to the same WordPress site/network context before applying ID-based data.
- Rejects incomplete backup payloads before any destructive restore operation starts.
- Validates String Translation table relationships before replacing table contents.
- Post/term `_itkt_*` metadata is restored only for object IDs that still exist; skipped metadata is counted and reported.
- Linked translated-content snapshots are restored only when the current post type still matches the backup snapshot.
- String schema/dbDelta preparation now runs before the data transaction to avoid DDL implicitly breaking rollback guarantees.
- Failed restores flush the WordPress object cache after rollback to avoid stale cached values.

### Scope
- No commercial/licensing work was added.
- This release closes a production-safety gap in the existing Backup / Restore feature.

## [0.12.18]

### Production consistency
- Removed outdated admin copy that incorrectly described JavaScript i18n and plural support as future work.
- Backup/Restore now displays the current plugin version dynamically instead of a historical implementation version.
- SEO/admin help copy no longer presents old implementation version numbers as the current feature state.

### Diagnostics
- Systemstatus now measures captured frontend-string translation coverage per active non-default language.
- Coverage is reported as a warning-only readiness signal, so incomplete storefront text is visible without blocking the plugin from loading.

### Validation
- PHP and JavaScript syntax checks are required before ZIP handoff.
- Real-site browser acceptance is still required before marking the current build production-confirmed.

## [0.12.17]

### Final production acceptance
- Added a version-bound manual browser acceptance checklist to **Systemstatus**.
- The checklist is generated for every active language and covers Shop, Search, Product, Category, Filters, Mini-Cart, Cart, Checkout, My Account, Wishlist, Popups/Offcanvas and same-context language switching.
- Added cross-cutting checks for Frontend Texte, WooCommerce AJAX/Blocks, Live Editor, Backup/Restore, cache visibility, transactional e-mail language and mobile/tablet behaviour.
- Added a clear production-acceptance progress indicator.
- A build is marked ready only when the current-version manual matrix is complete and no critical automated self-test is failing.
- Added JSON export of the acceptance report including system checks and manual results.
- Old checklist state is not treated as current after a version change.

### Scope
- No sales, licensing or customer-updater work was added.
- This version is dedicated to closing the real-site acceptance phase for the current shop.

## [0.12.16]

### Production preflight / diagnostics
- Expanded **Systemstatus** with validation for all String Translation tables.
- Added a readiness check showing whether the Frontend Texte catalog has captured real storefront strings.
- Added WooCommerce Shop, Cart, Checkout and My Account page assignment checks.
- Added central My Account endpoint configuration checks.
- Added verification that ITKT's transactional WooCommerce e-mail language isolation hooks are registered.
- Added checks that Dynamic Runtime, Backup/Restore and cache invalidation modules are loaded.
- Detects WP Fastest Cache integration when the plugin API/class is available.

### Scope
- No commercial/licensing features were added.
- This version is focused only on final production readiness for the current shop.
- Real browser acceptance in DE/EN/AR remains required before marking the plugin production-confirmed.

## [0.12.15]

### Added / improved
- **Frontend Texte** now shows the configured default language as an optional editable override.
- Default-language frontend overrides can correct stubborn third-party source labels (for example English WoodMart/WooCommerce text on a German default storefront) without changing theme/plugin files.
- Live/global/native frontend string overrides can now be stored for the default language.
- Global and locator-based visual runtime maps honor explicit default-language overrides.
- PHP gettext runtime and JavaScript i18n runtime can honor explicit default-language overrides.
- Dynamic plural runtime is also available in the default storefront language.
- The frontend fetches the latest runtime string map after load even in the default language, reducing stale full-page-cache output after a text correction.

### Safety
- Leaving a default-language override empty uses the original/native theme or plugin value.
- Saving the unchanged original visual text removes the redundant default-language override instead of storing a duplicate.
- Product/page source content remains unchanged; this feature is limited to string/visual runtime overrides.

### Validation
- PHP and JavaScript syntax validation required before release ZIP handoff.
- Real-site verification is still required before v0.12.15 is marked production-confirmed.

## [0.12.14]

### WooCommerce routing
- Language switching now resolves physical WooCommerce Shop, Cart and Checkout pages before normal translated-page routing.
- Checkout `order-pay` and `order-received` endpoint state is preserved across language switches.
- Safe current query parameters are retained without leaking old ITKT language/rescue/preview parameters.
- Existing v0.12.10 My Account endpoint rescue remains unchanged and continues to take precedence.

### Live translation
- Expanded visual/live scopes for Mini-Cart, Wishlist, Popups/Offcanvas, Notices/Errors, Product, Category, Shop and Search.
- Header, Footer, Menu, Mini-Cart, Wishlist, Popup, Notices, shared widgets, Shop/Search and other reusable UI labels can use global translation mode where appropriate.
- Product/category-specific content remains separated from generic global runtime replacements.

### JavaScript i18n
- Dynamic plural runtime now uses browser `Intl.PluralRules` when available.
- Native plural maps expose locale categories instead of only hard-coded `number === 1` logic.
- Arabic native forms support zero/one/two/few/many/other categories where available.
- Fallback remains compatible with browsers that lack `Intl.PluralRules`.

### Performance / cache
- Added `ITKT_Cache` coordinator.
- Translation-related cache purges are coalesced to one shutdown operation per request.
- Runtime data generation is invalidated when string translations are saved/deleted.
- Global DOM runtime maps, JavaScript gettext maps, visual maps and plural maps use versioned WordPress object-cache entries.
- WordPress object cache is flushed after ITKT translation changes.
- WP Fastest Cache is cleared automatically when its programmatic API/class is available.
- Added `itkt_after_cache_purge` integration action for host/server caches.
- Backup restore now triggers the common ITKT cache invalidation path.

### Validation
- Plugin header, `ITKT_VERSION` and WordPress `readme.txt` Stable tag synchronized to 0.12.14.
- PHP syntax validation passes for all plugin PHP files.
- JavaScript syntax validation passes for all bundled JS files.
- Installation ZIP integrity validation passes.
- Real-site acceptance is still required before calling v0.12.14 fully production-confirmed.

## [0.12.13]

### Added
- `ITKT_Backup` module and **Backup / Restore** admin page.
- JSON export for settings, languages, ITKT string tables, `_itkt_*` post/term metadata and translated-content snapshots.

### Restore safety
- Restore never creates products, pages, posts or taxonomy terms.
- Existing translated content is updated only when its WordPress ID still exists.
- Missing IDs are skipped and reported.
- Runtime/rewrite/search derived state is invalidated after restore.

## [0.12.12]
- Added `ITKT_Dynamic_Runtime` for WooCommerce/WoodMart/AJAX/Blocks rerenders.
- Added JavaScript `ngettext` / contextual plural bridge.
- Expanded Frontend Texte discovery for notices, attributes, controls and dynamic content.
- Improved Mini-Cart versus generic popup/offcanvas classification.

## [0.12.11]
- Added `ITKT_Frontend_Catalog` and **Frontend Texte** backend table.
- Added admin-only default-language discovery and direct per-language editing.
- Added AJAX/MutationObserver discovery for fragments, popups and offcanvas content.

## [0.12.10]
- Fixed WooCommerce My Account non-default-language endpoint routing with an independent rescue marker.
- Real staging test confirmed the Dashboard-fallback issue resolved in the tested flow.

## [0.12.1 - 0.12.9]
- Expanded WooCommerce taxonomy translation, account routing, Checkout/WoodMart compatibility and dynamic storefront handling.

## [0.12.0]
- Hardened Mini-Cart, Cart/Checkout Blocks and My Account language handling.

## [0.11.x]
- Added canonical WooCommerce/WordPress gettext handling, native language-pack fallback, protected variables and storefront-only locale separation.

## [0.10.x]
- Added frontend live translation, reusable global strings, cached/AJAX runtime refresh and content-only RTL support.

## Earlier milestones
- Language management and linked WordPress translations.
- Elementor-aware content workflows.
- One-product-ID WooCommerce translation model.
- Product/taxonomy/attribute translation.
- Language routing and translated slugs.
- SEO canonical/hreflang/sitemap basics.
- XLSX/CSV product translation import/export.
