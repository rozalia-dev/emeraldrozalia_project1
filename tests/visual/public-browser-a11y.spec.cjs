const { test, expect } = require('@playwright/test');

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

const sharedRoutes = publicRoutes.filter((route) => route !== '/factory');
const focusableSelector = 'a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])';

async function inspectPublicPage(page) {
  return page.evaluate((selector) => {
    const normalise = (value) => (value || '').replace(/\s+/g, ' ').trim();
    const isVisible = (element) => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();

      return style.display !== 'none'
        && style.visibility !== 'hidden'
        && rect.width > 0
        && rect.height > 0;
    };
    const accessibleName = (element) => {
      const ariaLabel = element.getAttribute('aria-label');
      if (ariaLabel) return normalise(ariaLabel);

      const labelledBy = (element.getAttribute('aria-labelledby') || '')
        .split(/\s+/)
        .filter(Boolean)
        .map((id) => document.getElementById(id)?.innerText || '')
        .join(' ');
      if (normalise(labelledBy)) return normalise(labelledBy);

      if (element.labels?.length) {
        const labelText = [...element.labels].map((label) => label.innerText).join(' ');
        if (normalise(labelText)) return normalise(labelText);
      }

      const parentLabel = element.closest('label');
      if (parentLabel && normalise(parentLabel.innerText)) return normalise(parentLabel.innerText);

      if (element.tagName === 'A') {
        const imageAlt = [...element.querySelectorAll('img[alt]')].map((image) => image.alt).join(' ');
        if (normalise(imageAlt)) return normalise(imageAlt);
      }

      if (element.tagName === 'IMG') return normalise(element.alt);
      if (element.tagName === 'INPUT' && ['submit', 'button', 'reset', 'image'].includes(element.type)) {
        return normalise(element.value || element.alt);
      }

      return normalise(element.innerText || element.textContent || '');
    };

    const visibleControls = [...document.querySelectorAll('button,input,select,textarea,[role="button"]')]
      .filter((element) => isVisible(element))
      .filter((element) => !element.disabled)
      .filter((element) => !(element.tagName === 'INPUT' && element.type === 'hidden'));

    const visibleLinks = [...document.links].filter(isVisible);
    const unnamedControls = visibleControls
      .filter((element) => !accessibleName(element))
      .map((element) => element.outerHTML.slice(0, 260));
    const unnamedLinks = visibleLinks
      .filter((element) => !accessibleName(element))
      .map((element) => element.outerHTML.slice(0, 260));
    const scrollWidth = Math.max(document.documentElement.scrollWidth, document.body?.scrollWidth || 0);
    const clientWidth = document.documentElement.clientWidth;

    return {
      route: location.pathname,
      shell: document.documentElement.getAttribute('data-public-shell') || 'structured',
      lang: document.documentElement.lang,
      title: document.title,
      description: document.querySelector('meta[name="description"]')?.content || '',
      canonical: document.querySelector('link[rel="canonical"]')?.href || '',
      h1Count: document.querySelectorAll('h1').length,
      missingAlt: [...document.images]
        .filter((image) => !image.hasAttribute('alt'))
        .map((image) => image.outerHTML.slice(0, 260)),
      unnamedControls,
      unnamedLinks,
      placeholderLinks: [...document.querySelectorAll('a[href="#"]')]
        .filter(isVisible)
        .map((link) => link.outerHTML.slice(0, 260)),
      directAssetImages: document.querySelectorAll('img[src^="/assets/"]').length,
      referenceRuntime: document.querySelectorAll('[data-approved-reference]').length,
      overflow: scrollWidth > clientWidth + 1,
      hasContactHeaderLink: Boolean(document.querySelector('[data-public-shell-region="header"] a[href$="/contact"]')),
      bodyError: /\b(server error|exception|syntax error|undefined variable)\b/i.test(document.body.innerText),
    };
  }, focusableSelector);
}

async function inspectFocusedElement(page) {
  return page.evaluate(() => {
    const element = document.activeElement;
    if (!element || element === document.body || element === document.documentElement) {
      return { valid: false, reason: 'focus did not move to a page control' };
    }

    const rect = element.getBoundingClientRect();
    const style = getComputedStyle(element);
    const labelledBy = (element.getAttribute('aria-labelledby') || '')
      .split(/\s+/)
      .filter(Boolean)
      .map((id) => document.getElementById(id)?.innerText || '')
      .join(' ');
    const labelled = element.labels?.length
      ? [...element.labels].map((label) => label.innerText).join(' ')
      : '';
    const imageAlt = [...element.querySelectorAll?.('img[alt]') || []]
      .map((image) => image.alt)
      .join(' ');
    const accessibleName = element.getAttribute('aria-label')
      || labelledBy
      || labelled
      || element.getAttribute('alt')
      || imageAlt
      || element.innerText
      || element.textContent
      || element.value
      || '';
    const focusableElements = [...document.querySelectorAll('a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])')];

    return {
      valid: true,
      domIndex: focusableElements.indexOf(element),
      visible: rect.width > 0 && rect.height > 0
        && style.display !== 'none'
        && style.visibility !== 'hidden',
      name: accessibleName.replace(/\s+/g, ' ').trim(),
      focusVisible: element.matches(':focus-visible'),
      indicator: style.outlineStyle !== 'none'
        && style.outlineWidth !== '0px'
        || style.boxShadow !== 'none',
      ariaHidden: Boolean(element.closest('[aria-hidden="true"]')),
    };
  });
}

test.describe('public browser and accessibility contract', () => {
  test('all public routes expose complete accessible structure', async ({ page }) => {
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    for (const route of publicRoutes) {
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });

      expect(response, route + ' should return a response').not.toBeNull();
      expect(response.status(), route + ' should return HTTP 200').toBe(200);

      const audit = await inspectPublicPage(page);
      expect(audit.lang, route + ' should declare a document language').toBeTruthy();
      expect(audit.title, route + ' should have a document title').toMatch(/Emerald Rozalia/i);
      expect(audit.description, route + ' should have a meta description').toBeTruthy();
      expect(audit.h1Count, route + ' should have exactly one H1').toBe(1);
      expect(audit.missingAlt, route + ' images should have alt attributes').toEqual([]);
      expect(audit.unnamedControls, route + ' controls should have accessible names').toEqual([]);
      expect(audit.unnamedLinks, route + ' links should have accessible names').toEqual([]);
      expect(audit.placeholderLinks, route + ' should not expose placeholder links').toEqual([]);
      expect(audit.directAssetImages, route + ' should not expose storage asset paths').toBe(0);
      expect(audit.referenceRuntime, route + ' should not render guide reference runtime').toBe(0);
      expect(audit.hiddenFocusable, route + ' should not contain hidden focusable controls').toBe(0);
      expect(audit.overflow, route + ' should not overflow horizontally').toBe(false);
      expect(audit.bodyError, route + ' should not render an application error').toBe(false);

      if (sharedRoutes.includes(route)) {
        expect(audit.shell, route + ' should use the shared public shell').toBe('shared');
        expect(audit.canonical, route + ' should have a canonical URL').toBeTruthy();
        expect(audit.hasContactHeaderLink, route + ' should expose Contact Us in the header').toBe(true);
      }
    }

    expect(pageErrors, 'public routes should not emit page errors').toEqual([]);
  });

  test('all public routes are keyboard traversable with visible focus', async ({ page }) => {
    for (const route of publicRoutes) {
      await page.goto(route, { waitUntil: 'domcontentloaded' });

      const focusableCount = await page.locator(focusableSelector).count();
      const visited = new Set();

      for (let index = 0; index < focusableCount + 2; index += 1) {
        await page.keyboard.press('Tab');
        const focused = await inspectFocusedElement(page);

        expect(focused.valid, route + ' should move focus to a control').toBe(true);
        expect(focused.visible, route + ' should keep focus visible').toBe(true);
        expect(focused.name, route + ' focused controls should have names').toBeTruthy();
        expect(focused.focusVisible, route + ' should expose :focus-visible').toBe(true);
        expect(focused.indicator, route + ' should expose a visible focus indicator').toBe(true);
        expect(focused.ariaHidden, route + ' should not focus aria-hidden content').toBe(false);

        if (visited.has(focused.domIndex)) break;
        visited.add(focused.domIndex);
      }

      expect(visited.size, route + ' should expose at least one keyboard focus stop').toBeGreaterThan(0);
    }
  });

  test('public motion respects prefers-reduced-motion', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });

    for (const route of publicRoutes) {
      await page.goto(route, { waitUntil: 'domcontentloaded' });

      const motion = await page.evaluate(() => [...document.querySelectorAll('a,button,summary,[role="button"]')]
        .slice(0, 30)
        .map((element) => {
          const style = getComputedStyle(element);
          return {
            transitionDuration: style.transitionDuration,
            animationName: style.animationName,
          };
        }));

      expect(
        motion.every((item) => item.transitionDuration === '0s' && item.animationName === 'none'),
        route + ' should disable motion when requested',
      ).toBe(true);
    }
  });
});
