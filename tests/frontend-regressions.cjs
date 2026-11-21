const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/frontend.js'), 'utf8');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

async function page(config = {}, html = '<main></main>') {
  const dom = new JSDOM(html, { url: 'https://example.test/shop/en/', runScripts: 'outside-only' });
  const w = dom.window;
  w.ITKTFrontend = Object.assign({
    currentLang: 'en', defaultLang: 'de', activeCodes: ['de', 'en', 'ar'],
    homeUrl: 'https://example.test/shop/', cookieName: 'itkt_language',
    visualTranslations: {}, globalTranslations: { exact: {}, patterns: [] }
  }, config);
  await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
  w.eval(source);
  w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
  return dom;
}

async function mutationBurst() {
  const dom = await page({ globalTranslations: { exact: { Warenkorb: 'Cart', Kasse: 'Checkout' }, patterns: [] } });
  try {
    const w = dom.window;
    const first = w.document.createElement('span'); first.textContent = 'Warenkorb';
    w.document.querySelector('main').appendChild(first);
    await sleep(20);
    const second = w.document.createElement('span'); second.textContent = 'Kasse';
    w.document.querySelector('main').appendChild(second);
    await sleep(250);
    assert.equal(first.textContent, 'Cart', 'The first AJAX fragment must survive a later debounce callback');
    assert.equal(second.textContent, 'Checkout');
  } finally { dom.window.close(); }
}

async function scopedLinks() {
  const dom = await page({}, '<main><a id="inside" href="/shop/kasse/?x=1#pay">Kasse</a><a id="sibling" href="/shopping/">Other application</a><a id="outside" href="/support/">Support</a><a id="external" href="https://elsewhere.test/shop/">External</a><a id="admin" href="/shop/wp-admin/">Admin</a><a id="switch" class="itkt-lang-link" href="/shop/ar/">Arabic</a></main>');
  try {
    const d = dom.window.document;
    assert.equal(d.querySelector('#inside').href, 'https://example.test/shop/en/kasse/?x=1#pay');
    assert.equal(d.querySelector('#sibling').href, 'https://example.test/shopping/', 'A path sharing only a textual prefix is not part of the WordPress installation');
    assert.equal(d.querySelector('#outside').href, 'https://example.test/support/');
    assert.equal(d.querySelector('#external').href, 'https://elsewhere.test/shop/');
    assert.equal(d.querySelector('#admin').href, 'https://example.test/shop/wp-admin/');
    assert.equal(d.querySelector('#switch').href, 'https://example.test/shop/ar/');
  } finally { dom.window.close(); }
}

(async () => {
  let failures = 0;
  for (const test of [mutationBurst, scopedLinks]) {
    try { await test(); console.log('PASS', test.name); }
    catch (e) { failures++; console.error('FAIL', test.name, e.message); }
  }
  process.exitCode = failures ? 1 : 0;
})();
