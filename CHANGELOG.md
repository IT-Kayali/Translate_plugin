# Changelog

All notable development changes to **IT-Kayali Translate** are recorded here.

Current development version: **0.12.15**.


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
