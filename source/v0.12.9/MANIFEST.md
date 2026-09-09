# IT-Kayali Translate v0.12.9 source manifest

This directory stores the exact **v0.12.9 delta** on top of the previously archived **v0.12.8** source package.

## Base version

- Base: **v0.12.8**
- Target: **v0.12.9**
- Development date: **09.09.2026**

## Rebuild the v0.12.9 patch

Concatenate the six Base64 chunks in numerical order:

```bash
cat patch-part-00.b64 patch-part-01.b64 patch-part-02.b64 \
    patch-part-03.b64 patch-part-04.b64 patch-part-05.b64 \
    > itkt129-patch.tar.xz.b64

base64 --decode itkt129-patch.tar.xz.b64 > itkt129-patch.tar.xz
```

Expected SHA-256 for `itkt129-patch.tar.xz`:

```text
c57ccdcab195c78017a13070ee6187f42d30cc5d309f629cd232b125cf4b7786
```

Extract the patch over the v0.12.8 plugin source:

```bash
tar -xJf itkt129-patch.tar.xz
```

## Files changed in v0.12.9

- `it-kayali-translate.php`
- `readme.txt`
- `includes/class-itkt-frontend.php`
- `includes/class-itkt-woocommerce.php`

## Main fix

v0.12.8 failed the real staging test for WooCommerce My Account in non-default languages. After switching to English or Arabic, the account view returned to Dashboard and Orders, Downloads, Addresses and Account details could not be opened reliably.

v0.12.9 therefore:

- preserves the active WooCommerce My Account endpoint while switching languages;
- derives endpoint state from the real language-prefixed browser URL;
- adds a direct endpoint-content dispatcher if WordPress/WoodMart loses the WooCommerce query var;
- forces known account-navigation links to use a full navigation to the canonical language endpoint URL, preventing competing theme JavaScript from routing back to Dashboard.

## Version synchronization

The following are set to **0.12.9**:

- WordPress plugin header
- `ITKT_VERSION`
- WordPress `readme.txt` stable tag
- repository `README.md`
- repository `CHANGELOG.md`
- repository `AGENDA.md`

## Validation

- All PHP files passed `php -l`.
- Installation ZIP passed archive-integrity validation.
- Real staging verification is still required before the My Account issue is marked closed.

## Chat installation package

Filename:

```text
IT-Kayali-Translate-v0.12.9-account-language-state-fix.zip
```

SHA-256:

```text
c4ac67f64e0915202bfa575fc387db04c597b17b31192895b51e72f62bcbfd5f
```

The staging test result must be recorded in `AGENDA.md` before moving on to the next P0/P1 item.
