# Changelog

All notable development changes to **IT-Kayali Translate** are recorded here.

Current development version: **0.12.12**.

## [0.12.12]

### Added
- New modular `ITKT_Dynamic_Runtime` frontend runtime extension.
- JavaScript plural runtime handling for `@wordpress/i18n` `ngettext` and `ngettext_with_context`.
- Additional WooCommerce Blocks/cart/checkout refresh events.
- Additional classic WooCommerce refresh triggers for checkout errors, coupons and shipping-method updates.
- Frontend text discovery for `aria-label`, `title`, placeholders, button values and select-option labels.
- Separate **Notices / Errors** frontend-text area.

### Improved
- Mini-Cart detection now wins before generic popup/offcanvas classification.
- Frontend-catalog MutationObserver also reacts to supported attribute changes.
- Dynamic runtime re-applies published translation maps after AJAX/fragment rerenders.

### Validation
- Plugin header, `ITKT_VERSION` and WordPress `readme.txt` stable tag synchronized to 0.12.12.
- All PHP files pass syntax validation locally.
- All bundled JavaScript files pass syntax validation locally.
- Installation ZIP integrity test passes.
- Real staging verification of the v0.12.11/v0.12.12 frontend-text and dynamic-runtime phase is still required before marking it complete.

## [0.12.11]

### Added
- New `ITKT_Frontend_Catalog` module.
- New backend page **Frontend Texte** grouped by Shop, Search, Product, Category, Filters, Mini-Cart, Cart, Checkout, My Account, Wishlist, Popups/Offcanvas, Header, Footer, Menu and Other.
- Automatic admin-only discovery of supported visible frontend labels while browsing the configured default language.
- Direct translation fields in the backend table for every active non-default language.
- AJAX/DOM MutationObserver discovery for dynamically inserted content.
- WooCommerce fragment/cart/checkout event rescanning.
- Popup/offcanvas/drawer detection.
- Captured frontend labels are stored through the existing global-string system so the current runtime translation layer can reuse them without modifying third-party source files.

### Safety / scope
- Discovery writes are restricted to logged-in administrators with `manage_options`.
- Capture runs only in the default storefront language to prevent translated text from becoming a source string.
- Common price, quantity, order-detail/customer-detail and technical product-value containers are excluded.

### Validation
- Plugin header, `ITKT_VERSION` and WordPress `readme.txt` stable tag synchronized to 0.12.11.
- All PHP files pass syntax validation locally.
- Existing frontend runtime JavaScript plus new `frontend-catalog.js` pass syntax validation.
- Installation ZIP integrity test passes.
- Real staging verification of the new frontend catalog is still required before marking the feature complete.

## [0.12.10]

### Fixed
- Added independent `itkt_wc_endpoint` rescue state for non-default-language WooCommerce My Account requests.
- My Account links keep pretty endpoint paths while the server receives canonical endpoint state.
- Account navigation is protected against WoodMart/other scripts collapsing endpoint clicks back to Dashboard.

### Validation
- Real staging test completed successfully: the non-default-language My Account dashboard-fallback issue is resolved in the tested flow.

## [0.12.9]
- Preserved the active WooCommerce account endpoint during language switching.
- Added direct endpoint content dispatch fallback.
- Hardened WoodMart/account navigation.
- Real staging test still showed Dashboard fallback, so the issue remained open until 0.12.10.

## [0.12.8]
- Hardened physical WooCommerce system-page resolution for My Account endpoints.
- Rebuilt language-prefixed account endpoint links.
- Added runtime query-state synchronization and frontend fallback handling.

## [0.12.1 - 0.12.7]
- Expanded WooCommerce taxonomy translation, My Account routing, Checkout/WoodMart compatibility and dynamic storefront handling.

## [0.12.0]
- Hardened Mini-Cart, Cart/Checkout Blocks and My Account language handling.

## [0.11.x]
- Added canonical WooCommerce/WordPress gettext handling, native language-pack fallback, protected variables and storefront-only locale separation.

## [0.10.x]
- Added frontend live translation, global system strings, cached/AJAX runtime refresh and content-only RTL support.

## Earlier milestones
- Language management and linked WordPress translations.
- Elementor-aware content workflows.
- One-product-ID WooCommerce translation model.
- Product/taxonomy/attribute translation.
- Language routing and translated slugs.
- SEO canonical/hreflang/sitemap basics.
- XLSX/CSV product translation import/export.
