# Frontend regression checks

Requires Node.js 18 or newer. Test dependencies are development-only and are not loaded by WordPress.

```sh
npm ci
npm test
```

This executes the actual `public/assets/frontend.js` in jsdom. It reproduces two failures on 0.12.39:

- Two AJAX/DOM insertions 20 ms apart must both be processed after the 80 ms debounce. Previously, the second observer callback cancelled the first root's scheduled work.
- On a WordPress site whose home is `/shop/`, links to `/shopping/` and `/support/` must not be rewritten into the shop's language directory. Store links still get the active prefix; external, admin and language-switch links remain unchanged. Query strings and fragments are preserved.

For a real headless-Chrome check:

```sh
CHROME_PATH=/path/to/google-chrome npm run test:browser
```

`CHROME_PATH` defaults to `/usr/bin/google-chrome`. A locally installed Chrome is required; no browser download is included. Optional `TEST_SCREENSHOT=/path/to/check.png` saves the English desktop fixture. The browser runner checks English/Arabic at 1440 px and 390 px, both AJAX fragments, scoped URLs and uncaught page errors. All network requests are fulfilled by a local test fixture, not a real shop. The fixture deliberately does not load WordPress, WooCommerce, WoodMart or third-party extensions.

## Results for this review

- The two DOM regression cases failed against the original 0.12.39 frontend source and pass with the changes.
- All three DOM cases and all four real-Chrome combinations pass after the fixes.
- All 32 repository PHP files (including AI-module tests) pass PHP 8.1 syntax checks. This is not a PHP 8.0 compatibility guarantee.
- All JavaScript files pass Node syntax checks.
- The browser fixture has been visually inspected. It is not a screenshot of the user's site.

## Language-menu keyboard extension (0.12.41)

```sh
npm run test:switcher
# All suites:
npm run test:all
```

The actual plugin frontend CSS/JS is loaded into `tests/fixtures/language-switcher.html`, a local copy of the dropdown structure with a test-page shell. Chrome checks 1440/768/390 px: keyboard open with Enter/Space/arrows, wrapping arrow navigation, Home/End, Escape focus return, normal Tab exit, mouse click, focus-leave closing, ARIA state and viewport bounds. Set `SCREENSHOT_DIR` to save desktop/tablet/mobile images. The page itself labels screenshots as local tests, not the customer shop. A complete accessibility audit is not claimed.

A third DOM regression reproduces insertion of a bare text node into an existing button. Previously the observer ignored that child-list mutation; the new case now passes locally and in the four language/browser combinations.

## Before main integration or deployment

These narrow tests do not establish that an installed shop works end to end. Use a staging copy and verify:

1. DE/EN/AR language navigation, including any subdirectory setup and links to other applications on the same domain.
2. Mini-cart and product fragments arriving in quick succession; both must retain their translated labels and correct URLs.
3. Classic and Blocks cart/checkout, coupon/shipping updates and My Account endpoints.
4. WoodMart standard/sticky headers and language switcher on desktop/tablet/mobile.
5. Inline translation save, runtime refresh and page/object cache behavior.

Use staging backups and keep production unchanged until acceptance. No live deployment or main-branch merge is part of this review. (Superseded 2026-11-22: the 0.12.42 build including these fixes was deployed to the production shop under a verified backup/rollback safeguard and checked read-only; main remains unmerged and checkout/payment/mobile acceptance remains open.)

## Further review suggestions, not implemented here

- Add these functional tests to CI. The current workflow checks syntax but does not execute these regression cases. Updating workflow files requires a separately scoped permission.
- Add WordPress integration coverage for backup/restore failure paths. Some destructive database operations and post-update failures need explicit fault-injection tests before the existing transaction code can be treated as proven rollback protection.
- Review frontend-catalog data minimization, especially URL query parameters and account/checkout content, before collecting production storefront text broadly.

## AI API module (0.12.42)

Run `npm run test:ai` with PHP 8.0+ available, or set `PHP_BIN` to its executable. This runs the guard/service/provider, admin flow and atomic-storage boundary tests, renders the actual PHP admin page with mocked data, and checks it in Chrome. No real API request or credential is used. `npm run test:all` now includes this suite. See `docs/AI-TRANSLATION.md` for setup, exact scope and remaining staging/live-provider checks.
