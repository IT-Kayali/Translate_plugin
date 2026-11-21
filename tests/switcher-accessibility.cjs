const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');
const source = fs.readFileSync(process.env.FRONTEND_SOURCE || path.join(__dirname, '../public/assets/frontend.js'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '../public/assets/frontend.css'), 'utf8');
const fixture = fs.readFileSync(path.join(__dirname, 'fixtures/language-switcher.html'), 'utf8');

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox'] });
  try {
    for (const width of [1440, 768, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      await page.route('**/*', route => route.fulfill({ contentType: 'text/html', body: fixture }));
      await page.goto('https://example.test/');
      await page.addStyleTag({ content: css });
      await page.evaluate(() => { window.ITKTFrontend = { currentLang: 'de', defaultLang: 'de', activeCodes: ['de', 'en', 'ar'], homeUrl: 'https://example.test/', cookieName: 'itkt_language', visualTranslations: {}, globalTranslations: { exact: {}, patterns: [] } }; });
      await page.addScriptTag({ content: source });
      await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
      const trigger = page.locator('.itkt-switcher-trigger');
      const items = page.locator('.itkt-menu-link');
      const expanded = () => trigger.getAttribute('aria-expanded');
      const focusedCode = () => page.evaluate(() => document.activeElement.getAttribute('data-itkt-lang'));
      await trigger.focus();
      await page.keyboard.press('ArrowDown');
      assert.equal(await expanded(), 'true');
      assert.equal(await focusedCode(), 'en');
      await page.keyboard.press('ArrowDown'); assert.equal(await focusedCode(), 'ar');
      await page.keyboard.press('ArrowDown'); assert.equal(await focusedCode(), 'en');
      await page.keyboard.press('ArrowUp'); assert.equal(await focusedCode(), 'ar');
      await page.keyboard.press('Home'); assert.equal(await focusedCode(), 'en');
      await page.keyboard.press('End'); assert.equal(await focusedCode(), 'ar');
      await page.keyboard.press('Escape');
      assert.equal(await expanded(), 'false');
      assert.equal(await trigger.evaluate(el => el === document.activeElement), true);
      assert.equal(await page.locator('.itkt-switcher-menu').getAttribute('aria-hidden'), 'true');
      for (const key of ['Enter', 'Space']) {
        await page.keyboard.press(key); assert.equal(await focusedCode(), 'en');
        await page.keyboard.press('Escape'); assert.equal(await expanded(), 'false');
      }
      await page.keyboard.press('ArrowUp'); assert.equal(await focusedCode(), 'ar');
      await page.keyboard.press('Tab');
      assert.equal(await expanded(), 'false');
      assert.equal(await page.locator('#after-switcher').evaluate(el => el === document.activeElement), true);
      await trigger.focus(); await page.keyboard.press('ArrowDown');
      await page.keyboard.press('Shift+Tab');
      assert.equal(await expanded(), 'false');
      assert.equal(await trigger.evaluate(el => el === document.activeElement), true);
      await trigger.click(); assert.equal(await expanded(), 'true');
      await page.locator('h1').click(); assert.equal(await expanded(), 'false');
      await trigger.focus(); await page.keyboard.press('ArrowDown');
      await page.locator('#after-switcher').focus(); assert.equal(await expanded(), 'false');
      await trigger.focus(); await page.keyboard.press('ArrowDown');
      await page.waitForTimeout(50);
      const bounds = await page.locator('.itkt-switcher-menu').boundingBox();
      assert(bounds.x >= 9 && bounds.x + bounds.width <= width - 9, 'Menu stays within viewport safety margins');
      assert.equal(await items.count(), 2);
      assert.deepEqual(errors, []);
      if (process.env.SCREENSHOT_DIR) await page.screenshot({ path: path.join(process.env.SCREENSHOT_DIR, 'translate-switcher-' + width + '.png'), fullPage: true });
      console.log('PASS switcher', width + 'px', 'keyboard open/cycle/Home/End/Escape/Tab, click, focusout, viewport, no page errors');
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(e => { console.error(e.stack); process.exitCode = 1; });
