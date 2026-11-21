# Automatic API translation, development 0.12.42

This review build adds **IT-Kayali Translate → KI-Übersetzung** for administrators. It automatically generates translations of selected catalog strings through a selectable provider. It does not require manual typing of the translated text. Generation and publication are deliberately separate actions.

## Initial providers

- OpenAI Chat Completions, fixed `api.openai.com` endpoint. Enter an available text-model ID from your own account in the form. No model is hardcoded, since model lifecycle and account access change.
- DeepL API Free, fixed `api-free.deepl.com` endpoint.
- DeepL API Pro, fixed `api.deepl.com` endpoint.

No custom base URL, provider fallback, scheduled call or automatic retry is enabled. An error stops the batch. A timeout can still incur a provider charge even if no preview is returned, so check provider usage before manually retrying.

## Server-side configuration

Configure only the providers you intend to use. Set the following server environment variables, or define constants with the same names in `wp-config.php` before WordPress starts:

- `ITKT_OPENAI_API_KEY`
- `ITKT_DEEPL_API_KEY`

Use your server's secret configuration mechanism. Never commit real keys or put them in frontend JavaScript, translation fields, screenshots, tickets or chats. The plugin does not provide a browser key-entry field and does not save API keys in WordPress options or its backup export. It reports only whether a key is present, not whether it is live/valid. Other logging/security plugins or server administrators may still observe outgoing requests, so secure those systems too.

## Workflow

1. Search the string catalog and select up to three non-plural, non-visual strings. The latest 30 matching records are shown. Review the exact source texts. The catalog can include previously captured personal content: do not select customer/order/payment data.
2. Choose provider and the actual source/target language. Catalog strings are not necessarily all written in the site's default language. For OpenAI, enter a currently available model ID.
3. Confirm the selected-text disclosure and possible API cost for this one batch. No key validation request or live test is made merely by opening the page.
4. Submit **Auswahl automatisch übersetzen**. Existing translations are skipped without a provider call. The request is limited to three entries, 4 KB per source and 12 KB total. OpenAI output is limited to 4096 completion tokens. These are request-size limits, not a monetary spending cap. Use provider-side budgets/quotas as well.
5. Review the generated suggestions. They are stored in a short-lived, user-specific WordPress transient for 15 minutes, not exposed to other administrators through this page. A persistent object cache or database backup may retain transient data outside that UI lifetime.
6. Select reviewed suggestions and explicitly confirm publication. Apply uses source-hash checks plus an atomic insert with exact-source matching. It cannot overwrite a concurrent existing translation. The catalog's normal translation-saved hook invalidates runtime caches. A new preview supersedes the old preview form.

Generation does not publish output. There are no background calls on customer visits. The first version targets catalog strings, **not full product fields, Elementor documents, entire pages, plural catalogs or visual-locator records**. It is not a claim that every plugin content type now has AI translation.

## Output guards

HTML tags/comments, shortcode syntax, printf placeholders and ITKT `{{n}}` variables are replaced with opaque markers before translation and restored only after validation. Missing, duplicated or unknown markers, added recognized markup, and changed structural ordering are rejected. Numbered printf variables can move for grammar; unnumbered printf variables retain order. This is a syntax-integrity guard, not semantic quality verification or a complete HTML/security parser. WordPress sanitization still runs before saving.

## Test evidence and limitations

The development tests use dummy credentials and mock the HTTP boundary. They cover provider request/response shapes, fixed hosts, key exclusion, bounded responses, no retries, auth/nonce/consent gating, duplicate-submit protection, preview isolation, stale-source and existing-translation protection, and explicit application. The actual atomic save method is tested against a DB boundary double, not a MySQL server. Browser screenshots show the actual PHP-rendered admin content with mocked database/API and a local visual shell, not an installed WordPress admin or customer shop.

**No real API call, paid request, live provider credential, customer content transmission, WordPress installation, deployment or main-branch merge has been performed.** Before main integration: test the installed module on staging, supply server secrets securely, and obtain explicit approval for the provider/content/cost of any real test. Verify model availability, language support and provider account limits there. Review translation quality before publication.

## Running the development tests

With PHP 8.0+ and Node 18+:

```sh
npm ci
php tests/ai-http-provider.php
php tests/ai-admin-flow.php
php tests/ai-apply-storage.php
php tests/ai-admin-flow.php render > /tmp/itkt-ai-admin.html
AI_ADMIN_HTML=/tmp/itkt-ai-admin.html SCREENSHOT_DIR=/tmp CHROME_PATH=/path/to/chrome node tests/ai-admin-browser.cjs
npm run test:all
```

`ai-http-provider.php` includes the guard and service suites. It replaces WordPress HTTP with an in-process mock, not a live API. Fixture keys are synthetic dummy values.

## API references checked for this implementation

- OpenAI Chat Completions request reference: https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create/index.md
- DeepL text translation endpoint: https://developers.deepl.com/api-reference/translate/request-translation
- DeepL text translation examples: https://developers.deepl.com/docs/translate/translate-text-quickstart

References establish documented request shapes, not live account access, model availability, pricing or a provider's approval of this plugin.
