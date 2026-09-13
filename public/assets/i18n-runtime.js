(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof window.ITKTI18nRuntime === 'undefined') return;
  if (!window.wp || !wp.hooks || !wp.hooks.addFilter) return;

  var cfg = window.ITKTI18nRuntime || {};

  function gettextMap() { return (cfg && cfg.gettext) || {}; }
  function contextMap() { return (cfg && cfg.gettextContext) || {}; }

  function overrideGettext(translation, text, domain) {
    domain = domain || 'default';
    var domains = gettextMap();
    if (domains[domain] && Object.prototype.hasOwnProperty.call(domains[domain], text)) {
      return domains[domain][text];
    }
    return translation;
  }

  function overrideGettextContext(translation, text, context, domain) {
    domain = domain || 'default'; context = context || '';
    var domains = contextMap();
    if (domains[domain] && domains[domain][context] && Object.prototype.hasOwnProperty.call(domains[domain][context], text)) {
      return domains[domain][context][text];
    }
    return translation;
  }

  // These are the official @wordpress/i18n runtime filters. Register early so WooCommerce
  // Cart/Checkout React components receive the override before sprintf/createInterpolateElement.
  wp.hooks.addFilter('i18n.gettext', 'itkt/runtime-gettext', overrideGettext, 100);
  wp.hooks.addFilter('i18n.gettext_with_context', 'itkt/runtime-gettext-context', overrideGettextContext, 100);

  // Allow the normal frontend runtime refresh to replace the map after an inline-editor save.
  window.ITKTI18nRuntimeUpdate = function (next) {
    if (!next || typeof next !== 'object') return;
    cfg.gettext = next.gettext || {};
    cfg.gettextContext = next.gettextContext || {};
  };
})();
