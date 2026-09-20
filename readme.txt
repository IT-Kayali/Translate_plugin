=== IT-Kayali Translate ===
Contributors: it-kayali
Tags: translation, multilingual, woocommerce, elementor, woodmart
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.12.35
License: GPLv2 or later

Modular multilingual translation management for WordPress with optional WooCommerce, Elementor and WoodMart integrations.
Plugin website: https://it-kayali.de

== Description ==

IT-Kayali Translate manages multilingual WordPress content without duplicating WooCommerce products.

Current development features include:

* Language-prefixed frontend URLs such as /en/ and /ar/.
* Linked WordPress page/post translations.
* Elementor-aware content handling.
* One physical WooCommerce product ID across all languages.
* Product title, short description, long description, slug, SEO, categories, tags and attributes.
* WooCommerce Cart, Checkout and My Account language routing.
* Plugin/theme string translation without editing third-party source files.
* Frontend live translation for administrators.
* Frontend text catalog that automatically discovers visible default-language storefront labels.
* Direct backend translation table grouped by Shop, Product, Filter, Cart, Checkout, Account, Popup, Header, Footer and other areas.
* AJAX/fragment/offcanvas detection for dynamically inserted frontend text.
* RTL text handling, canonical URLs, hreflang and translation sitemap.
* XLSX/CSV product translation import/export.
* Native IT-Kayali language switcher element for the WoodMart Header Builder.

== Installation ==

1. Upload the installable plugin ZIP under Plugins -> Add Plugin -> Upload Plugin. Its root folder is it-kayali-translate/.
2. If WordPress detects an existing installation, replace/update that copy instead of installing another plugin directory.
3. Do not install GitHub Code -> Download ZIP as a second WordPress copy; GitHub names that source directory Translate_plugin-main/.
4. Activate IT-Kayali Translate.
5. Complete the setup assistant and activate the required languages/modules.
6. After routing-related updates, clear page/server caches and resave Settings -> Permalinks if required.
7. Test default and non-default languages separately.

== Frontend text catalog ==

Since 0.12.11, logged-in administrators browsing the configured default language automatically collect supported visible frontend labels into IT-Kayali Translate -> Frontend Texte.

The collector is admin-only and intentionally ignores common price, quantity and technical-value containers. Captured texts are stored in the existing global-string system, so translations entered in the backend can be reused by the existing frontend runtime without modifying WooCommerce, WoodMart or theme files.

Dynamic AJAX/fragment updates, WooCommerce cart events, popups and offcanvas/drawer content are rescanned automatically.

== Changelog ==










= 0.12.35 =
* Makes the tablet/mobile language dropdown smaller and tighter.
* Reduces menu width, flag size, row height, padding and trigger-to-menu gap.
* Does not change routing, header inheritance or language-switching behavior.


= 0.12.34 =
* Keeps WoodMart header assignments shared across languages unless another header is explicitly selected for a translated page.
* Prevents language prefixes on WordPress/WoodMart admin-bar and Header Builder editor links.
* Repairs stale language-prefixed Header Builder URLs.
* Makes the tablet/mobile language dropdown more compact.


= 0.12.33 =
* Fixes dropdown clipping at the far left/right edge on mobile and tablet.
* Anchors the menu to the matching left, center or right trigger edge.
* Automatically switches to a safer horizontal anchor when space is insufficient.
* Keeps the final dropdown inside the visible viewport.

= 0.12.32 =
* Restricts Floating controls to the Floating Button display mode.
* Restores normal Flaggen/Pills/Text behavior on tablet and mobile.
* Prevents saved or global Floating settings from fixing normal header language elements to the viewport.
* Uses WoodMart conditional field requirements so Floating options are hidden when they do not apply.

= 0.12.31 =
* Automatically migrates known duplicate GitHub archive installs to the official it-kayali-translate plugin folder.
* Rewrites per-site and multisite activation data so the canonical plugin copy remains active.
* Automatically removes the known Translate_plugin-main / Translate_plugin legacy folder after the request finishes.
* Retries legacy-folder cleanup automatically when the filesystem cannot remove it immediately.
* Preserves all translation data and settings.

= 0.12.30 =
* Adds a separate Dropdown style for normal header/topbar placement.
* Keeps Floating Button as an independent optional viewport-fixed switcher.
* Fixes WoodMart global settings overriding a locally selected Flaggen display mode.
* WoodMart global inheritance now applies only responsive spacing/sizing while display behavior remains per element.
* Allows a header Dropdown and a second Floating Button to coexist.
* Hardens inline-flag visibility against theme button CSS.
* Adds shortcode fixed, position and direction overrides.

= 0.12.29 =
* Redesigns Floating Button as an active-language trigger with the other languages in a modern dropdown.
* Adds automatic up/down dropdown direction and viewport-edge correction.
* Adds a global IT-Kayali Translate -> Sprachumschalter settings page.
* Shares responsive switcher rules with Shortcodes, Elementor/Footer placements and opt-in WoodMart elements.
* Adds per-device X/Y floating offsets, dropdown gap, flag size, padding, alignment and outer spacing.
* Mobile can force regular switchers into the compact dropdown layout.
* Adds click-outside/Escape closing and ARIA dropdown state.

= 0.12.28 =
* Adds a Floating Button language-switcher style.
* Adds separate Desktop, Tablet and Mobile alignment controls to the native WoodMart language element.
* Adds responsive gap, inner padding and top/right/bottom/left outer spacing controls.
* Adds optional fixed floating mode with top/bottom placement and responsive screen-edge offsets.
* Updates the Shortcodes admin reference with the new Floating Button option.

= 0.12.27 =
* Adds an IT-Kayali Translate -> Shortcodes admin reference page.
* Provides copy buttons for supported language-switcher shortcode variants.
* Shows active-language status and explains why the switcher renders nothing with fewer than two active languages.
* Documents labels, names and style parameters plus Elementor, WordPress and WoodMart usage.

= 0.12.26 =
* Fixes the IT-Kayali Sprachen element not appearing in WoodMart Header Builder when the WoodMart module was not stored as enabled during initial ITKT setup.
* Detects the actually loaded WoodMart Header Builder instead of relying on the setup-module flag.
* Adds a Header Builder AJAX registration fallback and a frontend element-snapshot refresh fallback.
* Keeps v0.12.25 language-switcher settings and shortcode compatibility unchanged.

= 0.12.25 =
* Adds a native IT-Kayali Sprachen element to the WoodMart Header Builder.
* Avoids relying on the WoodMart Text/HTML field to execute the language-switcher shortcode.
* Supports flags, optional language codes/names, existing switcher styles and an optional CSS class.
* Keeps the original [itkt_language_switcher] shortcode available.
* Real-site desktop/mobile verification is required after installation.

= 0.12.24 =
* Fixes HTTP 500 errors on WooCommerce product category and product tag archives.
* Prevents recursive language detection while WordPress builds the queried taxonomy term.
* Limits the _itkt_language post-meta fallback to singular requests where post metadata is valid.
* Keeps translated taxonomy names, slugs, SEO and language switching unchanged.
* Real-site verification confirmed the previously failing product category and product tag archives open again without HTTP 500.

= 0.12.23 =
* Prevents fatal activation errors when two physical IT-Kayali Translate copies are loaded.
* Duplicate copies stop before class loading and no longer redefine ITKT constants.
* Includes are resolved from the current plugin file's own directory, preventing stale cross-folder ITKT_DIR paths.
* Shows administrators a duplicate-copy notice instead of a fatal require_once error.
* Documents that GitHub Code -> Download ZIP is a source archive; WordPress updates should use the installable ZIP with root folder it-kayali-translate/.

= 0.12.22 =
* Coalesced WooCommerce/Blocks runtime refreshes through one shared debounce scheduler.
* Avoids parallel duplicate runtime-map requests and queues only one follow-up refresh while a request is in flight.
* Removed overlapping dynamic-runtime listeners already handled by the base frontend runtime.
* Hardened the public runtime-string AJAX endpoint to the intended GET/XMLHttpRequest path.
* Avoids initializing a WooCommerce cart/session for anonymous runtime requests without existing WooCommerce/cart session cookies.

= 0.12.21 =
* Hardened language-switch query handling so switching languages does not replay state-changing GET actions.
* Cart/wishlist/order actions, AJAX action parameters and nonce/security values are stripped from copied current-request query state.
* Search/filter/sort query state remains preserved.
* WooCommerce order-pay/order-received can retain required key/payment context for the same checkout endpoint.
* Canonical translated-slug redirects use the same safe query-state sanitizer.
* SEO slug redirects skip Cart, Checkout/order-pay/order-received and My Account so stateful WooCommerce endpoints are not collapsed to their base page.

= 0.12.20 =
* Hardened WooCommerce product translation CSV/XLSX import before production sign-off.
* Import files are limited to 25 MB, 10,000 data rows, 512 columns and bounded cell/XML sizes.
* XLSX XML parts reject DOCTYPE/ENTITY declarations and are parsed with network access disabled.
* Workbook relationships may only resolve to worksheet XML below xl/worksheets/.
* Duplicate column names are rejected; duplicate product rows are skipped.
* When product_id and SKU are both present, a SKU mismatch prevents the row from updating the wrong product.
* Each resolved product is checked with edit_post capability before translation data is changed.

= 0.12.19 =
* Hardened Backup / Restore before production sign-off.
* Restore now rejects backups from a different site/network context instead of applying ID-based data to unrelated content.
* Incomplete or internally inconsistent backup payloads are rejected before destructive writes start.
* Post/term metadata is restored only for IDs that still exist; skipped metadata is counted and reported.
* Linked content snapshots are updated only when the current post type matches the backup snapshot.
* Schema/dbDelta work now runs before the restore transaction so DDL cannot silently break rollback guarantees; failed restores also flush possibly stale object-cache entries.

= 0.12.18 =
* Added Frontend Texte translation-coverage diagnostics per active non-default language in Systemstatus.
* Removed outdated admin copy that incorrectly described JavaScript i18n/plural handling as future work.
* Backup/Restore now shows the current plugin version dynamically; SEO/admin help copy no longer presents historical implementation versions as current state.
* No licensing/sales scope added; final real-site acceptance remains the last gate.

= 0.12.17 =
* Added a version-bound production acceptance checklist to Systemstatus.
* The checklist covers the complete active-language storefront journey plus AJAX, live editor, backup/restore, cache, transactional e-mail language and mobile checks.
* Added JSON export of the final acceptance report for support/documentation.
* Production readiness is shown only when all manual checks for the current version are complete and no critical automated self-test is failing.

= 0.12.16 =
* Expanded Systemstatus preflight checks for WooCommerce system pages and My Account endpoints.
* Added verification for transactional email language isolation.
* Added database-schema, Frontend Texte catalog and production-module readiness checks.
* Detects WP Fastest Cache integration when available.


= 0.12.15 =
* Frontend Texte can now optionally override the configured default storefront language.
* Live/global/native frontend text overrides can be saved for the default language without editing third-party source files.
* Cached default-language pages fetch the newest runtime string map after load.
* JavaScript/native runtime maps and plural runtime also support explicit default-language overrides.

= 0.12.14 =
* Production-finalization candidate for the current shop workflow.
* Language switching now keeps physical WooCommerce Cart, Checkout and Shop pages stable across languages; checkout order-pay/order-received endpoint state is preserved.
* Expanded live-translation scopes for Header, Footer, Menu, Mini-Cart, Wishlist, Popups/Offcanvas, Notices, Shop and Search.
* Improved JavaScript plural handling with locale-aware Intl.PluralRules categories, including Arabic cardinal forms when available in native catalogs.
* Added runtime-map object caching keyed by an ITKT translation generation to reduce repeated database work on frontend requests.
* Translation changes now coalesce cache invalidation and clear WordPress object cache plus WP Fastest Cache when available.
* Backup restore now invalidates runtime/cache generations after a successful restore.
* Code-side work for WooCommerce routing, frontend strings, dynamic/AJAX translation, live translation, JS i18n, backup/restore and cache handling is implemented; final real-site acceptance remains required.

= 0.12.13 =
* Added Backup / Restore for IT-Kayali Translate configuration and translation data.
* Backups include language/settings options, string tables, product/taxonomy translation metadata and snapshots of linked translated WordPress content.
* Restore replaces only ITKT-managed translation data and never creates duplicate products, pages, posts or terms.
* Missing content IDs are skipped instead of being recreated; derived routing/search/runtime caches are rebuilt after restore.



= 0.12.12 =
* Extended frontend text discovery for notices, aria-label/title attributes, select options and dynamic controls.
* Improved area detection for Mini-Cart versus generic popup/offcanvas content.
* Added a modular JavaScript plural runtime bridge for @wordpress/i18n ngettext and ngettext_with_context.
* Runtime refresh now updates singular, contextual and plural JavaScript translation maps after AJAX/fragment refreshes.
* Expanded dynamic WooCommerce/Blocks event coverage so translated strings are re-applied after checkout/cart rerenders.

= 0.12.11 =
* Added automatic admin-only frontend text discovery on the default language.
* Added a dedicated Frontend Texte backend table grouped by storefront area with direct translations per active language.
* Added AJAX-safe discovery for popups, off-canvas panels, WooCommerce fragments and dynamically loaded content.
* Frontend-discovered translations reuse the global runtime translation layer, so saved values apply without editing theme/plugin files.

= 0.12.10 =
* Fixed WooCommerce My Account endpoint navigation for non-default languages with an independent rescue marker and frontend click routing.
* Preserved pretty endpoint URLs while using a temporary query marker only for the server request.
* Real staging test confirmed the My Account dashboard-fallback issue is resolved in the tested flow.

= 0.12.1 - 0.12.9 =
* Expanded WooCommerce taxonomy, account routing, checkout, WoodMart and runtime translation handling.

= 0.10.x - 0.11.x =
* Added frontend live translation, reusable global strings, native language-pack fallback and JavaScript/runtime translation support.

= 0.2.x - 0.9.x =
* Added language management, WordPress/Elementor translations, WooCommerce single-product translation model, multilingual routing, SEO and XLSX/CSV workflows.
