const {createRequire} = require('node:module');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = createRequire(path.join(process.env.VIDEO_BROWSER_MODULES, 'package.json'))('playwright');

(async () => {
    const browser = await chromium.launch({headless: true});
    const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.setDefaultTimeout(20000);
    const base = 'http://127.0.0.1:8000';
    const artifacts = process.env.VIDEO_ARTIFACTS;
    fs.mkdirSync(artifacts, {recursive: true});

    try {
        await page.goto(base + '/login');
        await page.locator('#login-email').fill(process.env.ADMIN_EMAIL);
        await page.locator('#login-password').fill(process.env.ADMIN_PASSWORD);
        await Promise.all([
            page.waitForURL(url => !url.pathname.endsWith('/login')),
            page.locator('.auth-login form[action$="/login"] button[type=submit]').click(),
        ]);

        await page.goto(base + '/admin/resource/reports');
        await page.locator('[data-reports-root]').waitFor();
        assert.equal(await page.locator('a[href="#"]').count(), 0, 'Report overview has no placeholder links');
        const metric = page.locator('.reports-metric').first();
        await metric.waitFor();
        assert.equal(await metric.getAttribute('role'), 'link', 'Report KPI cards expose keyboard link semantics');
        await metric.focus();
        await metric.press('Enter');
        await page.waitForURL(/\/admin\/(resource\/reports|users-system)\//);

        await page.goto(base + '/admin/resource/reports/custom');
        await page.locator('[data-reports-root]').waitFor();
        const filterRows = page.locator('.reports-filter-table tbody tr');
        const initialFilterRows = await filterRows.count();
        await page.locator('[data-report-add-filter]').click();
        assert.equal(await filterRows.count(), initialFilterRows + 1, 'Add Filter appends a real named filter row');
        assert.ok((await page.locator('.reports-filter-table tbody tr').last().locator('[name^="filters["]').count()) > 0);
        const sortRows = page.locator('[data-report-sort-row]');
        const initialSortRows = await sortRows.count();
        await page.locator('[data-report-add-sort]').click();
        assert.equal(await sortRows.count(), initialSortRows + 1, 'Add Sort appends a real named sort row');
        await page.locator('[data-report-add-calculated]').click();
        assert.equal(await page.locator('[name="calculated_fields[]"]').count(), 1, 'Calculated fields are editable and submitted');
        const templates = page.locator('[data-report-template]');
        if (await templates.count() > 0) {
            await templates.first().click();
            assert.notEqual(await page.locator('.reports-builder-form [name="name"]').inputValue(), '', 'Template action loads the builder name');
        } else {
            assert.ok((await page.locator('.reports-empty').allTextContents()).some(text => text.includes('No saved report templates recorded.')), 'Empty template state is explicit');
        }
        await page.screenshot({path: path.join(artifacts, 'cpanel-reports-custom-desktop.png'), fullPage: true});

        await page.goto(base + '/admin/resource/reports/history');
        await page.locator('[data-reports-root]').waitFor();
        for (const name of ['q', 'module', 'status', 'from', 'to']) {
            assert.equal(await page.locator(`.reports-history-filters [name="${name}"]`).count(), 1, `History exposes ${name} filter`);
        }
        assert.equal(await page.locator('a[href="#"]').count(), 0, 'Report history has no placeholder links');

        await page.goto(base + '/admin/resource/reports/roles');
        await page.locator('[data-reports-root]').waitFor();
        assert.ok((await page.locator('.reports-tabs a[href]').count()) >= 6, 'Role report tabs are real navigation links');
        assert.ok((await page.locator('.reports-inline-filter [name="q"]').count()) === 1, 'Role search is a submitted filter');
        assert.equal(await page.locator('a[href="#"]').count(), 0, 'Role report has no placeholder links');

        await page.goto(base + '/admin/resource/reports/scheduler');
        await page.locator('[data-reports-root]').waitFor();
        for (const name of ['name', 'report', 'frequency', 'time', 'recipients', 'format', 'delivery_methods[]']) {
            assert.ok((await page.locator(`#schedule-form [name="${name}"]`).count()) > 0, `Scheduler exposes ${name}`);
        }
        await page.locator('[data-report-add-recipient]').click();
        assert.equal(await page.locator('[name="recipient_extra[]"]').count(), 1, 'Additional recipients can be added');
        const recipientGroups = page.locator('[data-report-recipient-tab="groups"]');
        await recipientGroups.click();
        assert.equal(await page.locator('[data-report-recipient-input]').getAttribute('aria-label'), 'Email group recipients');
        assert.equal(await page.locator('a[href="#"]').count(), 0, 'Scheduler has no placeholder links');

        await page.goto(base + '/admin/resource/alerts-notifications');
        await page.locator('[data-cc-root]').waitFor();
        assert.ok((await page.locator('[data-cc-kpi]').count()) > 0, 'Communication KPIs are interactive links');
        assert.equal(await page.locator('a[href="#"]').count(), 0, 'Communication record page has no placeholder links');
        await page.locator('[data-cc-create]').first().click();
        await page.locator('[data-cc-dialog][open]').waitFor();
        await page.locator('[data-cc-dialog][open] [data-cc-close]').first().click();

        await page.goto(base + '/admin/resource/reviews-ratings');
        await page.locator('[data-review-source]').waitFor();
        assert.equal(await page.locator('a[href="#"]').count(), 0, 'Reviews page has no placeholder links');
        assert.ok((await page.locator('.rr-tabs a[href]').count()) >= 7, 'Review tabs are real navigation links');
        assert.ok((await page.locator('[data-rr-metric]').count()) >= 5, 'Review KPI cards are interactive links');
        assert.equal(await page.locator('#rr-bulk-form [name="status"]').count(), 1, 'Bulk review moderation has a submitted status action');
        assert.equal(await page.locator('.rr-settings-form [name="auto_approve_reviews"]').count(), 2, 'Review settings preserve unchecked values in the form');
        if (await page.locator('[data-rr-view]').count()) {
            await page.locator('[data-rr-view]').first().click();
            await page.locator('[data-rr-dialog][open]').waitFor();
            assert.notEqual(await page.locator('[data-rr-detail-title]').textContent(), 'Review details', 'Review detail dialog is record-backed');
            await page.locator('[data-rr-dialog][open] [data-rr-dialog-close]').first().click();
        }
        const importTab = page.locator('.rr-tabs a', {hasText: 'Import Reviews'});
        await importTab.click();
        await page.locator('[name="file"][accept*=".csv"]').waitFor();
        assert.ok((await page.locator('.rr-import-card code').count()) >= 3, 'Review import documents its required columns');
        const qaTab = page.locator('.rr-tabs a', {hasText: 'Customer Q&A'});
        assert.ok((await qaTab.getAttribute('href')).includes('/admin/resource/inbox?kind=questions'), 'Q&A opens the conversation question view');

        await page.setViewportSize({width: 390, height: 844});
        await page.goto(base + '/admin/resource/reports/custom');
        await page.locator('[data-reports-root]').waitFor();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1), false, 'Reports custom page has no mobile overflow');
        await page.screenshot({path: path.join(artifacts, 'cpanel-reports-custom-mobile.png'), fullPage: true});
        await page.goto(base + '/admin/resource/communication-center');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1), false, 'Communication overview has no mobile overflow');
        await page.screenshot({path: path.join(artifacts, 'cpanel-communication-mobile.png'), fullPage: true});
        assert.deepEqual(errors, [], 'cPanel interaction routes have no browser JavaScript errors');
        console.log('cPanel interaction browser checks passed: report KPI navigation, builder controls, history filters, role tabs/actions, scheduler fields, communication dialog, mobile layout and JavaScript error checks.');
    } catch (error) {
        await page.screenshot({path: path.join(artifacts, 'cpanel-interaction-failure.png'), fullPage: true}).catch(() => {});
        console.error('cPanel interaction failure URL:', page.url());
        throw error;
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
