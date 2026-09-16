(function () {
  'use strict';
  if (typeof ITKTFrontend === 'undefined') return;

  function setLanguageCookie(code) {
    if (!code) return;
    var path = ITKTFrontend.cookiePath || '/';
    var secure = window.location.protocol === 'https:' ? '; Secure' : '';
    // Always keep a session cookie so WooCommerce AJAX/Store API requests know which storefront
    // language is active. The setting only controls whether that cookie survives the browser session.
    var persistent = ITKTFrontend.remember ? '; Max-Age=' + (60 * 60 * 24 * 365) : '';
    document.cookie = ITKTFrontend.cookieName + '=' + encodeURIComponent(code) + '; Path=' + path + persistent + '; SameSite=Lax' + secure;
  }

  function shouldSkipUrl(url, anchor) {
    if (!url || !anchor) return true;
    if (anchor.classList.contains('itkt-lang-link')) return true;
    var raw = anchor.getAttribute('href') || '';
    if (!raw || raw.charAt(0) === '#' || /^(mailto:|tel:|sms:|javascript:)/i.test(raw)) return true;
    if (url.origin !== window.location.origin) return true;
    if (anchor.hasAttribute('download')) return true;
    var path = url.pathname || '/';
    if (/\/(wp-admin|wp-login\.php|wp-json|wp-content|wp-includes)(\/|$)/i.test(path)) return true;
    if (/\.(pdf|zip|rar|7z|jpe?g|png|gif|webp|svg|mp4|mp3|webm|docx?|xlsx?|csv)(?:$|\?)/i.test(path)) return true;
    return false;
  }

  function languageUrlFor(url) {
    var current = ITKTFrontend.currentLang || '';
    var def = ITKTFrontend.defaultLang || '';
    if (!current || current === def) return url.href;

    var home = new URL(ITKTFrontend.homeUrl || '/', window.location.origin);
    var homePath = home.pathname.replace(/\/+$/, '');
    var path = url.pathname;
    if (homePath && path.indexOf(homePath) === 0) path = path.slice(homePath.length);
    var parts = path.split('/').filter(Boolean);
    var active = ITKTFrontend.activeCodes || [];
    if (parts.length && active.indexOf(parts[0]) !== -1) parts.shift();
    parts.unshift(current);
    var newPath = (homePath || '') + '/' + parts.join('/');
    if (url.pathname.endsWith('/') || parts.length === 1) newPath += '/';
    url.pathname = newPath.replace(/\/{2,}/g, '/');
    return url.href;
  }

  function rewriteAnchor(anchor) {
    if (!anchor || !anchor.href) return;
    var url;
    try { url = new URL(anchor.href, window.location.href); } catch (e) { return; }
    if (shouldSkipUrl(url, anchor)) return;
    var next = languageUrlFor(url);
    if (next && next !== anchor.href) anchor.href = next;
  }

  function rewriteWithin(root) {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll('a[href]').forEach(rewriteAnchor);
  }

  function accountEndpointSelectors(key) {
    return [
      '.woocommerce-MyAccount-navigation-link--' + key + ' a',
      '.woocommerce-MyAccount-navigation-link.' + 'woocommerce-MyAccount-navigation-link--' + key + ' a',
      '.wd-my-account-links .' + key + '-link a',
      '.wd-my-account-links .' + key + '-link > a',
      '.wd-my-account-links [class*="' + key + '"] a'
    ];
  }

  function applyAccountEndpointUrls(root) {
    var urls = ITKTFrontend.accountEndpointUrls || {};
    if (!urls || !Object.keys(urls).length) return;
    root = root && root.querySelectorAll ? root : document;
    Object.keys(urls).forEach(function(key){
      var target = urls[key];
      if (!target) return;
      var selectors = accountEndpointSelectors(key).join(',');
      var nodes = [];
      try {
        if (root.matches && root.matches(selectors)) nodes.push(root);
        nodes = nodes.concat(Array.prototype.slice.call(root.querySelectorAll(selectors)));
      } catch (e) {}
      nodes.forEach(function(a){ if (a && a.setAttribute) a.setAttribute('href', target); });
    });
  }

  function accountEndpointKeyFromAnchor(anchor) {
    if (!anchor || !anchor.closest) return '';
    var urls = ITKTFrontend.accountEndpointUrls || {};
    var keys = Object.keys(urls);
    for (var i = 0; i < keys.length; i++) {
      var key = keys[i];
      var li = anchor.closest('.woocommerce-MyAccount-navigation-link--' + key + ', .' + key + '-link');
      if (li) return key;
      try {
        if (accountEndpointSelectors(key).some(function(selector){ return anchor.matches(selector); })) return key;
      } catch(e) {}
    }
    return '';
  }

  function cleanAccountRescueMarker() {
    var marker = ITKTFrontend.accountEndpointMarker || 'itkt_wc_endpoint';
    var valueMarker = ITKTFrontend.accountEndpointValueMarker || 'itkt_wc_value';
    var url;
    try { url = new URL(window.location.href); } catch(e) { return; }
    if (!url.searchParams.has(marker) && !url.searchParams.has(valueMarker)) return;
    url.searchParams.delete(marker);
    url.searchParams.delete(valueMarker);
    try { window.history.replaceState(window.history.state, document.title, url.pathname + url.search + url.hash); } catch(e) {}
  }

  /* ----------------------------------------------------------------------
   * Visual frontend strings
   * ------------------------------------------------------------------- */
  var VISUAL_ZONE_SELECTOR = [
    'header', '.site-header', '.whb-header', '.whb-main-header', '.wd-header',
    'footer', '.site-footer', '.footer-container', '.wd-footer', '.wd-prefooter', '.copyrights-wrapper',
    'nav', '.wd-nav', '.mobile-nav', '.menu',
    '.widget', '.widget-area', '.sidebar-container',
    '.cart-widget-side', '.wd-side-hidden', '.woocommerce-mini-cart', '.woocommerce-mini-cart__buttons', '.woocommerce-mini-cart__total',
    '.woocommerce', '.woocommerce-page',
    '.woocommerce-cart-form', '.cart-collaterals', '.cart_totals', '.woocommerce-checkout',
    '.woocommerce-checkout-review-order', '.woocommerce-MyAccount-navigation', '.woocommerce-MyAccount-content',
    '.woocommerce-form', '.woocommerce-tabs', '.product_meta', '.summary.entry-summary', '.related.products', '.upsells.products', '.cross-sells',
    '.woocommerce-breadcrumb', '.woocommerce-notices-wrapper', '.shop-loop-head', '.woocommerce-ordering',
    '.page-title', '.wd-page-title', '.wd-checkout-steps', '.woodmart-checkout-steps', '.checkout-steps',
    'main', '#main', '#content', '.site-content', '.main-page-wrapper', '.page-content', '.entry-content',
    '.elementor-location-single', '.elementor-location-archive',
    '.wc-block-cart', '.wp-block-woocommerce-cart', '.wc-block-checkout', '.wp-block-woocommerce-checkout',
    '.wc-block-components-sidebar-layout', '.wc-block-components-totals-wrapper',
    '[role="dialog"]', '[aria-modal="true"]',
    '.offcanvas', '.off-canvas', '[class*="offcanvas"]', '[class*="off-canvas"]', '[class*="drawer"]',
    '[class*="filter-panel"]', '[class*="filter-drawer"]', '[class*="product-filter"]', '[id*="product-filter"]',
    '[class*="filters"]', '[class*="filter"]', '[id*="filter"]'
  ].join(',');
  var VISUAL_TEXT_SELECTOR = 'h1,h2,h3,h4,h5,h6,p,a,button,span,li,label,strong,em,small,div,legend,th,td,dt,dd';
  var VISUAL_CONTROL_SELECTOR = 'input[placeholder],textarea[placeholder],input[type="submit"][value],input[type="button"][value]';

  function visualHash(str) {
    str = String(str || '');
    var h1 = 0x811c9dc5, h2 = 0x9e3779b9;
    for (var i = 0; i < str.length; i++) {
      var c = str.charCodeAt(i);
      h1 ^= c; h1 = Math.imul(h1, 0x01000193);
      h2 ^= c + i; h2 = Math.imul(h2, 0x85ebca6b);
    }
    function hex(n){ return ('00000000' + (n >>> 0).toString(16)).slice(-8); }
    return hex(h1) + hex(h2);
  }


  function normalizeGlobalText(v) {
    return String(v == null ? '' : v).replace(/\s+/g, ' ').trim();
  }

  /**
   * Turn rendered money values into protected tokens. Example:
   * "Einschließlich 8,64 € MwSt." => "Einschließlich {{1}} MwSt."
   * Pure prices are excluded earlier by the WooCommerce dynamic-value guard.
   */
  function variableTemplate(v) {
    var text = normalizeGlobalText(v), i = 0;
    if (!text) return text;
    return text.replace(/(?:\d{1,3}(?:[.\s]\d{3})*|\d+)(?:[,.]\d{1,2})?\s*(?:€|EUR)/gi, function(match){
      i += 1; return '{{' + i + '}}';
    });
  }

  function escapeRegex(v) { return String(v || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  function matchTemplate(template, actual) {
    template = normalizeGlobalText(template); actual = normalizeGlobalText(actual);
    if (!template || template.indexOf('{{') === -1) return null;
    var parts = template.split(/(\{\{\d+\}\})/g), tokenOrder = [];
    var source = '^';
    parts.forEach(function(part){
      var m = part.match(/^\{\{(\d+)\}\}$/);
      if (m) { tokenOrder.push(parseInt(m[1],10)); source += '(.+?)'; }
      else source += escapeRegex(part);
    });
    source += '$';
    var match;
    try { match = actual.match(new RegExp(source, 'u')); } catch(e) { return null; }
    if (!match) return null;
    var values = {};
    tokenOrder.forEach(function(n, idx){ values[n] = match[idx+1]; });
    return values;
  }

  function fillTemplate(template, values) {
    return String(template || '').replace(/\{\{(\d+)\}\}/g, function(all,n){
      return Object.prototype.hasOwnProperty.call(values || {}, n) ? values[n] : all;
    });
  }

  function globalTranslationFor(original) {
    var map = ITKTFrontend.globalTranslations || {}, source = normalizeGlobalText(original);
    if (!source) return '';
    var exact = map.exact || {};
    if (Object.prototype.hasOwnProperty.call(exact, source) && exact[source] !== '') return exact[source];
    var patterns = map.patterns || [];
    for (var i=0; i<patterns.length; i++) {
      var row = patterns[i] || {}, values = matchTemplate(row.source || '', source);
      if (values) return fillTemplate(row.translation || '', values);
    }
    return '';
  }

  /*
   * Apply reusable system strings independently from the inline-editor locator system.
   * A global string is keyed by its wording, therefore it must also work in markup that the
   * editor cannot safely identify as one direct element (WooCommerce Blocks, WoodMart fragments,
   * third-party AJAX drawers, nested labels, etc.). We only touch text/label attributes; DOM
   * structure, links, prices, quantities and form/customer values remain untouched.
   */
  var runtimeApplying = false;

  function runtimeNodeSkipped(el) {
    if (!el || !el.closest) return true;
    if (!el.closest(VISUAL_ZONE_SELECTOR)) return true;
    if (el.closest('#wpadminbar,.itkt-inline-panel,.itkt-inline-overlay,.itkt-inline-toast,.itkt-language-switcher,script,style,noscript,svg,code,pre')) return true;
    return false;
  }

  function runtimeTextNodeAllowed(node) {
    if (!node || node.nodeType !== 3 || !node.parentElement) return false;
    var el = node.parentElement;
    if (runtimeNodeSkipped(el)) return false;
    if (el.closest('textarea,select')) return false;
    // Product/order/customer values are deliberately not global system strings.
    if (isDynamicWooValue(el)) return false;
    return true;
  }

  function replaceRuntimeTextNode(node, translation) {
    if (!node || typeof translation !== 'string' || translation === '') return false;
    var raw = String(node.nodeValue || '');
    var lead = (raw.match(/^\s*/) || [''])[0];
    var trail = (raw.match(/\s*$/) || [''])[0];
    var next = lead + translation + trail;
    if (next === raw) return false;
    node.nodeValue = next;
    return true;
  }

  /*
   * Pattern strings often contain a dynamic WooCommerce amount wrapped in its own <span>.
   * A plain text-node walker only sees "Einschließlich " and " MwSt." separately, so a
   * template such as "Einschließlich {{1}} MwSt." can never match. Handle these compact
   * compound labels at element level while moving the existing dynamic amount node into the
   * translated sentence. Price markup/classes remain untouched.
   */
  function elementDepth(el) {
    var d = 0; while (el && el.parentElement) { d++; el = el.parentElement; } return d;
  }

  function deepestValueNode(el, value) {
    value = normalizeGlobalText(value);
    if (!el || !value || !el.querySelectorAll) return null;
    var matches = Array.prototype.slice.call(el.querySelectorAll('*')).filter(function(child){
      if (runtimeNodeSkipped(child)) return false;
      return normalizeGlobalText(child.textContent || '') === value;
    });
    if (!matches.length) return null;
    matches.sort(function(a,b){
      var al = (a.textContent || '').length, bl = (b.textContent || '').length;
      if (al !== bl) return al - bl;
      return elementDepth(b) - elementDepth(a);
    });
    return matches[0];
  }

  function renderPatternElement(el, translation, values) {
    if (!el || !translation || !values) return false;
    var parts = String(translation).split(/(\{\{\d+\}\})/g);
    var valueNodes = {}, usable = false;
    Object.keys(values).forEach(function(key){
      var node = deepestValueNode(el, values[key]);
      if (node) { valueNodes[key] = node; usable = true; }
    });

    if (!usable) {
      // Several classic WooCommerce/WoodMart templates output the tax amount as plain text
      // instead of a nested .amount span. In that case preserving child markup is impossible,
      // but the label itself is a safe leaf/compact element. Rebuild only its text content with
      // the captured dynamic values and never touch interactive descendants.
      if (el.querySelector && el.querySelector('a,button,input,select,textarea,form')) return false;
      if (el.children && el.children.length > 2) return false;
      var filled = fillTemplate(translation, values);
      if (!filled) return false;
      el.textContent = filled;
      return true;
    }

    var frag = document.createDocumentFragment();
    parts.forEach(function(part){
      var m = part.match(/^\{\{(\d+)\}\}$/);
      if (!m) { if (part) frag.appendChild(document.createTextNode(part)); return; }
      var key = m[1];
      if (valueNodes[key]) frag.appendChild(valueNodes[key]);
      else frag.appendChild(document.createTextNode(values[key] != null ? String(values[key]) : part));
    });
    while (el.firstChild) el.removeChild(el.firstChild);
    el.appendChild(frag);
    return true;
  }

  function applyGlobalPatternElements(root) {
    var map = ITKTFrontend.globalTranslations || {}, patterns = map.patterns || [];
    if (!patterns.length) return;
    var start = root && root.querySelectorAll ? root : document;
    var selector = [
      '.includes_tax', '.tax_label', '.woocommerce-price-suffix',
      '.cart_totals small', '.order-total small', '.woocommerce-cart-form small',
      '.woocommerce-checkout-review-order small', '.woocommerce-checkout small',
      '.wc-block-components-totals-item__description', '.wc-block-components-totals-footer-item-tax',
      '.wc-block-components-totals-item__description small', '.wc-block-components-totals-footer-item-tax small'
    ].join(',');
    var els = [];
    if (start.matches && start.matches(selector)) els.push(start);
    if (start.querySelectorAll) els = els.concat(Array.prototype.slice.call(start.querySelectorAll(selector)));

    // Fallback for custom WoodMart/WooCommerce templates that use no standard tax class at all.
    // Only compact elements inside commerce zones are considered and a pattern must match the
    // *entire* visible text, so product rows/containers cannot be flattened accidentally.
    var commerceZones = '.woocommerce,.woocommerce-page,.wc-block-cart,.wp-block-woocommerce-cart,.wc-block-checkout,.wp-block-woocommerce-checkout,.cart-widget-side,.woocommerce-mini-cart,.wd-cart-content,.wd-cart-table,.wd-cart-totals,.wd-checkout,.wd-my-account,.cart-content-wrapper,.checkout-content-wrapper';
    if (start.querySelectorAll) {
      Array.prototype.slice.call(start.querySelectorAll(commerceZones + ' small,' + commerceZones + ' span,' + commerceZones + ' p,' + commerceZones + ' div,' + commerceZones + ' label')).forEach(function(el){
        if (els.indexOf(el) !== -1) return;
        if (el.querySelector && el.querySelector('a,button,input,select,textarea,form,table')) return;
        if (el.children && el.children.length > 4) return;
        var text = normalizeGlobalText(el.textContent || '');
        if (!text || text.length > 220) return;
        for (var pi=0; pi<patterns.length; pi++) {
          if (matchTemplate((patterns[pi] || {}).source || '', text)) { els.push(el); break; }
        }
      });

      // Some WoodMart templates render tax suffixes outside the standard WooCommerce wrapper.
      // Scan compact visible labels globally, but only accept an element when the *entire* text
      // matches one of our stored variable templates. This keeps the operation safe and avoids
      // flattening arbitrary page content.
      Array.prototype.slice.call(start.querySelectorAll('small,span,p,label,strong,em,div')).forEach(function(el){
        if (els.indexOf(el) !== -1 || runtimeNodeSkipped(el)) return;
        if (el.querySelector && el.querySelector('a,button,input,select,textarea,form,table,ul,ol')) return;
        if (el.children && el.children.length > 4) return;
        var text = normalizeGlobalText(el.textContent || '');
        if (!text || text.length > 180) return;
        for (var gi=0; gi<patterns.length; gi++) {
          if (matchTemplate((patterns[gi] || {}).source || '', text)) { els.push(el); break; }
        }
      });
    }
    // Deepest first prevents a parent and its matching child from both being rewritten.
    els.sort(function(a,b){ return elementDepth(b) - elementDepth(a); });
    els.forEach(function(el){
      if (runtimeNodeSkipped(el)) return;
      var actual = normalizeGlobalText(el.textContent || '');
      if (!actual || actual.length > 300) return;
      for (var i=0; i<patterns.length; i++) {
        var row = patterns[i] || {}, values = matchTemplate(row.source || '', actual);
        if (!values) continue;
        if (renderPatternElement(el, row.translation || '', values)) break;
      }
    });
  }

  function applyGlobalTextNodes(root) {
    var map = ITKTFrontend.globalTranslations || {};
    if (!map || (!(map.exact && Object.keys(map.exact).length) && !(map.patterns && map.patterns.length))) return;
    var start = root && root.nodeType ? root : document.body;
    if (!start) return;

    var walkerRoot = start.nodeType === 9 ? (start.body || start.documentElement) : start;
    if (!walkerRoot) return;
    var walker;
    try { walker = document.createTreeWalker(walkerRoot, NodeFilter.SHOW_TEXT, null); } catch(e) { return; }
    var node = walker.currentNode && walker.currentNode.nodeType === 3 ? walker.currentNode : walker.nextNode();
    while (node) {
      if (runtimeTextNodeAllowed(node)) {
        var raw = String(node.nodeValue || '');
        var source = normalizeGlobalText(raw);
        if (source && !/^[\d\s.,€$£%+\-–—:;()\/\\]+$/.test(source)) {
          var translated = globalTranslationFor(source);
          if (translated) replaceRuntimeTextNode(node, translated);
        }
      }
      node = walker.nextNode();
    }
  }

  function applyGlobalAttributes(root) {
    var map = ITKTFrontend.globalTranslations || {};
    if (!map || (!(map.exact && Object.keys(map.exact).length) && !(map.patterns && map.patterns.length))) return;
    var start = root && root.querySelectorAll ? root : document;
    var els = [];
    if (start.matches && start.matches('[placeholder],[title],[aria-label],input[type="submit"][value],input[type="button"][value]')) els.push(start);
    if (start.querySelectorAll) els = els.concat(Array.prototype.slice.call(start.querySelectorAll('[placeholder],[title],[aria-label],input[type="submit"][value],input[type="button"][value]')));
    els.forEach(function(el){
      if (runtimeNodeSkipped(el) || isDynamicWooValue(el)) return;
      var attrs = ['placeholder','title','aria-label'];
      if (el.matches('input[type="submit"],input[type="button"]')) attrs.push('value');
      attrs.forEach(function(attr){
        if (!el.hasAttribute(attr)) return;
        var original = normalizeGlobalText(el.getAttribute(attr));
        if (!original || /^[\d\s.,€$£%+\-–—:;()\/\\]+$/.test(original)) return;
        var translated = globalTranslationFor(original);
        if (translated && translated !== original) el.setAttribute(attr, translated);
      });
    });
  }


  /* ----------------------------------------------------------------------
   * WooCommerce cart / checkout product runtime translations
   * ------------------------------------------------------------------- */
  var COMMERCE_RUNTIME_SELECTOR = [
    '.woocommerce', '.woocommerce-page', '.cart-widget-side', '.woocommerce-mini-cart', '.woocommerce-cart-form', '.cart-collaterals', '.cart_totals',
    '.woocommerce-checkout', '.woocommerce-checkout-review-order', '.woocommerce-order',
    '.woocommerce-MyAccount-content', '.wishlist-content', '.wishlist_table',
    '.wc-block-cart', '.wp-block-woocommerce-cart', '.wc-block-checkout', '.wp-block-woocommerce-checkout',
    '.wc-block-components-sidebar-layout',
    '.wd-cart-content', '.wd-cart-table', '.wd-cart-totals', '.wd-checkout', '.wd-my-account',
    '.cart-content-wrapper', '.checkout-content-wrapper'
  ].join(',');

  function commerceReplacementPairs() {
    var products = ITKTFrontend.commerceTranslations || [];
    var targets = {}, conflicts = {};
    products.forEach(function(row){
      if (!row) return;
      var sourceTitle = normalizeGlobalText(row.sourceTitle || ''), targetTitle = normalizeGlobalText(row.title || '');
      if (sourceTitle && targetTitle && sourceTitle !== targetTitle) {
        if (targets[sourceTitle] && targets[sourceTitle] !== targetTitle) conflicts[sourceTitle] = true;
        else targets[sourceTitle] = targetTitle;
      }
      var reps = row.replacements || {};
      Object.keys(reps).forEach(function(source){
        var src = normalizeGlobalText(source), dst = normalizeGlobalText(reps[source]);
        if (!src || !dst || src === dst) return;
        if (targets[src] && targets[src] !== dst) conflicts[src] = true;
        else targets[src] = dst;
      });
    });
    return Object.keys(targets).filter(function(src){ return !conflicts[src]; }).map(function(src){
      return {source:src, target:targets[src]};
    }).sort(function(a,b){ return b.source.length - a.source.length; });
  }

  function applyCommerceTranslations(root) {
    var pairs = commerceReplacementPairs();
    if (!pairs.length) return;
    var start = root && root.nodeType ? root : document.body;
    if (!start) return;
    var roots = [];
    if (start.matches && start.matches(COMMERCE_RUNTIME_SELECTOR)) roots.push(start);
    if (start.querySelectorAll) roots = roots.concat(Array.prototype.slice.call(start.querySelectorAll(COMMERCE_RUNTIME_SELECTOR)));
    if (!roots.length && start.closest) {
      var parent = start.closest(COMMERCE_RUNTIME_SELECTOR); if (parent) roots.push(parent);
    }
    // Last safe fallback: if the current page is a WooCommerce runtime view rendered with a
    // theme-specific wrapper, use the main content area rather than giving up. Replacement pairs
    // are exact source product/attribute strings, so this remains scoped and deterministic.
    if (!roots.length && document.body && (document.body.classList.contains('woocommerce-cart') || document.body.classList.contains('woocommerce-checkout') || document.body.classList.contains('woocommerce-account'))) {
      var main = document.querySelector('main,.site-content,.main-page-wrapper,.container');
      if (main) roots.push(main);
    }
    roots = roots.filter(function(el, idx, arr){ return el && arr.indexOf(el) === idx; });
    roots.forEach(function(zone){
      var walker;
      try { walker = document.createTreeWalker(zone, NodeFilter.SHOW_TEXT, null); } catch(e) { return; }
      var node = walker.nextNode();
      while (node) {
        if (node.parentElement && !node.parentElement.closest('script,style,noscript,svg,code,pre,textarea,select,#wpadminbar,.itkt-inline-panel,.itkt-inline-overlay')) {
          var raw = String(node.nodeValue || ''), next = raw;
          pairs.forEach(function(pair){
            if (next.indexOf(pair.source) !== -1) next = next.split(pair.source).join(pair.target);
          });
          if (next !== raw) node.nodeValue = next;
        }
        node = walker.nextNode();
      }
    });
  }

  /* ----------------------------------------------------------------------
   * WooCommerce taxonomy / WoodMart filter runtime translations
   * ------------------------------------------------------------------- */
  var TAXONOMY_RUNTIME_SELECTOR = [
    '.widget_layered_nav', '.woocommerce-widget-layered-nav', '.woocommerce-widget-layered-nav-list',
    '.wd-filter', '.wd-filter-list', '.wd-filter-wrapper', '.wd-widget-layered-nav', '.wd-filter-content',
    '.woodmart-woocommerce-layered-nav', '.wd-active-filters', '.woodmart-active-filters',
    '.filters-area', '.area-sidebar-shop', '.sidebar-container', '.widget-area', '.wd-side-hidden',
    '.product-categories', '.wd-product-cats', '.wd-nav-product-cat', '.wd-nav-product-cat-wrap',
    '.category-grid-item', '.product-category', '.wd-product-category', '.product_meta',
    '.woocommerce-breadcrumb', '.wd-breadcrumbs', '.breadcrumbs',
    '.woocommerce-products-header', '.term-description', '.wd-term-desc', '.shop-loop-head'
  ].join(',');

  function taxonomyReplacementPairs() {
    var map = ITKTFrontend.taxonomyTranslations || {};
    var rows = [].concat(map.terms || [], map.attributes || []);
    var targets = {}, conflicts = {};
    rows.forEach(function(row){
      if (!row) return;
      var source = normalizeGlobalText(row.source || ''), target = normalizeGlobalText(row.target || '');
      if (!source || !target || source === target) return;
      if (targets[source] && targets[source] !== target) conflicts[source] = true;
      else targets[source] = target;
    });
    return Object.keys(targets).filter(function(source){ return !conflicts[source]; }).map(function(source){
      return {source:source, target:targets[source]};
    }).sort(function(a,b){ return b.source.length - a.source.length; });
  }

  function translateTaxonomyText(raw, pairs) {
    var value = String(raw || ''), trimmed = normalizeGlobalText(value);
    if (!trimmed) return value;
    for (var i=0; i<pairs.length; i++) {
      var pair = pairs[i];
      if (trimmed === pair.source) {
        var left = value.match(/^\s*/); var right = value.match(/\s*$/);
        return (left ? left[0] : '') + pair.target + (right ? right[0] : '');
      }
      // Common filter/breadcrumb forms keep punctuation in the same text node, e.g. "Farbe:"
      // or "Farbe: Rot". Replace only when the source is delimited rather than embedded inside
      // another word, preventing short term names from altering unrelated storefront copy.
      var escaped = pair.source.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      try {
        var re = new RegExp('(^|[\\s:;,/|()\\[\\]–—-])' + escaped + '(?=$|[\\s:;,/|()\\[\\]–—-])', 'gu');
        var next = value.replace(re, function(match, prefix){ return prefix + pair.target; });
        if (next !== value) value = next;
      } catch(e) {}
    }
    return value;
  }

  function applyTaxonomyTranslations(root) {
    var pairs = taxonomyReplacementPairs();
    if (!pairs.length) return;
    var start = root && root.nodeType ? root : document.body;
    if (!start) return;
    var roots = [];
    if (start.matches && start.matches(TAXONOMY_RUNTIME_SELECTOR)) roots.push(start);
    if (start.querySelectorAll) roots = roots.concat(Array.prototype.slice.call(start.querySelectorAll(TAXONOMY_RUNTIME_SELECTOR)));
    if (!roots.length && start.closest) {
      var parent = start.closest(TAXONOMY_RUNTIME_SELECTOR); if (parent) roots.push(parent);
    }
    roots = roots.filter(function(el, idx, arr){ return el && arr.indexOf(el) === idx; });
    roots.forEach(function(zone){
      var walker;
      try { walker = document.createTreeWalker(zone, NodeFilter.SHOW_TEXT, null); } catch(e) { return; }
      var node = walker.nextNode();
      while (node) {
        if (node.parentElement && !node.parentElement.closest('script,style,noscript,svg,code,pre,textarea,#wpadminbar,.itkt-inline-panel,.itkt-inline-overlay')) {
          var raw = String(node.nodeValue || ''), next = translateTaxonomyText(raw, pairs);
          if (next !== raw) node.nodeValue = next;
        }
        node = walker.nextNode();
      }

      // Native/selectWoo layered-nav options are not text nodes in the open dropdown until the
      // control is initialized. Updating the <option> text keeps both native and SelectWoo views
      // aligned after WoodMart refreshes a filter via AJAX.
      if (zone.querySelectorAll) {
        Array.prototype.forEach.call(zone.querySelectorAll('option'), function(option){
          var raw = String(option.textContent || ''), next = translateTaxonomyText(raw, pairs);
          if (next !== raw) option.textContent = next;
        });
      }
    });
  }

  function applyGlobalRuntimeTranslations(root) {
    if (runtimeApplying) return;
    runtimeApplying = true;
    try {
      applyGlobalPatternElements(root || document);
      applyGlobalTextNodes(root || document);
      applyGlobalAttributes(root || document);
      applyCommerceTranslations(root || document);
      applyTaxonomyTranslations(root || document);
    } finally { runtimeApplying = false; }
  }

  function refreshRuntimeTranslations() {
    var lang = ITKTFrontend.currentLang || '';
    if (!lang || !ITKTFrontend.runtimeAjaxUrl) {
      applyGlobalRuntimeTranslations(document);
      applyVisualTranslations(document);
      return Promise.resolve(false);
    }
    var url;
    try {
      url = new URL(ITKTFrontend.runtimeAjaxUrl, window.location.href);
      url.searchParams.set('action', ITKTFrontend.runtimeAction || 'itkt_runtime_strings');
      url.searchParams.set('lang', lang);
      url.searchParams.set('_itkt', String(Date.now()));
    } catch(e) { return Promise.resolve(false); }
    return fetch(url.href, { credentials:'same-origin', cache:'no-store', headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (!res || !res.success || !res.data) return false;
        ITKTFrontend.visualTranslations = res.data.visualTranslations || {};
        ITKTFrontend.globalTranslations = res.data.globalTranslations || {exact:{},patterns:[]};
        ITKTFrontend.commerceTranslations = res.data.commerceTranslations || [];
        ITKTFrontend.taxonomyTranslations = res.data.taxonomyTranslations || {terms:[],attributes:[]};
        if (window.ITKTI18nRuntimeUpdate && res.data.i18nTranslations) {
          window.ITKTI18nRuntimeUpdate(res.data.i18nTranslations);
        }
        applyGlobalRuntimeTranslations(document);
        applyVisualTranslations(document);
        return true;
      }).catch(function(){ return false; });
  }

  function cleanClasses(el) {
    if (!el || !el.classList) return [];
    return Array.prototype.slice.call(el.classList).filter(function(cls){
      return cls &&
        !/^itkt-/.test(cls) &&
        !/^(active|current|selected|hover|focus|open|opened|visible|hidden|show|is-|has-)/.test(cls) &&
        !/^menu-item-\d+$/.test(cls) &&
        !/^elementor-element-[a-f0-9]+$/i.test(cls) &&
        !/^(wd-active|wd-opened|wd-loaded)$/.test(cls);
    }).slice(0, 2);
  }

  function nthOfType(el) {
    if (!el || !el.parentElement) return 1;
    var siblings = Array.prototype.filter.call(el.parentElement.children, function(n){ return n.tagName === el.tagName; });
    return Math.max(1, siblings.indexOf(el) + 1);
  }

  function stableSegment(el) {
    if (!el || !el.tagName) return '';
    var tag = el.tagName.toLowerCase();
    var id = el.getAttribute('id') || '';
    if (id && !/^wpadminbar$/.test(id) && !/^elementor-\d+$/.test(id) && id.length < 80) return tag + '#' + id;
    var dataId = el.getAttribute('data-id');
    if (dataId && /^[A-Za-z0-9_-]{3,64}$/.test(dataId)) return tag + '[data-id="' + dataId + '"]';
    var name = el.getAttribute('name');
    if (name && /^[A-Za-z0-9_\-\[\]]{2,80}$/.test(name)) return tag + '[name="' + name + '"]';
    var classes = cleanClasses(el);
    return tag + (classes.length ? '.' + classes.join('.') : '') + ':nth-of-type(' + nthOfType(el) + ')';
  }

  function visualScope(el) {
    if (!el || !el.closest) return '';
    if (el.closest('.woocommerce-error,.woocommerce-message,.woocommerce-info,.wc-block-components-notice-banner,[role="alert"],.wd-notice,.wd-alert')) return 'notices';
    if (el.closest('.cart-widget-side,.woocommerce-mini-cart,.woocommerce-mini-cart__buttons,.woocommerce-mini-cart__total,.wd-cart-content,.wd-header-cart')) return 'mini-cart';
    if (el.closest('.woocommerce-checkout,.wc-block-checkout,.wp-block-woocommerce-checkout,.wd-checkout-steps,.woodmart-checkout-steps,.checkout-steps')) return 'checkout';
    if (el.closest('.woocommerce-cart-form,.cart-collaterals,.wc-block-cart,.wp-block-woocommerce-cart')) return 'cart';
    if (el.closest('.woocommerce-MyAccount-navigation,.woocommerce-MyAccount-content,.wd-my-account')) return 'account';
    if (el.closest('.wishlist_table,.wd-wishlist-content,[class*="wishlist"]')) return 'wishlist';
    if (el.closest('[class*="filter-panel"],[class*="filter-drawer"],[class*="product-filter"],[id*="product-filter"],[class*="filters"],[class*="filter"],[id*="filter"]')) return 'filter';
    if (el.closest('[role="dialog"],[aria-modal="true"],.popup,.wd-popup,.mfp-wrap,.offcanvas,.off-canvas,[class*="offcanvas"],[class*="drawer"],.wd-side-hidden')) return 'popup';
    if (el.closest('nav,.wd-nav,.mobile-nav,.menu')) return 'menu';
    if (el.closest('footer,.site-footer,.footer-container,.wd-footer,.wd-prefooter,.copyrights-wrapper')) return 'footer';
    if (el.closest('header,.site-header,.whb-header,.whb-main-header,.wd-header')) return 'header';
    if (el.closest('.single-product,.summary.entry-summary,.woocommerce-tabs')) return 'product';
    if (document.body && document.body.classList.contains('search-results')) return 'search';
    if (document.body && document.body.classList.contains('tax-product_cat')) return 'category';
    if (document.body && (document.body.classList.contains('post-type-archive-product') || document.body.classList.contains('woocommerce-shop'))) return 'shop';
    if (el.closest('.woocommerce,.wc-block-components-sidebar-layout')) return 'woocommerce';
    if (el.closest('.widget,.widget-area,.sidebar-container')) return 'widget';
    return 'global';
  }

  function visualZoneRoot(el) {
    if (!el || !el.closest) return null;
    return el.closest(VISUAL_ZONE_SELECTOR);
  }

  function visualLocator(el) {
    var root = visualZoneRoot(el);
    if (!root) return '';
    var parts = [], cur = el, guard = 0;
    while (cur && guard++ < 8) {
      parts.unshift(stableSegment(cur));
      if (cur === root) break;
      cur = cur.parentElement;
    }
    return visualScope(el) + '|' + parts.join('>');
  }

  function visualTextAllowed(text, el) {
    text = String(text || '').replace(/\s+/g, ' ').trim();
    if (!text || text.length < 2 || text.length > 500) return false;
    if (!/^[\d\s.,€$£%+\-–—:;()\/\\]+$/.test(text)) return true;
    // Opening-hour values are legitimate editable footer content even though they consist only
    // of numbers and punctuation. Prices/quantities remain excluded by isDynamicWooValue().
    return visualScope(el) === 'footer' && /^(?:\d{1,2}:\d{2})(?:\s*[-–—]\s*\d{1,2}:\d{2})?$/.test(text);
  }

  function directTextNode(el) {
    if (!el || !el.childNodes) return null;
    var nodes = Array.prototype.filter.call(el.childNodes, function(node){ return node.nodeType === 3 && String(node.nodeValue || '').trim() !== ''; });
    if (nodes.length !== 1) return null;
    var raw = String(nodes[0].nodeValue || '');
    var text = raw.replace(/\s+/g, ' ').trim();
    if (!visualTextAllowed(text, el)) return null;
    return { node: nodes[0], text: text, raw: raw };
  }

  // HTML blocks in a shared footer often split one visible line around <strong>, <a> or <br>.
  // Keep that markup intact and expose each text fragment as one field in a grouped live-editor
  // target instead of flattening/replacing the whole HTML block.
  function compositeTextParts(el) {
    if (!el || !el.childNodes || !el.querySelectorAll) return [];
    var scope = visualScope(el);
    if (!scope) return [];
    if (isDynamicWooValue(el)) return [];
    // Checkout legal text has its own protected %1$s/%2$s compound-system target.
    if (el.closest && el.closest('.wc-block-checkout__terms')) return [];
    if (!el.matches('p,span,label,small,div,li,dd,dt,blockquote,figcaption,address')) return [];
    if (el.matches('a,button,input,textarea,select,option')) return [];
    if (el.querySelector('form,input,textarea,select,button,table,ul,ol,img,picture,video,iframe,script,style,svg')) return [];

    // Safe inline markup only. This catches WooCommerce/My-Account sentences containing links,
    // strong tags or line breaks without flattening layout containers.
    var allowed = {BR:1,SPAN:1,STRONG:1,B:1,EM:1,I:1,SMALL:1,A:1,S:1,U:1,SUP:1,SUB:1,ABBR:1,MARK:1,CODE:1};
    var children = Array.prototype.slice.call(el.children || []);
    if (children.some(function(child){ return !allowed[child.tagName]; })) return [];
    if (scope !== 'footer' && children.length > 10) return [];

    var nodes = [];
    var walker;
    try { walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null); } catch(e) { return []; }
    var node = walker.nextNode();
    while (node) {
      if (node.parentElement && !node.parentElement.closest('script,style,noscript,svg,code,pre,textarea,#wpadminbar,.itkt-inline-panel,.itkt-inline-overlay')) {
        var raw = String(node.nodeValue || ''), text = raw.replace(/\s+/g, ' ').trim();
        if (visualTextAllowed(text, node.parentElement) && !isDynamicWooValue(node.parentElement)) nodes.push({node:node,text:text,raw:raw});
      }
      node = walker.nextNode();
    }
    if (nodes.length < 2 || nodes.length > 20) return [];
    var total = nodes.reduce(function(sum,row){ return sum + row.text.length; },0);
    return total <= (scope === 'footer' ? 900 : 1200) ? nodes : [];
  }

  function isDynamicWooValue(el) {
    if (!el || !el.closest) return false;
    // Tax/price suffixes such as "inkl. MwSt." are labels and may be translated.
    if (el.closest('.woocommerce-price-suffix,.tax_label')) return false;
    if (el.closest('.price,.amount,.woocommerce-Price-amount,.quantity,.qty,.sku,.product-remove,.variation-Quantity')) return true;
    if (el.closest('.products .product')) return true;
    if (el.closest('.mini_cart_item .wd-entities-title,.mini_cart_item .product-title,.mini_cart_item .quantity')) return true;
    if (el.closest('.woocommerce-cart-form__cart-item .product-name,.woocommerce-cart-form__cart-item .product-price,.woocommerce-cart-form__cart-item .product-subtotal')) return true;
    if (el.closest('.woocommerce-checkout-review-order-table .cart_item .product-name,.woocommerce-checkout-review-order-table .product-total')) return true;
    if (el.closest('.woocommerce-orders-table__cell-order-number,.woocommerce-orders-table__cell-order-date,.woocommerce-orders-table__cell-order-total')) return true;
    return false;
  }

  function visualCandidateAllowed(el) {
    if (!el || !el.closest) return false;
    if (!el.closest(VISUAL_ZONE_SELECTOR)) return false;
    if (el.closest('#wpadminbar,.itkt-inline-panel,.itkt-inline-overlay,.itkt-inline-toast,.itkt-language-switcher,script,style,noscript,svg')) return false;
    if (isDynamicWooValue(el)) return false;
    if (el.matches('input,textarea,select')) return false;
    if (el.matches('option') && !selectOptionsAllowed(el.parentElement)) return false;
    return true;
  }

  function selectOptionsAllowed(select) {
    if (!select || select.tagName !== 'SELECT') return false;
    var count = select.options ? select.options.length : 0;
    if (!count || count > 30) return false;
    var idName = ((select.id || '') + ' ' + (select.name || '')).toLowerCase();
    if (/(country|state|billing_country|shipping_country|billing_state|shipping_state)/.test(idName)) return false;
    return !!select.closest(VISUAL_ZONE_SELECTOR);
  }

  // WooCommerce Checkout Blocks render the legal consent sentence with two nested <a>
  // elements. A direct text-node collector would expose only fragments (or the two link labels),
  // making it impossible to translate/reorder the whole sentence safely. Expose the complete
  // canonical gettext source as one compound target; WooCommerce itself keeps the links alive via
  // %1$s and %2$s.
  function collectCheckoutTermsTargets(zone, out, seenEls) {
    if (!zone || !zone.querySelectorAll) return;
    var labels = [];
    if (zone.matches && zone.matches('.wc-block-checkout__terms .wc-block-components-checkbox__label')) labels.push(zone);
    labels = labels.concat(Array.prototype.slice.call(zone.querySelectorAll('.wc-block-checkout__terms .wc-block-components-checkbox__label')));
    labels.forEach(function(el){
      if (!el || seenEls.indexOf(el) !== -1 || !visualCandidateAllowed(el)) return;
      var terms = el.closest('.wc-block-checkout__terms');
      if (!terms) return;
      var checkbox = !!terms.querySelector('#terms-and-conditions,input[name="terms"]');
      var source = checkbox
        ? 'You must accept our %1$s and %2$s to continue with your purchase.'
        : 'By proceeding with your purchase you agree to our %1$s and %2$s';
      var actual = normalizeGlobalText(el.textContent || '');
      if (!actual) return;
      var locator = visualLocator(el); if (!locator) return;
      // Include a stable marker for the checkbox variant so PHP can select the matching canonical
      // source even when the surrounding React markup changes slightly between WooCommerce builds.
      if (checkbox) locator += '|terms-and-conditions';
      seenEls.push(el);
      out.push({
        el:el, node:null, kind:'compound_system', original:source, actualOriginal:actual,
        locator:locator, scope:'checkout', fingerprint:visualHash('native|woocommerce|' + source)
      });
    });
  }

  function collectControlTargets(zone, out, seenControls) {
    var controls = [];
    if (zone.matches && zone.matches(VISUAL_CONTROL_SELECTOR)) controls.push(zone);
    controls = controls.concat(Array.prototype.slice.call(zone.querySelectorAll ? zone.querySelectorAll(VISUAL_CONTROL_SELECTOR) : []));
    controls.forEach(function(el){
      if (seenControls.indexOf(el) !== -1 || !el.closest(VISUAL_ZONE_SELECTOR)) return;
      if (el.closest('#wpadminbar,.itkt-inline-panel,.itkt-inline-overlay,.itkt-inline-toast')) return;
      seenControls.push(el);
      var attr = el.hasAttribute('placeholder') ? 'placeholder' : 'value';
      var original = String(el.getAttribute(attr) || '').replace(/\s+/g, ' ').trim();
      if (!original || original.length < 2 || original.length > 300 || /^[\d\s.,€$£%+\-–—:;()\/\\]+$/.test(original)) return;
      var locator = visualLocator(el); if (!locator) return;
      out.push({
        el:el, node:null, kind:'attribute', attr:attr, original:original,
        locator:locator, scope:visualScope(el), fingerprint:visualHash(locator + '|attr:' + attr)
      });
    });

    var selects = [];
    if (zone.matches && zone.matches('select')) selects.push(zone);
    selects = selects.concat(Array.prototype.slice.call(zone.querySelectorAll ? zone.querySelectorAll('select') : []));
    selects.forEach(function(select){
      if (!selectOptionsAllowed(select) || seenControls.indexOf(select) !== -1) return;
      seenControls.push(select);
      Array.prototype.forEach.call(select.options || [], function(option){
        var info = directTextNode(option); if (!info) return;
        var locator = visualLocator(option); if (!locator) return;
        out.push({
          el:option, control:select, node:info.node, kind:'option', original:info.text,
          locator:locator, scope:visualScope(select), fingerprint:visualHash(locator)
        });
      });
    });
  }

  function collectCompositeTargets(zone, out, seenGroups) {
    var candidates = [];
    if (zone.matches && zone.matches(VISUAL_TEXT_SELECTOR)) candidates.push(zone);
    candidates = candidates.concat(Array.prototype.slice.call(zone.querySelectorAll ? zone.querySelectorAll(VISUAL_TEXT_SELECTOR) : []));
    candidates.forEach(function(el){
      if (seenGroups.indexOf(el) !== -1 || !visualCandidateAllowed(el)) return;
      // A normal single text node is already handled by the standard collector.
      if (directTextNode(el)) return;
      var parts = compositeTextParts(el);
      if (!parts.length) return;
      var locator = visualLocator(el); if (!locator) return;
      seenGroups.push(el);
      parts.forEach(function(info, idx){
        out.push({
          el:el, control:el, node:info.node, kind:'fragment', groupKind:'fragments',
          original:info.text, locator:locator + '|text-node:' + (idx + 1),
          scope:visualScope(el), fingerprint:visualHash(locator + '|text-node:' + (idx + 1))
        });
      });
    });
  }

  function collectVisualTargets(root) {
    root = root && root.querySelectorAll ? root : document;
    var roots = [];
    if (root.matches && root.matches(VISUAL_ZONE_SELECTOR)) roots.push(root);
    if (root.querySelectorAll) roots = roots.concat(Array.prototype.slice.call(root.querySelectorAll(VISUAL_ZONE_SELECTOR)));
    var seenEls = [], seenControls = [], seenGroups = [], out = [];
    roots.forEach(function(zone){
      collectCheckoutTermsTargets(zone, out, seenEls);
      var candidates = [];
      if (zone.matches && zone.matches(VISUAL_TEXT_SELECTOR)) candidates.push(zone);
      candidates = candidates.concat(Array.prototype.slice.call(zone.querySelectorAll ? zone.querySelectorAll(VISUAL_TEXT_SELECTOR) : []));
      candidates.forEach(function(el){
        if (seenEls.indexOf(el) !== -1 || !visualCandidateAllowed(el)) return;
        seenEls.push(el);
        var info = directTextNode(el); if (!info) return;
        var locator = visualLocator(el); if (!locator) return;
        out.push({
          el:el, node:info.node, kind:(el.tagName === 'OPTION' ? 'option' : 'text'),
          control:(el.tagName === 'OPTION' ? el.parentElement : null),
          original:info.text, locator:locator, scope:visualScope(el), fingerprint:visualHash(locator)
        });
      });
      collectCompositeTargets(zone, out, seenGroups);
      collectControlTargets(zone, out, seenControls);
    });
    return out;
  }

  function replaceVisualNode(target, translation) {
    if (!target || typeof translation !== 'string' || translation === '') return;
    if (target.kind === 'attribute' && target.el && target.attr) {
      target.el.setAttribute(target.attr, translation);
      target.el.setAttribute('data-itkt-visual-applied', target.fingerprint);
      return;
    }
    if (!target.node) return;
    var raw = String(target.node.nodeValue || '');
    var lead = (raw.match(/^\s*/) || [''])[0];
    var trail = (raw.match(/\s*$/) || [''])[0];
    target.node.nodeValue = lead + translation + trail;
    if (target.el && target.el.setAttribute) target.el.setAttribute('data-itkt-visual-applied', target.fingerprint);
  }

  function applyVisualTranslations(root) {
    var localMap = ITKTFrontend.visualTranslations || {};
    var globalMap = ITKTFrontend.globalTranslations || {};
    var hasLocal = localMap && Object.keys(localMap).length;
    var hasGlobal = globalMap && ((globalMap.exact && Object.keys(globalMap.exact).length) || (globalMap.patterns && globalMap.patterns.length));
    if (!hasLocal && !hasGlobal) return;
    collectVisualTargets(root).forEach(function(target){
      var translation = hasLocal ? localMap[String(target.fingerprint || '').toLowerCase()] : '';
      if (typeof translation === 'string' && translation !== '') { replaceVisualNode(target, translation); return; }
      translation = hasGlobal ? globalTranslationFor(target.original) : '';
      if (typeof translation === 'string' && translation !== '') replaceVisualNode(target, translation);
    });
  }

  window.ITKTVisualTools = {
    collect: collectVisualTargets,
    hash: visualHash,
    locator: visualLocator,
    scope: visualScope,
    directTextNode: directTextNode,
    template: variableTemplate,
    normalizeGlobalText: normalizeGlobalText,
    isDynamic: isDynamicWooValue,
    zoneSelector: VISUAL_ZONE_SELECTOR,
    apply: applyVisualTranslations,
    applyGlobal: applyGlobalRuntimeTranslations,
    refresh: refreshRuntimeTranslations
  };

  document.addEventListener('DOMContentLoaded', function () {
    applyGlobalRuntimeTranslations(document);
    applyVisualTranslations(document);
    // Apply canonical My Account links before the generic internal-link language rewrite.
    applyAccountEndpointUrls(document);
    rewriteWithin(document);
    applyAccountEndpointUrls(document);
    cleanAccountRescueMarker();
    // Always fetch the latest string map once. This bypasses full-page cache staleness.
    refreshRuntimeTranslations();

    if (typeof MutationObserver !== 'undefined') {
      var runtimeTimer = 0;
      var observer = new MutationObserver(function (mutations) {
        if (runtimeApplying) return;
        var roots = [];
        mutations.forEach(function (mutation) {
          Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
            if (node.nodeType === 1) roots.push(node);
          });
          if (mutation.type === 'characterData' && mutation.target && mutation.target.parentElement) roots.push(mutation.target.parentElement);
        });
        if (!roots.length) return;
        window.clearTimeout(runtimeTimer);
        runtimeTimer = window.setTimeout(function(){
          roots.forEach(function(node){
            applyGlobalRuntimeTranslations(node);
            applyVisualTranslations(node);
            if (node.matches && node.matches('a[href]')) rewriteAnchor(node);
            rewriteWithin(node);
            applyAccountEndpointUrls(node);
          });
        }, 80);
      });
      observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    }
  });


  // WooCommerce classic fragments and Blocks can replace the cart DOM after our initial runtime
  // map was loaded. Refresh both global strings and product translations after those events.
  document.addEventListener('wc-blocks_added_to_cart', function(){ window.setTimeout(refreshRuntimeTranslations, 40); });
  document.addEventListener('wc-blocks_removed_from_cart', function(){ window.setTimeout(refreshRuntimeTranslations, 40); });
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed updated_wc_div updated_cart_totals updated_checkout', function(){
      window.setTimeout(refreshRuntimeTranslations, 40);
    });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest && event.target.closest('a[href]');
    if (!link) return;
    if (link.matches('.itkt-lang-link[data-itkt-lang]')) {
      setLanguageCookie(link.getAttribute('data-itkt-lang'));
      return;
    }
    var accountKey = accountEndpointKeyFromAnchor(link);
    if (accountKey && ITKTFrontend.accountEndpointUrls && ITKTFrontend.accountEndpointUrls[accountKey]) {
      // Capture phase: own the final destination before WoodMart/account scripts can collapse the
      // click back to the dashboard. The rescue marker guarantees server-side endpoint dispatch.
      event.preventDefault();
      event.stopImmediatePropagation();
      window.location.assign(ITKTFrontend.accountEndpointUrls[accountKey]);
      return;
    }
    rewriteAnchor(link);
  }, true);
})();
