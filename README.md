# IT-Kayali Translate

**Current development version: v0.12.25**

IT-Kayali Translate is a modular multilingual WordPress plugin focused on a stable multilingual storefront for the current production project. Commercial licensing, customer updater infrastructure and marketplace preparation are intentionally postponed until later.

## Current state

- WooCommerce products remain one physical product ID across all languages.
- WordPress/Elementor content, product fields, taxonomies, attributes, string translation, routing, SEO, frontend live translation and RTL foundations are implemented.
- v0.12.10 fixed the WooCommerce **My Account** non-default-language endpoint problem and was confirmed on the real staging site.
- v0.12.11 added the admin-only **Frontend Texte** catalog with direct translation fields.
- v0.12.12 added dynamic WooCommerce/WoodMart/AJAX/Blocks runtime translation and JavaScript plural support.
- v0.12.13 added JSON **Backup / Restore** without creating duplicate products, pages, posts or terms.
- v0.12.24 fixed the WooCommerce product category/tag archive HTTP 500 error and is confirmed on the real site.
- v0.12.25 adds a native **IT-Kayali Sprachen** element to the WoodMart Header Builder so the language flags no longer depend on the Text/HTML shortcode field.



## v0.12.25 – native WoodMart Header Builder language switcher

- Adds **IT-Kayali Sprachen** directly to WoodMart -> Header builder -> Add element.
- Renders the same ITKT language-switching URLs and active-language state as the existing shortcode, but does not depend on WoodMart's Text/HTML element executing shortcodes.
- Supports flag-only display, optional language codes, optional native language names, existing `flags`, `pills` and `text` styles, and an optional CSS class.
- Works as a normal WoodMart header element, so it can be placed separately in desktop and mobile header layouts.
- No WoodMart theme or child-theme file is modified.
- The existing `[itkt_language_switcher]` shortcode remains supported.

### Real-site validation required

After installing v0.12.25, reload the WoodMart Header Builder, add **IT-Kayali Sprachen**, save the header, clear caches, and verify DE/EN/AR switching on desktop and mobile.


## v0.12.24 – WooCommerce category/tag archive 500 fix

- Fixes HTTP 500 errors when opening WooCommerce product category and product tag archives such as `/product-category/baklava/` and `/product-tag/.../`.
- The root cause was recursive language detection: `current_code()` called `get_queried_object_id()` on taxonomy requests while WordPress was still building the queried `WP_Term`; ITKT's `get_term` translation filter then called `current_code()` again.
- The `_itkt_language` post-meta fallback now runs only on real singular requests (pages/posts/products), where that metadata actually belongs.
- Taxonomy archives now stay on the normal term-language path and no longer enter the queried-object recursion.
- This change is intentionally narrow and does not alter translated category/tag names, slugs, SEO, filters or language-switch URLs.

### Validation

- PHP syntax validation passes for all plugin PHP files.
- JavaScript syntax validation passes for all bundled JavaScript files.
- A taxonomy recursion regression harness verifies that `current_code()` does not call `get_queried_object_id()` when `is_singular()` is false.
- Real-site verification confirmed that the previously failing WooCommerce product category and product tag archive requests open again without HTTP 500.

## v0.12.23 – duplicate-installation / activation hardening

- Prevents a second IT-Kayali Translate copy from redefining `ITKT_*` constants and crashing activation.
- Detects when two physical plugin copies are loaded (for example `it-kayali-translate/` plus GitHub's `Translate_plugin-main/`) and stops the duplicate copy before class loading.
- Shows administrators a clear duplicate-copy notice with both physical plugin paths instead of producing a fatal `require_once` error.
- Core includes are resolved from the current plugin file's own physical directory, so a stale `ITKT_DIR` from another copy can no longer redirect includes into the wrong folder.
- Re-including the same plugin copy in one request is harmless.
- The official installable ZIP continues to use the canonical root folder `it-kayali-translate/`.

### Installation note

Do **not** use GitHub **Code → Download ZIP** as a second WordPress plugin installation while an older IT-Kayali Translate copy is still installed. GitHub names that source folder `Translate_plugin-main`, which WordPress treats as a separate plugin directory. For WordPress updates use the installable release ZIP whose root folder is `it-kayali-translate/`; WordPress can then replace the existing copy instead of creating a duplicate.

### Validation

- PHP syntax validation passes for all plugin PHP files.
- JavaScript syntax validation passes for all bundled JavaScript files.
- Duplicate-copy bootstrap regression checks verify that the collision guard executes before class includes and that includes use the local bootstrap directory.
- Real-site activation/update verification is still required before marking v0.12.23 production-confirmed.

## v0.12.21 – safe language-switch query state

- Language-switch URLs now preserve ordinary scalar search/filter/sort state without replaying state-changing GET actions.
- Cart, wishlist and order actions, AJAX action parameters and nonce/security parameters are removed before current request query arguments are copied to another language URL.
- Checkout `order-pay` / `order-received` can still keep the WooCommerce `key` / `pay_for_order` context required for the same endpoint.
- Canonical translated-slug redirects use the same sanitizer, avoiding action replay during SEO normalization.
- SEO slug redirects now explicitly skip WooCommerce Cart, Checkout/order-pay/order-received and My Account routes so stateful endpoints are never collapsed back to their page base.
- A public `itkt_language_switch_query_args` filter remains available for site-specific read-only query-state extensions.


## v0.12.22 – runtime/AJAX production hardening

- WooCommerce/Blocks runtime refreshes are debounced through one shared scheduler instead of launching overlapping fetches for the same cart/checkout event.
- If a refresh is already in flight, additional changes queue one follow-up refresh instead of opening parallel requests.
- The dynamic runtime listens only to events not already owned by the base frontend runtime, removing duplicate cart/fragment listeners.
- The public `itkt_runtime_strings` endpoint is GET-only and accepts the plugin's same-origin XMLHttpRequest path rather than generic form/image-style requests.
- Cart translation runtime no longer initializes a WooCommerce cart/session when the browser has no WooCommerce/cart session cookies.
- This reduces avoidable admin-ajax traffic and anonymous WooCommerce session work without changing published translation data.

## v0.12.20 – product import production hardening

- Product translation CSV/XLSX uploads are capped at 25 MB and bounded to 10,000 data rows, 512 columns and safe per-cell/XML sizes.
- XLSX XML input rejects `DOCTYPE` / `ENTITY` declarations and is parsed with network access disabled.
- Workbook relationship targets are restricted to worksheet XML inside `xl/worksheets/`.
- Duplicate normalized column names are rejected and duplicate product rows are skipped.
- When an import row contains both `product_id` and `sku`, the current product SKU must match before the row can modify translations. If the ID is unavailable, SKU lookup remains the safe fallback.
- The importer checks `edit_post` for every resolved product before saving translation data.
- These checks are intended to prevent oversized/malformed spreadsheet input and accidental cross-product updates during production imports.

## v0.12.19 – Backup / Restore production hardening

Before final sign-off, Backup / Restore was tightened so a malformed or wrong-site JSON file cannot silently overwrite ID-based translation data. Restore now validates the complete expected backup structure and string-table relationships before any destructive restore operation begins.

Backups are intentionally restored only to the same WordPress site/network context. This prevents post, term and translation IDs from a different installation from being attached to unrelated content. Post/term metadata is restored only when the referenced object still exists, and linked content snapshots are updated only when the current post type matches the snapshot. Skipped rows are reported after restore.

The String Translation schema is now ensured before the data transaction starts because MySQL DDL/dbDelta operations may implicitly commit. Failed restore transactions also flush the WordPress object cache so a rolled-back restore cannot leave stale cached option/content values behind.


## v0.12.18 – production consistency and coverage checks

- Removes stale admin notices that still described JavaScript i18n/plural handling as future work.
- Uses the current plugin version dynamically in Backup/Restore and removes obsolete version wording from SEO/admin help text.
- The Systemstatus self-test now reports **Frontend translation coverage per active non-default language**, so missing captured storefront translations are visible before the final browser acceptance.
- No sales/licensing scope was added; this release stays focused on production readiness for the current shop.

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

## v0.12.13 – Backup / Restore foundation

### Added

- Added the `ITKT_Backup` module and the **Backup / Restore** admin page.
- Added JSON export for ITKT settings, language configuration, String Translation tables, `_itkt_*` post/term metadata and translated-content snapshots.

### Restore behaviour

- Restore updates existing WordPress objects only and does **not** create duplicate products, pages, posts or taxonomy terms.
- Missing object IDs are skipped instead of being recreated automatically.
- Derived runtime/rewrite/search state is invalidated after a successful restore.
- This version established the same-site rollback foundation that was later hardened further in v0.12.19.

## v0.12.12 – dynamic WooCommerce / AJAX runtime

- Added `ITKT_Dynamic_Runtime` for WooCommerce, WoodMart, AJAX, fragments and WooCommerce Blocks rerenders.
- Added JavaScript `ngettext` and contextual plural handling for dynamic frontend strings.
- Expanded **Frontend Texte** discovery to notices, controls, attributes and dynamically inserted frontend content.
- Improved classification of Mini-Cart content versus generic popup/offcanvas content.
- This version provided the runtime base that later received broader plural, cache and AJAX hardening in v0.12.14 and v0.12.22.

## v0.12.11 – Frontend Texte catalog

- Added the `ITKT_Frontend_Catalog` module and the **Frontend Texte** backend table.
- Logged-in administrators can discover supported visible storefront text while browsing the configured default language.
- Captured strings are grouped by storefront area and can be translated directly per active language.
- Added AJAX/MutationObserver discovery for WooCommerce fragments, popups and offcanvas content.
- Captured frontend strings reuse the existing ITKT String Translation runtime instead of modifying WooCommerce, WoodMart or theme source files.

## v0.12.10 – WooCommerce My Account endpoint rescue

- Fixed the non-default-language WooCommerce **My Account** endpoint problem with an independent endpoint-rescue marker.
- Endpoint state is restored server-side and the temporary marker is removed again in the browser after routing succeeds.
- Orders, Downloads, Addresses and Account details can stay on their intended endpoint instead of falling back to the My Account dashboard.
- This fix was confirmed on the real staging site in the tested non-default-language account flow.
- The v0.12.10 account-rescue logic remains the routing baseline for later WooCommerce language-switch hardening.

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

Restore updates only existing IDs and never creates duplicate products/pages/posts/terms. Missing IDs are skipped and reported. For production safety, restore accepts backups only from the same WordPress site/network context.

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

## Verbindliche Update-Regel

Ab jetzt gilt für jede erfolgreiche funktionale Änderung am Plugin:

- **README.md wird automatisch im selben Arbeitsgang ergänzt.** Dafür ist keine zusätzliche Aufforderung nötig.
- Der README-Eintrag beschreibt mindestens die neue Version, die konkrete Änderung und den Validierungsstatus.
- Gleichzeitig werden bei einem funktionalen Release auch **CHANGELOG.md**, **AGENDA.md**, **readme.txt Stable tag**, Plugin-Header / `ITKT_VERSION`, GitHub-`main` und die installierbare ZIP synchron gehalten.
- Eine funktionale Änderung gilt erst als vollständig abgeschlossen, wenn der aktuelle Stand auch in GitHub **README.md** dokumentiert ist.
- Reine Dokumentations-/Workflow-Änderungen benötigen keinen Plugin-Versionssprung.

GitHub prüft diese Regel zusätzlich über einen dauerhaften Docs-Sync-Workflow: Ändert ein Commit produktiven Plugin-Code, ohne die Release-Dokumentation im selben Commit mitzuführen, schlägt die Prüfung fehl.

## New-chat handoff rule

Before changing the plugin in a new chat, read `README.md`, `CHANGELOG.md`, `AGENDA.md` and the current source. Every functional release must synchronize the plugin header, `ITKT_VERSION`, `readme.txt` Stable tag, GitHub source/docs and the installable ZIP.

## Website

IT-Kayali: https://it-kayali.de
