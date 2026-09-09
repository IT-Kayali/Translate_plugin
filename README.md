# IT-Kayali Translate

**Current development version: v0.12.7**

IT-Kayali Translate is a modular multilingual translation plugin for WordPress. The goal is a reusable, distributable translation platform that works with plain WordPress and can optionally integrate with WooCommerce, Elementor, WoodMart and additional themes/builders through adapters.

> Development repository. The plugin is still under active development and has not reached v1.0.

## Current focus

The current development focus is stability of multilingual storefront routing and WooCommerce system pages, especially Cart, Checkout and My Account under language-prefixed URLs such as `/en/` and `/ar/`.

### v0.12.7

- Fixes WooCommerce **My Account** system-page resolution for non-default languages.
- Keeps account endpoints such as Orders, Downloads, Addresses and Account details on the selected language URL.
- Rebuilds WooCommerce account endpoint links at late priority.
- Adds a frontend fallback for WooCommerce/WoodMart account navigation.

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
