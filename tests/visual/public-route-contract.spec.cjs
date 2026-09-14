const { test, expect } = require('@playwright/test');

const sharedRoutes = [
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
];

const mediaStateRoutes = [
  '/collections',
  '/new-arrivals',
  '/corporate-orders',
  '/bulk-orders',
  '/franchise',
  '/be-a-store-owner',
  '/quality',
  '/factory',
];

const enquiryRoutes = [
  '/contact',
  '/corporate-orders',
  '/bulk-orders',
  '/franchise',
  '/be-a-store-owner',
  '/factory',
];

test.describe('public route interaction contract', () => {
  test('shared public routes load without placeholder links or storage media', async ({ page }) => {
    for (const route of sharedRoutes) {
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });

      expect(response, `${route} should return a response`).not.toBeNull();
      expect(response.status(), `${route} should return HTTP 200`).toBe(200);
      await expect(page).toHaveTitle(/Emerald Rozalia/i);
      await expect(page.locator('html[data-public-shell="shared"]')).toHaveCount(1);
      await expect(page.locator('a[href="#"]')).toHaveCount(0);
      await expect(page.locator('img[src^="/assets/"]')).toHaveCount(0);
      await expect(page.locator('[data-approved-reference]')).toHaveCount(0);
    }
  });

  test('public editorial routes expose an explicit media state', async ({ page }) => {
    for (const route of mediaStateRoutes) {
      await page.goto(route, { waitUntil: 'domcontentloaded' });

      await expect(page.locator('[data-public-media-state]')).toHaveCount(1);
      await expect(page.locator('img[src^="/assets/"]')).toHaveCount(0);
      await expect(page.locator('[data-approved-reference]')).toHaveCount(0);
    }
  });

  test('enquiry routes expose real POST forms with CSRF protection', async ({ page }) => {
    for (const route of enquiryRoutes) {
      await page.goto(route, { waitUntil: 'domcontentloaded' });

      const form = page.locator('form[action$="/inquiry"]').first();
      await expect(form, `${route} should expose an enquiry form`).toHaveCount(1);
      await expect(form).toHaveAttribute('method', /post/i);
      await expect(form.locator('input[name="_token"]')).toHaveCount(1);
    }
  });

  test('factory route is structured and has no screenshot hotspots', async ({ page }) => {
    const response = await page.goto('/factory', { waitUntil: 'domcontentloaded' });

    expect(response).not.toBeNull();
    expect(response.status()).toBe(200);
    await expect(page).toHaveTitle(/Emerald Rozalia/i);
    await expect(page.locator('[data-public-media-register="factory"]')).toHaveCount(1);
    await expect(page.locator('.factory-hotspot, [class*="factory-reference-hotspot"]')).toHaveCount(0);
    await expect(page.locator('img[src^="/assets/"]')).toHaveCount(0);
  });
});
