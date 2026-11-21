const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/frontend.js'), 'utf8');

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
    headless: true,
    args: ['--no-sandbox']
  });
  try {
    for (const language of ['en', 'ar']) {
      for (const width of [1440, 390]) {
        const page = await browser.newPage({ viewport: { width, height: 850 } });
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.route('**/*', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html><html><head><meta charset="utf-8"><title>ITKT Regressionstest</title><style>body{font:18px system-ui;padding:24px;background:#f5f7fa;color:#172335}main{max-width:900px}section{background:white;padding:20px;margin:20px 0;border:1px solid #d9e2ee;border-radius:12px}a{display:block;margin:12px 0}h1{font-size:27px}small{display:block;color:#40536d}</style></head><body><main><h1>IT-Kayali Translate 0.12.40</h1><p>Lokaler Browser-Regressionscheck, keine WordPress-Staging-Abnahme.</p><section id="fragments"><h2>AJAX-Fragmente</h2></section><section><h2>Links bei Installation unter /shop/</h2><a id="inside" href="/shop/kasse/?x=1#pay">Shop: Kasse</a><a id="sibling" href="/shopping/">Andere Anwendung: /shopping/</a><a id="outside" href="/support/">Außerhalb des Shops: /support/</a><small id="result">Test läuft</small></section></main></body></html>' }));
        await page.goto('https://example.test/shop/' + language + '/');
        await page.evaluate(lang => {
          window.ITKTFrontend = {
            currentLang: lang, defaultLang: 'de', activeCodes: ['de', 'en', 'ar'],
            homeUrl: 'https://example.test/shop/', cookieName: 'itkt_language', visualTranslations: {},
            globalTranslations: { exact: { Warenkorb: lang === 'ar' ? 'السلة' : 'Cart', Kasse: lang === 'ar' ? 'الدفع' : 'Checkout' }, patterns: [] }
          };
        }, language);
        await page.addScriptTag({ content: source });
        await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
        await page.evaluate(() => {
          const span = document.createElement('p'); span.id = 'first'; span.textContent = 'Warenkorb';
          document.querySelector('#fragments').appendChild(span);
        });
        await page.waitForTimeout(20);
        await page.evaluate(() => {
          const span = document.createElement('p'); span.id = 'second'; span.textContent = 'Kasse';
          document.querySelector('#fragments').appendChild(span);
        });
        await page.waitForFunction(() => document.querySelector('#first').textContent !== 'Warenkorb' && document.querySelector('#second').textContent !== 'Kasse');
        assert.equal(await page.locator('#first').textContent(), language === 'ar' ? 'السلة' : 'Cart');
        assert.equal(await page.locator('#second').textContent(), language === 'ar' ? 'الدفع' : 'Checkout');
        assert.equal(await page.locator('#inside').getAttribute('href'), 'https://example.test/shop/' + language + '/kasse/?x=1#pay');
        assert.equal(await page.locator('#sibling').getAttribute('href'), '/shopping/');
        assert.equal(await page.locator('#outside').getAttribute('href'), '/support/');
        assert.deepEqual(errors, []);
        await page.locator('#result').evaluate(el => { el.textContent = 'BESTANDEN: beide Fragmente übersetzt, fremde Pfade unverändert.'; });
        if (process.env.TEST_SCREENSHOT && language === 'en' && width === 1440) await page.screenshot({ path: process.env.TEST_SCREENSHOT, fullPage: true });
        console.log('PASS Chrome', language, width + 'px', 'AJAX fragments, scoped links, no page errors');
        await page.close();
      }
    }
  } finally { await browser.close(); }
})().catch(e => { console.error(e.message); process.exitCode = 1; });
