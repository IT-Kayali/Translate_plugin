# Changelog

All notable development changes to **IT-Kayali Translate** are recorded here.

Current development version: **0.12.32**.



## [0.12.32]

### Floating controls isolated from normal language-switcher modes
- WoodMart Floating-only controls now appear only when **Darstellung = Floating Button**.
- **Floating am Bildschirm fixieren** is hidden for Flaggen, Pills, Text and Dropdown.
- **Floating-Position vertikal** is shown only for Floating Button and only when viewport fixing is enabled.
- **Auf Mobile als Dropdown** is now a Floating Button-only control.
- **Dropdown-Öffnungsrichtung** in the WoodMart element is now treated as a Floating Button-only control.
- Normal Flaggen / Pills / Text modes ignore any previously saved Floating values, including old `floating_fixed=1` and `mobile_dropdown=1`.
- Global switcher settings can no longer force a normal flags/pills/text shortcode or WoodMart element into fixed/mobile-dropdown behavior.
- This restores the pre-floating behavior for normal language flags on tablet and mobile while keeping Floating Button fully configurable.


## [0.12.31]

### Automatic duplicate-install migration
- Replaces the manual duplicate-folder cleanup flow with an automatic migration for the known legacy GitHub archive folders `Translate_plugin-main` and `Translate_plugin`.
- When the official `it-kayali-translate` copy and a known legacy copy are both active, the plugin now automatically:
  - promotes the official canonical plugin basename in WordPress' active-plugin list
  - removes the legacy basename from per-site activation
  - updates multisite network activation when relevant
  - retires the legacy plugin directory after the current request finishes
- Cleanup is restricted to the two explicitly known legacy folder names; unknown duplicate folders are never deleted automatically.
- If the legacy directory cannot be removed on the first attempt, the canonical plugin retries cleanup on later requests.
- Translation data and settings are preserved because uninstall/data deletion is not performed and plugin data is stored in WordPress.
- Adds an admin success/warning notice after automatic migration so administrators can see whether cleanup completed.


## [0.12.30]

### Separate header Dropdown and Floating Button modes
- Added a dedicated **Dropdown** display mode for normal header/topbar placement.
- **Dropdown** shows only the active language and opens the remaining languages in a compact menu, but stays inside the normal page/header layout.
- **Floating Button** remains a separate display mode and can be fixed to the viewport independently.
- Fixed a v0.12.29 inheritance bug where enabling global switcher settings could override a WoodMart element configured as normal **Flaggen** and make it look like the global floating/dropdown style.
- WoodMart's **Globale Abstände/Größen verwenden** option now inherits only responsive spacing/sizing; display mode, labels, dropdown direction and floating behavior remain per-element.
- This allows one header Dropdown and a second independent Floating Button on the same page.
- Strengthened CSS visibility rules so theme button styles cannot expose the hidden dropdown trigger while the element is configured as normal inline flags.
- On mobile, normal flags/pills/text can still collapse to the active-language dropdown through **Auf Mobile als Dropdown**.
- Added shortcode overrides:
  - `style="dropdown"` for a normal layout dropdown
  - `style="floating" fixed="1"` for a fixed floating dropdown
  - `position="top|bottom"`
  - `direction="auto|up|down"`


## [0.12.29]

### Modern active-language dropdown and global switcher settings
- Redesigned the **Floating Button** so only the currently active language is visible while the other languages stay collapsed in a modern dropdown.
- Dropdown rows always include the native language name, while the trigger can still be configured to show flag, code and/or language name.
- Added automatic opening direction: the dropdown opens downward when space is available and upward when the trigger is near the bottom of the viewport; manual up/down overrides remain available.
- Added viewport-edge correction so the dropdown stays inside the visible screen instead of overflowing horizontally.
- Added keyboard/accessibility behavior: `aria-expanded`, `aria-controls`, menu semantics, Escape-to-close and click-outside closing.
- Added **IT-Kayali Translate -> Sprachumschalter** as a central backend configuration page.
- Global settings now control shortcode, Elementor/footer placements and new WoodMart language elements from one place.
- Global responsive settings include Desktop/Tablet/Mobile alignment, language gap, trigger padding, horizontal/vertical floating offsets, dropdown gap, flag size and outer margins.
- Mobile can be forced into the same active-language dropdown even when the desktop style is regular flags/pills/text.
- The WoodMart element can either inherit all global switcher settings or explicitly use local per-element settings.
- Existing WoodMart elements created before v0.12.29 keep their local configuration until the new global-setting toggle is enabled, avoiding an unexpected production design change.


## [0.12.28]

### Responsive language switcher layout and Floating Button
- Added a new **Floating Button** visual style for `[itkt_language_switcher]` and the native WoodMart **IT-Kayali Sprachen** element.
- WoodMart Header Builder now exposes separate **Desktop**, **Tablet** and **Mobile** tabs.
- Each device can define its own left/center/right alignment.
- Each device can define the language gap, horizontal/vertical inner padding and outer top/right/bottom/left spacing in pixels.
- Optional fixed floating mode keeps the language switcher visible while scrolling.
- Fixed floating mode supports top or bottom placement and a separate responsive screen-edge offset for desktop, tablet and mobile.
- Numeric spacing values are clamped to safe ranges before being written as CSS custom properties.
- The Shortcodes admin reference now documents the Floating Button style and points users to the native WoodMart element for responsive layout controls.


## [0.12.27]

### Admin shortcode reference
- Added a dedicated **Shortcodes** page under IT-Kayali Translate in WordPress admin.
- Documents all supported `[itkt_language_switcher]` variants for flags, language codes, native language names and display styles.
- Added one-click copy buttons for every example.
- Added a live active-language status at the top of the page.
- When fewer than two languages are active, the page now explicitly explains that the language switcher intentionally renders nothing and links directly to the language settings.
- Added usage guidance for Elementor, WordPress Block Editor and the native WoodMart **IT-Kayali Sprachen** Header Builder element.


## [0.12.26]

### WoodMart Header Builder registration fix
- Fixed the native **IT-Kayali Sprachen** element not appearing in the WoodMart Header Builder on sites where the WoodMart module was not stored as enabled during ITKT's original setup.
- Header-element registration now follows the actually loaded WoodMart Header Builder classes instead of the old setup-module flag.
- Added an AJAX fallback immediately before WoodMart builds the "Add element" list.
- Added a frontend safety net that refreshes WoodMart's element snapshot before the header renders, covering installations with a different initialization order.
- No WoodMart theme files are modified.

### Real-site finding
- v0.12.25 was installed on the production site, but the element was absent from the Header Builder element picker; this directly motivated the registration fix in v0.12.26.


## [0.12.25]

### Native WoodMart Header Builder language switcher
- Added a dedicated **IT-Kayali Sprachen** element to WoodMart Header Builder.
- The integration registers after WoodMart loads its built-in header elements and before WoodMart snapshots the frontend element map, so it works in the builder UI and on the storefront without editing theme files.
- The element renders ITKT's existing language URL logic directly instead of depending on WoodMart Text/HTML shortcode execution.
- Header settings support flags only, optional language codes, optional native language names, the existing flags/pills/text styles and an optional custom CSS class.
- The original `[itkt_language_switcher]` shortcode remains available and unchanged.

### Validation status
- Requires real-site verification in the active WoodMart header on desktop and mobile after installing v0.12.25.


## [0.12.24]

### WooCommerce taxonomy archive fatal fix
- Fixed HTTP 500 errors on WooCommerce product category and product tag archives.
- `ITKT_Languages::current_code()` no longer asks WordPress for a queried object ID on taxonomy archives.
- The post-meta language fallback is now limited to singular requests, which is the only place `_itkt_language` post metadata is valid.
- This prevents recursion between taxonomy queried-object construction, the ITKT `get_term` filter and `current_code()`.

### Validation
- PHP and JavaScript syntax checks pass.
- Taxonomy recursion regression test verifies that non-singular requests never call `get_queried_object_id()` from `current_code()`.
- Real-site browser verification confirmed the previously failing WooCommerce product category and product tag archives open again without HTTP 500.

## [0.12.23]

### Duplicate installation / activation hardening
- Added an early bootstrap collision guard when a second physical IT-Kayali Translate copy is loaded.
- Duplicate copies no longer redefine `ITKT_VERSION`, `ITKT_FILE`, `ITKT_DIR`, `ITKT_URL` or `ITKT_BASENAME`.
- A duplicate copy stops before class loading and shows administrators a clear notice with both plugin paths.
- Includes now resolve from the current plugin file's own physical directory instead of trusting a potentially stale `ITKT_DIR` from another copy.
- Re-including the same physical plugin copy in one request is harmless.
- Documented that GitHub `Code -> Download ZIP` is a source archive (`Translate_plugin-main/`) and should not be installed as a second WordPress copy; production updates use the canonical installable ZIP (`it-kayali-translate/`).

### Validation
- PHP and JavaScript syntax checks pass.
- Bootstrap regression checks verify early duplicate detection, guarded constants and local-path includes.
- Real WordPress replacement/activation remains required before production confirmation.

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
