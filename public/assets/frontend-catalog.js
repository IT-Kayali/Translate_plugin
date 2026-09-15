(function(){
  'use strict';
  if (typeof ITKTFrontendCatalog === 'undefined' || !ITKTFrontendCatalog.ajaxUrl) return;

  var seen = Object.create(null), timer = 0;
  var TEXT_SELECTOR = 'h1,h2,h3,h4,h5,h6,p,a,button,span,li,label,strong,em,small,div,legend,th,td,dt,dd';
  var CONTROL_SELECTOR = 'input[placeholder],textarea[placeholder],input[type="submit"][value],input[type="button"][value]';
  var SKIP_SELECTOR = '#wpadminbar,.itkt-inline-panel,.itkt-inline-overlay,.itkt-inline-toast,.itkt-language-switcher,script,style,noscript,svg,code,pre,textarea,select';

  function normalize(v){ return String(v == null ? '' : v).replace(/\s+/g,' ').trim(); }
  function candidate(v){
    v = normalize(v);
    if (!v || v.length < 2 || v.length > Number(ITKTFrontendCatalog.maxText || 500)) return '';
    if (/^[\d\s.,+\-–—:%€$£¥()\/]+$/u.test(v)) return '';
    if (/^(https?:|mailto:|tel:)/i.test(v)) return '';
    return v;
  }
  function dynamicValue(el){
    if (!el || !el.closest) return false;
    return !!el.closest('.price,.amount,.woocommerce-Price-amount,.quantity,.qty,.product-quantity,.product-subtotal,.cart-subtotal,.order-total,.woocommerce-order-details,.woocommerce-customer-details,[data-product_id],[data-product-price]');
  }
  function area(el){
    if (!el || !el.closest) return 'other';
    if (el.closest('[role="dialog"],[aria-modal="true"],.popup,.wd-popup,.mfp-wrap,.offcanvas,.off-canvas,[class*="offcanvas"],[class*="drawer"],.cart-widget-side,.wd-side-hidden')) return 'popup';
    if (el.closest('.woocommerce-checkout,.wc-block-checkout,.wp-block-woocommerce-checkout')) return 'checkout';
    if (el.closest('.woocommerce-cart-form,.cart-collaterals,.wc-block-cart,.wp-block-woocommerce-cart')) return 'cart';
    if (el.closest('.woocommerce-mini-cart,.widget_shopping_cart,.cart-widget-side')) return 'mini-cart';
    if (el.closest('.woocommerce-MyAccount-navigation,.woocommerce-MyAccount-content')) return 'account';
    if (el.closest('.wishlist_table,.wd-wishlist-content,[class*="wishlist"]')) return 'wishlist';
    if (el.closest('.single-product,.product.type-product,.summary.entry-summary,.woocommerce-tabs')) return 'product';
    if (el.closest('.filters-area,.wd-filter-buttons,.shop-loop-head,[class*="filter"],[id*="filter"]')) return 'filters';
    if (el.closest('.tax-product_cat,.woocommerce-products-header,.product-grid-item,.products')) return 'category';
    if (el.closest('header,.site-header,.whb-header,.wd-header')) return 'header';
    if (el.closest('footer,.site-footer,.wd-footer,.wd-prefooter')) return 'footer';
    if (el.closest('nav,.wd-nav,.mobile-nav,.menu')) return 'menu';
    if (document.body && document.body.classList.contains('search-results')) return 'search';
    if (document.body && (document.body.classList.contains('post-type-archive-product') || document.body.classList.contains('woocommerce-shop'))) return 'shop';
    return 'other';
  }
  function directText(el){
    if (!el || !el.childNodes) return [];
    var out=[];
    Array.prototype.forEach.call(el.childNodes,function(node){ if(node.nodeType===3){ var t=candidate(node.nodeValue); if(t) out.push(t); } });
    return out;
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
      var values=directText(el);
      if (el.hasAttribute && el.hasAttribute('placeholder')) values.push(candidate(el.getAttribute('placeholder')));
      if (el.matches && el.matches('input[type="submit"],input[type="button"]')) values.push(candidate(el.value));
      values.forEach(function(text){
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
        fetch(ITKTFrontendCatalog.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).catch(function(){});
      }
    });
  }
  function schedule(root){
    window.clearTimeout(timer);
    timer=window.setTimeout(function(){ send(collect(root||document)); },180);
  }

  document.addEventListener('DOMContentLoaded',function(){
    schedule(document);
    if(typeof MutationObserver!=='undefined'){
      var observer=new MutationObserver(function(mutations){
        var roots=[];
        mutations.forEach(function(m){
          Array.prototype.forEach.call(m.addedNodes||[],function(n){ if(n.nodeType===1) roots.push(n); });
          if(m.type==='characterData'&&m.target&&m.target.parentElement) roots.push(m.target.parentElement);
        });
        if(!roots.length) return;
        window.clearTimeout(timer);
        timer=window.setTimeout(function(){ roots.forEach(schedule); },100);
      });
      observer.observe(document.body,{childList:true,subtree:true,characterData:true});
    }
  });

  document.addEventListener('wc-blocks_added_to_cart',function(){schedule(document);});
  document.addEventListener('wc-blocks_removed_from_cart',function(){schedule(document);});
  if(window.jQuery){
    window.jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed updated_wc_div updated_cart_totals updated_checkout',function(){schedule(document);});
  }
})();
