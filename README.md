# IT-Kayali Translate

**Current development version: v0.12.9**

IT-Kayali Translate is a modular multilingual translation plugin for WordPress. The goal is a reusable, distributable translation platform that works with plain WordPress and can optionally integrate with WooCommerce, Elementor, WoodMart and additional themes/builders through adapters.

> Development repository. The plugin is still under active development and has not reached v1.0.

## Current focus

The current development focus is stability of multilingual storefront routing and WooCommerce system pages, especially Cart, Checkout and My Account under language-prefixed URLs such as `/en/` and `/ar/`.

### v0.12.9

- Preserves the active WooCommerce **My Account endpoint when switching language**. Switching from Orders, Downloads, Addresses, Account details or View order no longer intentionally falls back to Dashboard.
- Adds a direct WooCommerce endpoint content dispatcher for language-prefixed account URLs as a final fallback when WordPress/WoodMart loses the endpoint query state.
- Treats the real language-prefixed My Account request path as authoritative when rebuilding account endpoint URLs.
- Strengthens the WoodMart/account navigation fallback: known account links now perform a direct full-page navigation to the canonical language endpoint URL instead of allowing another theme script to redirect to Dashboard.
- Plugin header, ITKT_VERSION and WordPress readme stable tag are synchronized to 0.12.9.
- All plugin PHP files pass syntax validation and the installation ZIP passes archive validation.

### Test still required on the staging site

v0.12.8 failed the real staging test: after switching from the default language to English/Arabic, My Account returned to Dashboard and endpoint tabs still did not open reliably. v0.12.9 specifically addresses that observed behavior. Verify the real staging site before closing the issue.

## Persistent project hand-off

The repository is the canonical hand-off point for continued development, including when work moves to a new chat.

Before changing the plugin, read:

- `README.md` for product architecture and capabilities.
- `CHANGELOG.md` for version history.
- `AGENDA.md` for the current bug status, priorities and next steps.
- The current plugin source for the exact implementation state.

For every new plugin version, the WordPress ZIP delivered in chat and the GitHub source must represent the same version. The release is not considered complete until the plugin version, README, CHANGELOG and AGENDA are synchronized.

### Current verified open issue

The real-world v0.12.8 test confirms that WooCommerce My Account still works only in the default language. After switching to English/Arabic the visitor lands on the account Dashboard, and Orders, Downloads, Addresses and Account details cannot be opened reliably. v0.12.9 is the current fix candidate and remains **in verification** until the staging test succeeds.

## Core principles

- Modular translation core; WooCommerce, Elementor and WoodMart are optional integrations.
- WordPress pages/posts may have linked language versions when a page builder needs a complete document.
- WooCommerce products remain **one physical product ID** across languages.
- Prices, SKU, stock, images, variants and technical product data are not duplicated.
- Translatable product fields are stored language-dependently.
- Theme/plugin source files are read only; translations are stored separately.
- Layout/CSS/IDs/images/URLs/code must not be changed by automatic text translation.
- RTL text can be enabled without forcing the entire theme/layout to mirror.

## Supported languages

The project currently provides language management for:

- German (`de`)
- Arabic (`ar`, RTL)
- English (`en`)
- French (`fr`)
- Spanish (`es`)
- Turkish (`tr`)
- Swedish (`sv`)
- Dutch (`nl`)

Only active languages are shown in translation workflows and the frontend language switcher.

## Main modules

### WordPress content

- Pages and posts
- Linked language versions
- Translation status
- Elementor-aware duplication and editing
- Language-specific slugs and routing

### WooCommerce

- One product ID across languages
- Product title
- Short description
- Long description
- Product categories and tags
- Global attributes and attribute values
- Custom product attributes
- Language-stable product/category links
- Cart / Checkout / My Account language handling
- Product slug routing and fallbacks
- Storefront-only translation layer; backend/order documents are intentionally kept separate

### Elementor / WoodMart

- Structured text-field detection
- Safe translation of supported widget text
- Protection of design/layout/query fields
- WoodMart/XTemos compatibility layer
- Header/menu/layout/HTML-block special cases are being expanded progressively

### String Translation

- PHP gettext scanning for plugins and themes
- Stored translations without editing original source files
- WooCommerce/WordPress native language-pack fallback
- IT-Kayali overrides take priority
- JavaScript i18n and advanced plural handling are still being expanded

### Frontend Live Translation

For logged-in administrators, supported frontend text can be selected and translated directly on the website. The editor protects markup, links and dynamic values where supported.

### SEO / Routing

- Language-prefixed URLs
- Per-language slugs where supported
- Canonical URLs
- hreflang
- x-default
- SEO title/meta fields
- Translation sitemap
- Routing diagnostics and repair tools

### Import / Export

Product translations support XLSX/CSV workflows with dynamic language columns. Existing WooCommerce products are matched by product ID with SKU fallback; imports do not create duplicate products.

## Requirements

- WordPress 6.4 or newer
- PHP 8.0 or newer
- WooCommerce only when WooCommerce features are used
- Elementor/WoodMart only when their adapters are used

## Installation during development

1. Download/build the plugin ZIP.
2. In WordPress go to **Plugins → Add Plugin → Upload Plugin**.
3. Upload the ZIP and replace the existing development version when prompted.
4. After routing-related updates, clear page/server caches and resave **Settings → Permalinks** if required.
5. Test default and non-default languages separately.

## Repository structure

```text
admin/
  assets/
includes/
public/
  assets/
it-kayali-translate.php
readme.txt
uninstall.php
README.md
CHANGELOG.md
```

## Versioning rule

From now on, every release must keep these locations synchronized:

- Plugin header version in `it-kayali-translate.php`
- `ITKT_VERSION`
- WordPress `readme.txt` stable tag
- `README.md` current version
- `CHANGELOG.md`

Every functional change should receive a changelog entry before the version is considered finished.

## Development status toward v1.0

Major foundations already exist: modular core, language management, WordPress/Elementor content workflow, WooCommerce translation without product duplication, product import/export, language routing, RTL controls, SEO basics, string translation, frontend live translation and diagnostics.

Important remaining work includes broader JS/plural string coverage, additional frontend-inline translation targets, role/capability handling, systematic WoodMart edge-case testing, complete WooCommerce end-to-end tests, performance/caching tests, migration/update infrastructure and later licensing/updater work.

## Website

IT-Kayali: https://it-kayali.de
