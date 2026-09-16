(function(){
  'use strict';
  if (typeof ITKTFrontendCatalog === 'undefined' || !ITKTFrontendCatalog.ajaxUrl) return;

  var seen = Object.create(null), timer = 0, pendingRoots = [];
  var TEXT_SELECTOR = 'h1,h2,h3,h4,h5,h6,p,a,button,span,li,label,strong,em,small,div,legend,th,td,dt,dd,option';
  var CONTROL_SELECTOR = 'input[placeholder],textarea[placeholder],input[type="submit"][value],input[type="button"][value],[aria-label],[title]';
  var SKIP_SELECTOR = '#wpadminbar,.itkt-inline-panel,.itkt-inline-overlay,.itkt-inline-toast,.itkt-language-switcher,script,style,noscript,svg,code,pre,textarea,select';

  function normalize(v){ return String(v == null ? '' : v).replace(/\s+/g,' ').trim(); }
  function candidate(v){
    v = normalize(v);
    if (!v || v.length < 2 || v.length > Number(ITKTFrontendCatalog.maxText || 500)) return '';
    if (/^[\d\s.,+\-–—:%€$£¥()\/\\]+$/u.test(v)) return '';
    if (/^(https?:|mailto:|tel:|javascript:)/i.test(v)) return '';
    return v;
  }
  function dynamicValue(el){
    if (!el || !el.closest) return false;
    return !!el.closest('.price,.amount,.woocommerce-Price-amount,.quantity,.qty,.product-quantity,.product-subtotal,.cart-subtotal,.order-total,.woocommerce-order-details,.woocommerce-customer-details,[data-product_id],[data-product-price],[data-price]');
  }
  function area(el){
    if (!el || !el.closest) return 'other';
    if (el.closest('.woocommerce-error,.woocommerce-message,.woocommerce-info,.wc-block-components-notice-banner,[role="alert"],.wd-notice,.wd-alert')) return 'notices';
    if (el.closest('.woocommerce-mini-cart,.widget_shopping_cart,.cart-widget-side,.wd-cart-content,.wd-header-cart')) return 'mini-cart';
    if (el.closest('.woocommerce-checkout,.wc-block-checkout,.wp-block-woocommerce-checkout')) return 'checkout';
    if (el.closest('.woocommerce-cart-form,.cart-collaterals,.wc-block-cart,.wp-block-woocommerce-cart')) return 'cart';
    if (el.closest('.woocommerce-MyAccount-navigation,.woocommerce-MyAccount-content,.wd-my-account')) return 'account';
    if (el.closest('.wishlist_table,.wd-wishlist-content,[class*="wishlist"]')) return 'wishlist';
    if (el.closest('.single-product,.product.type-product,.summary.entry-summary,.woocommerce-tabs')) return 'product';
    if (el.closest('.filters-area,.wd-filter-buttons,.shop-loop-head,[class*="filter"],[id*="filter"]')) return 'filters';
    if (el.closest('.tax-product_cat,.woocommerce-products-header,.product-grid-item,.products')) return 'category';
    if (el.closest('[role="dialog"],[aria-modal="true"],.popup,.wd-popup,.mfp-wrap,.offcanvas,.off-canvas,[class*="offcanvas"],[class*="drawer"],.wd-side-hidden')) return 'popup';
    if (el.closest('header,.site-header,.whb-header,.wd-header')) return 'header';
    if (el.closest('footer,.site-footer,.wd-footer,.wd-prefooter')) return 'footer';
    if (el.closest('nav,.wd-nav,.mobile-nav,.menu')) return 'menu';
    if (document.body && document.body.classList.contains('search-results')) return 'search';
    if (document.body && document.body.classList.contains('tax-product_cat')) return 'category';
    if (document.body && (document.body.classList.contains('post-type-archive-product') || document.body.classList.contains('woocommerce-shop'))) return 'shop';
    return 'other';
  }
  function directText(el){
    if (!el || !el.childNodes) return [];
    var out=[];
    Array.prototype.forEach.call(el.childNodes,function(node){ if(node.nodeType===3){ var t=candidate(node.nodeValue); if(t) out.push(t); } });
    if (el.tagName === 'OPTION') { var ot=candidate(el.textContent); if (ot) out.push(ot); }
    return out;
  }
  function valuesFor(el){
    var values=directText(el);
    if (!el || !el.getAttribute) return values;
    ['placeholder','aria-label','title'].forEach(function(attr){
      if (el.hasAttribute(attr)) { var v=candidate(el.getAttribute(attr)); if(v) values.push(v); }
    });
    if (el.matches && el.matches('input[type="submit"],input[type="button"]')) { var iv=candidate(el.value); if(iv) values.push(iv); }
    return values;
  }
  function collect(root){
    if (!root || !root.querySelectorAll) return {};
    var nodes=[];
    try {
      if (root.matches && root.matches(TEXT_SELECTOR+','+CONTROL_SELECTOR)) nodes.push(root);
      nodes=nodes.concat(Array.prototype.slice.call(root.querySelectorAll(TEXT_SELECTOR+','+CONTROL_SELECTOR)));
    } catch(e){}
    var groups={};
    nodes.forEach(function(el){
      if (!el || !el.closest || el.closest(SKIP_SELECTOR) || dynamicValue(el)) return;
      valuesFor(el).forEach(function(text){
        if(!text) return;
        var a=area(el), key=a+'\0'+text;
        if(seen[key]) return;
        seen[key]=1;
        if(!groups[a]) groups[a]=[];
        groups[a].push({text:text});
      });
    });
    return groups;
  }
  function send(groups){
    Object.keys(groups||{}).forEach(function(a){
      var rows=groups[a]||[];
      for(var i=0;i<rows.length;i+=100){
        var body=new URLSearchParams();
        body.set('action',ITKTFrontendCatalog.action||'itkt_capture_frontend_strings');
        body.set('nonce',ITKTFrontendCatalog.nonce||'');
        body.set('area',a);
        body.set('url',window.location.href);
        body.set('items',JSON.stringify(rows.slice(i,i+100)));
        fetch(ITKTFrontendCatalog.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString(),cache:'no-store'}).catch(function(){});
      }
    });
  }
  function flush(){
    var roots=pendingRoots.length?pendingRoots.splice(0):[document], merged={};
    roots.forEach(function(root){
      var groups=collect(root);
      Object.keys(groups).forEach(function(a){ if(!merged[a]) merged[a]=[]; merged[a]=merged[a].concat(groups[a]); });
    });
    send(merged);
  }
  function schedule(root){
    if(root) pendingRoots.push(root);
    window.clearTimeout(timer);
    timer=window.setTimeout(flush,160);
  }

  document.addEventListener('DOMContentLoaded',function(){
    schedule(document);
    if(typeof MutationObserver!=='undefined'){
      var observer=new MutationObserver(function(mutations){
        mutations.forEach(function(m){
          Array.prototype.forEach.call(m.addedNodes||[],function(n){ if(n.nodeType===1) schedule(n); });
          if(m.type==='characterData'&&m.target&&m.target.parentElement) schedule(m.target.parentElement);
          if(m.type==='attributes'&&m.target&&m.target.nodeType===1) schedule(m.target);
        });
      });
      observer.observe(document.body,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:['placeholder','aria-label','title','value']});
    }
  });

  ['wc-blocks_added_to_cart','wc-blocks_removed_from_cart','wc-blocks_updated_cart','wc-blocks_updated_checkout'].forEach(function(name){
    document.addEventListener(name,function(){schedule(document);});
  });
  if(window.jQuery){
    window.jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed updated_wc_div updated_cart_totals updated_checkout checkout_error applied_coupon removed_coupon updated_shipping_method',function(){schedule(document);});
  }
})();
