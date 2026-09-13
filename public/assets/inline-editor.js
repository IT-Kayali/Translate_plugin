(function () {
  'use strict';
  if (typeof ITKTInline === 'undefined') return;

  var active = false;
  var context = ITKTInline.context || { type: 'none', targets: [] };
  var panel, overlay, toast;
  var marked = [];
  var observerTimer = null;
  var lastAuditCount = -1;

  function q(sel, root) { try { return (root || document).querySelector(sel); } catch (e) { return null; } }
  function qa(sel, root) { try { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); } catch (e) { return []; } }
  function normalizeText(v) {
    var box = document.createElement('div'); box.innerHTML = v || '';
    return (box.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
  }
  function escapeHtml(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]; }); }
  function domDepth(el) { var d=0; while(el && el.parentElement){ d++; el=el.parentElement; } return d; }

  function storageKey() { return 'itkt_inline_mode'; }
  function currentStoredMode() { try { return sessionStorage.getItem(storageKey()) === '1'; } catch(e) { return false; } }
  function storeMode(on) { try { sessionStorage.setItem(storageKey(), on ? '1' : '0'); } catch(e) {} }

  function ensureUi() {
    if (panel) return;
    overlay = document.createElement('div'); overlay.className = 'itkt-inline-overlay'; overlay.addEventListener('click', closePanel);
    panel = document.createElement('aside'); panel.className = 'itkt-inline-panel'; panel.setAttribute('aria-hidden', 'true');
    panel.innerHTML = '<div class="itkt-inline-panel-head"><div><span class="itkt-inline-kicker">IT-KAYALI TRANSLATE</span><h2>Frontend Übersetzung</h2></div><button type="button" class="itkt-inline-close" aria-label="Schließen">×</button></div><div class="itkt-inline-panel-body"></div>';
    panel.querySelector('.itkt-inline-close').addEventListener('click', closePanel);
    toast = document.createElement('div'); toast.className = 'itkt-inline-toast';
    document.body.appendChild(overlay); document.body.appendChild(panel); document.body.appendChild(toast);
  }

  function showToast(text, error) {
    ensureUi(); toast.textContent = text; toast.classList.toggle('is-error', !!error); toast.classList.add('is-visible');
    window.setTimeout(function(){ toast.classList.remove('is-visible'); }, 2600);
  }

  function setAdminBarState() {
    var node = q('#wp-admin-bar-itkt-inline-translate');
    if (!node) return;
    node.classList.toggle('itkt-is-active', active);
    var label = q('.ab-label', node); if (label) label.textContent = active ? ITKTInline.strings.active : ITKTInline.strings.toggle;
  }

  function clearMarks() {
    marked.forEach(function(el){
      if(el._itktInlineClick){ el.removeEventListener('click',el._itktInlineClick,true); delete el._itktInlineClick; }
      el.classList.remove('itkt-inline-editable');
      el.removeAttribute('data-itkt-inline-key');
      el.removeAttribute('data-itkt-inline-label');
      el.removeAttribute('data-itkt-inline-type');
    });
    marked = [];
    qa('.itkt-inline-edit-badge').forEach(function(b){ b.remove(); });
  }

  function bestTextElement(widget, source) {
    var needle = normalizeText(source);
    if (!needle || !widget) return widget;
    var candidates = qa('h1,h2,h3,h4,h5,h6,p,a,button,span,li,label,blockquote,figcaption', widget);
    var exact = candidates.filter(function(el){ return normalizeText(el.innerHTML) === needle; });
    if (exact.length) return exact.sort(function(a,b){ return a.textContent.length - b.textContent.length; })[0];
    var contained = candidates.filter(function(el){ var t = normalizeText(el.innerHTML); return t && (t.indexOf(needle) !== -1 || needle.indexOf(t) !== -1); });
    if (contained.length) return contained.sort(function(a,b){ return a.textContent.length - b.textContent.length; })[0];
    return widget;
  }

  function markElement(el, target, label) {
    if (!el || marked.indexOf(el) !== -1) return;
    el.classList.add('itkt-inline-editable');
    el.setAttribute('data-itkt-inline-key', target.key);
    el.setAttribute('data-itkt-inline-type', target.type || context.type || 'post');
    el.setAttribute('data-itkt-inline-label', label || target.label || 'Text');
    var canContainBadge = !/^(INPUT|TEXTAREA|SELECT|OPTION)$/.test(el.tagName || '');
    if (canContainBadge) {
      var badge = document.createElement('span'); badge.className = 'itkt-inline-edit-badge'; badge.textContent = '✎ Übersetzen';
      badge.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); openTarget(target); });
      el.appendChild(badge);
    }
    el._itktInlineClick = function(e){
      if (!active || (e.target.closest && e.target.closest('.itkt-inline-edit-badge'))) return;
      e.preventDefault(); e.stopPropagation(); openTarget(target);
    };
    el.addEventListener('click', el._itktInlineClick, true);
    marked.push(el);
  }

  function applyStructuredMarks() {
    if (!context.targets || !context.targets.length) return;
    context.targets.forEach(function(target){
      if (target.kind === 'elementor' || target.kind === 'visual_group') {
        var widget = q(target.selector); if (!widget) return;
        var seen = [];
        (target.segments || []).forEach(function(seg){
          var el = bestTextElement(widget, seg.source);
          if (el && seen.indexOf(el) === -1) { markElement(el, target, seg.label); seen.push(el); }
        });
        if (!seen.length) markElement(widget, target, target.label);
      } else {
        qa(target.selector).forEach(function(el){ markElement(el, target, target.label); });
      }
    });
  }

  function visualLabel(scope) {
    return ({
      header:'Header-Text',
      footer:'Footer-/Widget-Text',
      menu:'Menütext',
      cart:'Warenkorb-/Mini-Cart-Text',
      checkout:'Checkout-Text',
      account:'Mein-Konto-Text',
      woocommerce:'WooCommerce-Text',
      filter:'Filter-/Plugin-Text',
      widget:'Widget-Text',
      global:'Frontend-Text'
    })[scope] || 'Frontend-Text';
  }


  function visualTranslationMode(scope, original) {
    var reusable = ['menu','cart','checkout','account','woocommerce','filter'];
    if (reusable.indexOf(scope || '') === -1) return { mode:'local', source:original || '' };
    var source = original || '';
    if (window.ITKTVisualTools && typeof window.ITKTVisualTools.template === 'function') {
      var tpl = window.ITKTVisualTools.template(source);
      if (tpl && tpl !== source) return { mode:'pattern', source:tpl };
    }
    return { mode:'global', source:source };
  }

  function applyVisualMarks() {
    if (!window.ITKTVisualTools || typeof window.ITKTVisualTools.collect !== 'function') return;
    var items = window.ITKTVisualTools.collect(document);
    var visualGroups = [], regularItems = [];

    items.forEach(function(item){
      if ((item.kind === 'option' || item.kind === 'fragment') && item.control) {
        var group = visualGroups.filter(function(g){ return g.control === item.control && g.kind === item.kind; })[0];
        if (!group) {
          group = { control:item.control, items:[], kind:item.kind, scope:item.scope || 'global', locator:(window.ITKTVisualTools.locator(item.control) || String(item.locator || '').split('|text-node:')[0] || '') };
          visualGroups.push(group);
        }
        group.items.push(item);
      } else {
        regularItems.push(item);
      }
    });

    function buildGroupTarget(group) {
      var isFragment = group.kind === 'fragment';
      var groupKey = window.ITKTVisualTools.hash((group.locator || '') + (isFragment ? '|text-fragments' : '|select-options'));
      return {
        type: 'visual_group',
        kind: 'visual_group',
        key: groupKey,
        label: isFragment ? visualLabel(group.scope) : 'Auswahltexte',
        scope: group.scope,
        locator: group.locator || '',
        mode: isFragment ? 'local' : 'global',
        items: group.items.map(function(item, idx){
          var modeInfo = visualTranslationMode(item.scope || group.scope, item.original || '');
          if (isFragment) modeInfo = {mode:'local', source:item.original || ''};
          return {
            key: modeInfo.mode === 'local' ? item.fingerprint : window.ITKTVisualTools.hash('global|' + modeInfo.mode + '|' + modeInfo.source),
            original:modeInfo.source,
            actualOriginal:item.original,
            mode:modeInfo.mode,
            scope:item.scope || group.scope,
            locator:item.locator || '',
            group_kind:isFragment ? 'fragment' : 'option',
            label:isFragment ? ('Textteil ' + (idx + 1) + ': ' + item.original) : ('Option: ' + item.original)
          };
        })
      };
    }

    // Compound Checkout Block system text must be marked before its nested AGB/privacy links.
    // Otherwise the deepest-first rule would make only the link labels clickable and the complete
    // legal sentence could never be edited/reordered as one protected gettext template.
    regularItems.filter(function(item){ return item && item.kind === 'compound_system'; })
      .forEach(function(item){
        var el = item.el;
        if (!el || el.classList.contains('itkt-inline-editable') || (el.closest && el.closest('.itkt-inline-editable'))) return;
        var modeInfo = { mode:'global', source:item.original || '' };
        var target = {
          type:'visual', kind:'visual_compound_system',
          key:window.ITKTVisualTools.hash('global|' + modeInfo.mode + '|' + modeInfo.source),
          label:'Checkout-Rechtstext', original:modeInfo.source,
          actualOriginal:item.actualOriginal || item.original || '', mode:modeInfo.mode,
          scope:item.scope || 'checkout', locator:item.locator || ''
        };
        markElement(el, target, 'Checkout-Rechtstext');
      });

    // Composite text blocks (footer, My Account, checkout, content, ...) are grouped so text
    // around nested <a>/<strong>/<span> tags is editable without destroying markup.
    visualGroups.filter(function(g){ return g.kind === 'fragment'; })
      .sort(function(a,b){ return domDepth(b.control) - domDepth(a.control); })
      .forEach(function(group){
        var el = group.control;
        if (!el || el.classList.contains('itkt-inline-editable') || (el.closest && el.closest('.itkt-inline-editable'))) return;
        markElement(el, buildGroupTarget(group), 'Gemeinsamer Textblock');
      });

    // For normal targets prefer the deepest/most specific leaf.
    regularItems.filter(function(item){ return !item || item.kind !== 'compound_system'; })
      .sort(function(a,b){ return domDepth((b && b.el) || null) - domDepth((a && a.el) || null); })
      .forEach(function(item){
      var el = item.el;
      if (!el || el.classList.contains('itkt-inline-editable') || (el.closest && el.closest('.itkt-inline-editable'))) return;
      var modeInfo = visualTranslationMode(item.scope || 'global', item.original || '');
      var targetKey = modeInfo.mode === 'local' ? item.fingerprint : window.ITKTVisualTools.hash('global|' + modeInfo.mode + '|' + modeInfo.source);
      var target = {
        type: 'visual',
        kind: item.kind === 'attribute' ? 'visual_attribute' : 'visual',
        key: targetKey,
        label: visualLabel(item.scope),
        original: modeInfo.source,
        actualOriginal: item.original,
        mode: modeInfo.mode,
        scope: item.scope || 'global',
        locator: item.locator || '',
        attr: item.attr || ''
      };
      markElement(el, target, item.kind === 'attribute' ? 'Eingabefeld / Platzhalter' : target.label);
    });

    // Select-option groups are marked after regular text to avoid changing unrelated wrappers.
    visualGroups.filter(function(g){ return g.kind === 'option'; })
      .sort(function(a,b){ return domDepth(b.control) - domDepth(a.control); })
      .forEach(function(group){
        var el = group.control;
        if (!el || el.classList.contains('itkt-inline-editable') || (el.closest && el.closest('.itkt-inline-editable'))) return;
        markElement(el, buildGroupTarget(group), 'Auswahltexte');
      });
  }

  function applyMarks(showAudit) {
    clearMarks();
    if (!active) return;
    applyStructuredMarks();
    applyVisualMarks();
    if (showAudit) {
      if (!marked.length) showToast(ITKTInline.strings.noFields, false);
      else if (lastAuditCount !== marked.length) {
        lastAuditCount = marked.length;
        showToast('Automatische Seitenprüfung: ' + marked.length + ' übersetzbare Bereiche erkannt.', false);
      }
    }
  }

  function toggleMode(force) {
    active = typeof force === 'boolean' ? force : !active;
    storeMode(active); document.body.classList.toggle('itkt-inline-mode', active); setAdminBarState();
    if (active) { ensureUi(); applyMarks(true); }
    else { clearMarks(); closePanel(); }
  }

  function postAjax(action, data) {
    var body = new URLSearchParams(); body.append('action', action); body.append('nonce', ITKTInline.nonce);
    Object.keys(data || {}).forEach(function(k){ body.append(k, data[k]); });
    return fetch(ITKTInline.ajaxUrl, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:body.toString() })
      .then(function(r){ return r.json(); });
  }

  function ajaxTargetData(target) {
    var isVisual = (target.type === 'visual' || target.type === 'visual_group');
    return {
      type: isVisual ? target.type : context.type,
      source_id: isVisual ? 0 : context.sourceId,
      key: target.key,
      original: target.type === 'visual' ? (target.original || '') : '',
      actual_original: target.type === 'visual' ? (target.actualOriginal || target.original || '') : '',
      scope: isVisual ? (target.scope || 'global') : '',
      locator: isVisual ? (target.locator || '') : '',
      mode: isVisual ? (target.mode || 'local') : '',
      native_string_id: isVisual ? (target.nativeStringId || 0) : 0,
      legacy_visual_id: isVisual ? (target.legacyVisualId || 0) : 0,
      items: target.type === 'visual_group' ? JSON.stringify(target.items || []) : ''
    };
  }

  function openTarget(target) {
    ensureUi();
    panel.classList.add('is-open'); overlay.classList.add('is-open'); panel.setAttribute('aria-hidden','false');
    q('.itkt-inline-panel-body', panel).innerHTML = '<div class="itkt-inline-loading">' + escapeHtml(ITKTInline.strings.loading) + '</div>';
    postAjax('itkt_inline_load', ajaxTargetData(target)).then(function(res){
      if (!res || !res.success) throw new Error((res && res.data && res.data.message) || ITKTInline.strings.error);
      renderForm(target, res.data || {});
    }).catch(function(err){ q('.itkt-inline-panel-body', panel).innerHTML = '<div class="itkt-inline-error">' + escapeHtml(err.message || ITKTInline.strings.error) + '</div>'; });
  }

  function renderForm(target, data) {
    var fields = data.fields || [];
    var langs = ITKTInline.languages || [];
    var def = ITKTInline.defaultLang;
    var currentLabel = data.contextLabel || context.label || target.label || 'Frontend';
    var isVisualTarget = (target.type === 'visual' || target.type === 'visual_group');
    target.nativeStringId = parseInt(data.nativeStringId || 0, 10) || 0;
    target.legacyVisualId = parseInt(data.legacyVisualId || 0, 10) || 0;
    target.nativeDomain = data.nativeDomain || target.nativeDomain || '';
    var html = '<div class="itkt-inline-current"><span>' + (isVisualTarget ? 'Sichtbarer Frontend-Bereich' : 'Aktuelle Seite') + '</span><strong>' + escapeHtml(currentLabel) + '</strong></div>';
    if (target.nativeStringId) {
      var standardName = target.nativeDomain === 'woodmart' ? 'WoodMart' : (target.nativeDomain === 'woocommerce' ? 'WooCommerce' : 'System');
      html += '<div class="itkt-inline-visual-note"><strong>' + escapeHtml(standardName) + ' Standardübersetzung:</strong> Die Felder werden aus den installierten Sprachpaketen vorbelegt. Deine Eingabe ist eine Anpassung und hat Vorrang. Feld leeren = wieder ' + escapeHtml(standardName) + '-Standard verwenden.</div>';
    }
    if (isVisualTarget) {
      var mode = target.mode || ((target.type === 'visual_group') ? 'global' : 'local');
      if (mode === 'global') html += '<div class="itkt-inline-visual-note"><strong>Globaler Systemtext:</strong> Einmal übersetzen – die Übersetzung gilt überall, wo dieser Text erscheint. Funktionen, Links und Design bleiben unverändert.</div>';
      else if (mode === 'pattern') html += '<div class="itkt-inline-visual-note"><strong>Globaler variabler Systemtext:</strong> Werte wie Preise bleiben als <code>{{1}}</code> geschützt. Die Variable muss in jeder Übersetzung erhalten bleiben.</div>';
      else html += '<div class="itkt-inline-visual-note"><strong>Lokaler Text:</strong> Diese Übersetzung gilt nur an dieser Stelle. Funktionen, Links und Design bleiben unverändert.</div>';
    }
    if (!fields.length) { html += '<div class="itkt-inline-empty">' + escapeHtml(ITKTInline.strings.noFields) + '</div>'; q('.itkt-inline-panel-body',panel).innerHTML=html; return; }
    html += '<form class="itkt-inline-form"><input type="hidden" name="targetKey" value="' + escapeHtml(target.key) + '">';
    fields.forEach(function(field, fi){
      html += '<section class="itkt-inline-field" data-field="' + escapeHtml(field.key) + '"><h3>' + escapeHtml(field.label || data.label || 'Text') + '</h3>';
      langs.forEach(function(lang){
        var value = (field.values && field.values[lang.code]) || '';
        var nativeDefault = (field.defaults && field.defaults[lang.code]) || '';
        var isDefault = lang.code === def;
        html += '<label class="itkt-inline-language ' + (isDefault?'is-source':'') + '"><span><b>' + escapeHtml(lang.flag + ' ' + lang.name) + '</b>' + (isDefault?'<em>Original · schreibgeschützt</em>':'') + '</span>';
        var placeholder = (!isDefault && nativeDefault) ? ' placeholder="' + escapeHtml(nativeDefault) + '"' : '';
        if (field.rich) html += '<textarea name="v_' + fi + '_' + escapeHtml(lang.code) + '" data-lang="' + escapeHtml(lang.code) + '" data-field-key="' + escapeHtml(field.key) + '" ' + (isDefault?'readonly':'') + ' dir="' + escapeHtml(lang.direction || 'ltr') + '"' + placeholder + '>' + escapeHtml(value) + '</textarea>';
        else html += '<input type="text" name="v_' + fi + '_' + escapeHtml(lang.code) + '" data-lang="' + escapeHtml(lang.code) + '" data-field-key="' + escapeHtml(field.key) + '" value="' + escapeHtml(value) + '" ' + (isDefault?'readonly':'') + ' dir="' + escapeHtml(lang.direction || 'ltr') + '"' + placeholder + '>';
        if (!isDefault && nativeDefault) html += '<small class="itkt-inline-native-default">' + escapeHtml(field.defaultLabel || 'WooCommerce-Standard') + ': ' + escapeHtml(nativeDefault) + '</small>';
        html += '</label>';
      });
      html += '</section>';
    });
    html += '<div class="itkt-inline-actions"><button type="button" class="itkt-inline-secondary" data-action="close">' + escapeHtml(ITKTInline.strings.close) + '</button><button type="submit" class="itkt-inline-save">' + escapeHtml(ITKTInline.strings.save) + '</button></div></form>';
    var body = q('.itkt-inline-panel-body',panel); body.innerHTML = html;
    q('[data-action="close"]',body).addEventListener('click',closePanel);
    q('.itkt-inline-form',body).addEventListener('submit',function(e){ e.preventDefault(); saveForm(target, fields, e.currentTarget); });
  }

  function saveForm(target, fields, form) {
    var button = q('.itkt-inline-save',form); button.disabled=true; button.textContent=ITKTInline.strings.saving;
    var def = ITKTInline.defaultLang;
    var payload = {};
    var multiFieldNative = !!target.nativeStringId && fields.length > 1;
    if (target.kind === 'elementor' || target.kind === 'visual_group' || multiFieldNative) {
      (ITKTInline.languages || []).forEach(function(lang){
        if (lang.code === def) return;
        payload[lang.code] = {};
        qa('[data-lang="' + lang.code + '"]',form).forEach(function(input){ payload[lang.code][input.getAttribute('data-field-key')] = input.value; });
      });
    } else {
      (ITKTInline.languages || []).forEach(function(lang){
        if (lang.code === def) return;
        var input = q('[data-lang="' + lang.code + '"]',form); if (input) payload[lang.code] = input.value;
      });
    }
    var req = ajaxTargetData(target); req.values = JSON.stringify(payload);
    postAjax('itkt_inline_save', req).then(function(res){
      if (!res || !res.success) throw new Error((res && res.data && res.data.message) || ITKTInline.strings.error);
      button.textContent=ITKTInline.strings.saved; showToast(ITKTInline.strings.saved,false);
      // Global/system strings should become visible immediately on the current language without
      // relying on a full-page cache purge. The frontend runtime fetches the newest published map.
      if (window.ITKTVisualTools && typeof window.ITKTVisualTools.refresh === 'function') { window.ITKTVisualTools.refresh(); }
      var actions=q('.itkt-inline-actions',form); if(actions && !q('.itkt-inline-reload',actions)) { var r=document.createElement('button'); r.type='button'; r.className='itkt-inline-secondary itkt-inline-reload'; r.textContent=ITKTInline.strings.reload; r.addEventListener('click',function(){ window.location.reload(); }); actions.insertBefore(r,button); }
      window.setTimeout(function(){ button.disabled=false; button.textContent=ITKTInline.strings.save; },1000);
    }).catch(function(err){ button.disabled=false; button.textContent=ITKTInline.strings.save; showToast(err.message || ITKTInline.strings.error,true); });
  }

  function closePanel(){ if(panel){panel.classList.remove('is-open'); panel.setAttribute('aria-hidden','true');} if(overlay)overlay.classList.remove('is-open'); }

  function setupDynamicObserver(){
    if (typeof MutationObserver === 'undefined') return;
    var observer = new MutationObserver(function(mutations){
      if (!active) return;
      var meaningful = mutations.some(function(m){
        return Array.prototype.some.call(m.addedNodes || [], function(n){
          return n.nodeType === 1 && !(n.classList && (n.classList.contains('itkt-inline-edit-badge') || n.classList.contains('itkt-inline-panel') || n.classList.contains('itkt-inline-overlay')));
        });
      });
      if (!meaningful) return;
      window.clearTimeout(observerTimer);
      observerTimer = window.setTimeout(function(){ if(active) applyMarks(false); }, 220);
    });
    observer.observe(document.body, { childList:true, subtree:true });
  }

  document.addEventListener('DOMContentLoaded', function(){
    ensureUi(); active=currentStoredMode(); document.body.classList.toggle('itkt-inline-mode',active); setAdminBarState(); if(active)applyMarks(true);
    setupDynamicObserver();
    document.addEventListener('click',function(e){ var toggle=e.target.closest && e.target.closest('#wp-admin-bar-itkt-inline-translate > a, #wp-admin-bar-itkt-inline-translate'); if(toggle){e.preventDefault();e.stopPropagation();toggleMode();}},true);
    document.addEventListener('keydown',function(e){ if(e.key==='Escape')closePanel(); });
  });
})();
