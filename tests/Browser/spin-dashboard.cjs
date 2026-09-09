const {createRequire} = require('node:module');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = createRequire(path.join(process.env.VIDEO_BROWSER_MODULES,'package.json'))('playwright');

(async () => {
    const browser = await chromium.launch({headless:true});
    const context = await browser.newContext({viewport:{width:1915,height:1128}});
    const page = await context.newPage();
    const errors=[];
    page.on('pageerror',error=>errors.push(error.message));
    page.setDefaultTimeout(20000);
    const base='http://127.0.0.1:8000';
    const artifacts=process.env.VIDEO_ARTIFACTS;
    fs.mkdirSync(artifacts,{recursive:true});
    try {
        await page.goto(base+'/login');
        await page.locator('#login-email').fill(process.env.ADMIN_EMAIL);
        await page.locator('#login-password').fill(process.env.ADMIN_PASSWORD);
        await Promise.all([
            page.waitForURL(url=>!url.pathname.endsWith('/login')),
            page.locator('.auth-login form[action$="/login"] button[type=submit]').click(),
        ]);

        await page.goto(base+'/admin/resource/360-product-view');
        await page.locator('[data-spin-dashboard]').waitFor();
        for (const label of ['360° Product View','360° Summary','360° by Type','Quick Actions','UUID Traceability','Immersive Experience']) {
            await page.getByText(label,{exact:false}).first().waitFor();
        }
        const row=page.locator('tbody tr').filter({hasText:'BROWSER-SPIN-001'}).first();
        await row.waitFor();
        assert.match(await row.innerText(),/Browser Spin Cap/);
        assert.match(await row.innerText(),/Published/);
        await page.locator('.sd-preview-card [data-spin-widget]').waitFor();
        assert.equal(await page.locator('.sd-preview-card .sv-frame').innerText(),'1 / 2');
        await page.locator('.sd-preview-card [data-sv-next]').click();
        assert.equal(await page.locator('.sd-preview-card .sv-frame').innerText(),'2 / 2');

        await page.locator('[data-copy-uuid]').waitFor();
        await page.locator('[data-audit]').last().click();
        await page.locator('#sd-audit[open]').waitFor();
        const auditText=await page.locator('#sd-audit [data-audit-content]').innerText();
        assert.match(auditText,/^UUID: [0-9a-f-]+/i,'Audit endpoint returns the selected UUID');
        await page.locator('#sd-audit [data-close]').click();

        const publicHref=await page.locator('.sd-preview-card a[href*="/360/"]').getAttribute('href');
        assert.ok(publicHref,'Public 360 viewer link is available');
        const visitor=await browser.newContext();
        const publicPage=await visitor.newPage();
        await publicPage.goto(new URL(publicHref,base).toString());
        await publicPage.locator('[data-spin-widget]').waitFor();
        assert.equal((await visitor.request.get(base+'/360-sitemap.xml')).status(),200);

        await page.screenshot({path:path.join(artifacts,'360-dashboard-desktop.png'),fullPage:true});
        await page.locator('[data-create]').first().click();
        await page.locator('#sd-create[open]').waitFor();
        await page.locator('#sd-create [name=title]').fill('Browser UI Draft');
        assert.ok(await page.locator('#sd-create [name=product_id]').isVisible(),'Create dialog exposes product selection');
        await page.locator('#sd-create [data-close]').click();

        await page.locator('.sd-filters [name=q]').fill('Browser Spin Cap');
        await Promise.all([page.waitForURL(/q=/),page.locator('.sd-filters button[type=submit]').click()]);
        await page.locator('tbody tr').filter({hasText:'BROWSER-SPIN-001'}).first().waitFor();

        await page.setViewportSize({width:390,height:844});
        await page.screenshot({path:path.join(artifacts,'360-dashboard-mobile.png'),fullPage:true});
        const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1);
        assert.equal(overflow,false,'360 dashboard must not overflow the mobile viewport');
        assert.deepEqual(errors,[],'No browser JavaScript errors');
        await visitor.close();
        console.log('360 dashboard browser checks passed: layout, data, preview rotation, audit endpoint, public viewer, sitemap, create dialog, filtering and mobile responsiveness.');
    } catch (error) {
        await page.screenshot({path:path.join(artifacts,'360-dashboard-failure.png'),fullPage:true}).catch(()=>{});
        console.error('360 browser failure URL:',page.url());
        throw error;
    } finally {
        await browser.close();
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
