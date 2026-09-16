=== IT-Kayali Translate ===
Contributors: it-kayali
Tags: translation, multilingual, woocommerce, elementor, woodmart
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.12.14
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

== Installation ==

1. Upload the plugin ZIP under Plugins -> Add Plugin -> Upload Plugin.
2. Activate IT-Kayali Translate.
3. Complete the setup assistant and activate the required languages/modules.
4. After routing-related updates, clear page/server caches and resave Settings -> Permalinks if required.
5. Test default and non-default languages separately.

== Frontend text catalog ==

Since 0.12.11, logged-in administrators browsing the configured default language automatically collect supported visible frontend labels into IT-Kayali Translate -> Frontend Texte.

The collector is admin-only and intentionally ignores common price, quantity and technical-value containers. Captured texts are stored in the existing global-string system, so translations entered in the backend can be reused by the existing frontend runtime without modifying WooCommerce, WoodMart or theme files.

Dynamic AJAX/fragment updates, WooCommerce cart events, popups and offcanvas/drawer content are rescanned automatically.

== Changelog ==

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
