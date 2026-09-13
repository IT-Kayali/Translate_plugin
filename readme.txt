=== IT-Kayali Translate ===
Contributors: it-kayali
Tags: translation, multilingual, woocommerce, elementor, woodmart
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.12.10
License: GPLv2 or later

Modular multilingual translation management for WordPress.
Plugin website: https://it-kayali.de


== 0.5.0 ==
* Dedicated optional WooCommerce bridge; plain WordPress installations remain independent.
* Products remain one physical WooCommerce product ID for every language.
* Product name, short description and description are translated per language.
* Product categories, product tags, global attribute labels and global attribute values are translated without duplicate terms.
* Custom non-taxonomy product attributes can be translated directly inside the product translation matrix.
* Product/category/tag/attribute links keep the active language prefix in shop loops and archives.
* Clean language routing now resolves WooCommerce taxonomy archives such as /en/product-category/.../.
* Cart, checkout, account, shop and WooCommerce endpoint URLs keep the active language.
* Arabic WooCommerce text can use RTL text direction while the surrounding WoodMart/Elementor layout remains unchanged.

== 0.3.0 ==
* Missing page/post translations now show a red "Erstellen" action directly in each language column.
* Creating a translation duplicates the complete WordPress/Elementor document and opens the copy in the matching editor.
* Original page action always opens the correct WordPress or Elementor editor.
* Frontend language switcher shortcode: [itkt_language_switcher].
* Flags-only switcher: [itkt_language_switcher labels="0" style="flags"].
* Basic frontend language context for translated products, terms and WooCommerce attribute labels.
* RTL/LTR language attributes are applied automatically.
* New multilingual Menus admin page with one menu per theme location and language.
* Missing language menus can be cloned from the original menu; linked translated pages are used where available.
* Menu assignment switches automatically in the frontend.

== 0.2.0 ==
* Languages limited to Arabic, German, English, French, Spanish, Turkish, Swedish and Dutch.
* Central translation workspace with Not translated / Translated / All tabs.
* Pages and posts remain separate translation documents for reliable page-builder editing.
* Elementor pages open directly in Elementor from the translation manager.
* Safe Elementor text segment extraction; HTML/shortcode/code widgets are marked for manual review.
* WooCommerce products are NEVER duplicated per language. Translatable product fields are stored on the same product ID.
* Product categories, attribute labels and attribute terms are managed under the Products area without duplicating terms.
* Modern dark-blue/orange/white admin interface.

== Architecture ==
Core translation services remain independent of WooCommerce, Elementor and WoodMart. Optional integrations are detected at runtime and future adapters can register through itkt_register_adapters.


== Changelog ==

= 0.12.10 =
* Fix WooCommerce My Account endpoint navigation for non-default languages with an independent rescue marker and frontend click routing.
* Preserve pretty endpoint URLs while using a temporary query marker only for the server request.

= 0.12.9 =
* Fixed: language switching inside WooCommerce My Account now preserves the active endpoint instead of returning to Dashboard.
* Fixed: direct content dispatcher renders the endpoint action from the real /LANG/my-account/ENDPOINT/ URL when a theme/router loses WooCommerce query state.
* Fixed: account endpoint links are rebuilt even when WoodMart normalizes the queried account page/permalink.
* Fixed: account navigation click fallback now performs a direct full-page navigation to the canonical language endpoint URL.


= 0.12.8 =
* Fix: WooCommerce My Account endpoint URLs are now built from the exact physical WooCommerce system page instead of translated page copies.
* Fix: My Account permalink filtering now runs at final priority so WoodMart/other filters cannot collapse non-default-language account links back to the dashboard.
* Fix: Orders, Downloads, Addresses, Account details and other account endpoints are reconstructed directly for /LANG/... URLs.
* Fix: Runtime endpoint state is synchronized through both $wp/$wp_query and set_query_var() before WooCommerce renders account content.
* Fix: Footer/click fallback rewrites WooCommerce and WoodMart account navigation hrefs from a canonical endpoint map if theme JavaScript replaces them later.

= 0.12.7 =
* Fix: WooCommerce My Account system page now always resolves to the physical WooCommerce account page in non-default languages.
* Fix: Account endpoint links are rebuilt at final priority so /LANG/my-account/orders/, downloads, addresses and account details keep their endpoint.
* Fix: Added a lightweight frontend fallback for WooCommerce/WoodMart account navigation links.

= 0.12.6 =
* My Account endpoint routing hardened with dedicated language-prefixed rewrite rules.
* Added a runtime endpoint rescue that synchronizes both WordPress query contexts before WooCommerce account navigation/content renders.
* Prevents Orders, Downloads, Addresses, Account details, Payment methods, View order and Logout from falling back to the account dashboard on non-default languages.

= 0.12.5 =
* My Account endpoints use the physical WooCommerce My Account page while the storefront language remains virtual.
* Added a late parse_request endpoint rescue based on the real REQUEST_URI for IONOS/nginx/Apache rewrite edge cases.
* Orders, downloads, addresses, account details, payment methods, view-order and logout endpoint values are preserved centrally.


= 0.12.4 =
* WooCommerce My-Account-Endpunkte funktionieren jetzt auch unter Sprach-URLs wie /en/.../orders/, /ar/.../downloads/, edit-address, edit-account, view-order und logout.
* Endpoint-URLs behalten Query-Parameter/Nonces.
* Automatische Frontend-Seitenprüfung erweitert: Hauptinhalt, WooCommerce/My-Account und verschachtelte Inline-Texte werden systematisch als übersetzbar erkannt.
* Zusammengesetzte Sätze mit Links/strong/span werden als sicherer gemeinsamer Textblock bearbeitet, ohne Markup oder Links zu zerstören.
* Übersetzungsmodus zeigt nach dem Aktivieren die Anzahl automatisch erkannter Bereiche.


= 0.12.3 =
* WooCommerce Checkout Block: legal consent text is now one editable system string with protected Terms/Privacy link placeholders.
* Added JS-i18n coverage for Terms and Conditions, Privacy Policy and the checkout validation message.
* Nested legal links stay intact and may be reordered safely in RTL translations.

= 0.12.2 =
* Adds first-class WoodMart checkout-step support for the shared Shopping cart / Checkout / Order complete breadcrumb/progress area, including automatic native language-pack defaults and live-editor overrides.
* Shared page-title/checkout-step containers are now part of the visual/runtime translation zones even when WoodMart renders them outside the normal WooCommerce wrapper.
* Live editor now marks deepest text elements first so nested footer values are not swallowed by a broad parent wrapper.
* Adds safe grouped translation for mixed inline footer/HTML-block content split by links, strong tags or line breaks; markup and links are preserved.
* Footer opening-hour values such as 10:30-22:00 can now be selected in the live editor when desired.
* Native system panels distinguish WoodMart from WooCommerce instead of labeling every theme string as WooCommerce.
* Frontend-only policy remains unchanged: backend, emails, invoices and packing slips are not translated by ITKT.

= 0.12.1 =
* Completes the storefront taxonomy layer for WooCommerce product categories, product tags, global attribute labels and attribute values without duplicating terms or changing canonical filter slugs.
* WooCommerce/WoodMart term collections are translated through both get_term and get_terms, including storefront admin-ajax requests used by layered-nav/filter drawers. Normal wp-admin taxonomy screens stay in the administrator language.
* Adds translated archive-title fallbacks for themes that use single_term_title/get_the_archive_title instead of WooCommerce page-title helpers.
* Core layered-nav filter links are normalized back onto the currently selected /en/, /ar/, ... storefront URL while preserving filter/query parameters.
* Taxonomy menu items now use the translated public category/tag/attribute URL rather than only prefixing the source-language slug.
* Adds a compact cached taxonomy runtime map for WoodMart/AJAX/full-page-cache fragments so filter labels, category/tag names, attribute labels, breadcrumbs, category cards and product meta can be repaired after dynamic DOM replacement.
* WooCommerce filter query values continue to use the original canonical term slugs internally; only visitor-facing labels are translated.
* Frontend-only policy remains unchanged: backend, emails, invoices and packing slips are not translated by ITKT.

= 0.12.0 =
* Frontend WooCommerce end-to-end hardening for Mini-Cart, Cart/Checkout Blocks and My Account without affecting backend, emails, invoices or packing slips.
* Cart/Store API variation values now use translated global attribute terms and custom product attribute values through WooCommerce's canonical `woocommerce_variation_option_name` hook.
* Product links returned by classic cart, mini-cart, Store API cart items and frontend order details stay on the currently selected language.
* Frontend order-detail product names preserve their existing product links instead of replacing linked markup with plain text.
* Continues the frontend-only policy: transactional emails, generated documents and WooCommerce admin remain on the shop/admin language and original order data.

= 0.11.9 =
* Adds a dedicated per-language WooCommerce product search index without duplicating products.
* Frontend/WoodMart WP_Query searches can find products by translated title, short/long description, SEO text, categories, tags, global attributes/terms and custom product attributes.
* Existing translations are indexed automatically in bounded background batches; newly saved/imported product translations update their search index immediately.
* Adds Systemstatus self-test and a manual "Produkt-Suchindex reparieren" tool for immediate rebuilding/testing.
* Search remains storefront-only: backend/admin searches, emails, invoices and packing slips keep their normal/original language behavior.

= 0.11.8 =
* Frontend-only language policy: selected storefront language affects website output only.
* WooCommerce customer/admin emails are no longer translated from the visitor/order language; ITKT forces its own runtime to the configured default language during email rendering without changing the WordPress locale.
* Removed StoreaBill/Germanized invoice and packing-slip product/attribute translation hooks. New/generated documents keep the original WooCommerce order snapshot and the document plugin/site language.
* _itkt_language may remain stored as informational order metadata only; it no longer controls emails or documents.
* My Account / frontend order details continue to use the language currently selected on the website.

= 0.11.7 =
* Historical v0.11.7 behavior (removed in v0.11.8): order-time storefront language no longer drives emails or documents.
* Historical v0.11.7 behavior (removed in v0.11.8): Germanized invoice/shipment outputs are no longer tied to ITKT order language.
* Historical v0.11.7 behavior (removed in v0.11.8): StoreaBill/Germanized document translation hooks were removed.
* Renamed admin order meta display to “Website-Sprache bei Bestellung” for clarity.

= 0.11.6 =
* Frontend order-detail presentation can translate custom non-taxonomy attributes (e.g. Packung / 1000 Gramm) using the currently selected website language; emails/documents remain original.
* Historic WooCommerce order meta remains unchanged; translations are presentation-only on frontend account/order views.
* Existing global pa_* attribute/term translation path remains intact.


= 0.11.5 =
* Persists the selected storefront language on classic checkout orders and WooCommerce Checkout Block / Store API orders as _itkt_language.
* Adds a temporary ITKT language-context stack for transactional rendering without switching the global WordPress locale or RTL/layout state.
* Historical v0.11.5 behavior (removed in v0.11.8): customer e-mail order sections no longer use ITKT order-language translations.
* Historical v0.11.5 behavior (removed in v0.11.8): e-mail subjects/headings no longer use the saved storefront language.
* Shows the saved order language on the WooCommerce admin order screen.
* Keeps admin/merchant notification e-mails in the normal store language.

= 0.11.4 =
* Separates the WooCommerce Blocks `Including %s` system string from the configured tax-rate label contained inside `%s`.
* Translates WooCommerce tax-rate labels through the canonical `woocommerce_rate_label` hook so Store API, Cart/Checkout Blocks and classic templates share the same language.
* Adds editable per-language tax-rate label overrides without changing the stored WooCommerce tax configuration.
* Provides safe defaults for common VAT labels such as MwSt., VAT, TVA, IVA, KDV, moms and btw.
* Fixes English canonical overrides that equal the source msgid so they still win over an already-loaded German WordPress locale.

= 0.11.3 =
* Fixes WooCommerce system-text reverse resolution by inspecting the translation catalog WordPress actually loaded for the current request before falling back to catalog files.
* Dynamic rendered labels such as "Einschließlich 8,64 € MwSt." can now resolve to the canonical WooCommerce/WoodMart gettext msgid instead of remaining a legacy visual-only pattern.
* Native system-text fields now contain only IT-Kayali overrides. The WooCommerce language-pack value is shown separately as the fallback; an empty field means "use WooCommerce standard".
* Empty override fields can now be saved. Saving empty deletes the stored override instead of failing protected-token validation.
* When a visual legacy pattern is upgraded to a canonical gettext string, its old per-language override is removed so stale browser/runtime replacements cannot compete with the canonical gettext override.

= 0.11.1 =
* Audit fix: live WooCommerce variable labels are resolved back to their canonical gettext msgid before an override is stored. Rendered values such as “Einschließlich 8,64 € MwSt.” can now map to WooCommerce’s canonical “(includes %s)” source.
* Live Translation now sends both the protected editor template and the untouched rendered label to the server, allowing native catalog pattern matching for dynamic WooCommerce strings.
* Native translation catalog lookup now uses WordPress translation-file APIs/registry when available and falls back conservatively for WordPress 6.4 compatibility.
* Native reverse lookup supports printf placeholders, contexts and catalog plural keys, while keeping {{1}}, {{2}} editor tokens stable.
* Storefront language and RTL/layout direction are fully decoupled; ITKT no longer filters the global WordPress locale.
* Added diagnostics for missing native WooCommerce language catalogs. Missing packs do not disable custom ITKT overrides.
* Keeps the legacy visual/global string layer as a compatibility fallback, while canonical gettext overrides take priority for recognized WooCommerce/WordPress system strings.

= 0.11.0 =
* WooCommerce system strings now use canonical gettext source keys instead of relying only on rendered DOM wording.
* Native WooCommerce/WordPress language packs provide the default translation per active language without changing the global site locale or RTL layout.
* IT-Kayali overrides have priority over the native language-pack translation; clearing an override falls back to the WooCommerce standard.
* Live Translation recognizes native WooCommerce/WoodMart system strings and pre-fills Arabic/English/etc. from installed language packs when available.
* Canonical printf placeholders are shown as protected {{1}}, {{2}} tokens in the live editor and converted back safely at gettext runtime.
* Canonical overrides are also exposed to the DOM runtime fallback for WooCommerce Blocks/JavaScript fragments.
* Adds native plural translation fallback through ngettext without globally switching the WordPress locale.

= 0.10.8 =
* Fix: WooCommerce cart/checkout product titles and attributes now have a runtime translation fallback for Blocks/WoodMart output.
* Fix: Storefront language persists through WC AJAX/Store API requests via a session cookie and WooCommerce session fallback.
* Fix: Variable global strings such as tax suffixes also work when the dynamic amount is plain text or rendered by custom cart templates.

= 0.10.7 =
* Fixes global variable strings whose dynamic WooCommerce amounts are wrapped in nested markup (for example "Einschließlich {{1}} MwSt."). The existing amount span is preserved and moved into the translated sentence.
* Storefront language detection now respects the ITKT language cookie during admin-ajax requests, preventing WooCommerce/custom AJAX fragments from falling back to the default language.
* Cart, mini-cart and checkout product names are resolved from the same physical product ID and displayed in the selected language.
* Cart variation/custom attribute labels and values are translated from ITKT product/term translations where available.
* Frontend customer order-detail product names and global attribute meta use the currently selected storefront language.
* The selected language may be stored in WooCommerce session/order meta as informational storefront context only; it does not control emails, invoices or packing slips.

= 0.10.6 =
* Fix protected {{1}}/{{2}} variable validation in RTL fields by normalizing invisible bidi controls and token formatting.
* Adds a content-only RTL layout mode: main/page/WooCommerce content can be mirrored while header and footer stay LTR.
* Full-page RTL remains available separately and takes precedence over content-only RTL.

= 0.10.5 =
* Global system strings now refresh through a public read-only runtime endpoint, so page caches do not keep stale translations.
* Global translations are applied to safe text nodes and common UI attributes after initial load and dynamic AJAX/DOM updates.
* Improves WooCommerce, WoodMart, account/cart/checkout and third-party filter UI string coverage without changing prices, quantities, links or layout.

= 0.10.4 =
* Live Translation now distinguishes reusable global system strings from locator-specific local content.
* WooCommerce, cart, checkout, account, menu and filter/plugin labels are global by default: translate once and reuse everywhere.
* Dynamic money values inside system sentences are protected as {{1}}, {{2}}, ... variables instead of being translated as content.
* Global exact strings can also override gettext output when the visible/source wording matches, improving Loco-Translate-like behavior.
* Header/footer/widget content remains local by default to avoid unintentionally changing unrelated custom content.
* Dropdown/filter options use the same reusable global-string layer.

= 0.9.9 =
* Added system health self-test, routing diagnostics, translation-status metrics and repair tools.
* Added bounded technical diagnostic logging for translated 404s and routing maintenance.
* Added WooCommerce translated slug-index repair tool and rewrite refresh action.

= 0.9.0 =
* Advanced Elementor adapter with widget-specific safe text controls.
* WoodMart/XTemos Elementor widget profiles.
* Builder content area for Elementor templates and detected WoodMart content types.
* Rich repeater support for tabs, accordions, forms and similar widgets.
* Structural/design/link/query fields are explicitly protected from translation.
* Builder translation summary with automatic/manual/WoodMart field counts.


= 0.8.2 =
* Theme/plugin String Translation scanning now runs in resumable AJAX batches instead of one long admin-post request.
* Large themes such as WoodMart no longer need to be recursively parsed within a single web request, preventing common 504/Gateway Timeout errors.
* Directory discovery is incremental and heavy vendor/node_modules/test/cache folders are skipped.
* Live scan panel shows files checked, gettext occurrences found and new strings while scanning.
* Interrupted short AJAX requests retry automatically; saved string translations remain untouched when a source is rescanned.
* Non-JavaScript form fallback creates a resumable scan job and the admin page continues it after redirect.

= 0.8.0 =
* New String Translation module for PHP gettext strings from plugins and themes.
* Read-only source scanner for __(), _e(), _x(), esc_html__(), esc_attr__() and related context/escaping calls.
* Separate Plugins/Themes views with source, status and text search filters.
* Translation matrix follows currently active target languages dynamically.
* Runtime gettext overrides apply saved strings to the currently selected frontend language without modifying source files.
* WooCommerce, WoodMart and other plugins/themes can be scanned individually.
* Dedicated database tables keep string catalog, source references and translations separate from WordPress content.
* JavaScript i18n and complex plural rules are intentionally deferred to a later adapter to avoid unsafe or incorrect replacements.

= 0.7.1 =
* Fix: WooCommerce/WordMart single-product short descriptions now use the active language translation via the woocommerce_short_description filter.
* Added extra compatibility for themes rendering product excerpts through the_excerpt.


= 0.3.2 =
* Translation creation now opens the new language page in a new browser tab while keeping the translation overview open.
= 0.3.1 =
* Elementor duplicates now preserve the original document/container tree exactly.
* Arabic/RTL languages no longer mirror the complete theme or Elementor layout.
* Translation Manager changes Elementor text settings only; container structure is protected.
* Elementor pages no longer expose/edit the raw post_content field in the translation matrix.
* Added a layout repair action for translations created with older plugin versions.


== 0.4.0 ==
* Clean language-directory frontend routing such as /en/, /ar/ and /fr/.
* Language switcher stays on the same logical page across languages.
* Internal duplicate slugs such as home-2 are hidden from public language URLs.
* Language-specific WordPress/WoodMart menu locations and internal menu-link rewriting.
* Auto-created language menus can follow translated page titles.
* Existing ?itkt_lang= links redirect to clean directory URLs.
* WooCommerce products remain one product ID while language URLs load translated product text.


== 0.5.1 ==
* Existing pages/posts can be linked as translations without duplication.
* Excel-compatible UTF-8 CSV import/export for product translations.
* Direct menu-ID mappings for WoodMart/Header builders.
* Larger flag-only language switcher without backgrounds.


== 0.5.2 ==
* Product Excel/CSV export now mirrors every field available in the manual product translation editor.
* Source title, short description and full description are included for reference.
* Custom non-taxonomy product attribute names and values are exported for every product.
* Every active target language gets title, short description, description, custom attribute name and custom attribute values columns.
* CSV import writes custom attribute translations back to the same WooCommerce product without duplicating products or attributes.
* Stable attribute key columns protect attribute mapping when the spreadsheet is edited externally.


== 0.5.3 ==
* Real XLSX product translation export is now the default for better Excel and ChatGPT upload compatibility.
* XLSX files are generated completely on disk before download and streamed with Content-Length and the official Excel MIME type.
* XLSX import is supported, including files edited by Excel or ChatGPT that use shared strings.
* CSV export/import remains available as a fallback.
* Export columns remain dynamic and follow the currently active target languages.
* Excel export includes frozen headers, filters, readable column widths and IT-Kayali navy/white styling.


== 0.5.4 ==
* WooCommerce short description and full/long description are explicitly managed as two separate translatable fields.
* Product editor labels the second field as "Lange Beschreibung" for clarity.
* XLSX/CSV export uses *_short_description and *_long_description columns.
* Imports remain backward-compatible with older *_description columns.


== 0.6.0 ==
* Active language is persisted in a visitor cookie when enabled.
* Optional homepage redirect restores the saved language (for example logo/home stays on /ar/ after Arabic is selected).
* WordPress page/post permalinks, menu links and root home/logo URLs keep the active language.
* JavaScript fallback keeps hard-coded Elementor/WoodMart internal links in the selected language, including dynamically inserted menu/off-canvas links.
* Per-language direction controls: automatic detection, manual LTR/RTL override, and optional full RTL theme/layout mirroring.
* Arabic can remain text-only RTL while Elementor/WoodMart container geometry stays LTR.
* WooCommerce breadcrumb home and return-to-shop URLs keep the active language.
* Frontend settings expose language memory, saved-language homepage behavior and missing-translation fallback.

== 0.7.0 ==
* Language-specific public slugs for pages, posts, WooCommerce products and product terms.
* Canonical URLs follow the active language and translated public slug.
* hreflang alternate links are generated for available published translations plus x-default.
* Per-language SEO title and meta description fields in the translation editors.
* Yoast SEO and Rank Math title/description/canonical filter integration.
* Dedicated translation sitemap at /itkt-translations-sitemap.xml and robots.txt discovery.
* Core WordPress sitemap excludes internal translated duplicate posts such as home-2.
* Product XLSX/CSV import/export now includes slug, SEO title and SEO description for every active target language.
* Old source-language URLs inside a selected language redirect to the translated slug when one exists.




== 0.9.5 ==
* Fix: Unicode/Arabic product slugs now persist correctly. A malformed regex in 0.9.4 could normalize a manually entered Arabic slug to an empty value during save.
* Arabic slugs remain human-readable in the translation editor and are only URL-encoded by the browser/HTTP layer.
* Product translation editor now shows a visible success notice after saving.

== 0.9.3 ==
* Product slugs are no longer generated automatically from translated product titles. An empty language slug now means: use the default-language product URL according to the configured fallback mode.
* Existing legacy auto-generated language slugs are treated as implicit and no longer used for public routing unless they were explicitly saved.
* Product-language 404 recovery can identify a product from legacy/translated slugs and falls back to the default-language product instead of showing a 404 when the target-language slug is unavailable.
* Language-switcher links for products respect the default-language fallback when a target-language slug has not been explicitly translated.

== 0.9.2 ==
* Fix: language switching on WooCommerce single-product pages always resolves the same physical product and uses the target language slug.
* Legacy product translations without the later _itkt_slug_LANG index are detected from stored translation data and indexed lazily on first use.
* Product 404 recovery can resolve stale/malformed language URLs and redirect to the correct DE/EN/AR product URL.
* The language switcher can recover the product ID even while the current request is a 404, preventing an Arabic/English slug from being carried into the default-language URL.


== 0.9.6 ==
* Fix: Arabic/Unicode WooCommerce product slugs are resolved directly from REQUEST_URI and the language-specific product slug index.
* Added a parse_request fail-safe so server-specific rewrite encoding cannot turn a valid translated product URL into a 404.
* A unique explicit slug match for the requested language now directly resolves the physical WooCommerce product ID.
* Legacy translated slugs without a fast index are lazily re-indexed during resolution.

== 0.9.7 ==
* Robust Unicode product routing via SHA-256 slug indexes.
* Lazy migration for existing readable Arabic slug indexes.
* Direct database fallback avoids collation/encoding issues.

= 0.9.8 =
* Reworked translated WooCommerce product routing.
* Added dedicated /LANG/product/SLUG rewrite variables instead of relying only on the generic language catch-all.
* Added a pre_get_posts main-query rescue using REQUEST_URI, so managed hosting/WoodMart stacks cannot leave valid translated product URLs as 404 queries.
* ASCII and Unicode product slugs now use the same routing path.


== 0.10.3 ==
* Live-Übersetzung erweitert auf WooCommerce-Systembereiche: Warenkorb, Checkout, Mein Konto, Produkt-Metadaten, Tabs, Related Products, Notices und Block-basierte WooCommerce-Oberflächen.
* WooCommerce-Systemseiten werden nicht mehr als ein großer Seiteninhalt/Shortcode markiert; einzelne sichtbare Labels und Buttons bleiben anklickbar.
* Dynamische Offcanvas-/Drawer-/Filter-Oberflächen aus eigenen Plugins werden im Live-Modus erkannt und nach AJAX/JavaScript-Updates erneut gescannt.
* Platzhalter von Such-/Formularfeldern sowie kleine Select-/Sortierlisten können direkt im Frontend übersetzt werden.
* Preis-/Mengen-/SKU-/Produktdaten bleiben von visuellen Textübersetzungen ausgeschlossen; Preis-Suffixe wie „inkl. MwSt.“ bleiben übersetzbar.
* Neue Live-Bereiche: WooCommerce, Checkout, Mein Konto und Filter/Plugin-Oberfläche.

== 0.10.2 ==
* Frontend Inline Translation erweitert: Header, Footer, Widgets, Menüs, Mini-Cart/Side-Cart, Breadcrumbs und weitere sichtbare Theme-Texte können direkt im Live-Modus übersetzt werden.
* Globale visuelle Textübersetzungen bleiben unabhängig von Widget-/Theme-Struktur und verändern keine Links, CSS, Container oder Preise.
* Visuelle Übersetzungen werden auch bei dynamisch nachgeladenen Bereichen per MutationObserver angewendet.

== 0.10.1 ==
* Fix: translated WooCommerce short descriptions can no longer leak into the Shop/archive header on non-default languages.
* WooCommerce/WordMart short-description compatibility filters are now restricted to real single-product requests; product-loop translations continue through WooCommerce product getters.

== 0.10.0 ==
* Admin-only frontend inline translation mode.
* Direct WooCommerce product title, short description and long description translation.
* Safe Elementor/WoodMart recognized text editing without changing layout/CSS/URLs.
* Multi-language side panel using the active language list.


= 0.10.9 =
* Arabic locale is now independent from RTL layout, so WooCommerce can load Arabic strings while Header/Footer remain LTR.
* Hardened variable global-string matching and WoodMart cart/checkout product runtime replacements.


= 0.10.10 =
* Hotfix: removed unsafe early locale switching that could cause HTTP 500 immediately after installation.
* Locale changes are now guarded until WordPress init; Arabic text/content RTL remains supported without touching Header/Footer layout.
* Keeps v0.10.9 cart/checkout runtime translation improvements while restoring safe bootstrap behavior.

