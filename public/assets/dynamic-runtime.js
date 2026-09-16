(function(){
  'use strict';
  if (typeof window === 'undefined' || typeof ITKTDynamicRuntime === 'undefined') return;

  var cfg = ITKTDynamicRuntime || {};

  var pluralRules = null;
  try { if (window.Intl && Intl.PluralRules) pluralRules = new Intl.PluralRules(cfg.locale || cfg.language || undefined); } catch(e) {}

  function pluralValue(row, number, fallback){
    if (!row || typeof row !== 'object') return fallback;
    var category = '';
    if (pluralRules) { try { category = pluralRules.select(Number(number)); } catch(e) {} }
    if (category && row.forms && typeof row.forms[category] === 'string' && row.forms[category] !== '') return row.forms[category];
    var value = Number(number) === 1 ? row.single : row.plural;
    return typeof value === 'string' && value !== '' ? value : fallback;
  }
  function ngettext(translation, single, plural, number, domain){
    domain = domain || 'default';
    var map = cfg.ngettext || {}, key = String(single || '') + '\0' + String(plural || '');
    if (map[domain] && Object.prototype.hasOwnProperty.call(map[domain], key)) return pluralValue(map[domain][key], number, translation);
    return translation;
  }
  function ngettextContext(translation, single, plural, number, context, domain){
    domain = domain || 'default'; context = context || '';
    var map = cfg.ngettextContext || {}, key = String(single || '') + '\0' + String(plural || '');
    if (map[domain] && map[domain][context] && Object.prototype.hasOwnProperty.call(map[domain][context], key)) return pluralValue(map[domain][context][key], number, translation);
    return translation;
  }

  if (window.wp && wp.hooks && wp.hooks.addFilter) {
    wp.hooks.addFilter('i18n.ngettext', 'itkt/dynamic-runtime-ngettext', ngettext, 100);
    wp.hooks.addFilter('i18n.ngettext_with_context', 'itkt/dynamic-runtime-ngettext-context', ngettextContext, 100);
  }

  function refresh(){
    if (window.ITKTVisualTools && typeof window.ITKTVisualTools.refresh === 'function') {
      window.ITKTVisualTools.refresh();
    } else if (window.ITKTVisualTools && typeof window.ITKTVisualTools.applyGlobal === 'function') {
      window.ITKTVisualTools.applyGlobal(document);
    }
  }

  ['wc-blocks_added_to_cart','wc-blocks_removed_from_cart','wc-blocks_updated_cart','wc-blocks_updated_checkout'].forEach(function(name){
    document.addEventListener(name,function(){ window.setTimeout(refresh,50); });
  });
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed updated_wc_div updated_cart_totals updated_checkout checkout_error applied_coupon removed_coupon updated_shipping_method country_to_state_changed',function(){
      window.setTimeout(refresh,50);
    });
  }

  if (typeof MutationObserver !== 'undefined') {
    var timer = 0;
    var observer = new MutationObserver(function(mutations){
      var relevant = mutations.some(function(m){ return m.type === 'attributes'; });
      if (!relevant) return;
      window.clearTimeout(timer);
      timer = window.setTimeout(function(){
        if (window.ITKTVisualTools && typeof window.ITKTVisualTools.applyGlobal === 'function') window.ITKTVisualTools.applyGlobal(document);
      },90);
    });
    document.addEventListener('DOMContentLoaded',function(){
      if (document.body) observer.observe(document.body,{subtree:true,attributes:true,attributeFilter:['placeholder','title','aria-label','value']});
    });
  }
})();
