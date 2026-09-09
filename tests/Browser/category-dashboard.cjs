const {createRequire}=require('node:module');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const {chromium}=createRequire(path.join(process.env.VIDEO_BROWSER_MODULES,'package.json'))('playwright');

(async()=>{
 const browser=await chromium.launch({headless:true});
 const context=await browser.newContext({viewport:{width:1915,height:1128}});
 const page=await context.newPage();
 const errors=[];page.on('pageerror',error=>errors.push(error.message));page.setDefaultTimeout(20000);
 const base='http://127.0.0.1:8000';const artifacts=process.env.VIDEO_ARTIFACTS;fs.mkdirSync(artifacts,{recursive:true});
 try{
  await page.goto(base+'/login');
  await page.locator('#login-email').fill(process.env.ADMIN_EMAIL);
  await page.locator('#login-password').fill(process.env.ADMIN_PASSWORD);
  await Promise.all([page.waitForURL(url=>!url.pathname.endsWith('/login')),page.locator('.auth-login form[action$="/login"] button[type=submit]').click()]);
  await page.goto(base+'/admin/resource/categories');
  await page.locator('[data-category-dashboard]').waitFor();
  for(const label of ['Categories','Category Tree','Category Details','Category Summary','Quick Actions','Category Visibility','UUID Traceability','Intuitive Hierarchy'])await page.getByText(label,{exact:false}).first().waitFor();
  assert.ok(await page.locator('[data-category-row]').count()>0,'Seeded categories are rendered from the real catalog');
  await page.locator('[data-create]').first().click();await page.locator('#cg-create[open]').waitFor();
  await page.locator('#cg-create [name=name]').fill('Browser Category');
  await page.locator('#cg-create [name=slug]').fill('browser-category');
  await page.locator('#cg-create [name=status]').selectOption('active');
  await page.locator('#cg-create [name=is_visible]').selectOption('1');
  await page.locator('#cg-create button:not([type=button])').click();
  await page.waitForURL(/selected=/);await page.getByText('Browser Category',{exact:true}).first().waitFor();
  await page.locator('[data-audit]').click();await page.locator('#cg-audit[open]').waitFor();
  const audit=page.locator('[data-audit-output]');await audit.filter({hasText:/category\.created/}).waitFor();assert.match(await audit.innerText(),/^UUID: [0-9a-f-]+/i);
  await page.locator('#cg-audit [data-close]').click();
  await page.locator('[data-import]').click();await page.locator('#cg-import[open]').waitFor();assert.ok(await page.locator('#cg-import input[type=file]').isVisible());await page.locator('#cg-import [data-close]').first().click();
  await page.locator('[data-guide]').click();await page.locator('#cg-guide[open]').waitFor();await page.getByText('Circular hierarchy is blocked',{exact:false}).waitFor();await page.locator('#cg-guide [data-close]').click();
  await page.screenshot({path:path.join(artifacts,'categories-dashboard-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:844});await page.screenshot({path:path.join(artifacts,'categories-dashboard-mobile.png'),fullPage:true});
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1);assert.equal(overflow,false,'Categories dashboard must not overflow the mobile viewport');
  assert.deepEqual(errors,[],'No category dashboard JavaScript errors');
  console.log('Category browser checks passed: mockup sections, live catalog tree, create workflow, audit, import, guide and mobile responsiveness.');
 }catch(error){await page.screenshot({path:path.join(artifacts,'categories-dashboard-failure.png'),fullPage:true}).catch(()=>{});console.error('Category browser failure URL:',page.url());throw error;}finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
