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

        await page.goto(base+'/admin/resource/virtual-try-on');
        await page.locator('[data-tryon-dashboard]').waitFor();
        for (const label of ['Virtual Try-On','Try-On Summary','Try-Ons by Device','Quick Actions','UUID Traceability','Realistic Experience']) {
            await page.getByText(label,{exact:false}).first().waitFor();
        }
        const row=page.locator('tbody tr').filter({hasText:'BROWSER-TRYON-001'}).first();
        await row.waitFor();
        assert.match(await row.innerText(),/Browser Try-On Cap/);
        assert.match(await row.innerText(),/Published/);
        assert.match(await row.innerText(),/Unisex/);

        const preview=page.locator('.to-preview-card img').first();
        await preview.waitFor();
        assert.match(await preview.getAttribute('src'),/\/try-on\/[0-9a-f-]+\/assets\/preview/i);

        await page.locator('[data-audit]').last().click();
        await page.locator('#to-audit[open]').waitFor();
        const audit=page.locator('#to-audit [data-audit-content]');
        await audit.filter({hasText:/UUID:/i}).waitFor();
        assert.match(await audit.innerText(),/^UUID: [0-9a-f-]+/i);
        await page.locator('#to-audit [data-close]').click();

        const launchHref=await row.locator('a[href*="/virtual-tryon?"]').first().getAttribute('href');
        assert.ok(launchHref,'Published asset has a storefront launch link');
        const visitor=await browser.newContext({viewport:{width:1280,height:900}});
        const studio=await visitor.newPage();
        const studioErrors=[];
        studio.on('pageerror',error=>studioErrors.push(error.message));
        await studio.goto(new URL(launchHref,base).toString());
        await studio.locator('[data-try-studio]').waitFor();
        const selector=studio.locator('[data-try-product-select]');
        assert.match(await selector.locator('option:checked').innerText(),/Browser Try-On Cap/);
        const overlay=studio.locator('[data-hat-overlay]');
        await overlay.waitFor();
        const overlaySrc=await overlay.getAttribute('src');
        assert.match(overlaySrc,/\/try-on\/[0-9a-f-]+\/assets\/preview/i,'Storefront uses managed published try-on asset');
        const facePng=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR4nGP8z8DAwMDAxMDAwMAAAA0AAWgmWQ0AAAAASUVORK5CYII=','base64');
        await studio.locator('[data-face-upload]').setInputFiles({name:'face.png',mimeType:'image/png',buffer:facePng});
        await studio.locator('[data-face-preview]:not([hidden])').waitFor();
        await studio.locator('[data-hat-size]').fill('100');
        assert.equal(await overlay.evaluate(el=>el.style.width),'100%');
        assert.deepEqual(studioErrors,[],'Storefront try-on has no browser JavaScript errors');
        await studio.screenshot({path:path.join(artifacts,'tryon-storefront-desktop.png'),fullPage:true});
        await visitor.close();

        await page.screenshot({path:path.join(artifacts,'tryon-dashboard-desktop.png'),fullPage:true});
        await page.locator('[data-create]').first().click();
        await page.locator('#to-create[open]').waitFor();
        await page.locator('#to-create [name=title]').fill('Browser Try-On Draft');
        assert.ok(await page.locator('#to-create [name=product_id]').isVisible(),'Create dialog exposes product selection');
        assert.ok(await page.locator('#to-create [name=asset]').isVisible(),'Create dialog exposes try-on upload');
        await page.locator('#to-create [data-close]').click();

        await page.locator('.sd-filters [name=q]').fill('Browser Try-On Cap');
        await Promise.all([page.waitForURL(/q=/),page.locator('.sd-filters .sd-button').click()]);
        await page.locator('tbody tr').filter({hasText:'BROWSER-TRYON-001'}).first().waitFor();

        await page.setViewportSize({width:390,height:844});
        await page.screenshot({path:path.join(artifacts,'tryon-dashboard-mobile.png'),fullPage:true});
        const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1);
        assert.equal(overflow,false,'Virtual Try-On dashboard must not overflow the mobile viewport');
        assert.deepEqual(errors,[],'No admin browser JavaScript errors');
        console.log('Virtual Try-On browser checks passed: mockup sections, data, preview, audit, storefront launch, managed overlay, fitting controls, create dialog, filtering and mobile responsiveness.');
    } catch (error) {
        await page.screenshot({path:path.join(artifacts,'tryon-dashboard-failure.png'),fullPage:true}).catch(()=>{});
        console.error('Try-On browser failure URL:',page.url());
        throw error;
    } finally {
        await browser.close();
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
