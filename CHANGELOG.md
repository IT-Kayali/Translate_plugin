# Changelog

All notable development changes to **IT-Kayali Translate** are recorded here.

The current development version is **0.12.10**.

## [0.12.10]

### Fixed
- Added an independent `itkt_wc_endpoint` rescue marker for non-default-language WooCommerce My Account requests.
- My Account links retain their pretty endpoint path while carrying the canonical endpoint key separately for the server request.
- Server-side endpoint restoration now trusts the validated rescue marker before rewrite-derived endpoint state.
- The normal ITKT frontend bundle now rewrites My Account navigation links and intercepts known account-tab clicks in capture phase, so WoodMart/other scripts cannot redirect them back to Dashboard.
- The temporary marker is removed from the visible browser address after the endpoint page has loaded.
- Endpoint state is synchronized into WordPress/WooCommerce runtime state and the direct endpoint content dispatcher.

### Validation
- All plugin PHP files pass `php -l`.
- `public/assets/frontend.js` passes JavaScript syntax validation.
- A local PHP harness confirmed marker validation and direct dispatch to `woocommerce_account_orders_endpoint`.
- Installation ZIP integrity test passes.
- Real staging verification completed successfully: the non-default-language My Account endpoint fallback issue is resolved in the tested flow.

## [0.12.9]

### Fixed
- Language switching inside WooCommerce My Account preserves the current endpoint instead of resetting to Dashboard.
- Added a direct endpoint-content dispatcher based on the real `/LANG/my-account/ENDPOINT/` browser URL when WooCommerce/WoodMart loses endpoint query state.
- My Account endpoint URL rebuilding now also trusts the current language-prefixed account path.
- WoodMart/account navigation fallback now performs a direct full-page navigation to the canonical endpoint URL, preventing competing theme JavaScript from routing back to Dashboard.

### Validation
- Plugin header, `ITKT_VERSION` and WordPress `readme.txt` stable tag synchronized to 0.12.9.
- All PHP files pass syntax validation.
- Installation ZIP integrity test passes.
- Real staging verification for English and Arabic is still required.

## [0.12.8]

### Fixed
- My Account endpoint URLs are now based on the exact physical WooCommerce system page instead of translated page copies.
- The My Account permalink filter now runs at final priority to prevent WoodMart/other filters from collapsing non-default-language links back to the dashboard.
- Orders, Downloads, Addresses, Account details and related endpoints are rebuilt directly under the selected language prefix.
- Endpoint state is synchronized through `$wp`, `$wp_query` and `set_query_var()` before WooCommerce renders account content.
- Added a footer/click-time fallback for WooCommerce/WoodMart account navigation links.

### Validation
- All plugin PHP files pass syntax validation.
- Local routing test confirms `/ar/mein-konto/orders/` maps to the physical My Account page and exposes the `orders` endpoint.
- Real staging verification is still required for Arabic/English before the issue is considered fully closed.

## [0.12.7]

### Fixed
- WooCommerce My Account system page now resolves to the physical WooCommerce account page for non-default languages.
- Account endpoint links are rebuilt at final priority so language-prefixed Orders, Downloads, Addresses and Account details keep their endpoint.
- Added a lightweight WooCommerce/WoodMart frontend fallback for account navigation links.

## [0.12.6]

### Fixed
- Hardened language-prefixed My Account endpoint routing.
- Added runtime endpoint rescue before WooCommerce account navigation/content renders.
- Prevented Orders, Downloads, Addresses, Account details, Payment methods, View order and Logout from silently falling back to the account dashboard.

## [0.12.5]

### Fixed
- My Account endpoints use the physical WooCommerce My Account page while preserving the virtual storefront language.
- Added late request-URI endpoint rescue for rewrite edge cases.
- Preserved account endpoint values centrally for non-default languages.

## [0.12.4]

### Changed
- Extended WooCommerce My Account endpoint handling for language URLs.
- Expanded automatic frontend-page inspection.
- Improved safe grouped translation of mixed inline text without destroying markup.

## [0.12.3]

### Changed
- Added first-class WooCommerce Checkout Block legal-text translation with protected Terms/Privacy link placeholders.
- Expanded JavaScript i18n coverage for checkout legal/validation strings.

## [0.12.2]

### Changed
- Added WoodMart checkout-step translation support.
- Improved live-editor detection for nested text elements.
- Added safe grouped translation for mixed footer/HTML-block content.

## [0.12.1]

### Changed
- Completed storefront taxonomy translation layer for WooCommerce categories, tags, global attributes and values.
- Improved WoodMart/AJAX taxonomy and layered-navigation language handling.

## [0.12.0]

### Changed
- Hardened frontend WooCommerce translation for Mini-Cart, Cart/Checkout Blocks and My Account.
- Improved product links and translated variation/attribute output under the selected storefront language.

## [0.11.x]

### Milestone
- Introduced canonical WooCommerce/WordPress gettext handling.
- Added native language-pack fallbacks and IT-Kayali override priority.
- Added protected variable/token handling for dynamic strings.
- Improved WooCommerce tax-label translation and storefront-only locale separation.

## [0.10.x]

### Milestone
- Added frontend live translation for administrators.
- Added reusable global system strings and protected dynamic variables.
- Added runtime refresh support for cached/AJAX-generated frontend strings.
- Added content-only RTL mode.
- Added WooCommerce/AJAX/Store API translation fallbacks.
- Added system diagnostics and routing repair tools.

## [0.9.x]

### Milestone
- Added advanced Elementor and WoodMart adapters.
- Hardened WooCommerce product language routing, Unicode slugs and fallbacks.
- Added system health and diagnostics.

## [0.8.x]

### Milestone
- Added plugin/theme PHP gettext String Translation.
- Added resumable AJAX batch scanning for large themes/plugins.

## [0.7.x]

### Milestone
- Added SEO foundations, language-specific slugs, canonical/hreflang handling and translation sitemap.
- Improved WooCommerce single-product short-description translation.

## [0.6.x]

### Milestone
- Added persistent language selection, language-stable internal navigation and configurable RTL behavior.

## [0.5.x]

### Milestone
- Added WooCommerce translation architecture based on one physical product ID.
- Added categories, tags, attributes and values without duplicate WooCommerce objects.
- Added product XLSX/CSV workflows and WoodMart menu integration.

## [0.4.x]

### Milestone
- Added clean language-directory frontend routing such as `/en/`, `/ar/` and `/fr/`.
- Added same-logical-page switching and language-aware internal navigation.

## [0.3.x]

### Milestone
- Added page/post translation creation and Elementor structure copying.
- Added frontend language switcher and multilingual menu handling.
- Stabilized Elementor layout protection and RTL behavior.

## [0.2.x]

### Milestone
- Established central translation workspace.
- Established one-product-ID WooCommerce model.
- Added initial supported-language set and Elementor-aware translation flow.

## [0.1.x]

### Milestone
- Initial modular architecture, setup assistant, language management and admin interface.

---

## Release checklist

For every new version:

1. Update the plugin header version.
2. Update `ITKT_VERSION`.
3. Update `readme.txt` stable tag and changelog.
4. Update the current version in `README.md`.
5. Add the new entry to this `CHANGELOG.md`.
6. Run PHP syntax checks.
7. Test default and non-default language routing.
8. Build the WordPress installation ZIP.
9. Tag the stable Git commit with the matching version.
