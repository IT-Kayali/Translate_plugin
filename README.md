# IT-Kayali Translate

**Current development version: v0.12.10**

IT-Kayali Translate is a modular multilingual translation plugin for WordPress. The goal is a reusable, distributable translation platform that works with plain WordPress and can optionally integrate with WooCommerce, Elementor, WoodMart and additional themes/builders through adapters.

> Development repository. The plugin is still under active development and has not reached v1.0.

## Current focus

The current development focus is stability of multilingual storefront routing and WooCommerce system pages, especially Cart, Checkout and My Account under language-prefixed URLs such as `/en/` and `/ar/`.

### v0.12.10

- Adds an **independent WooCommerce My Account rescue marker** for non-default-language account links. The marker does not depend on WordPress rewrite rules.
- Account links keep their clean pretty path (for example `/en/mein-konto/orders/`) while a short-lived query marker tells the server exactly which WooCommerce endpoint must render.
- The marker is validated against WooCommerce's canonical endpoint map before it can affect rendering.
- The standard frontend JavaScript bundle now rewrites My Account navigation links directly, instead of relying only on a footer-specific WoodMart fallback.
- Capture-phase click handling owns the final destination for known account tabs before WoodMart or another script can collapse the click back to Dashboard.
- After the correct endpoint page loads, the temporary rescue marker is removed from the visible address bar using `history.replaceState()`.
- The server synchronizes the marker into `$wp`, `$wp_query` and WooCommerce endpoint state, and the direct content dispatcher uses the same validated marker as a final fallback.
- All PHP files pass syntax validation, the frontend JavaScript passes syntax validation, and a local marker/dispatcher test passed.

### Staging verification

v0.12.10 has now been confirmed on the real staging site: WooCommerce My Account navigation works again in non-default languages after the rescue-marker fix. The previous Dashboard fallback is considered resolved for the tested account flow.

## Persistent project hand-off

The repository is the canonical hand-off point for continued development, including when work moves to a new chat.

Before changing the plugin, read:

- `README.md` for product architecture and capabilities.
- `CHANGELOG.md` for version history.
- `AGENDA.md` for the current bug status, priorities and next steps.
- The current plugin source for the exact implementation state.

For every new plugin version, the WordPress ZIP delivered in chat and the GitHub source must represent the same version. The release is not considered complete until the plugin version, README, CHANGELOG and AGENDA are synchronized.

### Current verified status

The WooCommerce My Account language-endpoint issue is **resolved in v0.12.10** based on the real staging test. Non-default-language account navigation no longer falls back to Dashboard in the tested flow.

The next development focus is broader WooCommerce end-to-end verification across languages and remaining untranslated frontend/WoodMart strings.

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
