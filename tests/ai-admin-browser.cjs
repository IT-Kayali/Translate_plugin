const {chromium} = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
(async()=>{
 const html=fs.readFileSync(process.env.AI_ADMIN_HTML,'utf8');
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
 try {
  for(const width of [1440,390]) {
   const page=await browser.newPage({viewport:{width,height:1100}});
   await page.route('**/*',route=>route.fulfill({contentType:'text/html',body:html}));
   await page.goto('https://example.test/wp-admin/admin.php?page=itkt-ai');
   assert.equal(await page.locator('select[name=provider] option').count(),3);
   for(const provider of ['openai','deepl_free','deepl_pro'])await page.locator('select[name=provider]').selectOption(provider);
   await page.locator('select[name=provider]').selectOption('openai');
   assert.equal(await page.locator('form[method=post]').count(),2);
   assert.equal(await page.locator('input[name=ai_search]').count(),1);
   assert.equal(await page.locator('[name=confirm_transfer][required]').count(),1);
   assert.equal(await page.locator('[name=confirm_apply][required]').count(),1);
   assert.equal(await page.locator('input[type=password]').count(),0);
   assert.equal(await page.locator('body').innerText().then(x=>x.includes('local-test-dummy')),false);
   await page.screenshot({path:path.join(process.env.SCREENSHOT_DIR||'/downloads','translate-ai-admin-'+width+'.png'),fullPage:true});
   console.log('PASS admin browser',width,'provider options, separated forms, explicit consent, no key exposure');
   await page.close();
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
