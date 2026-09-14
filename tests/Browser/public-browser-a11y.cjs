const { createRequire } = require('node:module');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const loadPlaywright = createRequire(path.join(process.env.VIDEO_BROWSER_MODULES, 'package.json'));
const { chromium } = loadPlaywright('playwright');

const publicRoutes = [
    '/',
    '/shop',
    '/collections',
    '/new-arrivals',
    '/corporate-orders',
    '/bulk-orders',
    '/franchise',
    '/be-a-store-owner',
    '/quality',
    '/careers',
    '/global-network',
    '/contact',
    '/virtual-tryon',
    '/irish-traditional',
    '/irish-heritage',
    '/cart',
    '/factory',
];

const sharedRoutes = publicRoutes.filter(route => route !== '/factory');
const focusableSelector = 'a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])';

function normalise(value) {
    return (value || '').replace(/\s+/g, ' ').trim();
}

async function inspectPublicPage(page) {
    return page.evaluate(selector => {
        const normalise = value => (value || '').replace(/\s+/g, ' ').trim();
        const visible = element => {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && rect.width > 0
                && rect.height > 0;
        };
        const accessibleName = element => {
            if (!element) return '';

            const aria = element.getAttribute('aria-label');
            if (aria) return normalise(aria);

            const labelledBy = (element.getAttribute('aria-labelledby') || '')
                .split(/\s+/)
                .filter(Boolean)
                .map(id => document.getElementById(id)?.innerText || '')
                .join(' ');
            if (normalise(labelledBy)) return normalise(labelledBy);

            if (element.labels?.length) {
                const labelled = [...element.labels].map(label => label.innerText).join(' ');
                if (normalise(labelled)) return normalise(labelled);
            }

            const parentLabel = element.closest('label')?.innerText || '';
            if (normalise(parentLabel)) return normalise(parentLabel);

            if (element.tagName === 'A') {
                const imageAlt = [...element.querySelectorAll('img[alt]')]
                    .map(image => image.alt)
                    .join(' ');
                if (normalise(imageAlt)) return normalise(imageAlt);
            }

            if (element.tagName === 'IMG') return normalise(element.alt);
            if (element.tagName === 'INPUT' && ['submit', 'button', 'reset', 'image'].includes(element.type)) {
                return normalise(element.value || element.alt);
            }

            return normalise(element.innerText || element.textContent || '');
        };

        const controls = [...document.querySelectorAll('button,input,select,textarea,[role="button"]')]
            .filter(element => visible(element))
            .filter(element => !element.disabled)
            .filter(element => !(element.tagName === 'INPUT' && element.type === 'hidden'));
        const links = [...document.links].filter(visible);
        const scrollWidth = Math.max(document.documentElement.scrollWidth, document.body?.scrollWidth || 0);

        return {
            shell: document.documentElement.getAttribute('data-public-shell') || 'structured',
            lang: document.documentElement.lang,
            title: document.title,
            description: document.querySelector('meta[name="description"]')?.content || '',
            canonical: document.querySelector('link[rel="canonical"]')?.href || '',
            h1Count: document.querySelectorAll('h1').length,
            unnamedControls: controls.filter(element => !accessibleName(element)).map(element => element.outerHTML.slice(0, 260)),
            unnamedLinks: links.filter(element => !accessibleName(element)).map(element => element.outerHTML.slice(0, 260)),
            missingAlt: [...document.images]
                .filter(image => !image.hasAttribute('alt'))
                .map(image => image.outerHTML.slice(0, 260)),
            placeholderLinks: [...document.querySelectorAll('a[href="#"]')]
                .filter(visible)
                .map(link => link.outerHTML.slice(0, 260)),
            directAssetImages: document.querySelectorAll('img[src^="/assets/"]').length,
            referenceRuntime: document.querySelectorAll('[data-approved-reference]').length,
            overflow: scrollWidth > document.documentElement.clientWidth + 1,
            hasContactHeaderLink: Boolean(document.querySelector('[data-public-shell-region="header"] a[href$="/contact"]')),
            bodyError: /\b(server error|exception|syntax error|undefined variable)\b/i.test(document.body.innerText),
            focusableCount: document.querySelectorAll(selector).length,
        };
    }, focusableSelector);
}

async function inspectFocusedElement(page) {
    return page.evaluate(() => {
        const normalise = value => (value || '').replace(/\s+/g, ' ').trim();
        const element = document.activeElement;
        if (!element || element === document.body || element === document.documentElement) {
            return { body: true };
        }

        const labelledBy = (element.getAttribute('aria-labelledby') || '')
            .split(/\s+/)
            .filter(Boolean)
            .map(id => document.getElementById(id)?.innerText || '')
            .join(' ');
        const labelled = element.labels?.length
            ? [...element.labels].map(label => label.innerText).join(' ')
            : '';
        const parentLabel = element.closest('label')?.innerText || '';
        const imageAlt = [...(element.querySelectorAll?.('img[alt]') || [])]
            .map(image => image.alt)
            .join(' ');
        const name = element.getAttribute('aria-label')
            || labelledBy
            || labelled
            || parentLabel
            || element.getAttribute('alt')
            || imageAlt
            || element.innerText
            || element.textContent
            || element.value
            || '';
        const rect = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        const proxy = element.nextElementSibling;
        const proxyRect = proxy?.getBoundingClientRect();
        const proxyStyle = proxy ? getComputedStyle(proxy) : null;
        const ownVisible = rect.width > 0
            && rect.height > 0
            && style.display !== 'none'
            && style.visibility !== 'hidden'
            && style.opacity !== '0';
        const proxyVisible = Boolean(proxyRect
            && proxyRect.width > 0
            && proxyRect.height > 0
            && proxyStyle?.display !== 'none'
            && proxyStyle?.visibility !== 'hidden');
        const focusable = [...document.querySelectorAll('a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])')];
        const className = typeof element.className === 'string' ? element.className : '';
        const proxyClassName = typeof proxy?.className === 'string' ? proxy.className : '';

        return {
            body: false,
            domIndex: focusable.indexOf(element),
            name: normalise(name),
            tag: element.tagName,
            type: element.getAttribute('type') || '',
            id: element.id || '',
            className,
            outerHTML: element.outerHTML?.slice(0, 500) || '',
            visible: ownVisible || proxyVisible,
            focusVisible: element.matches(':focus-visible'),
            indicator: (style.outlineStyle !== 'none' && style.outlineWidth !== '0px')
                || style.boxShadow !== 'none'
                || proxyStyle?.boxShadow !== 'none',
            outlineStyle: style.outlineStyle,
            outlineWidth: style.outlineWidth,
            outlineColor: style.outlineColor,
            boxShadow: style.boxShadow,
            proxyTag: proxy?.tagName || '',
            proxyClassName,
            proxyBoxShadow: proxyStyle?.boxShadow || '',
            bodyClassName: document.body.className,
            ariaHidden: Boolean(element.closest('[aria-hidden="true"]')),
        };
    });
}

async function runKeyboardAudit(page, route, viewport) {
    await page.goto(route, {waitUntil: 'domcontentloaded'});
    const focusableCount = await page.locator(focusableSelector).count();
    const visited = new Set();
    let stops = 0;

    for (let index = 0; index < focusableCount + 3; index += 1) {
        await page.keyboard.press('Tab');
        const focused = await inspectFocusedElement(page);

        if (focused.body) break;

        stops += 1;
        assert.equal(focused.visible, true, route + ' ' + viewport + ' focus should be visible');
        assert.ok(focused.name, route + ' ' + viewport + ' focus should have an accessible name');
        assert.equal(focused.focusVisible, true, route + ' ' + viewport + ' should expose :focus-visible');
        assert.equal(
            focused.indicator,
            true,
            route + ' ' + viewport + ' should expose a visible focus indicator; focused=' + JSON.stringify(focused),
        );
        assert.equal(focused.ariaHidden, false, route + ' ' + viewport + ' should not focus aria-hidden content');

        if (visited.has(focused.domIndex)) break;
        visited.add(focused.domIndex);
    }

    assert.ok(visited.size > 0, route + ' ' + viewport + ' should expose at least one keyboard focus stop');

    return {stops, uniqueStops: visited.size};
}

async function assertPublicContract(page, url, viewport) {
    const route = new URL(url).pathname;
    const response = await page.goto(url, {waitUntil: 'domcontentloaded'});
    assert.ok(response, route + ' should return a response');
    assert.equal(response.status(), 200, route + ' should return HTTP 200');

    const audit = await inspectPublicPage(page);
    assert.ok(audit.lang, route + ' should declare a document language');
    assert.match(audit.title, /Emerald Rozalia/i, route + ' should have a document title');
    assert.ok(audit.description, route + ' should have a meta description');
    assert.equal(audit.h1Count, 1, route + ' should have exactly one H1');
    assert.deepEqual(audit.missingAlt, [], route + ' images should have alt attributes');
    assert.deepEqual(audit.unnamedControls, [], route + ' controls should have accessible names');
    assert.deepEqual(audit.unnamedLinks, [], route + ' links should have accessible names');
    assert.deepEqual(audit.placeholderLinks, [], route + ' should not expose placeholder links');
    assert.equal(audit.directAssetImages, 0, route + ' should not expose storage asset paths');
    assert.equal(audit.referenceRuntime, 0, route + ' should not render guide reference runtime');
    assert.equal(audit.overflow, false, route + ' ' + viewport + ' should not overflow horizontally');
    assert.equal(audit.bodyError, false, route + ' should not render an application error');

    if (sharedRoutes.includes(route)) {
        assert.equal(audit.shell, 'shared', route + ' should use the shared public shell');
        assert.ok(audit.canonical, route + ' should have a canonical URL');
        assert.equal(audit.hasContactHeaderLink, true, route + ' should expose Contact Us in the header');
    }

    return audit;
}

async function runPublicBrowserEvidence() {
    const base = process.env.PUBLIC_BROWSER_BASE_URL || 'http://127.0.0.1:8000';
    const artifacts = process.env.VIDEO_ARTIFACTS || path.join(process.cwd(), 'browser-artifacts');
    const pageErrors = [];
    const manifest = {
        commit: process.env.GITHUB_SHA || null,
        base,
        generatedAt: new Date().toISOString(),
        routes: [],
        keyboard: [],
        reducedMotion: [],
    };

    fs.mkdirSync(artifacts, {recursive: true});

    const browser = await chromium.launch({headless: true});
    const context = await browser.newContext({
        colorScheme: 'dark',
        locale: 'en-IE',
        timezoneId: 'Europe/Dublin',
        viewport: {width: 1440, height: 1000},
    });
    const page = await context.newPage();
    page.setDefaultTimeout(20000);
    page.on('pageerror', error => pageErrors.push(error.message));

    try {
        for (const route of publicRoutes) {
            const audit = await assertPublicContract(page, base + route, 'desktop');
            await page.screenshot({
                path: path.join(artifacts, 'public-' + (route === '/' ? 'home' : route.slice(1).replaceAll('/', '-')) + '-desktop.png'),
                fullPage: true,
            });
            manifest.routes.push({route, viewport: 'desktop', ...audit});
        }

        await page.setViewportSize({width: 390, height: 844});
        for (const route of publicRoutes) {
            const audit = await assertPublicContract(page, base + route, 'mobile');
            await page.screenshot({
                path: path.join(artifacts, 'public-' + (route === '/' ? 'home' : route.slice(1).replaceAll('/', '-')) + '-mobile.png'),
                fullPage: true,
            });
            manifest.routes.push({route, viewport: 'mobile', ...audit});
        }

        for (const viewport of [{name: 'desktop', width: 1440, height: 1000}, {name: 'mobile', width: 390, height: 844}]) {
            await page.setViewportSize({width: viewport.width, height: viewport.height});
            for (const route of publicRoutes) {
                const keyboard = await runKeyboardAudit(page, base + route, viewport.name);
                manifest.keyboard.push({route, viewport: viewport.name, ...keyboard});
            }
        }

        const reducedPage = await context.newPage();
        reducedPage.on('pageerror', error => pageErrors.push(error.message));
        await reducedPage.emulateMedia({reducedMotion: 'reduce'});
        for (const route of publicRoutes) {
            await reducedPage.goto(base + route, {waitUntil: 'domcontentloaded'});
            const motion = await reducedPage.evaluate(() => [...document.querySelectorAll('a,button,summary,[role="button"]')]
                .slice(0, 30)
                .map(element => {
                    const style = getComputedStyle(element);
                    return {
                        transitionDuration: style.transitionDuration,
                        animationName: style.animationName,
                    };
                }));
            assert.equal(
                motion.every(item => item.transitionDuration === '0s' && item.animationName === 'none'),
                true,
                route + ' should disable motion when prefers-reduced-motion is requested',
            );
            manifest.reducedMotion.push({route, checked: motion.length});
        }

        assert.deepEqual(pageErrors, [], 'public routes should not emit browser JavaScript errors');
        fs.writeFileSync(
            path.join(artifacts, 'public-browser-a11y-manifest.json'),
            JSON.stringify(manifest, null, 2) + '\n',
        );
        console.log('Public browser evidence passed: 17 routes, desktop/mobile structure and screenshots, keyboard focus, reduced motion, and JavaScript error checks.');
    } catch (error) {
        await page.screenshot({path: path.join(artifacts, 'public-browser-a11y-failure.png'), fullPage: true}).catch(() => {});
        throw error;
    } finally {
        await browser.close();
    }
}

module.exports = {runPublicBrowserEvidence};

if (require.main === module) {
    runPublicBrowserEvidence().catch(error => {
        console.error(error);
        process.exitCode = 1;
    });
}
