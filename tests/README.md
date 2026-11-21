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
- Both DOM cases and all four real-Chrome combinations pass after the fix.
- All 23 PHP files pass PHP 8.1 syntax checks. This is not a PHP 8.0 compatibility guarantee.
- All JavaScript files pass Node syntax checks.
- The browser fixture has been visually inspected. It is not a screenshot of the user's site.

## Before main integration or deployment

These narrow tests do not establish that an installed shop works end to end. Use a staging copy and verify:

1. DE/EN/AR language navigation, including any subdirectory setup and links to other applications on the same domain.
2. Mini-cart and product fragments arriving in quick succession; both must retain their translated labels and correct URLs.
3. Classic and Blocks cart/checkout, coupon/shipping updates and My Account endpoints.
4. WoodMart standard/sticky headers and language switcher on desktop/tablet/mobile.
5. Inline translation save, runtime refresh and page/object cache behavior.

Use staging backups and keep production unchanged until acceptance. No live deployment or main-branch merge is part of this review.

## Further review suggestions, not implemented here

- Add these functional tests to CI. The current workflow checks syntax but does not execute these regression cases. Updating workflow files requires a separately scoped permission.
- Add WordPress integration coverage for backup/restore failure paths. Some destructive database operations and post-update failures need explicit fault-injection tests before the existing transaction code can be treated as proven rollback protection.
- Review frontend-catalog data minimization, especially URL query parameters and account/checkout content, before collecting production storefront text broadly.
