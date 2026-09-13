# IT-Kayali Translate

**Current development version: v0.12.10**

IT-Kayali Translate is a modular multilingual translation plugin for WordPress. The goal is a reusable and distributable translation platform that works with plain WordPress and can optionally integrate with WooCommerce, Elementor, WoodMart and additional themes/builders through adapters.

> Development repository. The plugin is still under active development and has not reached v1.0.

## Repository status

The repository now contains the **complete installable v0.12.10 plugin source directly at the repository root**. The former archive/patch/import layout has been removed from `main`.

For normal development use, the repository itself is the canonical source. No Base64 parts, patch archives or reconstruction workflow are required anymore.

The current source was rebuilt from the verified v0.12.10 installation ZIP and validated before being published to `main`.

## Current focus

The WooCommerce My Account routing problem in non-default languages is resolved in v0.12.10 on the real staging site.

The next development focus is systematic WooCommerce end-to-end verification in every active language and finding remaining untranslated or dynamically generated WooCommerce/WoodMart frontend strings.

### v0.12.10

- Adds an independent WooCommerce My Account rescue marker for non-default-language account links.
- Account links keep their clean pretty path while a short-lived validated marker tells the server which WooCommerce endpoint must render.
- The normal frontend JavaScript bundle rewrites My Account navigation links directly.
- Capture-phase click handling prevents WoodMart or another script from collapsing known account links back to Dashboard.
- The temporary marker is removed from the visible URL after the correct endpoint loads.
- Endpoint state is synchronized into WordPress/WooCommerce runtime state with a direct content dispatcher as final fallback.
- All plugin PHP files pass syntax validation and the frontend JavaScript passes syntax validation.
- Real staging verification succeeded: Orders, Downloads, Addresses and Account details no longer fall back to Dashboard in the tested non-default-language flow.

## Persistent project hand-off

This repository is the canonical hand-off point when development moves to another chat.

Before changing the plugin, read:

- `README.md` for architecture, capabilities and current version.
- `CHANGELOG.md` for version history.
- `AGENDA.md` for current priorities, completed fixes and next steps.
- The plugin source itself for the exact implementation state.

For every new plugin version, the WordPress ZIP delivered in chat and the GitHub source must represent the same version. A version is not considered finished until source, version numbers, README, CHANGELOG and AGENDA are synchronized.

## Core principles

- Modular translation core; WooCommerce, Elementor and WoodMart are optional integrations.
- WordPress pages/posts may have linked language versions when a page builder needs a complete document.
- WooCommerce products remain **one physical product ID** across languages.
- Prices, SKU, stock, images, variants and technical product data are not duplicated.
- Translatable product fields are stored language-dependently.
- Theme/plugin source files are read only; translations are stored separately.
- Layout, CSS, IDs, images, URLs and code must not be changed by automatic text translation.
- RTL text can be enabled without forcing the entire theme/layout to mirror.
- Frontend language selection and backend/admin language remain logically separate.
- Orders, invoices, delivery notes and transactional e-mails are intended to remain in the configured standard language rather than automatically following the storefront language.

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
- Short and long description
- Categories and tags
- Global and custom attributes
- Language-stable product/category links
- Cart / Checkout / My Account language handling
- Product slug routing and fallbacks
- Storefront-only translation layer
- XLSX/CSV product translation workflow

### Elementor / WoodMart

- Structured text-field detection
- Safe translation of supported widget text
- Protection of design/layout/query fields
- WoodMart/XTemos compatibility layer
- Header, menu, layout and HTML-block compatibility is expanded progressively

### String Translation

- PHP gettext scanning for plugins and themes
- Stored translations without editing original source files
- WooCommerce/WordPress native language-pack fallback
- IT-Kayali overrides take priority
- JavaScript i18n and advanced plural handling are still being expanded

### Frontend Live Translation

Logged-in administrators can select supported frontend text and translate it directly on the website. Markup, links and dynamic values are protected where supported.

### SEO / Routing

- Language-prefixed URLs
- Per-language slugs where supported
- Canonical URLs
- hreflang
- x-default
- SEO title/meta fields
- Translation sitemap
- Routing diagnostics and repair tools

## Requirements

- WordPress 6.4 or newer
- PHP 8.0 or newer
- WooCommerce only when WooCommerce features are used
- Elementor/WoodMart only when their adapters are used

## Installation from GitHub

For the current development repository:

1. Open the repository on GitHub.
2. Select **Code → Download ZIP**.
3. In WordPress go to **Plugins → Add Plugin → Upload Plugin**.
4. Upload the downloaded ZIP and install/replace the development version.
5. After routing-related updates, clear page/server caches and resave **Settings → Permalinks** when required.
6. Test the default language and non-default languages separately.

A separately packaged WordPress ZIP may also be supplied in chat for each version. Both GitHub and the chat ZIP must stay on the same version.

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
AGENDA.md
.gitignore
```

Temporary `source/`, `.repo-import/`, `.repo-fix/` and one-time migration workflow folders are not part of the canonical `main` branch anymore.

## Versioning rule

Every functional release must keep these locations synchronized:

- Plugin header version in `it-kayali-translate.php`
- `ITKT_VERSION`
- WordPress `readme.txt` stable tag
- `README.md` current version
- `CHANGELOG.md`
- `AGENDA.md`

Every functional change should receive a changelog entry before the version is considered finished.

## Development status toward v1.0

Major foundations already exist: modular core, language management, WordPress/Elementor content workflow, WooCommerce translation without product duplication, product import/export, language routing, RTL controls, SEO basics, string translation, frontend live translation and diagnostics.

Important remaining work includes broader JS/plural string coverage, additional frontend-inline translation targets, role/capability handling, systematic WoodMart edge-case testing, complete WooCommerce end-to-end tests, performance/caching tests, migration/update infrastructure and later licensing/updater work.

## Website

IT-Kayali: https://it-kayali.de
