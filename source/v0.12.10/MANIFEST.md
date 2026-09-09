# IT-Kayali Translate v0.12.10 source manifest

This directory stores the exact **v0.12.10 delta** on top of the archived **v0.12.9** source state.

## Base and target

- Base: **v0.12.9**
- Target: **v0.12.10**
- Development date: **09.09.2026**

## Why v0.12.10 exists

The real staging test of v0.12.9 still failed for WooCommerce **My Account** in non-default languages. English loaded and translated, but Orders, Downloads, Addresses and Account details still fell back to or remained on the Dashboard.

v0.12.10 no longer relies only on WordPress pretty-path rewrite state. It adds an independent, validated WooCommerce endpoint rescue marker for the request and removes that temporary marker from the visible URL after the correct page loads.

## Changed files

- `it-kayali-translate.php`
- `readme.txt`
- `includes/class-itkt-frontend.php`
- `includes/class-itkt-woocommerce.php`
- `public/assets/frontend.js`

## Main changes

- Added validated `itkt_wc_endpoint` / optional `itkt_wc_value` request markers.
- Server restores the exact WooCommerce endpoint from the marker before rewrite-derived state.
- Endpoint runtime is synchronized through WordPress/WooCommerce query state.
- Direct WooCommerce endpoint-content dispatch remains available as the final server fallback.
- Normal `public/assets/frontend.js` now rewrites My Account links.
- Capture-phase click handling owns known account-tab navigation before WoodMart/other scripts can redirect to Dashboard.
- The rescue marker is removed from the visible address after page load with `history.replaceState()`.
- Version synchronized to **0.12.10** in plugin header, `ITKT_VERSION` and WordPress `readme.txt`.

## Rebuild the delta archive

Concatenate these files **in this exact order**:

1. `patch-part-00.b64`
2. `patch-part-01.b64`
3. `patch-part-02-03.b64`
4. `patch-tail-00.b64`
5. `patch-tail-01.b64`
6. `patch-tail-02.b64`
7. `patch-tail-03.b64`
8. `patch-tail-04.b64`
9. `patch-tail-05.b64`
10. `patch-tail-06.b64`
11. `patch-tail-07.b64`

Example:

```bash
cat patch-part-00.b64 \
    patch-part-01.b64 \
    patch-part-02-03.b64 \
    patch-tail-00.b64 \
    patch-tail-01.b64 \
    patch-tail-02.b64 \
    patch-tail-03.b64 \
    patch-tail-04.b64 \
    patch-tail-05.b64 \
    patch-tail-06.b64 \
    patch-tail-07.b64 \
    > itkt1210-patch.tar.xz.b64

base64 --decode itkt1210-patch.tar.xz.b64 > itkt1210-patch.tar.xz
```

Expected Base64 length: **60656 characters**.

Expected SHA-256 of the decoded delta archive:

```text
92a8435fb6f42fe9f672d438313aa2f9a1a97ab922720528b340d5ff01971c28
```

Apply/extract the delta over the v0.12.9 plugin source:

```bash
tar -xJf itkt1210-patch.tar.xz
```

## Validation

- All plugin PHP files passed `php -l`.
- `public/assets/frontend.js` passed JavaScript syntax validation.
- Local marker/dispatcher harness passed: an `orders` rescue marker dispatches `woocommerce_account_orders_endpoint`.
- Installation ZIP passed archive-integrity validation.
- All Base64 chunks stored here were verified against the local source chunks by exact length and checksum comparison.

## Chat installation package

Filename:

```text
IT-Kayali-Translate-v0.12.10-account-rescue-fix.zip
```

SHA-256:

```text
dd18aea5099b932f77cee0a73522937b760207f9a9b1943262d7b8cc050ebe82
```

## Status

**P0 remains in verification.** The issue must not be marked complete until English and Arabic My Account endpoint navigation works on the real staging environment.

After the staging test, update `AGENDA.md`, `README.md`, and `CHANGELOG.md` before starting the next release.
